<?php

/**
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

namespace Codad5\WPToolkit\Adapters\Log;

use Codad5\WPToolkit\Contracts\Log\Logger;
use Codad5\WPToolkit\Contracts\Log\LogLevel;
use Codad5\WPToolkit\Exceptions\InvalidConfigException;
use Codad5\WPToolkit\Foundation\Config;
use Codad5\WPToolkit\Foundation\HookRegistrar;

/**
 * Builds the logger described by config:
 *
 *     'log' => ['channels' => ['error_log', 'query_monitor', 'console'], 'level' => 'warning']
 *
 * Default: `error_log` at `warning`, plus `console` at `debug` when WP_DEBUG is on.
 */
final class LoggerFactory
{
    public function __construct(private readonly Config $config, private readonly HookRegistrar $hooks)
    {
    }

    public function make(): Logger
    {
        $debug = defined('WP_DEBUG') && (bool) constant('WP_DEBUG');
        $channels = $this->config->get('log.channels', $debug ? ['error_log', 'console'] : ['error_log']);
        $level = LogLevel::fromName((string) $this->config->get('log.level', $debug ? 'debug' : 'warning'));

        if (!is_array($channels)) {
            throw new InvalidConfigException("'log.channels' must be a list.");
        }

        $loggers = [];
        foreach ($channels as $channel) {
            $loggers[] = match ($channel) {
                'error_log' => new ErrorLogLogger($this->config->slug, $level),
                'query_monitor' => new QueryMonitorLogger($this->config->slug, $level),
                'console' => new BrowserConsoleLogger($this->config->slug, $this->hooks, $level),
                'null' => new NullLogger(),
                default => throw new InvalidConfigException(sprintf(
                    'Unknown log channel "%s". Use error_log, query_monitor, console or null.',
                    is_scalar($channel) ? (string) $channel : get_debug_type($channel)
                )),
            };
        }

        return match (count($loggers)) {
            0 => new NullLogger(),
            1 => $loggers[0],
            default => new StackLogger(...$loggers),
        };
    }
}
