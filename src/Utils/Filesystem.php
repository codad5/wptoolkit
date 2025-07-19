<?php

/**
 * Filesystem Helper
 *
 * @author Chibueze Aniezeofor <hello@codad5.me>
 * @license GPL-2.0-or-later http://www.gnu.org/licenses/gpl-2.0.txt
 * @link https://github.com/codad5/wptoolkit
 */

declare(strict_types=1);

namespace Codad5\WPToolkit\Utils;

use InvalidArgumentException;

/**
 * Filesystem Helper class for file operations and media management.
 *
 * Provides a clean API for file operations, uploads, media library management,
 * and file metadata handling using WordPress filesystem functions.
 * Now fully object-based with dependency injection support.
 */
class Filesystem
{
    /**
     * WordPress filesystem instance.
     */
    protected $wp_filesystem = null;

    /**
     * Allowed file types for uploads.
     *
     * @var array<string>
     */
    protected array $allowed_types = [];

    /**
     * Upload directory information.
     *
     * @var array<string, string>
     */
    protected array $upload_dir = [];

    /**
     * Application slug for identification.
     */
    protected string $app_slug;

    /**
     * Text domain for translations.
     */
    protected string $text_domain;

    /**
     * Config instance (optional dependency).
     */
    protected ?Config $config = null;

    /**
     * Base upload directory for this app.
     */
    protected string $app_upload_dir;

    /**
     * Constructor for creating a new Filesystem instance.
     *
     * @param Config|string $config_or_slug Config instance or app slug
     * @param array<string> $allowed_types Additional allowed file types
     * @throws InvalidArgumentException If parameters are invalid
     */
    public function __construct(Config|string $config_or_slug, array $allowed_types = [])
    {
        $this->parseConfigOrSlug($config_or_slug);
        $this->initializeFilesystem();
        $this->setupAllowedTypes($allowed_types);
        $this->setupUploadDirectory();
    }

    /**
     * Static factory method for creating Filesystem instances.
     *
     * @param Config|string $config_or_slug Config instance or app slug
     * @param array<string> $allowed_types Additional allowed file types
     * @return static New Filesystem instance
     */
    public static function create(Config|string $config_or_slug, array $allowed_types = []): static
    {
        return new static($config_or_slug, $allowed_types);
    }

    /**
     * Read file contents.
     *
     * @param string $file_path Path to the file
     * @return string|false File contents or false on failure
     */
    public function getContents(string $file_path)
    {
        if (!$this->wp_filesystem) {
            return false;
        }

        $file_path = $this->sanitizeFilePath($file_path);

        if (!$this->fileExists($file_path)) {
            return false;
        }

        return $this->wp_filesystem->get_contents($file_path);
    }

    /**
     * Write contents to a file.
     *
     * @param string $file_path Path to the file
     * @param string $contents File contents
     * @param int $mode File permissions mode
     * @return bool Success status
     */
    public function putContents(string $file_path, string $contents, int $mode = 0644): bool
    {
        if (!$this->wp_filesystem) {
            return false;
        }

        $file_path = $this->sanitizeFilePath($file_path);

        // Create directory if it doesn't exist
        $dir = dirname($file_path);
        if (!$this->wp_filesystem->is_dir($dir)) {
            if (!$this->createDirectory($dir)) {
                return false;
            }
        }

        $result = $this->wp_filesystem->put_contents($file_path, $contents, $mode);
        return $result !== false;
    }

    /**
     * Check if a file exists.
     *
     * @param string $file_path Path to the file
     * @return bool Whether the file exists
     */
    public function fileExists(string $file_path): bool
    {
        if (!$this->wp_filesystem) {
            return file_exists($file_path);
        }

        $file_path = $this->sanitizeFilePath($file_path);
        return $this->wp_filesystem->exists($file_path);
    }

    /**
     * Delete a file.
     *
     * @param string $file_path Path to the file
     * @return bool Success status
     */
    public function deleteFile(string $file_path): bool
    {
        if (!$this->wp_filesystem) {
            return false;
        }

        $file_path = $this->sanitizeFilePath($file_path);

        if (!$this->fileExists($file_path)) {
            return true; // Already deleted
        }

        return $this->wp_filesystem->delete($file_path);
    }

