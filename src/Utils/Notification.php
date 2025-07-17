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


/**
 * Notification Helper class for managing admin notices and notifications.
 *
 * Provides a clean API for displaying temporary messages in the WordPress admin
 * with support for different notification types and page targeting.
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
     * Initialize the notification system.
     *
     * Should be called during plugin initialization to register hooks.
     *
     * @return bool Whether initialization was successful
     */
    public static function init(): bool
    {
        if (!is_admin()) {
            return false;
        }

        add_action('admin_notices', [self::class, 'display_notifications']);
        add_action('admin_init', [self::class, 'maybe_dismiss_notification']);

        return true;
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
    public static function add(
        string $message,
        string $type = 'success',
        string|array $allowed_pages = 'current',
        ?int $expiration = null,
        bool $dismissible = true
    ): bool {
        // Validate and sanitize inputs
        $type = self::validate_type($type);
        $message = wp_kses_post($message);
        $expiration = $expiration ?? self::DEFAULT_EXPIRATION;

        if (empty($message)) {
            return false;
        }

        // Create notification data
        $notification = [
            'id' => self::generate_notification_id(),
            'message' => $message,
            'type' => $type,
            'allowed_pages' => $allowed_pages,
            'dismissible' => $dismissible,
            'created_at' => time(),
        ];

        // Get existing notifications
        $notifications = self::get_notifications();

        // Prevent duplicate notifications
        if (self::is_duplicate_notification($notification, $notifications)) {
            return true; // Already exists, consider it successful
        }

        // Add new notification
        $notifications[] = $notification;

        // Store notifications
        return set_transient(
            self::get_cache_key(),
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
    public static function success(
        string $message,
        string|array $allowed_pages = 'current',
        ?int $expiration = null
    ): bool {
        return self::add($message, 'success', $allowed_pages, $expiration);
    }

    /**
     * Add an error notification.
     *
     * @param string $message The notification message
     * @param string|array<string> $allowed_pages Page targeting
     * @param int|null $expiration Expiration time in seconds
     * @return bool Success status
     */
    public static function error(
        string $message,
        string|array $allowed_pages = 'current',
        ?int $expiration = null
    ): bool {
        return self::add($message, 'error', $allowed_pages, $expiration);
    }

    /**
     * Add a warning notification.
     *
     * @param string $message The notification message
     * @param string|array<string> $allowed_pages Page targeting
     * @param int|null $expiration Expiration time in seconds
     * @return bool Success status
     */
    public static function warning(
        string $message,
        string|array $allowed_pages = 'current',
        ?int $expiration = null
    ): bool {
        return self::add($message, 'warning', $allowed_pages, $expiration);
    }

    /**
     * Add an info notification.
     *
     * @param string $message The notification message
     * @param string|array<string> $allowed_pages Page targeting
     * @param int|null $expiration Expiration time in seconds
     * @return bool Success status
     */
    public static function info(
        string $message,
        string|array $allowed_pages = 'current',
        ?int $expiration = null
    ): bool {
        return self::add($message, 'info', $allowed_pages, $expiration);
    }

    /**
     * Get all notifications for the current user.
     *
     * @return array<array<string, mixed>> Array of notifications
     */
    public static function get_notifications(): array
    {
        $notifications = get_transient(self::get_cache_key());

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
     * Clear all notifications for the current user.
     *
     * @return bool True on success, false on failure
     */
    public static function clear(): bool
    {
        return delete_transient(self::get_cache_key());
    }

    /**
     * Dismiss a specific notification by ID.
     *
     * @param string $notification_id The notification ID to dismiss
     * @return bool True on success, false on failure
     */
    public static function dismiss(string $notification_id): bool
    {
        $notifications = self::get_notifications();
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
            return self::clear();
        }

        return set_transient(
            self::get_cache_key(),
            $updated_notifications,
            self::DEFAULT_EXPIRATION
        );
    }

    /**
     * Display notifications in the WordPress admin area.
     *
     * @return void
     */
    public static function display_notifications(): void
    {
        $notifications = self::get_notifications();

        if (empty($notifications)) {
            return;
        }

        $current_page = self::get_current_page_key();
        $displayed_notifications = [];
        $requeue_notifications = [];

        foreach ($notifications as $notification) {
            if (self::should_display_notification($notification, $current_page)) {
                self::render_notification($notification);
                $displayed_notifications[] = $notification['id'] ?? '';
            } else {
                $requeue_notifications[] = $notification;
            }
        }

        // Update notifications list (remove displayed ones)
        if (!empty($displayed_notifications)) {
            if (empty($requeue_notifications)) {
                self::clear();
            } else {
                set_transient(
                    self::get_cache_key(),
                    $requeue_notifications,
                    self::DEFAULT_EXPIRATION
                );
            }
        }
    }

    /**
     * Handle notification dismissal via AJAX or GET request.
     *
     * @return void
     */
    public static function maybe_dismiss_notification(): void
    {
        if (!isset($_GET['dismiss_notification'])) {
            return;
        }

        $notification_id = sanitize_text_field($_GET['dismiss_notification']);
        $nonce = sanitize_text_field($_GET['_wpnonce'] ?? '');

        if (!wp_verify_nonce($nonce, "dismiss_notification_{$notification_id}")) {
            return;
        }

        self::dismiss($notification_id);

        // Redirect to remove the dismiss parameters from URL
        $redirect_url = remove_query_arg(['dismiss_notification', '_wpnonce']);
        wp_safe_redirect(esc_url_raw($redirect_url));
        exit;
    }

    /**
     * Determine if a notification should be displayed on the current page.
     *
     * @param array<string, mixed> $notification Notification data
     * @param string $current_page Current page identifier
     * @return bool Whether to display the notification
     */
    protected static function should_display_notification(array $notification, string $current_page): bool
    {
        $allowed_pages = $notification['allowed_pages'] ?? 'current';

        if ($allowed_pages === 'all') {
            return true;
        }

        if ($allowed_pages === 'plugin' && self::is_plugin_page($current_page)) {
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
    protected static function render_notification(array $notification): void
    {
        $message = $notification['message'] ?? '';
        $type = $notification['type'] ?? 'info';
        $dismissible = $notification['dismissible'] ?? true;
        $notification_id = $notification['id'] ?? '';

        $classes = ['notice', "notice-{$type}"];
        if ($dismissible) {
            $classes[] = 'is-dismissible';
        }

        $plugin_name = Config::get('name') ?? 'Plugin';
        $dismiss_url = '';

        if ($dismissible && !empty($notification_id)) {
            $dismiss_url = wp_nonce_url(
                add_query_arg('dismiss_notification', $notification_id),
                "dismiss_notification_{$notification_id}"
            );
        }

        printf(
            '<div class="%s" data-notification-id="%s">',
            esc_attr(implode(' ', $classes)),
            esc_attr($notification_id)
        );

        printf(
            '<p><strong>%s:</strong> %s</p>',
            esc_html($plugin_name),
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

    /**
     * Validate notification type.
     *
     * @param string $type Type to validate
     * @return string Valid type
     */
    protected static function validate_type(string $type): string
    {
        $type = sanitize_key($type);
        return in_array($type, self::TYPES, true) ? $type : 'info';
    }

    /**
     * Generate a unique notification ID.
     *
     * @return string Unique notification ID
     */
    protected static function generate_notification_id(): string
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
    protected static function is_duplicate_notification(array $new_notification, array $existing_notifications): bool
    {
        foreach ($existing_notifications as $existing) {
            if (
                ($existing['message'] ?? '') === ($new_notification['message'] ?? '') &&
                ($existing['type'] ?? '') === ($new_notification['type'] ?? '')
            ) {
                return true;
            }
        }
        return false;
    }

    /**
     * Get the current page key/identifier.
     *
     * @return string Current page key
     */
    protected static function get_current_page_key(): string
    {
        global $pagenow;

        $page = sanitize_text_field($_GET['page'] ?? '');

        if (!empty($page)) {
            return $page;
        }

        return $pagenow ?? '';
    }

    /**
     * Check if the current page is a plugin-specific page.
     *
     * @param string $current_page Current page identifier
     * @return bool Whether it's a plugin page
     */
    protected static function is_plugin_page(string $current_page): bool
    {
        $plugin_slug = Config::get('slug') ?? '';

        if (empty($plugin_slug)) {
            return false;
        }

        return str_contains($current_page, $plugin_slug);
    }

    /**
     * Get the cache key for storing notifications.
     *
     * @return string Cache key
     */
    protected static function get_cache_key(): string
    {
        $user_id = get_current_user_id();
        $plugin_slug = Config::get('slug') ?? 'wp_plugin';

        return "notifications_{$plugin_slug}_user_{$user_id}";
    }
}
