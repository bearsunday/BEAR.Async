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

$cacheDir = __DIR__ . '/var/tmp/prod-swoole-hal-app';
deleteDir($cacheDir);
echo "Cache cleared: {$cacheDir}\n";
