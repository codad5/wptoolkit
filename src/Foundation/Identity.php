<?php

/**
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

namespace Codad5\WPToolkit\Foundation;

use Codad5\WPToolkit\Exceptions\InvalidConfigException;

/**
 * Every name the library writes into WordPress's global namespace, derived from the consumer's
 * slug, so two plugins — or two copies of WPToolkit — never collide (ADR-0005, ADR-0006).
 *
 * Option and meta keys keep 0.x's exact shapes, because sites already store data under them
 * (ADR-0016). Everything else follows ADR-0006.
 *
 * Library code never writes one of these names as a string literal; it asks Identity.
 */
final class Identity
{
    /** WordPress's `option_name` column is VARCHAR(191). */
    private const MAX_OPTION_LENGTH = 191;

    /** `_transient_timeout_` (19 chars) + name must fit in an option name. */
    private const MAX_TRANSIENT_LENGTH = 172;

    /** `_site_transient_timeout_` makes site transients shorter still. */
    private const MAX_SITE_TRANSIENT_LENGTH = 167;

    private readonly string $underscored;

    public function __construct(public readonly string $slug)
    {
        if (preg_match('/^[a-z0-9][a-z0-9_-]*$/', $slug) !== 1) {
            throw new InvalidConfigException(sprintf('"%s" is not a valid slug.', $slug));
        }

        $this->underscored = str_replace('-', '_', $slug);
    }

    /**
     * An action or filter name: `my-plugin/http/before_dispatch`.
     *
     * @return non-empty-string
     */
    public function hook(string $name): string
    {
        return $this->slug . '/' . $this->clean($name, '/._-');
    }

    /**
     * An admin-ajax action: `my_plugin_books_search`.
     *
     * @return non-empty-string
     */
    public function ajaxAction(string $name): string
    {
        return $this->underscored . '_' . $this->clean($name, '_');
    }

    /**
     * A REST namespace: `my-plugin/v1`.
     *
     * @return non-falsy-string
     */
    public function restNamespace(string $version = 'v1'): string
    {
        return $this->slug . '/' . $this->clean($version, '._-');
    }

    /**
     * An option name, in 0.x's shape: `{slug}_{sanitize_key(key)}` — e.g. `pau-alumni-manager_api_key`.
     *
     * @throws InvalidConfigException When the result is longer than the options table allows.
     *
     * @return non-empty-string
     */
    public function optionKey(string $key): string
    {
        $name = $this->slug . '_' . $this->sanitizeKey($key);

        if (strlen($name) > self::MAX_OPTION_LENGTH) {
            throw new InvalidConfigException(sprintf(
                'Option name "%s" is %d characters; WordPress allows %d.',
                $name,
                strlen($name),
                self::MAX_OPTION_LENGTH
            ));
        }

        return $name;
    }

    /**
     * A transient name. Long keys are shortened with a hash so they always fit.
     *
     * @return non-empty-string
     */
    public function transientKey(string $key, bool $site = false): string
    {
        $name = $this->underscored . '_' . $this->clean($key, '_.-:');
        $max = $site ? self::MAX_SITE_TRANSIENT_LENGTH : self::MAX_TRANSIENT_LENGTH;

        if (strlen($name) <= $max) {
            return $name;
        }

        return $this->underscored . '_h_' . md5($key);
    }

    /**
     * A post meta key, in 0.x MetaBox's shape: `{box_id}_{post_type}_{field}`.
     *
     * Pass `$prefix` when the box was given a custom prefix (0.x `set_prefix()`); it then replaces
     * `{box_id}_{post_type}_` entirely, exactly as 0.x did.
     *
     * @return non-empty-string
     */
    public function metaKey(string $boxId, string $postType, string $field, ?string $prefix = null): string
    {
        $prefix ??= $this->sanitizeKey($boxId) . '_' . $postType . '_';
        $key = str_starts_with($field, $prefix) ? $field : $prefix . $field;

        if ($field === '' || $key === '') {
            throw new InvalidConfigException('A meta key needs a non-empty field name.');
        }

        return $key;
    }

    /**
     * A custom table name, without `$wpdb->prefix`: `my_plugin_books`.
     *
     * @return non-empty-string
     */
    public function table(string $name): string
    {
        return $this->underscored . '_' . $this->clean($name, '_');
    }

    /**
     * A WP-Cron hook: `my_plugin_cleanup`.
     *
     * @return non-empty-string
     */
    public function cronHook(string $name): string
    {
        return $this->underscored . '_' . $this->clean($name, '_');
    }

    /**
     * A script or style handle: `my-plugin-toolkit-api`.
     *
     * @return non-empty-string
     */
    public function handle(string $name): string
    {
        return $this->slug . '-' . $this->clean($name, '-_.');
    }

    /**
     * The one shared JavaScript namespace: `window.wptoolkit`. Every plugin's data lives under its
     * own slug key inside it, never at the top level (ADR-0006 amendment). Writers must merge
     * (`window.wptoolkit = window.wptoolkit || {}`), never replace.
     */
    public const JS_NAMESPACE = 'wptoolkit';

    /**
     * This consumer's key inside `window.wptoolkit` — its slug, unique per site.
     */
    public function jsKey(): string
    {
        return $this->slug;
    }

    /**
     * A JavaScript expression for this consumer's data: `window.wptoolkit["my-plugin"]`.
     *
     * @return non-empty-string
     */
    public function jsAccessor(): string
    {
        return 'window.' . self::JS_NAMESPACE . '[' . (string) json_encode($this->slug) . ']';
    }

    /**
     * A nonce action: `my-plugin:books.search`.
     *
     * @return non-empty-string
     */
    public function nonceAction(string $name): string
    {
        return $this->slug . ':' . $this->clean($name, '._-:');
    }

    /**
     * Lowercase, and drop anything but letters, digits and the allowed punctuation.
     */
    private function clean(string $name, string $allowed): string
    {
        $pattern = '/[^a-z0-9' . preg_quote($allowed, '/') . ']/';
        $clean = (string) preg_replace($pattern, '', strtolower($name));

        if ($clean === '') {
            throw new InvalidConfigException(sprintf('"%s" has no usable characters for a WordPress name.', $name));
        }

        return $clean;
    }

    /**
     * WordPress's sanitize_key(), without needing WordPress loaded.
     */
    private function sanitizeKey(string $key): string
    {
        return (string) preg_replace('/[^a-z0-9_\-]/', '', strtolower($key));
    }
}
