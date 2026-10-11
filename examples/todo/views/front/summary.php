<?php
/**
 * Output of [wptk_todo_summary]. Themes can replace it with {theme}/wptk-todo/front/summary.php.
 *
 * @var list<\WptkTodo\Todo> $todos
 */
?>
<?php if ($todos === []) : ?>
    <p class="wptk-todo-summary wptk-todo-summary--empty"><?php $e->html(__('Nothing to do.', 'wptk-todo')); ?></p>
<?php else : ?>
    <ul class="wptk-todo-summary">
        <?php foreach ($todos as $todo) : ?>
            <li><?php $e->html($todo->get('title')); ?></li>
        <?php endforeach; ?>
    </ul>
<?php endif; ?>
