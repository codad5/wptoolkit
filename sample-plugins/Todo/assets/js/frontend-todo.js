/**
 * Frontend Todo Management with WPToolkit Ajax
 */
class TodoManager {
    constructor() {
        // Get config from localized data
        this.config = window.wpToolkit?.['wptk-todo']?.['todo-frontend-js'] || window.wptkTodoAjax || {};

        if (!this.config.ajax_url) {
            console.error('Todo Ajax configuration not found');
            return;
        }

        // Initialize WPToolkit Ajax
        this.ajax = new WPToolkitAjax(this.config, {
            debug: true,
            globalErrorHandler: (error, action) => {
                console.error(`Ajax error in ${action}:`, error);
                this.showMessage(error.message || 'An error occurred', 'error');
            }
        });

        this.init();
    }

    init() {
        this.bindEvents();
        this.loadTodos();
    }

    bindEvents() {
        // Add todo button
        document.getElementById('add-todo-btn')?.addEventListener('click', () => {
            this.showAddModal();
        });

        // Modal close
        document.querySelector('#add-todo-modal .close')?.addEventListener('click', () => {
            this.hideAddModal();
        });

        // Cancel button
        document.getElementById('cancel-add-todo')?.addEventListener('click', () => {
            this.hideAddModal();
        });

        // Add todo form
        document.getElementById('add-todo-form')?.addEventListener('submit', (e) => {
            e.preventDefault();
            this.addTodo();
        });

        // Filters
        document.getElementById('priority-filter')?.addEventListener('change', () => {
            this.loadTodos();
        });

        document.getElementById('status-filter')?.addEventListener('change', () => {
            this.loadTodos();
        });

        // Refresh button
        document.getElementById('refresh-todos')?.addEventListener('click', () => {
            this.loadTodos();
        });

        // Modal background click
        document.getElementById('add-todo-modal')?.addEventListener('click', (e) => {
            if (e.target === e.currentTarget) {
                this.hideAddModal();
            }
        });
    }

    async loadTodos() {
        try {
            this.showLoading(true);

            const filters = {
                priority: document.getElementById('priority-filter')?.value || '',
                status: document.getElementById('status-filter')?.value || ''
            };

            const response = await this.ajax.post('get_todos', { filters });
            this.renderTodos(response.data.todos || []);
        } catch (error) {
            console.error('Failed to load todos:', error);
        } finally {
            this.showLoading(false);
        }
    }

    async addTodo() {
        try {
            const form = document.getElementById('add-todo-form');
            const formData = new FormData(form);

            const todoData = {
                title: formData.get('title'),
                description: formData.get('description'),
                priority: formData.get('priority'),
                due_date: formData.get('due_date')
            };

            const response = await this.ajax.post('add_todo', todoData);

            this.showMessage('Todo added successfully!', 'success');
            this.hideAddModal();
            form.reset();
            this.loadTodos(); // Reload the list
        } catch (error) {
            console.error('Failed to add todo:', error);
        }
    }

    async toggleTodoStatus(todoId, newStatus) {
        try {
            await this.ajax.post('toggle_status', {
                post_id: todoId,
                status: newStatus
            });

            this.showMessage('Status updated successfully!', 'success');
            this.loadTodos(); // Reload to show changes
        } catch (error) {
            console.error('Failed to update status:', error);
        }
    }

    async deleteTodo(todoId) {
        if (!confirm('Are you sure you want to delete this todo?')) {
            return;
        }

        try {
            await this.ajax.post('delete_todo', { post_id: todoId });
            this.showMessage('Todo deleted successfully!', 'success');
            this.loadTodos(); // Reload the list
        } catch (error) {
            console.error('Failed to delete todo:', error);
        }
    }

    renderTodos(todos) {
        console.log('Rendering todos:', todos);
        const container = document.getElementById('todo-list');
        if (!container) return;

        if (todos.length === 0) {
            container.innerHTML = '<p>No todos found. Create your first todo!</p>';
            return;
        }

        const todosHtml = todos.map(todo => this.renderTodoItem(todo)).join('');
        container.innerHTML = todosHtml;

        // Bind events for individual todos
        this.bindTodoEvents();
    }

    renderTodoItem(todo) {
        const priorityClass = `priority-${todo.priority}`;
        const statusClass = `status-${todo.status}`;

        return `
            <div class="todo-item ${priorityClass} ${statusClass}" data-id="${todo.id}">
                <div class="todo-content">
                    <h3 class="todo-title">${this.escapeHtml(todo.title)}</h3>
                    ${todo.description ? `<p class="todo-description">${this.escapeHtml(todo.description)}</p>` : ''}
                    <div class="todo-meta">
                        <span class="todo-priority">${this.escapeHtml(todo.priority)}</span>
                        <span class="todo-status">${this.escapeHtml(todo.status.replace('_', ' '))}</span>
                        ${todo.due_date ? `<span class="todo-due-date">Due: ${todo.due_date}</span>` : ''}
                    </div>
                </div>
                <div class="todo-actions">
                    <select class="status-select" data-todo-id="${todo.id}">
                        <option value="pending" ${todo.status === 'pending' ? 'selected' : ''}>Pending</option>
                        <option value="in_progress" ${todo.status === 'in_progress' ? 'selected' : ''}>In Progress</option>
                        <option value="completed" ${todo.status === 'completed' ? 'selected' : ''}>Completed</option>
                    </select>
                    <button class="btn btn-danger btn-sm delete-todo" data-todo-id="${todo.id}">Delete</button>
                </div>
            </div>
        `;
    }

    bindTodoEvents() {
        // Status change events
        document.querySelectorAll('.status-select').forEach(select => {
            select.addEventListener('change', (e) => {
                const todoId = e.target.dataset.todoId;
                const newStatus = e.target.value;
                this.toggleTodoStatus(todoId, newStatus);
            });
        });

        // Delete events
        document.querySelectorAll('.delete-todo').forEach(button => {
            button.addEventListener('click', (e) => {
                const todoId = e.target.dataset.todoId;
                this.deleteTodo(todoId);
            });
        });
    }

    showAddModal() {
        const modal = document.getElementById('add-todo-modal');
        if (modal) {
            modal.style.display = 'block';
            document.getElementById('todo-title')?.focus();
        }
    }

    hideAddModal() {
        const modal = document.getElementById('add-todo-modal');
        if (modal) {
            modal.style.display = 'none';
        }
    }

    showLoading(show) {
        const loading = document.getElementById('todo-loading');
        if (loading) {
            loading.style.display = show ? 'block' : 'none';
        }
    }

    showMessage(message, type = 'info') {
        // Create a simple message display
        const messageDiv = document.createElement('div');
        messageDiv.className = `message message-${type}`;
        messageDiv.textContent = message;
        messageDiv.style.cssText = `
            position: fixed;
            top: 20px;
            right: 20px;
            padding: 10px 20px;
            background: ${type === 'error' ? '#f44336' : '#4CAF50'};
            color: white;
            border-radius: 4px;
            z-index: 9999;
        `;

        document.body.appendChild(messageDiv);

        setTimeout(() => {
            messageDiv.remove();
        }, 3000);
    }

    escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }
}

// Initialize when DOM is ready
document.addEventListener('DOMContentLoaded', () => {
    new TodoManager();
});