<?php

/**
 * Notification Helper
 *
 * @author Your Name <username@example.com>
 * @license GPL-2.0-or-later http://www.gnu.org/licenses/gpl-2.0.txt
 * @link https://github.com/szepeviktor/starter-plugin
 */

declare(strict_types=1);

namespace Codad5\WPToolkit\Utils;

use Codad5\WPToolkit\Registry;
use function is_admin;
use function get_current_user_id;
use function wp_kses_post;
use function wp_generate_uuid4;
use function wp_nonce_url;
use function wp_safe_redirect;
use function esc_url_raw;
use function esc_html;
use function esc_attr;
use function sanitize_text_field;
use function sanitize_key;
use function add_query_arg;
use function remove_query_arg;
use function wp_verify_nonce;
use function maybe_unserialize;
use function delete_transient;
use function set_transient;




use InvalidArgumentException;

/**
 * Notification Helper class for managing admin notices and notifications.
 *
 * Provides a clean API for displaying temporary messages in the WordPress admin
 * with support for different notification types and page targeting.
 * Now fully object-based with enhanced static methods for backward compatibility.
 */
class Notification
{
    /**
     * Notification types allowed in WordPress admin notices.
     */
    const TYPES = ['success', 'error', 'warning', 'info'];

    /**
     * Page targeting options for notifications.
     */
    const PAGE_TARGETS = [
        'all' => 'all',           // Show on all admin pages
        'plugin' => 'plugin',     // Show on plugin pages only
        'current' => 'current',   // Show on current page only
    ];

    /**
     * Default expiration time for notifications in seconds.
     */
    const DEFAULT_EXPIRATION = 300; // 5 minutes

    /**
     * Application slug for identification and cache keys.
     */
    protected string $app_slug;

    /**
     * Text domain for translations.
     */
    protected string $text_domain;

    /**
     * Plugin/app name for display.
     */
    protected string $app_name;

    /**
     * Config instance (optional dependency).
     */
    protected ?Config $config = null;

    /**
     * Whether hooks have been registered for this instance.
     */
    protected bool $hooks_registered = false;

    /**
     * Constructor for creating a new Notification instance.
     *
     * @param Config|string $config_or_slug Config instance or app slug
     * @param string|null $app_name App name for display (optional)
     * @throws InvalidArgumentException If parameters are invalid
     */
    public function __construct(Config|string $config_or_slug, ?string $app_name = null)
    {
        $this->parseConfigOrSlug($config_or_slug, $app_name);
        $this->registerHooks();
    }

    /**
     * Static factory method for creating Notification instances.
     *
     * @param Config|string $config_or_slug Config instance or app slug
     * @param string|null $app_name App name for display (optional)
     * @return static New Notification instance
     */
    public static function create(Config|string $config_or_slug, ?string $app_name = null): static
    {
        return new static($config_or_slug, $app_name);
    }

    /**
     * Add a notification message to be displayed in the admin area.
     *
     * @param string $message The notification message (can contain HTML)
     * @param string $type The type of notification
     * @param string|array<string> $allowed_pages Page targeting configuration
     * @param int|null $expiration Expiration time in seconds
     * @param bool $dismissible Whether the notification can be dismissed
     * @return bool True on success, false on failure
     */
    public function add(
        string $message,
        string $type = 'success',
        string|array $allowed_pages = 'current',
        ?int $expiration = null,
        bool $dismissible = true
    ): bool {
        if (!is_admin()) {
            return false;
        }

        // Validate and sanitize inputs
        $type = $this->validateType($type);
        $message = wp_kses_post($message);
        $expiration = $expiration ?? self::DEFAULT_EXPIRATION;

        if (empty($message)) {
            return false;
        }

        // Create notification data
        $notification = [
            'id' => $this->generateNotificationId(),
            'message' => $message,
            'type' => $type,
            'allowed_pages' => $allowed_pages,
            'dismissible' => $dismissible,
            'created_at' => time(),
            'app_slug' => $this->app_slug,
        ];

        // Get existing notifications
        $notifications = $this->getNotifications();

        // Prevent duplicate notifications
        if ($this->isDuplicateNotification($notification, $notifications)) {
            return true; // Already exists, consider it successful
        }

        // Add new notification
        $notifications[] = $notification;

        // Store notifications
        return set_transient(
            $this->getCacheKey(),
            $notifications,
            absint($expiration)
        );
    }

