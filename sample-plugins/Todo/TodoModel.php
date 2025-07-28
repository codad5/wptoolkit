<?php

namespace Codad5\SamplePlugins\Todo;

use Codad5\WPToolkit\DB\MetaBox;
use Codad5\WPToolkit\DB\Model;
use Codad5\WPToolkit\Registry;
use Codad5\WPToolkit\Utils\Cache;


/**
 * Todo Model - Manages todo items as custom post type
 */
class TodoModel extends Model
{
	protected const string POST_TYPE = 'wptk_todo';

	protected static function get_post_type_args(): array
	{
		return [
			'labels' => [
				'name' => __('Todos', 'wptk-todo'),
				'singular_name' => __('Todo', 'wptk-todo'),
				'add_new' => __('Add New Todo', 'wptk-todo'),
				'add_new_item' => __('Add New Todo Item', 'wptk-todo'),
				'edit_item' => __('Edit Todo', 'wptk-todo'),
				'new_item' => __('New Todo', 'wptk-todo'),
				'view_item' => __('View Todo', 'wptk-todo'),
				'search_items' => __('Search Todos', 'wptk-todo'),
				'not_found' => __('No todos found', 'wptk-todo'),
				'not_found_in_trash' => __('No todos found in trash', 'wptk-todo'),
			],
			'public' => true,
			'has_archive' => true,
			'show_in_menu' => false,
			'menu_icon' => 'dashicons-yes-alt',
			'supports' => ['title', 'editor'],
			'show_in_rest' => true,
		];
	}

	protected function before_run(): void
	{
		$this->setup_metaboxes();
	}

	private function setup_metaboxes(): void
	{
		$metabox = MetaBox::create('todo_details', __('Todo Details', 'wptk-todo'), self::POST_TYPE, $this->config)
		                  ->add_field('priority', __('Priority', 'wptk-todo'), 'select', [
			                  'low' => __('Low', 'wptk-todo'),
			                  'medium' => __('Medium', 'wptk-todo'),
			                  'high' => __('High', 'wptk-todo'),
			                  'urgent' => __('Urgent', 'wptk-todo')
		                  ], [
			                  'default' => 'medium',
			                  'required' => true
		                  ])
		                  ->add_field('due_date', __('Due Date', 'wptk-todo'), 'date', [], [
			                  'required' => false
		                  ])
		                  ->add_field('status', __('Status', 'wptk-todo'), 'select', [
			                  'pending' => __('Pending', 'wptk-todo'),
			                  'in_progress' => __('In Progress', 'wptk-todo'),
			                  'completed' => __('Completed', 'wptk-todo')
		                  ], [
			                  'default' => 'pending',
			                  'required' => true
		                  ])
		                  ->add_field('estimated_hours', __('Estimated Hours', 'wptk-todo'), 'number', [], [
			                  'min' => 0,
			                  'step' => 0.5
		                  ])
		                  ->onSuccess(function($post_id, $metabox) {
			                  // Clear cache when todo is saved
			                  Cache::delete("todo_stats", 'wptk_todos');

			                  // Get notification service and show success message
			                  $notification = Registry::get('wptk-todo', 'notification');
			                  $notification->success(__('Todo saved successfully!', 'wptk-todo'));
		                  })
		                  ->onError(function($errors, $post_id, $metabox) {
			                  error_log('Todo save failed: ' . print_r($errors, true));
		                  })
		                  ->setup_actions();

		$this->register_metabox($metabox);
	}

	protected function get_admin_columns(): array
	{
		return [
			'priority' => [
				'label' => __('Priority', 'wptk-todo'),
				'type' => 'text',
				'sortable' => true,
				'metabox_id' => 'todo_details',
				'field_id' => 'priority',
				'callback' => function($value) {
					$colors = [
						'low' => '#28a745',
						'medium' => '#ffc107',
						'high' => '#fd7e14',
						'urgent' => '#dc3545'
					];
					$color = $colors[$value] ?? '#6c757d';
					return sprintf(
						'<span style="color: %s; font-weight: bold;">%s</span>',
						$color,
						ucfirst($value)
					);
				}
			],
			'status' => [
				'label' => __('Status', 'wptk-todo'),
				'type' => 'text',
				'sortable' => true,
				'metabox_id' => 'todo_details',
				'field_id' => 'status',
				'callback' => function($value) {
					$icons = [
						'pending' => '⏳',
						'in_progress' => '🔄',
						'completed' => '✅'
					];
					return ($icons[$value] ?? '❓') . ' ' . ucfirst(str_replace('_', ' ', $value));
				}
			],
			'due_date' => [
				'label' => __('Due Date', 'wptk-todo'),
				'type' => 'date',
				'sortable' => true,
				'metabox_id' => 'todo_details',
				'field_id' => 'due_date'
			]
		];
	}

	/**
	 * Get todo statistics
	 */
	public function get_stats(): array
	{
		return Cache::remember('todo_stats', function() {
			$todos = $this->get_posts();

			$stats = [
				'total' => count($todos),
				'completed' => 0,
				'pending' => 0,
				'in_progress' => 0,
				'overdue' => 0,
				'priority_breakdown' => ['low' => 0, 'medium' => 0, 'high' => 0, 'urgent' => 0]
			];

			$today = date('Y-m-d');

			foreach ($todos as $todo) {
				$status = $todo['meta']['status'] ?? 'pending';
				$priority = $todo['meta']['priority'] ?? 'medium';
				$due_date = $todo['meta']['due_date'] ?? '';

				$stats[$status]++;
				$stats['priority_breakdown'][$priority]++;

				if ($due_date && $due_date < $today && $status !== 'completed') {
					$stats['overdue']++;
				}
			}

			return $stats;
		}, 300, 'wptk_todos'); // Cache for 5 minutes
	}

	/**
	 * Get todos by status
	 */
	public function get_by_status(string $status): array
	{
		return $this->get_posts([
			'meta_query' => [
				[
					'key' => 'todo_details_status',
					'value' => $status,
					'compare' => '='
				]
			],
			'posts_per_page' => -1
		]);
	}
}
