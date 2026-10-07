<?php

declare(strict_types=1);

namespace Codad5\WPToolkit\Tests\Unit\View;

use PHPUnit\Framework\TestCase;

/**
 * Phase 5 DoD: a template that echoes data without escaping fails the project's PHPCS ruleset, and
 * one that prints through `$e` (or esc_*()) passes. Runs the real sniff so a ruleset change that
 * drops WordPress.Security.EscapeOutput is caught here.
 */
final class TemplateEscapingSniffTest extends TestCase
{
    private const ROOT = __DIR__ . '/../../..';

    public function test_a_raw_echo_in_a_template_fails_the_sniff(): void
    {
        [$exit, $output] = $this->phpcs('tests/Fixtures/phpcs/raw-echo.php');

        self::assertNotSame(0, $exit);
        self::assertStringContainsString('WordPress.Security.EscapeOutput', $output);
    }

    public function test_printing_through_the_escaper_passes(): void
    {
        [$exit, $output] = $this->phpcs('tests/Fixtures/phpcs/escaped.php');

        self::assertSame(0, $exit, $output);
    }

    /**
     * @return array{int, string}
     */
    private function phpcs(string $file): array
    {
        $command = sprintf(
            '%s %s --standard=%s --report=full -q -s %s 2>&1',
            escapeshellarg(PHP_BINARY),
            escapeshellarg(self::ROOT . '/vendor/squizlabs/php_codesniffer/bin/phpcs'),
            escapeshellarg(self::ROOT . '/phpcs.xml.dist'),
            escapeshellarg(self::ROOT . '/' . $file)
        );
        exec($command, $lines, $exit);

        return [$exit, implode("\n", $lines)];
    }
}
