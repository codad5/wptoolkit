<?php

/**
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

namespace Codad5\WPToolkit\Data;

use Codad5\WPToolkit\Contracts\Cache\CacheStore;
use Codad5\WPToolkit\Data\Attributes\PostType;
use Codad5\WPToolkit\Data\Attributes\Taxonomy;
use Codad5\WPToolkit\Data\Field\Field;
use Codad5\WPToolkit\Data\Field\FieldTypes;
use Codad5\WPToolkit\Exceptions\InvalidConfigException;
use Codad5\WPToolkit\Foundation\HookRegistrar;
use Codad5\WPToolkit\Foundation\Identity;

/**
 * Tells WordPress about entities and meta boxes: registers post types and taxonomies on `init`
 * and adds the edit-screen meta box whose keys match the repository's.
 *
 *     $data->entity(Book::class);                                   // post type, taxonomies, meta box
 *     $data->metaBox('extra', __('Extra', 'my-plugin'), 'page', [$f->text('subtitle')]);
 */
final class EntityRegistrar
{
    /** WordPress's limits: post type keys ≤ 20 characters, taxonomy keys ≤ 32. */
    private const MAX_POST_TYPE = 20;
    private const MAX_TAXONOMY = 32;

    /** @var array<string, MetaBox> */
    private array $metaBoxes = [];

    public function __construct(
        private readonly HookRegistrar $hooks,
        private readonly FieldTypes $types,
        private readonly Identity $identity,
        private readonly CacheStore $flash
    ) {
    }

    /**
     * Register a #[PostType] entity's post type (unless `register: false`), its taxonomies and a
     * meta box of its non-column fields titled `$boxTitle`.
     *
     * @param class-string<Entity> $entityClass
     */
    public function entity(string $entityClass, ?string $boxTitle = null): ?MetaBox
    {
        $definition = EntityDefinition::of($entityClass);
        $postType = $definition->storage;
        if (!$postType instanceof PostType) {
            throw new InvalidConfigException(sprintf('%s is not a #[PostType] entity; only post types have edit screens.', $entityClass));
        }
        self::assertKey('Post type', $postType->name, self::MAX_POST_TYPE);

        if ($postType->register) {
            $this->hooks->addAction('init', fn () => $this->registerPostType($postType), 5);
        }
        foreach ($definition->taxonomies as $taxonomy) {
            self::assertKey('Taxonomy', $taxonomy->name, self::MAX_TAXONOMY);
            if ($taxonomy->register) {
                $this->hooks->addAction('init', fn () => $this->registerTaxonomy($taxonomy, $postType->name), 5);
            }
        }

        $fields = array_values(array_filter(
            $definition->fields,
            static fn (Field $f): bool => $postType->columnFor($f->name) === null
        ));
        if ($fields === []) {
            return null;
        }

        $box = $this->metaBox($postType->box, $boxTitle ?? __('Details', 'wptoolkit'), $postType->name, $fields);
        if ($postType->metaPrefix !== null) {
            $box->prefix($postType->metaPrefix);
        }

        return $box;
    }

    /**
     * @param list<Field> $fields
     */
    public function metaBox(string $id, string $title, string $postType, array $fields): MetaBox
    {
        $key = $postType . '/' . $id;
        if (isset($this->metaBoxes[$key])) {
            throw new InvalidConfigException(sprintf('Meta box "%s" is already registered on "%s".', $id, $postType));
        }

        $box = new MetaBox($id, $title, $postType, $fields, $this->types, $this->identity, $this->flash);
        $box->register($this->hooks);

        return $this->metaBoxes[$key] = $box;
    }

    private static function assertKey(string $what, string $key, int $max): void
    {
        if ($key === '' || sanitize_key($key) !== $key || strlen($key) > $max) {
            throw new InvalidConfigException(sprintf('%s "%s" must be 1–%d lowercase letters, digits, "_" or "-".', $what, $key, $max));
        }
    }

    private function registerPostType(PostType $postType): void
    {
        $singular = $postType->singular ?? ucfirst(str_replace(['_', '-'], ' ', $postType->name));
        $plural = $postType->plural ?? $singular . 's';

        $key = sanitize_key($postType->name); // validated in entity(); this narrows the type
        if ($key === '') {
            return;
        }

        register_post_type($key, $postType->args + [
            'labels' => ['name' => $plural, 'singular_name' => $singular],
            'public' => $postType->public,
            'show_ui' => true,
            'show_in_rest' => $postType->public,
            'supports' => ['title', 'editor'],
        ]);
    }

    private function registerTaxonomy(Taxonomy $taxonomy, string $postType): void
    {
        $singular = $taxonomy->singular ?? ucfirst(str_replace(['_', '-'], ' ', $taxonomy->name));
        $plural = $taxonomy->plural ?? $singular . 's';

        register_taxonomy(sanitize_key($taxonomy->name), $postType, $taxonomy->args + [
            'labels' => ['name' => $plural, 'singular_name' => $singular],
            'hierarchical' => $taxonomy->hierarchical,
            'public' => $taxonomy->public,
            'show_ui' => true,
            'show_admin_column' => true,
            'show_in_rest' => $taxonomy->public,
        ]);
    }
}
