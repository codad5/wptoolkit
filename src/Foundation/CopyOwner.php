<?php

/**
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

namespace Codad5\WPToolkit\Foundation;

/**
 * Works out which plugin, must-use plugin or theme a WPToolkit copy was loaded from, so a conflict
 * message can name it instead of leaving the administrator to decode a path.
 *
 * Call at render time (admin requests): it may read plugin headers.
 */
final class CopyOwner
{
    /**
     * @return array{type: 'plugin'|'mu-plugin'|'theme', name: string, directory: string}|null
     *         Null when the path isn't inside a plugin, must-use plugin or theme directory.
     */
    public static function of(string $path): ?array
    {
        $path = self::normalize($path);

        $roots = [
            'plugin' => defined('WP_PLUGIN_DIR') ? (string) constant('WP_PLUGIN_DIR') : null,
            'mu-plugin' => defined('WPMU_PLUGIN_DIR') ? (string) constant('WPMU_PLUGIN_DIR') : null,
            'theme' => function_exists('get_theme_root') ? get_theme_root() : null,
        ];

        foreach ($roots as $type => $root) {
            if ($root === null || $root === '') {
                continue;
            }

            $root = rtrim(self::normalize($root), '/') . '/';
            if (!str_starts_with($path, $root)) {
                continue;
            }

            $directory = explode('/', substr($path, strlen($root)))[0];
            if ($directory === '') {
                continue;
            }

            return [
                'type' => $type,
                'name' => self::displayName($type, $directory) ?? $directory,
                'directory' => $directory,
            ];
        }

        return null;
    }

    /**
     * @param 'plugin'|'mu-plugin'|'theme' $type
     */
    private static function displayName(string $type, string $directory): ?string
    {
        if ($type === 'theme') {
            if (!function_exists('wp_get_theme')) {
                return null;
            }
            $name = wp_get_theme($directory)->get('Name');
            return is_string($name) && $name !== '' ? $name : null;
        }

        if ($type === 'plugin') {
            if (!function_exists('get_plugins') && defined('ABSPATH')) {
                $file = constant('ABSPATH') . 'wp-admin/includes/plugin.php';
                if (is_readable($file)) {
                    require_once $file;
                }
            }
            if (function_exists('get_plugins')) {
                foreach (get_plugins('/' . $directory) as $data) {
                    if (!empty($data['Name'])) {
                        return (string) $data['Name'];
                    }
                }
            }
        }

        return null;
    }

    private static function normalize(string $path): string
    {
        return str_replace('\\', '/', $path);
    }
}
