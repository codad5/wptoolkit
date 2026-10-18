<?php

/**
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

namespace Codad5\WPToolkit\Tools\ApiDocs;

use ReflectionClass;
use ReflectionClassConstant;
use ReflectionEnum;
use ReflectionEnumBackedCase;
use ReflectionFunctionAbstract;
use ReflectionIntersectionType;
use ReflectionMethod;
use ReflectionNamedType;
use ReflectionParameter;
use ReflectionProperty;
use ReflectionType;
use ReflectionUnionType;

/**
 * What the reference says about one class: read from Reflection (signatures, modifiers, defaults —
 * always in step with the code) and from its docblocks (the prose, which explains why).
 *
 * @phpstan-type ParamDoc array{name: string, type: string, text: string, signature: string}
 * @phpstan-type MethodDoc array{
 *     name: string,
 *     static: bool,
 *     signature: string,
 *     summary: string,
 *     prose: string,
 *     params: list<ParamDoc>,
 *     returns: array{type: string, text: string}|null,
 *     throws: list<array{type: string, text: string}>,
 *     deprecated: string|null
 * }
 * @phpstan-type ClassDoc array{
 *     kind: string,
 *     declaration: string,
 *     fqcn: string,
 *     short: string,
 *     namespace: string,
 *     extends: string|null,
 *     implements: list<string>,
 *     summary: string,
 *     prose: string,
 *     since: string,
 *     deprecated: string|null,
 *     adrs: list<int>,
 *     constants: list<array{name: string, value: string, summary: string}>,
 *     cases: list<array{name: string, value: string}>,
 *     properties: list<array{name: string, type: string, readonly: bool, summary: string}>,
 *     methods: list<MethodDoc>
 * }
 */
final class ClassModel
{
    /** A class documented before `@since` tags existed shipped in the first 1.0 candidate. */
    public const DEFAULT_SINCE = '1.0.0';

    /** Interfaces PHP adds by itself (a `__toString()` makes a class Stringable; every enum is a UnitEnum): noise, not design. */
    private const IMPLICIT = ['Stringable', 'UnitEnum', 'BackedEnum'];

    /**
     * @param ReflectionClass<object> $class
     * @param list<string> $volatile `Class::CONSTANT` names whose value changes with every release; the value is not printed.
     * @return ClassDoc|null Null for a class marked `@internal`: it is wiring, not API.
     */
    public static function of(ReflectionClass $class, array $volatile = []): ?array
    {
        $doc = DocBlock::parse($class->getDocComment());
        if ($doc->has('internal') || $class->isAnonymous()) {
            return null;
        }

        $parent = $class->getParentClass();
        $deprecated = $doc->first('deprecated');
        $since = $doc->first('since');

        return [
            'kind' => self::kind($class),
            'declaration' => self::declaration($class),
            'fqcn' => $class->getName(),
            'short' => $class->getShortName(),
            'namespace' => $class->getNamespaceName(),
            'extends' => $parent === false ? null : $parent->getName(),
            'implements' => array_values(array_filter($class->getInterfaceNames(), static fn (string $name): bool => !in_array($name, self::IMPLICIT, true))),
            'summary' => $doc->summary,
            'prose' => $doc->prose(),
            'since' => $since !== null && $since['text'] !== '' ? $since['text'] : self::DEFAULT_SINCE,
            'deprecated' => $deprecated === null ? null : $deprecated['text'],
            'adrs' => self::adrs($class),
            'constants' => self::constants($class, $volatile),
            'cases' => self::cases($class),
            'properties' => self::properties($class),
            'methods' => self::methods($class),
        ];
    }

    /**
     * @param ReflectionClass<object> $class
     */
    private static function kind(ReflectionClass $class): string
    {
        return match (true) {
            $class->isEnum() => 'enum',
            $class->isInterface() => 'interface',
            $class->isTrait() => 'trait',
            default => 'class',
        };
    }

    /**
     * @param ReflectionClass<object> $class
     */
    private static function declaration(ReflectionClass $class): string
    {
        $kind = self::kind($class);
        if ($kind !== 'class') {
            return $kind;
        }

        // ReflectionClass::isReadOnly() arrived in PHP 8.2; the library's floor is 8.1.
        $readonly = method_exists($class, 'isReadOnly') && $class->isReadOnly() ? 'readonly ' : '';

        return ($class->isFinal() ? 'final ' : ($class->isAbstract() ? 'abstract ' : '')) . $readonly . 'class';
    }

