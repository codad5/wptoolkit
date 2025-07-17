<?php

/**
 * Filesystem Helper
 *
 * @author Your Name <username@example.com>
 * @license GPL-2.0-or-later http://www.gnu.org/licenses/gpl-2.0.txt
 * @link https://github.com/szepeviktor/starter-plugin
 */

declare(strict_types=1);

namespace Codad5\WPToolkit\Utils;

/**
 * Filesystem Helper class for file operations and media management.
 *
 * Provides a clean API for file operations, uploads, media library management,
 * and file metadata handling using WordPress filesystem functions.
 */
class Filesystem
{
    /**
     * WordPress filesystem instance.
     */
    protected static $wp_filesystem = null;

    /**
     * Allowed file types for uploads.
     */
    protected static array $allowed_types = [];

    /**
     * Upload directory information.
     */
    protected static array $upload_dir = [];

    /**
     * Initialize the filesystem helper.
     *
     * @param array<string> $allowed_types Additional allowed file types
     * @return bool Success status
     */
    public static function init(array $allowed_types = []): bool
    {
        if (!self::init_wp_filesystem()) {
            return false;
        }

        self::$allowed_types = array_merge([
            'jpg',
            'jpeg',
            'png',
            'gif',
            'webp',
            'svg',
            'pdf',
            'doc',
            'docx',
            'xls',
            'xlsx',
            'ppt',
            'pptx',
            'txt',
            'csv',
            'zip',
            'tar',
            'gz'
        ], $allowed_types);

        self::$upload_dir = wp_upload_dir();

        return true;
    }

    /**
     * Read file contents.
     *
     * @param string $file_path Path to the file
     * @return string|false File contents or false on failure
     */
    public static function get_contents(string $file_path)
    {
        if (!self::init_wp_filesystem()) {
            return false;
        }

        $file_path = self::sanitize_file_path($file_path);

        if (!self::file_exists($file_path)) {
            return false;
        }

        return self::$wp_filesystem->get_contents($file_path);
    }

    /**
     * Write contents to a file.
     *
     * @param string $file_path Path to the file
     * @param string $contents File contents
     * @param int $mode File permissions mode
     * @return bool Success status
     */
    public static function put_contents(string $file_path, string $contents, int $mode = 0644): bool
    {
        if (!self::init_wp_filesystem()) {
            return false;
        }

        $file_path = self::sanitize_file_path($file_path);

        // Create directory if it doesn't exist
        $dir = dirname($file_path);
        if (!self::$wp_filesystem->is_dir($dir)) {
            if (!self::create_directory($dir)) {
                return false;
            }
        }

        $result = self::$wp_filesystem->put_contents($file_path, $contents, $mode);
        return $result !== false;
    }

    /**
     * Check if a file exists.
     *
     * @param string $file_path Path to the file
     * @return bool Whether the file exists
     */
    public static function file_exists(string $file_path): bool
    {
        if (!self::init_wp_filesystem()) {
            return file_exists($file_path);
        }

        $file_path = self::sanitize_file_path($file_path);
        return self::$wp_filesystem->exists($file_path);
    }

    /**
     * Delete a file.
     *
     * @param string $file_path Path to the file
     * @return bool Success status
     */
    public static function delete_file(string $file_path): bool
    {
        if (!self::init_wp_filesystem()) {
            return false;
        }

        $file_path = self::sanitize_file_path($file_path);

        if (!self::file_exists($file_path)) {
            return true; // Already deleted
        }

        return self::$wp_filesystem->delete($file_path);
    }

    /**
     * Copy a file.
     *
     * @param string $source Source file path
     * @param string $destination Destination file path
     * @param bool $overwrite Whether to overwrite existing file
     * @return bool Success status
     */
    public static function copy_file(string $source, string $destination, bool $overwrite = false): bool
    {
        if (!self::init_wp_filesystem()) {
            return false;
        }

        $source = self::sanitize_file_path($source);
        $destination = self::sanitize_file_path($destination);

        if (!self::file_exists($source)) {
            return false;
        }

        if (!$overwrite && self::file_exists($destination)) {
            return false;
        }

        // Create destination directory if needed
        $dest_dir = dirname($destination);
        if (!self::$wp_filesystem->is_dir($dest_dir)) {
            if (!self::create_directory($dest_dir)) {
                return false;
            }
        }

        return self::$wp_filesystem->copy($source, $destination, $overwrite);
    }

