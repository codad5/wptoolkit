<?php

/**
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

namespace Codad5\WPToolkit\Frontend;

use Closure;
use Codad5\WPToolkit\Assets\AssetManager;
use Codad5\WPToolkit\Contracts\Log\Logger;
use Codad5\WPToolkit\Contracts\View\Renderer;
use Codad5\WPToolkit\Exceptions\InvalidConfigException;
use Codad5\WPToolkit\Exceptions\LifecycleException;
use Codad5\WPToolkit\Foundation\Identity;
use Codad5\WPToolkit\Support\Validation\Rules;
use Codad5\WPToolkit\Support\Validation\Sanitizer;
use Codad5\WPToolkit\Support\Validation\Validator;
use Throwable;

/**
 * Shortcodes done safely (ADR-0022): a prefixed tag, declared and validated attributes, an explicit
 * access rule, a view or a returned string (never an echo), assets that load only where the
 * shortcode appears, and a handler that cannot take the page down.
 *
 * @since 1.0.0-rc.2
 */
final class Shortcodes
{
    /** @var array<string, Shortcode> keyed by name */
    private array $shortcodes = [];

    public function __construct(
        private readonly Identity $identity,
        private readonly Renderer $renderer,
        private readonly AssetManager $assets,
        private readonly Logger $logger,
        private readonly bool $developmentMode = false
    ) {
    }

    /**
     * @param Closure(array<string, mixed>, string): (string|array<string, mixed>) $handler
     * @param string|null $tag Use an existing tag verbatim (it is stored in users' content); default is Identity::shortcodeTag($name).
     */
    public function add(string $name, Closure $handler, ?string $tag = null): Shortcode
    {
        if (preg_match('/^[a-z0-9][a-z0-9_\-]*$/', $name) !== 1) {
            throw new InvalidConfigException(sprintf('Shortcode name "%s" may contain lowercase letters, digits, "-" and "_".', $name));
        }
        $tag ??= $this->identity->shortcodeTag($name);
        if (preg_match('/^[A-Za-z0-9][A-Za-z0-9_\-]*$/', $tag) !== 1) {
            throw new InvalidConfigException(sprintf('Shortcode tag "%s" may contain letters, digits, "-" and "_".', $tag));
        }
        if (isset($this->shortcodes[$name])) {
            throw new InvalidConfigException(sprintf('Shortcode "%s" is already added.', $name));
        }
        foreach ($this->shortcodes as $existing) {
            if ($existing->tag === $tag) {
                throw new InvalidConfigException(sprintf('Shortcode tag "%s" is already used by "%s".', $tag, $existing->name));
            }
        }
        if (shortcode_exists($tag)) {
            throw new InvalidConfigException(sprintf('Shortcode tag "%s" is already registered by something else; pick another tag.', $tag));
        }

        $shortcode = new Shortcode($name, $tag, $handler);
        $this->shortcodes[$name] = $shortcode;
        add_shortcode($tag, fn (mixed $atts = [], ?string $content = null): string => $this->fromWordPress($name, $atts, $content));

        return $shortcode;
    }

    /**
     * Development-time check, run once every provider has booted: a shortcode with no access rule
     * is a mistake worth failing loudly for (in production it prints nothing).
     */
    public function verify(): void
    {
        if (!$this->developmentMode) {
            return;
        }
        foreach ($this->shortcodes as $shortcode) {
            if ($shortcode->accessRule() === null) {
                throw new LifecycleException(sprintf(
                    'Shortcode [%s] has no access rule. Add ->public(), ->loggedIn() or ->can(). (In production it would print nothing.)',
                    $shortcode->tag
                ));
            }
        }
    }

    /**
     * The output of a shortcode, produced exactly as a post render would: access check, attribute
     * validation, handler, view. The one supported way for code to run a shortcode — never build
     * `[tag …]` strings from request data.
     *
     * @param array<string, mixed> $attributes
     */
    public function render(string $name, array $attributes = [], string $content = ''): string
    {
        $shortcode = $this->shortcodes[$name] ?? throw new InvalidConfigException(sprintf('No shortcode named "%s".', $name));

        if (!$this->allowed($shortcode)) {
            return '';
        }

        try {
            $result = ($shortcode->handler())($this->attributes($shortcode, $attributes), $content);
            $output = $this->output($shortcode, $result);
            if ($output !== '') {
                $this->assets->load(...$shortcode->assetNames());
            }

            return $output;
        } catch (Throwable $e) {
            $this->logger->error('Shortcode [{tag}] failed.', ['tag' => $shortcode->tag, 'exception' => $e]);
            if ($this->developmentMode) {
                throw $e;
            }

            return '';
        }
    }

    /**
     * WordPress passes `''` instead of an array when a shortcode has no attributes.
     */
    private function fromWordPress(string $name, mixed $atts, ?string $content): string
    {
        return $this->render($name, is_array($atts) ? $atts : [], (string) $content);
    }

    /**
     * @return array<string, Shortcode>
     */
    public function all(): array
    {
        return $this->shortcodes;
    }

    private function allowed(Shortcode $shortcode): bool
    {
        switch ($shortcode->accessRule()) {
            case 'public':
                return true;
            case 'logged_in':
                return is_user_logged_in();
            case 'can':
                return current_user_can((string) $shortcode->capability());
            default:
                $this->logger->warning('Shortcode [{tag}] has no access rule and printed nothing. Add ->public(), ->loggedIn() or ->can().', [
                    'tag' => $shortcode->tag,
                ]);

                return false;
        }
    }

    /**
     * Validated, sanitized values for the declared attributes only. One that fails validation falls
     * back to its default (or null) — a visitor can't fix a post's content — and is logged by name.
     *
     * @param array<string, mixed> $given
     * @return array<string, mixed>
     */
    private function attributes(Shortcode $shortcode, array $given): array
    {
        $declared = $shortcode->declaredAttributes();
        if ($declared === []) {
            return [];
        }

        [$values, $errors] = (new Validator($declared))->validate($given);
        foreach (array_keys($errors) as $field) {
            $default = $declared[$field]['default'] ?? null;
            $values[$field] = Rules::isEmpty($default) ? $default : Sanitizer::sanitize($default, $declared[$field]['type'] ?? 'text');
        }
        if ($errors !== []) {
            $this->logger->notice('Shortcode [{tag}] had invalid attributes: {fields}.', [
                'tag' => $shortcode->tag,
                'fields' => implode(', ', array_keys($errors)),
            ]);
        }

        return $values;
    }

    private function output(Shortcode $shortcode, mixed $result): string
    {
        $view = $shortcode->viewName();
        if ($view !== null) {
            if (!is_array($result)) {
                throw new InvalidConfigException(sprintf('Shortcode [%s] has a view, so its handler must return an array of view data.', $shortcode->tag));
            }

            return $this->renderer->render($view, $result);
        }
        if (!is_string($result)) {
            throw new InvalidConfigException(sprintf('Shortcode [%s] has no view, so its handler must return an HTML string.', $shortcode->tag));
        }

        return $result;
    }
}
