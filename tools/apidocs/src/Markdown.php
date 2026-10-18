<?php

/**
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

namespace Codad5\WPToolkit\Tools\ApiDocs;

use Closure;

/**
 * Turns a page's model into Markdown with YAML front matter (schema `wptoolkit-api/1`, ADR-0028).
 *
 * The front matter is what tools read; the footer repeats previous / up / next as ordinary links so a
 * person reading on GitHub can follow the same chain. Output is deterministic: no dates, LF endings,
 * a fixed field order.
 *
 * @phpstan-type Link array{title: string, path: string}
 * @phpstan-type Page array{
 *     type: string,
 *     path: string,
 *     title: string,
 *     order: int,
 *     layer: string,
 *     fqcn: string,
 *     namespace: string,
 *     source: string,
 *     since: string,
 *     stability: string,
 *     summary: string,
 *     adrs: list<int>,
 *     prev: Link|null,
 *     next: Link|null,
 *     up: Link|null,
 *     doc: array<string, mixed>|null,
 *     children: list<array{title: string, path: string, kind: string, summary: string}>,
 *     intro: string
 * }
 */
final class Markdown
{
    public const SCHEMA = 'wptoolkit-api/1';

    /**
     * @param Closure(string, string): (string|null) $classLink (name, fromDirectory) => relative path of a documented class.
     * @param Closure(int, string): (string|null) $adrLink (number, fromDirectory) => relative path of an ADR.
     */
    public function __construct(private readonly Closure $classLink, private readonly Closure $adrLink)
    {
    }

    /**
     * @param Page $page
     */
    public function render(array $page): string
    {
        $directory = dirname($page['path']) === '.' ? '' : dirname($page['path']);
        $out = $this->frontMatter($page) . "\n";

        $out .= match ($page['type']) {
            'class' => $this->classBody($page, $directory),
            default => $this->indexBody($page, $directory),
        };

        return rtrim($out) . "\n\n" . $this->footer($page) . "\n";
    }

    /**
     * @param Page $page
     */
    private function frontMatter(array $page): string
    {
        $fields = [
            'schema' => self::string(self::SCHEMA),
            'title' => self::string($page['title']),
            'type' => self::string($page['type'] === 'class' ? (string) ($page['doc']['kind'] ?? 'class') : $page['type']),
        ];
        if ($page['fqcn'] !== '') {
            $fields['fqcn'] = self::string($page['fqcn']);
        }
        if ($page['namespace'] !== '') {
            $fields['namespace'] = self::string($page['namespace']);
        }
        $fields['layer'] = self::string($page['layer']);
        if ($page['source'] !== '') {
            $fields['source'] = self::string($page['source']);
        }
        if ($page['type'] === 'class') {
            $fields['since'] = self::string($page['since']);
            $fields['stability'] = self::string($page['stability']);
        }
        $fields['order'] = (string) $page['order'];
        $fields['summary'] = self::string($page['summary']);
        if ($page['adrs'] !== []) {
            $fields['related'] = '{ adr: [' . implode(', ', $page['adrs']) . '] }';
        }
        foreach (['prev', 'next', 'up'] as $link) {
            if ($page[$link] !== null) {
                $fields[$link] = '{ title: ' . self::string($page[$link]['title']) . ', path: ' . self::string($page[$link]['path']) . ' }';
            }
        }

        $lines = ['---'];
        foreach ($fields as $name => $value) {
            $lines[] = $name . ': ' . $value;
        }
        $lines[] = '---';

        return implode("\n", $lines) . "\n";
    }

    /**
     * @param Page $page
     */
    private function classBody(array $page, string $directory): string
    {
        $doc = $page['doc'] ?? [];
        $out = '# ' . $page['title'] . "\n\n";
        $out .= '`' . $doc['declaration'] . ' ' . $doc['fqcn'] . '`' . $this->heritage($doc, $directory) . "\n\n";

        if (($doc['deprecated'] ?? null) !== null) {
            $out .= '> **Deprecated.** ' . $doc['deprecated'] . "\n\n";
        }
        if ($doc['prose'] !== '') {
            $out .= $doc['prose'] . "\n\n";
        }

        $facts = ['Source `' . $page['source'] . '`', 'Since ' . $page['since']];
        $decisions = [];
        foreach ($page['adrs'] as $number) {
            $path = ($this->adrLink)($number, $directory);
            $label = sprintf('ADR-%04d', $number);
            $decisions[] = $path === null ? $label : '[' . $label . '](' . $path . ')';
        }
        if ($decisions !== []) {
            $facts[] = 'Decisions: ' . implode(', ', $decisions);
        }
        $out .= implode(' · ', $facts) . "\n\n";

        if ($doc['cases'] !== []) {
            $out .= "## Cases\n\n| Case | Value |\n| --- | --- |\n";
            foreach ($doc['cases'] as $case) {
                $out .= '| `' . $case['name'] . '` | ' . ($case['value'] === '' ? '' : '`' . self::cell($case['value']) . '`') . " |\n";
            }
            $out .= "\n";
        }
        if ($doc['constants'] !== []) {
            $out .= "## Constants\n\n| Constant | Value | Description |\n| --- | --- | --- |\n";
            foreach ($doc['constants'] as $constant) {
                $out .= '| `' . $constant['name'] . '` | `' . self::cell($constant['value']) . '` | ' . self::cell($constant['summary']) . " |\n";
            }
            $out .= "\n";
        }
        if ($doc['properties'] !== []) {
            $out .= "## Properties\n\n| Property | Type | Description |\n| --- | --- | --- |\n";
            foreach ($doc['properties'] as $property) {
                $out .= '| `$' . $property['name'] . '`' . ($property['readonly'] ? ' (readonly)' : '') . ' | '
                    . $this->type($property['type'], $directory) . ' | ' . self::cell($property['summary']) . " |\n";
            }
            $out .= "\n";
        }
        if ($doc['methods'] !== []) {
            $out .= "## Methods\n\n";
            foreach ($doc['methods'] as $method) {
                $out .= $this->method($method, $directory);
            }
        }

        return $out;
    }

