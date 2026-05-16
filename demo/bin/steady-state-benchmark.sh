#!/usr/bin/env bash
set -euo pipefail

usage() {
  cat <<'USAGE'
Usage: bash bin/steady-state-benchmark.sh [auto|parallel|swoole]

Runs wrk against a long-running HTTP benchmark server.

Environment:
  BENCH_HOST              Host to bind/connect (default: 127.0.0.1)
  BENCH_PATH              Request path/query (default: /dashboard?user_id=1)
  BENCH_USE_EXISTING      Use an already-running server when set to 1
  BENCH_PARALLEL_PORT     ext-parallel server port (default: 8081)
  BENCH_PARALLEL_WORKERS  ext-parallel HTTP worker processes (default: WRK_CONNECTIONS)
  BENCH_PARALLEL_SHARED_CACHE_WARMUP
                          Warm shared DI/cache before forking workers (default: 1)
  BENCH_PARALLEL_STARTUP_WARMUP
                          Warm each ext-parallel worker before wrk (default: 1)
  SWOOLE_PORT             Swoole server port (default: 8080)
  BENCH_PHP               PHP binary for benchmark servers (default: php)
  PARALLEL_PHP            PHP binary for ext-parallel server (default: BENCH_PHP)
  SWOOLE_PHP              PHP binary for Swoole server (default: BENCH_PHP)
  WRK_DURATION            wrk duration (default: 10s)
  WRK_THREADS             wrk threads (default: 1)
  WRK_CONNECTIONS         wrk connections (default: 1)
  BENCH_WARMUP_REQUESTS   Warmup HTTP requests before wrk (default: 1)
  BENCH_CLEAR_CACHE       Clear the target DI cache before starting a server (default: 1)
  PDO_POOL_SIZE           Swoole PDO pool size (default: 64)
  PARALLEL_POOL_SIZE      ext-parallel worker pool size (default: 8)
  DB_DSN                  Database DSN (default: mysql:host=mysql;dbname=demo)
  DB_USER                 Database user (default: demo)
  DB_PASS                 Database password (default: demo)
USAGE
}

mode="${1:-auto}"
if [[ "$mode" == "-h" || "$mode" == "--help" ]]; then
  usage
  exit 0
fi

case "$mode" in
  auto|parallel|swoole) ;;
  *)
    usage >&2
    exit 1
    ;;
esac

root_dir="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$root_dir"

host="${BENCH_HOST:-127.0.0.1}"
path="${BENCH_PATH:-/dashboard?user_id=1}"
parallel_port="${BENCH_PARALLEL_PORT:-8081}"
swoole_port="${SWOOLE_PORT:-8080}"
duration="${WRK_DURATION:-10s}"
threads="${WRK_THREADS:-1}"
connections="${WRK_CONNECTIONS:-1}"
parallel_workers="${BENCH_PARALLEL_WORKERS:-$connections}"
warmup_requests="${BENCH_WARMUP_REQUESTS:-1}"
clear_cache="${BENCH_CLEAR_CACHE:-1}"
use_existing="${BENCH_USE_EXISTING:-0}"
tmp_dir="${TMPDIR:-/tmp}"
bench_php="${BENCH_PHP:-php}"

if ! command -v wrk >/dev/null 2>&1; then
  cat >&2 <<'ERROR'
wrk is required for steady-state benchmarks.

If you are inside the demo Docker service, rebuild and recreate it so the
Dockerfile-installed wrk package is present:

  docker compose build parallel swoole
  docker compose up -d --wait --force-recreate parallel swoole

On a host machine, install wrk with your package manager.
ERROR
  exit 1
fi

has_extension() {
  local php_bin="$1"
  local extension="$2"

  "$php_bin" -r "exit(extension_loaded('$extension') ? 0 : 1);" >/dev/null 2>&1
}

has_active_xdebug() {
  local php_bin="$1"

  "$php_bin" -r '
    if (! extension_loaded("xdebug")) {
        exit(1);
    }

    $mode = getenv("XDEBUG_MODE");
    if ($mode !== false) {
        exit($mode !== "" && $mode !== "off" ? 0 : 1);
    }

    $iniMode = ini_get("xdebug.mode");
    if ($iniMode === false) {
        exit(0);
    }

    exit($iniMode !== "" && $iniMode !== "off" ? 0 : 1);
  ' >/dev/null 2>&1
}

wait_for_port() {
  local port="$1"
  local label="$2"
  local pid="${3:-}"

  for _ in $(seq 1 100); do
    if is_port_open "$port"; then
      return 0
    fi

    if [[ -n "$pid" ]] && ! kill -0 "$pid" >/dev/null 2>&1; then
      echo "$label process ($pid) exited before opening $host:$port" >&2
      return 1
    fi

    sleep 0.1
  done

  echo "$label did not become ready on $host:$port" >&2
  return 1
}

is_port_open() {
  local port="$1"

  php -r '
    $fp = @fsockopen($argv[1], (int) $argv[2], $errno, $errstr, 0.1);
    if ($fp) {
        fclose($fp);
        exit(0);
    }
    exit(1);
  ' "$host" "$port"
}

