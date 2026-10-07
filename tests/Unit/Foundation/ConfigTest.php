<?php

declare(strict_types=1);

namespace Codad5\WPToolkit\Tests\Unit\Foundation;

use Codad5\WPToolkit\Exceptions\InvalidConfigException;
use Codad5\WPToolkit\Foundation\Config;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class ConfigTest extends TestCase
{
    public function test_reads_top_level_and_dotted_keys(): void
    {
        $config = Config::fromArray('/p/my-plugin.php', [
            'slug' => 'my-plugin',
            'cache' => ['driver' => 'object', 'ttl' => 60],
        ]);

        self::assertSame('my-plugin', $config->slug);
        self::assertSame('/p/my-plugin.php', $config->file);
        self::assertSame('object', $config->get('cache.driver'));
        self::assertSame(['driver' => 'object', 'ttl' => 60], $config->get('cache'));
        self::assertSame('fallback', $config->get('cache.missing', 'fallback'));
        self::assertTrue($config->has('cache.ttl'));
        self::assertFalse($config->has('cache.missing'));
    }

    public function test_a_null_value_still_counts_as_present(): void
    {
        $config = Config::fromArray('/p.php', ['slug' => 'x', 'option' => null]);

        self::assertTrue($config->has('option'));
        self::assertNull($config->get('option', 'default'));
    }

    public function test_text_domain_defaults_to_the_slug(): void
    {
        self::assertSame('my-plugin', Config::fromArray('/p.php', ['slug' => 'my-plugin'])->textDomain());
        self::assertSame('custom', Config::fromArray('/p.php', ['slug' => 'my-plugin', 'textdomain' => 'custom'])->textDomain());
    }

    public function test_with_returns_a_new_config_and_keeps_the_slug(): void
    {
        $original = Config::fromArray('/p.php', ['slug' => 'my-plugin', 'debug' => false]);

        $changed = $original->with(['debug' => true, 'slug' => 'hijacked']);

        self::assertFalse($original->get('debug'));
        self::assertTrue($changed->get('debug'));
        self::assertSame('my-plugin', $changed->slug);
    }

    /**
     * @return iterable<string, array{mixed}>
     */
    public static function invalidSlugs(): iterable
    {
        yield 'missing' => [null];
        yield 'empty' => [''];
        yield 'uppercase' => ['MyPlugin'];
        yield 'space' => ['my plugin'];
        yield 'leading hyphen' => ['-plugin'];
        yield 'not a string' => [42];
    }

    #[DataProvider('invalidSlugs')]
    public function test_rejects_invalid_slugs(mixed $slug): void
    {
        $this->expectException(InvalidConfigException::class);

        Config::fromArray('/p.php', ['slug' => $slug]);
    }
}