    /**
     * Move a file.
     *
     * @param string $source Source file path
     * @param string $destination Destination file path
     * @param bool $overwrite Whether to overwrite existing file
     * @return bool Success status
     */
    public static function move_file(string $source, string $destination, bool $overwrite = false): bool
    {
        if (!self::init_wp_filesystem()) {
            return false;
        }

        $source = self::sanitize_file_path($source);
        $destination = self::sanitize_file_path($destination);

        if (!self::file_exists($source)) {
            return false;
        }

        if (!$overwrite && self::file_exists($destination)) {
            return false;
        }

        // Create destination directory if needed
        $dest_dir = dirname($destination);
        if (!self::$wp_filesystem->is_dir($dest_dir)) {
            if (!self::create_directory($dest_dir)) {
                return false;
            }
        }

        return self::$wp_filesystem->move($source, $destination, $overwrite);
    }

    /**
     * Create a directory.
     *
     * @param string $dir_path Directory path
     * @param int $mode Directory permissions
     * @param bool $recursive Create parent directories
     * @return bool Success status
     */
    public static function create_directory(string $dir_path, int $mode = 0755, bool $recursive = true): bool
    {
        if (!self::init_wp_filesystem()) {
            return false;
        }

        $dir_path = self::sanitize_file_path($dir_path);

        if (self::$wp_filesystem->is_dir($dir_path)) {
            return true;
        }

        return self::$wp_filesystem->mkdir($dir_path, $mode, $recursive);
    }

    /**
     * Delete a directory.
     *
     * @param string $dir_path Directory path
     * @param bool $recursive Delete recursively
     * @return bool Success status
     */
    public static function delete_directory(string $dir_path, bool $recursive = false): bool
    {
        if (!self::init_wp_filesystem()) {
            return false;
        }

        $dir_path = self::sanitize_file_path($dir_path);

        if (!self::$wp_filesystem->is_dir($dir_path)) {
            return true;
        }

        return self::$wp_filesystem->rmdir($dir_path, $recursive);
    }

    /**
     * Get file size.
     *
     * @param string $file_path Path to the file
     * @return int|false File size in bytes or false on failure
     */
    public static function get_file_size(string $file_path)
    {
        if (!self::init_wp_filesystem()) {
            return false;
        }

        $file_path = self::sanitize_file_path($file_path);

        if (!self::file_exists($file_path)) {
            return false;
        }

        return self::$wp_filesystem->size($file_path);
    }

    /**
     * Get file modification time.
     *
     * @param string $file_path Path to the file
     * @return int|false Modification timestamp or false on failure
     */
    public static function get_modification_time(string $file_path)
    {
        if (!self::init_wp_filesystem()) {
            return false;
        }

        $file_path = self::sanitize_file_path($file_path);

        if (!self::file_exists($file_path)) {
            return false;
        }

        return self::$wp_filesystem->mtime($file_path);
    }

    /**
     * Get file permissions.
     *
     * @param string $file_path Path to the file
     * @return string|false File permissions or false on failure
     */
    public static function get_file_permissions(string $file_path)
    {
        if (!self::init_wp_filesystem()) {
            return false;
        }

        $file_path = self::sanitize_file_path($file_path);

        if (!self::file_exists($file_path)) {
            return false;
        }

        return self::$wp_filesystem->getchmod($file_path);
    }

    /**
     * Set file permissions.
     *
     * @param string $file_path Path to the file
     * @param int $mode Permission mode
     * @param bool $recursive Apply recursively for directories
     * @return bool Success status
     */
    public static function set_file_permissions(string $file_path, int $mode, bool $recursive = false): bool
    {
        if (!self::init_wp_filesystem()) {
            return false;
        }

        $file_path = self::sanitize_file_path($file_path);

        if (!self::file_exists($file_path)) {
            return false;
        }

        return self::$wp_filesystem->chmod($file_path, $mode, $recursive);
    }

