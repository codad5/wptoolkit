<?php

/**
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

namespace Codad5\WPToolkit\Foundation;

use Codad5\WPToolkit\Exceptions\InvalidConfigException;

/**
 * Immutable configuration for one plugin or theme (replaces 0.x `Utils\Config`).
 *
 * Pure value object: no WordPress calls, no magic accessors. Nested keys are read with dots:
 * `$config->get('cache.driver')`.
 */
final class Config
{
    /**
     * @param array<string, mixed> $values
     */
    private function __construct(
        public readonly string $slug,
        public readonly string $file,
        private readonly array $values
    ) {
    }

    /**
     * @param string $file The main plugin file (or the theme's functions.php).
     * @param array<string, mixed> $values Must contain a 'slug'.
     * @throws InvalidConfigException When the slug is missing or malformed.
     */
    public static function fromArray(string $file, array $values): self
    {
        $slug = $values['slug'] ?? null;
        if (!is_string($slug) || preg_match('/^[a-z0-9][a-z0-9_-]*$/', $slug) !== 1) {
            throw new InvalidConfigException(
                'Config needs a "slug" of lowercase letters, digits, hyphens and underscores, '
                . 'starting with a letter or digit.'
            );
        }

        return new self($slug, $file, $values);
    }

    public function get(string $key, mixed $default = null): mixed
    {
        if (array_key_exists($key, $this->values)) {
            return $this->values[$key];
        }

        $value = $this->values;
        foreach (explode('.', $key) as $segment) {
            if (!is_array($value) || !array_key_exists($segment, $value)) {
                return $default;
            }
            $value = $value[$segment];
        }

        return $value;
    }

    public function has(string $key): bool
    {
        $missing = new \stdClass();
        return $this->get($key, $missing) !== $missing;
    }

    /**
     * The consumer's text domain: 'text_domain' if set, else the slug.
     */
    public function textDomain(): string
    {
        $domain = $this->values['text_domain'] ?? $this->slug;
        return is_string($domain) && $domain !== '' ? $domain : $this->slug;
    }

    /**
     * A copy with the given values merged over this one. The slug and file can't change.
     *
     * @param array<string, mixed> $values
     */
    public function with(array $values): self
    {
        unset($values['slug']);
        return new self($this->slug, $this->file, array_replace($this->values, $values));
    }

    /**
     * @return array<string, mixed>
     */
    public function all(): array
    {
        return $this->values;
    }
}