    /**
     * ADRs the class and its methods cite, so a page can link the decisions behind it.
     *
     * @param ReflectionClass<object> $class
     * @return list<int>
     */
    private static function adrs(ReflectionClass $class): array
    {
        $text = (string) $class->getDocComment();
        foreach ($class->getMethods() as $method) {
            if ($method->getDeclaringClass()->getName() === $class->getName()) {
                $text .= (string) $method->getDocComment();
            }
        }

        preg_match_all('/ADR-(\d{4})|adr\/(\d{4})-/', $text, $matches);
        $numbers = array_map('intval', array_filter(array_merge($matches[1], $matches[2]), static fn (string $n): bool => $n !== ''));
        $numbers = array_values(array_unique($numbers));
        sort($numbers);

        return $numbers;
    }

    /**
     * @param ReflectionClass<object> $class
     * @param list<string> $volatile
     * @return list<array{name: string, value: string, summary: string}>
     */
    private static function constants(ReflectionClass $class, array $volatile): array
    {
        $constants = [];
        foreach ($class->getReflectionConstants(ReflectionClassConstant::IS_PUBLIC) as $constant) {
            if ($constant->getDeclaringClass()->getName() !== $class->getName() || $constant->isEnumCase()) {
                continue;
            }
            $doc = DocBlock::parse($constant->getDocComment());
            if ($doc->has('internal')) {
                continue;
            }
            $isVolatile = in_array($class->getName() . '::' . $constant->getName(), $volatile, true);
            $constants[] = [
                'name' => $constant->getName(),
                'value' => $isVolatile ? '(set at release)' : self::export($constant->getValue()),
                'summary' => $doc->summary,
            ];
        }

        return $constants;
    }

    /**
     * @param ReflectionClass<object> $class
     * @return list<array{name: string, value: string}>
     */
    private static function cases(ReflectionClass $class): array
    {
        $name = $class->getName();
        if (!$class->isEnum() || !enum_exists($name)) {
            return [];
        }

        $cases = [];
        foreach ((new ReflectionEnum($name))->getCases() as $case) {
            $value = $case instanceof ReflectionEnumBackedCase ? $case->getBackingValue() : null;
            $cases[] = ['name' => $case->getName(), 'value' => $value === null ? '' : self::export($value)];
        }

        return $cases;
    }

    /**
     * Public properties the class declares itself; a promoted constructor property takes its text
     * from the constructor's `@param`.
     *
     * @param ReflectionClass<object> $class
     * @return list<array{name: string, type: string, readonly: bool, summary: string}>
     */
    private static function properties(ReflectionClass $class): array
    {
        $promoted = [];
        $constructor = $class->getConstructor();
        if ($constructor !== null) {
            foreach (DocBlock::parse($constructor->getDocComment())->all('param') as $tag) {
                $promoted[ltrim($tag['variable'], '$')] = $tag;
            }
        }

        $properties = [];
        foreach ($class->getProperties(ReflectionProperty::IS_PUBLIC) as $property) {
            if ($property->getDeclaringClass()->getName() !== $class->getName() || $property->isStatic()) {
                continue;
            }
            $doc = DocBlock::parse($property->getDocComment());
            $var = $doc->first('var');
            $param = $promoted[$property->getName()] ?? null;
            $documented = $var ?? $param;
            $properties[] = [
                'name' => $property->getName(),
                'type' => $documented !== null && $documented['type'] !== '' ? ltrim($documented['type'], '\\') : self::type($property->getType()),
                'readonly' => $property->isReadOnly(),
                'summary' => $doc->summary !== '' ? $doc->summary : ($param['text'] ?? ''),
            ];
        }

        return $properties;
    }

    /**
     * @param ReflectionClass<object> $class
     * @return list<MethodDoc>
     */
    private static function methods(ReflectionClass $class): array
    {
        $methods = [];
        foreach ($class->getMethods(ReflectionMethod::IS_PUBLIC) as $method) {
            if ($method->getDeclaringClass()->getName() !== $class->getName()) {
                continue;
            }
            $doc = DocBlock::parse($method->getDocComment());
            if ($doc->has('internal') || str_starts_with($method->getName(), '__') && $method->getName() !== '__construct') {
                continue;
            }

            $returns = $doc->first('return');
            $deprecated = $doc->first('deprecated');
            $methods[] = [
                'name' => $method->getName(),
                'static' => $method->isStatic(),
                'signature' => self::signature($method),
                'summary' => $doc->summary,
                'prose' => $doc->prose(),
                'params' => self::params($method, $doc),
                'returns' => $method->getName() === '__construct'
                    ? null
                    : self::returns($method, $returns),
                'throws' => array_map(
                    static fn (array $t): array => ['type' => ltrim($t['type'], '\\'), 'text' => $t['text']],
                    $doc->all('throws')
                ),
                'deprecated' => $deprecated === null ? null : $deprecated['text'],
            ];
        }

        return $methods;
    }

