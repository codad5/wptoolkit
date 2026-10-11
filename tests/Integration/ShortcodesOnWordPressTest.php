<?php

declare(strict_types=1);

namespace Codad5\WPToolkit\Tests\Integration;

use Codad5\WPToolkit\Adapters\Log\ArrayLogger;
use Codad5\WPToolkit\Assets\AssetManager;
use Codad5\WPToolkit\Assets\JsNamespace;
use Codad5\WPToolkit\Foundation\Identity;
use Codad5\WPToolkit\Frontend\Shortcodes;
use Codad5\WPToolkit\View\PhpTemplateRenderer;
use Codad5\WPToolkit\View\TemplateLocator;
use PHPUnit\Framework\TestCase;

/**
 * Shortcodes through WordPress's real parser: `do_shortcode()` lowercases and quotes attributes in
 * ways a unit test can't reproduce, and an attacker only controls post content and shortcode text.
 */
final class ShortcodesOnWordPressTest extends TestCase
{
    private Shortcodes $shortcodes;

    protected function setUp(): void
    {
        $identity = new Identity('wptk-sc');
        $this->shortcodes = new Shortcodes(
            $identity,
            new PhpTemplateRenderer(new TemplateLocator([dirname(__DIR__) . '/Fixtures/views'], null)),
            new AssetManager($identity, new JsNamespace($identity, '1.0.0'), dirname(__DIR__) . '/Fixtures/assets', content_url(), dirname(__DIR__, 2), content_url(), 'wptk-sc', '/tmp'),
            new ArrayLogger()
        );
    }

    protected function tearDown(): void
    {
        remove_shortcode('wptk_sc_hello');
        wp_set_current_user(0);
    }

    public function test_wordpress_parses_attributes_and_our_handler_gets_only_declared_ones(): void
    {
        $seen = [];
        $this->shortcodes->add('hello', function (array $a) use (&$seen): string {
            $seen = $a;
            return 'hi';
        })->public()->attributes(['count' => ['type' => 'int', 'default' => 3, 'rules' => 'integer|min:1|max:9']]);

        self::assertSame('hi', do_shortcode('[wptk_sc_hello count="5" role=administrator]'));
        self::assertSame(['count' => 5], $seen);
    }

    public function test_a_visitor_cannot_run_a_capability_shortcode(): void
    {
        $this->shortcodes->add('hello', fn (): string => 'private')->can('manage_options');

        self::assertSame('', do_shortcode('[wptk_sc_hello]'));

        wp_set_current_user(1);
        self::assertSame('private', do_shortcode('[wptk_sc_hello]'));
    }

    public function test_output_is_escaped_by_the_view_not_the_attribute(): void
    {
        $this->shortcodes->add('hello', fn (array $a): array => ['name' => $a['name']])
            ->public()
            ->attributes(['name' => ['type' => 'text', 'default' => 'x']])
            ->view('admin/greeting');

        $html = do_shortcode('[wptk_sc_hello name="<b onmouseover=alert(1)>"]');

        self::assertStringNotContainsString('<b ', $html);
    }
}
