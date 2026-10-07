<?php
/**
 * The front-end board. Themes can replace it with {theme}/wptk-todo/front/board.php.
 *
 * @var list<\WptkTodo\Todo> $todos
 */
?>
<section class="wptk-todo-board" aria-labelledby="wptk-todo-board-title">
    <h1 id="wptk-todo-board-title"><?php $e->html(__('Todo board', 'wptk-todo')); ?></h1>
    <?php if ($todos === []) : ?>
        <p><?php $e->html(__('Nothing to do.', 'wptk-todo')); ?></p>
    <?php else : ?>
        <ul class="wptk-todo-board__list">
            <?php foreach ($todos as $todo) : ?>
                <li class="wptk-todo-board__item wptk-todo-board__item--<?php $e->attr($todo->get('priority')); ?>">
                    <span class="wptk-todo-board__title"><?php $e->html($todo->get('title')); ?></span>
                    <span class="wptk-todo-board__due"><?php $e->html($todo->get('due_date') ?? ''); ?></span>
                </li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>
</section>
