<?php
/*
Plugin Name: WPToolkit Todo List
Plugin URI: https://codad5.me
Description: Simple Todo List plugin for testing WPToolkit framework
Version: 1.0.0
Author: Codad5
Author URI: https://codad5.me
*/

// Prevent direct access

if (!defined('ABSPATH')) {
	exit;
}

// Include WPToolkit
require_once __DIR__ . '/autoloader.php';

use Codad5\WPToolkit\Utils\Autoloader;

Autoloader::init([
	'Codad5\\SamplePlugins\\' => __DIR__ . '/sample-plugins/',
]);

use Codad5\SamplePlugins\PluginEngine;
use Codad5\SamplePlugins\Todo\WPToolkitTodoPlugin;



PluginEngine::runInstance(
	WPToolkitTodoPlugin::getInstance()
);
