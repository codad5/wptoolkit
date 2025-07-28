(function($) {
    'use strict';

    class TodoManager {
        constructor() {
            this.init();
        }

        init() {
            this.bindEvents();
            this.loadTodos();
        }

        bindEvents() {
            // Modal controls
            $('#add-todo-btn').on('click', () => this.openAddTodoModal());
            $('.close, #cancel-add-todo').on('click', () => this.closeAddTodoModal());

            // Form submission
            $('#add-todo-form').on('submit', (e) => this.handleAddTodo(e));

            // Filters
            $('#priority-filter, #status-filter').on('change', () => this.loadTodos());

            // Refresh button
            $('#refresh-todos').on('click', () => this.loadTodos());

            // Modal click outside to close
            $('#add-todo-modal').on('click', (e) => {
                if (e.target.id === 'add-todo-modal') {
                    this.closeAddTodoModal();
                }
            });
        }

        openAddTodoModal() {
            $('#add-todo-modal').fadeIn();
            $('#todo-title').focus();
        }

        closeAddTodoModal() {
            $('#add-todo-modal').fadeOut();
            $('#add-todo-form')[0].reset();
        }

        showLoading() {
            $('#todo-loading').show();
            $('#todo-list').addClass('loading-state');
        }

        hideLoading() {
            $('#todo-loading').hide();
            $('#todo-list').removeClass('loading-state');
        }

        loadTodos() {
            this.showLoading();

            const filters = {
                priority: $('#priority-filter').val(),
                status: $('#status-filter').val()
            };

            $.ajax({
                url: wptkTodoAjax.ajax_url,
                type: 'POST',
                data: {
                    action: 'wptk_todo_get_todos',
                    _wpnonce: wptkTodoAjax.nonces.get_todos,
                    filters: filters
                },
                success: (response) => {
                    this.hideLoading();
                    if (response.success) {
                        this.renderTodos(response.data.todos);
                    } else {
                        this.showError('Failed to load todos: ' + response.data.message);
                    }
                },
                error: () => {
                    this.hideLoading();
                    this.showError('Network error occurred while loading todos');
                }
            });
        }

        renderTodos(todos) {
            const $container = $('#todo-list');

            if (!todos || todos.length === 0) {
                $container.html(`
                    <div class="no-todos">
                        <p>No todos found. <a href="#" id="add-first-todo">Add your first todo!</a></p>
                    </div>
                `);

                $('#add-first-todo').on('click', (e) => {
                    e.preventDefault();
                    this.openAddTodoModal();
                });
                return;
            }

            let html = '';
            todos.forEach(todo => {
                html += this.renderTodoItem(todo);
            });

            $container.html(html);
            this.bindTodoEvents();
        }

        renderTodoItem(todo) {
            const priorityClass = `priority-${todo.priority}`;
            const statusClass = `status-${todo.status}`;
            const isOverdue = todo.due_date && new Date(todo.due_date) < new Date() && todo.status !== 'completed';
            const overdueClass = isOverdue ? 'overdue' : '';

            return `
                <div class="todo-item ${statusClass} ${priorityClass} ${overdueClass}" data-id="${todo.id}">
                    <div class="todo-content">
                        <div class="todo-header">
                            <h3 class="todo-title">${this.escapeHtml(todo.title)}</h3>
                            <div class="todo-meta">
                                <span class="priority-badge">${todo.priority}</span>
                                <span class="status-badge">${todo.status.replace('_', ' ')}</span>
                            </div>
                        </div>
                        
                        ${todo.description ? `<p class="todo-description">${this.escapeHtml(todo.description)}</p>` : ''}
                        
                        <div class="todo-footer">
                            ${todo.due_date ? `<span class="due-date">Due: ${todo.due_date}</span>` : ''}
                            <div class="todo-actions">
                                <select class="status-select" data-id="${todo.id}">
                                    <option value="pending" ${todo.status === 'pending' ? 'selected' : ''}>Pending</option>
                                    <option value="in_progress" ${todo.status === 'in_progress' ? 'selected' : ''}>In Progress</option>
                                    <option value="completed" ${todo.status === 'completed' ? 'selected' : ''}>Completed</option>
                                </select>
                                <button class="btn btn-sm btn-danger delete-todo" data-id="${todo.id}">Delete</button>
                            </div>
                        </div>
                    </div>
                </div>
            `;
        }

        bindTodoEvents() {
            // Status change
            $('.status-select').on('change', (e) => {
                const $select = $(e.target);
                const todoId = $select.data('id');
                const newStatus = $select.val();
                this.updateTodoStatus(todoId, newStatus);
            });

            // Delete todo
            $('.delete-todo').on('click', (e) => {
                const todoId = $(e.target).data('id');
                if (confirm('Are you sure you want to delete this todo?')) {
                    this.deleteTodo(todoId);
                }
            });
        }

        handleAddTodo(e) {
            e.preventDefault();

            const formData = {
                action: 'wptk_todo_add_todo',
                _wpnonce: wptkTodoAjax.nonces.add_todo,
                title: $('#todo-title').val(),
                description: $('#todo-description').val(),
                priority: $('#todo-priority').val(),
                due_date: $('#todo-due-date').val()
            };

            $.ajax({
                url: wptkTodoAjax.ajax_url,
                type: 'POST',
                data: formData,
                success: (response) => {
                    if (response.success) {
                        this.closeAddTodoModal();
                        this.loadTodos();
                        this.showSuccess('Todo added successfully!');
                    } else {
                        this.showError('Failed to add todo: ' + response.data.message);
                    }
                },
                error: () => {
                    this.showError('Network error occurred while adding todo');
                }
            });
        }

        updateTodoStatus(todoId, newStatus) {
            $.ajax({
                url: wptkTodoAjax.ajax_url,
                type: 'POST',
                data: {
                    action: 'wptk_todo_toggle_status',
                    _wpnonce: wptkTodoAjax.nonces.toggle_status,
                    post_id: todoId,
                    status: newStatus
                },
                success: (response) => {
                    if (response.success) {
                        this.showSuccess('Todo status updated!');
                        // Update the visual state immediately
                        this.updateTodoVisualState(todoId, newStatus);
                    } else {
                        this.showError('Failed to update status: ' + response.data.message);
                        this.loadTodos(); // Reload to reset state
                    }
                },
                error: () => {
                    this.showError('Network error occurred while updating status');
                    this.loadTodos(); // Reload to reset state
                }
            });
        }

        updateTodoVisualState(todoId, newStatus) {
            const $todoItem = $(`.todo-item[data-id="${todoId}"]`);
            $todoItem.removeClass('status-pending status-in_progress status-completed');
            $todoItem.addClass(`status-${newStatus}`);

            const $statusBadge = $todoItem.find('.status-badge');
            $statusBadge.text(newStatus.replace('_', ' '));
        }

        deleteTodo(todoId) {
            $.ajax({
                url: wptkTodoAjax.ajax_url,
                type: 'POST',
                data: {
                    action: 'wptk_todo_delete_todo',
                    _wpnonce: wptkTodoAjax.nonces.delete_todo,
                    post_id: todoId
                },
                success: (response) => {
                    if (response.success) {
                        this.showSuccess('Todo deleted successfully!');
                        this.loadTodos();
                    } else {
                        this.showError('Failed to delete todo: ' + response.data.message);
                    }
                },
                error: () => {
                    this.showError('Network error occurred while deleting todo');
                }
            });
        }

        showSuccess(message) {
            this.showNotification(message, 'success');
        }

        showError(message) {
            this.showNotification(message, 'error');
        }

        showNotification(message, type) {
            const $notification = $(`
                <div class="notification notification-${type}">
                    ${this.escapeHtml(message)}
                    <button class="notification-close">&times;</button>
                </div>
            `);

            $('body').append($notification);

            setTimeout(() => {
                $notification.addClass('show');
            }, 100);

            $notification.on('click', '.notification-close', () => {
                this.hideNotification($notification);
            });

            setTimeout(() => {
                this.hideNotification($notification);
            }, 5000);
        }

        hideNotification($notification) {
            $notification.removeClass('show');
            setTimeout(() => {
                $notification.remove();
            }, 300);
        }

        escapeHtml(text) {
            const map = {
                '&': '&amp;',
                '<': '&lt;',
                '>': '&gt;',
                '"': '&quot;',
                "'": '&#039;'
            };
            return text.replace(/[&<>"']/g, (m) => map[m]);
        }
    }

    // Initialize when document is ready
    $(document).ready(() => {
        new TodoManager();
    });

})(jQuery);