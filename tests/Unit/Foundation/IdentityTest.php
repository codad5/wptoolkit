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
        self::assertSame('my-plugin', $id->jsKey());
        self::assertSame('window.wptoolkit["my-plugin"]', $id->jsAccessor());
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
        self::assertSame('silverbird_rating', $id->metaKey('box', 'book', 'rating', 'Silverbird_'), '0.x sanitize_key()d custom prefixes');
        self::assertSame('rating', $id->metaKey('box', 'book', 'rating', ''), 'an empty prefix is the bare field name, as in 0.x');
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
        self::assertNotSame($a->jsAccessor(), $b->jsAccessor());
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

    public function test_js_accessor_is_valid_javascript_for_any_slug(): void
    {
        // Bracket access works for slugs that aren't JS identifiers (digits first, hyphens).
        self::assertSame('window.wptoolkit["3d-viewer"]', (new Identity('3d-viewer'))->jsAccessor());
        self::assertSame('wptoolkit', Identity::JS_NAMESPACE);
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
