<?php

/**
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

namespace Codad5\WPToolkit\Admin\Settings;

use Codad5\WPToolkit\Data\Field\Field;
use Codad5\WPToolkit\Data\Field\FieldTypes;
use Codad5\WPToolkit\Data\Field\FieldValidator;
use Codad5\WPToolkit\Foundation\HookRegistrar;
use Codad5\WPToolkit\Foundation\Identity;
use Codad5\WPToolkit\Support\Crypto\SecretBox;

/**
 * Settings on an admin page through the WordPress Settings API: `options.php` checks the nonce and
 * `manage_options`; each field validates before it is stored and an invalid value keeps the old one
 * with an error shown. Sensitive fields (ADR-0021) render empty, keep their value when submitted
 * blank, and have an explicit "clear" checkbox.
 *
 *     $form = new SettingsForm($settings, $types, $identity);
 *     $form->section('api', __('API', 'my-plugin'))->register($hooks, 'my-plugin-settings');
 *     // in the page callback:
 *     $form->render('my-plugin-settings');
 */
final class SettingsForm
{
    /** @var array<string, string> group => section title */
    private array $sections = [];

    public function __construct(
        private readonly Settings $settings,
        private readonly FieldTypes $types,
        private readonly Identity $identity
    ) {
    }

    public function section(string $group, string $title): self
    {
        $this->sections[$group] = $title;

        return $this;
    }

    public function register(HookRegistrar $hooks, string $page): void
    {
        $hooks->addAction('admin_init', fn () => $this->registerFields($page));
    }

    /**
     * The `<form>` for the page: Settings API nonce fields, the sections, a save button.
     */
    public function render(string $page): void
    {
        echo '<form method="post" action="options.php" novalidate="novalidate">';
        settings_fields($this->optionGroup());
        do_settings_sections($page);
        submit_button();
        echo '</form>';
    }

    public function optionGroup(): string
    {
        return $this->identity->optionKey('settings');
    }

    /**
     * @internal Hooked to admin_init.
     */
    public function registerFields(string $page): void
    {
        foreach ($this->settings->fields() as $name => $field) {
            $group = $this->settings->groupOf($field);
            if (!isset($this->sections[$group])) {
                $this->sections[$group] = ucwords(str_replace(['_', '-'], ' ', $group));
            }
        }
        foreach ($this->sections as $group => $title) {
            add_settings_section($this->identity->handle('section-' . $group), $title, '__return_null', $page);
        }

        foreach ($this->settings->fields() as $name => $field) {
            $option = $this->settings->optionKey($name);
            register_setting($this->optionGroup(), $option, [
                'sanitize_callback' => fn (mixed $submitted): mixed => $this->sanitize($field, $submitted),
                'show_in_rest' => false,
            ]);
            add_settings_field(
                $option,
                esc_html($field->labelText()),
                fn () => $this->renderField($field),
                $page,
                $this->identity->handle('section-' . $this->settings->groupOf($field)),
                ['label_for' => $this->inputId($name)]
            );
        }
    }

    /**
     * @internal The register_setting() sanitize callback: what is stored for one submitted value.
     */
    public function sanitize(Field $field, mixed $submitted): mixed
    {
        $option = $this->settings->optionKey($field->name);
        $current = get_option($option, '');

        // WordPress runs this twice when the option is first created; a sealed value is already final.
        if (SecretBox::isSealed($submitted)) {
            return $submitted;
        }

        if ($field->isSensitive()) {
            if ($this->clearRequested($field->name)) {
                return '';
            }
            if ($submitted === null || $submitted === '') {
                return $current; // blank means "keep the saved secret"
            }
        }

        $errors = (new FieldValidator($this->types))->validate($field, $submitted);
        if ($errors !== []) {
            foreach ($errors as $i => $message) {
                add_settings_error($option, $option . '-' . $i, $message);
            }
            return $current;
        }

        return $this->settings->toStored($field, $submitted);
    }

    private function renderField(Field $field): void
    {
        $name = $field->name;
        $id = $this->inputId($name);
        $value = $field->isSensitive() ? null : $this->settings->get($name);
        $describedBy = $id . '-description';
        $description = $field->setting('description');
        $input = is_string($description) && $description !== ''
            ? $field->attributes(['aria-describedby' => $describedBy] + $field->htmlAttributes())
            : $field;

        // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- FieldType::render() escapes everything it outputs.
        echo $this->types->get($field->type)->render($input, $this->settings->optionKey($name), $id, $value);

        if ($field->isSensitive() && $this->hasStoredValue($name)) {
            printf(
                ' <label><input type="checkbox" name="%s[]" value="%s" /> %s</label>',
                esc_attr($this->clearFieldName()),
                esc_attr($name),
                esc_html__('Clear the saved value', 'wptoolkit')
            );
        }
        if ($field->isSensitive() && $this->settings->isUnreadable($name)) {
            printf(
                '<p class="description">%s</p>',
                esc_html__('The saved value can no longer be decrypted (were the site\'s salts changed?). Enter it again.', 'wptoolkit')
            );
        }
        if (is_string($description) && $description !== '') {
            printf('<p class="description" id="%s">%s</p>', esc_attr($describedBy), esc_html($description));
        }
    }

    private function hasStoredValue(string $name): bool
    {
        $stored = get_option($this->settings->optionKey($name), '');

        return $stored !== '' && $stored !== false && $stored !== null;
    }

    private function clearRequested(string $name): bool
    {
        // phpcs:ignore WordPress.Security.NonceVerification.Missing -- options.php verified the Settings API nonce before sanitizing.
        $clear = isset($_POST[$this->clearFieldName()]) ? array_map('sanitize_key', (array) wp_unslash($_POST[$this->clearFieldName()])) : [];

        return in_array($name, $clear, true);
    }

    private function clearFieldName(): string
    {
        return $this->identity->optionKey('settings_clear');
    }

    private function inputId(string $name): string
    {
        return $this->identity->handle('setting-' . $name);
    }
}
