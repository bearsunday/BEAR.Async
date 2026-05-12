<?php
$autoload = __DIR__ . '/vendor/autoload.php';
putenv('BEAR_ASYNC_AUTOLOAD=' . $autoload);

require $autoload;
