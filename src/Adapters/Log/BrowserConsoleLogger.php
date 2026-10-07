<?php

/**
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

namespace Codad5\WPToolkit\Adapters\Log;

use Closure;
use Codad5\WPToolkit\Contracts\Log\LogLevel;
use Codad5\WPToolkit\Foundation\HookRegistrar;

/**
 * Mirrors records to the browser console — for developers, never visitors. Replaces 0.x's
 * Debugger, fixing its two bugs by design:
 *
 * - records are only buffered; nothing touches the script queue. The buffer is printed as one
 *   inline <script> in `wp_footer` / `admin_footer`, so "enqueued too early" notices can't happen;
 * - it prints only with WP_DEBUG on and only for users who can `manage_options` — 0.x printed
 *   traces and paths to every visitor.
 */
final class BrowserConsoleLogger extends BaseLogger
{
    /** @var list<array{level: string, message: string, context: array<string, mixed>}> */
    private array $buffer = [];

    private bool $hooked = false;

    /** @var Closure(): bool */
    private readonly Closure $mayShow;

    /**
     * @param (Closure(): bool)|null $mayShow Whether the current viewer may see the console output;
     *        default: WP_DEBUG is on and the user can manage_options.
     */
    public function __construct(
        private readonly string $channel,
        private readonly HookRegistrar $hooks,
        LogLevel $minimum = LogLevel::Debug,
        ?Closure $mayShow = null
    ) {
        parent::__construct($minimum);
        $this->mayShow = $mayShow ?? static fn (): bool => defined('WP_DEBUG') && constant('WP_DEBUG') && current_user_can('manage_options');
    }

    protected function write(LogLevel $level, string $message, array $context): void
    {
        $this->buffer[] = [
            'level' => $level->value,
            'message' => $message,
            'context' => array_map([BaseLogger::class, 'stringify'], $context),
        ];

        if (!$this->hooked) {
            $this->hooked = true;
            $this->hooks->addAction('wp_footer', [$this, 'flush'], 100);
            $this->hooks->addAction('admin_footer', [$this, 'flush'], 100);
        }
    }

    /**
     * @internal Hooked to the footers by write().
     */
    public function flush(): void
    {
        $records = $this->buffer;
        $this->buffer = [];

        if ($records === [] || !($this->mayShow)()) {
            return;
        }

        $lines = [];
        foreach ($records as $record) {
            $method = match ($record['level']) {
                'debug' => 'debug',
                'info', 'notice' => 'info',
                'warning' => 'warn',
                default => 'error',
            };
            // JSON_HEX_TAG etc. make the payload safe inside <script>, whatever the message contains.
            $lines[] = sprintf(
                'console.%s(%s, %s);',
                $method,
                (string) wp_json_encode('[' . $this->channel . '] ' . $record['message'], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT),
                (string) wp_json_encode($record['context'], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT)
            );
        }

        // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- every value is JSON-encoded with HTML-significant characters hex-escaped.
        echo "<script>\n" . implode("\n", $lines) . "\n</script>\n";
    }
}
