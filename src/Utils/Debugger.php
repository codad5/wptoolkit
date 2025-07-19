<?php

/**
 * Debugger Helper
 *
 * @author Your Name <username@example.com>
 * @license GPL-2.0-or-later http://www.gnu.org/licenses/gpl-2.0.txt
 * @link https://github.com/szepeviktor/starter-plugin
 */

declare(strict_types=1);

namespace Codad5\WPToolkit\Utils;

/**
 * Debugger Helper class for development debugging and logging.
 *
 * Provides comprehensive debugging tools including console logging,
 * variable dumping, and WordPress-specific debugging features.
 * Supports multiple app instances with static methods.
 */
class Debugger
{
    /**
     * Console log types allowed.
     */
    const CONSOLE_TYPES = ['log', 'info', 'warn', 'error', 'debug'];

    /**
     * Maximum string length for console output.
     */
    const MAX_CONSOLE_LENGTH = 5000;

    /**
     * Registered debugger instances for different apps.
     *
     * @var array<string, array{
     *     is_dev: bool,
     *     script_handle: string,
     *     app_name: string,
     *     text_domain: string,
     *     hooks_registered: bool
     * }>
     */
    private static array $instances = [];

    /**
     * Initialize a debugger instance for a specific app.
     *
     * @param string $app_slug Application slug/identifier
     * @param bool|null $is_dev Whether to enable development mode
     * @param string|null $app_name Application name for display
     * @param string|null $text_domain Text domain for translations
     * @return bool Success status
     */
    public static function init(string $app_slug, ?bool $is_dev = null, ?string $app_name = null, ?string $text_domain = null): bool
    {
        $app_slug = sanitize_key($app_slug);

        if ($is_dev === null) {
            $is_dev = defined('WP_DEBUG') && WP_DEBUG;
        }

        $app_name = $app_name ?? ucfirst(str_replace(['-', '_'], ' ', $app_slug));
        $text_domain = $text_domain ?? $app_slug;

        self::$instances[$app_slug] = [
            'is_dev' => $is_dev,
            'script_handle' => $app_slug . '-debugger-console',
            'app_name' => $app_name,
            'text_domain' => $text_domain,
            'hooks_registered' => false,
        ];

        self::log($app_slug, 'Debugger initialized with is_dev = ' . ($is_dev ? 'true' : 'false'));

        if ($is_dev && !self::$instances[$app_slug]['hooks_registered']) {
            add_action('wp_footer', function () use ($app_slug) {
                self::outputConsoleScripts($app_slug);
            });
            add_action('admin_footer', function () use ($app_slug) {
                self::outputConsoleScripts($app_slug);
            });
            self::$instances[$app_slug]['hooks_registered'] = true;
        }

        return true;
    }

    /**
     * Initialize debugger from Config instance.
     *
     * @param Config $config Configuration instance
     * @param bool|null $is_dev Whether to enable development mode
     * @return bool Success status
     */
    public static function initFromConfig(Config $config, ?bool $is_dev = null): bool
    {
        return self::init(
            $config->slug,
            $is_dev ?? $config->isDevelopment(),
            $config->get('name'),
            $config->get('text_domain', $config->slug)
        );
    }

    /**
     * Check if debugger is in development mode for a specific app.
     *
     * @param string $app_slug Application slug
     * @return bool Development mode status
     */
    public static function isDev(string $app_slug): bool
    {
        $app_slug = sanitize_key($app_slug);
        return self::$instances[$app_slug]['is_dev'] ?? false;
    }

    /**
     * Log a message to the browser console.
     *
     * @param string $app_slug Application slug
     * @param mixed $message The message to log
     * @param string $type Console method type
     * @param bool $include_trace Whether to include stack trace
     * @return bool|null True if successful, false if failed, null if not in dev mode
     */
    public static function console(
        string $app_slug,
        mixed $message,
        string $type = 'log',
        bool $include_trace = true
    ): ?bool {
        $app_slug = sanitize_key($app_slug);

        self::log($app_slug, $message);

        if (!self::isDev($app_slug)) {
            return null;
        }

        $instance = self::$instances[$app_slug] ?? null;
        if (!$instance) {
            return false;
        }

        // Validate console type
        $type = in_array($type, self::CONSOLE_TYPES, true) ? $type : 'log';

        // Convert message to string if needed
        if (!is_string($message)) {
            $message = self::formatVariable($message);
        }

        // Truncate long messages
        if (strlen($message) > self::MAX_CONSOLE_LENGTH) {
            $message = substr($message, 0, self::MAX_CONSOLE_LENGTH) . '... [truncated]';
        }

        // Prepare console script
        $safe_message = wp_json_encode($message, JSON_UNESCAPED_UNICODE);

        $script = sprintf(
            "console.%s('[%s] %s'",
            esc_js($type),
            esc_js($instance['app_name']),
            $safe_message
        );

        // Add trace information if requested
        if ($include_trace) {
            $trace = self::getCallerInfo();
            $script .= sprintf(
                ", '\\n📍 Called from: %s:%d'",
                esc_js($trace['file']),
                esc_js($trace['line'])
            );
        }

        $script .= ');';

        return self::enqueueConsoleScript($app_slug, $script);
    }

