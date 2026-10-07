<?php

/**
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

namespace Codad5\WPToolkit\Build\Steps;

use Codad5\WPToolkit\Build\BuildContext;
use Codad5\WPToolkit\Build\BuildException;
use Codad5\WPToolkit\Build\BuildStep;
use Codad5\WPToolkit\Build\Phase;

/**
 * Writes the build's version everywhere it appears, in the staging copy:
 *
 * - `'header'`      the `Version:` header of the main plugin file or style.css
 * - `'readme.txt'`  the `Stable tag:` line
 * - `'NAME'`        a constant: `define('NAME', '…')` or `const NAME = '…'` in the main file
 */
final class SyncVersion implements BuildStep
{
    /** @var list<string> */
    private readonly array $targets;

    public function __construct(string ...$targets)
    {
        $this->targets = array_values($targets);
    }

    public function name(): string
    {
        return 'sync-version';
    }

    public function phase(): Phase
    {
        return Phase::Transform;
    }

    public function run(BuildContext $context): void
    {
        $version = $context->version;
        $headerFile = $context->staged($context->project->relativeHeaderFile());

        foreach ($this->targets as $target) {
            if ($target === 'header') {
                $this->replace($context, $headerFile, '/^([ \t\/*#@]*Version:[ \t]*)\S+/mi', '${1}' . $version, 'Version header');
            } elseif (strcasecmp($target, 'readme.txt') === 0) {
                $this->replace($context, $context->staged('readme.txt'), '/^(Stable tag:[ \t]*)\S+/mi', '${1}' . $version, 'Stable tag');
            } else {
                $this->replaceConstant($context, $headerFile, $target, $version);
            }
        }
    }

    private function replaceConstant(BuildContext $context, string $file, string $name, string $version): void
    {
        $quoted = preg_quote($name, '/');
        $source = (string) file_get_contents($file);
        $updated = preg_replace(
            ["/(define\(\s*['\"]{$quoted}['\"]\s*,\s*['\"])[^'\"]+(['\"])/", "/(const\s+{$quoted}\s*=\s*['\"])[^'\"]+(['\"])/"],
            '${1}' . $version . '${2}',
            $source,
            -1,
            $count
        );

        if ($count === 0 || !is_string($updated)) {
            throw BuildException::inStep($this->name(), sprintf('Constant %s not found in %s.', $name, basename($file)));
        }

        file_put_contents($file, $updated);
        $context->say("  {$name} = {$version}");
    }

    private function replace(BuildContext $context, string $file, string $pattern, string $replacement, string $label): void
    {
        if (!is_file($file)) {
            throw BuildException::inStep($this->name(), sprintf('%s not found.', basename($file)));
        }

        $updated = preg_replace($pattern, $replacement, (string) file_get_contents($file), 1, $count);
        if ($count === 0 || !is_string($updated)) {
            throw BuildException::inStep($this->name(), sprintf('No %s line in %s.', $label, basename($file)));
        }

        file_put_contents($file, $updated);
        $context->say("  {$label} = {$context->version}");
    }
}
