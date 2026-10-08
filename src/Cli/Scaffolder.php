<?php

/**
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

namespace Codad5\WPToolkit\Cli;

use Codad5\WPToolkit\Contracts\Clock\Clock;

/**
 * Writes starter classes from `resources/stubs` into a plugin (`wp {slug} make:*`). The stubs are
 * correct as generated — access rules on routes, translatable strings, prefixed keys — so the safe
 * version is the one you start from. Existing files are never overwritten without `--force`.
 */
final class Scaffolder
{
    /** kind => [stub, directory under the plugin, class-name suffix] */
    private const KINDS = [
        'entity' => ['entity.stub', 'src/Entities', ''],
        'controller' => ['controller.stub', 'src/Http', 'Controller'],
        'provider' => ['provider.stub', 'src', 'ServiceProvider'],
        'field' => ['field.stub', 'src/Fields', 'FieldType'],
        'migration' => ['migration.stub', 'src/Migrations', ''],
    ];

    public function __construct(
        private readonly string $pluginDir,
        private readonly string $namespace,
        private readonly string $slug,
        private readonly string $textDomain,
        private readonly string $stubDir,
        private readonly Clock $clock
    ) {
    }

    /**
     * The plugin's root namespace from its composer.json (`autoload.psr-4` entry for `src/`).
     */
    public static function namespaceFromComposer(string $pluginDir): ?string
    {
        $file = $pluginDir . '/composer.json';
        $json = is_file($file) ? json_decode((string) file_get_contents($file), true) : null;
        $psr4 = is_array($json) ? ($json['autoload']['psr-4'] ?? null) : null;
        if (!is_array($psr4)) {
            return null;
        }

        foreach ($psr4 as $prefix => $dirs) {
            foreach ((array) $dirs as $dir) {
                if (is_string($dir) && trim($dir, '/') === 'src' && is_string($prefix)) {
                    return rtrim($prefix, '\\');
                }
            }
        }

        return null;
    }

    /**
     * @return list<string>
     */
    public static function kinds(): array
    {
        return array_keys(self::KINDS);
    }

    /**
     * @return string The file written.
     * @throws CliException
     */
    public function make(string $kind, string $name, bool $force = false): string
    {
        [$stub, $dir, $suffix] = self::KINDS[$kind] ?? throw new CliException(sprintf('Unknown kind "%s". Use: %s.', $kind, implode(', ', self::kinds())));

        $base = $this->studly($name);
        $class = $base;
        $id = '';
        if ($kind === 'migration') {
            $id = $this->clock->now()->format('Y_m_d_His') . '_' . $this->snake($base);
            $class = $base;
        } elseif ($kind === 'field') {
            $id = $this->snake($base);
        }

        $path = $this->pluginDir . '/' . $dir . '/' . $class . $suffix . '.php';
        if (is_file($path) && !$force) {
            throw new CliException(sprintf('%s already exists. Use --force to replace it.', $path));
        }

        $template = (string) file_get_contents($this->stubDir . '/' . $stub);
        $code = strtr($template, [
            '{{namespace}}' => $this->namespace,
            '{{class}}' => $class,
            '{{label}}' => trim((string) preg_replace('/(?<!^)[A-Z]/', ' $0', $base)),
            '{{post_type}}' => substr($this->snake($base), 0, 20),
            '{{route}}' => str_replace('_', '-', $this->snake($base)) . 's',
            '{{id}}' => $id,
            '{{slug}}' => $this->slug,
            '{{text_domain}}' => $this->textDomain,
        ]);

        if (!is_dir(dirname($path)) && !mkdir(dirname($path), 0755, true) && !is_dir(dirname($path))) {
            throw new CliException(sprintf('Could not create %s.', dirname($path)));
        }
        if (file_put_contents($path, $code) === false) {
            throw new CliException(sprintf('Could not write %s.', $path));
        }

        return $path;
    }

    private function studly(string $name): string
    {
        $studly = str_replace(' ', '', ucwords((string) preg_replace('/[^A-Za-z0-9]+/', ' ', $name)));
        if (preg_match('/^[A-Z][A-Za-z0-9]*$/', $studly) !== 1) {
            throw new CliException(sprintf('"%s" is not a usable class name; start with a letter.', $name));
        }

        return $studly;
    }

    private function snake(string $studly): string
    {
        return strtolower((string) preg_replace('/(?<!^)[A-Z]/', '_$0', $studly));
    }
}
