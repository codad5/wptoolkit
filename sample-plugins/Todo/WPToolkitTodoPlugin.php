<?php
/*
Plugin Name: WPToolkit Todo List
Plugin URI: https://codad5.me
Description: Simple Todo List plugin for testing WPToolkit framework
Version: 1.0.0
Author: Codad5
Author URI: https://codad5.me
*/


namespace Codad5\SamplePlugins\Todo;
// Prevent direct access
if (!defined('ABSPATH')) {
	exit;
}


use Codad5\SamplePlugins\PluginEngine;
use Codad5\WPToolkit\Utils\{Config, Settings, Page, Notification, Ajax, EnqueueManager, Cache};
use Exception;
use Codad5\WPToolkit\Registry;
use function  wp_create_nonce;


/**
 * Main Todo Plugin Class
 */
class WPToolkitTodoPlugin extends  PluginEngine
{
	private Config $config;
	private TodoModel $todo_model;
	private Page $page;
	private static WPToolkitTodoPlugin $instance;

	/**
	 * Get the singleton instance of the plugin
	 *
	 * @return WPToolkitTodoPlugin
	 */
	public static function getInstance(): static {
		if (!isset(self::$instance)) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * @throws Exception
	 */
	 function run(): static {
		// Create configuration
		$this->config = Config::plugin('wptk-todo', __FILE__, [
			'name' => __('WPToolkit Todo List', 'wptk-todo'),
			'version' => '1.0.0',
			'text_domain' => 'wptk-todo'
		]);

		// Register with service registry
		Registry::registerApp($this->config);

		$this->init_services();
		$this->setup_hooks();
        return $this;
	}

	/**
	 * @throws Exception
	 */
	private function init_services(): void
	{
		// Settings
		$settings = Settings::create([
			'default_priority' => [
				'type' => 'select',
				'label' => __('Default Priority', 'wptk-todo'),
				'choices' => [
					'low' => __('Low', 'wptk-todo'),
					'medium' => __('Medium', 'wptk-todo'),
					'high' => __('High', 'wptk-todo'),
					'urgent' => __('Urgent', 'wptk-todo')
				],
				'default' => 'medium',
				'description' => __('Default priority for new todos', 'wptk-todo')
			],
			'show_stats_dashboard' => [
				'type' => 'checkbox',
				'label' => __('Show Stats on Dashboard', 'wptk-todo'),
				'default' => true,
				'description' => __('Display todo statistics on the WordPress dashboard', 'wptk-todo')
			]
		], $this->config);

		// Update your existing asset manager setup
		$assets = EnqueueManager::create($this->config, __DIR__.'/assets/');

// Keep your existing admin group
		$assets->createScriptGroup('test-admin-pages')
		       ->addScriptToGroup('test-admin-pages', 'my-test-admin-script', 'js/test.js');

// Add new frontend group
		$assets->createScriptGroup('frontend-todos', [
			'condition' => function() {
				return !is_admin() && get_query_var('wptk-todo_page') === 'list';
			}
		])
       ->addScriptToGroup('frontend-todos', 'todo-frontend-js', 'js/frontend-todo.js', ['jquery'])
       ->addStyleToGroup('frontend-todos', 'todo-frontend-css', 'css/frontend-todo.css');

// Add localization for AJAX
		$assets->addLocalization('todo-frontend-js', 'wptkTodoAjax', [
			'ajax_url' => admin_url('admin-ajax.php'),
			'nonces' => [
				'get_todos' => 'sample_todo_ajax_nonce_get_todos',
				'add_todo' => 'wptk_todo_ajax_nonce_add_todo',
				'toggle_status' => 'wptk_todo_ajax_nonce_toggle_status',
				'delete_todo' => 'wptk_todo_ajax_nonce_delete_todo',
			]
		]);
		$this->todo_model = TodoModel::get_instance($this->config)->run();
		$this->page = Page::create($this->config, __DIR__ . '/templates/');

        $this->page->setAssetManager($assets);

		$this->page->addMenuPage('todo', [
			'page_title' => __('Todo List', 'wptk-todo'),
			'menu_title' => __('Todos', 'wptk-todo'),
			'capability' => 'edit_posts',
			'icon' => 'dashicons-yes-alt',
			'position' => 20,
			'callback' => [$this, 'render_dashboard_page']
		]);

		$this->page->addFrontendPage('list', [
			'title' => __('Todo List', 'wptk-todo'),
			'template' => 'frontend/todo-list.php',
			'public' => true,
			'path' => 'todo-list',
			'asset_groups' => ['frontend-todos']
		]);



		// Add settings page
		$this->page->addSubmenuPage('settings', [
			'parent_slug' => 'todo',
			'page_title' => __('Todo Settings', 'wptk-todo'),
			'menu_title' => __('Settings', 'wptk-todo'),
			'capability' => 'manage_options',
			'asset_groups' => ['test-admin-pages'],
			'callback' => [$this, 'render_settings_page']
		]);



		$this->page->addSubmenuPage($this->todo_model, [
			'parent_slug' => 'todo',
			'page_title' => __('Add New Todo', 'wptk-todo'),
			'menu_title' => __('Add New', 'wptk-todo'),
			'capability' => 'manage_options',
			'callback' => false
		]);


		if ($settings->get('show_stats_dashboard', true)) {
			$this->page->addDashboardWidget('wptk_todo_stats', "Todo Statistics", [$this, 'render_dashboard_widget'], );
		}

		// Notification system
		$notification = Notification::create($this->config, 'Todo List');

		// AJAX handler
		$ajax = Ajax::create($this->config);
		$ajax->addAction('toggle_status', [$this, 'ajax_toggle_status'], [
			'capability' => 'edit_posts',
			'validate_nonce' => true
		]);

		$ajax->addAction('add_todo', [$this, 'ajax_add_todo'], [
			'capability' => 'edit_posts',
			'validate_nonce' => true
		]);

		$ajax->addAction('delete_todo', [$this, 'ajax_delete_todo'], [
			'capability' => 'delete_posts',
			'validate_nonce' => true
		]);

		// Todo model

		// Register all services
		Registry::addMany($this->config, [
			'settings' => $settings,
			'page' => $this->page,
			'notification' => $notification,
			'ajax' => $ajax,
			'assets' => $assets,
			'todo_model' => $this->todo_model
		]);
	}

	private function setup_hooks(): void
	{
		add_action('plugins_loaded', [$this, 'load_textdomain']);
		add_action('init', [$this, 'init_models']);
	}

	public function load_textdomain(): void
	{
		load_plugin_textdomain('wptk-todo', false, dirname(plugin_basename(__FILE__)) . '/languages');
	}

	public function init_models(): void
	{
		$this->todo_model->run();
	}



	public function render_settings_page(): void
	{
		$settings = Registry::get('wptk-todo', 'settings');

		if ($_POST && check_admin_referer('wptk_todo_settings')) {
			foreach (['default_priority', 'show_stats_dashboard'] as $key) {
				if (isset($_POST[$key])) {
					$settings->set($key, $_POST[$key]);
				}
			}

			$notification = Registry::get('wptk-todo', 'notification');
			$notification->success(__('Settings saved successfully!', 'wptk-todo'));
		}

		?>
		<div class="wrap">
			<h1><?php _e('Todo Settings', 'wptk-todo'); ?></h1>
			<form method="post">
				<?php wp_nonce_field('wptk_todo_settings'); ?>
				<table class="form-table">
					<tr>
						<th scope="row"><?php _e('Default Priority', 'wptk-todo'); ?></th>
						<td><?php echo $settings->renderField('default_priority'); ?></td>
					</tr>
					<tr>
						<th scope="row"><?php _e('Dashboard Widget', 'wptk-todo'); ?></th>
						<td><?php echo $settings->renderField('show_stats_dashboard'); ?></td>
					</tr>
				</table>
				<?php submit_button(); ?>
			</form>
		</div>
		<?php
	}

	public function render_dashboard_page(): void
	{
		$stats = $this->todo_model->get_stats();
		?>
		<div class="wrap">
			<h1><?php _e('Todo Dashboard', 'wptk-todo'); ?></h1>

			<div class="dashboard-widgets-wrap">
				<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 20px; margin: 20px 0;">

					<!-- Overview Stats -->
					<div class="postbox">
						<h2 class="hndle"><?php _e('Overview', 'wptk-todo'); ?></h2>
						<div class="inside">
							<div style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 15px; text-align: center;">
								<div>
									<div style="font-size: 2em; font-weight: bold; color: #0073aa;"><?php echo $stats['total']; ?></div>
									<div><?php _e('Total Todos', 'wptk-todo'); ?></div>
								</div>
								<div>
									<div style="font-size: 2em; font-weight: bold; color: #00a32a;"><?php echo $stats['completed']; ?></div>
									<div><?php _e('Completed', 'wptk-todo'); ?></div>
								</div>
								<div>
									<div style="font-size: 2em; font-weight: bold; color: #ffb900;"><?php echo $stats['in_progress']; ?></div>
									<div><?php _e('In Progress', 'wptk-todo'); ?></div>
								</div>
								<div>
									<div style="font-size: 2em; font-weight: bold; color: #d63638;"><?php echo $stats['overdue']; ?></div>
									<div><?php _e('Overdue', 'wptk-todo'); ?></div>
								</div>
							</div>
						</div>
					</div>

					<!-- Priority Breakdown -->
					<div class="postbox">
						<h2 class="hndle"><?php _e('Priority Breakdown', 'wptk-todo'); ?></h2>
						<div class="inside">
							<?php foreach ($stats['priority_breakdown'] as $priority => $count): ?>
								<div style="display: flex; justify-content: space-between; margin: 10px 0; padding: 8px; background: #f9f9f9; border-radius: 4px;">
									<span><?php echo ucfirst($priority); ?></span>
									<strong><?php echo $count; ?></strong>
								</div>
							<?php endforeach; ?>
						</div>
					</div>
				</div>
			</div>

			<h2><?php _e('Recent Todos', 'wptk-todo'); ?></h2>
			<?php
			$recent_todos = $this->todo_model->get_posts(['posts_per_page' => 5], true);

			if ($recent_todos): ?>
				<table class="wp-list-table widefat fixed striped">
					<thead>
					<tr>
						<th><?php _e('Title', 'wptk-todo'); ?></th>
						<th><?php _e('Priority', 'wptk-todo'); ?></th>
						<th><?php _e('Status', 'wptk-todo'); ?></th>
						<th><?php _e('Due Date', 'wptk-todo'); ?></th>
					</tr>
					</thead>
					<tbody>
					<?php foreach ($recent_todos as ["post" => $todo, 'meta' => ['todo_details' => $todo_details]]): ?>
						<tr>
							<td>
								<a href="<?php echo get_edit_post_link($todo->ID); ?>">
									<?php echo esc_html($todo->post_title); ?>
								</a>
							</td>
							<td><?php echo ucfirst($todo_details['priority'] ?? 'medium'); ?></td>
							<td><?php echo ucfirst(str_replace('_', ' ', $todo_details['status'] ?? 'pending')); ?></td>
							<td><?php echo $todo_details['due_date'] ?? __('No due date', 'wptk-todo');  ?></td>
						</tr>
					<?php endforeach; ?>
					</tbody>
				</table>
			<?php else: ?>
				<p><?php _e('No todos found. Create your first todo!', 'wptk-todo'); ?></p>
			<?php endif; ?>
		</div>
		<?php
	}

	public function render_dashboard_widget(): void
	{
		$stats = $this->todo_model->get_stats();
		?>
		<div style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 10px; text-align: center;">
			<div>
				<div style="font-size: 1.5em; font-weight: bold; color: #0073aa;"><?php echo $stats['total']; ?></div>
				<div style="font-size: 0.9em;"><?php _e('Total', 'wptk-todo'); ?></div>
			</div>
			<div>
				<div style="font-size: 1.5em; font-weight: bold; color: #00a32a;"><?php echo $stats['completed']; ?></div>
				<div style="font-size: 0.9em;"><?php _e('Completed', 'wptk-todo'); ?></div>
			</div>
			<div>
				<div style="font-size: 1.5em; font-weight: bold; color: #ffb900;"><?php echo $stats['pending']; ?></div>
				<div style="font-size: 0.9em;"><?php _e('Pending', 'wptk-todo'); ?></div>
			</div>
			<div>
				<div style="font-size: 1.5em; font-weight: bold; color: #d63638;"><?php echo $stats['overdue']; ?></div>
				<div style="font-size: 0.9em;"><?php _e('Overdue', 'wptk-todo'); ?></div>
			</div>
		</div>
		<p style="text-align: center; margin-top: 15px;">
			<a href="<?php echo admin_url('edit.php?post_type=wptk_todo'); ?>" class="button">
				<?php _e('Manage Todos', 'wptk-todo'); ?>
			</a>
		</p>
		<?php
	}

	public function ajax_toggle_status(): void
	{
		$ajax = Registry::get('wptk-todo', 'ajax');

		$post_id = (int) ($_POST['post_id'] ?? 0);
		$new_status = sanitize_text_field($_POST['status'] ?? '');

		if (!$post_id || !in_array($new_status, ['pending', 'in_progress', 'completed'])) {
			$ajax->error(__('Invalid parameters', 'wptk-todo'));
		}

		$result = $this->todo_model->update($post_id, [], ['status' => $new_status]);

		if (is_wp_error($result)) {
			$ajax->error($result->get_error_message());
		}

		$ajax->success([
			'message' => __('Status updated successfully', 'wptk-todo'),
			'new_status' => $new_status
		]);
	}

	public function ajax_get_todos(): void
	{
		$ajax = Registry::get('wptk-todo', 'ajax');

		$filters = $_POST['filters'] ?? [];
		$args = ['posts_per_page' => -1];

		// Apply filters
		if (!empty($filters['priority'])) {
			$args['meta_query'][] = [
				'key' => 'todo_details',
				'value' => '"priority":"' . sanitize_text_field($filters['priority']) . '"',
				'compare' => 'LIKE'
			];
		}

		if (!empty($filters['status'])) {
			$args['meta_query'][] = [
				'key' => 'todo_details',
				'value' => '"status":"' . sanitize_text_field($filters['status']) . '"',
				'compare' => 'LIKE'
			];
		}

		$todos = $this->todo_model->get_posts($args, true);
		$formatted_todos = [];

		foreach ($todos as $todo_data) {
			$post = $todo_data['post'];
			$meta = $todo_data['meta']['todo_details'] ?? [];

			$formatted_todos[] = [
				'id' => $post->ID,
				'title' => $post->post_title,
				'description' => $post->post_content,
				'priority' => $meta['priority'] ?? 'medium',
				'status' => $meta['status'] ?? 'pending',
				'due_date' => $meta['due_date'] ?? '',
				'created' => $post->post_date
			];
		}

		$ajax->success([
			'todos' => $formatted_todos,
			'count' => count($formatted_todos)
		]);
	}

	public function ajax_add_todo(): void
	{
		$ajax = Registry::get('wptk-todo', 'ajax');

		$title = sanitize_text_field($_POST['title'] ?? '');
		$description = sanitize_textarea_field($_POST['description'] ?? '');
		$priority = sanitize_text_field($_POST['priority'] ?? 'medium');
		$due_date = sanitize_text_field($_POST['due_date'] ?? '');

		if (empty($title)) {
			$ajax->error(__('Title is required', 'wptk-todo'));
		}

		$todo_data = [
			'title' => $title,
			'content' => $description,
			'status' => 'publish'
		];

		$meta_data = [
			'priority' => $priority,
			'status' => 'pending',
			'due_date' => $due_date
		];

		$result = $this->todo_model->create($todo_data, $meta_data);

		if (is_wp_error($result)) {
			$ajax->error($result->get_error_message());
		}

		$ajax->success([
			'todo_id' => $result,
			'message' => __('Todo created successfully', 'wptk-todo')
		]);
	}

	public function ajax_delete_todo(): void
	{
		$ajax = Registry::get('wptk-todo', 'ajax');

		$post_id = (int) ($_POST['post_id'] ?? 0);

		if (!$post_id) {
			$ajax->error(__('Invalid todo ID', 'wptk-todo'));
		}

		$result = $this->todo_model->delete($post_id);

		if (is_wp_error($result)) {
			$ajax->error($result->get_error_message());
		}

		$ajax->success([
			'message' => __('Todo deleted successfully', 'wptk-todo')
		]);
	}
}
