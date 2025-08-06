<?php

/**
 * Enhanced Filesystem Helper extending WordPress Filesystem Base
 *
 * @author Chibueze Aniezeofor <hello@codad5.me>
 * @license GPL-2.0-or-later http://www.gnu.org/licenses/gpl-2.0.txt
 * @link https://github.com/codad5/wptoolkit
 */

declare(strict_types=1);

namespace Codad5\WPToolkit\Utils;

use InvalidArgumentException;

/**
 * Enhanced Filesystem class extending WordPress base with native PHP implementation.
 *
 * Provides a reliable API for file operations, uploads, media library management,
 * and file metadata handling using native PHP functions with WordPress integration.
 */
class Filesystem
{
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

		$this->method = 'native_php';
		$this->parseConfigOrSlug($config_or_slug);
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

	// ============================================================================
	// WP_Filesystem_Base Abstract Method Implementations
	// ============================================================================

	/**
	 * Connects filesystem.
	 *
	 * @since 2.5.0
	 *
	 * @return bool True on success, false on failure
	 */
	public function connect(): bool
	{
		return true; // Native PHP filesystem is always "connected"
	}

	/**
	 * Reads entire file into a string.
	 *
	 * @since 2.5.0
	 *
	 * @param string $file Name of the file to read
	 * @return string|false Read data on success, false on failure
	 */
	public function get_contents($file)
	{
		$file = $this->sanitizeFilePath($file);

		if (!$this->exists($file) || !$this->is_readable($file)) {
			return false;
		}

		return @file_get_contents($file);
	}

	/**
	 * Reads entire file into an array.
	 *
	 * @since 2.5.0
	 *
	 * @param string $file Path to the file
	 * @return array|false File contents in an array on success, false on failure
	 */
	public function get_contents_array($file)
	{
		$file = $this->sanitizeFilePath($file);

		if (!$this->exists($file) || !$this->is_readable($file)) {
			return false;
		}

		$contents = @file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
		return $contents !== false ? $contents : false;
	}

	/**
	 * Writes a string to a file.
	 *
	 * @since 2.5.0
	 *
	 * @param string $file Remote path to the file where to write the data
	 * @param string $contents The data to write
	 * @param int|false $mode Optional. The file permissions as octal number, usually 0644
	 * @return bool True on success, false on failure
	 */
	public function put_contents($file, $contents, $mode = false): bool
	{
		$file = $this->sanitizeFilePath($file);

		// Create directory if it doesn't exist
		$dir = dirname($file);
		if (!$this->is_dir($dir)) {
			if (!$this->mkdir($dir, 0755)) {
				return false;
			}
		}

		$result = @file_put_contents($file, $contents, LOCK_EX);

		if ($result !== false && $mode !== false) {
			$this->chmod($file, $mode);
		}

		return $result !== false;
	}

	/**
	 * Gets the current working directory.
	 *
	 * @since 2.5.0
	 *
	 * @return string|false The current working directory on success, false on failure
	 */
	public function cwd()
	{
		return @getcwd();
	}

	/**
	 * Changes current directory.
	 *
	 * @since 2.5.0
	 *
	 * @param string $dir The new current directory
	 * @return bool True on success, false on failure
	 */
	public function chdir($dir): bool
	{
		return @chdir($this->sanitizeFilePath($dir));
	}

	/**
	 * Changes the file group.
	 *
	 * @since 2.5.0
	 *
	 * @param string $file Path to the file
	 * @param string|int $group A group name or number
	 * @param bool $recursive Optional. If set to true, changes file group recursively
	 * @return bool True on success, false on failure
	 */
	public function chgrp($file, $group, $recursive = false): bool
	{
		$file = $this->sanitizeFilePath($file);

		if (!function_exists('chgrp')) {
			return false;
		}

		if ($recursive && $this->is_dir($file)) {
			$iterator = new \RecursiveIteratorIterator(
				new \RecursiveDirectoryIterator($file, \RecursiveDirectoryIterator::SKIP_DOTS),
				\RecursiveIteratorIterator::SELF_FIRST
			);

			foreach ($iterator as $item) {
				if (!@chgrp($item->getPathname(), $group)) {
					return false;
				}
			}
		}

		return @chgrp($file, $group);
	}

