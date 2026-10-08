<?php

declare(strict_types=1);

namespace Codad5\WPToolkit\Tests\Unit\Adapters\Repository;

use Brain\Monkey\Functions;
use Codad5\WPToolkit\Adapters\Repository\OptionsRepository;
use Codad5\WPToolkit\Contracts\Data\Repository;
use Codad5\WPToolkit\Data\Entity;
use Codad5\WPToolkit\Data\Field\FieldTypes;
use Codad5\WPToolkit\Exceptions\InvalidConfigException;
use Codad5\WPToolkit\Foundation\Identity;
use Codad5\WPToolkit\Tests\Contract\RepositoryContract;
use Codad5\WPToolkit\Tests\Support\Entities\MemoryBook;
use Codad5\WPToolkit\Tests\Support\Entities\OptionBook;
use Codad5\WPToolkit\Tests\TestCase;

final class OptionsRepositoryTest extends TestCase
{
    use RepositoryContract;

    /** @var array<string, array{mixed, bool|null}> */
    private array $options = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->options = [];
        Functions\when('get_option')->alias(fn (string $name, $default = false) => $this->options[$name][0] ?? $default);
        Functions\when('update_option')->alias(function (string $name, $value, $autoload = null) {
            $this->options[$name] = [$value, $autoload];
            return true;
        });
        Functions\stubs(['sanitize_email' => static fn ($v) => (string) $v, 'esc_url_raw' => static fn ($v) => (string) $v]);
    }

    protected function repository(): Repository
    {
        return new OptionsRepository(OptionBook::class, new FieldTypes(), new Identity('my-plugin'));
    }

    protected function newBook(array $values = []): Entity
    {
        return new OptionBook($values);
    }

    public function test_everything_lives_in_one_prefixed_option_that_is_not_autoloaded(): void
    {
        $this->repository()->save($this->newBook(['title' => 'Dune']));

        self::assertSame(['my-plugin_contract_books'], array_keys($this->options));
        self::assertFalse($this->options['my-plugin_contract_books'][1]);
        self::assertSame('Dune', $this->options['my-plugin_contract_books'][0]['items'][1]['fields']['title']);
    }

    public function test_a_corrupt_option_reads_as_empty(): void
    {
        $this->options['my-plugin_contract_books'] = ['garbage', null];

        self::assertSame(0, $this->repository()->count());
    }

    public function test_entities_without_option_storage_are_refused(): void
    {
        $this->expectException(InvalidConfigException::class);

        new OptionsRepository(MemoryBook::class, new FieldTypes(), new Identity('my-plugin'));
    }
}
