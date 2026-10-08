<?php

declare(strict_types=1);

namespace Codad5\WPToolkit\Tests\Support\Entities;

use Codad5\WPToolkit\Data\Attributes\Table;
use Codad5\WPToolkit\Data\Entity;

#[Table('books')]
final class TableBook extends Entity
{
    use BookFields;
}
