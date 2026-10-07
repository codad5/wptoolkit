<?php

declare(strict_types=1);

namespace Codad5\WPToolkit\Tests\Support;

use Brain\Monkey\Functions;

/**
 * In-memory options with WordPress's semantics: add_option() refuses an existing name (that is what
 * makes it usable as a lock), update_option() creates or replaces, and autoload is remembered.
 */
final class FakeOptions
{
    /** @var array<string, array{value: mixed, autoload: bool}> */
    public array $options = [];

    public function install(): void
    {
        Functions\when('get_option')->alias(fn (string $name, $default = false) => array_key_exists($name, $this->options) ? $this->options[$name]['value'] : $default);
        Functions\when('add_option')->alias(function (string $name, $value = '', $deprecated = '', $autoload = true) {
            if (array_key_exists($name, $this->options)) {
                return false;
            }
            $this->options[$name] = ['value' => $value, 'autoload' => (bool) $autoload];
            return true;
        });
        Functions\when('update_option')->alias(function (string $name, $value, $autoload = null) {
            $this->options[$name] = ['value' => $value, 'autoload' => $autoload ?? ($this->options[$name]['autoload'] ?? true)];
            return true;
        });
        Functions\when('delete_option')->alias(function (string $name) {
            $existed = array_key_exists($name, $this->options);
            unset($this->options[$name]);
            return $existed;
        });
        Functions\when('wp_load_alloptions')->alias(fn () => array_map(
            static fn (array $o) => $o['value'],
            array_filter($this->options, static fn (array $o) => $o['autoload'])
        ));
    }

    public function value(string $name): mixed
    {
        return $this->options[$name]['value'] ?? null;
    }
}
