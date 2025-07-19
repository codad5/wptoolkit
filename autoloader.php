<?php

// Only require the autoloader if the class doesn't already exist
if (!class_exists(\Codad5\WPToolkit\Utils\Autoloader::class)) {
    require_once __DIR__ . '/src/Utils/Autoloader.php';

    \Codad5\WPToolkit\Utils\Autoloader::init([
        'Codad5\\WPToolkit\\' => __DIR__ . '/src/',
    ]);
}