wait_for_parallel_workers() {
  local log_file="$1"
  local expected="$2"

  if [[ "$use_existing" == "1" || "$expected" -le 1 ]]; then
    return
  fi

  for _ in $(seq 1 300); do
    if [[ -f "$log_file" ]]; then
      local ready
      ready="$(grep -c 'parallel benchmark worker .* ready' "$log_file" || true)"
      if [[ "$ready" -ge "$expected" ]]; then
        return
      fi
    fi

    sleep 0.1
  done

  echo "ext-parallel benchmark workers did not become ready: expected $expected" >&2
  [[ -f "$log_file" ]] && cat "$log_file" >&2
  exit 1
}

ensure_port_free() {
  local port="$1"
  local label="$2"

  if ! is_port_open "$port"; then
    return
  fi

  cat >&2 <<ERROR
$label port is already accepting connections on $host:$port.
Stop the existing server, set BENCH_USE_EXISTING=1, or choose another port.
ERROR
  exit 1
}

prepare_server_env() {
  if [[ -z "${DB_DSN:-}" ]]; then
    export DB_DSN="mysql:host=mysql;dbname=demo"
  fi

  if [[ -z "${DB_USER:-}" ]]; then
    export DB_USER="demo"
  fi

  if [[ -z "${DB_PASS:-}" ]]; then
    export DB_PASS="demo"
  fi
}

clear_context_cache() {
  local context="$1"

  if [[ "$clear_cache" != "1" || "$use_existing" == "1" ]]; then
    return
  fi

  rm -rf "$root_dir/var/tmp/$context"
}

check_database() {
  local php_bin="$1"

  if "$php_bin" -r '
    $dsn = getenv("DB_DSN") ?: "";
    if ($dsn === "" || str_starts_with($dsn, "sqlite:")) {
        exit(0);
    }

    try {
        new PDO($dsn, getenv("DB_USER") ?: "", getenv("DB_PASS") ?: "");
        exit(0);
    } catch (Throwable $e) {
        fwrite(STDERR, "Database connection failed.\n");
        fwrite(STDERR, "DB_DSN: {$dsn}\n");
        fwrite(STDERR, $e->getMessage() . "\n");
        exit(1);
    }
  '; then
    return
  fi

  cat >&2 <<'ERROR'

The demo MySQL service is intentionally only exposed on the compose network.
Run this benchmark inside the matching compose service:

  docker compose up -d --wait mysql
  docker compose exec parallel composer steady-state-parallel

For host runs, set DB_DSN/DB_USER/DB_PASS to a database reachable from the host.
ERROR
  exit 1
}

pids=()
pgids=()
ran_any=0
cleanup() {
  if [[ "${#pids[@]}" -eq 0 ]]; then
    return
  fi

  for index in "${!pids[@]}"; do
    local pid="${pids[$index]}"
    local pgid="${pgids[$index]:-}"
    if [[ -n "$pgid" ]]; then
      kill -TERM -- "-$pgid" >/dev/null 2>&1 || true
    else
      kill "$pid" >/dev/null 2>&1 || true
    fi
  done

  for _ in $(seq 1 20); do
    local alive=0
    for pid in "${pids[@]}"; do
      if kill -0 "$pid" >/dev/null 2>&1; then
        alive=1
      fi
    done

    if [[ "$alive" -eq 0 ]]; then
      break
    fi

    sleep 0.1
  done

  for index in "${!pids[@]}"; do
    local pid="${pids[$index]}"
    local pgid="${pgids[$index]:-}"
    if ! kill -0 "$pid" >/dev/null 2>&1; then
      continue
    fi

    if [[ -n "$pgid" ]]; then
      kill -KILL -- "-$pgid" >/dev/null 2>&1 || true
    else
      kill -KILL "$pid" >/dev/null 2>&1 || true
    fi
  done

  for pid in "${pids[@]}"; do
    wait "$pid" >/dev/null 2>&1 || true
  done

  pids=()
  pgids=()
}
trap cleanup EXIT

start_managed_server() {
  local log_file="$1"
  shift

  if command -v setsid >/dev/null 2>&1; then
    setsid "$@" >"$log_file" 2>&1 &
    pids+=("$!")
    pgids+=("$!")

    return
  fi

  "$@" >"$log_file" 2>&1 &
  pids+=("$!")
  pgids+=("")
}

run_wrk() {
  local label="$1"
  local url="$2"

  echo
  echo "## $label"
  echo "URL: $url"
  echo "wrk: -t$threads -c$connections -d$duration"
  wrk -t"$threads" -c"$connections" -d"$duration" -H "Cache-Control: no-cache" "$url"
  ran_any=1
}

