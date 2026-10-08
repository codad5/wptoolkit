<?php

declare(strict_types=1);

namespace Codad5\WPToolkit\Tests\Unit\Adapters\Repository;

use Codad5\WPToolkit\Adapters\Repository\ArrayRepository;
use Codad5\WPToolkit\Contracts\Data\Repository;
use Codad5\WPToolkit\Data\Entity;
use Codad5\WPToolkit\Tests\Contract\RepositoryContract;
use Codad5\WPToolkit\Tests\Support\Entities\MemoryBook;
use Codad5\WPToolkit\Tests\TestCase;

final class ArrayRepositoryTest extends TestCase
{
    use RepositoryContract;

    protected function repository(): Repository
    {
        return new ArrayRepository(MemoryBook::class);
    }

    protected function newBook(array $values = []): Entity
    {
        return new MemoryBook($values);
    }

    protected function supportsTerms(): bool
    {
        return true;
    }
}
