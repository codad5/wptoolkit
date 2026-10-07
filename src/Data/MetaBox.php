<?php

/**
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

namespace Codad5\WPToolkit\Data;

use Codad5\WPToolkit\Contracts\Cache\CacheStore;
use Codad5\WPToolkit\Data\Field\Field;
use Codad5\WPToolkit\Data\Field\FieldTypes;
use Codad5\WPToolkit\Data\Field\FieldValidator;
use Codad5\WPToolkit\Exceptions\InvalidConfigException;
use Codad5\WPToolkit\Foundation\HookRegistrar;
use Codad5\WPToolkit\Foundation\Identity;
use Codad5\WPToolkit\Support\Validation\Rules;
use WP_Post;

/**
 * A meta box of fields on one post type (rebuilds 0.x `DB\MetaBox` on the field system).
 *
 * Storage is exactly 0.x's (ADR-0016): key `{box_id}_{post_type}_{field}` or a custom prefix; one
 * meta row per value for multiple fields. Saving checks autosave, revisions, post type, nonce and
 * `edit_post` before anything is written; invalid fields are skipped and reported, valid ones save.
 */
final class MetaBox
{
    /** @var array<string, Field> */
    private array $fields = [];

    /** @var 'normal'|'side'|'advanced' */
    private string $context = 'normal';

    /** @var 'high'|'core'|'default'|'low' */
    private string $priority = 'default';

    private ?string $prefix = null;

    private bool $quickEditNoncePrinted = false;

    /**
     * @param list<Field> $fields
     */
    public function __construct(
        public readonly string $id,
        private readonly string $title,
        public readonly string $postType,
        array $fields,
        private readonly FieldTypes $types,
        private readonly Identity $identity,
        private readonly CacheStore $flash
    ) {
        if (preg_match('/^[a-z0-9_\-]+$/', $id) !== 1) {
            throw new InvalidConfigException(sprintf('Meta box id "%s" may contain only lowercase letters, digits, "_" and "-".', $id));
        }
        foreach ($fields as $field) {
            $types->get($field->type); // fail now, not on save, for an unknown type
            $unfillable = $field->isMultiple() || $field->isSensitive() || in_array($field->type, self::NO_QUICK_EDIT, true);
            if ($unfillable && $field->setting('quick_edit', false) === true) {
                throw new InvalidConfigException(sprintf(
                    'Field "%s" cannot be quick-edited: quick edit supports single, non-sensitive fields other than %s.',
                    $field->name,
                    implode(', ', self::NO_QUICK_EDIT)
                ));
            }
            $this->fields[$field->name] = $field;
        }
    }

    /** Types whose input needs more than a plain value set by script. */
    private const NO_QUICK_EDIT = ['media', 'wp_media', 'wysiwyg'];

    /**
     * Store under a custom prefix instead of `{box_id}_{post_type}_` — 0.x's `set_prefix()`.
     */
    public function prefix(string $prefix): self
    {
        $this->prefix = $prefix;

        return $this;
    }

    /**
     * @param 'normal'|'side'|'advanced' $context
     * @param 'high'|'core'|'default'|'low' $priority
     */
    public function placement(string $context, string $priority = 'default'): self
    {
        $this->context = $context;
        $this->priority = $priority;

        return $this;
    }

    /**
     * @return array<string, Field>
     */
    public function fields(): array
    {
        return $this->fields;
    }

    public function metaKey(string $fieldName): string
    {
        $this->field($fieldName);

        return $this->identity->metaKey($this->id, $this->postType, $fieldName, $this->prefix);
    }

    // --- Reading ------------------------------------------------------------------------------

    /**
     * One field's value as code should see it, or its default when nothing is stored.
     */
    public function value(int $postId, string $fieldName): mixed
    {
        $field = $this->field($fieldName);
        $key = $this->metaKey($fieldName);
        $type = $this->types->get($field->type);

        if (!metadata_exists('post', $postId, $key)) {
            return $field->defaultValue();
        }

        if ($field->isMultiple()) {
            $stored = get_post_meta($postId, $key, false);
            return array_values(array_filter(
                array_map(static fn ($v) => $type->read($v, $field), is_array($stored) ? $stored : []),
                static fn ($v) => $v !== null
            ));
        }

        return $type->read(get_post_meta($postId, $key, true), $field);
    }

    /**
     * Every field's value, without sensitive fields (ADR-0021) — read those with value().
     *
     * @return array<string, mixed>
     */
    public function values(int $postId): array
    {
        $values = [];
        foreach ($this->fields as $name => $field) {
            if (!$field->isSensitive()) {
                $values[$name] = $this->value($postId, $name);
            }
        }

        return $values;
    }

    // --- Writing ------------------------------------------------------------------------------

    /**
     * Validate and store one field. Returns the error messages; empty means it was saved.
     *
     * @return list<string>
     */
    public function save(int $postId, string $fieldName, mixed $value): array
    {
        $field = $this->field($fieldName);
        $errors = (new FieldValidator($this->types))->validate($field, $value);
        if ($errors !== []) {
            return $errors;
        }

        $this->write($postId, $field, $value);

        return [];
    }

