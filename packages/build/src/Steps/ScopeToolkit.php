<?php

/**
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

namespace Codad5\WPToolkit\Build\Steps;

use Codad5\WPToolkit\Build\BuildContext;
use Codad5\WPToolkit\Build\BuildException;
use Codad5\WPToolkit\Build\BuildStep;
use Codad5\WPToolkit\Build\Phase;

/**
 * Scopes the bundled WPToolkit copy in staging with its own bin/scope.php (ADR-0005, ADR-0014).
 */
final class ScopeToolkit implements BuildStep
{
    private const CANDIDATES = ['vendor/codad5/wptoolkit', 'lib/wptoolkit', 'wptoolkit'];

    public function __construct(private readonly string $namespace, private readonly ?string $toolkitPath = null)
    {
    }

    public function name(): string
    {
        return 'scope';
    }

    public function phase(): Phase
    {
        return Phase::Transform;
    }

    public function run(BuildContext $context): void
    {
        $path = $this->locate($context);
        $scoper = $context->staged($path . '/bin/scope.php');
        if (!is_file($scoper)) {
            throw BuildException::inStep($this->name(), sprintf('%s/bin/scope.php not found in the staged copy.', $path));
        }

        $context->exec(
            $this->name(),
            escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg($scoper) . ' ' . escapeshellarg($this->namespace) . ' ' . escapeshellarg($context->staged($path)),
            $context->staging
        );
    }

    private function locate(BuildContext $context): string
    {
        if ($this->toolkitPath !== null) {
            return trim($this->toolkitPath, '/');
        }

        foreach (self::CANDIDATES as $candidate) {
            if (is_file($context->staged($candidate . '/src/Foundation/Application.php'))) {
                return $candidate;
            }
        }

        throw BuildException::inStep(
            $this->name(),
            'No bundled WPToolkit found (looked in ' . implode(', ', self::CANDIDATES) . '). Pass its path: scope($namespace, $path).'
        );
    }
}