warm_up() {
  local url="$1"
  local label="$2"
  local log_file="${3:-}"

  if [[ "$warmup_requests" -lt 1 ]]; then
    return
  fi

  echo "Warming $label with $warmup_requests request(s)..."
  for _ in $(seq 1 "$warmup_requests"); do
    if ! php -r '
      $url = $argv[1];
      $context = stream_context_create([
          "http" => [
              "timeout" => 10,
              "ignore_errors" => true,
              "header" => "Cache-Control: no-cache\r\n",
          ],
      ]);
      $response = file_get_contents($url, false, $context);
      $headers = $http_response_header ?? [];
      $status = 0;
      foreach ($headers as $header) {
          if (preg_match("/^HTTP\/\S+\s+(\d+)/", $header, $matches) === 1) {
              $status = (int) $matches[1];
          }
      }

      if ($response === false || $status < 200 || $status >= 400) {
          fwrite(STDERR, "Warmup request failed: {$url}\n");
          fwrite(STDERR, "HTTP status: " . ($status ?: "unavailable") . "\n");
          if ($headers !== []) {
              fwrite(STDERR, "Response headers:\n" . implode("\n", $headers) . "\n");
          }
          if (is_string($response) && $response !== "") {
              fwrite(STDERR, "Response body:\n" . substr($response, 0, 4000) . "\n");
          }
          exit(1);
      }
    ' "$url"; then
      if [[ -n "$log_file" && -f "$log_file" ]]; then
        echo "Server log ($log_file):" >&2
        tail -n 120 "$log_file" >&2
      fi
      exit 1
    fi
  done
}

run_parallel() {
  local php_bin="${PARALLEL_PHP:-$bench_php}"
  local app_context="${APP_CONTEXT:-prod-hal-app}"

  if ! has_extension "$php_bin" parallel; then
    if [[ "$mode" == "parallel" ]]; then
      echo "ext-parallel is not loaded in this PHP runtime: $php_bin" >&2
      exit 1
    fi

    echo "Skipping ext-parallel: extension is not loaded."
    return 0
  fi

  local log_file="$tmp_dir/bear-async-parallel-server.log"
  local server_pid=""
  if [[ "$use_existing" != "1" ]]; then
    prepare_server_env
    check_database "$php_bin"
    ensure_port_free "$parallel_port" "ext-parallel benchmark server"
    clear_context_cache "$app_context"
    start_managed_server "$log_file" env APP_CONTEXT="$app_context" BENCH_HOST="$host" BENCH_PARALLEL_PORT="$parallel_port" BENCH_PARALLEL_WORKERS="$parallel_workers" "$php_bin" bin/parallel-server.php
    server_pid="${pids[-1]}"
  fi

  if ! wait_for_port "$parallel_port" "ext-parallel benchmark server" "$server_pid"; then
    [[ -f "$log_file" ]] && cat "$log_file" >&2
    exit 1
  fi
  wait_for_parallel_workers "$log_file" "$parallel_workers"

  local url="http://$host:$parallel_port$path"
  warm_up "$url" "ext-parallel" "$log_file"
  run_wrk "ext-parallel steady-state HTTP" "$url"
}

run_swoole() {
  local php_bin="${SWOOLE_PHP:-$bench_php}"
  local app_context="${APP_CONTEXT:-prod-swoole-hal-api-app}"

  if ! has_extension "$php_bin" swoole && ! has_extension "$php_bin" openswoole; then
    if [[ "$mode" == "swoole" ]]; then
      echo "ext-swoole or ext-openswoole is not loaded in this PHP runtime: $php_bin" >&2
      exit 1
    fi

    echo "Skipping Swoole: extension is not loaded."
    return 0
  fi

  if has_active_xdebug "$php_bin"; then
    cat >&2 <<ERROR
Xdebug is active in the Swoole PHP runtime: $php_bin
Swoole coroutines are not safe with active Xdebug.

Use a PHP runtime without active Xdebug, or run:

  XDEBUG_MODE=off WRK_DURATION=$duration WRK_CONNECTIONS=$connections WRK_THREADS=$threads composer steady-state-swoole

ERROR
    exit 1
  fi

  local log_file="$tmp_dir/bear-async-swoole-server.log"
  local pdo_pool_size="${PDO_POOL_SIZE:-64}"
  local server_pid=""

  if [[ "$use_existing" != "1" ]]; then
    prepare_server_env
    check_database "$php_bin"
    ensure_port_free "$swoole_port" "Swoole benchmark server"
    clear_context_cache "$app_context"
    start_managed_server "$log_file" env APP_CONTEXT="$app_context" PDO_POOL_SIZE="$pdo_pool_size" SWOOLE_HOST="$host" SWOOLE_PORT="$swoole_port" "$php_bin" bin/swoole.php
    server_pid="${pids[-1]}"
  fi

  if ! wait_for_port "$swoole_port" "Swoole benchmark server" "$server_pid"; then
    [[ -f "$log_file" ]] && cat "$log_file" >&2
    exit 1
  fi

  local url="http://$host:$swoole_port$path"
  warm_up "$url" "Swoole" "$log_file"
  run_wrk "Swoole steady-state HTTP" "$url"
}

case "$mode" in
  auto)
    run_parallel
    run_swoole
    ;;
  parallel)
    run_parallel
    ;;
  swoole)
    run_swoole
    ;;
esac

if [[ "$ran_any" -eq 0 ]]; then
  echo "No benchmark ran. Use the parallel or swoole demo container, or load the matching extension." >&2
  exit 1
fi