    /**
     * Copy a file.
     *
     * @param string $source Source file path
     * @param string $destination Destination file path
     * @param bool $overwrite Whether to overwrite existing file
     * @return bool Success status
     */
    public function copyFile(string $source, string $destination, bool $overwrite = false): bool
    {
        if (!$this->wp_filesystem) {
            return false;
        }

        $source = $this->sanitizeFilePath($source);
        $destination = $this->sanitizeFilePath($destination);

        if (!$this->fileExists($source)) {
            return false;
        }

        if (!$overwrite && $this->fileExists($destination)) {
            return false;
        }

        // Create destination directory if needed
        $dest_dir = dirname($destination);
        if (!$this->wp_filesystem->is_dir($dest_dir)) {
            if (!$this->createDirectory($dest_dir)) {
                return false;
            }
        }

        return $this->wp_filesystem->copy($source, $destination, $overwrite);
    }

    /**
     * Move a file.
     *
     * @param string $source Source file path
     * @param string $destination Destination file path
     * @param bool $overwrite Whether to overwrite existing file
     * @return bool Success status
     */
    public function moveFile(string $source, string $destination, bool $overwrite = false): bool
    {
        if (!$this->wp_filesystem) {
            return false;
        }

        $source = $this->sanitizeFilePath($source);
        $destination = $this->sanitizeFilePath($destination);

        if (!$this->fileExists($source)) {
            return false;
        }

        if (!$overwrite && $this->fileExists($destination)) {
            return false;
        }

        // Create destination directory if needed
        $dest_dir = dirname($destination);
        if (!$this->wp_filesystem->is_dir($dest_dir)) {
            if (!$this->createDirectory($dest_dir)) {
                return false;
            }
        }

        return $this->wp_filesystem->move($source, $destination, $overwrite);
    }

    /**
     * Create a directory.
     *
     * @param string $dir_path Directory path
     * @param int $mode Directory permissions
     * @param bool $recursive Create parent directories
     * @return bool Success status
     */
    public function createDirectory(string $dir_path, int $mode = 0755, bool $recursive = true): bool
    {
        if (!$this->wp_filesystem) {
            return false;
        }

        $dir_path = $this->sanitizeFilePath($dir_path);

        if ($this->wp_filesystem->is_dir($dir_path)) {
            return true;
        }

        return $this->wp_filesystem->mkdir($dir_path, $mode, $recursive);
    }

    /**
     * Delete a directory.
     *
     * @param string $dir_path Directory path
     * @param bool $recursive Delete recursively
     * @return bool Success status
     */
    public function deleteDirectory(string $dir_path, bool $recursive = false): bool
    {
        if (!$this->wp_filesystem) {
            return false;
        }

        $dir_path = $this->sanitizeFilePath($dir_path);

        if (!$this->wp_filesystem->is_dir($dir_path)) {
            return true;
        }

        return $this->wp_filesystem->rmdir($dir_path, $recursive);
    }

    /**
     * Get file size.
     *
     * @param string $file_path Path to the file
     * @return int|false File size in bytes or false on failure
     */
    public function getFileSize(string $file_path)
    {
        if (!$this->wp_filesystem) {
            return false;
        }

        $file_path = $this->sanitizeFilePath($file_path);

        if (!$this->fileExists($file_path)) {
            return false;
        }

        return $this->wp_filesystem->size($file_path);
    }

    /**
     * Get file modification time.
     *
     * @param string $file_path Path to the file
     * @return int|false Modification timestamp or false on failure
     */
    public function getModificationTime(string $file_path)
    {
        if (!$this->wp_filesystem) {
            return false;
        }

        $file_path = $this->sanitizeFilePath($file_path);

        if (!$this->fileExists($file_path)) {
            return false;
        }

        return $this->wp_filesystem->mtime($file_path);
    }

    /**
     * Get file permissions.
     *
     * @param string $file_path Path to the file
     * @return string|false File permissions or false on failure
     */
    public function getFilePermissions(string $file_path)
    {
        if (!$this->wp_filesystem) {
            return false;
        }

        $file_path = $this->sanitizeFilePath($file_path);

        if (!$this->fileExists($file_path)) {
            return false;
        }

        return $this->wp_filesystem->getchmod($file_path);
    }