    /**
     * @param array<string, mixed> $method
     */
    private function method(array $method, string $directory): string
    {
        $out = '### `' . $method['name'] . "()`\n\n```php\n" . $method['signature'] . ";\n```\n\n";
        if ($method['deprecated'] !== null) {
            $out .= '> **Deprecated.** ' . $method['deprecated'] . "\n\n";
        }
        if ($method['prose'] !== '') {
            $out .= $method['prose'] . "\n\n";
        }

        if ($method['params'] !== []) {
            $out .= "| Parameter | Type | Description |\n| --- | --- | --- |\n";
            foreach ($method['params'] as $param) {
                $out .= '| `' . $param['name'] . '` | ' . $this->type($param['type'], $directory) . ' | ' . self::cell($param['text']) . " |\n";
            }
            $out .= "\n";
        }
        if ($method['returns'] !== null) {
            $out .= '**Returns** ' . $this->type($method['returns']['type'], $directory)
                . ($method['returns']['text'] !== '' ? ' — ' . $method['returns']['text'] : '') . "\n\n";
        }
        foreach ($method['throws'] as $throws) {
            $out .= '**Throws** ' . $this->type($throws['type'], $directory) . ($throws['text'] !== '' ? ' — ' . $throws['text'] : '') . "\n\n";
        }

        return $out;
    }

    /**
     * @param array<string, mixed> $doc
     */
    private function heritage(array $doc, string $directory): string
    {
        $parts = [];
        if ($doc['extends'] !== null) {
            $parts[] = 'extends ' . $this->name($doc['extends'], $directory, $doc['short']);
        }
        if ($doc['implements'] !== []) {
            $parts[] = 'implements ' . implode(', ', array_map(fn (string $name): string => $this->name($name, $directory, $doc['short']), $doc['implements']));
        }

        return $parts === [] ? '' : ' — ' . implode(', ', $parts);
    }

    /**
     * @param Page $page
     */
    private function indexBody(array $page, string $directory): string
    {
        $out = '# ' . $page['title'] . "\n\n" . $page['intro'] . "\n\n";
        $out .= "| Page | Kind | Description |\n| --- | --- | --- |\n";
        foreach ($page['children'] as $child) {
            $out .= '| [' . $child['title'] . '](' . $child['path'] . ') | ' . $child['kind'] . ' | ' . self::cell($child['summary']) . " |\n";
        }

        return $out;
    }

    /**
     * @param Page $page
     */
    private function footer(array $page): string
    {
        $parts = [];
        if ($page['prev'] !== null) {
            $parts[] = '← [Previous: ' . $page['prev']['title'] . '](' . $page['prev']['path'] . ')';
        }
        if ($page['up'] !== null) {
            $parts[] = '[Up: ' . $page['up']['title'] . '](' . $page['up']['path'] . ')';
        }
        if ($page['next'] !== null) {
            $parts[] = '[Next: ' . $page['next']['title'] . '](' . $page['next']['path'] . ') →';
        }

        return "---\n\n" . implode(' · ', $parts);
    }

    /**
     * A type for a table cell: a documented class becomes a link, anything else stays code.
     */
    private function type(string $type, string $directory): string
    {
        $bare = ltrim($type, '?');
        $path = ($this->classLink)($bare, $directory);

        return $path === null ? '`' . self::cell($type) . '`' : '[`' . self::cell($type) . '`](' . $path . ')';
    }

    /**
     * @param string $own The short name of the class being described: a parent or interface with the
     *        same name (`Foundation\Container` implements `Contracts\Container`) is shown in full.
     */
    private function name(string $name, string $directory, string $own = ''): string
    {
        $path = ($this->classLink)($name, $directory);
        $short = str_contains($name, '\\') ? substr($name, (int) strrpos($name, '\\') + 1) : $name;
        if ($short === $own) {
            $short = $name;
        }

        return $path === null ? '`' . $short . '`' : '[`' . $short . '`](' . $path . ')';
    }

    private static function string(string $value): string
    {
        return (string) json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }

    /**
     * Safe inside a table cell: no pipes, no line breaks.
     */
    private static function cell(string $text): string
    {
        return trim((string) preg_replace('/\s+/', ' ', str_replace('|', '\\|', $text)));
    }
}
