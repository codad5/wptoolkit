<?php

/**
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

namespace Codad5\WPToolkit\Frontend;

use Closure;
use Codad5\WPToolkit\Exceptions\InvalidConfigException;
use Codad5\WPToolkit\Support\Validation\Rule;
use Codad5\WPToolkit\Support\Validation\Sanitizer;

/**
 * One declared shortcode (ADR-0022). Built by `Shortcodes::add()`; configure it fluently:
 *
 *     $shortcodes->add('widget', fn (array $a): array => ['coins' => $coins->top($a['count'])])
 *         ->public()
 *         ->attributes(['count' => ['type' => 'int', 'default' => 10, 'rules' => 'integer|min:1|max:50']])
 *         ->view('front/widget')
 *         ->assets('widget');
 */
final class Shortcode
{
    /** @var 'public'|'logged_in'|'can'|null */
    private ?string $access = null;

    private ?string $capability = null;

    /** @var array<string, array{rules?: string|list<string|Rule>, type?: string, label?: string, default?: mixed}> */
    private array $attributes = [];

    private ?string $view = null;

    /** @var list<string> */
    private array $assets = [];

    /**
     * @param Closure(array<string, mixed>, string): (string|array<string, mixed>) $handler Receives the validated
     *        attributes and the enclosed content; returns view data (with ->view()) or an HTML string.
     */
    public function __construct(
        public readonly string $name,
        public readonly string $tag,
        private readonly Closure $handler
    ) {
    }

    /** Anyone who can see the post may see the output. */
    public function public(): self
    {
        $this->access = 'public';

        return $this;
    }

    public function loggedIn(): self
    {
        $this->access = 'logged_in';

        return $this;
    }

    /** Only users with this capability see the output; everyone else gets nothing printed. */
    public function can(string $capability): self
    {
        $this->access = 'can';
        $this->capability = $capability;

        return $this;
    }

    /**
     * Declared attributes, in the shape of `Route::args()`. Names are lowercase, because WordPress
     * lowercases attribute names when it parses a shortcode.
     *
     * @param array<string, array{rules?: string|list<string|Rule>, type?: string, label?: string, default?: mixed}> $attributes
     */
    public function attributes(array $attributes): self
    {
        foreach ($attributes as $name => $definition) {
            if (preg_match('/^[a-z0-9_\-]+$/', (string) $name) !== 1) {
                throw new InvalidConfigException(sprintf('Shortcode attribute "%s" must be lowercase letters, digits, "-" or "_".', $name));
            }
            $type = $definition['type'] ?? 'text';
            if (!in_array($type, Sanitizer::TYPES, true)) {
                throw new InvalidConfigException(sprintf(
                    'Unknown input type "%s" for attribute "%s". Use one of: %s.',
                    $type,
                    $name,
                    implode(', ', Sanitizer::TYPES)
                ));
            }
        }
        $this->attributes = $attributes;

        return $this;
    }

    /** Render the handler's returned data through this view (escaped late, theme-overridable). */
    public function view(string $view): self
    {
        $this->view = $view;

        return $this;
    }

    /** Enqueue these on-demand assets when — and only when — this shortcode renders. */
    public function assets(string ...$names): self
    {
        $this->assets = array_values($names);

        return $this;
    }

    public function accessRule(): ?string
    {
        return $this->access;
    }

    public function capability(): ?string
    {
        return $this->capability;
    }

    /**
     * @return array<string, array{rules?: string|list<string|Rule>, type?: string, label?: string, default?: mixed}>
     */
    public function declaredAttributes(): array
    {
        return $this->attributes;
    }

    public function viewName(): ?string
    {
        return $this->view;
    }

    /**
     * @return list<string>
     */
    public function assetNames(): array
    {
        return $this->assets;
    }

    public function handler(): Closure
    {
        return $this->handler;
    }
}
