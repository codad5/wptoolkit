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
use Codad5\WPToolkit\Build\Phase;

/**
 * Scopes the bundled WPToolkit in staging (ADR-0005, ADR-0014) and everything that has to agree
 * with it, so the packaged plugin loads:
 *
 *   1. the toolkit copy, with its own bin/scope.php;
 *   2. the plugin's own PHP, which was written against `Codad5\WPToolkit`;
 *   3. for a Composer install, the generated autoload map (the package's PSR-4 prefix is renamed in
 *      Composer's metadata and the autoloader is dumped again);
 *   4. a check that the result loads — a build that can't boot fails here instead of shipping.
 *
 * Other packages under vendor/ are not touched: a dependency that itself needs WPToolkit can't be
 * scoped this way.
 */
final class ScopeToolkit implements BuildStep
{
    private const CANDIDATES = ['vendor/codad5/wptoolkit', 'lib/wptoolkit', 'wptoolkit'];

    private const PACKAGE = 'codad5/wptoolkit';

    private const SKIPPED = ['vendor', 'node_modules', '.git'];

    public function __construct(
        private readonly string $namespace,
        private readonly ?string $toolkitPath = null,
        private readonly string $composer = 'composer'
    ) {
    }

    public function name(): string
    {
        return 'scope';
    }

    public function phase(): Phase
    {
        return Phase::Transform;
    }

    public function run(BuildContext $context): void
    {
        $path = $this->locate($context);
        $scoper = $context->staged($path . '/bin/scope.php');
        if (!is_file($scoper)) {
            throw BuildException::inStep($this->name(), sprintf('%s/bin/scope.php not found in the staged copy.', $path));
        }

        $context->exec(
            $this->name(),
            escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg($scoper) . ' ' . escapeshellarg($this->namespace) . ' ' . escapeshellarg($context->staged($path)),
            $context->staging
        );

        $rewriter = new NamespaceRewriter($this->namespace);
        $changed = $this->rewriteConsumer($context, $path, $rewriter);
        $context->say(sprintf('  scoped %d reference(s) in the project\'s own code', $changed));

        if (str_starts_with($path, 'vendor/')) {
            $this->fixComposerAutoload($context);
            $this->assertLoads($context);
        }
    }

    /**
     * Rewrite the project's own PHP (not the toolkit copy, not other packages) and return how many
     * references changed.
     */
    private function rewriteConsumer(BuildContext $context, string $toolkitPath, NamespaceRewriter $rewriter): int
    {
        $changed = 0;
        foreach (self::consumerFiles($context->staging, $toolkitPath) as $file) {
            $original = (string) file_get_contents($file);
            [$source, $count] = $rewriter->rewrite($original);
            if ($count > 0) {
                file_put_contents($file, $source);
                $changed += $count;
            }
            if ($rewriter->namesToolkitInAString($original)) {
                $context->warn(sprintf(
                    '%s names a WPToolkit class in a string; strings are not scoped. Use ::class there.',
                    substr(str_replace('\\', '/', $file), strlen($context->staging) + 1)
                ));
            }
        }

        return $changed;
    }

    /**
     * The project's own PHP files: everything except the toolkit copy, other packages and tooling folders.
     *
     * @return iterable<string>
     */
    public static function consumerFiles(string $root, string $toolkitPath): iterable
    {
        $toolkit = str_replace('\\', '/', $root . '/' . $toolkitPath) . '/';
        $filter = new \RecursiveCallbackFilterIterator(
            new \RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS),
            static function (\SplFileInfo $item) use ($toolkit): bool {
                $path = str_replace('\\', '/', $item->getPathname());
                if ($item->isDir()) {
                    return !in_array($item->getFilename(), self::SKIPPED, true) && !str_starts_with($path . '/', $toolkit);
                }

                return $item->getExtension() === 'php';
            }
        );

        foreach (new \RecursiveIteratorIterator($filter) as $file) {
            yield $file->getPathname();
        }
    }

    /**
     * Composer's autoloader was generated for `Codad5\WPToolkit\`. Rename that prefix in Composer's
     * record of the installed package and regenerate, so the classmap and PSR-4 map are rebuilt
     * from the scoped files rather than patched by hand.
     */
    private function fixComposerAutoload(BuildContext $context): void
    {
        $installed = $context->staged('vendor/composer/installed.json');
        $data = is_file($installed) ? json_decode((string) file_get_contents($installed), true) : null;
        $packages = is_array($data) ? ($data['packages'] ?? null) : null;
        if (!is_array($data) || !is_array($packages)) {
            throw BuildException::inStep(
                $this->name(),
                'vendor/composer/installed.json is missing or unreadable, so the autoloader cannot be rebuilt for the scoped namespace.'
            );
        }

        $found = false;
        foreach ($packages as $i => $package) {
            if (!is_array($package) || ($package['name'] ?? null) !== self::PACKAGE) {
                continue;
            }
            $found = true;
            $psr4 = $package['autoload']['psr-4'] ?? [];
            if (is_array($psr4) && isset($psr4[NamespaceRewriter::ORIGINAL . '\\'])) {
                $psr4[$this->namespace . '\\'] = $psr4[NamespaceRewriter::ORIGINAL . '\\'];
                unset($psr4[NamespaceRewriter::ORIGINAL . '\\']);
                $packages[$i]['autoload']['psr-4'] = $psr4;
            }
        }
        if (!$found) {
            throw BuildException::inStep($this->name(), sprintf(
                '%s is not in vendor/composer/installed.json, so the autoloader cannot be rebuilt for the scoped namespace.',
                self::PACKAGE
            ));
        }

        $data['packages'] = $packages;
        file_put_contents($installed, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n");

        $command = $this->composer . ' dump-autoload --optimize --no-interaction';
        if (($data['dev'] ?? true) === false) {
            $command .= ' --no-dev';
        }
        $context->exec($this->name(), $command, $context->staging);
    }

    /**
     * Load the scoped Application through the staged autoloader, in a separate process.
     */
    private function assertLoads(BuildContext $context): void
    {
        $script = $context->staged('.wptoolkit-scope-check.php');
        file_put_contents($script, sprintf(
            "<?php\nrequire __DIR__ . '/vendor/autoload.php';\nexit(class_exists(%s) ? 0 : 1);\n",
            var_export($this->namespace . '\\Foundation\\Application', true)
        ));

        try {
            $result = $context->runner->run(escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg($script), $context->staging);
        } finally {
            unlink($script);
        }

        if ($result['exitCode'] !== 0) {
            throw BuildException::inStep($this->name(), sprintf(
                'After scoping, %s\\Foundation\\Application does not load through vendor/autoload.php:%s%s',
                $this->namespace,
                PHP_EOL,
                trim($result['output'])
            ));
        }
    }

    /**
     * Where a bundled copy sits in a staged project, or null when there is none.
     */
    public static function find(string $staging): ?string
    {
        foreach (self::CANDIDATES as $candidate) {
            if (is_file($staging . '/' . $candidate . '/src/Foundation/Application.php')) {
                return $candidate;
            }
        }

        return null;
    }

    private function locate(BuildContext $context): string
    {
        if ($this->toolkitPath !== null) {
            return trim($this->toolkitPath, '/');
        }

        $found = self::find($context->staging);
        if ($found !== null) {
            return $found;
        }

        throw BuildException::inStep(
            $this->name(),
            'No bundled WPToolkit found (looked in ' . implode(', ', self::CANDIDATES) . '). Pass its path: scope($namespace, $path).'
        );
    }
}
