<?php

/**
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

namespace Codad5\WPToolkit\Contracts\Log;

use Codad5\WPToolkit\Exceptions\InvalidConfigException;

/**
 * PSR-3's eight levels, most severe first.
 */
enum LogLevel: string
{
    case Emergency = 'emergency';
    case Alert = 'alert';
    case Critical = 'critical';
    case Error = 'error';
    case Warning = 'warning';
    case Notice = 'notice';
    case Info = 'info';
    case Debug = 'debug';

    /**
     * Higher is more severe (debug = 0 … emergency = 7).
     */
    public function severity(): int
    {
        return match ($this) {
            self::Emergency => 7,
            self::Alert => 6,
            self::Critical => 5,
            self::Error => 4,
            self::Warning => 3,
            self::Notice => 2,
            self::Info => 1,
            self::Debug => 0,
        };
    }

    public function isAtLeast(self $minimum): bool
    {
        return $this->severity() >= $minimum->severity();
    }

    public static function fromName(string $name): self
    {
        return self::tryFrom(strtolower($name))
            ?? throw new InvalidConfigException(sprintf('Unknown log level "%s".', $name));
    }
}