    /**
     * Add a success notification.
     *
     * @param string $message The notification message
     * @param string|array<string> $allowed_pages Page targeting
     * @param int|null $expiration Expiration time in seconds
     * @return bool Success status
     */
    public function success(
        string $message,
        string|array $allowed_pages = 'current',
        ?int $expiration = null
    ): bool {
        return $this->add($message, 'success', $allowed_pages, $expiration);
    }

    /**
     * Add an error notification.
     *
     * @param string $message The notification message
     * @param string|array<string> $allowed_pages Page targeting
     * @param int|null $expiration Expiration time in seconds
     * @return bool Success status
     */
    public function error(
        string $message,
        string|array $allowed_pages = 'current',
        ?int $expiration = null
    ): bool {
        return $this->add($message, 'error', $allowed_pages, $expiration);
    }

    /**
     * Add a warning notification.
     *
     * @param string $message The notification message
     * @param string|array<string> $allowed_pages Page targeting
     * @param int|null $expiration Expiration time in seconds
     * @return bool Success status
     */
    public function warning(
        string $message,
        string|array $allowed_pages = 'current',
        ?int $expiration = null
    ): bool {
        return $this->add($message, 'warning', $allowed_pages, $expiration);
    }

    /**
     * Add an info notification.
     *
     * @param string $message The notification message
     * @param string|array<string> $allowed_pages Page targeting
     * @param int|null $expiration Expiration time in seconds
     * @return bool Success status
     */
    public function info(
        string $message,
        string|array $allowed_pages = 'current',
        ?int $expiration = null
    ): bool {
        return $this->add($message, 'info', $allowed_pages, $expiration);
    }

    /**
     * Get all notifications for the current user and app.
     *
     * @return array<array<string, mixed>> Array of notifications
     */
    public function getNotifications(): array
    {
        $notifications = get_transient($this->getCacheKey());

        if (!is_array($notifications)) {
            return [];
        }

        // Remove expired notifications
        $current_time = time();
        $notifications = array_filter($notifications, function ($notification) use ($current_time) {
            $created_at = $notification['created_at'] ?? 0;
            $max_age = self::DEFAULT_EXPIRATION;

            return ($current_time - $created_at) < $max_age;
        });

        return array_values($notifications); // Re-index array
    }

    /**
     * Clear all notifications for the current user and app.
     *
     * @return bool True on success, false on failure
     */
    public function clear(): bool
    {
        return delete_transient($this->getCacheKey());
    }

    /**
     * Dismiss a specific notification by ID.
     *
     * @param string $notification_id The notification ID to dismiss
     * @return bool True on success, false on failure
     */
    public function dismiss(string $notification_id): bool
    {
        $notifications = $this->getNotifications();
        $updated_notifications = [];

        foreach ($notifications as $notification) {
            if (($notification['id'] ?? '') !== $notification_id) {
                $updated_notifications[] = $notification;
            }
        }

        if (count($updated_notifications) === count($notifications)) {
            return false; // Nothing was removed
        }

        if (empty($updated_notifications)) {
            return $this->clear();
        }

        return set_transient(
            $this->getCacheKey(),
            $updated_notifications,
            self::DEFAULT_EXPIRATION
        );
    }

    /**
     * Get the application slug.
     *
     * @return string Application slug
     */
    public function getAppSlug(): string
    {
        return $this->app_slug;
    }

    /**
     * Get the text domain.
     *
     * @return string Text domain
     */
    public function getTextDomain(): string
    {
        return $this->text_domain;
    }

    /**
     * Get the app name.
     *
     * @return string App name
     */
    public function getAppName(): string
    {
        return $this->app_name;
    }

    /**
     * Get the config instance if available.
     *
     * @return Config|null Config instance or null
     */
    public function getConfig(): ?Config
    {
        return $this->config;
    }

    // =============================================================================
    // ENHANCED STATIC METHODS FOR BACKWARD COMPATIBILITY
    // =============================================================================

