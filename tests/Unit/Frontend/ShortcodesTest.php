<?php

declare(strict_types=1);

namespace Codad5\WPToolkit\Tests\Unit\Frontend;

use Brain\Monkey\Functions;
use Codad5\WPToolkit\Adapters\Log\ArrayLogger;
use Codad5\WPToolkit\Assets\AssetManager;
use Codad5\WPToolkit\Assets\JsNamespace;
use Codad5\WPToolkit\Contracts\Log\LogLevel;
use Codad5\WPToolkit\Exceptions\InvalidConfigException;
use Codad5\WPToolkit\Exceptions\LifecycleException;
use Codad5\WPToolkit\Foundation\Identity;
use Codad5\WPToolkit\Frontend\Shortcodes;
use Codad5\WPToolkit\Tests\TestCase;
use Codad5\WPToolkit\View\PhpTemplateRenderer;
use Codad5\WPToolkit\View\TemplateLocator;
use RuntimeException;

final class ShortcodesTest extends TestCase
{
    /** @var array<string, callable> */
    private array $registered = [];

    /** @var list<string> */
    private array $enqueued = [];

    private ArrayLogger $logger;

    private AssetManager $assets;

    protected function setUp(): void
    {
        parent::setUp();
        $this->registered = [];
        $this->enqueued = [];
        $this->logger = new ArrayLogger();
        Functions\when('shortcode_exists')->alias(fn (string $tag): bool => isset($this->registered[$tag]));
        Functions\when('add_shortcode')->alias(function (string $tag, callable $callback): void {
            $this->registered[$tag] = $callback;
        });
        Functions\when('wp_enqueue_script')->alias(function (string $handle): bool {
            $this->enqueued[] = $handle;
            return true;
        });
        Functions\when('wp_enqueue_style')->alias(function (string $handle): bool {
            $this->enqueued[] = $handle;
            return true;
        });
        Functions\when('wp_add_inline_script')->justReturn(true);
        Functions\when('wp_set_script_translations')->justReturn(true);
        Functions\when('wp_style_add_data')->justReturn(true);
        Functions\when('is_user_logged_in')->justReturn(false);
        Functions\when('current_user_can')->justReturn(false);
    }

    public function test_the_default_tag_is_prefixed_by_the_slug_and_registered_with_wordpress(): void
    {
        $shortcodes = $this->shortcodes();
        $shortcodes->add('widget', fn (): string => 'x')->public();

        self::assertArrayHasKey('my_plugin_widget', $this->registered);
    }

    public function test_an_explicit_tag_is_used_verbatim_for_tags_already_stored_in_content(): void
    {
        $shortcodes = $this->shortcodes();
        $shortcodes->add('widget', fn (): string => 'x', 'ucwp_widget')->public();

        self::assertArrayHasKey('ucwp_widget', $this->registered);
        self::assertSame('x', ($this->registered['ucwp_widget'])([], ''));
    }

    public function test_a_tag_someone_else_registered_is_refused(): void
    {
        $this->registered['gallery'] = static fn (): string => '';

        $this->expectException(InvalidConfigException::class);
        $this->shortcodes()->add('gallery', fn (): string => 'x', 'gallery');
    }

    public function test_two_shortcodes_cannot_share_a_name_or_a_tag(): void
    {
        $shortcodes = $this->shortcodes();
        $shortcodes->add('a', fn (): string => '', 'same')->public();

        $this->expectException(InvalidConfigException::class);
        $shortcodes->add('b', fn (): string => '', 'same');
    }

    public function test_the_handler_gets_only_declared_attributes_validated_and_typed(): void
    {
        $seen = null;
        $shortcodes = $this->shortcodes();
        $shortcodes->add('widget', function (array $atts) use (&$seen): string {
            $seen = $atts;
            return 'ok';
        })->public()->attributes([
            'count' => ['type' => 'int', 'default' => 10, 'rules' => 'integer|min:1|max:50'],
        ]);

        $shortcodes->render('widget', ['count' => '7', 'evil' => '<script>alert(1)</script>', 'id' => '5']);

        self::assertSame(['count' => 7], $seen, 'undeclared attributes never reach the handler');
    }

    public function test_an_invalid_attribute_falls_back_to_its_default_and_is_logged_by_name_not_value(): void
    {
        $seen = null;
        $shortcodes = $this->shortcodes();
        $shortcodes->add('widget', function (array $atts) use (&$seen): string {
            $seen = $atts;
            return 'ok';
        })->public()->attributes([
            'count' => ['type' => 'int', 'default' => 10, 'rules' => 'integer|min:1|max:50'],
        ]);

        $shortcodes->render('widget', ['count' => '99999<script>']);

        self::assertSame(['count' => 10], $seen);
        $record = $this->logger->records[0];
        self::assertSame(LogLevel::Notice, $record['level']);
        self::assertSame('count', $record['context']['fields']);
        self::assertStringNotContainsString('script', json_encode($record));
    }

