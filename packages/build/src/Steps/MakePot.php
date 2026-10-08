<?php

/**
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

namespace Codad5\WPToolkit\Build\Steps;

use Codad5\WPToolkit\Build\BuildContext;
use Codad5\WPToolkit\Build\BuildStep;
use Codad5\WPToolkit\Build\Phase;

/**
 * Generates `languages/{text-domain}.pot` in staging with WP-CLI's `i18n make-pot`. Uses the
 * project's vendor/bin/wp when present, else `wp` on the PATH.
 */
final class MakePot implements BuildStep
{
    public function __construct(private readonly ?string $domain = null, private readonly string $directory = 'languages')
    {
    }

    public function name(): string
    {
        return 'make-pot';
    }

    public function phase(): Phase
    {
        return Phase::Transform;
    }

    public function run(BuildContext $context): void
    {
        $domain = $this->domain ?? $context->project->header('Text Domain') ?? $context->project->slug;
        $wp = is_file($context->project->root . '/vendor/bin/wp')
            ? escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg($context->project->root . '/vendor/wp-cli/wp-cli/php/boot-fs.php')
            : 'wp';

        $context->exec($this->name(), sprintf(
            '%s i18n make-pot . %s --slug=%s --domain=%s --exclude=vendor,node_modules',
            $wp,
            escapeshellarg($this->directory . '/' . $domain . '.pot'),
            escapeshellarg($context->project->slug),
            escapeshellarg($domain)
        ), $context->staging);
    }
}
