<?php

declare(strict_types=1);

namespace Codad5\WPToolkit\Tests\Unit\Foundation;

use Brain\Monkey\Actions;
use Brain\Monkey\Functions;
use Codad5\WPToolkit\Contracts\Container\Container;
use Codad5\WPToolkit\Exceptions\InvalidConfigException;
use Codad5\WPToolkit\Exceptions\LifecycleException;
use Codad5\WPToolkit\Foundation\Application;
use Codad5\WPToolkit\Foundation\Config;
use Codad5\WPToolkit\Foundation\Identity;
use Codad5\WPToolkit\Tests\Fixtures\Container\Logger;
use Codad5\WPToolkit\Tests\Fixtures\Providers\EventLog;
use Codad5\WPToolkit\Tests\Fixtures\Providers\FirstProvider;
use Codad5\WPToolkit\Tests\Fixtures\Providers\SecondProvider;
use Codad5\WPToolkit\Tests\Fixtures\Providers\Uninstaller;
use Codad5\WPToolkit\Tests\TestCase;

final class ApplicationTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        EventLog::$events = [];
        Functions\when('is_textdomain_loaded')->justReturn(false);
        Functions\when('plugin_basename')->justReturn('my-plugin/my-plugin.php');
        Functions\when('register_activation_hook')->justReturn(null);
        Functions\when('register_deactivation_hook')->justReturn(null);
        Functions\when('load_plugin_textdomain')->alias(static function (string $domain): bool {
            EventLog::$events[] = "textdomain:{$domain}";
            return true;
        });
    }

    public function test_registers_providers_immediately_and_boots_them_on_init(): void
    {
        Actions\expectAdded('init')->once();

        $app = $this->app()->providers([FirstProvider::class, SecondProvider::class])->boot();

        self::assertSame(['register:first', 'register:second'], EventLog::$events);
        self::assertFalse($app->isBooted());

        $app->bootProviders(); // what WordPress does on init

        self::assertTrue($app->isBooted());
        self::assertSame(
            ['register:first', 'register:second', 'textdomain:my-plugin', 'boot:first', 'boot:second', 'boot:second:' . Logger::class],
            EventLog::$events
        );
    }

    public function test_text_domain_is_loaded_before_any_provider_boots(): void
    {
        do_action('init'); // Brain Monkey records it, so did_action('init') is now 1

        $this->app()->providers([FirstProvider::class])->boot();

        self::assertSame(['register:first', 'textdomain:my-plugin', 'boot:first'], EventLog::$events);
    }

    public function test_boots_immediately_when_init_already_ran(): void
    {
        do_action('init'); // Brain Monkey records it, so did_action('init') is now 1
        Actions\expectAdded('init')->never();

        $app = $this->app()->providers([FirstProvider::class])->boot();

        self::assertTrue($app->isBooted());
    }

    public function test_boot_injects_services_into_provider_boot_methods(): void
    {
        do_action('init'); // Brain Monkey records it, so did_action('init') is now 1

        $this->app()->providers([SecondProvider::class])->boot();

        self::assertContains('boot:second:' . Logger::class, EventLog::$events);
    }

    public function test_boot_and_boot_providers_are_idempotent(): void
    {
        do_action('init'); // Brain Monkey records it, so did_action('init') is now 1

        $app = $this->app()->providers([FirstProvider::class])->boot();
        $app->boot();
        $app->bootProviders();

        self::assertSame(['register:first', 'textdomain:my-plugin', 'boot:first'], EventLog::$events);
    }

    public function test_core_services_are_in_the_container(): void
    {
        $app = $this->app();

        self::assertSame($app, $app->container()->get(Application::class));
        self::assertSame($app->config(), $app->container()->get(Config::class));
        self::assertSame($app->container(), $app->container()->get(Container::class));
        self::assertSame('my-plugin', $app->identity()->slug);
        self::assertSame($app->identity(), $app->container()->get(Identity::class));
    }

    public function test_two_applications_share_nothing(): void
    {
        $a = Application::create('/a/a.php', ['slug' => 'plugin-a']);
        $b = Application::create('/b/b.php', ['slug' => 'plugin-b']);
        $a->container()->singleton(Logger::class);
        $b->container()->singleton(Logger::class);

        self::assertNotSame($a->container(), $b->container());
        self::assertNotSame($a->container()->get(Logger::class), $b->container()->get(Logger::class));
    }

    public function test_rejects_a_class_that_is_not_a_provider(): void
    {
        $this->expectException(InvalidConfigException::class);

        $this->app()->providers([Logger::class]); // @phpstan-ignore argument.type
    }

    public function test_cannot_add_providers_after_boot(): void
    {
        do_action('init'); // Brain Monkey records it, so did_action('init') is now 1
        $app = $this->app()->boot();

        $this->expectException(LifecycleException::class);

        $app->providers([FirstProvider::class]);
    }

    public function test_shutdown_removes_the_init_hook_it_added(): void
    {
        $app = $this->app()->boot();

        self::assertNotFalse(has_action('init', [$app, 'bootProviders']));

        $app->shutdown();

        self::assertFalse(has_action('init', [$app, 'bootProviders']));
    }

    public function test_a_theme_loads_its_text_domain_from_its_own_directory(): void
    {
        do_action('init'); // Brain Monkey records it, so did_action('init') is now 1
        Functions\expect('load_theme_textdomain')->once()->with('my-theme', '/themes/my-theme/lang');

        Application::create('/themes/my-theme/functions.php', [
            'slug' => 'my-theme',
            'type' => 'theme',
            'domain_path' => '/lang/',
        ])->boot();
    }

    public function test_boot_registers_activation_and_deactivation_hooks_for_plugins(): void
    {
        $registered = [];
        Functions\when('register_activation_hook')->alias(function ($file, $cb) use (&$registered) {
            $registered['activate'] = [$file, $cb];
        });
        Functions\when('register_deactivation_hook')->alias(function ($file, $cb) use (&$registered) {
            $registered['deactivate'] = [$file, $cb];
        });

        $app = $this->app()->boot();

        self::assertSame(['/plugins/my-plugin/my-plugin.php', [$app, 'activate']], $registered['activate']);
        self::assertSame(['/plugins/my-plugin/my-plugin.php', [$app, 'deactivate']], $registered['deactivate']);
    }

    public function test_activate_runs_provider_activate_and_registers_the_static_uninstall_handler(): void
    {
        Functions\expect('register_uninstall_hook')
            ->once()
            ->with('/plugins/my-plugin/my-plugin.php', [Uninstaller::class, 'uninstall']);
        $app = Application::create('/plugins/my-plugin/my-plugin.php', ['slug' => 'my-plugin', 'uninstall' => Uninstaller::class])
            ->providers([FirstProvider::class])
            ->boot();

        $app->activate();

        self::assertContains('activate:first', EventLog::$events);
    }

    public function test_activate_rejects_an_uninstall_handler_without_a_static_method(): void
    {
        $app = Application::create('/p/p.php', ['slug' => 'my-plugin', 'uninstall' => Logger::class])->boot();

        $this->expectException(InvalidConfigException::class);

        $app->activate();
    }

    public function test_deactivate_runs_provider_deactivate_then_removes_every_hook(): void
    {
        $app = $this->app()->providers([FirstProvider::class])->boot();
        self::assertNotFalse(has_action('init', [$app, 'bootProviders']));

        $app->deactivate();

        self::assertContains('deactivate:first', EventLog::$events);
        self::assertFalse(has_action('init', [$app, 'bootProviders']));
    }

    public function test_themes_do_not_register_plugin_lifecycle_hooks(): void
    {
        do_action('init');
        Functions\when('load_theme_textdomain')->justReturn(true);
        $called = false;
        Functions\when('register_activation_hook')->alias(function () use (&$called) {
            $called = true;
        });

        Application::create('/themes/t/functions.php', ['slug' => 't', 'type' => 'theme'])->boot();

        self::assertFalse($called);
    }

    private function app(): Application
    {
        return Application::create('/plugins/my-plugin/my-plugin.php', ['slug' => 'my-plugin']);
    }
}