	/**
	 * Changes filesystem permissions.
	 *
	 * @since 2.5.0
	 *
	 * @param string $file Path to the file
	 * @param int|false $mode Optional. The permissions as octal number, usually 0644 for files, 0755 for directories
	 * @param bool $recursive Optional. If set to true, changes file permissions recursively
	 * @return bool True on success, false on failure
	 */
	public function chmod($file, $mode = false, $recursive = false): bool
	{
		if ($mode === false) {
			return true;
		}

		$file = $this->sanitizeFilePath($file);

		if ($recursive && $this->is_dir($file)) {
			$iterator = new \RecursiveIteratorIterator(
				new \RecursiveDirectoryIterator($file, \RecursiveDirectoryIterator::SKIP_DOTS),
				\RecursiveIteratorIterator::SELF_FIRST
			);

			foreach ($iterator as $item) {
				$item_mode = $item->isDir() ? $mode : ($mode & ~0111); // Remove execute for files
				if (!@chmod($item->getPathname(), $item_mode)) {
					return false;
				}
			}
		}

		return @chmod($file, $mode);
	}

	/**
	 * Changes the owner of a file or directory.
	 *
	 * @since 2.5.0
	 *
	 * @param string $file Path to the file or directory
	 * @param string|int $owner A user name or number
	 * @param bool $recursive Optional. If set to true, changes file owner recursively
	 * @return bool True on success, false on failure
	 */
	public function chown($file, $owner, $recursive = false): bool
	{
		$file = $this->sanitizeFilePath($file);

		if (!function_exists('chown')) {
			return false;
		}

		if ($recursive && $this->is_dir($file)) {
			$iterator = new \RecursiveIteratorIterator(
				new \RecursiveDirectoryIterator($file, \RecursiveDirectoryIterator::SKIP_DOTS),
				\RecursiveIteratorIterator::SELF_FIRST
			);

			foreach ($iterator as $item) {
				if (!@chown($item->getPathname(), $owner)) {
					return false;
				}
			}
		}

		return @chown($file, $owner);
	}

	/**
	 * Gets the file owner.
	 *
	 * @since 2.5.0
	 *
	 * @param string $file Path to the file
	 * @return string|false Username of the owner on success, false on failure
	 */
	public function owner($file)
	{
		$file = $this->sanitizeFilePath($file);

		if (!$this->exists($file)) {
			return false;
		}

		$uid = @fileowner($file);
		if ($uid === false) {
			return false;
		}

		if (function_exists('posix_getpwuid')) {
			$user = @posix_getpwuid($uid);
			return $user !== false ? $user['name'] : (string)$uid;
		}

		return (string)$uid;
	}

	/**
	 * Gets the file's group.
	 *
	 * @since 2.5.0
	 *
	 * @param string $file Path to the file
	 * @return string|false The group on success, false on failure
	 */
	public function group($file)
	{
		$file = $this->sanitizeFilePath($file);

		if (!$this->exists($file)) {
			return false;
		}

		$gid = @filegroup($file);
		if ($gid === false) {
			return false;
		}

		if (function_exists('posix_getgrgid')) {
			$group = @posix_getgrgid($gid);
			return $group !== false ? $group['name'] : (string)$gid;
		}

		return (string)$gid;
	}

	/**
	 * Copies a file.
	 *
	 * @since 2.5.0
	 *
	 * @param string $source Path to the source file
	 * @param string $destination Path to the destination file
	 * @param bool $overwrite Optional. Whether to overwrite the destination file if it exists
	 * @param int|false $mode Optional. The permissions as octal number, usually 0644 for files, 0755 for dirs
	 * @return bool True on success, false on failure
	 */
	public function copy($source, $destination, $overwrite = false, $mode = false): bool
	{
		$source = $this->sanitizeFilePath($source);
		$destination = $this->sanitizeFilePath($destination);

		if (!$this->exists($source)) {
			return false;
		}

		if (!$overwrite && $this->exists($destination)) {
			return false;
		}

		// Create destination directory if needed
		$dest_dir = dirname($destination);
		if (!$this->is_dir($dest_dir)) {
			if (!$this->mkdir($dest_dir, 0755)) {
				return false;
			}
		}

		$result = @copy($source, $destination);

		if ($result && $mode !== false) {
			$this->chmod($destination, $mode);
		}

		return $result;
	}

