#!/usr/bin/env bash
set -euo pipefail

usage() {
  cat <<'USAGE'
Usage: bash bin/steady-state-matrix.sh [all|parallel|swoole]

Runs the demo steady-state HTTP benchmark matrix from the host with Docker
Compose and writes TSV plus Markdown summary files.

Environment:
  BENCH_MATRIX_RUNS         Runs per configuration (default: 3)
  WRK_DURATION              wrk duration for each run (default: 30s)
  BENCH_MATRIX_CONNECTIONS  Space-separated connection counts (default: "1 4 16")
  BENCH_MATRIX_THREADS      Space-separated wrk thread counts (default: "1 2")
  BENCH_MATRIX_PDO_POOLS    Space-separated Swoole PDO pool sizes (default: "64 8")
  BENCH_MATRIX_OUT          TSV output path (default: build/steady-state-matrix.tsv)
  BENCH_MATRIX_SUMMARY      Markdown summary path (default: build/steady-state-matrix.md)
  BENCH_MATRIX_STATS_DELAY  Seconds before sampling docker stats (default: 5)
  PARALLEL_POOL_SIZE        ext-parallel runtime pool size per HTTP worker (default: 8)
USAGE
}

mode="${1:-all}"
case "$mode" in
  -h|--help)
    usage
    exit 0
    ;;
  all|parallel|swoole) ;;
  *)
    usage >&2
    exit 1
    ;;
esac

if ! command -v docker >/dev/null 2>&1; then
  echo "docker is required for the steady-state matrix" >&2
  exit 1
fi

root_dir="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$root_dir"

runs="${BENCH_MATRIX_RUNS:-3}"
duration="${WRK_DURATION:-30s}"
connections_list="${BENCH_MATRIX_CONNECTIONS:-1 4 16}"
threads_list="${BENCH_MATRIX_THREADS:-1 2}"
pdo_pools="${BENCH_MATRIX_PDO_POOLS:-64 8}"
parallel_pool_size="${PARALLEL_POOL_SIZE:-8}"
stats_delay="${BENCH_MATRIX_STATS_DELAY:-5}"
out_file="${BENCH_MATRIX_OUT:-build/steady-state-matrix.tsv}"
summary_file="${BENCH_MATRIX_SUMMARY:-build/steady-state-matrix.md}"

mkdir -p "$(dirname "$out_file")" "$(dirname "$summary_file")"
printf 'runtime\tconfig\tconnections\tthreads\trun\trps\tlatency_avg_ms\tsocket_timeouts\tservice_cpu\tservice_mem\tmysql_threads_connected\n' >"$out_file"

run_compose() {
  docker compose "$@"
}

ensure_services() {
  case "$mode" in
    all)
      run_compose up -d --wait parallel swoole
      ;;
    parallel)
      run_compose up -d --wait parallel
      ;;
    swoole)
      run_compose up -d --wait swoole
      ;;
  esac
}

sample_mysql_threads() {
  run_compose exec -T mysql mysql -uroot -proot -N -e "SHOW STATUS LIKE 'Threads_connected';" 2>/dev/null | awk '{print $2}'
}

sample_service_stats() {
  local service="$1"
  local container_id
  local stats

  container_id="$(run_compose ps -q "$service")"
  if [[ -z "$container_id" ]]; then
    printf '\t'
    return
  fi

  stats="$(docker stats --no-stream --format '{{.CPUPerc}}\t{{.MemUsage}}' "$container_id" 2>/dev/null || true)"
  if [[ -z "$stats" ]]; then
    printf '\t'
    return
  fi

  printf '%s' "$stats"
}

parse_metric() {
  local output_file="$1"
  php -r '
    $text = file_get_contents($argv[1]);
    $rps = "";
    $latencyMs = "";
    $timeouts = "0";

    if (preg_match("/Requests\/sec:\s+([0-9.]+)/", $text, $matches) === 1) {
        $rps = $matches[1];
    }

    if (preg_match("/Latency\s+([0-9.]+)(us|ms|s)/", $text, $matches) === 1) {
        $value = (float) $matches[1];
        $unit = $matches[2];
        $latencyMs = match ($unit) {
            "us" => $value / 1000,
            "s" => $value * 1000,
            default => $value,
        };
        $latencyMs = sprintf("%.3f", $latencyMs);
    }

    if (preg_match("/Socket errors:.*timeout\s+([0-9]+)/", $text, $matches) === 1) {
        $timeouts = $matches[1];
    }

    echo $rps . "\t" . $latencyMs . "\t" . $timeouts;
  ' "$output_file"
}

