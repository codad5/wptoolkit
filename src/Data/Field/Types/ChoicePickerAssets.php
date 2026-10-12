<?php

/**
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

namespace Codad5\WPToolkit\Data\Field\Types;

/**
 * The script and style of the multiple-choice picker, printed once in the page footer.
 *
 * A field type renders HTML and has no AssetManager, so the first picker on a page queues one
 * footer hook (a footer hook is a legitimate place to print assets, rule 10). The checkboxes work
 * without it; it only turns them into chips with search. The files ship with the library and are
 * read once. If they are missing the list simply stays a list.
 */
final class ChoicePickerAssets
{
    private bool $queued = false;

    private bool $printed = false;

    /**
     * Ask for the picker's assets to be printed in this page's footer. Safe to call per field.
     */
    public function queue(): void
    {
        if ($this->queued) {
            return;
        }
        $this->queued = true;

        // Admin screens and the front end have different footer hooks; whichever runs prints once.
        add_action('admin_print_footer_scripts', [$this, 'print'], 20);
        add_action('wp_print_footer_scripts', [$this, 'print'], 20);
    }

    /**
     * @internal Footer hook.
     */
    public function print(): void
    {
        if ($this->printed) {
            return;
        }
        $this->printed = true;

        $directory = dirname(__DIR__, 4) . '/resources';
        $css = is_readable($directory . '/css/picker.css') ? (string) file_get_contents($directory . '/css/picker.css') : '';
        $js = is_readable($directory . '/js/picker.js') ? (string) file_get_contents($directory . '/js/picker.js') : '';
        if ($css === '' || $js === '' || stripos($js, '</script') !== false || stripos($css, '</style') !== false) {
            return;
        }

        // Static files from this library, not user input: printed as they are.
        // phpcs:disable WordPress.Security.EscapeOutput.OutputNotEscaped
        echo '<style id="wptoolkit-picker-css">' . $css . '</style>';
        echo '<script id="wptoolkit-picker-js">' . $js . '</script>';
        // phpcs:enable
    }
}
