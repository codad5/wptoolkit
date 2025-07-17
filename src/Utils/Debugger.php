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
 */
class Debugger
{
    /**
     * Whether the debugger is in development mode.
     */
    private static bool $is_dev = false;

    /**
     * Console log types allowed.
     */
    const CONSOLE_TYPES = ['log', 'info', 'warn', 'error', 'debug'];

    /**
     * Maximum string length for console output.
     */
    const MAX_CONSOLE_LENGTH = 5000;

    /**
     * Script handle for console scripts.
     */
    private static string $script_handle = '';

    /**
     * Initialize the debugger.
     *
     * @param bool $is_dev Whether to enable development mode
     * @return void
     */
    public static function init(bool $is_dev = null): void
    {
        if ($is_dev === null) {
            $is_dev = defined('WP_DEBUG') && WP_DEBUG;
        }

        self::$is_dev = $is_dev;
        self::$script_handle = Config::get('slug') . '-debugger-console';

        self::log('Debugger initialized with is_dev = ' . ($is_dev ? 'true' : 'false'));

        if ($is_dev) {
            add_action('wp_footer', [self::class, 'output_console_scripts']);
            add_action('admin_footer', [self::class, 'output_console_scripts']);
        }
    }

    /**
     * Check if debugger is in development mode.
     *
     * @return bool Development mode status
     */
    public static function is_dev(): bool
    {
        return self::$is_dev;
    }

    /**
     * Log a message to the browser console.
     *
     * @param mixed $message The message to log
     * @param string $type Console method type
     * @param bool $include_trace Whether to include stack trace
     * @return bool|null True if successful, false if failed, null if not in dev mode
     */
    public static function console(
        mixed $message,
        string $type = 'log',
        bool $include_trace = true
    ): ?bool {
        self::log($message);

        if (!self::$is_dev) {
            return null;
        }

        // Validate console type
        $type = in_array($type, self::CONSOLE_TYPES, true) ? $type : 'log';

        // Convert message to string if needed
        if (!is_string($message)) {
            $message = self::format_variable($message);
        }

        // Truncate long messages
        if (strlen($message) > self::MAX_CONSOLE_LENGTH) {
            $message = substr($message, 0, self::MAX_CONSOLE_LENGTH) . '... [truncated]';
        }

        // Prepare console script
        $safe_message = wp_json_encode($message, JSON_UNESCAPED_UNICODE);
        $plugin_name = Config::get('name') ?? 'Plugin';

        $script = sprintf(
            "console.%s('[%s] %s'",
            esc_js($type),
            esc_js($plugin_name),
            $safe_message
        );

        // Add trace information if requested
        if ($include_trace) {
            $trace = self::get_caller_info();
            $script .= sprintf(
                ", '\\n📍 Called from: %s:%d'",
                esc_js($trace['file']),
                esc_js($trace['line'])
            );
        }

        $script .= ');';

        return self::enqueue_console_script($script);
    }

    /**
     * Log info message to console.
     *
     * @param mixed $message Message to log
     * @param bool $include_trace Include stack trace
     * @return bool|null Success status
     */
    public static function info(mixed $message, bool $include_trace = true): ?bool
    {
        return self::console($message, 'info', $include_trace);
    }

    /**
     * Log warning message to console.
     *
     * @param mixed $message Message to log
     * @param bool $include_trace Include stack trace
     * @return bool|null Success status
     */
    public static function warn(mixed $message, bool $include_trace = true): ?bool
    {
        return self::console($message, 'warn', $include_trace);
    }

    /**
     * Log error message to console.
     *
     * @param mixed $message Message to log
     * @param bool $include_trace Include stack trace
     * @return bool|null Success status
     */
    public static function error(mixed $message, bool $include_trace = true): ?bool
    {
        return self::console($message, 'error', $include_trace);
    }

    /**
     * Print a human-readable representation of a variable.
     *
     * @param mixed $data The data to print
     * @param bool|string $die Whether to stop execution after printing
     * @return void
     */
    public static function print_r(mixed $data, bool|string $die = false): void
    {
        self::log($data);

        if (!self::$is_dev) {
            return;
        }

        $caller = self::get_caller_info();
        $plugin_name = Config::get('name') ?? 'Plugin';

        printf(
            '<div style="background: #f1f1f1; border: 1px solid #ccc; padding: 15px; margin: 10px; font-family: monospace; white-space: pre-wrap; overflow: auto;">',
        );

        printf(
            '<h4 style="margin: 0 0 10px 0; color: #333;">🐛 %s Debug Output</h4>',
            esc_html($plugin_name)
        );

        printf(
            '<p style="margin: 0 0 10px 0; font-size: 12px; color: #666;">📍 Called from: <code>%s:%d</code></p>',
            esc_html($caller['file']),
            esc_html($caller['line'])
        );

        echo '<pre style="margin: 0; background: white; padding: 10px; border: 1px solid #ddd; border-radius: 3px; overflow: auto; max-height: 400px;">';
        echo esc_html(self::format_variable($data));
        echo '</pre>';

        echo '</div>';

        if ($die) {
            $message = is_string($die) ? $die : 'Script execution stopped by Debugger::print_r';
            wp_die(esc_html($message));
        }
    }

