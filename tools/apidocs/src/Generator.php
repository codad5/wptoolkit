<?php

/**
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

namespace Codad5\WPToolkit\Tools\ApiDocs;

use FilesystemIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use ReflectionClass;

/**
 * Builds the API reference (`docs/api/`) from `src/` (ADR-0028): which classes, in what reading
 * order, linked to their neighbours, plus a machine-readable index.
 *
 * @phpstan-import-type ClassDoc from ClassModel
 * @phpstan-import-type Page from Markdown
 * @phpstan-type Config array{
 *     output: string,
 *     layers: list<string>,
 *     first: array<string, list<string>>,
 *     summaries: array<string, string>,
 *     volatile: list<string>
 * }
 */
final class Generator
{
    private const ROOT_NAMESPACE = 'Codad5\\WPToolkit\\';

    /**
     * @param Config $config
     */
    public function __construct(private readonly string $root, private readonly array $config)
    {
    }

    /**
     * @param string $root The repository root.
     */
    public static function fromConfigFile(string $root): self
    {
        $json = json_decode((string) file_get_contents($root . '/tools/apidocs/config.json'), true);
        if (!is_array($json) || !isset($json['layers'], $json['output'])) {
            throw new \RuntimeException('tools/apidocs/config.json is missing or invalid.');
        }

        return new self($root, [
            'output' => (string) $json['output'],
            'layers' => array_values(array_map('strval', (array) $json['layers'])),
            'first' => is_array($json['first'] ?? null) ? $json['first'] : [],
            'summaries' => is_array($json['summaries'] ?? null) ? $json['summaries'] : [],
            'volatile' => array_values(array_map('strval', (array) ($json['volatile'] ?? []))),
        ]);
    }

    /**
     * Every file the reference consists of: path (relative to the output directory) => contents.
     *
     * @return array<string, string>
     */
    public function build(): array
    {
        $pages = $this->pages();
        $paths = [];
        foreach ($pages as $page) {
            if ($page['fqcn'] !== '') {
                $paths[$page['fqcn']] = $page['path'];
            }
        }
        $shortNames = $this->uniqueShortNames($paths);

        $markdown = new Markdown(
            fn (string $name, string $from): ?string => $this->link($paths, $shortNames, $name, $from),
            fn (int $number, string $from): ?string => $this->adrPath($number, $from)
        );

        $files = [];
        $index = [];
        foreach ($pages as $position => $page) {
            $files[$page['path']] = $markdown->render($page);
            $up = match ($page['type']) {
                'class' => $page['layer'] . '/index.md',
                'namespace' => $pages[0]['path'],
                default => null,
            };
            $index[] = [
                'path' => $page['path'],
                'title' => $page['title'],
                'type' => $page['type'] === 'class' ? (string) ($page['doc']['kind'] ?? 'class') : $page['type'],
                'fqcn' => $page['fqcn'] === '' ? null : $page['fqcn'],
                'layer' => $page['layer'],
                'order' => $page['order'],
                'since' => $page['type'] === 'class' ? $page['since'] : null,
                'summary' => $page['summary'],
                'prev' => $pages[$position - 1]['path'] ?? null,
                'next' => $pages[$position + 1]['path'] ?? null,
                'up' => $up,
            ];
        }
        $files['index.json'] = json_encode(
            ['schema' => Markdown::SCHEMA, 'pages' => $index],
            JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
        ) . "\n";

        ksort($files, SORT_STRING);

        return $files;
    }

    /**
     * What differs between the reference on disk and what the source says it should be.
     *
     * @param array<string, string> $files
     * @return list<string> One line per problem; empty when the reference is current.
     */
    public function drift(array $files): array
    {
        $problems = [];
        $output = $this->outputDirectory();

        foreach ($files as $path => $contents) {
            $file = $output . '/' . $path;
            if (!is_file($file)) {
                $problems[] = 'missing: ' . $this->config['output'] . '/' . $path;
            } elseif (str_replace("\r\n", "\n", (string) file_get_contents($file)) !== $contents) {
                $problems[] = 'out of date: ' . $this->config['output'] . '/' . $path;
            }
        }
        foreach ($this->existing() as $path) {
            if (!isset($files[$path])) {
                $problems[] = 'stale (no longer generated): ' . $this->config['output'] . '/' . $path;
            }
        }

        return $problems;
    }