    /**
     * Static method to add a notification with explicit config/slug.
     *
     * @param Config|string $config_or_slug Config instance or app slug
     * @param string $message The notification message
     * @param string $type The type of notification
     * @param string|array<string> $allowed_pages Page targeting
     * @param int|null $expiration Expiration time in seconds
     * @param bool $dismissible Whether the notification can be dismissed
     * @return bool Success status
     */
    public static function addStatic(
        Config|string $config_or_slug,
        string $message,
        string $type = 'success',
        string|array $allowed_pages = 'current',
        ?int $expiration = null,
        bool $dismissible = true
    ): bool {
        $instance = self::create($config_or_slug);
        return $instance->add($message, $type, $allowed_pages, $expiration, $dismissible);
    }

    /**
     * Static method to add a success notification.
     *
     * @param Config|string $config_or_slug Config instance or app slug
     * @param string $message The notification message
     * @param string|array<string> $allowed_pages Page targeting
     * @param int|null $expiration Expiration time in seconds
     * @return bool Success status
     */
    public static function successStatic(
        Config|string $config_or_slug,
        string $message,
        string|array $allowed_pages = 'current',
        ?int $expiration = null
    ): bool {
        return self::addStatic($config_or_slug, $message, 'success', $allowed_pages, $expiration);
    }

    /**
     * Static method to add an error notification.
     *
     * @param Config|string $config_or_slug Config instance or app slug
     * @param string $message The notification message
     * @param string|array<string> $allowed_pages Page targeting
     * @param int|null $expiration Expiration time in seconds
     * @return bool Success status
     */
    public static function errorStatic(
        Config|string $config_or_slug,
        string $message,
        string|array $allowed_pages = 'current',
        ?int $expiration = null
    ): bool {
        return self::addStatic($config_or_slug, $message, 'error', $allowed_pages, $expiration);
    }

    /**
     * Static method to add a warning notification.
     *
     * @param Config|string $config_or_slug Config instance or app slug
     * @param string $message The notification message
     * @param string|array<string> $allowed_pages Page targeting
     * @param int|null $expiration Expiration time in seconds
     * @return bool Success status
     */
    public static function warningStatic(
        Config|string $config_or_slug,
        string $message,
        string|array $allowed_pages = 'current',
        ?int $expiration = null
    ): bool {
        return self::addStatic($config_or_slug, $message, 'warning', $allowed_pages, $expiration);
    }

    /**
     * Static method to add an info notification.
     *
     * @param Config|string $config_or_slug Config instance or app slug
     * @param string $message The notification message
     * @param string|array<string> $allowed_pages Page targeting
     * @param int|null $expiration Expiration time in seconds
     * @return bool Success status
     */
    public static function infoStatic(
        Config|string $config_or_slug,
        string $message,
        string|array $allowed_pages = 'current',
        ?int $expiration = null
    ): bool {
        return self::addStatic($config_or_slug, $message, 'info', $allowed_pages, $expiration);
    }

    /**
     * Static method to clear notifications for a specific app.
     *
     * @param Config|string $config_or_slug Config instance or app slug
     * @return bool Success status
     */
    public static function clearStatic(Config|string $config_or_slug): bool
    {
        $instance = self::create($config_or_slug);
        return $instance->clear();
    }

    // =============================================================================
    // GLOBAL STATIC METHODS (for displaying all notifications)
    // =============================================================================

    /**
     * Display all notifications from all apps in the WordPress admin area.
     * This should be called once globally, not per app.
     *
     * @return void
     */
    public static function displayAllNotifications(): void
    {
        if (!is_admin()) {
            return;
        }

        // Get all notification cache keys and display them
        global $wpdb;

        $current_page = self::getCurrentPageKeyStatic();
        $user_id = get_current_user_id();

        // Get all transients that look like notification caches
        $transient_pattern = "_transient_notifications_%_user_{$user_id}";

        $transients = $wpdb->get_results($wpdb->prepare(
            "SELECT option_name, option_value FROM {$wpdb->options} 
             WHERE option_name LIKE %s",
            $transient_pattern
        ));

        foreach ($transients as $transient) {
            $cache_key = str_replace('_transient_', '', $transient->option_name);
            $notifications = maybe_unserialize($transient->option_value);

            if (!is_array($notifications)) {
                continue;
            }

            $displayed_notifications = [];
            $requeue_notifications = [];

            foreach ($notifications as $notification) {
                if (self::shouldDisplayNotificationStatic($notification, $current_page)) {
                    self::renderNotificationStatic($notification);
                    $displayed_notifications[] = $notification['id'] ?? '';
                } else {
                    $requeue_notifications[] = $notification;
                }
            }

            // Update notifications list (remove displayed ones)
            if (!empty($displayed_notifications)) {
                if (empty($requeue_notifications)) {
                    delete_transient($cache_key);
                } else {
                    set_transient($cache_key, $requeue_notifications, self::DEFAULT_EXPIRATION);
                }
            }
        }
    }

