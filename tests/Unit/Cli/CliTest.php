<?php

declare(strict_types=1);

namespace Codad5\WPToolkit\Tests\Unit\Cli;

use Codad5\WPToolkit\Adapters\Clock\FrozenClock;
use Codad5\WPToolkit\Adapters\Log\ArrayLogger;
use Codad5\WPToolkit\Adapters\Migrations\ArrayMigrationStore;
use Codad5\WPToolkit\Cli\BufferedConsole;
use Codad5\WPToolkit\Cli\CliException;
use Codad5\WPToolkit\Cli\Scaffolder;
use Codad5\WPToolkit\Cli\ToolkitCommand;
use Codad5\WPToolkit\Data\Migrations\Migration;
use Codad5\WPToolkit\Data\Migrations\Migrator;
use Codad5\WPToolkit\Foundation\Container;
use Codad5\WPToolkit\Foundation\HookRegistrar;
use Codad5\WPToolkit\Foundation\Identity;
use Codad5\WPToolkit\Frontend\PublicPages;
use Codad5\WPToolkit\Http\Dispatcher;
use Codad5\WPToolkit\Http\Router;
use Codad5\WPToolkit\Http\Transport\AjaxTransport;
use Codad5\WPToolkit\Http\Transport\RestTransport;
use Codad5\WPToolkit\Tests\TestCase;
use Codad5\WPToolkit\View\PhpTemplateRenderer;
use Codad5\WPToolkit\View\TemplateLocator;
use DateTimeImmutable;

final class CliTest extends TestCase
{
    private string $dir;

    private BufferedConsole $console;

    private ArrayMigrationStore $store;

    protected function setUp(): void
    {
        parent::setUp();
        $this->dir = sys_get_temp_dir() . '/wptk-cli-' . bin2hex(random_bytes(4));
        mkdir($this->dir);
        $this->console = new BufferedConsole();
        $this->store = new ArrayMigrationStore();
    }

    protected function tearDown(): void
    {
        $this->remove($this->dir);
        parent::tearDown();
    }

    // --- make:* --------------------------------------------------------------------------------

    public function test_every_kind_generates_valid_php_in_the_plugins_namespace(): void
    {
        $scaffolder = $this->scaffolder();

        foreach (['entity' => 'Book', 'controller' => 'book', 'provider' => 'admin-tools', 'field' => 'StarRating', 'migration' => 'RenameRating'] as $kind => $name) {
            $path = $scaffolder->make($kind, $name);

            exec(escapeshellarg(PHP_BINARY) . ' -l ' . escapeshellarg($path) . ' 2>&1', $output, $code);
            self::assertSame(0, $code, $kind . ': ' . implode("\n", $output));
            self::assertStringContainsString('namespace MyPlugin', (string) file_get_contents($path), $kind);
            self::assertStringNotContainsString('{{', (string) file_get_contents($path), $kind . ': unfilled placeholder');
        }

        self::assertFileExists($this->dir . '/src/Entities/Book.php');
        self::assertFileExists($this->dir . '/src/Http/BookController.php');
        self::assertFileExists($this->dir . '/src/AdminToolsServiceProvider.php');
        self::assertFileExists($this->dir . '/src/Fields/StarRatingFieldType.php');
        self::assertStringContainsString("'2026_10_08_093000_rename_rating'", (string) file_get_contents($this->dir . '/src/Migrations/RenameRating.php'));
        self::assertStringContainsString("->can('read')", (string) file_get_contents($this->dir . '/src/Http/BookController.php'), 'the example route has an access rule');
        self::assertStringContainsString("'my-plugin')", (string) file_get_contents($this->dir . '/src/Entities/Book.php'), 'strings use the plugin text domain');
    }

    public function test_existing_files_are_kept_unless_forced(): void
    {
        $scaffolder = $this->scaffolder();
        $path = $scaffolder->make('entity', 'Book');
        file_put_contents($path, '<?php // mine');

        try {
            $scaffolder->make('entity', 'Book');
            self::fail('Expected a refusal.');
        } catch (CliException $e) {
            self::assertStringContainsString('--force', $e->getMessage());
        }
        self::assertSame('<?php // mine', file_get_contents($path));

        $scaffolder->make('entity', 'Book', force: true);
        self::assertStringContainsString('final class Book', (string) file_get_contents($path));
    }

    public function test_bad_names_and_kinds_are_refused(): void
    {
        foreach ([['entity', '9lives'], ['entity', '---'], ['widget', 'Thing']] as [$kind, $name]) {
            try {
                $this->scaffolder()->make($kind, $name);
                self::fail("{$kind} {$name}");
            } catch (CliException) {
                self::addToAssertionCount(1);
            }
        }
    }