    /**
     * Upload a file to WordPress media library.
     *
     * @param array<string, mixed> $file_data File data ($_FILES format)
     * @param string $title Optional file title
     * @param string $description Optional file description
     * @param int $parent_post_id Optional parent post ID
     * @return int|false Attachment ID or false on failure
     */
    public static function upload_to_media_library(array $file_data, string $title = '', string $description = '', int $parent_post_id = 0)
    {
        if (!function_exists('wp_handle_upload')) {
            require_once ABSPATH . 'wp-admin/includes/file.php';
        }

        if (!function_exists('wp_generate_attachment_metadata')) {
            require_once ABSPATH . 'wp-admin/includes/image.php';
        }

        if (!function_exists('media_handle_upload')) {
            require_once ABSPATH . 'wp-admin/includes/media.php';
        }

        // Validate file type
        $file_extension = strtolower(pathinfo($file_data['name'], PATHINFO_EXTENSION));
        if (!self::is_allowed_file_type($file_extension)) {
            return false;
        }

        // Handle the upload
        $upload_overrides = [
            'test_form' => false,
            'mimes' => self::get_allowed_mime_types(),
        ];

        $uploaded_file = wp_handle_upload($file_data, $upload_overrides);

        if (isset($uploaded_file['error'])) {
            return false;
        }

        // Create attachment
        $attachment_data = [
            'post_mime_type' => $uploaded_file['type'],
            'post_title' => $title ?: sanitize_file_name(pathinfo($uploaded_file['file'], PATHINFO_FILENAME)),
            'post_content' => $description,
            'post_status' => 'inherit'
        ];

        $attachment_id = wp_insert_attachment($attachment_data, $uploaded_file['file'], $parent_post_id);

        if (is_wp_error($attachment_id)) {
            return false;
        }

        // Generate metadata
        $attachment_metadata = wp_generate_attachment_metadata($attachment_id, $uploaded_file['file']);
        wp_update_attachment_metadata($attachment_id, $attachment_metadata);

        return $attachment_id;
    }

    /**
     * Get file information from media library.
     *
     * @param int $attachment_id Attachment ID
     * @return array<string, mixed>|false File information or false if not found
     */
    public static function get_media_file_info(int $attachment_id)
    {
        $attachment = get_post($attachment_id);

        if (!$attachment || $attachment->post_type !== 'attachment') {
            return false;
        }

        $file_path = get_attached_file($attachment_id);
        $file_url = wp_get_attachment_url($attachment_id);
        $metadata = wp_get_attachment_metadata($attachment_id);

        return [
            'id' => $attachment_id,
            'title' => $attachment->post_title,
            'description' => $attachment->post_content,
            'filename' => basename($file_path),
            'file_path' => $file_path,
            'url' => $file_url,
            'mime_type' => $attachment->post_mime_type,
            'file_size' => self::get_file_size($file_path),
            'upload_date' => $attachment->post_date,
            'metadata' => $metadata,
        ];
    }

    /**
     * Delete file from media library.
     *
     * @param int $attachment_id Attachment ID
     * @param bool $force_delete Whether to bypass trash
     * @return bool Success status
     */
    public static function delete_media_file(int $attachment_id, bool $force_delete = true): bool
    {
        $result = wp_delete_attachment($attachment_id, $force_delete);
        return $result !== false;
    }

    /**
     * Create a unique filename in upload directory.
     *
     * @param string $filename Original filename
     * @param string $subdirectory Optional subdirectory
     * @return string Unique filename
     */
    public static function get_unique_filename(string $filename, string $subdirectory = ''): string
    {
        $upload_dir = self::get_upload_dir($subdirectory);
        $filename = sanitize_file_name($filename);

        return wp_unique_filename($upload_dir['path'], $filename);
    }

    /**
     * Get upload directory information.
     *
     * @param string $subdirectory Optional subdirectory
     * @return array<string, string> Upload directory information
     */
    public static function get_upload_dir(string $subdirectory = ''): array
    {
        $upload_dir = wp_upload_dir();

        if (!empty($subdirectory)) {
            $subdirectory = trim($subdirectory, '/');
            $upload_dir['path'] .= '/' . $subdirectory;
            $upload_dir['url'] .= '/' . $subdirectory;
        }

        return $upload_dir;
    }