	/**
	 * Moves a file.
	 *
	 * @since 2.5.0
	 *
	 * @param string $source Path to the source file
	 * @param string $destination Path to the destination file
	 * @param bool $overwrite Optional. Whether to overwrite the destination file if it exists
	 * @return bool True on success, false on failure
	 */
	public function move($source, $destination, $overwrite = false): bool
	{
		$source = $this->sanitizeFilePath($source);
		$destination = $this->sanitizeFilePath($destination);

		if (!$this->exists($source)) {
			return false;
		}

		if (!$overwrite && $this->exists($destination)) {
			return false;
		}

		// Create destination directory if needed
		$dest_dir = dirname($destination);
		if (!$this->is_dir($dest_dir)) {
			if (!$this->mkdir($dest_dir, 0755)) {
				return false;
			}
		}

		return @rename($source, $destination);
	}

	/**
	 * Deletes a file or directory.
	 *
	 * @since 2.5.0
	 *
	 * @param string $file Path to the file or directory
	 * @param bool $recursive Optional. If set to true, deletes files and folders recursively
	 * @param string|false $type Type of resource. 'f' for file, 'd' for directory
	 * @return bool True on success, false on failure
	 */
	public function delete($file, $recursive = false, $type = false): bool
	{
		$file = $this->sanitizeFilePath($file);

		if (!$this->exists($file)) {
			return true; // Already deleted
		}

		if ($this->is_file($file)) {
			return @unlink($file);
		} elseif ($this->is_dir($file)) {
			return $this->rmdir($file, $recursive);
		}

		return false;
	}

	/**
	 * Checks if a file or directory exists.
	 *
	 * @since 2.5.0
	 *
	 * @param string $path Path to file or directory
	 * @return bool Whether $path exists or not
	 */
	public function exists($path): bool
	{
		return @file_exists($this->sanitizeFilePath($path));
	}

	/**
	 * Checks if resource is a file.
	 *
	 * @since 2.5.0
	 *
	 * @param string $file File path
	 * @return bool Whether $file is a file
	 */
	public function is_file($file): bool
	{
		return @is_file($this->sanitizeFilePath($file));
	}

	/**
	 * Checks if resource is a directory.
	 *
	 * @since 2.5.0
	 *
	 * @param string $path Directory path
	 * @return bool Whether $path is a directory
	 */
	public function is_dir($path): bool
	{
		return @is_dir($this->sanitizeFilePath($path));
	}

	/**
	 * Checks if a file is readable.
	 *
	 * @since 2.5.0
	 *
	 * @param string $file Path to file
	 * @return bool Whether $file is readable
	 */
	public function is_readable($file): bool
	{
		return @is_readable($this->sanitizeFilePath($file));
	}

	/**
	 * Checks if a file or directory is writable.
	 *
	 * @since 2.5.0
	 *
	 * @param string $path Path to file or directory
	 * @return bool Whether $path is writable
	 */
	public function is_writable($path): bool
	{
		return @is_writable($this->sanitizeFilePath($path));
	}

	/**
	 * Gets the file's last access time.
	 *
	 * @since 2.5.0
	 *
	 * @param string $file Path to file
	 * @return int|false Unix timestamp representing last access time, false on failure
	 */
	public function atime($file)
	{
		$file = $this->sanitizeFilePath($file);
		return $this->exists($file) ? @fileatime($file) : false;
	}

	/**
	 * Gets the file modification time.
	 *
	 * @since 2.5.0
	 *
	 * @param string $file Path to file
	 * @return int|false Unix timestamp representing modification time, false on failure
	 */
	public function mtime($file)
	{
		$file = $this->sanitizeFilePath($file);
		return $this->exists($file) ? @filemtime($file) : false;
	}

	/**
	 * Gets the file size (in bytes).
	 *
	 * @since 2.5.0
	 *
	 * @param string $file Path to file
	 * @return int|false Size of the file in bytes on success, false on failure
	 */
	public function size($file)
	{
		$file = $this->sanitizeFilePath($file);
		return $this->exists($file) ? @filesize($file) : false;
	}

	/**
	 * Sets the access and modification times of a file.
	 *
	 * @since 2.5.0
	 *
	 * @param string $file Path to file
	 * @param int $time Optional. Modified time to set for file
	 * @param int $atime Optional. Access time to set for file
	 * @return bool True on success, false on failure
	 */
	public function touch($file, $time = 0, $atime = 0): bool
	{
		$file = $this->sanitizeFilePath($file);

		if ($time === 0) {
			$time = time();
		}
		if ($atime === 0) {
			$atime = $time;
		}

		return @touch($file, $time, $atime);
	}