    /**
     * @param array{name: string, type: string, variable: string, text: string}|null $tag
     * @return array{type: string, text: string}|null
     */
    private static function returns(ReflectionMethod $method, ?array $tag): ?array
    {
        $native = self::type($method->getReturnType());
        $type = $tag !== null && $tag['type'] !== '' ? ltrim($tag['type'], '\\') : $native;

        return $type === '' || $type === 'void' ? null : ['type' => $type, 'text' => $tag['text'] ?? ''];
    }

    /**
     * @return list<ParamDoc>
     */
    private static function params(ReflectionFunctionAbstract $method, DocBlock $doc): array
    {
        $tags = [];
        foreach ($doc->all('param') as $tag) {
            $tags[ltrim($tag['variable'], '$')] = $tag;
        }

        $params = [];
        foreach ($method->getParameters() as $parameter) {
            $tag = $tags[$parameter->getName()] ?? null;
            $params[] = [
                'name' => '$' . $parameter->getName(),
                'type' => $tag !== null && $tag['type'] !== '' ? ltrim($tag['type'], '\\') : (self::type($parameter->getType()) ?: 'mixed'),
                'text' => $tag['text'] ?? '',
                'signature' => self::parameter($parameter),
            ];
        }

        return $params;
    }

    private static function signature(ReflectionMethod $method): string
    {
        $abstract = $method->isAbstract() && !$method->getDeclaringClass()->isInterface();
        $modifiers = 'public' . ($method->isStatic() ? ' static' : '') . ($abstract ? ' abstract' : '');
        $return = self::type($method->getReturnType());

        return sprintf(
            '%s function %s(%s)%s',
            $modifiers,
            $method->getName(),
            implode(', ', array_map(self::parameter(...), $method->getParameters())),
            $return === '' ? '' : ': ' . $return
        );
    }

    private static function parameter(ReflectionParameter $parameter): string
    {
        $type = self::type($parameter->getType());
        $text = ($type === '' ? '' : $type . ' ')
            . ($parameter->isPassedByReference() ? '&' : '')
            . ($parameter->isVariadic() ? '...' : '')
            . '$' . $parameter->getName();

        if ($parameter->isDefaultValueAvailable()) {
            $default = $parameter->isDefaultValueConstant()
                ? (string) $parameter->getDefaultValueConstantName()
                : self::export($parameter->getDefaultValue());
            $text .= ' = ' . $default;
        }

        return $text;
    }

    /**
     * A native type as written in code, with class names shortened: a page already says which
     * namespace it is in, and `Container` reads better than `Codad5\\WPToolkit\\Foundation\\Container`.
     */
    private static function type(?ReflectionType $type): string
    {
        if ($type === null) {
            return '';
        }
        if ($type instanceof ReflectionNamedType) {
            $name = $type->isBuiltin() ? $type->getName() : self::short($type->getName());

            return ($type->allowsNull() && $type->getName() !== 'mixed' && $type->getName() !== 'null' ? '?' : '') . $name;
        }
        if ($type instanceof ReflectionUnionType || $type instanceof ReflectionIntersectionType) {
            $glue = $type instanceof ReflectionUnionType ? '|' : '&';

            return implode($glue, array_map(
                static fn (ReflectionType $t): string => $t instanceof ReflectionNamedType && !$t->isBuiltin() ? self::short($t->getName()) : (string) $t,
                $type->getTypes()
            ));
        }

        return (string) $type;
    }

    private static function short(string $name): string
    {
        $position = strrpos($name, '\\');

        return $position === false ? $name : substr($name, $position + 1);
    }

    /**
     * A value as PHP source, short and stable: `'text'`, `[]`, `['a' => 1]`, `null`.
     */
    private static function export(mixed $value): string
    {
        return match (true) {
            $value === null => 'null',
            is_bool($value) => $value ? 'true' : 'false',
            is_string($value) => "'" . str_replace(["\\", "'", "\0"], ["\\\\", "\\'", '\0'], $value) . "'",
            is_array($value) => self::exportArray($value),
            is_object($value) => $value::class . ' instance',
            default => var_export($value, true),
        };
    }

    /**
     * @param array<mixed> $value
     */
    private static function exportArray(array $value): string
    {
        if ($value === []) {
            return '[]';
        }

        $isList = array_is_list($value);
        $parts = [];
        foreach ($value as $key => $item) {
            $parts[] = ($isList ? '' : self::export($key) . ' => ') . self::export($item);
        }

        return '[' . implode(', ', $parts) . ']';
    }
}