    public function test_a_shortcode_without_an_access_rule_prints_nothing_and_says_why(): void
    {
        $ran = false;
        $shortcodes = $this->shortcodes();
        $shortcodes->add('widget', function () use (&$ran): string {
            $ran = true;
            return 'secret';
        });

        self::assertSame('', $shortcodes->render('widget'));
        self::assertFalse($ran, 'the handler must not run without an access rule');
        self::assertStringContainsString('no access rule', $this->logger->messages()[0]);
    }

    public function test_in_development_a_missing_access_rule_fails_at_verify(): void
    {
        $shortcodes = $this->shortcodes(development: true);
        $shortcodes->add('widget', fn (): string => 'x');

        $this->expectException(LifecycleException::class);
        $shortcodes->verify();
    }

    public function test_logged_in_and_capability_rules_hide_the_output_from_everyone_else(): void
    {
        $shortcodes = $this->shortcodes();
        $shortcodes->add('members', fn (): string => 'members only')->loggedIn();
        $shortcodes->add('admins', fn (): string => 'admins only')->can('manage_options');

        self::assertSame('', $shortcodes->render('members'));
        self::assertSame('', $shortcodes->render('admins'));

        Functions\when('is_user_logged_in')->justReturn(true);
        Functions\when('current_user_can')->alias(static fn (string $cap): bool => $cap === 'manage_options');
        self::assertSame('members only', $shortcodes->render('members'));
        self::assertSame('admins only', $shortcodes->render('admins'));
    }

    public function test_a_view_renders_returned_data_with_late_escaping(): void
    {
        $shortcodes = $this->shortcodes();
        $shortcodes->add('hello', fn (array $atts): array => ['name' => $atts['name']])
            ->public()
            ->attributes(['name' => ['type' => 'raw', 'default' => 'x']])
            ->view('admin/greeting');

        $html = $shortcodes->render('hello', ['name' => '<img src=x onerror=alert(1)>']);

        self::assertStringNotContainsString('<img', $html);
        self::assertStringContainsString('&lt;img', $html);
    }

    public function test_assets_load_only_when_the_shortcode_renders_and_access_passed(): void
    {
        $shortcodes = $this->shortcodes();
        $this->assets->script('widget', 'build/widget.js')->onDemand();
        $shortcodes->add('widget', fn (): string => '<div>x</div>')->public()->assets('widget');
        $shortcodes->add('private', fn (): string => 'p')->can('manage_options')->assets('widget');

        $shortcodes->render('private');
        self::assertSame([], $this->enqueued, 'a denied visitor does not cause assets to load');

        $this->assets->enqueue('front', '');
        self::assertSame([], $this->enqueued, 'on-demand assets are skipped by the enqueue hooks');

        $shortcodes->render('widget');
        self::assertSame(['my-plugin-widget'], $this->enqueued);
    }

    public function test_a_throwing_handler_prints_nothing_and_is_logged_in_production(): void
    {
        $shortcodes = $this->shortcodes();
        $shortcodes->add('widget', function (): string {
            throw new RuntimeException('database password is hunter2');
        })->public();

        self::assertSame('', $shortcodes->render('widget'));
        self::assertSame(LogLevel::Error, $this->logger->records[0]['level']);
    }

    public function test_in_development_a_throwing_handler_is_rethrown(): void
    {
        $shortcodes = $this->shortcodes(development: true);
        $shortcodes->add('widget', function (): string {
            throw new RuntimeException('boom');
        })->public();

        $this->expectException(RuntimeException::class);
        $shortcodes->render('widget');
    }

    public function test_the_wordpress_callback_survives_empty_attributes_passed_as_a_string(): void
    {
        $shortcodes = $this->shortcodes();
        $shortcodes->add('widget', fn (array $atts): string => 'n=' . count($atts))->public();

        self::assertSame('n=0', ($this->registered['my_plugin_widget'])('', null));
    }

    public function test_attribute_names_must_be_lowercase(): void
    {
        $this->expectException(InvalidConfigException::class);
        $this->shortcodes()->add('widget', fn (): string => '')->attributes(['CoinCount' => ['type' => 'int']]);
    }

    private function shortcodes(bool $development = false): Shortcodes
    {
        $identity = new Identity('my-plugin');
        $this->assets = new AssetManager(
            $identity,
            new JsNamespace($identity, '1.2.3'),
            __DIR__ . '/../../Fixtures/assets',
            'https://example.test/wp-content/plugins/my-plugin',
            '/srv/lib/wptoolkit',
            'https://example.test/lib',
            'my-plugin',
            '/plugin/languages'
        );

        return new Shortcodes(
            $identity,
            new PhpTemplateRenderer(new TemplateLocator([__DIR__ . '/../../Fixtures/views'], null)),
            $this->assets,
            $this->logger,
            $development
        );
    }
}
