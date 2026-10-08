<?php

declare(strict_types=1);

namespace Codad5\WPToolkit\Tests\Unit\Bootstrap;

use Brain\Monkey\Actions;
use Brain\Monkey\Functions;
use Codad5\WPToolkit\Foundation\Application;
use Codad5\WPToolkit\Tests\TestCase;
use RuntimeException;

/**
 * ADR-0013 / ADR-0020: a plugin started through the guard never takes the site down, and the
 * guard — not the possibly-foreign loaded WPToolkit — decides compatibility.
 */
final class GuardTest extends TestCase
{
    private string $tmp;

    /** @var callable|null */
    private $activationCallback = null;

    /** @var callable|null */
    private $notice = null;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tmp = sys_get_temp_dir() . '/wptoolkit-guard-' . uniqid();
        mkdir($this->tmp . '/plugin', 0777, true);
        $GLOBALS['wp_version'] = '6.6.0';
        Functions\when('current_user_can')->justReturn(true);
        Functions\when('register_activation_hook')->alias(function ($file, $callback) {
            $this->activationCallback = $callback;
        });
        Actions\expectAdded('admin_notices')->zeroOrMoreTimes()->whenHappen(function ($callback) {
            $this->notice = $callback;
        });
    }

    protected function tearDown(): void
    {
        unset($GLOBALS['wp_version'], $GLOBALS['guard_test_loaded']);
        $this->removeDirectory($this->tmp);
        parent::tearDown();
    }

    public function test_boots_when_every_requirement_is_met(): void
    {
        $booted = false;

        $result = $this->guard()($this->pluginFile(), ['php' => '7.0', 'wp' => '6.4', 'autoload' => false], function () use (&$booted) {
            $booted = true;
        });

        self::assertTrue($result);
        self::assertTrue($booted);
    }

    public function test_too_old_php_keeps_the_plugin_inert_and_explains_why(): void
    {
        $booted = false;

        $result = $this->guard()($this->pluginFile(), ['php' => '99.0', 'name' => 'Shop <Pro>'], function () use (&$booted) {
            $booted = true;
        });

        self::assertFalse($result);
        self::assertFalse($booted);
        $notice = $this->renderNotice();
        self::assertStringContainsString('Shop &lt;Pro&gt; has been paused', $notice);
        self::assertStringContainsString('PHP 99.0 or newer is required', $notice);
    }

    public function test_too_old_wordpress_is_reported(): void
    {
        $GLOBALS['wp_version'] = '6.0';

        self::assertFalse($this->guard()($this->pluginFile(), ['wp' => '6.4'], fn () => null));
        self::assertStringContainsString('WordPress 6.4 or newer is required; this site runs 6.0', $this->renderNotice());
    }

    public function test_missing_extension_is_reported(): void
    {
        self::assertFalse($this->guard()($this->pluginFile(), ['extensions' => ['definitely_not_an_extension']], fn () => null));
        self::assertStringContainsString('&quot;definitely_not_an_extension&quot;', $this->renderNotice());
    }

    public function test_missing_required_plugin_is_reported(): void
    {
        Functions\when('get_option')->justReturn(['other/other.php']);
        Functions\when('get_site_option')->justReturn([]);

        self::assertFalse($this->guard()($this->pluginFile(), ['plugins' => ['woocommerce/woocommerce.php']], fn () => null));
        self::assertStringContainsString('woocommerce/woocommerce.php', $this->renderNotice());
    }

    public function test_activating_while_requirements_fail_is_refused(): void
    {
        $this->guard()($this->pluginFile(), ['php' => '99.0'], fn () => null);
        Functions\expect('wp_die')->once();

        self::assertIsCallable($this->activationCallback);
        ($this->activationCallback)();
    }

    public function test_an_exception_during_boot_is_contained_and_logged(): void
    {
        Functions\expect('error_log')->once()->with(\Mockery::pattern('/could not start: RuntimeException: boom/'));

        $result = $this->guard()($this->pluginFile(), ['autoload' => false, 'rethrow' => false], function () {
            throw new RuntimeException('boom');
        });

        self::assertFalse($result);
        self::assertStringContainsString('failed while starting', $this->renderNotice());
    }

    public function test_a_syntax_error_in_the_plugin_code_is_contained(): void
    {
        // A plugin that declares a PHP version but uses newer syntax: the file can't even parse.
        $broken = $this->tmp . '/plugin/broken.php';
        file_put_contents($broken, "<?php\nfunction broken( {\n");
        Functions\expect('error_log')->once()->with(\Mockery::pattern('/ParseError/'));

        $result = $this->guard()($this->pluginFile(), ['autoload' => false, 'rethrow' => false], function () use ($broken) {
            require $broken;
        });

        self::assertFalse($result);
    }

    public function test_rethrow_lets_developers_see_the_real_error(): void
    {
        Functions\when('error_log')->justReturn(true);
        $this->expectException(RuntimeException::class);

        $this->guard()($this->pluginFile(), ['autoload' => false, 'rethrow' => true], function () {
            throw new RuntimeException('boom');
        });
    }

    public function test_auto_autoload_prefers_the_plugins_composer_autoloader(): void
    {
        mkdir($this->tmp . '/plugin/vendor');
        file_put_contents($this->tmp . '/plugin/vendor/autoload.php', "<?php \$GLOBALS['guard_test_loaded'] = 'composer';");

        self::assertTrue($this->guard()($this->pluginFile(), [], fn () => null));
        self::assertSame('composer', $GLOBALS['guard_test_loaded'] ?? null);
    }

    public function test_auto_autoload_falls_back_to_the_standalone_loader(): void
    {
        $copy = $this->fakeCopy('1.0.0-dev', withStandaloneLoader: true);

        self::assertTrue($this->guard($copy)($this->pluginFile(), [], fn () => null));
        self::assertSame('standalone', $GLOBALS['guard_test_loaded'] ?? null);
    }

    public function test_a_missing_class_loader_is_reported_instead_of_a_fatal_error(): void
    {
        self::assertFalse($this->guard()($this->pluginFile(), ['autoload' => 'composer'], fn () => null));
        self::assertStringContainsString('class loader is missing', $this->renderNotice());
    }

    /**
     * The scenario the maintainer raised: an unscoped copy from another plugin is already loaded.
     * The guard must judge it with its own code, not with the loaded copy's classes.
     */
    public function test_an_incompatible_copy_loaded_by_another_plugin_keeps_this_one_inert(): void
    {
        class_exists(Application::class); // the repository's copy plays "the other plugin's copy"
        $mine = $this->fakeCopy('2.3.0');
        $booted = false;

        $result = $this->guard($mine)($this->pluginFile(), ['toolkit' => '^2.0', 'autoload' => false], function () use (&$booted) {
            $booted = true;
        });

        self::assertFalse($result);
        self::assertFalse($booted);
        $notice = $this->renderNotice();
        self::assertStringContainsString('WPToolkit ^2.0 is required, but WPToolkit ' . Application::VERSION . ' was already loaded', $notice);
        self::assertStringContainsString('Deactivate that one', $notice);
    }

    public function test_a_compatible_copy_loaded_by_another_plugin_is_fine(): void
    {
        class_exists(Application::class);

        self::assertTrue($this->guard($this->fakeCopy('1.0.0'))($this->pluginFile(), ['toolkit' => '^1.0', 'autoload' => false], fn () => null));
    }

    public function test_toolkit_path_points_the_guard_at_another_copy(): void
    {
        class_exists(Application::class);
        $prefixed = $this->fakeCopy('3.0.0');
        $booted = false;

        // Guard from the repository, inspecting a copy elsewhere (as with Strauss's vendor-prefixed/).
        $result = $this->guard()($this->pluginFile(), ['toolkit_path' => $prefixed, 'toolkit' => '^3.0', 'autoload' => false], function () use (&$booted) {
            $booted = true;
        });

        self::assertFalse($result, 'the loaded copy (repository) is not ^3.0, and the guard judged by the inspected copy');
        self::assertFalse($booted);
    }

    public function test_the_guard_leaks_no_variables_into_the_including_scope(): void
    {
        $before = array_keys(get_defined_vars());
        $guard = require dirname(__DIR__, 3) . '/bootstrap/guard.php';
        $after = array_diff(array_keys(get_defined_vars()), $before, ['before', 'guard']);

        self::assertSame([], array_values($after));
        self::assertIsCallable($guard);
    }

    private function guard(?string $copy = null): callable
    {
        return require ($copy ?? dirname(__DIR__, 3)) . '/bootstrap/guard.php';
    }

    private function pluginFile(): string
    {
        return $this->tmp . '/plugin/plugin.php';
    }

    /**
     * A copy of WPToolkit somewhere else on disk: the real guard, and an Application.php that is
     * read for its version but never loaded.
     */
    private function fakeCopy(string $version, bool $withStandaloneLoader = false): string
    {
        $copy = $this->tmp . '/copy-' . uniqid();
        mkdir($copy . '/bootstrap', 0777, true);
        mkdir($copy . '/src/Foundation', 0777, true);
        copy(dirname(__DIR__, 3) . '/bootstrap/guard.php', $copy . '/bootstrap/guard.php');
        file_put_contents(
            $copy . '/src/Foundation/Application.php',
            "<?php\nnamespace Codad5\\WPToolkit\\Foundation;\nfinal class Application { public const VERSION = '{$version}'; }\n"
        );
        if ($withStandaloneLoader) {
            file_put_contents($copy . '/bootstrap/autoload.php', "<?php \$GLOBALS['guard_test_loaded'] = 'standalone';");
        }

        return $copy;
    }

    private function renderNotice(): string
    {
        self::assertIsCallable($this->notice, 'the guard should have added an admin notice');

        ob_start();
        ($this->notice)();
        return (string) ob_get_clean();
    }

    private function removeDirectory(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }
        foreach (new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS), \RecursiveIteratorIterator::CHILD_FIRST) as $item) {
            $item->isDir() ? rmdir($item->getPathname()) : unlink($item->getPathname());
        }
        rmdir($dir);
    }
}
