<?php

// Only require the autoloader if the class doesn't already exist
if (!class_exists(\Codad5\WPToolkit\Utils\Autoloader::class)) {
    require_once __DIR__ . '/legacy/Utils/Autoloader.php';

    \Codad5\WPToolkit\Utils\Autoloader::init([
        // 0.x lives in legacy/ during the 1.0 port (ADR-0001). New 1.0 code in src/ is loaded
        // by Composer or, from Phase 1 §1.5, by bootstrap/autoload.php.
        'Codad5\\WPToolkit\\' => __DIR__ . '/legacy/',
    ]);
}
