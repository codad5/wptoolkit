<?php

/**
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

namespace Codad5\WPToolkit\Build;

use Closure;
use Codad5\WPToolkit\Build\Patterns\PatternSet;
use Codad5\WPToolkit\Build\Patterns\PatternSource;
use Codad5\WPToolkit\Build\Steps\ComposerInstall;
use Codad5\WPToolkit\Build\Steps\MakePot;
use Codad5\WPToolkit\Build\Steps\RunCommands;
use Codad5\WPToolkit\Build\Steps\ScopeToolkit;
use Codad5\WPToolkit\Build\Steps\SyncVersion;
use Codad5\WPToolkit\Build\Steps\Verify;
use FilesystemIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use ZipArchive;

/**
 * A fluent, verified build for a WordPress plugin or theme (ADR-0015 amendment):
 *
 *     Build::plugin(__DIR__)
 *         ->version(Version::fromPackageJson())
 *         ->syncVersionTo('header', 'readme.txt')
 *         ->run('npm ci', 'npm run build')
 *         ->composer(noDev: true)
 *         ->exclude(Patterns::fromDistignore(), 'docs')
 *         ->zip('dist/{slug}-{version}.zip');
 *
 * Phases: Prepare (in your project) → stage (copy to a temp directory) → Transform → prune
 * (excludes) → Verify (always) → zip. Your working tree is never modified.
 */
final class Build
{
    /** 1980-01-01, the earliest time a zip entry can hold. */
    private const FIXED_MTIME = 315532800;

    /** Never copied into staging. */
    private const NEVER_STAGED = ['.git', 'node_modules', '.svn'];

    /** Removed from every package unless include()d back. */
    public const DEFAULT_EXCLUDES = [
        '.git', '.github', '.gitignore', '.gitattributes', '.distignore', '.editorconfig', '.svn',
        '.idea', '.vscode', '.claude', '.agents', '.wp-env.json', '.wp-env.override.json',
        'node_modules', '/tests', '/test', 'phpunit.xml*', 'phpcs.xml*', '.phpcs.xml*', 'phpstan.neon*',
        'psalm.xml*', 'rector.php', 'composer.json', 'composer.lock', 'package.json', 'package-lock.json',
        'yarn.lock', 'pnpm-lock.yaml', 'webpack.config.js', 'vite.config.*', 'tailwind.config.js',
        'postcss.config.js', '/build.php', '/wptoolkit.json', '/build-tools', '/README.md', '/CLAUDE.md',
        '/AGENTS.md', '*.zip', '*.zip.sha256', '*.log', '.env', '.env.*', '.DS_Store', 'Thumbs.db',
    ];

    private ?VersionSource $version = null;

    /** @var list<BuildStep> */
    private array $steps = [];

    /** @var list<string|PatternSource> */
    private array $excludes = [];

    /** @var list<string|PatternSource> */
    private array $includes = [];

    /** @var list<string> */
    private array $forbid = [];

    private ?int $maxSizeMb = null;

    private CommandRunner $runner;

    /** @var Closure(string): void */
    private Closure $output;

    private function __construct(private readonly Project $project)
    {
        $this->runner = new ProcessCommandRunner();
        $this->output = static function (string $line): void {
            fwrite(STDOUT, $line . PHP_EOL);
        };
    }

    public static function plugin(string $directory): self
    {
        return new self(Project::plugin($directory));
    }

    /**
     * @param string $versionFile Relative to `$directory`; read by Version::fromConstant().
     */
    public static function library(string $directory, string $slug, string $versionFile): self
    {
        return new self(Project::library($directory, $slug, $versionFile));
    }

    public static function theme(string $directory): self
    {
        return new self(Project::theme($directory));
    }

    public function version(VersionSource $source): self
    {
        $this->version = $source;

        return $this;
    }

    /**
     * Write the version into the staged copy: 'header', 'readme.txt', or a constant name.
     */
    public function syncVersionTo(string ...$targets): self
    {
        return $this->step(new SyncVersion(...$targets));
    }

    /**
     * Shell commands in your project directory, before staging (asset builds).
     */
    public function run(string ...$commands): self
    {
        return $this->step(new RunCommands(...$commands));
    }

    /**
     * Reinstall Composer dependencies in staging; `noDev` drops dev packages.
     */
    public function composer(bool $noDev = true, string $binary = 'composer'): self
    {
        return $this->step(new ComposerInstall($noDev, $binary));
    }

