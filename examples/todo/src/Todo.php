<?php

declare(strict_types=1);

namespace WptkTodo;

use Codad5\WPToolkit\Data\Attributes\PostType;
use Codad5\WPToolkit\Data\Entity;
use Codad5\WPToolkit\Data\Field\FieldFactory;

/**
 * A todo item, stored as the `wptk_todo` post type. The title and notes are the post's title and
 * content; everything else is post meta under the `todo_details` box — the same keys the 0.x sample
 * plugin used, so its data reads unchanged.
 */
#[PostType(
    'wptk_todo',
    singular: 'Todo',
    plural: 'Todos',
    box: 'todo_details',
    columns: ['title' => 'post_title', 'notes' => 'post_content'],
    args: ['menu_icon' => 'dashicons-yes-alt'],
)]
final class Todo extends Entity
{
    public static function fields(FieldFactory $f): array
    {
        return [
            $f->text('title')->label(__('Title', 'wptk-todo'))->required()->rules('max:200'),
            $f->textarea('notes')->label(__('Notes', 'wptk-todo')),
            $f->select('priority', [
                'low' => __('Low', 'wptk-todo'),
                'medium' => __('Medium', 'wptk-todo'),
                'high' => __('High', 'wptk-todo'),
                'urgent' => __('Urgent', 'wptk-todo'),
            ])->label(__('Priority', 'wptk-todo'))->default('medium')->required(),
            $f->select('status', [
                'pending' => __('Pending', 'wptk-todo'),
                'in_progress' => __('In progress', 'wptk-todo'),
                'completed' => __('Completed', 'wptk-todo'),
            ])->label(__('Status', 'wptk-todo'))->default('pending')->required()->quickEdit(),
            $f->date('due_date')->label(__('Due date', 'wptk-todo'))->quickEdit(),
            $f->number('estimated_hours')->label(__('Estimated hours', 'wptk-todo'))->rules('min:0')->attributes(['step' => '0.5']),
        ];
    }
}