    /**
     * Log info message to console.
     *
     * @param string $app_slug Application slug
     * @param mixed $message Message to log
     * @param bool $include_trace Include stack trace
     * @return bool|null Success status
     */
    public static function info(string $app_slug, mixed $message, bool $include_trace = true): ?bool
    {
        return self::console($app_slug, $message, 'info', $include_trace);
    }

    /**
     * Log warning message to console.
     *
     * @param string $app_slug Application slug
     * @param mixed $message Message to log
     * @param bool $include_trace Include stack trace
     * @return bool|null Success status
     */
    public static function warn(string $app_slug, mixed $message, bool $include_trace = true): ?bool
    {
        return self::console($app_slug, $message, 'warn', $include_trace);
    }

    /**
     * Log error message to console.
     *
     * @param string $app_slug Application slug
     * @param mixed $message Message to log
     * @param bool $include_trace Include stack trace
     * @return bool|null Success status
     */
    public static function error(string $app_slug, mixed $message, bool $include_trace = true): ?bool
    {
        return self::console($app_slug, $message, 'error', $include_trace);
    }

    /**
     * Print a human-readable representation of a variable.
     *
     * @param string $app_slug Application slug
     * @param mixed $data The data to print
     * @param bool|string $die Whether to stop execution after printing
     * @return void
     */
    public static function printR(string $app_slug, mixed $data, bool|string $die = false): void
    {
        $app_slug = sanitize_key($app_slug);

        self::log($app_slug, $data);

        if (!self::isDev($app_slug)) {
            return;
        }

        $instance = self::$instances[$app_slug] ?? null;
        if (!$instance) {
            return;
        }

        $caller = self::getCallerInfo();

        printf(
            '<div style="background: #f1f1f1; border: 1px solid #ccc; padding: 15px; margin: 10px; font-family: monospace; white-space: pre-wrap; overflow: auto;">',
        );

        printf(
            '<h4 style="margin: 0 0 10px 0; color: #333;">🐛 %s Debug Output</h4>',
            esc_html($instance['app_name'])
        );

        printf(
            '<p style="margin: 0 0 10px 0; font-size: 12px; color: #666;">📍 Called from: <code>%s:%d</code></p>',
            esc_html($caller['file']),
            esc_html($caller['line'])
        );

        echo '<pre style="margin: 0; background: white; padding: 10px; border: 1px solid #ddd; border-radius: 3px; overflow: auto; max-height: 400px;">';
        echo esc_html(self::formatVariable($data));
        echo '</pre>';

        echo '</div>';

        if ($die) {
            $message = is_string($die) ? $die : 'Script execution stopped by Debugger::printR';
            wp_die(esc_html($message));
        }
    }

    /**
     * Dump information about a variable using var_dump.
     *
     * @param string $app_slug Application slug
     * @param mixed $data The data to dump
     * @param bool|string $die Whether to stop execution after dumping
     * @return void
     */
    public static function varDump(string $app_slug, mixed $data, bool|string $die = false): void
    {
        $app_slug = sanitize_key($app_slug);

        self::log($app_slug, $data);

        if (!self::isDev($app_slug)) {
            return;
        }

        $instance = self::$instances[$app_slug] ?? null;
        if (!$instance) {
            return;
        }

        $caller = self::getCallerInfo();

        printf(
            '<div style="background: #f1f1f1; border: 1px solid #ccc; padding: 15px; margin: 10px; font-family: monospace; white-space: pre-wrap; overflow: auto;">',
        );

        printf(
            '<h4 style="margin: 0 0 10px 0; color: #333;">🐛 %s var_dump Output</h4>',
            esc_html($instance['app_name'])
        );

        printf(
            '<p style="margin: 0 0 10px 0; font-size: 12px; color: #666;">📍 Called from: <code>%s:%d</code></p>',
            esc_html($caller['file']),
            esc_html($caller['line'])
        );

        echo '<pre style="margin: 0; background: white; padding: 10px; border: 1px solid #ddd; border-radius: 3px; overflow: auto; max-height: 400px;">';

        ob_start();
        var_dump($data);
        echo esc_html(ob_get_clean());

        echo '</pre>';
        echo '</div>';

        if ($die) {
            $message = is_string($die) ? $die : 'Script execution stopped by Debugger::varDump';
            wp_die(esc_html($message));
        }
    }

