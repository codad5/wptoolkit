<?php

/**
 * WPToolkit boot guard (ADR-0013, ADR-0020).
 *
 * Usage, in the main plugin file:
 *
 *     $guard = require __DIR__ . '/vendor/codad5/wptoolkit/bootstrap/guard.php';
 *     $guard(__FILE__, array('php' => '8.1', 'wp' => '6.4', 'toolkit' => '^1.0'), function () {
 *         require __DIR__ . '/src/boot.php';
 *     });
 *
 * Rules this file must keep:
 * - PHP 5.6 syntax only. It has to run, and fail politely, on servers too old for the library.
 * - No global names (functions, classes, constants). It returns a closure, so any number of
 *   plugins can require their own copy of this file without collisions.
 * - It loads no WPToolkit class before the checks pass, and it never relies on a WPToolkit class
 *   for the checks themselves: with unscoped installs another plugin's copy may be the one loaded,
 *   and its code can't be trusted to judge compatibility with this copy.
 *
 * Options ($needs):
 *   php, wp       minimum versions ('8.1', '6.4')
 *   extensions    required PHP extensions (array('mbstring'))
 *   plugins       plugins that must be active, as basenames (array('woocommerce/woocommerce.php'))
 *   toolkit       WPToolkit version constraint ('^1.0'): checked against the copy actually loaded
 *   name          display name (default: the plugin's folder name)
 *   autoload      'auto' (default: vendor/autoload.php if present, else the bundled standalone
 *                 loader), 'composer', 'standalone', a file path, or false
 *   rethrow       rethrow boot errors instead of containing them (default: WP_DEBUG on a
 *                 'local' or 'development' environment)
 *
 * Returns true when the plugin started, false when it was kept inert.
 *
 * @license GPL-2.0-or-later
 */

