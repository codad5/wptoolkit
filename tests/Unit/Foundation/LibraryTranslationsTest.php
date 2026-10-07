<?php

declare(strict_types=1);

namespace Codad5\WPToolkit\Tests\Unit\Foundation;

use Brain\Monkey\Filters;
use Brain\Monkey\Functions;
use Codad5\WPToolkit\Foundation\Identity;
use Codad5\WPToolkit\Foundation\LibraryTranslations;
use Codad5\WPToolkit\Tests\TestCase;

final class LibraryTranslationsTest extends TestCase
{
    private string $dir;

    protected function setUp(): void
    {
        parent::setUp();
        $this->dir = sys_get_temp_dir() . '/wptoolkit-i18n-' . uniqid();
        mkdir($this->dir);
        Functions\when('determine_locale')->justReturn('fr_FR');
        Functions\when('is_textdomain_loaded')->justReturn(false);
    }

    protected function tearDown(): void
    {
        array_map('unlink', glob($this->dir . '/*') ?: []);
        rmdir($this->dir);
        parent::tearDown();
    }

    public function test_loads_the_mo_file_for_the_current_locale(): void
    {
        touch($this->dir . '/wptoolkit-fr_FR.mo');
        Functions\expect('load_textdomain')
            ->once()
            ->with('wptoolkit', $this->dir . '/wptoolkit-fr_FR.mo', 'fr_FR')
            ->andReturn(true);

        self::assertTrue($this->translations()->load());
    }

    public function test_does_nothing_when_there_is_no_file_for_the_locale(): void
    {
        Functions\expect('load_textdomain')->never();

        self::assertFalse($this->translations()->load());
    }

    public function test_does_not_load_twice_when_another_copy_already_did(): void
    {
        touch($this->dir . '/wptoolkit-fr_FR.mo');
        Functions\when('is_textdomain_loaded')->justReturn(true);
        Functions\expect('load_textdomain')->never();

        self::assertFalse($this->translations()->load());
    }

    public function test_consumer_can_point_at_its_own_translation_through_a_prefixed_filter(): void
    {
        $custom = $this->dir . '/custom-fr.mo';
        touch($custom);
        Filters\expectApplied('my-plugin/i18n/library_mofile')->once()->andReturn($custom);
        Functions\expect('load_textdomain')->once()->with('wptoolkit', $custom, 'fr_FR')->andReturn(true);

        self::assertTrue($this->translations()->load());
    }

    public function test_bundled_directory_is_the_package_languages_folder(): void
    {
        self::assertSame(
            realpath(dirname(__DIR__, 3) . '/languages'),
            realpath(LibraryTranslations::bundledDirectory())
        );
    }

    private function translations(): LibraryTranslations
    {
        return new LibraryTranslations(new Identity('my-plugin'), $this->dir);
    }
}
