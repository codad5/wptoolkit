<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/vendor/autoload.php';

if (!defined('ABSPATH')) {
    define('ABSPATH', '/srv/wordpress/');
}
define('WP_PLUGIN_DIR', ABSPATH . 'wp-content/plugins');
define('WPMU_PLUGIN_DIR', ABSPATH . 'wp-content/mu-plugins');

require_once __DIR__ . '/Stubs/wordpress-classes.php';
