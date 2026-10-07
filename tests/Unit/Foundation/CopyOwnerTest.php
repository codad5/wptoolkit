<?php

declare(strict_types=1);

namespace Codad5\WPToolkit\Tests\Unit\Foundation;

use Brain\Monkey\Functions;
use Codad5\WPToolkit\Foundation\CopyOwner;
use Codad5\WPToolkit\Tests\TestCase;

final class CopyOwnerTest extends TestCase
{
    public function test_names_the_plugin_a_copy_was_bundled_in(): void
    {
        Functions\expect('get_plugins')->once()->with('/gamma-forms')->andReturn([
            'gamma-forms.php' => ['Name' => 'Gamma Forms'],
        ]);

        self::assertSame(
            ['type' => 'plugin', 'name' => 'Gamma Forms', 'directory' => 'gamma-forms'],
            CopyOwner::of('/srv/wordpress/wp-content/plugins/gamma-forms/vendor/codad5/wptoolkit')
        );
    }

    public function test_falls_back_to_the_directory_name_when_headers_are_unavailable(): void
    {
        Functions\when('get_plugins')->justReturn([]);

        self::assertSame('gamma-forms', CopyOwner::of('/srv/wordpress/wp-content/plugins/gamma-forms/lib/wptoolkit')['name'] ?? null);
    }

    public function test_recognises_must_use_plugins(): void
    {
        self::assertSame('mu-plugin', CopyOwner::of('/srv/wordpress/wp-content/mu-plugins/site-core/wptoolkit')['type'] ?? null);
    }

    public function test_names_the_theme_a_copy_was_bundled_in(): void
    {
        $theme = new class {
            public function get(string $header): string
            {
                return $header === 'Name' ? 'SilverBird' : '';
            }
        };
        Functions\expect('wp_get_theme')->once()->with('silverbird-fusionintel')->andReturn($theme);

        self::assertSame(
            ['type' => 'theme', 'name' => 'SilverBird', 'directory' => 'silverbird-fusionintel'],
            CopyOwner::of('/srv/wordpress/wp-content/themes/silverbird-fusionintel/wptoolkit')
        );
    }

    public function test_windows_paths_work(): void
    {
        Functions\when('get_plugins')->justReturn([]);

        self::assertSame('gamma', CopyOwner::of('\\srv\\wordpress\\wp-content\\plugins\\gamma\\vendor\\wptoolkit')['directory'] ?? null);
    }

    public function test_a_path_outside_plugins_and_themes_has_no_owner(): void
    {
        self::assertNull(CopyOwner::of('/home/dev/wptoolkit'));
    }
}
