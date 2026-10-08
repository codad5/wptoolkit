<?php

declare(strict_types=1);

namespace Codad5\WPToolkit\Tests\Unit\View;

use Brain\Monkey\Functions;
use Codad5\WPToolkit\Exceptions\ViewException;
use Codad5\WPToolkit\Tests\TestCase;
use Codad5\WPToolkit\View\PhpTemplateRenderer;
use Codad5\WPToolkit\View\TemplateLocator;

final class PhpTemplateRendererTest extends TestCase
{
    private const VIEWS = __DIR__ . '/../../Fixtures/views';

    private const THEME = __DIR__ . '/../../Fixtures/theme';

    protected function setUp(): void
    {
        parent::setUp();
        // No theme override unless a test installs one.
        Functions\when('locate_template')->justReturn('');
    }

    public function test_data_is_escaped_through_e(): void
    {
        $html = $this->renderer()->render('admin/greeting', ['name' => '<script>alert(1)</script>']);

        self::assertStringNotContainsString('<script>', $html);
        self::assertStringContainsString('&lt;script&gt;', $html);
    }

    public function test_layouts_sections_and_partials_compose(): void
    {
        $html = $this->renderer()->render('admin/page', ['name' => 'Ada', 'tip' => 'Save often']);

        self::assertStringStartsWith('<div class="wrap"><h1>Settings</h1><aside>Save often</aside><main>', $html);
        self::assertStringContainsString('<p>Body for Ada</p>', $html);
        self::assertStringContainsString('<h1>Ada</h1>', $html, 'the partial rendered inside the content section');
        self::assertStringEndsWith('</main></div>', trim($html));
    }

    public function test_a_theme_overrides_under_its_folder_like_0x(): void
    {
        Functions\when('locate_template')->alias(static function (array $candidates): string {
            foreach ($candidates as $candidate) {
                if (is_file(self::THEME . '/' . $candidate)) {
                    return self::THEME . '/' . $candidate;
                }
            }
            return '';
        });

        self::assertStringContainsString('Theme says hi to Ada', $this->renderer()->render('admin/greeting', ['name' => 'Ada']));
    }

    public function test_directories_resolve_to_their_index(): void
    {
        self::assertSame('<p>widget index</p>', trim($this->renderer()->render('widget')));
    }

    public function test_templates_run_in_a_sealed_scope(): void
    {
        self::assertSame('no this sealed', trim($this->renderer()->render('admin/scope')));
    }

    public function test_failures_throw_and_discard_partial_output(): void
    {
        $level = ob_get_level();

        try {
            $this->renderer()->render('admin/throws');
            self::fail('Expected a ViewException.');
        } catch (ViewException $e) {
            self::assertStringContainsString('View "admin/throws" failed: boom', $e->getMessage());
        }

        self::assertSame($level, ob_get_level(), 'no output buffer left behind');
    }

    public function test_unclosed_sections_are_reported(): void
    {
        $this->expectException(ViewException::class);
        $this->expectExceptionMessage('left section "never" open');

        $this->renderer()->render('admin/unclosed');
    }

    public function test_a_layout_that_uses_itself_is_stopped(): void
    {
        $this->expectException(ViewException::class);
        $this->expectExceptionMessage('nests more than 10 layouts');

        $this->renderer()->render('layouts/loop');
    }

    public function test_missing_views_say_where_they_looked(): void
    {
        $this->expectException(ViewException::class);
        $this->expectExceptionMessage('View "admin/nope" not found. Looked in: (theme)/my-plugin/admin/nope');

        $this->renderer()->render('admin/nope');
    }

    public function test_traversal_and_reserved_names_are_refused(): void
    {
        foreach (['../secrets', 'admin/../../etc/passwd', 'admin/<x>'] as $name) {
            try {
                $this->renderer()->render($name);
                self::fail($name);
            } catch (ViewException $e) {
                self::assertStringContainsString('is invalid', $e->getMessage());
            }
        }

        $this->expectException(ViewException::class);
        $this->renderer()->render('admin/greeting', ['e' => 'shadow the escaper']);
    }

    public function test_exists_and_shared_data(): void
    {
        $renderer = $this->renderer();
        $renderer->share('name', 'Everyone');

        self::assertTrue($renderer->exists('admin/greeting'));
        self::assertFalse($renderer->exists('admin/nope'));
        self::assertStringContainsString('Everyone', $renderer->render('admin/greeting'));
        self::assertStringContainsString('Ada', $renderer->render('admin/greeting', ['name' => 'Ada']), 'per-render data wins');
    }

    private function renderer(): PhpTemplateRenderer
    {
        return new PhpTemplateRenderer(new TemplateLocator([self::VIEWS], 'my-plugin'));
    }
}