    public function scope(string $namespace, ?string $toolkitPath = null): self
    {
        return $this->step(new ScopeToolkit($namespace, $toolkitPath));
    }

    public function makePot(?string $domain = null): self
    {
        return $this->step(new MakePot($domain));
    }

    /**
     * Keep paths a default or other exclude would remove. Globs or pattern files.
     */
    public function include(string|PatternSource ...$patterns): self
    {
        array_push($this->includes, ...$patterns);

        return $this;
    }

    /**
     * Leave paths out of the package. Globs (gitignore syntax) or pattern files.
     */
    public function exclude(string|PatternSource ...$patterns): self
    {
        array_push($this->excludes, ...$patterns);

        return $this;
    }

    public function step(BuildStep $step): self
    {
        $this->steps[] = $step;

        return $this;
    }

    /**
     * Extra verification rules. Verification always runs; this adds to it.
     *
     * @param list<string> $forbid Patterns that must not appear in the package.
     */
    public function verify(array $forbid = [], ?int $maxSizeMb = null): self
    {
        array_push($this->forbid, ...$forbid);
        $this->maxSizeMb = $maxSizeMb ?? $this->maxSizeMb;

        return $this;
    }

    public function withRunner(CommandRunner $runner): self
    {
        $this->runner = $runner;

        return $this;
    }

    /**
     * @param Closure(string): void $output
     */
    public function withOutput(Closure $output): self
    {
        $this->output = $output;

        return $this;
    }

    /**
     * Run the build and write the zip. `{slug}` and `{version}` are replaced; a relative path is
     * relative to the project. A `.sha256` file is written next to it.
     */
    public function zip(string $target = 'dist/{slug}-{version}.zip'): BuildReport
    {
        if (!class_exists(ZipArchive::class)) {
            throw new BuildException('The PHP zip extension is required to build packages.');
        }

        $version = ($this->version ?? Version::fromHeader())->resolve($this->project, $this->runner);
        $zip = $this->absolute(strtr($target, ['{slug}' => $this->project->slug, '{version}' => $version]));
        $staging = sys_get_temp_dir() . '/wptoolkit-build-' . bin2hex(random_bytes(6)) . '/' . $this->project->slug;

        $context = new BuildContext($this->project, $staging, $version, $this->runner, $this->output);
        $context->say(sprintf('Building %s %s (%s)', $this->project->slug, $version, $this->project->type));

        try {
            $this->runPhase(Phase::Prepare, $context);
            $this->stage($staging, $zip);
            $this->runPhase(Phase::Transform, $context);
            $this->prune($staging);
            $this->runPhase(Phase::Verify, $context, [new Verify($this->forbid, $this->maxSizeMb)]);

            $report = $this->package($context, $zip);
            $context->say($report->summary());

            return $report;
        } finally {
            self::removeDirectory(dirname($staging));
        }
    }

