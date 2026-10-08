<?php

declare(strict_types=1);

namespace Codad5\WPToolkit\Tests\Unit\Assets;

use Brain\Monkey\Functions;
use Codad5\WPToolkit\Adapters\Log\ArrayLogger;
use Codad5\WPToolkit\Assets\Asset;
use Codad5\WPToolkit\Assets\AssetManager;
use Codad5\WPToolkit\Assets\JsNamespace;
use Codad5\WPToolkit\Exceptions\InvalidConfigException;
use Codad5\WPToolkit\Foundation\Container;
use Codad5\WPToolkit\Foundation\HookRegistrar;
use Codad5\WPToolkit\Foundation\Identity;
use Codad5\WPToolkit\Http\Dispatcher;
use Codad5\WPToolkit\Http\Router;
use Codad5\WPToolkit\Http\Transport\AjaxTransport;
use Codad5\WPToolkit\Http\Transport\RestTransport;
use Codad5\WPToolkit\Tests\TestCase;

final class AssetManagerTest extends TestCase
{
    private const DIR = __DIR__ . '/../../Fixtures/assets';

    /** @var list<array{string, mixed}> */
    private array $calls = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->calls = [];
        foreach (['wp_enqueue_script', 'wp_enqueue_style', 'wp_add_inline_script', 'wp_set_script_translations', 'wp_style_add_data'] as $function) {
            Functions\when($function)->alias(function (...$args) use ($function) {
                $this->calls[] = [$function, $args];
                return true;
            });
        }
    }

    public function test_declaring_assets_touches_nothing_until_the_enqueue_hook(): void
    {
        $assets = $this->assets();
        $assets->script('admin', 'build/admin.js')->in(Asset::ADMIN);
        $assets->style('front', 'build/front.css');

        self::assertSame([], $this->calls, 'C4: nothing reaches WordPress outside an enqueue hook');

        $hooks = new HookRegistrar();
        $assets->register($hooks);
        self::assertSame(['wp_enqueue_scripts', 'admin_enqueue_scripts', 'login_enqueue_scripts'], array_column($hooks->all(), 'hook'));
    }

    public function test_assets_load_only_in_their_context_and_when_their_condition_holds(): void
    {
        $assets = $this->assets();
        $assets->script('admin', 'build/admin.js')->in(Asset::ADMIN)->when(static fn (string $hook): bool => $hook === 'toplevel_page_books');
        $assets->style('front', 'build/front.css');

        $assets->enqueue(Asset::ADMIN, 'index.php');
        self::assertSame([], $this->calls);

        $assets->enqueue(Asset::ADMIN, 'toplevel_page_books');
        self::assertSame('my-plugin-admin', $this->call('wp_enqueue_script')[0]);
        self::assertNull($this->call('wp_enqueue_style'));
    }

    public function test_a_wordpress_scripts_manifest_supplies_deps_version_and_translations(): void
    {
        $assets = $this->assets();
        $assets->script('admin', 'build/admin.js')->in(Asset::ADMIN)->dependsOn('jquery');

        $assets->enqueue(Asset::ADMIN, '');

        [$handle, $url, $deps, $version, $args] = $this->call('wp_enqueue_script');
        self::assertSame('my-plugin-admin', $handle);
        self::assertSame('https://example.test/wp-content/plugins/my-plugin/build/admin.js', $url);
        self::assertSame(['wp-element', 'wp-i18n', 'jquery'], $deps);
        self::assertSame('abc123', $version);
        self::assertSame(['in_footer' => true], $args);
        self::assertSame(['my-plugin-admin', 'my-plugin', '/plugin/languages'], $this->call('wp_set_script_translations'));
    }

    public function test_styles_with_an_rtl_sibling_swap_on_rtl_sites(): void
    {
        $assets = $this->assets();
        $assets->style('front', 'build/front.css');
        $assets->style('plain', 'build/plain.css');

        $assets->enqueue(Asset::FRONT, '');

        self::assertSame([['my-plugin-front', 'rtl', 'replace']], array_column(array_filter($this->calls, static fn ($c) => $c[0] === 'wp_style_add_data'), 1));
    }

    public function test_script_data_is_merged_into_the_locked_namespace_with_the_toolkit_version(): void
    {
        $assets = $this->assets();
        $assets->script('front', 'https://cdn.example.test/x.js')->with(['perPage' => 20, 'html' => '</script><b>']);

        $assets->enqueue(Asset::FRONT, '');

        [$handle, $js, $position] = $this->call('wp_add_inline_script');
        self::assertSame(['my-plugin-front', 'before'], [$handle, $position]);
        self::assertStringContainsString('Object.defineProperty(w,n,{value:{},writable:false,configurable:false', $js);
        self::assertStringContainsString('"toolkitVersion":"1.2.3"', $js);
        self::assertStringContainsString('"perPage":20', $js);
        self::assertStringNotContainsString('</script>', $js, 'data cannot close the script element');
        self::assertSame('https://cdn.example.test/x.js', $this->call('wp_enqueue_script')[1]);
    }

    public function test_the_api_client_gets_this_plugins_routes(): void
    {
        Functions\stubs(['rest_url' => 'https://example.test/wp-json/', 'admin_url' => 'https://example.test/wp-admin/admin-ajax.php']);
        Functions\when('wp_create_nonce')->alias(static fn (string $action) => 'nonce:' . $action);
        $router = $this->router();
        $router->get('todos', fn () => [])->can('edit_posts');
        $router->post('todos', fn () => [])->can('edit_posts')->exposeVia('ajax');

        $assets = $this->assets();
        $assets->client($router);
        $assets->enqueue(Asset::FRONT, '');

        self::assertSame('https://example.test/wp-content/plugins/my-plugin/vendor/wptoolkit/resources/js/client.js', $this->call('wp_enqueue_script')[1]);
        $inline = array_values(array_filter($this->calls, static fn ($c) => $c[0] === 'wp_add_inline_script'));
        self::assertSame(['after', 'before'], [$inline[0][1][2], $inline[1][1][2]]);
        self::assertStringContainsString('e.api=w[n].__clients[v](e.__api)', $inline[0][1][1]);
        self::assertStringContainsString('"todos":{"methods":["GET","POST"],"transport":"rest","namespace":"my-plugin/v1","path":"todos"}', $inline[1][1][1]);
        self::assertStringContainsString('"restNonce":"nonce:wp_rest"', $inline[1][1][1]);
    }

    /**
     * The client registers its factory under its own VERSION; PHP looks it up by Application::VERSION.
     * If they drift, `window.wptoolkit[slug].api` silently never appears.
     */
    public function test_the_js_client_version_matches_the_library_version(): void
    {
        $js = (string) file_get_contents(__DIR__ . '/../../../resources/js/client.js');

        self::assertSame(1, preg_match("/var VERSION = '([^']+)';/", $js, $match));
        self::assertSame(\Codad5\WPToolkit\Foundation\Application::VERSION, $match[1]);
    }

    public function test_bad_declarations_are_refused(): void
    {
        $assets = $this->assets();
        $assets->script('admin', 'a.js');

        foreach ([
            static fn () => $assets->script('admin', 'b.js'),
            static fn () => $assets->script('Bad Name', 'b.js'),
            static fn () => $assets->script('ok', 'b.js')->in('everywhere'),
        ] as $i => $bad) {
            try {
                $bad();
                self::fail('case ' . $i);
            } catch (InvalidConfigException) {
                self::addToAssertionCount(1);
            }
        }
    }

    /**
     * The generated script, run in Node: the namespace survives `window.wptoolkit = {}`, and two
     * plugins' entries (and two scripts of one plugin) merge instead of replacing each other.
     */
    public function test_the_namespace_script_locks_and_merges_in_a_real_js_engine(): void
    {
        $node = trim((string) shell_exec(PHP_OS_FAMILY === 'Windows' ? 'where node 2>NUL' : 'command -v node 2>/dev/null'));
        if ($node === '') {
            self::markTestSkipped('Node.js is not installed; the CI js job runs this check (tests/js).');
        }

        $a = new JsNamespace(new Identity('plugin-a'), '1.0.0');
        $b = new JsNamespace(new Identity('plugin-b'), '1.1.0');
        $script = 'var window = {};'
            . $a->merge(['one' => 1])
            . $b->merge(['two' => 2])
            . 'window.wptoolkit = {};'
            . $a->merge(['three' => 3])
            . 'console.log(JSON.stringify(window.wptoolkit));';
        $file = tempnam(sys_get_temp_dir(), 'ns') . '.js';
        file_put_contents($file, $script);

        $output = trim((string) shell_exec('node ' . escapeshellarg($file)));
        unlink($file);

        self::assertSame('{"plugin-a":{"toolkitVersion":"1.0.0","one":1,"three":3},"plugin-b":{"toolkitVersion":"1.1.0","two":2}}', $output);
    }

    private function assets(): AssetManager
    {
        return new AssetManager(
            new Identity('my-plugin'),
            new JsNamespace(new Identity('my-plugin'), '1.2.3'),
            self::DIR,
            'https://example.test/wp-content/plugins/my-plugin',
            '/srv/lib/wptoolkit',
            'https://example.test/wp-content/plugins/my-plugin/vendor/wptoolkit',
            'my-plugin',
            '/plugin/languages'
        );
    }

    private function router(): Router
    {
        $identity = new Identity('my-plugin');
        $dispatcher = new Dispatcher(new Container(), $identity, new ArrayLogger());
        $hooks = new HookRegistrar();

        return new Router($hooks, new RestTransport($dispatcher, $identity), new AjaxTransport($dispatcher, $identity, $hooks));
    }

    /**
     * @return list<mixed>|null The first call's arguments.
     */
    private function call(string $function): ?array
    {
        foreach ($this->calls as [$name, $args]) {
            if ($name === $function) {
                return $args;
            }
        }

        return null;
    }
}