    /**
     * @param array<string, string> $files
     */
    public function write(array $files): void
    {
        $output = $this->outputDirectory();
        foreach ($this->existing() as $path) {
            if (!isset($files[$path])) {
                unlink($output . '/' . $path);
            }
        }
        foreach ($files as $path => $contents) {
            $file = $output . '/' . $path;
            if (!is_dir(dirname($file)) && !mkdir(dirname($file), 0777, true) && !is_dir(dirname($file))) {
                throw new \RuntimeException('Cannot create ' . dirname($file));
            }
            file_put_contents($file, $contents);
        }
    }

    /**
     * All pages in reading order, with their neighbours.
     *
     * @return list<Page>
     */
    private function pages(): array
    {
        $top = $this->page('index', 'API reference', 'index.md', '', '');
        $top['summary'] = 'Every public class of WPToolkit, in the order to read them.';
        $top['intro'] = 'Generated from the source by `composer docs`; do not edit these files. Each page lists a class\'s public API '
            . 'with the reasoning from its docblocks. Pages carry machine-readable front matter (schema `' . Markdown::SCHEMA
            . '`, [ADR-0028](../adr/0028-the-api-reference-is-generated-from-the-source.md)) and `index.json` lists them all in reading order.';

        $chain = [$top];
        foreach ($this->config['layers'] as $layer) {
            $classes = $this->classes($layer);
            $index = $this->page('namespace', $layer, $layer . '/index.md', $layer, self::ROOT_NAMESPACE . $layer);
            $index['summary'] = $this->config['summaries'][$layer] ?? '';
            $index['intro'] = $index['summary'];
            $index['children'] = array_map(static fn (array $page): array => [
                'title' => $page['title'],
                'path' => substr($page['path'], strlen($layer) + 1),
                'kind' => (string) ($page['doc']['kind'] ?? 'class'),
                'summary' => $page['summary'],
            ], $classes);
            $chain[] = $index;
            array_push($chain, ...$classes);
        }

        $top['children'] = array_map(fn (array $layerIndex): array => [
            'title' => $layerIndex['title'],
            'path' => $layerIndex['path'],
            'kind' => 'namespace',
            'summary' => $layerIndex['summary'],
        ], array_values(array_filter($chain, static fn (array $p): bool => $p['type'] === 'namespace')));
        $chain[0] = $top;

        return $this->linkChain($chain);
    }

    /**
     * @param list<Page> $chain
     * @return list<Page>
     */
    private function linkChain(array $chain): array
    {
        $topPath = $chain[0]['path'];
        foreach ($chain as $i => $page) {
            $directory = dirname($page['path']) === '.' ? '' : dirname($page['path']);
            $chain[$i]['order'] = $i;
            $chain[$i]['prev'] = $i > 0 ? $this->reference($chain[$i - 1], $directory) : null;
            $chain[$i]['next'] = $i < count($chain) - 1 ? $this->reference($chain[$i + 1], $directory) : null;
            if ($page['type'] === 'class') {
                $layerIndex = $page['layer'] . '/index.md';
                $title = $page['layer'];
                $chain[$i]['up'] = ['title' => $title, 'path' => self::relative($directory, $layerIndex)];
            } elseif ($page['type'] === 'namespace') {
                $chain[$i]['up'] = ['title' => $chain[0]['title'], 'path' => self::relative($directory, $topPath)];
            }
        }

        return $chain;
    }

    /**
     * @param Page $page
     * @return array{title: string, path: string}
     */
    private function reference(array $page, string $fromDirectory): array
    {
        return ['title' => $page['title'], 'path' => self::relative($fromDirectory, $page['path'])];
    }

    /**
     * @return list<Page>
     */
    private function classes(string $layer): array
    {
        $found = [];
        $directory = $this->root . '/src/' . $layer;
        $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($directory, FilesystemIterator::SKIP_DOTS));
        foreach ($files as $file) {
            if ($file->getExtension() !== 'php') {
                continue;
            }
            $relative = substr(str_replace('\\', '/', $file->getPathname()), strlen(str_replace('\\', '/', $this->root . '/src/')));
            $fqcn = self::ROOT_NAMESPACE . str_replace('/', '\\', substr($relative, 0, -4));
            if (!class_exists($fqcn) && !interface_exists($fqcn) && !trait_exists($fqcn) && !enum_exists($fqcn)) {
                continue;
            }

            /** @var class-string $fqcn */
            $doc = ClassModel::of(new ReflectionClass($fqcn), $this->config['volatile']);
            if ($doc === null) {
                continue;
            }

            $page = $this->page('class', $doc['short'], $layer . '/' . substr($relative, strlen($layer) + 1, -4) . '.md', $layer, $fqcn);
            $page['doc'] = $doc;
            $page['namespace'] = $doc['namespace'];
            $page['source'] = 'src/' . $relative;
            $page['since'] = $doc['since'];
            $page['stability'] = $doc['deprecated'] === null ? 'stable' : 'deprecated';
            $page['summary'] = $doc['summary'];
            $page['adrs'] = $doc['adrs'];
            $found[] = $page;
        }

