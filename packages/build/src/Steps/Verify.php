<?php

/**
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

namespace Codad5\WPToolkit\Build\Steps;

use Codad5\WPToolkit\Build\BuildContext;
use Codad5\WPToolkit\Build\BuildException;
use Codad5\WPToolkit\Build\BuildStep;
use Codad5\WPToolkit\Build\NamespaceRewriter;
use Codad5\WPToolkit\Build\Patterns\PatternSet;
use Codad5\WPToolkit\Build\Phase;
use Codad5\WPToolkit\Build\Project;

/**
 * Refuses a package that would ship what must never ship, or whose version doesn't add up. Always
 * runs; `Build::verify()` adds rules.
 *
 * Built-in refusals (from real zips: a plugin shipped ~3,000 files of PHPUnit/PHPStan/PHPCS, a theme
 * shipped `.claude/`): dev dependencies in vendor/, VCS and editor folders, agent config,
 * node_modules, nested zips, env files, and a `Version:` header that differs from the build version.
 * It also refuses a project whose code still names the unscoped library while the bundled copy has
 * been scoped to another namespace: that package is a fatal error on the first request.
 */
final class Verify implements BuildStep
{
    public const FORBIDDEN = [
        'vendor/bin/',
        'vendor/phpunit/',
        'vendor/phpstan/',
        'vendor/squizlabs/',
        'vendor/wp-coding-standards/',
        'vendor/phpcompatibility/',
        'vendor/mockery/',
        'vendor/brain/',
        'vendor/rector/',
        'vendor/vimeo/',
        '.git/',
        '.svn/',
        '.idea/',
        '.vscode/',
        '.claude/',
        '.agents/',
        'node_modules/',
        '*.zip',
        '.env',
        '.env.*',
    ];

    /** @var list<string> */
    private readonly array $forbid;

    /**
     * @param list<string> $forbid Extra patterns that must not appear.
     */
    public function __construct(array $forbid = [], private readonly ?int $maxSizeMb = null)
    {
        $this->forbid = $forbid;
    }

    public function name(): string
    {
        return 'verify';
    }

    public function phase(): Phase
    {
        return Phase::Verify;
    }

    public function run(BuildContext $context): void
    {
        $forbidden = new PatternSet([...self::FORBIDDEN, ...$this->forbid]);
        $found = [];
        $bytes = 0;

        $files = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($context->staging, \FilesystemIterator::SKIP_DOTS));
        foreach ($files as $file) {
            $relative = substr(str_replace('\\', '/', $file->getPathname()), strlen($context->staging) + 1);
            $bytes += $file->getSize();
            if ($forbidden->matches($relative)) {
                $found[] = $relative;
            }
        }

        if ($found !== []) {
            $sample = array_slice($found, 0, 8);
            throw BuildException::inStep($this->name(), sprintf(
                "%d file(s) that must not ship, e.g.:\n  - %s%s\nUse composer() for --no-dev installs, or exclude() them.",
                count($found),
                implode("\n  - ", $sample),
                count($found) > count($sample) ? "\n  - …" : ''
            ));
        }

        $this->refuseNamespaceMismatch($context);

        $header = Project::readHeader($context->staged($context->project->relativeHeaderFile()), 'Version');
        if ($header !== null && $header !== $context->version) {
            throw BuildException::inStep($this->name(), sprintf(
                'The Version header says %s but the build version is %s. Use syncVersionTo(\'header\').',
                $header,
                $context->version
            ));
        }

        if ($this->maxSizeMb !== null && $bytes > $this->maxSizeMb * 1024 * 1024) {
            throw BuildException::inStep($this->name(), sprintf('Package is %.1f MB; the limit is %d MB.', $bytes / 1048576, $this->maxSizeMb));
        }
    }

    private function refuseNamespaceMismatch(BuildContext $context): void
    {
        $path = ScopeToolkit::find($context->staging);
        if ($path === null) {
            return;
        }

        $source = (string) file_get_contents($context->staged($path . '/src/Foundation/Application.php'));
        $namespace = preg_match('/^namespace\s+(.+)\\\\Foundation;/m', $source, $m) === 1 ? trim($m[1]) : NamespaceRewriter::ORIGINAL;
        if ($namespace === NamespaceRewriter::ORIGINAL) {
            return;
        }

        $rewriter = new NamespaceRewriter($namespace);
        $stale = [];
        foreach (ScopeToolkit::consumerFiles($context->staging, $path) as $file) {
            if ($rewriter->remaining((string) file_get_contents($file)) > 0) {
                $stale[] = substr(str_replace('\\', '/', $file), strlen($context->staging) + 1);
            }
        }

        if ($stale !== []) {
            throw BuildException::inStep($this->name(), sprintf(
                "The bundled WPToolkit is scoped to %s, but %d file(s) still use %s, e.g.:\n  - %s\n"
                    . 'Use scope() in the build, or write that code against the scoped namespace.',
                $namespace,
                count($stale),
                NamespaceRewriter::ORIGINAL,
                implode("\n  - ", array_slice($stale, 0, 8))
            ));
        }
    }
}
