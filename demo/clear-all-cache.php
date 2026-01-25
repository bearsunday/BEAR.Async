<?php

declare(strict_types=1);

function deleteDir(string $dir): void
{
    if (!is_dir($dir)) {
        return;
    }
    $files = scandir($dir);
    foreach ($files as $file) {
        if ($file === '.' || $file === '..') {
            continue;
        }
        $path = $dir . '/' . $file;
        if (is_dir($path)) {
            deleteDir($path);
        } else {
            unlink($path);
        }
    }
    rmdir($dir);
}

// Clear var/tmp (compiled DI scripts)
$tmpDir = __DIR__ . '/var/tmp';
if (is_dir($tmpDir)) {
    $dirs = scandir($tmpDir);
    foreach ($dirs as $dir) {
        if ($dir === '.' || $dir === '..') {
            continue;
        }
        $path = $tmpDir . '/' . $dir;
        if (is_dir($path)) {
            deleteDir($path);
            echo "Cleared: {$path}\n";
        }
    }
}

// Clear var/cache (PSR-6 cache for injector)
$cacheDir = __DIR__ . '/var/cache';
if (is_dir($cacheDir)) {
    deleteDir($cacheDir);
    echo "Cleared: {$cacheDir}\n";
}

echo "All caches cleared.\n";
