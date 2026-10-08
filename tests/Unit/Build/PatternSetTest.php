<?php

declare(strict_types=1);

namespace Codad5\WPToolkit\Tests\Unit\Build;

use Codad5\WPToolkit\Build\Patterns\PatternSet;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class PatternSetTest extends TestCase
{
    /**
     * @return iterable<string, array{list<string>, string, bool, bool}>
     */
    public static function cases(): iterable
    {
        yield 'bare name matches at any depth' => [['node_modules'], 'a/b/node_modules/x.js', false, true];
        yield 'bare name matches a file' => [['README.md'], 'docs/README.md', false, true];
        yield 'anchored only at the root' => [['/README.md'], 'docs/README.md', false, false];
        yield 'anchored at the root matches' => [['/README.md'], 'README.md', false, true];
        yield 'slash in the middle anchors' => [['assets/dist'], 'src/assets/dist/x', false, false];
        yield 'star stays in one segment' => [['*.map'], 'assets/app.js.map', false, true];
        yield 'single star does not cross directories' => [['src/*.ts'], 'src/deep/x.ts', false, false];
        yield 'double star crosses directories' => [['src/**/*.ts'], 'src/deep/er/x.ts', false, true];
        yield 'double star also matches directly' => [['src/**/*.ts'], 'src/x.ts', false, true];
        yield 'trailing slash is directories only (file)' => [['build/'], 'build', false, false];
        yield 'trailing slash is directories only (inside)' => [['build/'], 'build/x.js', false, true];
        yield 'negation re-includes' => [['*.php', '!keep.php'], 'keep.php', false, false];
        yield 'last rule wins' => [['!keep.php', '*.php'], 'keep.php', false, true];
        yield 'comment and blank ignored' => [['# comment', '', 'x'], 'x', false, true];
        yield 'question mark is one char' => [['file?.txt'], 'file1.txt', false, true];
        yield 'windows separators normalised' => [['assets/dist'], 'assets\\dist\\a.js', false, true];
    }

    /**
     * @param list<string> $patterns
     */
    #[DataProvider('cases')]
    public function test_matching(array $patterns, string $path, bool $isDirectory, bool $expected): void
    {
        self::assertSame($expected, (new PatternSet($patterns))->matches($path, $isDirectory));
    }
}