    /**
     * The files a build would ship, without running any command or writing anything. Steps that
     * change files (composer, scope, make-pot) are not run, so their effects aren't reflected.
     *
     * @return list<string> Paths relative to the project, sorted.
     */
    public function dryRun(): array
    {
        $staging = sys_get_temp_dir() . '/wptoolkit-dryrun-' . bin2hex(random_bytes(6)) . '/' . $this->project->slug;

        try {
            $this->stage($staging, $this->project->root . '/dist/.dry-run.zip');
            $this->prune($staging);

            $files = [];
            $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($staging, FilesystemIterator::SKIP_DOTS));
            foreach ($iterator as $file) {
                $files[] = substr(str_replace('\\', '/', $file->getPathname()), strlen($staging) + 1);
            }
            sort($files);

            return $files;
        } finally {
            self::removeDirectory(dirname($staging));
        }
    }

    public function project(): Project
    {
        return $this->project;
    }

    /**
     * @param list<BuildStep> $extra
     */
    private function runPhase(Phase $phase, BuildContext $context, array $extra = []): void
    {
        foreach ([...$this->steps, ...$extra] as $step) {
            if ($step->phase() !== $phase) {
                continue;
            }

            $context->say('→ ' . $step->name());
            try {
                $step->run($context);
            } catch (BuildException $failure) {
                throw $failure;
            } catch (\Throwable $error) {
                throw BuildException::inStep($step->name(), $error->getMessage());
            }
        }
    }

    private function stage(string $staging, string $zip): void
    {
        $root = $this->project->root;
        $outputDirectory = str_replace('\\', '/', dirname($zip));
        mkdir($staging, 0777, true);

        $items = new RecursiveIteratorIterator(
            new \RecursiveCallbackFilterIterator(
                new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS),
                static function (\SplFileInfo $item) use ($outputDirectory): bool {
                    $path = str_replace('\\', '/', $item->getPathname());
                    return !in_array($item->getFilename(), self::NEVER_STAGED, true) && $path !== $outputDirectory;
                }
            ),
            RecursiveIteratorIterator::SELF_FIRST
        );

        foreach ($items as $item) {
            $target = $staging . substr(str_replace('\\', '/', $item->getPathname()), strlen($root));
            if ($item->isDir()) {
                if (!is_dir($target)) {
                    mkdir($target, 0777, true);
                }
            } else {
                copy($item->getPathname(), $target);
            }
        }
    }

    private function prune(string $staging): void
    {
        $excludes = new PatternSet(self::DEFAULT_EXCLUDES);
        $excludes->add(...$this->expand($this->excludes));
        $includes = new PatternSet($this->expand($this->includes));

        $items = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($staging, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST
        );
        foreach ($items as $item) {
            $relative = substr(str_replace('\\', '/', $item->getPathname()), strlen($staging) + 1);
            $isDirectory = $item->isDir() && !$item->isLink();

            if ($excludes->matches($relative, $isDirectory) && !$includes->matches($relative, $isDirectory)) {
                $isDirectory ? self::removeDirectory($item->getPathname()) : unlink($item->getPathname());
            }
        }
    }

    /**
     * @param list<string|PatternSource> $patterns
     * @return list<string>
     */
    private function expand(array $patterns): array
    {
        $expanded = [];
        foreach ($patterns as $pattern) {
            array_push($expanded, ...(is_string($pattern) ? [$pattern] : $pattern->patterns($this->project->root)));
        }

        return $expanded;
    }

    private function package(BuildContext $context, string $zipPath): BuildReport
    {
        if (!is_dir(dirname($zipPath))) {
            mkdir(dirname($zipPath), 0777, true);
        }
        if (is_file($zipPath)) {
            unlink($zipPath);
        }

        $files = [];
        $sizes = [];
        $total = 0;
        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($context->staging, FilesystemIterator::SKIP_DOTS));
        foreach ($iterator as $file) {
            $relative = substr(str_replace('\\', '/', $file->getPathname()), strlen($context->staging) + 1);
            $files[$relative] = $file->getPathname();
            $top = explode('/', $relative)[0] . (str_contains($relative, '/') ? '/' : '');
            $sizes[$top] = ($sizes[$top] ?? 0) + $file->getSize();
            $total += $file->getSize();
        }
        ksort($files, SORT_STRING); // deterministic order

        $archive = new ZipArchive();
        if ($archive->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new BuildException(sprintf('Cannot write %s.', $zipPath));
        }

        // A single folder named after the slug, the way WordPress expects plugin/theme zips.
        // Fixed timestamps and permissions: the same sources produce the same bytes, so a checksum
        // proves what was released. libzip also reads the file's own mtime, hence the touch().
        $prefix = $this->project->slug . '/';
        foreach ($files as $relative => $absolute) {
            $name = $prefix . $relative;
            touch($absolute, self::FIXED_MTIME);
            $archive->addFile($absolute, $name);
            $archive->setMtimeName($name, self::FIXED_MTIME);
            $archive->setExternalAttributesName($name, ZipArchive::OPSYS_UNIX, 0100644 << 16);
        }
        $archive->close();

        $sha256 = (string) hash_file('sha256', $zipPath);
        file_put_contents($zipPath . '.sha256', $sha256 . '  ' . basename($zipPath) . PHP_EOL);
        arsort($sizes);

        return new BuildReport(
            $zipPath,
            $sha256,
            $context->version,
            $total,
            array_map(static fn (string $f): string => $prefix . $f, array_keys($files)),
            $sizes,
            $context->warnings
        );
    }

    private function absolute(string $path): string
    {
        $path = str_replace('\\', '/', $path);

        return preg_match('#^(/|[a-z]:/)#i', $path) === 1 ? $path : $this->project->root . '/' . $path;
    }

    private static function removeDirectory(string $directory): void
    {
        if (!is_dir($directory)) {
            return;
        }

        $items = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($directory, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST
        );
        foreach ($items as $item) {
            $item->isDir() && !$item->isLink() ? rmdir($item->getPathname()) : unlink($item->getPathname());
        }
        rmdir($directory);
    }
}