	/**
	 * Creates a directory.
	 *
	 * @since 2.5.0
	 *
	 * @param string $path Path for new directory
	 * @param int|false $chmod Optional. The permissions as octal number (or false to skip chmod)
	 * @param string|int|false $chown Optional. A user name or number (or false to skip chown)
	 * @param string|int|false $chgrp Optional. A group name or number (or false to skip chgrp)
	 * @return bool True on success, false on failure
	 */
	public function mkdir($path, $chmod = false, $chown = false, $chgrp = false): bool
	{
		$path = $this->sanitizeFilePath($path);

		if ($this->is_dir($path)) {
			return true;
		}

		// Use WordPress wp_mkdir_p if available (most reliable)
		if (function_exists('wp_mkdir_p')) {
			if (wp_mkdir_p($path)) {
				// Apply custom permissions if specified
				if ($chmod !== false) {
					$this->chmod($path, $chmod);
				}
				if ($chown !== false) {
					$this->chown($path, $chown);
				}
				if ($chgrp !== false) {
					$this->chgrp($path, $chgrp);
				}
				return true;
			}
		}

		// Fallback to native PHP mkdir
		$mode = $chmod !== false ? $chmod : 0755;
		if (@mkdir($path, $mode, true)) {
			if ($chown !== false) {
				$this->chown($path, $chown);
			}
			if ($chgrp !== false) {
				$this->chgrp($path, $chgrp);
			}
			return true;
		}

		return false;
	}

	/**
	 * Deletes a directory.
	 *
	 * @since 2.5.0
	 *
	 * @param string $path Path to directory
	 * @param bool $recursive Optional. Whether to recursively remove files/directories
	 * @return bool True on success, false on failure
	 */
	public function rmdir($path, $recursive = false): bool
	{
		$path = $this->sanitizeFilePath($path);

		if (!$this->is_dir($path)) {
			return true; // Already deleted
		}

		if ($recursive) {
			$iterator = new \RecursiveIteratorIterator(
				new \RecursiveDirectoryIterator($path, \RecursiveDirectoryIterator::SKIP_DOTS),
				\RecursiveIteratorIterator::CHILD_FIRST
			);

			foreach ($iterator as $item) {
				if ($item->isDir()) {
					if (!@rmdir($item->getPathname())) {
						return false;
					}
				} else {
					if (!@unlink($item->getPathname())) {
						return false;
					}
				}
			}
		}

		return @rmdir($path);
	}

	/**
	 * Gets details for files in a directory or a specific file.
	 *
	 * @since 2.5.0
	 *
	 * @param string $path Path to directory or file
	 * @param bool $include_hidden Optional. Whether to include details of hidden ("." prefixed) files
	 * @param bool $recursive Optional. Whether to recursively include file details in nested directories
	 * @return array|false Array of file information, false if unable to list directory contents
	 */
	public function dirlist($path, $include_hidden = true, $recursive = false)
	{
		$path = $this->sanitizeFilePath($path);

		if (!$this->is_dir($path)) {
			return false;
		}

		$files = [];

		try {
			$iterator = new \DirectoryIterator($path);

			foreach ($iterator as $file) {
				if ($file->isDot()) {
					continue;
				}

				$filename = $file->getFilename();

				// Skip hidden files if not requested
				if (!$include_hidden && str_starts_with($filename, '.')) {
					continue;
				}

				$file_info = [
					'name' => $filename,
					'perms' => $this->gethchmod($file->getPathname()),
					'permsn' => substr(sprintf('%o', $file->getPerms()), -4),
					'number' => $file->getInode(),
					'owner' => $this->owner($file->getPathname()),
					'group' => $this->group($file->getPathname()),
					'size' => $file->getSize(),
					'lastmodunix' => $file->getMTime(),
					'lastmod' => date('M j', $file->getMTime()),
					'time' => date('H:i:s', $file->getMTime()),
					'type' => $file->isDir() ? 'd' : ($file->isLink() ? 'l' : 'f'),
					'files' => false,
				];

				if ($recursive && $file->isDir()) {
					$file_info['files'] = $this->dirlist($file->getPathname(), $include_hidden, true);
				}

				$files[$filename] = $file_info;
			}
		} catch (\Exception $e) {
			return false;
		}

		return $files;
	}

	/**
	 * Gets the permissions of the specified file or filepath in their octal format.
	 *
	 * @since 2.5.0
	 *
	 * @param string $file Path to the file
	 * @return string Mode of the file (the last 3 digits)
	 */
	public function getchmod($file): string
	{
		$file = $this->sanitizeFilePath($file);

		if (!$this->exists($file)) {
			return '000';
		}

		$perms = @fileperms($file);
		if ($perms === false) {
			return '000';
		}

		return substr(sprintf('%o', $perms), -3);
	}