    /**
     * Create a breakpoint to stop script execution and inspect state.
     *
     * @param string $app_slug Application slug
     * @param string $message Optional message to display
     * @param array<string, mixed> $context Additional context data
     * @return void
     */
    public static function breakpoint(string $app_slug, string $message = '', array $context = []): void
    {
        $app_slug = sanitize_key($app_slug);

        if (!self::isDev($app_slug)) {
            return;
        }

        $instance = self::$instances[$app_slug] ?? null;
        if (!$instance) {
            return;
        }

        $caller = self::getCallerInfo();

        ob_start();
?>
        <div style="background: #ffeb3b; border: 2px solid #ff9800; padding: 20px; margin: 20px; font-family: Arial, sans-serif;">
            <h2 style="margin: 0 0 15px 0; color: #e65100;">🛑 <?php echo esc_html($instance['app_name']); ?> - Breakpoint Hit</h2>

            <p style="margin: 0 0 10px 0; font-size: 14px;">
                <strong>📍 Location:</strong> <code><?php echo esc_html($caller['file']); ?>:<?php echo esc_html($caller['line']); ?></code>
            </p>

            <?php if (!empty($message)): ?>
                <p style="margin: 0 0 15px 0; font-size: 14px;">
                    <strong>💬 Message:</strong> <?php echo esc_html($message); ?>
                </p>
            <?php endif; ?>

            <?php if (!empty($context)): ?>
                <details style="margin: 15px 0;">
                    <summary style="cursor: pointer; font-weight: bold;">📊 Context Data</summary>
                    <pre style="background: white; padding: 10px; border: 1px solid #ddd; border-radius: 3px; margin: 10px 0; overflow: auto; max-height: 300px;"><?php echo esc_html(self::formatVariable($context)); ?></pre>
                </details>
            <?php endif; ?>

            <p style="margin: 15px 0 0 0; font-size: 12px; color: #666;">
                <em>Script execution will stop after this message.</em>
            </p>
        </div>
<?php

        $output = ob_get_clean();
        echo $output;

        self::log($app_slug, "Breakpoint hit: {$message}");
        wp_die('Script execution stopped by debugger breakpoint.');
    }

    /**
     * Add a debug notification using the Notification helper.
     *
     * @param string $app_slug Application slug
     * @param string $message Notification message
     * @param string $type Notification type
     * @return bool Success status
     */
    public static function notification(string $app_slug, string $message, string $type = 'info'): bool
    {
        $app_slug = sanitize_key($app_slug);

        if (!self::isDev($app_slug)) {
            return false;
        }

        $instance = self::$instances[$app_slug] ?? null;
        if (!$instance) {
            return false;
        }

        $debug_message = sprintf('🐛 %s Debug: %s', $instance['app_name'], $message);

        if (class_exists(Notification::class)) {
            return Notification::addStatic($app_slug, $debug_message, $type, 'plugin');
        }

        return false;
    }

    /**
     * Sleep for a given number of seconds (only in dev mode).
     *
     * @param string $app_slug Application slug
     * @param int $seconds Number of seconds to sleep
     * @return void
     */
    public static function sleep(string $app_slug, int $seconds): void
    {
        if (self::isDev($app_slug) && $seconds > 0) {
            sleep(absint($seconds));
        }
    }

    /**
     * Log a message to the WordPress error log.
     *
     * @param string $app_slug Application slug
     * @param mixed $message Message to log
     * @param string $level Log level for context
     * @return void
     */
    public static function log(string $app_slug, mixed $message, string $level = 'DEBUG'): void
    {
        $app_slug = sanitize_key($app_slug);

        if (!is_string($message)) {
            $message = self::formatVariable($message);
        }

        $instance = self::$instances[$app_slug] ?? null;
        $app_name = $instance['app_name'] ?? ucfirst(str_replace(['-', '_'], ' ', $app_slug));
        $caller = self::getCallerInfo();

        $log_message = sprintf(
            '[%s] %s: %s (at %s:%d)',
            strtoupper($level),
            $app_name,
            $message,
            basename($caller['file']),
            $caller['line']
        );

        error_log($log_message);
    }

    /**
     * Log performance timing information.
     *
     * @param string $app_slug Application slug
     * @param string $operation Operation name
     * @param float|null $start_time Start time (microtime(true))
     * @return float Current time for chaining
     */
    public static function timer(string $app_slug, string $operation, ?float $start_time = null): float
    {
        $current_time = microtime(true);

        if ($start_time !== null) {
            $duration = round(($current_time - $start_time) * 1000, 2);
            self::log($app_slug, sprintf('%s took %sms', $operation, $duration), 'PERFORMANCE');
        } else {
            self::log($app_slug, sprintf('%s started', $operation), 'PERFORMANCE');
        }

        return $current_time;
    }