    /**
     * Create plugin-specific upload directory.
     *
     * @param string $subdirectory Optional subdirectory name
     * @return array<string, string>|false Directory info or false on failure
     */
    public static function create_plugin_upload_dir(string $subdirectory = ''): array|false
    {
        $plugin_slug = Config::get('slug') ?? 'wp-plugin';
        $dir_name = $plugin_slug . (!empty($subdirectory) ? '/' . $subdirectory : '');

        $upload_dir = self::get_upload_dir($dir_name);

        if (!self::create_directory($upload_dir['path'])) {
            return false;
        }

        // Create .htaccess for security if needed
        $htaccess_path = $upload_dir['path'] . '/.htaccess';
        if (!self::file_exists($htaccess_path)) {
            $htaccess_content = "Options -Indexes\nDeny from all\n";
            self::put_contents($htaccess_path, $htaccess_content);
        }

        return $upload_dir;
    }

    /**
     * Get file MIME type.
     *
     * @param string $file_path Path to the file
     * @return string|false MIME type or false on failure
     */
    public static function get_mime_type(string $file_path)
    {
        if (!self::file_exists($file_path)) {
            return false;
        }

        // Use WordPress function if available
        if (function_exists('wp_check_filetype')) {
            $file_info = wp_check_filetype($file_path);
            return $file_info['type'] ?: false;
        }

        // Fallback to PHP function
        if (function_exists('mime_content_type')) {
            return mime_content_type($file_path);
        }

        return false;
    }

    /**
     * Check if file type is allowed.
     *
     * @param string $file_extension File extension
     * @return bool Whether the file type is allowed
     */
    public static function is_allowed_file_type(string $file_extension): bool
    {
        $file_extension = strtolower(ltrim($file_extension, '.'));
        return in_array($file_extension, self::$allowed_types, true);
    }

    /**
     * Add allowed file types.
     *
     * @param array<string> $types File extensions to allow
     * @return void
     */
    public static function add_allowed_file_types(array $types): void
    {
        foreach ($types as $type) {
            $type = strtolower(ltrim($type, '.'));
            if (!in_array($type, self::$allowed_types, true)) {
                self::$allowed_types[] = $type;
            }
        }
    }

    /**
     * Get allowed MIME types.
     *
     * @return array<string, string> MIME types mapping
     */
    public static function get_allowed_mime_types(): array
    {
        $mime_types = [];

        foreach (self::$allowed_types as $extension) {
            $file_info = wp_check_filetype('file.' . $extension);
            if ($file_info['type']) {
                $mime_types[$extension] = $file_info['type'];
            }
        }

        return $mime_types;
    }

    /**
     * Format file size for display.
     *
     * @param int $size File size in bytes
     * @param int $precision Number of decimal places
     * @return string Formatted file size
     */
    public static function format_file_size(int $size, int $precision = 2): string
    {
        if ($size === 0) {
            return '0 B';
        }

        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $base = log($size, 1024);
        $unit_index = floor($base);

        return round(pow(1024, $base - $unit_index), $precision) . ' ' . $units[$unit_index];
    }

    /**
     * Initialize WordPress filesystem.
     *
     * @return bool Success status
     */
    protected static function init_wp_filesystem(): bool
    {
        if (self::$wp_filesystem !== null) {
            return true;
        }

        global $wp_filesystem;

        if (empty($wp_filesystem)) {
            require_once ABSPATH . '/wp-admin/includes/file.php';
            WP_Filesystem();
        }

        self::$wp_filesystem = $wp_filesystem;
        return self::$wp_filesystem !== null;
    }

    /**
     * Sanitize file path.
     *
     * @param string $file_path File path to sanitize
     * @return string Sanitized file path
     */
    protected static function sanitize_file_path(string $file_path): string
    {
        // Remove any null bytes
        $file_path = str_replace("\0", '', $file_path);

        // Normalize directory separators
        $file_path = str_replace('\\', '/', $file_path);

        // Remove any dangerous path components
        $file_path = preg_replace('/\.\.+/', '.', $file_path);

        return $file_path;
    }
}
