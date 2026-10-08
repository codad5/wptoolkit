<?php
/**
 * Plugin Name: WPToolkit Todo example
 * Description: A todo list on WPToolkit 1.0 — an entity, its repository, an edit screen, list columns and a REST/Ajax API.
 * Version: 1.0.0
 * Requires PHP: 8.1
 * Requires at least: 6.4
 * Text Domain: wptk-todo
 * License: GPL-2.0-or-later
 */

if (!defined('ABSPATH')) {
    exit;
}

// The guard is PHP 5.6 syntax: on an old PHP or WordPress it shows a notice instead of a fatal error,
// and nothing below runs (ADR-0013). It also loads Composer's autoloader for this plugin.
call_user_func(require __DIR__ . '/vendor/codad5/wptoolkit/bootstrap/guard.php', __FILE__, array(
    'php' => '8.1',
    'wp' => '6.4',
    'toolkit' => '^1.0',
    'name' => 'WPToolkit Todo example',
), function () {
    \Codad5\WPToolkit\Foundation\Application::create(__FILE__, [
        'slug' => 'wptk-todo',
        'text_domain' => 'wptk-todo',
    ])
        ->providers([\WptkTodo\TodoServiceProvider::class])
        ->boot();
});
