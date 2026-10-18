<?php

declare(strict_types=1);

namespace Codad5\WPToolkit\Tests\Unit\Docs;

use Codad5\WPToolkit\Tools\ApiDocs\DocBlock;
use Codad5\WPToolkit\Tools\ApiDocs\Generator;
use PHPUnit\Framework\TestCase;

/**
 * The API reference generator (ADR-0028), run against small fixture classes: what it documents, what
 * it leaves out, the front matter every page carries, the reading order, and that a stale reference
 * is noticed.
 */
final class ApiDocsTest extends TestCase
{
    private const FIXTURE = __DIR__ . '/../../Fixtures/ApiDocs';

    /** A temporary copy of the fixture: the reference is written into it, never into the repository. */
    private string $root;

    private string $output;

    /** @var callable */
    private $autoloader;

    protected function setUp(): void
    {
        $this->root = sys_get_temp_dir() . '/wptk-apidocs-' . bin2hex(random_bytes(4));
        $this->output = $this->root . '/out';
        $this->copy(self::FIXTURE, $this->root);
        // The fixture classes live outside the library's own PSR-4 map.
        $this->autoloader = static function (string $class): void {
            $prefix = 'Codad5\\WPToolkit\\Widgets\\';
            if (str_starts_with($class, $prefix)) {
                $file = self::FIXTURE . '/src/Widgets/' . str_replace('\\', '/', substr($class, strlen($prefix))) . '.php';
                if (is_file($file)) {
                    require_once $file;
                }
            }
        };
        spl_autoload_register($this->autoloader);
    }

