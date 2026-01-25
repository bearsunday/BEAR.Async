<?php

declare(strict_types=1);

chdir(dirname(__DIR__));
passthru('rm -rf ./var/tmp/*');

// Initialize SQLite database
$dbDir = dirname(__DIR__) . '/var/db';
if (! is_dir($dbDir)) {
    mkdir($dbDir, 0755, true);
}

$dbPath = $dbDir . '/blog.sqlite';
$sqlDir = dirname(__DIR__) . '/sql';

echo "Initializing database...\n";

$pdo = new PDO('sqlite:' . $dbPath);
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

// Load schema
$schema = file_get_contents($sqlDir . '/schema.sql');
// Remove SQL comments
$schema = preg_replace('/^--.*$/m', '', $schema);
// Convert MySQL syntax to SQLite
$schema = str_replace('AUTO_INCREMENT', 'AUTOINCREMENT', $schema);
$schema = str_replace('INT PRIMARY KEY AUTOINCREMENT', 'INTEGER PRIMARY KEY AUTOINCREMENT', $schema);
// Remove FOREIGN KEY constraints (with ON DELETE CASCADE etc.)
$schema = preg_replace('/,\s*FOREIGN KEY\s*\([^)]+\)\s*REFERENCES\s*\w+\s*\([^)]+\)[^,)]*/', '', $schema);
// Remove INDEX definitions
$schema = preg_replace('/,\s*INDEX\s+\w+\s*\([^)]+\)/', '', $schema);
$schema = str_replace('TIMESTAMP DEFAULT CURRENT_TIMESTAMP', 'DATETIME DEFAULT CURRENT_TIMESTAMP', $schema);
$schema = str_replace('TIMESTAMP NULL', 'DATETIME NULL', $schema);

foreach (explode(';', $schema) as $statement) {
    $statement = trim($statement);
    if ($statement !== '') {
        $pdo->exec($statement);
    }
}

// Load seed data
$seed = file_get_contents($sqlDir . '/seed.sql');
// Remove SQL comments
$seed = preg_replace('/^--.*$/m', '', $seed);
foreach (explode(';', $seed) as $statement) {
    $statement = trim($statement);
    if ($statement !== '') {
        $pdo->exec($statement);
    }
}

echo "Database initialized at: {$dbPath}\n";
