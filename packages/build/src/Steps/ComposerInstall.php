<?php

/**
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

namespace Codad5\WPToolkit\Build\Steps;

use Codad5\WPToolkit\Build\BuildContext;
use Codad5\WPToolkit\Build\BuildStep;
use Codad5\WPToolkit\Build\Phase;

/**
 * Reinstalls Composer dependencies inside the staging copy, without dev packages — so the local
 * vendor/ (PHPUnit, PHPStan, PHPCS…) never reaches the zip, and your working tree isn't touched.
 */
final class ComposerInstall implements BuildStep
{
    public function __construct(private readonly bool $noDev = true, private readonly string $composer = 'composer')
    {
    }

    public function name(): string
    {
        return 'composer';
    }

    public function phase(): Phase
    {
        return Phase::Transform;
    }

    public function run(BuildContext $context): void
    {
        if (!is_file($context->staged('composer.json'))) {
            $context->warn('composer() was requested but there is no composer.json; skipped.');
            return;
        }

        $this->removeDirectory($context->staged('vendor'));

        $command = $this->composer . ' install --no-interaction --no-progress --optimize-autoloader --prefer-dist';
        if ($this->noDev) {
            $command .= ' --no-dev';
        }

        $context->exec($this->name(), $command, $context->staging);
    }

    private function removeDirectory(string $directory): void
    {
        if (!is_dir($directory)) {
            return;
        }

        $items = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($directory, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );
        foreach ($items as $item) {
            $item->isDir() && !$item->isLink() ? rmdir($item->getPathname()) : unlink($item->getPathname());
        }
        rmdir($directory);
    }
}
