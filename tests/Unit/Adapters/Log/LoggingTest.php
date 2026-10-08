<?php

declare(strict_types=1);

namespace Codad5\WPToolkit\Tests\Unit\Adapters\Log;

use Brain\Monkey\Actions;
use Brain\Monkey\Functions;
use Codad5\WPToolkit\Adapters\Log\ArrayLogger;
use Codad5\WPToolkit\Adapters\Log\BaseLogger;
use Codad5\WPToolkit\Adapters\Log\BrowserConsoleLogger;
use Codad5\WPToolkit\Adapters\Log\ErrorLogLogger;
use Codad5\WPToolkit\Adapters\Log\LoggerFactory;
use Codad5\WPToolkit\Adapters\Log\QueryMonitorLogger;
use Codad5\WPToolkit\Adapters\Log\StackLogger;
use Codad5\WPToolkit\Contracts\Log\LogLevel;
use Codad5\WPToolkit\Exceptions\InvalidConfigException;
use Codad5\WPToolkit\Foundation\Config;
use Codad5\WPToolkit\Foundation\HookRegistrar;
use Codad5\WPToolkit\Tests\TestCase;
use RuntimeException;

final class LoggingTest extends TestCase
{
    public function test_placeholders_are_interpolated(): void
    {
        $logger = new ArrayLogger();

        $logger->info('Order {id} paid by {user}', ['id' => 42, 'user' => 'ada', 'unused' => true]);

        self::assertSame(['Order 42 paid by ada'], $logger->messages());
    }

    public function test_secret_looking_keys_are_redacted_at_any_depth(): void
    {
        $logger = new ArrayLogger();

        $logger->error('Login with {password}', [
            'password' => 'hunter2',
            'request' => ['headers' => ['Authorization' => 'Bearer abc', 'Accept' => 'json'], 'api_key' => 'k'],
        ]);

        $record = $logger->records[0];
        self::assertSame('Login with ' . BaseLogger::REDACTED, $record['message']);
        self::assertSame(BaseLogger::REDACTED, $record['context']['request']['headers']['Authorization']);
        self::assertSame('json', $record['context']['request']['headers']['Accept']);
        self::assertSame(BaseLogger::REDACTED, $record['context']['request']['api_key']);
    }

    public function test_records_below_the_minimum_level_are_dropped(): void
    {
        $logger = new ArrayLogger(LogLevel::Warning);

        $logger->debug('noise');
        $logger->info('noise');
        $logger->warning('kept');
        $logger->log('critical', 'kept too');

        self::assertSame(['kept', 'kept too'], $logger->messages());
    }

    public function test_unknown_level_is_rejected(): void
    {
        $this->expectException(InvalidConfigException::class);

        (new ArrayLogger())->log('loud', 'x');
    }

    public function test_error_log_writes_one_readable_line(): void
    {
        Functions\expect('error_log')->once()->with(\Mockery::on(static function (string $line): bool {
            return str_starts_with($line, '[my-plugin] ERROR: Import failed {"file":"a.csv"}')
                && str_contains($line, 'RuntimeException: disk full');
        }));

        (new ErrorLogLogger('my-plugin'))->error('Import failed', ['file' => 'a.csv', 'exception' => new RuntimeException('disk full')]);
    }

    public function test_query_monitor_receives_records_on_its_actions(): void
    {
        Actions\expectDone('qm/warning')->once()->with('[my-plugin] Slow query');

        (new QueryMonitorLogger('my-plugin'))->warning('Slow query');
    }

    public function test_stack_sends_to_every_logger_with_their_own_levels(): void
    {
        $everything = new ArrayLogger();
        $errorsOnly = new ArrayLogger(LogLevel::Error);

        (new StackLogger($everything, $errorsOnly))->info('hello');

        self::assertSame(['hello'], $everything->messages());
        self::assertSame([], $errorsOnly->messages());
    }

    public function test_console_buffers_and_prints_once_in_the_footer_for_developers(): void
    {
        $this->stubJson();
        $hooks = new HookRegistrar();
        $logger = new BrowserConsoleLogger('my-plugin', $hooks, LogLevel::Debug, static fn () => true);

        $logger->info('first');
        $logger->warning('second');

        self::assertCount(2, $hooks->all(), 'one footer hook each for front end and admin, added once');
        ob_start();
        $logger->flush();
        $html = (string) ob_get_clean();

        self::assertStringContainsString('console.info("[my-plugin] first"', $html);
        self::assertStringContainsString('console.warn("[my-plugin] second"', $html);

        ob_start();
        $logger->flush();
        self::assertSame('', ob_get_clean(), 'the buffer is emptied');
    }

    public function test_console_prints_nothing_for_visitors(): void
    {
        $this->stubJson();
        $logger = new BrowserConsoleLogger('my-plugin', new HookRegistrar(), LogLevel::Debug, static fn () => false);
        $logger->error('secret path /var/www');

        ob_start();
        $logger->flush();

        self::assertSame('', ob_get_clean());
    }

    public function test_console_output_cannot_break_out_of_the_script_tag(): void
    {
        $this->stubJson();
        $logger = new BrowserConsoleLogger('my-plugin', new HookRegistrar(), LogLevel::Debug, static fn () => true);
        $logger->info('</script><script>alert(1)</script>');

        ob_start();
        $logger->flush();
        $html = (string) ob_get_clean();

        self::assertSame(1, substr_count($html, '</script>'), 'only the closing tag the logger itself writes');
    }

    public function test_factory_builds_the_configured_channels(): void
    {
        $config = Config::fromArray('/p.php', ['slug' => 'my-plugin', 'log' => ['channels' => ['error_log', 'query_monitor'], 'level' => 'error']]);

        self::assertInstanceOf(StackLogger::class, (new LoggerFactory($config, new HookRegistrar()))->make());
    }

    public function test_factory_rejects_unknown_channels(): void
    {
        $config = Config::fromArray('/p.php', ['slug' => 'my-plugin', 'log' => ['channels' => ['papertrail']]]);

        $this->expectException(InvalidConfigException::class);

        (new LoggerFactory($config, new HookRegistrar()))->make();
    }

    private function stubJson(): void
    {
        Functions\when('wp_json_encode')->alias(static fn ($data, int $flags = 0) => json_encode($data, $flags));
    }
}
