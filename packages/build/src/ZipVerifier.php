<?php

/**
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

namespace Codad5\WPToolkit\Build;

use Codad5\WPToolkit\Build\Patterns\PatternSet;
use Codad5\WPToolkit\Build\Steps\Verify;
use ZipArchive;

/**
 * Checks an existing zip — including ones built by other tools — against the same rules the
 * build's Verify step applies, plus the zip-specific ones: one top-level folder, no stray paths.
 */
final class ZipVerifier
{
    /**
     * @param list<string> $forbid Extra patterns that must not appear.
     * @return list<string> Problems; empty when the zip is fine.
     */
    public static function verify(string $zip, array $forbid = []): array
    {
        $archive = new ZipArchive();
        if ($archive->open($zip, ZipArchive::RDONLY) !== true) {
            return [sprintf('%s is not a readable zip.', $zip)];
        }

        $names = [];
        for ($i = 0; $i < $archive->numFiles; $i++) {
            $names[] = (string) $archive->getNameIndex($i);
        }
        $archive->close();

        $problems = [];
        $roots = array_unique(array_map(static fn (string $n): string => explode('/', $n)[0], $names));
        if (count($roots) !== 1) {
            $problems[] = sprintf(
                'Expected one top-level folder (the plugin/theme slug); found %d entries at the root: %s.',
                count($roots),
                implode(', ', array_slice($roots, 0, 5))
            );
        }

        $forbidden = new PatternSet([...Verify::FORBIDDEN, ...$forbid]);
        $found = [];
        foreach ($names as $name) {
            $inside = count($roots) === 1 ? substr($name, strlen((string) reset($roots)) + 1) : $name;
            if ($inside !== '' && !str_ends_with($name, '/') && $forbidden->matches($inside)) {
                $found[] = $inside;
            }
        }
        if ($found !== []) {
            $problems[] = sprintf('%d file(s) that must not ship, e.g. %s.', count($found), implode(', ', array_slice($found, 0, 5)));
        }

        return $problems;
    }
}