summarize() {
  php -r '
    $tsv = $argv[1];
    $summary = $argv[2];
    $rows = array_map(static fn($line) => str_getcsv($line, "\t", "\"", "\\"), file($tsv, FILE_IGNORE_NEW_LINES));
    $header = array_shift($rows);
    $groups = [];

    foreach ($rows as $row) {
        if (count($row) !== count($header)) {
            continue;
        }

        $record = array_combine($header, $row);
        $key = implode("|", [
            $record["runtime"],
            $record["config"],
            $record["connections"],
            $record["threads"],
        ]);
        $groups[$key][] = $record;
    }

    $median = static function (array $values): float {
        sort($values, SORT_NUMERIC);
        $count = count($values);
        if ($count === 0) {
            return 0.0;
        }

        $middle = intdiv($count, 2);
        if ($count % 2 === 1) {
            return (float) $values[$middle];
        }

        return ((float) $values[$middle - 1] + (float) $values[$middle]) / 2;
    };

    $markdown = [];
    $markdown[] = "| Runtime | Config | Connections | Threads | Runs | Median req/s | Median latency | Socket timeouts | Sample CPU | Sample memory | MySQL threads |";
    $markdown[] = "|---|---:|---:|---:|---:|---:|---:|---:|---:|---|---:|";

    foreach ($groups as $records) {
        $first = $records[0];
        $rps = array_map(static fn($record) => (float) $record["rps"], $records);
        $latency = array_map(static fn($record) => (float) $record["latency_avg_ms"], $records);
        $timeouts = array_sum(array_map(static fn($record) => (int) $record["socket_timeouts"], $records));
        $cpu = $records[(int) floor((count($records) - 1) / 2)]["service_cpu"];
        $mem = $records[(int) floor((count($records) - 1) / 2)]["service_mem"];
        $mysqlThreads = array_filter(array_map(static fn($record) => (int) $record["mysql_threads_connected"], $records));
        $mysqlMedian = $mysqlThreads === [] ? 0 : $median($mysqlThreads);

        $markdown[] = sprintf(
            "| %s | %s | %d | %d | %d | %.2f | %.2f ms | %d | %s | %s | %.0f |",
            $first["runtime"],
            $first["config"],
            (int) $first["connections"],
            (int) $first["threads"],
            count($records),
            $median($rps),
            $median($latency),
            $timeouts,
            $cpu,
            $mem,
            $mysqlMedian,
        );
    }

    file_put_contents($summary, implode(PHP_EOL, $markdown) . PHP_EOL);
  ' "$out_file" "$summary_file"
}

run_one() {
  local runtime="$1"
  local config="$2"
  local service="$3"
  local connections="$4"
  local threads="$5"
  local run="$6"
  shift 6

  local output
  local status_file
  output="$(mktemp)"
  status_file="$(mktemp)"

  echo "[$runtime $config] c=$connections t=$threads run=$run/$runs" >&2
  (
    set +e
    run_compose exec -T "$@" >"$output" 2>&1
    echo "$?" >"$status_file"
  ) &
  local bench_pid="$!"

  sleep "$stats_delay"
  local stats
  stats="$(sample_service_stats "$service")"
  local mysql_threads
  mysql_threads="$(sample_mysql_threads)"
  wait "$bench_pid"

  local status
  status="$(cat "$status_file")"
  if [[ "$status" != "0" ]]; then
    cat "$output" >&2
    rm -f "$output" "$status_file"
    exit "$status"
  fi

  local metrics
  metrics="$(parse_metric "$output")"
  local rps latency timeouts
  IFS=$'\t' read -r rps latency timeouts <<<"$metrics"
  local cpu mem
  IFS=$'\t' read -r cpu mem <<<"$stats"

  if [[ -z "$rps" || -z "$latency" ]]; then
    cat "$output" >&2
    rm -f "$output" "$status_file"
    echo "Failed to parse wrk output" >&2
    exit 1
  fi

  printf '%s\t%s\t%s\t%s\t%s\t%s\t%s\t%s\t%s\t%s\t%s\n' \
    "$runtime" "$config" "$connections" "$threads" "$run" "$rps" "$latency" "$timeouts" "${cpu:-}" "${mem:-}" "${mysql_threads:-}" >>"$out_file"
  rm -f "$output" "$status_file"
}

ensure_services

if [[ "$mode" == "all" || "$mode" == "parallel" ]]; then
  for connections in $connections_list; do
    for threads in $threads_list; do
      if (( threads > connections )); then
        echo "[ext-parallel] skipping c=$connections t=$threads because wrk requires connections >= threads" >&2
        continue
      fi

      for run in $(seq 1 "$runs"); do
        run_one "ext-parallel" "workers=${connections},pool=${parallel_pool_size}" "parallel" "$connections" "$threads" "$run" \
          -e WRK_DURATION="$duration" \
          -e WRK_CONNECTIONS="$connections" \
          -e WRK_THREADS="$threads" \
          -e BENCH_PARALLEL_WORKERS="$connections" \
          -e PARALLEL_POOL_SIZE="$parallel_pool_size" \
          parallel composer steady-state-parallel
      done
    done
  done
fi

if [[ "$mode" == "all" || "$mode" == "swoole" ]]; then
  for pdo_pool in $pdo_pools; do
    for connections in $connections_list; do
      for threads in $threads_list; do
        if (( threads > connections )); then
          echo "[Swoole pdo_pool=${pdo_pool}] skipping c=$connections t=$threads because wrk requires connections >= threads" >&2
          continue
        fi

        for run in $(seq 1 "$runs"); do
          run_one "Swoole" "pdo_pool=${pdo_pool}" "swoole" "$connections" "$threads" "$run" \
            -e WRK_DURATION="$duration" \
            -e WRK_CONNECTIONS="$connections" \
            -e WRK_THREADS="$threads" \
            -e PDO_POOL_SIZE="$pdo_pool" \
            swoole composer steady-state-swoole
        done
      done
    done
  done
fi

summarize

echo "Wrote $out_file"
echo "Wrote $summary_file"
