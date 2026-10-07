<?php

/**
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

namespace Codad5\WPToolkit\Cli;

use Codad5\WPToolkit\Data\Migrations\Migrator;
use Codad5\WPToolkit\Foundation\Coexistence;
use Codad5\WPToolkit\Foundation\HookRegistrar;
use Codad5\WPToolkit\Frontend\PublicPages;
use Codad5\WPToolkit\Http\Route;
use Codad5\WPToolkit\Http\Router;
use Throwable;

/**
 * `wp {slug} …` — inspect and maintain a plugin built on WPToolkit. Registered once per plugin, so
 * two plugins on one site each get their own command.
 */
final class ToolkitCommand
{
    public function __construct(
        private readonly Console $console,
        private readonly string $version,
        private readonly Router $router,
        private readonly PublicPages $pages,
        private readonly HookRegistrar $hooks,
        private readonly Migrator $migrator,
        private readonly ?Scaffolder $scaffolder
    ) {
    }

    /**
     * Shows this plugin's copy of WPToolkit and every other copy loaded on the site.
     *
     * ## OPTIONS
     *
     * [--format=<format>]
     * : table, json, csv or yaml.
     * ---
     * default: table
     * ---
     *
     * @subcommand toolkit:info
     *
     * @param list<string> $args
     * @param array<string, string|bool> $assoc
     */
    public function info(array $args, array $assoc): void
    {
        $this->console->line(sprintf(
            'This plugin runs WPToolkit %s from %s (namespace %s).',
            $this->version,
            Coexistence::thisCopyPath(),
            Coexistence::thisCopyNamespace()
        ));

        $rows = [];
        foreach (Coexistence::copies() as $copy) {
            $rows[] = [
                'version' => $copy['version'],
                'namespace' => $copy['namespace'],
                'scoped' => $copy['namespace'] === 'Codad5\\WPToolkit' ? 'no' : 'yes',
                'path' => $copy['path'],
                'this plugin' => $copy['path'] === Coexistence::thisCopyPath() ? 'yes' : '',
            ];
        }
        $this->console->table($rows, ['version', 'namespace', 'scoped', 'path', 'this plugin'], $this->format($assoc));
    }

    /**
     * Lists this plugin's REST and Ajax routes and its public pages, with who may call each.
     *
     * ## OPTIONS
     *
     * [--format=<format>]
     * : table, json, csv or yaml.
     * ---
     * default: table
     * ---
     *
     * @subcommand routes:list
     *
     * @param list<string> $args
     * @param array<string, string|bool> $assoc
     */
    public function routes(array $args, array $assoc): void
    {
        $rows = [];
        foreach ($this->router->routes() as $route) {
            $rows[] = $this->routeRow($route, implode(',', $route->transports()));
        }
        foreach ($this->pages->routes() as $route) {
            $rows[] = $this->routeRow($route, 'page');
        }

        $this->console->table($rows, ['methods', 'path', 'name', 'via', 'access'], $this->format($assoc));
    }

    /**
     * Lists every hook this plugin added through WPToolkit.
     *
     * ## OPTIONS
     *
     * [--format=<format>]
     * : table, json, csv or yaml.
     * ---
     * default: table
     * ---
     *
     * @subcommand hooks:list
     *
     * @param list<string> $args
     * @param array<string, string|bool> $assoc
     */
    public function hooks(array $args, array $assoc): void
    {
        $rows = array_map(static fn (array $hook): array => [
            'type' => $hook['type'],
            'hook' => $hook['hook'],
            'priority' => $hook['priority'],
            'args' => $hook['args'],
        ], $this->hooks->all());

        $this->console->table($rows, ['type', 'hook', 'priority', 'args'], $this->format($assoc));
    }

    /**
     * Applies pending data migrations.
     *
     * ## OPTIONS
     *
     * [--dry-run]
     * : List what would run without running it.
     *
     * @param list<string> $args
     * @param array<string, string|bool> $assoc
     */
    public function migrate(array $args, array $assoc): void
    {
        $result = $this->migrator->run(null, (bool) ($assoc['dry-run'] ?? false));

        if ($result->locked) {
            $this->console->error('Another migration run is in progress. Try again shortly.');
        }
        if ($result->dryRun) {
            $this->console->line($result->applied === [] ? 'Nothing to migrate.' : 'Would apply: ' . implode(', ', $result->applied));
            return;
        }
        foreach ($result->applied as $id) {
            $this->console->line('Applied ' . $id);
        }
        if ($result->failed !== null) {
            $this->console->error(sprintf('%s failed: %s', $result->failed['id'], $result->failed['message']));
        }
        $this->console->success($result->applied === [] ? 'Nothing to migrate.' : sprintf('%d migration(s) applied.', count($result->applied)));
    }

