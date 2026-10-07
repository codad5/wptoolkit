<?php

declare(strict_types=1);

namespace Codad5\WPToolkit\Tests\Unit\Build;

use Codad5\WPToolkit\Build\Build;
use Codad5\WPToolkit\Build\Doctor;
use Codad5\WPToolkit\Build\Project;
use Codad5\WPToolkit\Build\ZipVerifier;
use PHPUnit\Framework\TestCase;
use ZipArchive;

final class DoctorAndVerifierTest extends TestCase
{
    private string $tmp;

    protected function setUp(): void
    {
        $this->tmp = sys_get_temp_dir() . '/wptoolkit-doctor-' . uniqid();
        mkdir($this->tmp . '/shop', 0777, true);
    }

    protected function tearDown(): void
    {
        foreach (new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($this->tmp, \FilesystemIterator::SKIP_DOTS), \RecursiveIteratorIterator::CHILD_FIRST) as $item) {
            $item->isDir() ? rmdir($item->getPathname()) : unlink($item->getPathname());
        }
        rmdir($this->tmp);
    }

    public function test_doctor_finds_disagreeing_versions_and_php_requirements(): void
    {
        $this->plugin("<?php\n/*\n * Plugin Name: Shop\n * Version: 1.0.0\n * Requires PHP: 8.0\n */\ncall_user_func(require __DIR__ . '/vendor/codad5/wptoolkit/bootstrap/guard.php', __FILE__, array('php' => '8.1'), function () {});\n");
        file_put_contents($this->tmp . '/shop/package.json', '{"version": "1.1.0"}');
        file_put_contents($this->tmp . '/shop/readme.txt', "Stable tag: 1.0.0\n");

        $messages = array_column(Doctor::examine(Project::plugin($this->tmp . '/shop')), 'message');

        self::assertCount(1, array_filter($messages, static fn ($m) => str_contains($m, 'Versions disagree: header = 1.0.0, package.json = 1.1.0, readme.txt = 1.0.0')));
        self::assertCount(1, array_filter($messages, static fn ($m) => str_contains($m, 'Requires PHP: 8.0') && str_contains($m, "'php' => '8.1'")));
    }

    public function test_doctor_warns_about_dev_dependencies_and_a_missing_guard(): void
    {
        $this->plugin("<?php\n/*\n * Plugin Name: Shop\n * Version: 1.0.0\n */\n");
        mkdir($this->tmp . '/shop/vendor/codad5/wptoolkit', 0777, true);
        mkdir($this->tmp . '/shop/vendor/phpunit', 0777, true);

        $findings = Doctor::examine(Project::plugin($this->tmp . '/shop'));
        $messages = implode("\n", array_column($findings, 'message'));

        self::assertStringContainsString('does not start through bootstrap/guard.php', $messages);
        self::assertStringContainsString('vendor/phpunit is installed', $messages);
        self::assertSame(['warning'], array_values(array_unique(array_column($findings, 'level'))));
    }

    public function test_verify_flags_the_problems_found_in_real_zips(): void
    {
        $zip = $this->tmp . '/old.zip';
        $archive = new ZipArchive();
        $archive->open($zip, ZipArchive::CREATE);
        // The theme zip shape: files at the root (no theme folder), agent config included.
        $archive->addFromString('style.css', '/* Theme Name: X */');
        $archive->addFromString('.claude/skills/a.md', 'x');
        $archive->addFromString('vendor/phpunit/phpunit/x.php', '<?php');
        $archive->close();

        $problems = implode("\n", ZipVerifier::verify($zip));

        self::assertStringContainsString('Expected one top-level folder', $problems);
        self::assertStringContainsString('.claude/skills/a.md', $problems);
    }

    public function test_verify_passes_a_zip_built_by_the_tool(): void
    {
        $this->plugin("<?php\n/*\n * Plugin Name: Shop\n * Version: 1.0.0\n */\n");

        $report = Build::plugin($this->tmp . '/shop')->withOutput(static function () {
        })->zip();

        self::assertSame([], ZipVerifier::verify($report->zip));
    }

    public function test_dry_run_lists_what_would_ship_without_writing_a_zip(): void
    {
        $this->plugin("<?php\n/*\n * Plugin Name: Shop\n * Version: 1.0.0\n */\n");
        mkdir($this->tmp . '/shop/node_modules');
        file_put_contents($this->tmp . '/shop/node_modules/x.js', '');
        file_put_contents($this->tmp . '/shop/README.md', '');

        $files = Build::plugin($this->tmp . '/shop')->dryRun();

        self::assertSame(['shop.php'], $files);
        self::assertDirectoryDoesNotExist($this->tmp . '/shop/dist');
    }

    private function plugin(string $main): void
    {
        file_put_contents($this->tmp . '/shop/shop.php', $main);
    }
}