    public function test_the_namespace_comes_from_composer_json(): void
    {
        file_put_contents($this->dir . '/composer.json', (string) json_encode(['autoload' => ['psr-4' => ['Other\\' => 'lib/', 'MyPlugin\\' => 'src/']]]));

        self::assertSame('MyPlugin', Scaffolder::namespaceFromComposer($this->dir));
        self::assertNull(Scaffolder::namespaceFromComposer($this->dir . '/missing'));
    }

    // --- Inspection ----------------------------------------------------------------------------

    public function test_routes_list_shows_who_may_call_each_route_and_page(): void
    {
        $command = $this->command();
        [$router, $pages] = $this->routing;
        $router->get('books', fn () => [])->can('read');
        $router->post('books', fn () => [])->loggedIn()->exposeVia('ajax');
        $router->get('open', fn () => []);
        $pages->page('library', 'Library', 'v')->public();

        $command->routes([], []);

        self::assertSame([
            ['methods' => 'GET', 'path' => 'books', 'name' => 'books', 'via' => 'rest', 'access' => 'can:read'],
            ['methods' => 'POST', 'path' => 'books', 'name' => 'books', 'via' => 'ajax', 'access' => 'logged_in'],
            ['methods' => 'GET', 'path' => 'open', 'name' => 'open', 'via' => 'rest', 'access' => 'NONE (refused)'],
            ['methods' => 'GET', 'path' => 'library', 'name' => 'page_library', 'via' => 'page', 'access' => 'public'],
        ], $this->console->tables[0]['rows']);
    }

    // --- Migrations ----------------------------------------------------------------------------

    public function test_migrate_status_and_rollback(): void
    {
        $command = $this->command([$this->migration('2026_10_08_000000_one')]);

        $command->migrate([], ['dry-run' => true]);
        self::assertSame('Would apply: 2026_10_08_000000_one', $this->console->lines[0]);
        self::assertSame([], $this->store->applied);

        $command->migrate([], []);
        self::assertContains('Success: 1 migration(s) applied.', $this->console->lines);

        $command->migrateStatus([], []);
        self::assertSame('yes', $this->console->tables[0]['rows'][0]['applied']);

        $command->migrateRollback([], ['steps' => '1']);
        self::assertContains('Success: Rolled back: 2026_10_08_000000_one', $this->console->lines);
        self::assertSame([], $this->store->applied);
    }

    public function test_a_locked_run_is_an_error(): void
    {
        $this->store->lockedUntil = PHP_INT_MAX;

        $this->expectException(CliException::class);
        $this->expectExceptionMessage('in progress');

        $this->command([$this->migration('2026_10_08_000000_one')])->migrate([], []);
    }

    /** @var array{Router, PublicPages} */
    private array $routing;

    /**
     * @param list<Migration> $migrations
     */
    private function command(array $migrations = []): ToolkitCommand
    {
        $identity = new Identity('my-plugin');
        $hooks = new HookRegistrar();
        $dispatcher = new Dispatcher(new Container(), $identity, new ArrayLogger());
        $router = new Router($hooks, new RestTransport($dispatcher, $identity), new AjaxTransport($dispatcher, $identity, $hooks));
        $pages = new PublicPages($identity, $hooks, $dispatcher, new PhpTemplateRenderer(new TemplateLocator([], null)));
        $this->routing = [$router, $pages];

        return new ToolkitCommand(
            $this->console,
            '1.0.0',
            $router,
            $pages,
            $hooks,
            new Migrator($migrations, $this->store, new FrozenClock(), new ArrayLogger()),
            $this->scaffolder()
        );
    }

    private function scaffolder(): Scaffolder
    {
        return new Scaffolder(
            $this->dir,
            'MyPlugin',
            'my-plugin',
            'my-plugin',
            dirname(__DIR__, 3) . '/resources/stubs',
            new FrozenClock(new DateTimeImmutable('2026-10-08 09:30:00'))
        );
    }

    private function migration(string $id): Migration
    {
        return new class ($id) extends Migration {
            public function __construct(private string $name)
            {
            }

            public function id(): string
            {
                return $this->name;
            }

            public function up(): void
            {
            }

            public function down(): void
            {
            }
        };
    }

    private function remove(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }
        foreach (new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS), \RecursiveIteratorIterator::CHILD_FIRST) as $item) {
            $item->isDir() ? rmdir($item->getPathname()) : unlink($item->getPathname());
        }
        rmdir($dir);
    }
}