    /**
     * Dump current WordPress query information.
     *
     * @param string $app_slug Application slug
     * @return void
     */
    public static function queryInfo(string $app_slug): void
    {
        if (!self::isDev($app_slug)) {
            return;
        }

        global $wp_query;

        $query_data = [
            'is_admin' => is_admin(),
            'is_home' => is_home(),
            'is_front_page' => is_front_page(),
            'is_single' => is_single(),
            'is_page' => is_page(),
            'is_category' => is_category(),
            'is_tag' => is_tag(),
            'is_archive' => is_archive(),
            'is_search' => is_search(),
            'is_404' => is_404(),
            'query_vars' => $wp_query->query_vars ?? [],
        ];

        self::printR($app_slug, $query_data);
    }

    /**
     * Get all registered debugger instances.
     *
     * @return array<string, array<string, mixed>> All debugger instances
     */
    public static function getInstances(): array
    {
        return self::$instances;
    }

    /**
     * Check if an app has a debugger instance.
     *
     * @param string $app_slug Application slug
     * @return bool Whether the app has a debugger instance
     */
    public static function hasInstance(string $app_slug): bool
    {
        $app_slug = sanitize_key($app_slug);
        return isset(self::$instances[$app_slug]);
    }

    /**
     * Remove a debugger instance.
     *
     * @param string $app_slug Application slug
     * @return bool Success status
     */
    public static function removeInstance(string $app_slug): bool
    {
        $app_slug = sanitize_key($app_slug);

        if (isset(self::$instances[$app_slug])) {
            unset(self::$instances[$app_slug]);
            return true;
        }

        return false;
    }

    /**
     * Format a variable for display.
     *
     * @param mixed $variable Variable to format
     * @return string Formatted variable
     */
    protected static function formatVariable(mixed $variable): string
    {
        if (is_string($variable)) {
            return $variable;
        }

        return print_r($variable, true);
    }

    /**
     * Get caller information from debug backtrace.
     *
     * @return array<string, mixed> Caller file and line information
     */
    protected static function getCallerInfo(): array
    {
        $backtrace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 3);

        // Get the caller (skip current method)
        $caller = $backtrace[1] ?? $backtrace[0] ?? [];

        return [
            'file' => self::normalizeFilePath($caller['file'] ?? 'unknown'),
            'line' => $caller['line'] ?? 0,
            'function' => $caller['function'] ?? 'unknown',
        ];
    }

    /**
     * Normalize file path for display.
     *
     * @param string $file_path Full file path
     * @return string Normalized path
     */
    protected static function normalizeFilePath(string $file_path): string
    {
        // Convert backslashes to forward slashes
        $file_path = str_replace('\\', '/', $file_path);

        // Try to make path relative to WordPress root or plugin
        $replacements = [
            ABSPATH => '/',
            WP_PLUGIN_DIR => '/plugins',
            WP_CONTENT_DIR => '/wp-content',
        ];

        foreach ($replacements as $search => $replace) {
            $search = str_replace('\\', '/', $search);
            if (str_starts_with($file_path, $search)) {
                return $replace . substr($file_path, strlen($search));
            }
        }

        return basename($file_path);
    }

    /**
     * Enqueue a console script for a specific app.
     *
     * @param string $app_slug Application slug
     * @param string $script JavaScript code to execute
     * @return bool Success status
     */
    protected static function enqueueConsoleScript(string $app_slug, string $script): bool
    {
        $instance = self::$instances[$app_slug] ?? null;
        if (!$instance) {
            return false;
        }

        $script_handle = $instance['script_handle'];

        if (!wp_script_is($script_handle, 'registered')) {
            wp_register_script($script_handle, '', [], false, true);
        }

        if (!wp_script_is($script_handle, 'enqueued')) {
            wp_enqueue_script($script_handle);
        }

        wp_add_inline_script($script_handle, $script);
        return true;
    }

    /**
     * Output console scripts in footer for a specific app.
     *
     * @param string $app_slug Application slug
     * @return void
     */
    protected static function outputConsoleScripts(string $app_slug): void
    {
        $instance = self::$instances[$app_slug] ?? null;
        if (!$instance || !$instance['is_dev']) {
            return;
        }

        $script_handle = $instance['script_handle'];
        if (!wp_script_is($script_handle, 'enqueued')) {
            return;
        }

        // Scripts are automatically output by WordPress
    }
}