    private function write(int $postId, Field $field, mixed $value): void
    {
        $key = $this->metaKey($field->name);
        $type = $this->types->get($field->type);

        if ($field->isMultiple()) {
            delete_post_meta($postId, $key);
            foreach (is_array($value) ? $value : [$value] as $item) {
                $clean = $type->sanitize($item, $field);
                if ($clean !== null && $clean !== '') {
                    add_post_meta($postId, $key, wp_slash($clean)); // the meta API unslashes
                }
            }
            return;
        }

        $clean = Rules::isEmpty($value) ? null : $type->sanitize($value, $field);
        if ($clean === null || $clean === '') {
            delete_post_meta($postId, $key);
            return;
        }

        update_post_meta($postId, $key, wp_slash($clean));
    }

    // --- WordPress integration -----------------------------------------------------------------

    public function register(HookRegistrar $hooks): void
    {
        $hooks->addAction('add_meta_boxes_' . $this->postType, [$this, 'addToScreen']);
        $hooks->addAction('save_post_' . $this->postType, [$this, 'handleSave'], 10, 2);
        $hooks->addAction('admin_notices', [$this, 'printErrors']);

        if ($this->quickEditFields() !== []) {
            $hooks->addAction('quick_edit_custom_box', [$this, 'renderQuickEdit'], 10, 2);
            $hooks->addAction('add_inline_data', [$this, 'printInlineData'], 10, 1);
            $hooks->addAction('admin_enqueue_scripts', [$this, 'enqueueQuickEdit']);
        }
    }

    /**
     * Fields marked `->quickEdit()`, keyed by meta key.
     *
     * @return array<string, Field>
     */
    public function quickEditFields(): array
    {
        $fields = [];
        foreach ($this->fields as $name => $field) {
            if ($field->setting('quick_edit', false) === true) {
                $fields[$this->metaKey($name)] = $field;
            }
        }

        return $fields;
    }

    /**
     * @internal Hooked to quick_edit_custom_box: one input for the list-table column named after
     * the field's meta key (Admin\Columns uses that id). Values arrive from printInlineData().
     */
    public function renderQuickEdit(string $column, string $postType): void
    {
        $field = $this->quickEditFields()[$column] ?? null;
        if ($postType !== $this->postType || $field === null) {
            return;
        }

        if (!$this->quickEditNoncePrinted) {
            $this->quickEditNoncePrinted = true;
            wp_nonce_field($this->nonceAction(), $this->nonceName(), false);
        }

        $id = $this->identity->handle('quick-' . $this->id . '-' . $field->name);
        echo '<fieldset class="inline-edit-col-right"><div class="inline-edit-col"><label class="inline-edit-group">';
        printf('<span class="title">%s</span>', esc_html($field->labelText()));
        // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- FieldType::render() escapes everything it outputs.
        echo $this->types->get($field->type)->render($field->required(false), $column, $id, null);
        echo '</label></div></fieldset>';
    }

    /**
     * @internal Hooked to add_inline_data: the row's current values, in the hidden block WordPress
     * already prints for quick edit. Replaces 0.x's public Ajax fetch (S2) — nothing new is exposed:
     * only users who can edit the post see the row's inline data, and sensitive fields are never quick-editable.
     */
    public function printInlineData(WP_Post $post): void
    {
        if ($post->post_type !== $this->postType || !current_user_can('edit_post', $post->ID)) {
            return;
        }

        foreach ($this->quickEditFields() as $key => $field) {
            $value = $this->value($post->ID, $field->name);
            if (is_bool($value)) {
                $value = $value ? '1' : '0';
            }
            if (is_scalar($value) || $value === null) {
                printf('<div class="wptoolkit-quick-edit" data-key="%s">%s</div>', esc_attr($key), esc_html((string) $value));
            }
        }
    }

    /**
     * @internal Hooked to admin_enqueue_scripts: fills the quick edit inputs from the inline data.
     */
    public function enqueueQuickEdit(string $hookSuffix): void
    {
        $screen = function_exists('get_current_screen') ? get_current_screen() : null;
        if ($hookSuffix !== 'edit.php' || $screen === null || $screen->post_type !== $this->postType) {
            return;
        }

        $keys = (string) wp_json_encode(array_keys($this->quickEditFields()));
        wp_add_inline_script('inline-edit-post', sprintf(self::QUICK_EDIT_JS, $keys));
    }

