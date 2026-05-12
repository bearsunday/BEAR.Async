<?php

declare(strict_types=1);

use Koriym\EnvJson\EnvJson;

chdir(dirname(__DIR__));
passthru('rm -rf ./var/tmp/*');

require dirname(__DIR__) . '/vendor/autoload.php';

(new EnvJson())->load(dirname(__DIR__));

$dsn  = getenv('DB_DSN')  ?: 'sqlite:' . dirname(__DIR__) . '/var/db/blog.sqlite';
$user = getenv('DB_USER') ?: '';
$pass = getenv('DB_PASS') ?: '';

$sqlDir = dirname(__DIR__) . '/sql';
$isSqlite = str_starts_with($dsn, 'sqlite:');

echo "Initializing database ({$dsn})...\n";

if ($isSqlite) {
    $dbPath = substr($dsn, strlen('sqlite:'));
    $dbDir = dirname($dbPath);
    if (! is_dir($dbDir)) {
        mkdir($dbDir, 0755, true);
    }
    if (file_exists($dbPath)) {
        unlink($dbPath);
    }
}

$pdo = new PDO($dsn, $user, $pass);
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$schema = file_get_contents($sqlDir . '/schema.sql');
if ($schema === false) {
    throw new RuntimeException('Failed to read schema.sql');
}

$schema = preg_replace('/^--.*$/m', '', $schema);

if ($isSqlite) {
    $schema = str_replace('AUTO_INCREMENT', 'AUTOINCREMENT', $schema);
    $schema = str_replace('INT PRIMARY KEY AUTOINCREMENT', 'INTEGER PRIMARY KEY AUTOINCREMENT', $schema);
    $schema = preg_replace('/,\s*FOREIGN KEY\s*\([^)]+\)\s*REFERENCES\s*\w+\s*\([^)]+\)[^,)]*/', '', $schema);
    $schema = preg_replace('/,\s*INDEX\s+\w+\s*\([^)]+\)/', '', $schema);
    $schema = str_replace('TIMESTAMP DEFAULT CURRENT_TIMESTAMP', 'DATETIME DEFAULT CURRENT_TIMESTAMP', $schema);
    $schema = str_replace('TIMESTAMP NULL', 'DATETIME NULL', $schema);
} else {
    $pdo->exec('SET FOREIGN_KEY_CHECKS = 0');
    $tables = $pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN);
    foreach ($tables as $table) {
        $pdo->exec("DROP TABLE IF EXISTS `{$table}`");
    }
    $pdo->exec('SET FOREIGN_KEY_CHECKS = 1');
}

foreach (explode(';', $schema) as $statement) {
    $statement = trim($statement);
    if ($statement !== '') {
        $pdo->exec($statement);
    }
}

$seed = file_get_contents($sqlDir . '/seed.sql');
if ($seed === false) {
    throw new RuntimeException('Failed to read seed.sql');
}

$seed = preg_replace('/^--.*$/m', '', $seed);
foreach (explode(';', $seed) as $statement) {
    $statement = trim($statement);
    if ($statement !== '') {
        $pdo->exec($statement);
    }
}

echo "Database initialized.\n";
