<?php

declare(strict_types=1);

namespace Codad5\WPToolkit\Tests\Unit\Bootstrap;

use PHPUnit\Framework\TestCase;

/**
 * ADR-0005 / ADR-0014: two plugins bundle two different versions of WPToolkit, each scoped with
 * bin/scope.php (no Composer), and both run in one PHP process without colliding.
 *
 * Runs PHP in a subprocess so the scoped classes never mix with the test run's own.
 */
final class ScopedCopiesTest extends TestCase
{
    private string $tmp;

    protected function setUp(): void
    {
        $this->tmp = sys_get_temp_dir() . '/wptoolkit-scope-' . uniqid();
        mkdir($this->tmp);
    }

    protected function tearDown(): void
    {
        foreach (new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($this->tmp, \FilesystemIterator::SKIP_DOTS), \RecursiveIteratorIterator::CHILD_FIRST) as $item) {
            $item->isDir() ? rmdir($item->getPathname()) : unlink($item->getPathname());
        }
        rmdir($this->tmp);
    }

    public function test_scope_rewrites_every_reference_and_leaves_none_behind(): void
    {
        $copy = $this->copyOfToolkit('alpha');

        [$code, $output] = $this->php([dirname(__DIR__, 3) . '/bin/scope.php', 'Alpha\\Vendor\\WPToolkit', $copy]);

        self::assertSame(0, $code, $output);
        self::assertStringContainsString('Scoped', $output);
        self::assertSame([], $this->filesStillReferencing($copy . '/src'), 'unscoped references remain');
    }

    public function test_scoping_twice_is_refused(): void
    {
        $copy = $this->copyOfToolkit('alpha');
        $this->php([dirname(__DIR__, 3) . '/bin/scope.php', 'Alpha\\WPToolkit', $copy]);

        [$code] = $this->php([dirname(__DIR__, 3) . '/bin/scope.php', 'Alpha\\WPToolkit', $copy]);

        self::assertSame(2, $code);
    }

    public function test_an_invalid_namespace_is_rejected(): void
    {
        [$code] = $this->php([dirname(__DIR__, 3) . '/bin/scope.php', 'not a namespace', $this->copyOfToolkit('x')]);

        self::assertSame(1, $code);
    }

    public function test_two_scoped_copies_of_different_versions_run_side_by_side(): void
    {
        $alpha = $this->copyOfToolkit('alpha');
        $beta = $this->copyOfToolkit('beta', version: '1.4.0');
        $scope = dirname(__DIR__, 3) . '/bin/scope.php';
        self::assertSame(0, $this->php([$scope, 'Alpha\\WPToolkit', $alpha])[0]);
        self::assertSame(0, $this->php([$scope, 'Beta\\WPToolkit', $beta])[0]);

        $script = $this->tmp . '/run.php';
        file_put_contents($script, <<<PHP
            <?php
            require '{$alpha}/bootstrap/autoload.php';
            require '{$beta}/bootstrap/autoload.php';
            \$loaders = count(spl_autoload_functions());
            require '{$alpha}/bootstrap/autoload.php'; // idempotent: no new loader
            \$a = \\Alpha\\WPToolkit\\Foundation\\Application::create('/plugins/alpha/alpha.php', ['slug' => 'alpha']);
            \$b = \\Beta\\WPToolkit\\Foundation\\Application::create('/plugins/beta/beta.php', ['slug' => 'beta']);
            echo json_encode([
                'versions' => [\\Alpha\\WPToolkit\\Foundation\\Application::VERSION, \\Beta\\WPToolkit\\Foundation\\Application::VERSION],
                'separateContainers' => \$a->container() !== \$b->container(),
                'handles' => [\$a->identity()->handle('api'), \$b->identity()->handle('api')],
                'ledger' => array_values(array_map(fn (\$c) => \$c['namespace'] . '@' . \$c['version'], \$GLOBALS['__wptoolkit_copies'])),
                'loadersStable' => \$loaders === count(spl_autoload_functions()),
                'unscopedLoaded' => class_exists('Codad5\\\\WPToolkit\\\\Foundation\\\\Application', false),
            ]);
            PHP);

        [$code, $output] = $this->php([$script]);
        self::assertSame(0, $code, $output);
        $result = json_decode($output, true);

        self::assertSame([\Codad5\WPToolkit\Foundation\Application::VERSION, '1.4.0'], $result['versions']);
        self::assertTrue($result['separateContainers']);
        self::assertSame(['alpha-api', 'beta-api'], $result['handles']);
        self::assertEqualsCanonicalizing(['Alpha\\WPToolkit@' . \Codad5\WPToolkit\Foundation\Application::VERSION, 'Beta\\WPToolkit@1.4.0'], $result['ledger']);
        self::assertTrue($result['loadersStable']);
        self::assertFalse($result['unscopedLoaded']);
    }

    /**
     * A copy of the package as a standalone install ships it: src/ and bootstrap/.
     */
    private function copyOfToolkit(string $name, ?string $version = null): string
    {
        $root = dirname(__DIR__, 3);
        $target = $this->tmp . '/' . $name;

        foreach (['src', 'bootstrap'] as $folder) {
            $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($root . '/' . $folder, \FilesystemIterator::SKIP_DOTS));
            foreach ($iterator as $file) {
                $destination = $target . '/' . $folder . substr($file->getPathname(), strlen($root . '/' . $folder));
                @mkdir(dirname($destination), 0777, true);
                copy($file->getPathname(), $destination);
            }
        }

        if ($version !== null) {
            $application = $target . '/src/Foundation/Application.php';
            $source = (string) file_get_contents($application);
            file_put_contents($application, (string) preg_replace("/VERSION = '[^']+'/", "VERSION = '{$version}'", $source));
        }

        return $target;
    }

    /**
     * @return list<string>
     */
    private function filesStillReferencing(string $dir): array
    {
        $found = [];
        foreach (new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS)) as $file) {
            foreach (\PhpToken::tokenize((string) file_get_contents($file->getPathname())) as $token) {
                if ($token->is([T_NAME_QUALIFIED, T_NAME_FULLY_QUALIFIED]) && str_starts_with(ltrim($token->text, '\\'), 'Codad5\\WPToolkit')) {
                    $found[] = $file->getFilename() . ': ' . $token->text;
                }
            }
        }

        return $found;
    }

    /**
     * @param list<string> $args
     * @return array{0: int, 1: string}
     */
    private function php(array $args): array
    {
        $command = escapeshellarg(PHP_BINARY) . ' ' . implode(' ', array_map('escapeshellarg', $args)) . ' 2>&1';
        exec($command, $lines, $code);

        return [$code, implode("\n", $lines)];
    }
}
