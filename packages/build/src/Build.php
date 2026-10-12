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
 *
 * Commands are silent unless they fail; `->verbose()` prints their output as they run, and
 * `->runVerbose(...)` does so for chosen commands only.
 */
final class Build
{
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

    private string $composerBinary = 'composer';

    private bool $verbose = false;

    private bool $quiet = false;

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
     * Like run(), but these commands' output is printed as they run even when the build is not verbose.
     */
    public function runVerbose(string ...$commands): self
    {
        return $this->step(RunCommands::streamed(...$commands));
    }

    /**
     * Print the output of every command the build runs (run(), composer(), scope(), make-pot) as it
     * is produced, instead of only when a command fails. Useful in CI logs and for slow asset builds.
     */
    public function verbose(bool $verbose = true): self
    {
        $this->verbose = $verbose;

        return $this;
    }

    /**
     * Print nothing but warnings: no progress lines and no summary. The report is still returned,
     * and a failure is still an exception. Wins over verbose().
     */
    public function quiet(bool $quiet = true): self
    {
        $this->quiet = $quiet;

        return $this;
    }

    /**
     * Reinstall Composer dependencies in staging; `noDev` drops dev packages.
     */
    public function composer(bool $noDev = true, string $binary = 'composer'): self
    {
        foreach ($this->steps as $step) {
            if ($step instanceof ScopeToolkit) {
                throw new BuildException('Call composer() before scope(): reinstalling dependencies after scoping would put the unscoped library back.');
            }
        }
        $this->composerBinary = $binary;

        return $this->step(new ComposerInstall($noDev, $binary));
    }

    /**
     * Move the bundled WPToolkit into your own namespace (ADR-0005), together with your project's
     * references to it and, for a Composer install, the autoloader — so you keep writing against
     * `Codad5\WPToolkit` and ship a copy no other plugin can collide with.
     */
    public function scope(string $namespace, ?string $toolkitPath = null): self
    {
        return $this->step(new ScopeToolkit($namespace, $toolkitPath, $this->composerBinary));
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
     * relative to the project. A `.sha256` file is written next to it. The summary is printed for
     * you (see quiet()); the returned report is for scripts that need the numbers.
     */
    public function zip(string $target = 'dist/{slug}-{version}.zip'): BuildReport
    {
        if (!class_exists(ZipArchive::class)) {
            throw new BuildException('The PHP zip extension is required to build packages.');
        }

        $version = $this->resolveVersion();
        $zip = $this->absolute(strtr($target, ['{slug}' => $this->project->slug, '{version}' => $version]));

        return $this->produce($version, [dirname($zip)], static fn (BuildContext $context): BuildReport => (new ZipPackager())->write($context, $zip));
    }

    /**
     * Run the same build and verification, and write the result as a folder instead of a zip — to
     * rsync or commit to SVN, to mount in a test site, or to look at exactly what ships.
     *
     * The folder is replaced, so it must be for output only: a target that is the project or a
     * folder above it is refused, and so is an existing folder that is neither empty nor an
     * earlier build of this project. Nothing is written if a step or verification fails.
     */
    public function toDirectory(string $target = 'dist/{slug}'): BuildReport
    {
        $version = $this->resolveVersion();
        $directory = DirectoryPackager::normalize($this->absolute(strtr($target, ['{slug}' => $this->project->slug, '{version}' => $version])));
        $packager = new DirectoryPackager();
        $packager->assertSafe($this->project, $directory); // before any command runs

        $skip = [$directory];
        if (dirname($directory) !== $this->project->root) {
            $skip[] = dirname($directory); // e.g. dist/, which may hold earlier zips and builds
        }

        return $this->produce($version, $skip, static fn (BuildContext $context): BuildReport => $packager->write($context, $directory));
    }

    private function resolveVersion(): string
    {
        return ($this->version ?? Version::fromHeader())->resolve($this->project, $this->runner);
    }

    /**
     * The pipeline both outputs share: Prepare → stage → Transform → prune → Verify → write.
     *
     * @param list<string> $notStaged Absolute paths left out of the staging copy (the output's own location).
     * @param Closure(BuildContext): BuildReport $write
     */
    private function produce(string $version, array $notStaged, Closure $write): BuildReport
    {
        $staging = sys_get_temp_dir() . '/wptoolkit-build-' . bin2hex(random_bytes(6)) . '/' . $this->project->slug;
        $context = new BuildContext($this->project, $staging, $version, $this->runner, $this->output, $this->verbose, $this->quiet);
        $context->say(sprintf('Building %s %s (%s)', $this->project->slug, $version, $this->project->type));

        try {
            $this->runPhase(Phase::Prepare, $context);
            $this->stage($staging, $notStaged);
            $this->runPhase(Phase::Transform, $context);
            $this->prune($staging);
            $this->runPhase(Phase::Verify, $context, [new Verify($this->forbid, $this->maxSizeMb)]);

            $report = $write($context);
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
            $this->stage($staging, [$this->project->root . '/dist']);
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

    /**
     * @param list<string> $notStaged Absolute paths to leave out.
     */
    private function stage(string $staging, array $notStaged): void
    {
        $root = $this->project->root;
        $skipped = array_map(static fn (string $path): string => rtrim(str_replace('\\', '/', $path), '/'), $notStaged);
        mkdir($staging, 0777, true);

        $items = new RecursiveIteratorIterator(
            new \RecursiveCallbackFilterIterator(
                new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS),
                static function (\SplFileInfo $item) use ($skipped): bool {
                    $path = str_replace('\\', '/', $item->getPathname());
                    return !in_array($item->getFilename(), self::NEVER_STAGED, true) && !in_array($path, $skipped, true);
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
