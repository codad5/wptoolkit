<?php

/**
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

namespace Codad5\WPToolkit\Adapters\Repository;

use Codad5\WPToolkit\Data\Attributes\OptionStorage;
use Codad5\WPToolkit\Data\Entity;
use Codad5\WPToolkit\Data\Field\FieldTypes;
use Codad5\WPToolkit\Exceptions\InvalidConfigException;
use Codad5\WPToolkit\Foundation\Identity;

/**
 * Keeps every entity of a class in one option, `{slug}_{key}` (#[OptionStorage('key')]).
 * Whole-collection reads and writes: meant for tens of records, not thousands.
 *
 * @template T of Entity
 * @extends StateRepository<T>
 */
final class OptionsRepository extends StateRepository
{
    private readonly string $option;

    private readonly bool $autoload;

    /**
     * @param class-string<T> $entityClass
     */
    public function __construct(string $entityClass, FieldTypes $types, Identity $identity)
    {
        parent::__construct($entityClass, $types);

        $storage = $this->definition->storage;
        if (!$storage instanceof OptionStorage) {
            throw new InvalidConfigException(sprintf('%s needs #[OptionStorage] to use OptionsRepository.', $entityClass));
        }
        $this->option = $identity->optionKey($storage->key);
        $this->autoload = $storage->autoload;
    }

    protected function load(): array
    {
        $state = get_option($this->option, null);
        if (!is_array($state) || !isset($state['next'], $state['items']) || !is_int($state['next']) || !is_array($state['items'])) {
            return ['next' => 1, 'items' => []];
        }

        /** @var array{next: int, items: array<int, array{fields: array<string, mixed>, terms: array<string, list<int|string>>}>} $state */
        return $state;
    }

    protected function persist(array $state): void
    {
        update_option($this->option, $state, $this->autoload);
    }
}
