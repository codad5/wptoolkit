<?php

/**
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

namespace Codad5\WPToolkit\Admin;

use Closure;
use Codad5\WPToolkit\Data\MetaBox;
use Codad5\WPToolkit\Foundation\HookRegistrar;
use WP_Query;

/**
 * List-table columns for a meta box's fields (extracted from 0.x `Model`). Each column's id is the
 * field's meta key, which is also what the meta box's quick edit answers to — so a `->quickEdit()`
 * field with a column is quick-editable with no further wiring.
 *
 *     (new Columns($eventDetails, [
 *         Column::field('start_date')->sortable()->position(Column::AFTER_TITLE),
 *         Column::field('ticket_price')->format(Column::CURRENCY, symbol: '₦'),
 *     ]))->register($hooks);
 */
final class Columns
{
    /** @var array<string, Column> meta key => column */
    private array $columns = [];

    /**
     * @param list<Column> $columns
     */
    public function __construct(private readonly MetaBox $box, array $columns)
    {
        foreach ($columns as $column) {
            $this->columns[$box->metaKey($column->field)] = $column; // throws for unknown fields
        }
    }

    public function register(HookRegistrar $hooks): void
    {
        $type = $this->box->postType;
        $hooks->addFilter("manage_{$type}_posts_columns", [$this, 'addColumns']);
        $hooks->addAction("manage_{$type}_posts_custom_column", [$this, 'renderCell'], 10, 2);
        $hooks->addFilter("manage_edit-{$type}_sortable_columns", [$this, 'sortableColumns']);
        $hooks->addAction('pre_get_posts', [$this, 'applySort']);
        $hooks->addAction('admin_head-edit.php', [$this, 'printWidths']);
    }

    /**
     * @internal manage_{post_type}_posts_columns
     * @param array<string, string> $columns
     * @return array<string, string>
     */
    public function addColumns(array $columns): array
    {
        $result = [];
        $placed = false;
        foreach ($columns as $id => $label) {
            $result[$id] = $label;
            if ($id === 'title') {
                $result += $this->labels(Column::AFTER_TITLE);
            }
            if ($id === 'date') {
                $result += $this->labels(Column::AFTER_DATE);
                $placed = true;
            }
        }
        if (!$placed) {
            $result += $this->labels(Column::AFTER_DATE);
        }

        return $result + $this->labels(Column::END) + $this->labels(Column::AFTER_TITLE);
    }

    /**
     * @internal manage_{post_type}_posts_custom_column
     */
    public function renderCell(string $columnId, int $postId): void
    {
        $column = $this->columns[$columnId] ?? null;
        if ($column === null) {
            return;
        }

        $value = $this->box->value($postId, $column->field);
        // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- format() escapes; custom formatters must return escaped HTML.
        echo $this->format($column, $value, $postId);
    }

    /**
     * @internal manage_edit-{post_type}_sortable_columns
     * @param array<string, string> $columns
     * @return array<string, string>
     */
    public function sortableColumns(array $columns): array
    {
        foreach ($this->columns as $id => $column) {
            if ($column->sortable !== false) {
                $columns[$id] = $id;
            }
        }

        return $columns;
    }

    /**
     * @internal pre_get_posts: only the main list-table query for this post type, only for our columns.
     */
    public function applySort(WP_Query $query): void
    {
        if (!is_admin() || !$query->is_main_query() || $query->get('post_type') !== $this->box->postType) {
            return;
        }

        $orderby = $query->get('orderby');
        $column = is_string($orderby) ? ($this->columns[$orderby] ?? null) : null;
        if ($column === null || $column->sortable === false) {
            return;
        }

        if ($column->sortable instanceof Closure) {
            ($column->sortable)($query, $orderby);
            return;
        }

        $numeric = in_array($this->box->fields()[$column->field]->type, ['number', 'media', 'wp_media'], true);
        $query->set('meta_key', $orderby);
        $query->set('orderby', $numeric ? 'meta_value_num' : 'meta_value');
    }

    /**
     * @internal admin_head-edit.php
     */
    public function printWidths(): void
    {
        $screen = function_exists('get_current_screen') ? get_current_screen() : null;
        if ($screen === null || $screen->post_type !== $this->box->postType) {
            return;
        }

        $css = '';
        foreach ($this->columns as $id => $column) {
            if ($column->width !== null) {
                $css .= sprintf('.wp-list-table .column-%s{width:%s}', sanitize_html_class($id), $column->width);
            }
        }
        if ($css !== '') {
            // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- class names sanitized, widths validated by Column::width().
            echo '<style>' . $css . '</style>';
        }
    }

    /**
     * @return array<string, string>
     */
    private function labels(string $position): array
    {
        $labels = [];
        foreach ($this->columns as $id => $column) {
            if ($column->position === $position) {
                $labels[$id] = $column->label ?? $this->box->fields()[$column->field]->labelText();
            }
        }

        return $labels;
    }

    private function format(Column $column, mixed $value, int $postId): string
    {
        if ($column->format instanceof Closure) {
            return ($column->format)($value, $postId);
        }

        $field = $this->box->fields()[$column->field];
        if (is_array($value)) {
            return $field->type === 'media' || $field->type === 'wp_media'
                ? implode(' ', array_map(fn ($id) => $this->thumbnail($id), $value))
                : esc_html(implode(', ', array_map(static fn ($v): string => is_scalar($v) ? (string) $v : '', $value)));
        }

        $format = $column->format === 'auto'
            ? match ($field->type) {
                'date' => Column::DATE,
                'number' => Column::NUMBER,
                default => Column::TEXT,
            }
            : $column->format;

        return match (true) {
            $value === null || $value === '' => '<span aria-hidden="true">—</span>',
            is_bool($value) => $value ? esc_html__('Yes', 'wptoolkit') : esc_html__('No', 'wptoolkit'),
            $field->type === 'media' || $field->type === 'wp_media' => $this->thumbnail($value),
            $field->choices() !== [] && is_scalar($value) => esc_html($field->choices()[(string) $value] ?? (string) $value),
            $format === Column::DATE => esc_html($this->date($value)),
            $format === Column::NUMBER && is_numeric($value) => esc_html(number_format_i18n((float) $value)),
            $format === Column::CURRENCY && is_numeric($value) => esc_html($column->symbol . number_format_i18n((float) $value, 2)),
            default => esc_html(is_scalar($value) ? (string) $value : ''),
        };
    }

    private function date(mixed $value): string
    {
        $time = is_scalar($value) ? strtotime((string) $value) : false;
        if ($time === false) {
            return is_scalar($value) ? (string) $value : '';
        }
        $format = get_option('date_format');

        return date_i18n(is_string($format) && $format !== '' ? $format : 'Y-m-d', $time);
    }

    private function thumbnail(mixed $id): string
    {
        return is_numeric($id) ? wp_get_attachment_image((int) $id, [40, 40]) : '';
    }

    /**
     * @return array<string, Column>
     */
    public function columns(): array
    {
        return $this->columns;
    }
}