// Nothing outside the closure: `require` runs this file in the caller's scope, so even a variable
// here would leak into the plugin's main file.
return function ($pluginFile, $needs, $boot) {
    $copyDirectory = dirname(__DIR__);
    $needs = is_array($needs) ? $needs : array();
    $name = isset($needs['name']) ? (string) $needs['name'] : basename(dirname($pluginFile));
    $problems = array();

    // --- Version matching, self-contained (no WPToolkit class may be trusted here) -------------
    $normalize = function ($version) {
        $version = ltrim(trim((string) $version), 'v');
        $base = preg_replace('/[-+].*$/', '', $version);
        $suffix = substr($version, strlen($base));
        $parts = explode('.', $base);
        while (count($parts) < 3) {
            $parts[] = '0';
        }
        return array(implode('.', array_slice($parts, 0, 3)), $suffix, array_map('intval', explode('.', $base)));
    };

    $satisfies = function ($version, $constraint) use ($normalize) {
        $have = $normalize($version);
        foreach (explode('||', $constraint) as $alternative) {
            $all = true;
            foreach (preg_split('/[\s,]+/', trim($alternative), -1, PREG_SPLIT_NO_EMPTY) as $part) {
                if (!preg_match('/^(\^|~|>=|<=|>|<|=)?\s*v?([\d.]+)$/', $part, $m)) {
                    $all = false;
                    break;
                }
                $bound = $normalize($m[2]);
                $segments = $bound[2];
                $operator = $m[1] === '' ? '=' : $m[1];
                $atLeast = version_compare($have[0] . $have[1], $bound[0], '>=') || version_compare($have[0], $bound[0], '==');
                if ($operator === '^' || $operator === '~') {
                    if ($operator === '~' && count($segments) >= 3) {
                        $ceiling = $segments[0] . '.' . ($segments[1] + 1) . '.0';
                    } elseif ($operator === '^' && $segments[0] === 0 && count($segments) > 1) {
                        $ceiling = '0.' . ($segments[1] + 1) . '.0';
                    } else {
                        $ceiling = ($segments[0] + 1) . '.0.0';
                    }
                    $ok = $atLeast && version_compare($have[0], $ceiling, '<');
                } elseif ($operator === '>=') {
                    $ok = $atLeast;
                } elseif ($operator === '=') {
                    $ok = version_compare($have[0], $bound[0], '==');
                } else {
                    $ok = version_compare($have[0], $bound[0], $operator);
                }
                if (!$ok) {
                    $all = false;
                    break;
                }
            }
            if ($all) {
                return true;
            }
        }
        return false;
    };

    // --- Environment checks -----------------------------------------------------------------------
    if (isset($needs['php']) && version_compare(PHP_VERSION, $needs['php'], '<')) {
        $problems[] = array('php', $needs['php'], PHP_VERSION);
    }

    if (isset($needs['wp'])) {
        $wpVersion = isset($GLOBALS['wp_version']) ? (string) $GLOBALS['wp_version'] : '0';
        if (version_compare($wpVersion, $needs['wp'], '<')) {
            $problems[] = array('wp', $needs['wp'], $wpVersion);
        }
    }

    if (!empty($needs['extensions'])) {
        foreach ((array) $needs['extensions'] as $extension) {
            if (!extension_loaded($extension)) {
                $problems[] = array('extension', $extension, '');
            }
        }
    }

    if (!empty($needs['plugins']) && function_exists('get_option')) {
        $active = (array) get_option('active_plugins', array());
        $network = function_exists('get_site_option') ? (array) get_site_option('active_sitewide_plugins', array()) : array();
        foreach ((array) $needs['plugins'] as $required) {
            if (!in_array($required, $active, true) && !isset($network[$required])) {
                $problems[] = array('plugin', $required, '');
            }
        }
    }

    // --- Which WPToolkit copy is loaded? (ADR-0020) ----------------------------------------------
    // Read this copy's namespace and version from its own file, without loading the class.
    $ownFile = $copyDirectory . '/src/Foundation/Application.php';
    $ownSource = is_readable($ownFile) ? (string) file_get_contents($ownFile) : '';
    $ownNamespace = preg_match('/^namespace\s+(.+)\\\\Foundation;/m', $ownSource, $m) ? trim($m[1]) : '';
    $ownVersion = preg_match('/const\s+VERSION\s*=\s*\'([^\']+)\'/', $ownSource, $m) ? $m[1] : '';
    $loadedFrom = '';
    $loadedVersion = $ownVersion;

    $applicationClass = $ownNamespace . '\\Foundation\\Application';
    if ($ownNamespace !== '' && class_exists($applicationClass, false)) {
        $reflection = new ReflectionClass($applicationClass);
        $loadedFile = (string) $reflection->getFileName();
        if (realpath($loadedFile) !== realpath($ownFile)) {
            // Another copy with the same (unscoped) namespace won the race.
            $loadedFrom = dirname(dirname(dirname($loadedFile)));
            $loadedVersion = (string) constant($applicationClass . '::VERSION');
        }
    }

    if (isset($needs['toolkit']) && $loadedVersion !== '' && !$satisfies($loadedVersion, (string) $needs['toolkit'])) {
        $problems[] = array('toolkit', (string) $needs['toolkit'], $loadedVersion, $loadedFrom);
    }

    // --- Messages are built only when shown (after init), so translating can't fire early ---------
    $relative = function ($path) {
        $path = str_replace('\\', '/', $path);
        $root = defined('ABSPATH') ? str_replace('\\', '/', ABSPATH) : '';
        return ($root !== '' && strpos($path, $root) === 0) ? substr($path, strlen($root)) : $path;
    };

    $describe = function ($problem) use ($relative) {
        switch ($problem[0]) {
            case 'php':
                /* translators: 1: required PHP version, 2: current PHP version */
                return sprintf(__('PHP %1$s or newer is required; this site runs PHP %2$s.', 'wptoolkit'), $problem[1], $problem[2]);
            case 'wp':
                /* translators: 1: required WordPress version, 2: current WordPress version */
                return sprintf(__('WordPress %1$s or newer is required; this site runs %2$s.', 'wptoolkit'), $problem[1], $problem[2]);
            case 'extension':
                /* translators: %s: PHP extension name */
                return sprintf(__('The PHP extension "%s" is required but not installed.', 'wptoolkit'), $problem[1]);
            case 'plugin':
                /* translators: %s: plugin basename */
                return sprintf(__('The plugin "%s" must be active.', 'wptoolkit'), $problem[1]);
            case 'toolkit':
                if ($problem[3] !== '') {
                    /* translators: 1: required WPToolkit version, 2: loaded version, 3: path of the loaded copy */
                    $what = __('WPToolkit %1$s is required, but WPToolkit %2$s was already loaded from %3$s by another plugin or theme.', 'wptoolkit');
                    /* translators: %s: loaded WPToolkit version */
                    $fix = __('Deactivate that one, or use a version of this plugin built for WPToolkit %s.', 'wptoolkit')
                        . ' ' . __('Developers: ship a scoped copy of WPToolkit.', 'wptoolkit');
                    return sprintf($what, $problem[1], $problem[2], $relative($problem[3])) . ' ' . sprintf($fix, $problem[2]);
                }
                /* translators: 1: required WPToolkit version, 2: bundled version */
                return sprintf(__('WPToolkit %1$s is required, but this plugin bundles %2$s.', 'wptoolkit'), $problem[1], $problem[2]);
            case 'boot':
                return __('It failed while starting. The error has been written to the PHP error log.', 'wptoolkit');
            case 'autoload':
                /* translators: %s: path of the missing autoloader file */
                return sprintf(__('Its class loader is missing (%s). Reinstall the plugin.', 'wptoolkit'), $relative($problem[1]));
        }
        return '';
    };

    $html = function ($problems) use ($name, $describe) {
        $items = '';
        foreach ($problems as $problem) {
            $items .= '<li>' . esc_html($describe($problem)) . '</li>';
        }
        /* translators: %s: plugin name */
        $headline = sprintf(__('%s has been paused and is not running:', 'wptoolkit'), $name);
        return '<p><strong>' . esc_html($headline) . '</strong></p><ul>' . $items . '</ul>';
    };

    $stayInert = function ($problems) use ($pluginFile, $html, $describe, $name) {
        $notice = function () use ($problems, $html) {
            if (function_exists('current_user_can') && !current_user_can('activate_plugins')) {
                return;
            }
            echo '<div class="notice notice-error" role="alert">' . wp_kses_post($html($problems)) . '</div>';
        };
        add_action('admin_notices', $notice);
        add_action('network_admin_notices', $notice);

        // Activating in this state is refused, so WordPress never records the plugin as active.
        if (function_exists('register_activation_hook')) {
            register_activation_hook($pluginFile, function () use ($problems, $html) {
                wp_die(wp_kses_post($html($problems)), esc_html__('Plugin not activated', 'wptoolkit'), array('back_link' => true));
            });
        }

        if (defined('WP_CLI') && WP_CLI && class_exists('WP_CLI')) {
            add_action('init', function () use ($problems, $describe, $name) {
                foreach ($problems as $problem) {
                    call_user_func(array('WP_CLI', 'warning'), $name . ': ' . $describe($problem));
                }
            });
        }
    };

    if (!empty($problems)) {
        $stayInert($problems);
        return false;
    }

    // --- Load classes, then boot, containing anything that goes wrong ----------------------------
    $mode = array_key_exists('autoload', $needs) ? $needs['autoload'] : 'auto';
    $vendor = dirname($pluginFile) . '/vendor/autoload.php';
    $standalone = $copyDirectory . '/bootstrap/autoload.php';

    if (array_key_exists('rethrow', $needs)) {
        $rethrow = (bool) $needs['rethrow'];
    } else {
        $environment = function_exists('wp_get_environment_type') ? wp_get_environment_type() : 'production';
        $rethrow = defined('WP_DEBUG') && WP_DEBUG && in_array($environment, array('local', 'development'), true);
    }

    $contain = function ($error) use ($name, $stayInert, $rethrow) {
        error_log(sprintf(
            '[WPToolkit] %s could not start: %s: %s in %s:%d',
            $name,
            get_class($error),
            $error->getMessage(),
            $error->getFile(),
            $error->getLine()
        ));
        if ($rethrow) {
            throw $error;
        }
        $stayInert(array(array('boot')));
        return false;
    };

    if ($mode === 'auto') {
        $loader = is_readable($vendor) ? $vendor : $standalone;
    } elseif ($mode === 'composer') {
        $loader = $vendor;
    } elseif ($mode === 'standalone') {
        $loader = $standalone;
    } else {
        $loader = is_string($mode) ? $mode : '';
    }

    // require_once on a missing file is a fatal compile error that no try/catch can contain.
    if ($loader !== '' && !is_readable($loader)) {
        $stayInert(array(array('autoload', $loader)));
        return false;
    }

    try {
        if ($loader !== '') {
            require_once $loader;
        }

        call_user_func($boot);
        return true;
    } catch (\Throwable $error) {
        return $contain($error);
    } catch (\Exception $error) {
        return $contain($error);
    }
};
