
<div class="wptk-todo-container">
	<header class="wptk-todo-header">
		<h1><?php _e('My Todo List', 'wptk-todo'); ?></h1>
		<button id="add-todo-btn" class="btn btn-primary">
			<?php _e('Add New Todo', 'wptk-todo'); ?>
		</button>
	</header>

	<div class="wptk-todo-filters">
		<select id="priority-filter">
			<option value=""><?php _e('All Priorities', 'wptk-todo'); ?></option>
			<option value="low"><?php _e('Low', 'wptk-todo'); ?></option>
			<option value="medium"><?php _e('Medium', 'wptk-todo'); ?></option>
			<option value="high"><?php _e('High', 'wptk-todo'); ?></option>
			<option value="urgent"><?php _e('Urgent', 'wptk-todo'); ?></option>
		</select>

		<select id="status-filter">
			<option value=""><?php _e('All Status', 'wptk-todo'); ?></option>
			<option value="pending"><?php _e('Pending', 'wptk-todo'); ?></option>
			<option value="in_progress"><?php _e('In Progress', 'wptk-todo'); ?></option>
			<option value="completed"><?php _e('Completed', 'wptk-todo'); ?></option>
		</select>

		<button id="refresh-todos" class="btn btn-secondary">
			<?php _e('Refresh', 'wptk-todo'); ?>
		</button>
	</div>

	<div id="todo-loading" class="loading" style="display: none;">
		<?php _e('Loading...', 'wptk-todo'); ?>
	</div>

	<div id="todo-list" class="wptk-todo-list">
		<!-- Todos will be loaded here via AJAX -->
	</div>

	<!-- Add Todo Modal -->
	<div id="add-todo-modal" class="modal" style="display: none;">
		<div class="modal-content">
			<span class="close">&times;</span>
			<h2><?php _e('Add New Todo', 'wptk-todo'); ?></h2>
			<form id="add-todo-form">
				<div class="form-group">
					<label for="todo-title"><?php _e('Title', 'wptk-todo'); ?></label>
					<input type="text" id="todo-title" name="title" required>
				</div>

				<div class="form-group">
					<label for="todo-description"><?php _e('Description', 'wptk-todo'); ?></label>
					<textarea id="todo-description" name="description" rows="3"></textarea>
				</div>

				<div class="form-group">
					<label for="todo-priority"><?php _e('Priority', 'wptk-todo'); ?></label>
					<select id="todo-priority" name="priority">
						<option value="low"><?php _e('Low', 'wptk-todo'); ?></option>
						<option value="medium" selected><?php _e('Medium', 'wptk-todo'); ?></option>
						<option value="high"><?php _e('High', 'wptk-todo'); ?></option>
						<option value="urgent"><?php _e('Urgent', 'wptk-todo'); ?></option>
					</select>
				</div>

				<div class="form-group">
					<label for="todo-due-date"><?php _e('Due Date', 'wptk-todo'); ?></label>
					<input type="date" id="todo-due-date" name="due_date">
				</div>

				<div class="form-actions">
					<button type="submit" class="btn btn-primary"><?php _e('Add Todo', 'wptk-todo'); ?></button>
					<button type="button" class="btn btn-secondary" id="cancel-add-todo"><?php _e('Cancel', 'wptk-todo'); ?></button>
				</div>
			</form>
		</div>
	</div>
</div>
