<?php

declare(strict_types=1);

namespace Codad5\WPToolkit\Tests\Integration;

use Codad5\WPToolkit\Admin\Settings\Settings;
use Codad5\WPToolkit\Data\Field\FieldFactory;
use Codad5\WPToolkit\Data\Field\FieldTypes;
use Codad5\WPToolkit\Foundation\Identity;
use PHPUnit\Framework\TestCase;

/**
 * ADR-0023 against the real options table: rows a plugin saved under its own prefix are read and
 * written where they are, and nothing appears under the slug.
 */
final class SettingsOptionPrefixOnWordPressTest extends TestCase
{
    protected function tearDown(): void
    {
        foreach (['legacyfx_per_page', 'legacyfx_api_key', 'wptk-px_per_page', 'wptk-px_api_key'] as $option) {
            delete_option($option);
        }
    }

    public function test_legacy_rows_are_read_and_written_in_place(): void
    {
        add_option('legacyfx_per_page', '25');
        add_option('legacyfx_api_key', 'saved-before-the-upgrade');
        $f = new FieldFactory();

        $settings = new Settings(
            [$f->number('per_page')->default(10), $f->password('api_key')->sensitive()],
            new FieldTypes(),
            new Identity('wptk-px'),
            optionPrefix: 'legacyfx_'
        );

        self::assertSame(25, $settings->get('per_page'));
        self::assertSame('saved-before-the-upgrade', $settings->get('api_key'));

        self::assertSame([], $settings->set('per_page', 40));
        self::assertEquals(40, get_option('legacyfx_per_page'));
        self::assertFalse(get_option('wptk-px_per_page'), 'nothing is written under the slug');
    }
}