    /**
     * Initialize global notification display hooks.
     * Call this once in your main plugin file.
     *
     * @return bool Success status
     */
    public static function initGlobal(): bool
    {
        if (!is_admin()) {
            return false;
        }

        add_action('admin_notices', [self::class, 'displayAllNotifications']);
        add_action('admin_init', [self::class, 'maybeDismissNotificationGlobal']);

        return true;
    }

    /**
     * Handle notification dismissal globally.
     *
     * @return void
     */
    public static function maybeDismissNotificationGlobal(): void
    {
        if (!isset($_GET['dismiss_notification'])) {
            return;
        }

        $notification_id = sanitize_text_field($_GET['dismiss_notification']);
        $nonce = sanitize_text_field($_GET['_wpnonce'] ?? '');

        if (!wp_verify_nonce($nonce, "dismiss_notification_{$notification_id}")) {
            return;
        }

        // Find and dismiss the notification across all apps
        global $wpdb;
        $user_id = get_current_user_id();
        $transient_pattern = "_transient_notifications_%_user_{$user_id}";

        $transients = $wpdb->get_results($wpdb->prepare(
            "SELECT option_name FROM {$wpdb->options} 
             WHERE option_name LIKE %s",
            $transient_pattern
        ));

        foreach ($transients as $transient) {
            $cache_key = str_replace('_transient_', '', $transient->option_name);
            $notifications = get_transient($cache_key);

            if (!is_array($notifications)) {
                continue;
            }

            $updated_notifications = array_filter($notifications, function ($notification) use ($notification_id) {
                return ($notification['id'] ?? '') !== $notification_id;
            });

            if (count($updated_notifications) !== count($notifications)) {
                // Found and removed the notification
                if (empty($updated_notifications)) {
                    delete_transient($cache_key);
                } else {
                    set_transient($cache_key, array_values($updated_notifications), self::DEFAULT_EXPIRATION);
                }
                break;
            }
        }

        // Redirect to remove the dismiss parameters from URL
        $redirect_url = remove_query_arg(['dismiss_notification', '_wpnonce']);
        wp_safe_redirect(esc_url_raw($redirect_url));
        exit;
    }

    // =============================================================================
    // PROTECTED INSTANCE METHODS
    // =============================================================================

    /**
     * Parse config or slug parameter and set instance properties.
     *
     * @param Config|string $config_or_slug Config instance or app slug
     * @param string|null $app_name App name for display
     * @return void
     * @throws InvalidArgumentException If parameters are invalid
     */
    protected function parseConfigOrSlug(Config|string $config_or_slug, ?string $app_name): void
    {
        if ($config_or_slug instanceof Config) {
            $this->config = $config_or_slug;
            $this->app_slug = $config_or_slug->slug;
            $this->text_domain = $config_or_slug->get('text_domain', $config_or_slug->slug);
            $this->app_name = $app_name ?? $config_or_slug->get('name', ucfirst(str_replace(['-', '_'], ' ', $config_or_slug->slug)));
        } elseif (is_string($config_or_slug)) {
            $this->config = null;
            $this->app_slug = sanitize_key($config_or_slug);
            $this->text_domain = $this->app_slug;
            $this->app_name = $app_name ?? ucfirst(str_replace(['-', '_'], ' ', $this->app_slug));
        } else {
            throw new InvalidArgumentException('First parameter must be Config instance or string');
        }

        if (empty($this->app_slug)) {
            throw new InvalidArgumentException('App slug cannot be empty');
        }
    }

    /**
     * Register WordPress hooks for this instance.
     *
     * @return void
     */
    protected function registerHooks(): void
    {
        if ($this->hooks_registered || !is_admin()) {
            return;
        }

        // Note: We don't register display hooks per instance
        // Instead, use the global initGlobal() method

        $this->hooks_registered = true;
    }

    /**
     * Get the cache key for storing notifications.
     *
     * @return string Cache key
     */
    protected function getCacheKey(): string
    {
        $user_id = get_current_user_id();
        return "notifications_{$this->app_slug}_user_{$user_id}";
    }

