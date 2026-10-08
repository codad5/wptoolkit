<?php

declare(strict_types=1);

namespace Codad5\WPToolkit\Tests\Unit\Build;

use Codad5\WPToolkit\Build\BuildException;
use Codad5\WPToolkit\Build\Project;
use PHPUnit\Framework\TestCase;

require_once dirname(__DIR__, 3) . '/bin/changelog.php';

/**
 * The scripts the release workflow runs: the changelog, the version guard and the library project
 * type the standalone zip is built with.
 */
final class ReleaseScriptsTest extends TestCase
{
    public function test_the_changelog_groups_conventional_commits_and_puts_breaking_changes_first(): void
    {
        $notes = \render_changelog([
            ['hash' => 'aaaaaaa1', 'subject' => 'feat(data): entities', 'body' => ''],
            ['hash' => 'bbbbbbb2', 'subject' => 'fix(http): pick the route by method', 'body' => ''],
            ['hash' => 'ccccccc3', 'subject' => 'refactor(legacy)!: delete the 0.x code', 'body' => "Every class is ported.\n\nBREAKING CHANGE: the 0.x classes are gone.\nUse 1.0's API."],
            ['hash' => 'ddddddd4', 'subject' => 'chore: bump deps', 'body' => ''],
            ['hash' => 'eeeeeee5', 'subject' => 'Merge branch next', 'body' => ''],
        ]);

        self::assertSame(
            "### Breaking changes\n\n- **legacy:** the 0.x classes are gone. Use 1.0's API.\n\n"
            . "### Features\n\n- **data:** entities (aaaaaaa)\n\n"
            . "### Fixes\n\n- **http:** pick the route by method (bbbbbbb)\n\n"
            . "### Refactoring\n\n- **legacy:** delete the 0.x code (ccccccc)\n",
            $notes
        );
        self::assertSame("No user-facing changes.\n", \render_changelog([['hash' => 'x', 'subject' => 'chore: x', 'body' => '']]));
    }

    public function test_the_release_build_refuses_a_version_that_is_not_the_tag(): void
    {
        $root = dirname(__DIR__, 3);
        exec(escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg($root . '/bin/build-release.php') . ' --expect=v99.0.0 2>&1', $output, $code);

        self::assertSame(1, $code);
        self::assertStringContainsString('the release is 99.0.0. Run: php bin/set-version.php 99.0.0', implode("\n", $output));
    }

    public function test_a_library_project_needs_its_version_file(): void
    {
        $project = Project::library(dirname(__DIR__, 3), 'wptoolkit', 'src/Foundation/Application.php');
        self::assertSame(['wptoolkit', 'library'], [$project->slug, $project->type]);

        $this->expectException(BuildException::class);
        Project::library(dirname(__DIR__, 3), 'wptoolkit', 'src/Missing.php');
    }
}
