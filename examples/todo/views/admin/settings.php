<?php
/**
 * Settings screen. $form is the SettingsForm; its render() prints the Settings API form.
 *
 * @var \Codad5\WPToolkit\Admin\Settings\SettingsForm $form
 * @var string $page
 */
?>
<div class="wrap">
    <h1><?php $e->html(__('Todo settings', 'wptk-todo')); ?></h1>
    <?php settings_errors(); ?>
    <?php $form->render($page); ?>
</div>
