<?php

declare(strict_types=1);

namespace Codad5\WPToolkit\Tests\Support\Entities;

use Codad5\WPToolkit\Data\Attributes\OptionStorage;
use Codad5\WPToolkit\Data\Entity;

#[OptionStorage('contract_books')]
final class OptionBook extends Entity
{
    use BookFields;
}
