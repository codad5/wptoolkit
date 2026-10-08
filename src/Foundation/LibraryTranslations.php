<?php

/**
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

namespace Codad5\WPToolkit\Foundation;

/**
 * Loads translations for the library's own strings (text domain `wptoolkit`) from the package's
 * `languages/` directory — wherever the package lives, including a consumer's `vendor/` or a
 * scoped copy (ADR-0006, ADR-0019).
 *
 * Called on `init`, never earlier, so WordPress 6.7's "translation loading triggered too early"
 * notice can't fire. When another copy of WPToolkit already loaded `wptoolkit`, this one doesn't
 * load again: identical source strings translate identically, which is the cost ADR-0006 accepts.
 */
final class LibraryTranslations
{
    public const DOMAIN = 'wptoolkit';

    public function __construct(private readonly Identity $identity, private readonly string $languagesDir)
    {
    }

    /**
     * The library's bundled `languages/` directory.
     */
    public static function bundledDirectory(): string
    {
        return dirname(__DIR__, 2) . '/languages';
    }

    /**
     * @return bool Whether a translation file was loaded by this call.
     */
    public function load(): bool
    {
        if (is_textdomain_loaded(self::DOMAIN)) {
            return false;
        }

        $locale = determine_locale();
        $file = $this->languagesDir . '/' . self::DOMAIN . '-' . $locale . '.mo';

        /**
         * Filters the .mo file used for WPToolkit's own strings, e.g. to ship your own translation.
         *
         * Hook name: "{slug}/i18n/library_mofile".
         *
         * @param string $file   Absolute path of the .mo file.
         * @param string $locale The locale being loaded.
         */
        $file = (string) apply_filters($this->identity->hook('i18n/library_mofile'), $file, $locale);

        if (!is_readable($file)) {
            return false;
        }

        return load_textdomain(self::DOMAIN, $file, $locale);
    }
}
