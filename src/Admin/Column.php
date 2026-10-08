<?php

/**
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

namespace Codad5\WPToolkit\Admin;

use Closure;
use Codad5\WPToolkit\Exceptions\InvalidConfigException;

/**
 * One list-table column for a meta box field (immutable). Extracted from 0.x `Model::get_admin_columns()`:
 *
 *     Column::field('start_date')->label(__('Starts', 'my-plugin'))->sortable()->position(Column::AFTER_TITLE)->width('120px')
 *     Column::field('price')->format(Column::CURRENCY, symbol: '₦')
 *     Column::field('venue')->format(fn (mixed $value, int $postId): string => esc_html(strtoupper((string) $value)))
 */
final class Column
{
    public const AFTER_TITLE = 'after_title';
    public const AFTER_DATE = 'after_date';
    public const END = 'end';

    public const TEXT = 'text';
    public const DATE = 'date';
    public const NUMBER = 'number';
    public const CURRENCY = 'currency';

    /**
     * @param bool|Closure(\WP_Query, string): void $sortable
     * @param string|Closure(mixed, int): string $format
     */
    private function __construct(
        public readonly string $field,
        public readonly ?string $label = null,
        public readonly bool|Closure $sortable = false,
        public readonly string $position = self::END,
        public readonly string|Closure $format = 'auto',
        public readonly string $symbol = '$',
        public readonly ?string $width = null
    ) {
    }

    public static function field(string $field): self
    {
        return new self($field);
    }

    public function label(string $label): self
    {
        return $this->with(['label' => $label]);
    }

    /**
     * Sort by the stored value, or with your own callback that adjusts the list-table query.
     *
     * @param bool|Closure(\WP_Query, string): void $sortable
     */
    public function sortable(bool|Closure $sortable = true): self
    {
        return $this->with(['sortable' => $sortable]);
    }

    public function position(string $position): self
    {
        if (!in_array($position, [self::AFTER_TITLE, self::AFTER_DATE, self::END], true)) {
            throw new InvalidConfigException(sprintf('Column position must be after_title, after_date or end; got "%s".', $position));
        }

        return $this->with(['position' => $position]);
    }

    /**
     * How a value is shown: TEXT, DATE, NUMBER, CURRENCY, or a callback returning **escaped** HTML.
     * The default picks from the field type.
     *
     * @param string|Closure(mixed, int): string $format
     */
    public function format(string|Closure $format, string $symbol = '$'): self
    {
        if (is_string($format) && !in_array($format, [self::TEXT, self::DATE, self::NUMBER, self::CURRENCY], true)) {
            throw new InvalidConfigException(sprintf('Unknown column format "%s".', $format));
        }

        return $this->with(['format' => $format, 'symbol' => $symbol]);
    }

    /**
     * A CSS width such as `120px` or `10%`.
     */
    public function width(string $width): self
    {
        if (preg_match('/^\d+(\.\d+)?(px|%|em|rem|ch)$/', $width) !== 1) {
            throw new InvalidConfigException(sprintf('Column width "%s" must be a number with px, %%, em, rem or ch.', $width));
        }

        return $this->with(['width' => $width]);
    }

    /**
     * @param array<string, mixed> $changes
     */
    private function with(array $changes): self
    {
        $values = [
            'field' => $this->field,
            'label' => $this->label,
            'sortable' => $this->sortable,
            'position' => $this->position,
            'format' => $this->format,
            'symbol' => $this->symbol,
            'width' => $this->width,
        ];

        return new self(...array_merge($values, $changes));
    }
}
