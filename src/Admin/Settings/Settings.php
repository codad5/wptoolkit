<?php

/**
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

namespace Codad5\WPToolkit\Admin\Settings;

use Closure;
use Codad5\WPToolkit\Contracts\Log\Logger;
use Codad5\WPToolkit\Contracts\Log\RedactsKeys;
use Codad5\WPToolkit\Data\Field\Field;
use Codad5\WPToolkit\Data\Field\FieldTypes;
use Codad5\WPToolkit\Data\Field\FieldValidator;
use Codad5\WPToolkit\Exceptions\InvalidConfigException;
use Codad5\WPToolkit\Foundation\Identity;
use Codad5\WPToolkit\Support\Crypto\SecretBox;
use Codad5\WPToolkit\Support\Validation\Rules;

/**
 * A plugin's settings, built from fields (ports 0.x `Settings`). Storage is 0.x's (ADR-0016): one
 * option per setting, `{slug}_{sanitize_key(name)}`.
 *
 * **Sensitive fields** (`->sensitive()`, ADR-0021) are readable only by name: `get('api_key')`
 * returns it; `all()` — and so every export, REST or Ajax response built from it — leaves it out,
 * `forScript()` refuses it, and the logger is told to redact it. Add `->with('encrypt', true)` to
 * keep it encrypted at rest.
 *
 *     $settings = new Settings([$f->url('api_base_url')->required(), $f->password('api_key')->sensitive()], …);
 *     $settings->get('api_key');   // by name only
 *     $settings->all();            // ['api_base_url' => …] — no api_key
 */
final class Settings
{
    private const MISSING = "\0wptoolkit-missing";

    /** @var array<string, Field> */
    private array $fields = [];

    /** @var array<string, Closure(mixed): mixed> */
    private array $upcasters = [];

    /** @var array<string, true> */
    private array $unreadable = [];

    /**
     * @param list<Field> $fields
     * @param SecretBox|null $box Required when any field has `->with('encrypt', true)`.
     */
    public function __construct(
        array $fields,
        private readonly FieldTypes $types,
        private readonly Identity $identity,
        private readonly ?SecretBox $box = null,
        ?Logger $logger = null
    ) {
        foreach ($fields as $field) {
            $types->get($field->type);
            if ($this->encrypts($field) && $box === null) {
                throw new InvalidConfigException(sprintf('Setting "%s" is encrypted but no SecretBox was given.', $field->name));
            }
            $this->fields[$field->name] = $field;
        }

        if ($logger instanceof RedactsKeys && $this->sensitiveKeys() !== []) {
            $keys = $this->sensitiveKeys();
            $logger->redactKeys(...$keys, ...array_map(fn (string $k): string => $this->optionKey($k), $keys));
        }
    }

    /**
     * Turn an old stored shape into the current one on read (ADR-0018). Writes store the current shape.
     *
     * @param Closure(mixed): mixed $upcaster Receives the stored value (never the "missing" marker).
     */
    public function upcast(string $name, Closure $upcaster): self
    {
        $this->field($name);
        $this->upcasters[$name] = $upcaster;

        return $this;
    }

    /**
     * One setting by name — the only way to read a sensitive one.
     */
    public function get(string $name): mixed
    {
        $field = $this->field($name);
        $stored = get_option($this->optionKey($name), self::MISSING);

        if ($stored === self::MISSING) {
            return $field->defaultValue();
        }
        if (SecretBox::isSealed($stored) && $this->box !== null) {
            $stored = $this->box->open((string) $stored);
            if ($stored === null) {
                $this->unreadable[$name] = true;
                return $field->defaultValue();
            }
        }
        if (isset($this->upcasters[$name])) {
            $stored = ($this->upcasters[$name])($stored);
        }

        return $this->read($field, $stored);
    }

    /**
     * Every setting (of a group), **without sensitive ones** — name those with get().
     *
     * @return array<string, mixed>
     */
    public function all(?string $group = null): array
    {
        $values = [];
        foreach ($this->fields as $name => $field) {
            if (!$field->isSensitive() && ($group === null || $this->groupOf($field) === $group)) {
                $values[$name] = $this->get($name);
            }
        }

        return $values;
    }

    /**
     * Named settings for `window.wptoolkit[slug]`. Asking for a sensitive one is an error.
     *
     * @return array<string, mixed>
     * @throws InvalidConfigException
     */
    public function forScript(string ...$names): array
    {
        $values = [];
        foreach ($names as $name) {
            if ($this->field($name)->isSensitive()) {
                throw new InvalidConfigException(sprintf('Setting "%s" is sensitive and cannot be sent to the browser (ADR-0021).', $name));
            }
            $values[$name] = $this->get($name);
        }

        return $values;
    }

    /**
     * Validate and store. Returns the error messages; empty means it was saved.
     *
     * @return list<string>
     */
    public function set(string $name, mixed $value): array
    {
        $field = $this->field($name);
        $errors = (new FieldValidator($this->types))->validate($field, $value);
        if ($errors !== []) {
            return $errors;
        }

        update_option($this->optionKey($name), $this->toStored($field, $value));
        unset($this->unreadable[$name]);

        return [];
    }

    public function delete(string $name): void
    {
        delete_option($this->optionKey($name));
    }

    /**
     * Whether an encrypted setting could not be decrypted (salts rotated): it must be entered again.
     */
    public function isUnreadable(string $name): bool
    {
        $this->get($name);

        return isset($this->unreadable[$name]);
    }

    /**
     * @return list<string>
     */
    public function sensitiveKeys(): array
    {
        return array_keys(array_filter($this->fields, static fn (Field $f): bool => $f->isSensitive()));
    }

    public function optionKey(string $name): string
    {
        return $this->identity->optionKey($name);
    }

    public function field(string $name): Field
    {
        return $this->fields[$name] ?? throw new InvalidConfigException(sprintf('There is no setting "%s".', $name));
    }

    /**
     * @return array<string, Field>
     */
    public function fields(): array
    {
        return $this->fields;
    }

    public function groupOf(Field $field): string
    {
        $group = $field->setting('group', 'general');

        return is_string($group) && $group !== '' ? $group : 'general';
    }

    /**
     * @internal What set() and the settings form store: sanitized, then sealed if the field encrypts.
     */
    public function toStored(Field $field, mixed $value): mixed
    {
        $type = $this->types->get($field->type);

        if ($field->isMultiple()) {
            $stored = [];
            foreach (is_array($value) ? $value : [$value] as $item) {
                $clean = Rules::isEmpty($item) ? null : $type->sanitize($item, $field);
                if ($clean !== null && $clean !== '') {
                    $stored[] = $clean;
                }
            }
            return $stored;
        }

        $stored = Rules::isEmpty($value) ? '' : $type->sanitize($value, $field);
        if ($this->encrypts($field) && $this->box !== null && is_scalar($stored) && $stored !== '') {
            return $this->box->seal((string) $stored);
        }

        return $stored ?? '';
    }

    private function read(Field $field, mixed $stored): mixed
    {
        $type = $this->types->get($field->type);

        if ($field->isMultiple()) {
            $values = [];
            foreach (is_array($stored) ? $stored : [] as $item) {
                $read = $type->read($item, $field);
                if ($read !== null) {
                    $values[] = $read;
                }
            }
            return $values;
        }

        // 0.x stored an unticked checkbox as false, i.e. '' — that is a value, not "empty".
        if ($stored === '' && $field->type !== 'checkbox') {
            return $field->defaultValue();
        }

        return $type->read($stored, $field);
    }

    private function encrypts(Field $field): bool
    {
        return $field->setting('encrypt', false) === true;
    }
}
