<?php $view->layout('layouts/admin', ['heading' => 'Settings']); ?>
<?php $view->start('sidebar'); ?><aside><?php $e->html($tip); ?></aside><?php $view->stop(); ?>
<p>Body for <?php $e->html($name); ?></p>
<?php $view->insert('admin/greeting', ['name' => $name]); ?>
