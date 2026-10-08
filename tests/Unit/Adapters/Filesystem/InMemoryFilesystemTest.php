<?php

declare(strict_types=1);

namespace Codad5\WPToolkit\Tests\Unit\Adapters\Filesystem;

use Codad5\WPToolkit\Adapters\Filesystem\InMemoryFilesystem;
use Codad5\WPToolkit\Contracts\Filesystem\Filesystem;
use Codad5\WPToolkit\Tests\Contract\FilesystemContract;

final class InMemoryFilesystemTest extends FilesystemContract
{
    private ?InMemoryFilesystem $fs = null;

    protected function filesystem(): Filesystem
    {
        return $this->fs ??= new InMemoryFilesystem();
    }
}