	// ============================================================================
	// Enhanced Public Methods (Original Filesystem API)
	// ============================================================================

	/**
	 * Read file contents (alias for get_contents for backward compatibility).
	 */
	public function getContents(string $file_path)
	{
		return $this->get_contents($file_path);
	}

	/**
	 * Write contents to a file (alias for put_contents for backward compatibility).
	 */
	public function putContents(string $file_path, string $contents, int $mode = 0644): bool
	{
		return $this->put_contents($file_path, $contents, $mode);
	}

	/**
	 * Check if a file exists (alias for exists for backward compatibility).
	 */
	public function fileExists(string $file_path): bool
	{
		return $this->exists($file_path);
	}

	/**
	 * Delete a file (enhanced version of delete).
	 */
	public function deleteFile(string $file_path): bool
	{
		return $this->delete($file_path, false, 'f');
	}

	/**
	 * Copy a file (alias for copy).
	 */
	public function copyFile(string $source, string $destination, bool $overwrite = false): bool
	{
		return $this->copy($source, $destination, $overwrite);
	}

	/**
	 * Move a file (alias for move).
	 */
	public function moveFile(string $source, string $destination, bool $overwrite = false): bool
	{
		return $this->move($source, $destination, $overwrite);
	}

	/**
	 * Create a directory (alias for mkdir with enhanced defaults).
	 */
	public function createDirectory(string $dir_path, int $mode = 0755, bool $recursive = true): bool
	{
		return $this->mkdir($dir_path, $mode);
	}

	/**
	 * Delete a directory (alias for rmdir).
	 */
	public function deleteDirectory(string $dir_path, bool $recursive = false): bool
	{
		return $this->rmdir($dir_path, $recursive);
	}

	/**
	 * Get file size (alias for size).
	 */
	public function getFileSize(string $file_path)
	{
		return $this->size($file_path);
	}

	/**
	 * Get file modification time (alias for mtime).
	 */
	public function getModificationTime(string $file_path)
	{
		return $this->mtime($file_path);
	}

	/**
	 * Get file permissions (enhanced version).
	 */
	public function getFilePermissions(string $file_path)
	{
		return $this->getchmod($file_path);
	}

	/**
	 * Set file permissions (alias for chmod).
	 */
	public function setFilePermissions(string $file_path, int $mode, bool $recursive = false): bool
	{
		return $this->chmod($file_path, $mode, $recursive);
	}

	// ============================================================================
	// WordPress Integration Methods
	// ============================================================================

	/**
	 * Upload a file to WordPress media library.
	 */
	public function uploadToMediaLibrary(array $file_data, string $title = '', string $description = '', int $parent_post_id = 0)
	{
		if (!function_exists('wp_handle_upload')) {
			require_once ABSPATH . 'wp-admin/includes/file.php';
		}

		if (!function_exists('wp_generate_attachment_metadata')) {
			require_once ABSPATH . 'wp-admin/includes/image.php';
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
			'file_size' => $this->size($file_path),
			'upload_date' => $attachment->post_date,
			'metadata' => $metadata,
		];
	}

	/**
	 * Delete file from media library.
	 */
	public function deleteMediaFile(int $attachment_id, bool $force_delete = true): bool
	{
		$result = wp_delete_attachment($attachment_id, $force_delete);
		return $result !== false;
	}

	/**
	 * Create a unique filename in upload directory.
	 */
	public function getUniqueFilename(string $filename, string $subdirectory = ''): string
	{
		$upload_dir = $this->getUploadDir($subdirectory);
		$filename = sanitize_file_name($filename);

		return wp_unique_filename($upload_dir['path'], $filename);
	}

	/**
	 * Get upload directory information.
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
	 * Create app-specific upload directory with enhanced error handling.
	 */
	public function createAppUploadDir(string $subdirectory = ''): array|false
	{
		$upload_dir = wp_upload_dir();

		// Check if uploads directory exists and is writable
		if (!$this->is_dir($upload_dir['path'])) {
			error_log("WPToolkit: WordPress uploads directory doesn't exist: " . $upload_dir['path']);
			return false;
		}

		if (!$this->is_writable($upload_dir['path'])) {
			error_log("WPToolkit: WordPress uploads directory not writable: " . $upload_dir['path']);
			return false;
		}

		$dir_name = $this->app_slug . (!empty($subdirectory) ? '/' . sanitize_file_name($subdirectory) : '');
		$target_path = $upload_dir['path'] . '/' . $dir_name;
		$target_url = $upload_dir['url'] . '/' . $dir_name;

		// Try to create the directory
		if (!$this->mkdir($target_path, 0755)) {
			error_log("WPToolkit: Failed to create app directory: " . $target_path);
			return false;
		}

		$result = [
			'path' => $target_path,
			'url' => $target_url
		];

		// Create index.php for security (prevent directory listing)
		$index_file = $target_path . '/index.php';
		if (!$this->exists($index_file)) {
			$index_content = "<?php\n// Silence is golden.\n";
			$this->put_contents($index_file, $index_content, 0644);
		}

		return $result;
	}