    /**
     * Set file permissions.
     *
     * @param string $file_path Path to the file
     * @param int $mode Permission mode
     * @param bool $recursive Apply recursively for directories
     * @return bool Success status
     */
    public function setFilePermissions(string $file_path, int $mode, bool $recursive = false): bool
    {
        if (!$this->wp_filesystem) {
            return false;
        }

        $file_path = $this->sanitizeFilePath($file_path);

        if (!$this->fileExists($file_path)) {
            return false;
        }

        return $this->wp_filesystem->chmod($file_path, $mode, $recursive);
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
    public function uploadToMediaLibrary(array $file_data, string $title = '', string $description = '', int $parent_post_id = 0)
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
        if (!$this->isAllowedFileType($file_extension)) {
            return false;
        }

        // Handle the upload
        $upload_overrides = [
            'test_form' => false,
            'mimes' => $this->getAllowedMimeTypes(),
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
    public function getMediaFileInfo(int $attachment_id)
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
            'file_size' => $this->getFileSize($file_path),
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
    public function deleteMediaFile(int $attachment_id, bool $force_delete = true): bool
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
    public function getUniqueFilename(string $filename, string $subdirectory = ''): string
    {
        $upload_dir = $this->getUploadDir($subdirectory);
        $filename = sanitize_file_name($filename);

        return wp_unique_filename($upload_dir['path'], $filename);
    }

    /**
     * Get upload directory information.
     *
     * @param string $subdirectory Optional subdirectory
     * @return array<string, string> Upload directory information
     */
    public function getUploadDir(string $subdirectory = ''): array
    {
        $upload_dir = $this->upload_dir;

        if (!empty($subdirectory)) {
            $subdirectory = trim($subdirectory, '/');
            $upload_dir['path'] .= '/' . $subdirectory;
            $upload_dir['url'] .= '/' . $subdirectory;
        }

        return $upload_dir;
    }

    /**
     * Create app-specific upload directory.
     *
     * @param string $subdirectory Optional subdirectory name
     * @return array<string, string>|false Directory info or false on failure
     */
    public function createAppUploadDir(string $subdirectory = ''): array|false
    {
        $dir_name = $this->app_slug . (!empty($subdirectory) ? '/' . $subdirectory : '');
        $upload_dir = $this->getUploadDir($dir_name);

        if (!$this->createDirectory($upload_dir['path'])) {
            return false;
        }

        // Create .htaccess for security if needed
        $htaccess_path = $upload_dir['path'] . '/.htaccess';
        if (!$this->fileExists($htaccess_path)) {
            $htaccess_content = "Options -Indexes\nDeny from all\n";
            $this->putContents($htaccess_path, $htaccess_content);
        }

        return $upload_dir;
    }

    /**
     * Get file MIME type.
     *
     * @param string $file_path Path to the file
     * @return string|false MIME type or false on failure
     */
    public function getMimeType(string $file_path)
    {
        if (!$this->fileExists($file_path)) {
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
    public function isAllowedFileType(string $file_extension): bool
    {
        $file_extension = strtolower(ltrim($file_extension, '.'));
        return in_array($file_extension, $this->allowed_types, true);
    }

    /**
     * Add allowed file types.
     *
     * @param array<string> $types File extensions to allow
     * @return static For method chaining
     */
    public function addAllowedFileTypes(array $types): static
    {
        foreach ($types as $type) {
            $type = strtolower(ltrim($type, '.'));
            if (!in_array($type, $this->allowed_types, true)) {
                $this->allowed_types[] = $type;
            }
        }
        return $this;
    }

    /**
     * Get allowed MIME types.
     *
     * @return array<string, string> MIME types mapping
     */
    public function getAllowedMimeTypes(): array
    {
        $mime_types = [];

        foreach ($this->allowed_types as $extension) {
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
    public function formatFileSize(int $size, int $precision = 2): string
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
     * Get file information.
     *
     * @param string $file_path Path to the file
     * @return array<string, mixed>|false File information or false on failure
     */
    public function getFileInfo(string $file_path)
    {
        if (!$this->fileExists($file_path)) {
            return false;
        }

        $size = $this->getFileSize($file_path);
        $mtime = $this->getModificationTime($file_path);

        return [
            'path' => $file_path,
            'filename' => basename($file_path),
            'extension' => strtolower(pathinfo($file_path, PATHINFO_EXTENSION)),
            'size' => $size,
            'size_formatted' => $size !== false ? $this->formatFileSize($size) : 'Unknown',
            'mime_type' => $this->getMimeType($file_path),
            'modified' => $mtime,
            'modified_formatted' => $mtime !== false ? wp_date(get_option('date_format') . ' ' . get_option('time_format'), $mtime) : 'Unknown',
            'permissions' => $this->getFilePermissions($file_path),
            'is_allowed' => $this->isAllowedFileType(pathinfo($file_path, PATHINFO_EXTENSION)),
        ];
    }

    /**
     * Scan directory for files.
     *
     * @param string $directory Directory to scan
     * @param bool $recursive Whether to scan recursively
     * @param array<string> $allowed_extensions Filter by extensions (empty = all allowed types)
     * @return array<string> List of found files
     */
    public function scanDirectory(string $directory, bool $recursive = false, array $allowed_extensions = []): array
    {
        $directory = $this->sanitizeFilePath($directory);

        if (!$this->wp_filesystem->is_dir($directory)) {
            return [];
        }

        $files = [];
        $extensions = !empty($allowed_extensions) ? $allowed_extensions : $this->allowed_types;

        $iterator = $recursive
            ? new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($directory))
            : new \DirectoryIterator($directory);

        foreach ($iterator as $file) {
            if ($file->isFile()) {
                $extension = strtolower($file->getExtension());
                if (in_array($extension, $extensions, true)) {
                    $files[] = $file->getPathname();
                }
            }
        }

        return $files;
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
     * Get allowed file types.
     *
     * @return array<string> Allowed file extensions
     */
    public function getAllowedFileTypes(): array
    {
        return $this->allowed_types;
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

    /**
     * Get the app upload directory path.
     *
     * @return string App upload directory
     */
    public function getAppUploadDir(): string
    {
        return $this->app_upload_dir;
    }

    /**
     * Parse config or slug parameter and set instance properties.
     *
     * @param Config|string $config_or_slug Config instance or app slug
     * @return void
     * @throws InvalidArgumentException If parameters are invalid
     */
    protected function parseConfigOrSlug(Config|string $config_or_slug): void
    {
        if ($config_or_slug instanceof Config) {
            $this->config = $config_or_slug;
            $this->app_slug = $config_or_slug->slug;
            $this->text_domain = $config_or_slug->get('text_domain', $config_or_slug->slug);
        } elseif (is_string($config_or_slug)) {
            $this->config = null;
            $this->app_slug = sanitize_key($config_or_slug);
            $this->text_domain = $this->app_slug;
        } else {
            throw new InvalidArgumentException('First parameter must be Config instance or string');
        }

        if (empty($this->app_slug)) {
            throw new InvalidArgumentException('App slug cannot be empty');
        }
    }

    /**
     * Initialize WordPress filesystem.
     *
     * @return void
     * @throws \RuntimeException If filesystem cannot be initialized
     */
    protected function initializeFilesystem(): void
    {
        global $wp_filesystem;

        if (empty($wp_filesystem)) {
            require_once ABSPATH . '/wp-admin/includes/file.php';
            WP_Filesystem();
        }

        $this->wp_filesystem = $wp_filesystem;

        if (!$this->wp_filesystem) {
            throw new \RuntimeException('Failed to initialize WordPress filesystem');
        }
    }

    /**
     * Setup allowed file types.
     *
     * @param array<string> $additional_types Additional file types to allow
     * @return void
     */
    protected function setupAllowedTypes(array $additional_types): void
    {
        $default_types = [
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
        ];

        $this->allowed_types = array_merge($default_types, $additional_types);
        $this->allowed_types = array_unique($this->allowed_types);
    }

    /**
     * Setup upload directory.
     *
     * @return void
     */
    protected function setupUploadDirectory(): void
    {
        $this->upload_dir = wp_upload_dir();
        $this->app_upload_dir = $this->upload_dir['path'] . '/' . $this->app_slug;
    }

    /**
     * Sanitize file path.
     *
     * @param string $file_path File path to sanitize
     * @return string Sanitized file path
     */
    protected function sanitizeFilePath(string $file_path): string
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