    /**
     * Validate notification type.
     *
     * @param string $type Type to validate
     * @return string Valid type
     */
    protected function validateType(string $type): string
    {
        $type = sanitize_key($type);
        return in_array($type, self::TYPES, true) ? $type : 'info';
    }

    /**
     * Generate a unique notification ID.
     *
     * @return string Unique notification ID
     */
    protected function generateNotificationId(): string
    {
        return 'notif_' . wp_generate_uuid4();
    }

    /**
     * Check if a notification is a duplicate.
     *
     * @param array<string, mixed> $new_notification New notification
     * @param array<array<string, mixed>> $existing_notifications Existing notifications
     * @return bool Whether it's a duplicate
     */
    protected function isDuplicateNotification(array $new_notification, array $existing_notifications): bool
    {
        foreach ($existing_notifications as $existing) {
            if (
                ($existing['message'] ?? '') === ($new_notification['message'] ?? '') &&
                ($existing['type'] ?? '') === ($new_notification['type'] ?? '') &&
                ($existing['app_slug'] ?? '') === ($new_notification['app_slug'] ?? '')
            ) {
                return true;
            }
        }
        return false;
    }

    // =============================================================================
    // STATIC HELPER METHODS
    // =============================================================================

    /**
     * Get the current page key/identifier.
     *
     * @return string Current page key
     */
    protected static function getCurrentPageKeyStatic(): string
    {
        global $pagenow;

        $page = sanitize_text_field($_GET['page'] ?? '');

        if (!empty($page)) {
            return $page;
        }

        return $pagenow ?? '';
    }

    /**
     * Determine if a notification should be displayed on the current page.
     *
     * @param array<string, mixed> $notification Notification data
     * @param string $current_page Current page identifier
     * @return bool Whether to display the notification
     */
    protected static function shouldDisplayNotificationStatic(array $notification, string $current_page): bool
    {
        $allowed_pages = $notification['allowed_pages'] ?? 'current';
        $app_slug = $notification['app_slug'] ?? '';

        if ($allowed_pages === 'all') {
            return true;
        }

        if ($allowed_pages === 'plugin' && str_contains($current_page, $app_slug)) {
            return true;
        }

        if ($allowed_pages === 'current') {
            return true; // Always show on current page
        }

        if (is_array($allowed_pages) && in_array($current_page, $allowed_pages, true)) {
            return true;
        }

        return false;
    }

    /**
     * Render a single notification.
     *
     * @param array<string, mixed> $notification Notification data
     * @return void
     */
    protected static function renderNotificationStatic(array $notification): void
    {
        $message = $notification['message'] ?? '';
        $type = $notification['type'] ?? 'info';
        $dismissible = $notification['dismissible'] ?? true;
        $notification_id = $notification['id'] ?? '';
        $app_slug = $notification['app_slug'] ?? 'plugin';

        $classes = ['notice', "notice-{$type}"];
        if ($dismissible) {
            $classes[] = 'is-dismissible';
        }

        // Try to get app name from config if available
        $app_name = 'Plugin';
        if (!empty($app_slug)) {
            $config = Registry::getConfig($app_slug);
            if ($config) {
                $app_name = $config->get('name', ucfirst(str_replace(['-', '_'], ' ', $app_slug)));
            } else {
                $app_name = ucfirst(str_replace(['-', '_'], ' ', $app_slug));
            }
        }

        $dismiss_url = '';
        if ($dismissible && !empty($notification_id)) {
            $dismiss_url = wp_nonce_url(
                add_query_arg('dismiss_notification', $notification_id),
                "dismiss_notification_{$notification_id}"
            );
        }

        printf(
            '<div class="%s" data-notification-id="%s" data-app-slug="%s">',
            esc_attr(implode(' ', $classes)),
            esc_attr($notification_id),
            esc_attr($app_slug)
        );

        printf(
            '<p><strong>%s:</strong> %s</p>',
            esc_html($app_name),
            wp_kses_post($message)
        );

        if ($dismissible && !empty($dismiss_url)) {
            printf(
                '<button type="button" class="notice-dismiss" onclick="window.location.href=\'%s\'">',
                esc_url($dismiss_url)
            );
            printf(
                '<span class="screen-reader-text">%s</span>',
                esc_html__('Dismiss this notice.')
            );
            echo '</button>';
        }

        echo '</div>';
    }
}