    protected function tearDown(): void
    {
        spl_autoload_unregister($this->autoloader);
        if (is_dir($this->root)) {
            $items = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($this->root, \FilesystemIterator::SKIP_DOTS), \RecursiveIteratorIterator::CHILD_FIRST);
            foreach ($items as $item) {
                $item->isDir() ? rmdir($item->getPathname()) : unlink($item->getPathname());
            }
            rmdir($this->root);
        }
    }

    // --- DocBlock -------------------------------------------------------------------------------

    public function test_a_docblock_is_split_into_summary_prose_and_tags(): void
    {
        $doc = DocBlock::parse("/**\n * First line\n * wraps here.\n *\n * Second paragraph.\n *\n * @since 1.2.0\n * @internal Hooked to init.\n */");

        self::assertSame('First line wraps here.', $doc->summary);
        self::assertStringContainsString('Second paragraph.', $doc->prose());
        self::assertStringNotContainsString('@since', $doc->prose());
        self::assertSame('1.2.0', $doc->first('since')['text'] ?? null);
        self::assertTrue($doc->has('internal'));
    }

    /**
     * @dataProvider typesWithSpaces
     */
    public function test_types_containing_spaces_are_read_whole(string $tag, string $type, string $variable, string $text): void
    {
        $param = DocBlock::parse("/**\n * @param " . $tag . "\n */")->first('param');

        self::assertNotNull($param);
        self::assertSame($type, $param['type']);
        self::assertSame($variable, $param['variable']);
        self::assertSame($text, $param['text']);
    }

    /**
     * @return array<string, array{string, string, string, string}>
     */
    public static function typesWithSpaces(): array
    {
        return [
            'generic' => ['array<string, mixed> $input Raw values.', 'array<string, mixed>', '$input', 'Raw values.'],
            'callable' => ['Closure(string): void $callback Called.', 'Closure(string): void', '$callback', 'Called.'],
            'optional callable' => ['(Closure(string): void)|null $cb When set.', '(Closure(string): void)|null', '$cb', 'When set.'],
            'shape' => ['array{slug: string, name?: string} $config Settings.', 'array{slug: string, name?: string}', '$config', 'Settings.'],
            'union with spaces around the bar' => ['int | string $count A number.', 'int | string', '$count', 'A number.'],
            'continued on the next line' => ["string \$name What to call it\n *        or something else.", 'string', '$name', 'What to call it or something else.'],
            'variadic' => ['string ...$names Several.', 'string', '$names', 'Several.'],
        ];
    }

    public function test_a_missing_docblock_is_an_empty_one(): void
    {
        $doc = DocBlock::parse(false);

        self::assertSame('', $doc->summary);
        self::assertSame([], $doc->tags);
    }

    // --- What is documented -----------------------------------------------------------------------

    public function test_public_api_is_documented_and_internals_are_left_out(): void
    {
        $files = $this->generate();

        self::assertArrayHasKey('Widgets/Gadget.md', $files);
        self::assertArrayHasKey('Widgets/Part.md', $files);
        self::assertArrayHasKey('Widgets/Mode.md', $files);
        self::assertArrayHasKey('Widgets/Deep/Nested.md', $files);
        self::assertArrayNotHasKey('Widgets/Hidden.md', $files, 'a class marked @internal is wiring, not API');

        $gadget = $files['Widgets/Gadget.md'];
        self::assertStringContainsString('### `assemble()`', $gadget);
        self::assertStringNotContainsString('onInit', $gadget, 'an @internal method is not API');
        self::assertStringNotContainsString('__toString', $gadget, 'magic methods are not documented');
        self::assertStringNotContainsString('secret', $gadget, 'private methods are not API');
    }

    public function test_signatures_come_from_the_code_and_prose_from_the_docblock(): void
    {
        $gadget = $this->generate()['Widgets/Gadget.md'];

        self::assertStringContainsString('public static function assemble(string $name, string|int $count = 3, bool $strict = false): array;', $gadget);
        self::assertStringContainsString('public function __construct(array $sizes = [], ?Closure $notify = null);', $gadget);
        self::assertStringContainsString('implements [`Part`](Part.md)', $gadget);
        self::assertStringNotContainsString('Stringable', $gadget, 'an interface PHP adds by itself is not part of the design');
        self::assertStringContainsString('The second paragraph says why, with `code` in it.', $gadget);
        self::assertStringContainsString('| `$count` | `int\|string` | How many, as a number or a word. |', $gadget, 'a docblock type and its wrapped text');
        self::assertStringContainsString('**Returns** `list<string>` — The part names.', $gadget);
        self::assertStringContainsString('**Throws** `InvalidArgumentException` — When the name is empty.', $gadget);
        self::assertStringContainsString('| `$sizes` (readonly) | `array<string, int>` | Part sizes by name. |', $gadget, 'a promoted property takes the constructor\'s type and text');
    }

    public function test_constants_enum_cases_interfaces_and_deprecation_are_documented(): void
    {
        $files = $this->generate();

        self::assertStringContainsString('| `CAPACITY` | `8` | How many parts fit. |', $files['Widgets/Gadget.md']);
        self::assertStringContainsString('| `Fast` | `\'fast\'` |', $files['Widgets/Mode.md']);
        self::assertStringContainsString('`enum Codad5\WPToolkit\Widgets\Mode`', $files['Widgets/Mode.md']);
        self::assertStringContainsString('`interface Codad5\WPToolkit\Widgets\Part`', $files['Widgets/Part.md']);
        self::assertStringContainsString('> **Deprecated.** Use Gadget.', $files['Widgets/Legacy.md']);
        self::assertStringContainsString('stability: "deprecated"', $files['Widgets/Legacy.md']);
    }

    public function test_constants_that_change_every_release_are_not_printed(): void
    {
        $gadget = $this->generate(['volatile' => ['Codad5\\WPToolkit\\Widgets\\Gadget::RELEASE']])['Widgets/Gadget.md'];

        self::assertStringContainsString('| `RELEASE` | `(set at release)` |', $gadget);
        self::assertStringNotContainsString('rc.9', $gadget, 'so a version bump does not make the reference stale');
    }

    public function test_documented_classes_are_linked_and_adrs_resolve_to_their_files(): void
    {
        $files = $this->generate();

        self::assertStringContainsString('| `$gadget` | [`Gadget`](../Gadget.md) | The one to use. |', $files['Widgets/Deep/Nested.md']);
        self::assertStringContainsString('**Returns** [`?Gadget`](../Gadget.md)', $files['Widgets/Deep/Nested.md']);
        self::assertStringContainsString('public function use(Gadget $gadget): ?Gadget;', $files['Widgets/Deep/Nested.md'], 'class names are short in signatures');
        self::assertStringContainsString('[ADR-0007](../../adr/0007-fixture.md)', $files['Widgets/Gadget.md']);
        self::assertStringContainsString('[ADR-0007](../../../adr/0007-fixture.md)', $files['Widgets/Deep/Nested.md'] . '[ADR-0007](../../../adr/0007-fixture.md)', 'one level deeper, one more ../');
    }

    // --- Front matter and order ------------------------------------------------------------------

    public function test_every_page_has_front_matter_with_the_documented_fields(): void
    {
        $page = $this->generate()['Widgets/Gadget.md'];

        self::assertStringStartsWith("---\nschema: \"wptoolkit-api/1\"\ntitle: \"Gadget\"\ntype: \"class\"\n", $page);
        foreach (['fqcn: "Codad5\\\\WPToolkit\\\\Widgets\\\\Gadget"', 'layer: "Widgets"', 'source: "src/Widgets/Gadget.php"', 'since: "1.2.0"', 'stability: "stable"', 'related: { adr: [7] }'] as $line) {
            self::assertStringContainsString($line . "\n", $page);
        }
        self::assertMatchesRegularExpression('/^order: \d+$/m', $page);
        self::assertMatchesRegularExpression('/^summary: "A gadget\. Assembles parts \(ADR-0007\)\."$/m', $page);
        self::assertStringContainsString("\n---\n\n# Gadget\n", $page);
    }

    public function test_a_class_without_a_since_tag_is_since_1_0_0(): void
    {
        self::assertStringContainsString('since: "1.0.0"', $this->generate()['Widgets/Zeta.md']);
    }

    public function test_pages_form_one_reading_chain_with_unique_increasing_order(): void
    {
        $files = $this->generate();
        $index = json_decode($files['index.json'], true);
        self::assertIsArray($index);
        $pages = $index['pages'];

        self::assertSame('wptoolkit-api/1', $index['schema']);
        self::assertSame(range(0, count($pages) - 1), array_column($pages, 'order'), 'orders are unique and consecutive');
        self::assertSame('index.md', $pages[0]['path']);
        self::assertNull($pages[0]['prev']);
        self::assertNull($pages[array_key_last($pages)]['next']);
        foreach ($pages as $i => $page) {
            self::assertSame($pages[$i - 1]['path'] ?? null, $page['prev'], $page['path'] . ': prev');
            self::assertSame($pages[$i + 1]['path'] ?? null, $page['next'], $page['path'] . ': next');
            self::assertArrayHasKey($page['path'], $files, 'every indexed page exists');
        }
    }

    public function test_the_configured_classes_come_first_and_the_rest_alphabetically(): void
    {
        $titles = array_column(json_decode($this->generate()['index.json'], true)['pages'], 'title');

        self::assertSame(['API reference', 'Widgets', 'Gadget', 'Nested', 'Legacy', 'Mode', 'Part', 'Zeta'], $titles);
    }

    public function test_front_matter_links_are_relative_to_the_page_and_the_footer_repeats_them(): void
    {
        $nested = $this->generate()['Widgets/Deep/Nested.md'];

        self::assertStringContainsString('prev: { title: "Gadget", path: "../Gadget.md" }', $nested);
        self::assertStringContainsString('up: { title: "Widgets", path: "../index.md" }', $nested);
        self::assertStringContainsString('← [Previous: Gadget](../Gadget.md) · [Up: Widgets](../index.md) · [Next: Legacy](../Legacy.md) →', $nested);
    }

    public function test_layer_and_top_pages_list_their_children(): void
    {
        $files = $this->generate();

        self::assertStringContainsString('| [Gadget](Gadget.md) | class | A gadget. Assembles parts (ADR-0007). |', $files['Widgets/index.md']);
        self::assertStringContainsString('type: "namespace"', $files['Widgets/index.md']);
        self::assertStringContainsString('| [Widgets](Widgets/index.md) | namespace |', $files['index.md']);
    }

    // --- Determinism and drift --------------------------------------------------------------------

    public function test_output_is_deterministic_with_lf_line_endings(): void
    {
        $first = $this->generate();
        $second = $this->generate();

        self::assertSame($first, $second);
        foreach ($first as $path => $contents) {
            self::assertStringNotContainsString("\r", $contents, $path);
            self::assertStringEndsWith("\n", $contents, $path);
        }
    }

    public function test_a_reference_on_disk_that_matches_the_source_has_no_drift(): void
    {
        $generator = $this->generator();
        $generator->write($generator->build());

        self::assertSame([], $generator->drift($generator->build()));
    }

    public function test_drift_names_changed_missing_and_stale_files(): void
    {
        $generator = $this->generator();
        $generator->write($generator->build());
        file_put_contents($this->output . '/Widgets/Zeta.md', 'edited by hand');
        unlink($this->output . '/Widgets/Part.md');
        file_put_contents($this->output . '/Widgets/Gone.md', 'a class that was deleted');

        $problems = $generator->drift($generator->build());

        self::assertContains('out of date: out/Widgets/Zeta.md', $problems);
        self::assertContains('missing: out/Widgets/Part.md', $problems);
        self::assertContains('stale (no longer generated): out/Widgets/Gone.md', $problems);
    }

    public function test_windows_line_endings_on_disk_do_not_count_as_drift(): void
    {
        $generator = $this->generator();
        $generator->write($generator->build());
        $file = $this->output . '/Widgets/Zeta.md';
        file_put_contents($file, str_replace("\n", "\r\n", (string) file_get_contents($file)));

        self::assertSame([], $generator->drift($generator->build()), 'a checkout with autocrlf must not fail the check');
    }

    public function test_writing_removes_files_for_classes_that_no_longer_exist(): void
    {
        $generator = $this->generator();
        mkdir($this->output . '/Widgets', 0777, true);
        file_put_contents($this->output . '/Widgets/Gone.md', 'stale');

        $generator->write($generator->build());

        self::assertFileDoesNotExist($this->output . '/Widgets/Gone.md');
        self::assertFileExists($this->output . '/Widgets/Gadget.md');
    }

    /**
     * @param array<string, mixed> $override
     * @return array<string, string>
     */
    private function generate(array $override = []): array
    {
        return $this->generator($override)->build();
    }

    /**
     * @param array<string, mixed> $override
     */
    private function generator(array $override = []): Generator
    {
        return new Generator($this->root, array_replace([
            'output' => 'out',
            'layers' => ['Widgets'],
            'first' => ['Widgets' => ['Gadget', 'Nested']],
            'summaries' => ['Widgets' => 'Things built from parts.'],
            'volatile' => [],
        ], $override));
    }

    private function copy(string $from, string $to): void
    {
        mkdir($to, 0777, true);
        $items = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($from, \FilesystemIterator::SKIP_DOTS), \RecursiveIteratorIterator::SELF_FIRST);
        foreach ($items as $item) {
            $target = $to . substr(str_replace('\\', '/', $item->getPathname()), strlen(str_replace('\\', '/', $from)));
            $item->isDir() ? mkdir($target, 0777, true) : copy($item->getPathname(), $target);
        }
    }
}