    /**
     * Shows every migration and whether it has run.
     *
     * ## OPTIONS
     *
     * [--format=<format>]
     * : table, json, csv or yaml.
     * ---
     * default: table
     * ---
     *
     * @subcommand migrate:status
     *
     * @param list<string> $args
     * @param array<string, string|bool> $assoc
     */
    public function migrateStatus(array $args, array $assoc): void
    {
        $rows = array_map(static fn (array $m): array => [
            'id' => $m['id'],
            'applied' => $m['applied'] ? 'yes' : 'no',
            'applied at' => $m['applied_at'] === null ? '' : gmdate('Y-m-d H:i:s', $m['applied_at']),
            'batches' => $m['batches'],
            'reversible' => $m['reversible'] ? 'yes' : 'no',
        ], $this->migrator->status());

        $this->console->table($rows, ['id', 'applied', 'applied at', 'batches', 'reversible'], $this->format($assoc));
        $failure = $this->migrator->lastFailure();
        if ($failure !== null) {
            $this->console->warning(sprintf('Last run failed at %s: %s', $failure['id'], $failure['message']));
        }
    }

    /**
     * Undoes the most recent migrations, newest first.
     *
     * ## OPTIONS
     *
     * [--steps=<steps>]
     * : How many to undo.
     * ---
     * default: 1
     * ---
     *
     * [--dry-run]
     * : List what would be undone without undoing it.
     *
     * @subcommand migrate:rollback
     *
     * @param list<string> $args
     * @param array<string, string|bool> $assoc
     */
    public function migrateRollback(array $args, array $assoc): void
    {
        $dryRun = (bool) ($assoc['dry-run'] ?? false);
        try {
            $ids = $this->migrator->rollback(max(1, (int) ($assoc['steps'] ?? 1)), $dryRun);
        } catch (Throwable $e) {
            $this->console->error($e->getMessage());
        }

        if ($ids === []) {
            $this->console->line('Nothing to roll back.');
            return;
        }
        $this->console->success(($dryRun ? 'Would roll back: ' : 'Rolled back: ') . implode(', ', $ids));
    }

    /**
     * Creates an entity class (a post type with fields).
     *
     * ## OPTIONS
     *
     * <name>
     * : Class name, e.g. Book.
     *
     * [--force]
     * : Replace an existing file.
     *
     * @subcommand make:entity
     *
     * @param list<string> $args
     * @param array<string, string|bool> $assoc
     */
    public function makeEntity(array $args, array $assoc): void
    {
        $this->make('entity', $args, $assoc);
    }

    /**
     * Creates a controller for routes.
     *
     * ## OPTIONS
     *
     * <name>
     * : Resource name, e.g. Book (creates BookController).
     *
     * [--force]
     * : Replace an existing file.
     *
     * @subcommand make:controller
     *
     * @param list<string> $args
     * @param array<string, string|bool> $assoc
     */
    public function makeController(array $args, array $assoc): void
    {
        $this->make('controller', $args, $assoc);
    }

    /**
     * Creates a service provider.
     *
     * ## OPTIONS
     *
     * <name>
     * : Name, e.g. Admin (creates AdminServiceProvider).
     *
     * [--force]
     * : Replace an existing file.
     *
     * @subcommand make:provider
     *
     * @param list<string> $args
     * @param array<string, string|bool> $assoc
     */
    public function makeProvider(array $args, array $assoc): void
    {
        $this->make('provider', $args, $assoc);
    }

    /**
     * Creates a custom field type.
     *
     * ## OPTIONS
     *
     * <name>
     * : Name, e.g. StarRating (creates StarRatingFieldType).
     *
     * [--force]
     * : Replace an existing file.
     *
     * @subcommand make:field
     *
     * @param list<string> $args
     * @param array<string, string|bool> $assoc
     */
    public function makeField(array $args, array $assoc): void
    {
        $this->make('field', $args, $assoc);
    }

    /**
     * Creates a migration with a timestamped id.
     *
     * ## OPTIONS
     *
     * <name>
     * : What it does, e.g. RenameRating.
     *
     * [--force]
     * : Replace an existing file.
     *
     * @subcommand make:migration
     *
     * @param list<string> $args
     * @param array<string, string|bool> $assoc
     */
    public function makeMigration(array $args, array $assoc): void
    {
        $this->make('migration', $args, $assoc);
    }

    /**
     * @param list<string> $args
     * @param array<string, string|bool> $assoc
     */
    private function make(string $kind, array $args, array $assoc): void
    {
        if ($this->scaffolder === null) {
            $this->console->error(
                'Cannot tell this plugin\'s namespace. Add an autoload.psr-4 entry for "src/" to its composer.json, '
                . 'or set "namespace" in the application config.'
            );
        }
        $name = $args[0] ?? '';

        try {
            $path = $this->scaffolder->make($kind, $name, (bool) ($assoc['force'] ?? false));
        } catch (CliException $e) {
            $this->console->error($e->getMessage());
        }

        $this->console->success('Created ' . $path);
    }

    /**
     * @return array<string, string>
     */
    private function routeRow(Route $route, string $via): array
    {
        $access = match ($route->access()) {
            'capability' => 'can:' . $route->capability(),
            null => 'NONE (refused)',
            default => (string) $route->access(),
        };

        return [
            'methods' => implode('|', $route->methods),
            'path' => $route->path,
            'name' => $route->routeName(),
            'via' => $via,
            'access' => $access,
        ];
    }

    /**
     * @param array<string, string|bool> $assoc
     */
    private function format(array $assoc): string
    {
        $format = $assoc['format'] ?? 'table';

        return is_string($format) && in_array($format, ['table', 'json', 'csv', 'yaml'], true) ? $format : 'table';
    }
}
