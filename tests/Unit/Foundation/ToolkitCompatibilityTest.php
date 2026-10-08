<?php

declare(strict_types=1);

namespace Codad5\WPToolkit\Tests\Unit\Foundation;

use Codad5\WPToolkit\Foundation\Application;
use Codad5\WPToolkit\Foundation\Config;
use Codad5\WPToolkit\Foundation\ToolkitCompatibility;
use Codad5\WPToolkit\Tests\TestCase;

final class ToolkitCompatibilityTest extends TestCase
{
    public function test_no_constraint_is_always_compatible(): void
    {
        self::assertTrue($this->check([])->isCompatible());
    }

    public function test_the_problem_names_both_versions(): void
    {
        $problem = $this->check(['name' => 'Beta Shop', 'requires_toolkit' => '^9.0'])->problem();

        self::assertStringContainsString('Beta Shop needs WPToolkit ^9.0', $problem);
        self::assertStringContainsString('WPToolkit ' . Application::VERSION . ' was already loaded', $problem);
    }

    public function test_options_never_suggest_activating_in_a_different_order(): void
    {
        $options = implode(' ', $this->check(['name' => 'Beta Shop', 'requires_toolkit' => '^9.0'])->options());

        self::assertStringContainsString('Deactivate', $options);
        self::assertStringContainsString('built for WPToolkit 1.x', $options);
        self::assertStringContainsString('scoped copy', $options);
        self::assertStringNotContainsString('first', $options);
    }

    public function test_html_escapes_names_and_lists_the_options(): void
    {
        $html = $this->check(['name' => '<script>x</script>', 'requires_toolkit' => '^9.0'])->html();

        self::assertStringNotContainsString('<script>', $html);
        self::assertStringContainsString('&lt;script&gt;', $html);
        self::assertSame(3, substr_count($html, '<li>'));
    }

    /**
     * @param array<string, mixed> $values
     */
    private function check(array $values): ToolkitCompatibility
    {
        return new ToolkitCompatibility(Config::fromArray('/p/p.php', ['slug' => 'beta'] + $values));
    }
}