    private const QUICK_EDIT_JS = <<<'JS'
        (function ($, keys) {
            if (!window.inlineEditPost) { return; }
            var edit = window.inlineEditPost.edit;
            window.inlineEditPost.edit = function (id) {
                edit.apply(this, arguments);
                var postId = typeof id === 'object' ? parseInt(this.getId(id), 10) : id;
                var data = $('#inline_' + postId), row = $('#edit-' + postId);
                keys.forEach(function (key) {
                    var source = data.find('.wptoolkit-quick-edit').filter(function () { return $(this).data('key') === key; });
                    if (!source.length) { return; }
                    var value = source.text();
                    row.find('[name="' + key + '"]').not('[type="hidden"]').each(function () {
                        if (this.type === 'checkbox') { this.checked = value === '1'; }
                        else if (this.type === 'radio') { this.checked = this.value === value; }
                        else { $(this).val(value); }
                    });
                });
            };
        })(jQuery, %s);
        JS;

    /**
     * @internal Hooked to add_meta_boxes_{post_type}.
     */
    public function addToScreen(): void
    {
        add_meta_box($this->identity->handle('box-' . $this->id), $this->title, [$this, 'render'], $this->postType, $this->context, $this->priority);
    }

    /**
     * @internal Meta box callback.
     */
    public function render(WP_Post $post): void
    {
        wp_nonce_field($this->nonceAction(), $this->nonceName());

        foreach ($this->fields as $name => $field) {
            $key = $this->metaKey($name);
            $id = $this->identity->handle($this->id . '-' . $name);
            $value = $this->value($post->ID, $name);
            $descriptionId = $id . '-description';
            $description = $field->setting('description');
            $hasDescription = is_string($description) && $description !== '';
            $input = $hasDescription ? $field->attributes(['aria-describedby' => $descriptionId] + $field->htmlAttributes()) : $field;

            echo '<p class="wptoolkit-field">';
            printf('<label for="%s"><strong>%s</strong></label><br />', esc_attr($id), esc_html($field->labelText()));
            // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- FieldType::render() escapes everything it outputs.
            echo $this->types->get($field->type)->render($input, $key, $id, $value);
            if ($hasDescription) {
                printf('<br /><span class="description" id="%s">%s</span>', esc_attr($descriptionId), esc_html($description));
            }
            echo '</p>';
        }
    }

    /**
     * @internal Hooked to save_post_{post_type}.
     */
    public function handleSave(int $postId, WP_Post $post): void
    {
        if (!$this->maySave($postId, $post)) {
            return;
        }

        $errors = [];
        foreach ($this->fields as $name => $field) {
            $key = $this->metaKey($name);
            // phpcs:ignore WordPress.Security.NonceVerification.Missing -- verified in maySave().
            if (!array_key_exists($key, $_POST)) {
                continue; // not on this form (e.g. quick edit) — leave it alone
            }
            // phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- sanitized by the field type in save().
            $submitted = wp_unslash($_POST[$key]);

            if ($field->isSensitive() && Rules::isEmpty($submitted)) {
                continue; // blank means "keep the stored secret" (ADR-0021)
            }

            foreach ($this->save($postId, $name, $submitted) as $message) {
                $errors[] = $message;
            }
        }

        $errorsKey = $this->errorsKey();
        if ($errors !== [] && $errorsKey !== null) {
            $this->flash->set($errorsKey, $errors, 60);
        }
    }

    /**
     * @internal Shows the previous save's validation errors once.
     */
    public function printErrors(): void
    {
        $key = $this->errorsKey();
        if ($key === null) {
            return;
        }

        $errors = $this->flash->get($key);
        if (!is_array($errors) || $errors === []) {
            return;
        }
        $this->flash->delete($key);

        echo '<div class="notice notice-error" role="alert"><p>' . esc_html__('Some fields were not saved:', 'wptoolkit') . '</p><ul>';
        foreach ($errors as $error) {
            echo '<li>' . esc_html(is_string($error) ? $error : '') . '</li>';
        }
        echo '</ul></div>';
    }

    /**
     * The guards every save passes before anything is written.
     */
    public function maySave(int $postId, WP_Post $post): bool
    {
        if (defined('DOING_AUTOSAVE') && constant('DOING_AUTOSAVE')) {
            return false;
        }
        if (wp_is_post_revision($postId) !== false || $post->post_type !== $this->postType) {
            return false;
        }

        // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- passed straight to wp_verify_nonce().
        $nonce = isset($_POST[$this->nonceName()]) ? wp_unslash($_POST[$this->nonceName()]) : '';
        if (!is_string($nonce) || wp_verify_nonce($nonce, $this->nonceAction()) === false) {
            return false;
        }

        return current_user_can('edit_post', $postId);
    }

    public function nonceName(): string
    {
        return $this->identity->ajaxAction('metabox_' . $this->id . '_nonce');
    }

    public function nonceAction(): string
    {
        return $this->identity->nonceAction('metabox.' . $this->id . '.save');
    }

    private function errorsKey(): ?string
    {
        $user = function_exists('get_current_user_id') ? get_current_user_id() : 0;

        return $user > 0 ? 'metabox_errors_' . $this->id . '_' . $user : null;
    }

    private function field(string $name): Field
    {
        return $this->fields[$name] ?? throw new InvalidConfigException(sprintf('Meta box "%s" has no field "%s".', $this->id, $name));
    }
}
