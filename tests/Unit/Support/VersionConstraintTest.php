<?php

declare(strict_types=1);

namespace Codad5\WPToolkit\Tests\Unit\Support;

use Codad5\WPToolkit\Exceptions\InvalidConfigException;
use Codad5\WPToolkit\Support\VersionConstraint;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class VersionConstraintTest extends TestCase
{
    /**
     * @return iterable<string, array{string, string, bool}>
     */
    public static function cases(): iterable
    {
        yield 'caret allows minor' => ['1.4.2', '^1.2', true];
        yield 'caret rejects next major' => ['2.0.0', '^1.2', false];
        yield 'caret rejects lower' => ['1.1.9', '^1.2', false];
        yield 'caret on 0.x pins minor' => ['0.4.0', '^0.3', false];
        yield 'caret on 0.x allows patch' => ['0.3.9', '^0.3', true];
        yield 'tilde two segments' => ['1.9.0', '~1.2', true];
        yield 'tilde three segments pins minor' => ['1.3.0', '~1.2.3', false];
        yield 'tilde three segments allows patch' => ['1.2.9', '~1.2.3', true];
        yield 'greater or equal' => ['1.2.0', '>=1.2', true];
        yield 'less than' => ['2.0.0', '<2.0', false];
        yield 'range with space' => ['1.5.0', '>=1.2 <2.0', true];
        yield 'range with comma' => ['2.1.0', '>=1.2, <2.0', false];
        yield 'or' => ['3.1.0', '^1.0 || ^3.0', true];
        yield 'exact' => ['1.2.3', '1.2.3', true];
        yield 'exact short equals padded' => ['1.2.0', '1.2', true];
        yield 'not equal' => ['1.3.0', '!=1.3.0', false];
        yield 'dev of the bound satisfies caret' => ['1.0.0-dev', '^1.0', true];
        yield 'dev of the next major does not' => ['2.0.0-dev', '^1.0', false];
    }

    #[DataProvider('cases')]
    public function test_constraint(string $version, string $constraint, bool $expected): void
    {
        self::assertSame($expected, VersionConstraint::satisfies($version, $constraint), "{$version} vs {$constraint}");
    }

    public function test_unsupported_syntax_is_rejected_loudly(): void
    {
        $this->expectException(InvalidConfigException::class);

        VersionConstraint::satisfies('1.0.0', '1.x');
    }
}
