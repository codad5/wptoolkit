<?php

require_once __DIR__.'/src/Utils/Autoloader.php';

use Codad5\WPToolkit\Utils\Autoloader;

Autoloader::init([
    'Codad5\\WPToolkit\\' => __DIR__ . '/src/',
]);
