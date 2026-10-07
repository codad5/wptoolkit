<?php

declare(strict_types=1);

namespace Codad5\WPToolkit\Tests\Unit\Foundation;

use Codad5\WPToolkit\Exceptions\InvalidConfigException;
use Codad5\WPToolkit\Foundation\Identity;
use PHPUnit\Framework\TestCase;

final class IdentityTest extends TestCase
{
    public function test_names_follow_adr_0006(): void
    {
        $id = new Identity('my-plugin');

        self::assertSame('my-plugin/http/before_dispatch', $id->hook('http/before_dispatch'));
        self::assertSame('my_plugin_books_search', $id->ajaxAction('books_search'));
        self::assertSame('my-plugin/v1', $id->restNamespace());
        self::assertSame('my-plugin/v2', $id->restNamespace('v2'));
        self::assertSame('my_plugin_books', $id->table('books'));
        self::assertSame('my_plugin_cleanup', $id->cronHook('cleanup'));
        self::assertSame('my-plugin-toolkit-api', $id->handle('toolkit-api'));
        self::assertSame('myPluginToolkit', $id->jsGlobal());
        self::assertSame('my-plugin:books.search', $id->nonceAction('books.search'));
    }

    /**
     * ADR-0016: these are the exact keys 0.x wrote, taken from the real consumers.
     */
    public function test_option_and_meta_keys_match_what_0x_stored(): void
    {
        $pau = new Identity('pau-alumni-manager');
        self::assertSame('pau-alumni-manager_api_base_url', $pau->optionKey('api_base_url'));
        self::assertSame('executive_role_pau-executive_title', $pau->metaKey('executive_role', 'pau-executive', 'title'));

        $silverbird = new Identity('silverbird-theme');
        self::assertSame('silverbird-theme_reach_api_key', $silverbird->optionKey('reach_api_key'));
        self::assertSame(
            '_silverbird_movies_availability',
            $silverbird->metaKey('movie_details', 'silverbird_movies', 'availability', '_silverbird_movies_')
        );
    }

    public function test_meta_key_is_not_prefixed_twice(): void
    {
        $id = new Identity('x');

        self::assertSame('box_book_isbn', $id->metaKey('box', 'book', 'box_book_isbn'));
    }

    public function test_option_key_is_sanitized_like_wordpress_sanitize_key(): void
    {
        // sanitize_key() lowercases and deletes (not replaces) other characters, as 0.x stored them.
        self::assertSame('my-plugin_apikey', (new Identity('my-plugin'))->optionKey('API Key!'));
        self::assertSame('my-plugin_api_key', (new Identity('my-plugin'))->optionKey('API_Key'));
    }

    public function test_two_identities_never_share_a_name(): void
    {
        $a = new Identity('alpha');
        $b = new Identity('beta');

        foreach (['hook', 'ajaxAction', 'table', 'cronHook', 'handle', 'nonceAction', 'optionKey', 'transientKey'] as $method) {
            self::assertNotSame($a->$method('same'), $b->$method('same'), $method);
        }
        self::assertNotSame($a->restNamespace(), $b->restNamespace());
        self::assertNotSame($a->jsGlobal(), $b->jsGlobal());
    }

    public function test_long_transient_keys_are_hashed_to_fit(): void
    {
        $id = new Identity('my-plugin');
        $key = str_repeat('k', 300);

        self::assertLessThanOrEqual(172, strlen($id->transientKey($key)));
        self::assertLessThanOrEqual(167, strlen($id->transientKey($key, site: true)));
        self::assertSame($id->transientKey($key), $id->transientKey($key), 'stable');
        self::assertNotSame($id->transientKey($key), $id->transientKey($key . 'x'));
    }

    public function test_option_key_longer_than_the_column_is_rejected(): void
    {
        $this->expectException(InvalidConfigException::class);

        (new Identity('my-plugin'))->optionKey(str_repeat('a', 200));
    }

    public function test_js_global_is_a_valid_identifier_even_for_numeric_slugs(): void
    {
        self::assertSame('_3dViewerToolkit', (new Identity('3d-viewer'))->jsGlobal());
        self::assertSame('aBCToolkit', (new Identity('a_b-c'))->jsGlobal());
    }

    public function test_unusable_names_are_rejected(): void
    {
        $this->expectException(InvalidConfigException::class);

        (new Identity('my-plugin'))->ajaxAction('!!!');
    }

    public function test_invalid_slug_is_rejected(): void
    {
        $this->expectException(InvalidConfigException::class);

        new Identity('My Plugin');
    }
}