	/**
	 * Get file MIME type.
	 */
	public function getMimeType(string $file_path)
	{
		if (!$this->exists($file_path)) {
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

	// ============================================================================
	// File Type Management
	// ============================================================================

	/**
	 * Check if file type is allowed.
	 */
	public function isAllowedFileType(string $file_extension): bool
	{
		$file_extension = strtolower(ltrim($file_extension, '.'));
		return in_array($file_extension, $this->allowed_types, true);
	}

	/**
	 * Add allowed file types.
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

	// ============================================================================
	// Utility Methods
	// ============================================================================

	/**
	 * Format file size for display.
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
	 * Get comprehensive file information.
	 */
	public function getFileInfo(string $file_path)
	{
		if (!$this->exists($file_path)) {
			return false;
		}

		$size = $this->size($file_path);
		$mtime = $this->mtime($file_path);

		return [
			'path' => $file_path,
			'filename' => basename($file_path),
			'extension' => strtolower(pathinfo($file_path, PATHINFO_EXTENSION)),
			'size' => $size,
			'size_formatted' => $size !== false ? $this->formatFileSize($size) : 'Unknown',
			'mime_type' => $this->getMimeType($file_path),
			'modified' => $mtime,
			'modified_formatted' => $mtime !== false ? wp_date(get_option('date_format') . ' ' . get_option('time_format'), $mtime) : 'Unknown',
			'permissions' => $this->getchmod($file_path),
			'is_allowed' => $this->isAllowedFileType(pathinfo($file_path, PATHINFO_EXTENSION)),
			'is_readable' => $this->is_readable($file_path),
			'is_writable' => $this->is_writable($file_path),
		];
	}

	/**
	 * Scan directory for files.
	 */
	public function scanDirectory(string $directory, bool $recursive = false, array $allowed_extensions = []): array
	{
		$directory = $this->sanitizeFilePath($directory);

		if (!$this->is_dir($directory)) {
			return [];
		}

		$files = [];
		$extensions = !empty($allowed_extensions) ? $allowed_extensions : $this->allowed_types;

		try {
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
		} catch (\Exception $e) {
			error_log("WPToolkit: Error scanning directory {$directory}: " . $e->getMessage());
		}

		return $files;
	}

	// ============================================================================
	// Getters
	// ============================================================================

	public function getAppSlug(): string
	{
		return $this->app_slug;
	}

	public function getTextDomain(): string
	{
		return $this->text_domain;
	}

	public function getAllowedFileTypes(): array
	{
		return $this->allowed_types;
	}

	public function getConfig(): ?Config
	{
		return $this->config;
	}

	public function getAppUploadDir(): string
	{
		return $this->app_upload_dir;
	}

	// ============================================================================
	// Protected Helper Methods
	// ============================================================================

	/**
	 * Parse config or slug parameter and set instance properties.
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
	 * Setup allowed file types.
	 */
	protected function setupAllowedTypes(array $additional_types): void
	{
		$default_types = [
			'jpg', 'jpeg', 'png', 'gif', 'webp', 'svg',
			'pdf', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx',
			'txt', 'csv', 'zip', 'tar', 'gz'
		];

		$this->allowed_types = array_unique(array_merge($default_types, $additional_types));
	}

	/**
	 * Setup upload directory.
	 */
	protected function setupUploadDirectory(): void
	{
		$this->upload_dir = wp_upload_dir();
		$this->app_upload_dir = $this->upload_dir['path'] . '/' . $this->app_slug;
	}

	/**
	 * Sanitize file path.
	 */
	protected function sanitizeFilePath(string $file_path): string
	{
		// Remove any null bytes
		$file_path = str_replace("\0", '', $file_path);

		// Normalize directory separators
		$file_path = str_replace('\\', '/', $file_path);

		// Remove any dangerous path components
		return preg_replace('/\.\.+/', '.', $file_path);
	}
}