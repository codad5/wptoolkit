<?php

declare(strict_types=1);

namespace Codad5\WPToolkit\Tests\Integration;

use Codad5\WPToolkit\Adapters\Filesystem\WpFilesystem;
use Codad5\WPToolkit\Contracts\Filesystem\Filesystem;
use Codad5\WPToolkit\Tests\Contract\FilesystemContract;

/**
 * The full Filesystem contract against WordPress's real WP_Filesystem.
 */
final class WpFilesystemTest extends FilesystemContract
{
    private string $root;

    private ?WpFilesystem $fs = null;

    protected function setUp(): void
    {
        $uploads = wp_upload_dir();
        $this->root = $uploads['basedir'] . '/wptoolkit-it-' . uniqid();
        wp_mkdir_p($this->root);
    }

    protected function tearDown(): void
    {
        (new WpFilesystem(dirname($this->root)))->deleteDirectory(basename($this->root));
    }

    protected function filesystem(): Filesystem
    {
        return $this->fs ??= new WpFilesystem($this->root);
    }
}
