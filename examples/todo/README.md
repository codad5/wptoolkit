# Todo example

A complete small plugin on WPToolkit 1.0. It replaces the 0.x `sample-plugins/Todo`, which was built on
the active-record `Model` that 1.0 splits up ([ADR-0009](../../docs/adr/0009-entity-and-repository-not-active-record.md)).

| File | Shows |
| --- | --- |
| [todo.php](todo.php) | Booting through the guard: an old PHP or WordPress gets a notice, not a fatal error |
| [src/Todo.php](src/Todo.php) | An entity: fields, a post type, opt-in post columns, quick-editable fields |
| [src/TodoServiceProvider.php](src/TodoServiceProvider.php) | Registering the post type, edit screen and columns; routes with access rules, validation and rate limits |
| [src/TodoController.php](src/TodoController.php) | A repository, a paged query, validation errors as a 422, and a search that exposes only chosen fields |

## Run it

```bash
composer install
```

Then copy the folder into `wp-content/plugins/` and activate **WPToolkit Todo example**. Todos appear
under their own admin menu; the API is at `/wp-json/wptk-todo/v1/todos` for users who can `edit_posts`.

## Upgrading the 0.x sample's data

None needed. The entity keeps the 0.x meta box id `todo_details`, so its fields are stored under the
same keys (`todo_details_wptk_todo_priority`, …) as before.
