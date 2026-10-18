<?php

/**
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

namespace Codad5\WPToolkit\Tools\ApiDocs;

/**
 * A parsed docblock: the prose, and its tags. Only what the reference needs — `@param`, `@return`,
 * `@throws`, `@var`, `@since`, `@internal`, `@deprecated` — with types read the way PHPStan writes
 * them (`array<string, mixed>`, `Closure(string): void`, `array{a: int}`), which contain spaces.
 */
final class DocBlock
{
    /**
     * @param list<array{name: string, type: string, variable: string, text: string}> $tags
     */
    private function __construct(
        public readonly string $summary,
        public readonly string $description,
        public readonly array $tags
    ) {
    }

    public static function parse(string|false $raw): self
    {
        if ($raw === false || trim($raw) === '') {
            return new self('', '', []);
        }

        $description = [];
        $tags = [];
        foreach (self::lines($raw) as $line) {
            if (preg_match('/^@([A-Za-z][A-Za-z0-9\-]*)\s*(.*)$/', ltrim($line), $match) === 1) {
                $tags[] = self::tag($match[1], $match[2]);
                continue;
            }
            if ($tags !== []) {
                $last = array_key_last($tags);
                $tags[$last]['text'] = trim($tags[$last]['text'] . ' ' . trim($line));
                continue;
            }
            $description[] = rtrim($line);
        }

        $body = trim(implode("\n", $description));
        $paragraph = preg_split('/\n\s*\n/', $body, 2);
        $summary = trim((string) preg_replace('/\s+/', ' ', is_array($paragraph) ? $paragraph[0] : $body));

        return new self($summary, $body, $tags);
    }

    public function has(string $tag): bool
    {
        return $this->first($tag) !== null;
    }

    /**
     * @return array{name: string, type: string, variable: string, text: string}|null
     */
    public function first(string $tag): ?array
    {
        foreach ($this->tags as $candidate) {
            if ($candidate['name'] === $tag) {
                return $candidate;
            }
        }

        return null;
    }

    /**
     * @return list<array{name: string, type: string, variable: string, text: string}>
     */
    public function all(string $tag): array
    {
        return array_values(array_filter($this->tags, static fn (array $t): bool => $t['name'] === $tag));
    }

    /**
     * The prose without the summary's own line wrapping, for the page's introduction.
     */
    public function prose(): string
    {
        return $this->description;
    }

    /**
     * @return list<string>
     */
    private static function lines(string $raw): array
    {
        $raw = (string) preg_replace('#^\s*/\*\*#', '', $raw);
        $raw = (string) preg_replace('#\*/\s*$#', '', $raw);
        $lines = [];
        foreach (preg_split('/\R/', $raw) ?: [] as $line) {
            // One space after the asterisk is the gutter; anything beyond it is indentation (code).
            $lines[] = (string) preg_replace('/^\s*\* ?/', '', $line);
        }

        return $lines;
    }

    /**
     * @return array{name: string, type: string, variable: string, text: string}
     */
    private static function tag(string $name, string $rest): array
    {
        $type = '';
        $variable = '';

        if (in_array($name, ['param', 'return', 'throws', 'var', 'property', 'phpstan-return', 'phpstan-param'], true)) {
            [$type, $rest] = self::splitType($rest);
            $named = in_array($name, ['param', 'var', 'property', 'phpstan-param'], true);
            if ($named && preg_match('/^(\.\.\.)?(&)?(\$[A-Za-z_][A-Za-z0-9_]*)\s*(.*)$/s', $rest, $m) === 1) {
                $variable = $m[3];
                $rest = $m[4];
            }
        }

        return ['name' => $name, 'type' => $type, 'variable' => $variable, 'text' => trim($rest)];
    }

    /**
     * Read one type off the front of a tag. Whitespace inside `<>`, `()`, `{}` and `[]` belongs to the
     * type, and so does the space after a callable's `:` (`Closure(string): void`).
     *
     * @return array{0: string, 1: string} The type, and what follows it.
     */
    private static function splitType(string $text): array
    {
        $text = ltrim($text);
        $depth = 0;
        $length = strlen($text);
        for ($i = 0; $i < $length; $i++) {
            $char = $text[$i];
            if (str_contains('<({[', $char)) {
                $depth++;
            } elseif (str_contains('>)}]', $char)) {
                $depth--;
            } elseif (ctype_space($char) && $depth <= 0) {
                $before = rtrim(substr($text, 0, $i));
                if (str_ends_with($before, ':') || str_ends_with($before, '|')) {
                    continue;
                }
                $after = ltrim(substr($text, $i));
                if (str_starts_with($after, '|')) {
                    continue;
                }

                return [$before, $after];
            }
        }

        return [trim($text), ''];
    }
}
