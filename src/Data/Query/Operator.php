<?php

/**
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

namespace Codad5\WPToolkit\Data\Query;

use Codad5\WPToolkit\Exceptions\InvalidConfigException;

/**
 * Comparison operators every repository adapter supports with the same meaning.
 * `LIKE` means "contains", case-insensitively; the adapter adds the wildcards.
 */
enum Operator: string
{
    case Equals = '=';
    case NotEquals = '!=';
    case GreaterThan = '>';
    case GreaterOrEqual = '>=';
    case LessThan = '<';
    case LessOrEqual = '<=';
    case In = 'in';
    case NotIn = 'not in';
    case Like = 'like';

    public static function parse(string $operator): self
    {
        return self::tryFrom(strtolower(trim($operator)))
            ?? throw new InvalidConfigException(sprintf('Unknown operator "%s". Use one of: %s.', $operator, implode(', ', array_map(
                static fn (self $o): string => $o->value,
                self::cases()
            ))));
    }

    public function takesList(): bool
    {
        return $this === self::In || $this === self::NotIn;
    }
}