        $first = $this->config['first'][$layer] ?? [];
        usort($found, static function (array $a, array $b) use ($first): int {
            $rank = static fn (array $page): int => ($i = array_search($page['title'], $first, true)) === false ? PHP_INT_MAX : (int) $i;
            $byRank = $rank($a) <=> $rank($b);
            if ($byRank !== 0) {
                return $byRank;
            }
            return strcmp($a['fqcn'], $b['fqcn']);
        });

        return $found;
    }

    /**
     * @return Page
     */
    private function page(string $type, string $title, string $path, string $layer, string $fqcn): array
    {
        return [
            'type' => $type, 'path' => $path, 'title' => $title, 'order' => 0, 'layer' => $layer,
            'fqcn' => $type === 'class' ? $fqcn : '', 'namespace' => $type === 'namespace' ? $fqcn : '', 'source' => '',
            'since' => '', 'stability' => 'stable', 'summary' => '', 'adrs' => [], 'prev' => null, 'next' => null, 'up' => null,
            'doc' => null, 'children' => [], 'intro' => '',
        ];
    }

    /**
     * @param array<string, string> $paths fqcn => page path
     * @return array<string, string> short class name => fqcn, for names that are unambiguous
     */
    private function uniqueShortNames(array $paths): array
    {
        $count = [];
        $map = [];
        foreach (array_keys($paths) as $fqcn) {
            $short = substr($fqcn, (int) strrpos($fqcn, '\\') + 1);
            $count[$short] = ($count[$short] ?? 0) + 1;
            $map[$short] = $fqcn;
        }

        return array_filter($map, static fn (string $short): bool => $count[substr($short, (int) strrpos($short, '\\') + 1)] === 1);
    }

    /**
     * @param array<string, string> $paths
     * @param array<string, string> $shortNames
     */
    private function link(array $paths, array $shortNames, string $name, string $from): ?string
    {
        $name = ltrim($name, '\\');
        $fqcn = isset($paths[$name]) ? $name : ($shortNames[$name] ?? null);

        return $fqcn !== null && isset($paths[$fqcn]) ? self::relative($from, $paths[$fqcn]) : null;
    }

    private function adrPath(int $number, string $from): ?string
    {
        $matches = glob($this->root . '/docs/adr/' . sprintf('%04d', $number) . '-*.md') ?: [];
        if ($matches === []) {
            return null;
        }

        // From docs/api/{from}/ to docs/adr/{file}: out of {from}, out of api.
        $depth = $from === '' ? 0 : count(explode('/', $from));

        return str_repeat('../', $depth + 1) . 'adr/' . basename($matches[0]);
    }

    /**
     * A path from one output directory to a page, using forward slashes: `../Http/Router.md`.
     */
    private static function relative(string $fromDirectory, string $to): string
    {
        $from = $fromDirectory === '' ? [] : explode('/', $fromDirectory);
        $target = explode('/', $to);
        while ($from !== [] && count($target) > 1 && $from[0] === $target[0]) {
            array_shift($from);
            array_shift($target);
        }

        return str_repeat('../', count($from)) . implode('/', $target);
    }

    private function outputDirectory(): string
    {
        return $this->root . '/' . $this->config['output'];
    }

    /**
     * @return list<string> Files already in the output directory, relative to it.
     */
    private function existing(): array
    {
        $output = $this->outputDirectory();
        if (!is_dir($output)) {
            return [];
        }

        $found = [];
        $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($output, FilesystemIterator::SKIP_DOTS));
        foreach ($files as $file) {
            $found[] = substr(str_replace('\\', '/', $file->getPathname()), strlen(str_replace('\\', '/', $output)) + 1);
        }
        sort($found, SORT_STRING);

        return $found;
    }
}
