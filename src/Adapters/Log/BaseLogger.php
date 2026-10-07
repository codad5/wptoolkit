<?php

/**
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

namespace Codad5\WPToolkit\Adapters\Log;

use Codad5\WPToolkit\Contracts\Log\Logger;
use Codad5\WPToolkit\Contracts\Log\LogLevel;
use DateTimeInterface;
use Stringable;
use Throwable;

/**
 * The shared part of every logger: level filtering, `{placeholder}` interpolation and redaction of
 * secrets, so an adapter only decides where a finished line goes.
 */
abstract class BaseLogger implements Logger
{
    /** Context keys whose values never reach a log (matched case-insensitively, as substrings). */
    private const SECRET_KEYS = ['password', 'passwd', 'secret', 'token', 'api_key', 'apikey', 'authorization', 'cookie', 'nonce'];

    public const REDACTED = '[redacted]';

    public function __construct(private readonly LogLevel $minimum = LogLevel::Debug)
    {
    }

    /**
     * Write one finished record.
     *
     * @param array<string, mixed> $context Already redacted.
     */
    abstract protected function write(LogLevel $level, string $message, array $context): void;

    public function log(LogLevel|string $level, string|Stringable $message, array $context = []): void
    {
        $level = $level instanceof LogLevel ? $level : LogLevel::fromName($level);
        if (!$level->isAtLeast($this->minimum)) {
            return;
        }

        $context = self::redact($context);
        $this->write($level, self::interpolate((string) $message, $context), $context);
    }

    public function emergency(string|Stringable $message, array $context = []): void
    {
        $this->log(LogLevel::Emergency, $message, $context);
    }

    public function alert(string|Stringable $message, array $context = []): void
    {
        $this->log(LogLevel::Alert, $message, $context);
    }

    public function critical(string|Stringable $message, array $context = []): void
    {
        $this->log(LogLevel::Critical, $message, $context);
    }

    public function error(string|Stringable $message, array $context = []): void
    {
        $this->log(LogLevel::Error, $message, $context);
    }

    public function warning(string|Stringable $message, array $context = []): void
    {
        $this->log(LogLevel::Warning, $message, $context);
    }

    public function notice(string|Stringable $message, array $context = []): void
    {
        $this->log(LogLevel::Notice, $message, $context);
    }

    public function info(string|Stringable $message, array $context = []): void
    {
        $this->log(LogLevel::Info, $message, $context);
    }

    public function debug(string|Stringable $message, array $context = []): void
    {
        $this->log(LogLevel::Debug, $message, $context);
    }

    /**
     * Replace `{key}` with the context value, the way PSR-3 describes.
     *
     * @param array<string, mixed> $context
     */
    public static function interpolate(string $message, array $context): string
    {
        $replace = [];
        foreach ($context as $key => $value) {
            $replace['{' . $key . '}'] = self::stringify($value);
        }

        return strtr($message, $replace);
    }

    /**
     * Hide values whose key looks secret, at any depth.
     *
     * @param array<array-key, mixed> $context
     * @return array<array-key, mixed>
     */
    public static function redact(array $context): array
    {
        foreach ($context as $key => $value) {
            if (is_string($key) && self::looksSecret($key)) {
                $context[$key] = self::REDACTED;
            } elseif (is_array($value)) {
                $context[$key] = self::redact($value);
            }
        }

        return $context;
    }

    /**
     * A one-line, log-safe rendering of any value.
     */
    public static function stringify(mixed $value): string
    {
        return match (true) {
            $value === null => 'null',
            is_bool($value) => $value ? 'true' : 'false',
            is_scalar($value) => (string) $value,
            $value instanceof Throwable => sprintf('%s: %s in %s:%d', $value::class, $value->getMessage(), $value->getFile(), $value->getLine()),
            $value instanceof DateTimeInterface => $value->format(DateTimeInterface::ATOM),
            $value instanceof Stringable => (string) $value,
            is_object($value) => '[object ' . $value::class . ']',
            default => (string) json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PARTIAL_OUTPUT_ON_ERROR),
        };
    }

    private static function looksSecret(string $key): bool
    {
        $key = strtolower($key);
        foreach (self::SECRET_KEYS as $secret) {
            if (str_contains($key, $secret)) {
                return true;
            }
        }

        return false;
    }
}