    /**
     * Dump information about a variable using var_dump.
     *
     * @param mixed $data The data to dump
     * @param bool|string $die Whether to stop execution after dumping
     * @return void
     */
    public static function var_dump(mixed $data, bool|string $die = false): void
    {
        self::log($data);

        if (!self::$is_dev) {
            return;
        }

        $caller = self::get_caller_info();
        $plugin_name = Config::get('name') ?? 'Plugin';

        printf(
            '<div style="background: #f1f1f1; border: 1px solid #ccc; padding: 15px; margin: 10px; font-family: monospace; white-space: pre-wrap; overflow: auto;">',
        );

        printf(
            '<h4 style="margin: 0 0 10px 0; color: #333;">🐛 %s var_dump Output</h4>',
            esc_html($plugin_name)
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
            $message = is_string($die) ? $die : 'Script execution stopped by Debugger::var_dump';
            wp_die(esc_html($message));
        }
    }

    /**
     * Create a breakpoint to stop script execution and inspect state.
     *
     * @param string $message Optional message to display
     * @param array<string, mixed> $context Additional context data
     * @return void
     */
    public static function breakpoint(string $message = '', array $context = []): void
    {
        if (!self::$is_dev) {
            return;
        }

        $caller = self::get_caller_info();
        $plugin_name = Config::get('name') ?? 'Plugin';

        ob_start();
?>
        <div style="background: #ffeb3b; border: 2px solid #ff9800; padding: 20px; margin: 20px; font-family: Arial, sans-serif;">
            <h2 style="margin: 0 0 15px 0; color: #e65100;">🛑 <?php echo esc_html($plugin_name); ?> - Breakpoint Hit</h2>

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
                    <pre style="background: white; padding: 10px; border: 1px solid #ddd; border-radius: 3px; margin: 10px 0; overflow: auto; max-height: 300px;"><?php echo esc_html(self::format_variable($context)); ?></pre>
                </details>
            <?php endif; ?>

            <p style="margin: 15px 0 0 0; font-size: 12px; color: #666;">
                <em>Script execution will stop after this message.</em>
            </p>
        </div>
<?php

        $output = ob_get_clean();
        echo $output;

        self::log("Breakpoint hit: {$message}");
        wp_die('Script execution stopped by debugger breakpoint.');
    }

    /**
     * Add a debug notification using the Notification helper.
     *
     * @param string $message Notification message
     * @param string $type Notification type
     * @return bool Success status
     */
    public static function notification(string $message, string $type = 'info'): bool
    {
        if (!self::$is_dev) {
            return false;
        }

        $plugin_name = Config::get('name') ?? 'Plugin';
        $debug_message = sprintf('🐛 %s Debug: %s', $plugin_name, $message);

        if (class_exists(Notification::class)) {
            return Notification::add($debug_message, $type, 'plugin');
        }

        return false;
    }

    /**
     * Sleep for a given number of seconds (only in dev mode).
     *
     * @param int $seconds Number of seconds to sleep
     * @return void
     */
    public static function sleep(int $seconds): void
    {
        if (self::$is_dev && $seconds > 0) {
            sleep(absint($seconds));
        }
    }

    /**
     * Log a message to the WordPress error log.
     *
     * @param mixed $message Message to log
     * @param string $level Log level for context
     * @return void
     */
    public static function log(mixed $message, string $level = 'DEBUG'): void
    {
        if (!is_string($message)) {
            $message = self::format_variable($message);
        }

        $plugin_name = Config::get('name') ?? 'Plugin';
        $caller = self::get_caller_info();

        $log_message = sprintf(
            '[%s] %s: %s (at %s:%d)',
            strtoupper($level),
            $plugin_name,
            $message,
            basename($caller['file']),
            $caller['line']
        );

        error_log($log_message);
    }

    /**
     * Log performance timing information.
     *
     * @param string $operation Operation name
     * @param float|null $start_time Start time (microtime(true))
     * @return float Current time for chaining
     */
    public static function timer(string $operation, ?float $start_time = null): float
    {
        $current_time = microtime(true);

        if ($start_time !== null) {
            $duration = round(($current_time - $start_time) * 1000, 2);
            self::log(sprintf('%s took %sms', $operation, $duration), 'PERFORMANCE');
        } else {
            self::log(sprintf('%s started', $operation), 'PERFORMANCE');
        }

        return $current_time;
    }

    /**
     * Dump current WordPress query information.
     *
     * @return void
     */
    public static function query_info(): void
    {
        if (!self::$is_dev) {
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

        self::print_r($query_data);
    }

    /**
     * Format a variable for display.
     *
     * @param mixed $variable Variable to format
     * @return string Formatted variable
     */
    protected static function format_variable(mixed $variable): string
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
    protected static function get_caller_info(): array
    {
        $backtrace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 3);

        // Get the caller (skip current method)
        $caller = $backtrace[1] ?? $backtrace[0] ?? [];

        return [
            'file' => self::normalize_file_path($caller['file'] ?? 'unknown'),
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
    protected static function normalize_file_path(string $file_path): string
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
     * Enqueue a console script.
     *
     * @param string $script JavaScript code to execute
     * @return bool Success status
     */
    protected static function enqueue_console_script(string $script): bool
    {
        if (!wp_script_is(self::$script_handle, 'registered')) {
            wp_register_script(self::$script_handle, '', [], false, true);
        }

        if (!wp_script_is(self::$script_handle, 'enqueued')) {
            wp_enqueue_script(self::$script_handle);
        }

        wp_add_inline_script(self::$script_handle, $script);
        return true;
    }

    /**
     * Output console scripts in footer.
     *
     * @return void
     */
    public static function output_console_scripts(): void
    {
        if (!self::$is_dev || !wp_script_is(self::$script_handle, 'enqueued')) {
            return;
        }

        // Scripts are automatically output by WordPress
    }
}
