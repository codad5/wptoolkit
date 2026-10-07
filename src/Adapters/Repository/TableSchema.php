<?php

/**
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

namespace Codad5\WPToolkit\Adapters\Repository;

use Codad5\WPToolkit\Data\Attributes\Table;
use Codad5\WPToolkit\Data\Entity;
use Codad5\WPToolkit\Data\EntityDefinition;
use Codad5\WPToolkit\Data\Field\Field;
use Codad5\WPToolkit\Exceptions\InvalidConfigException;
use Codad5\WPToolkit\Foundation\Identity;

/**
 * The table a #[Table] entity is stored in: `id` plus one nullable column per field. Run it from a
 * migration (ADR-0018); dbDelta() adds missing columns, it never drops or narrows them.
 *
 *     public function up(): void { TableSchema::install(Book::class, $this->identity); }
 *
 * Override a column's SQL type with `$f->text('isbn')->with('column', 'varchar(20)')`.
 */
final class TableSchema
{
    /**
     * @param class-string<Entity> $entityClass
     */
    public static function tableName(string $entityClass, Identity $identity): string
    {
        global $wpdb;

        return $wpdb->prefix . $identity->table(self::attribute($entityClass)->name);
    }

    /**
     * Field name → column name (hyphens are not valid in unquoted identifiers).
     */
    public static function column(string $field): string
    {
        return str_replace('-', '_', $field);
    }

    /**
     * @param class-string<Entity> $entityClass
     */
    public static function createSql(string $entityClass, Identity $identity): string
    {
        global $wpdb;

        $columns = ['id bigint(20) unsigned NOT NULL AUTO_INCREMENT'];
        foreach (EntityDefinition::of($entityClass)->fields as $field) {
            $columns[] = self::column($field->name) . ' ' . self::sqlType($field) . ' NULL';
        }
        $columns[] = 'PRIMARY KEY  (id)'; // dbDelta needs the two spaces

        return sprintf(
            "CREATE TABLE %s (\n%s\n) %s;",
            self::tableName($entityClass, $identity),
            implode(",\n", $columns),
            $wpdb->get_charset_collate()
        );
    }

    /**
     * Create or extend the table.
     *
     * @param class-string<Entity> $entityClass
     */
    public static function install(string $entityClass, Identity $identity): void
    {
        if (!function_exists('dbDelta')) {
            require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        }

        dbDelta(self::createSql($entityClass, $identity));
    }

    /**
     * @param class-string<Entity> $entityClass
     */
    public static function drop(string $entityClass, Identity $identity): void
    {
        global $wpdb;

        $table = self::tableName($entityClass, $identity);
        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.SchemaChange -- table name built from the identity; DDL cannot be prepared.
        $wpdb->query("DROP TABLE IF EXISTS {$table}");
    }

    private static function sqlType(Field $field): string
    {
        $custom = $field->setting('column');
        if (is_string($custom) && preg_match('/^[a-z]+(\(\d+(,\d+)?\))?( unsigned)?$/i', $custom) === 1) {
            return $custom;
        }
        if ($field->isMultiple()) {
            return 'longtext';
        }

        return match ($field->type) {
            'number' => 'double',
            'checkbox' => 'tinyint(1)',
            'media', 'wp_media' => 'bigint(20) unsigned',
            'date' => 'date',
            default => 'longtext',
        };
    }

    /**
     * @param class-string<Entity> $entityClass
     */
    private static function attribute(string $entityClass): Table
    {
        $storage = EntityDefinition::of($entityClass)->storage;

        return $storage instanceof Table
            ? $storage
            : throw new InvalidConfigException(sprintf('%s needs #[Table] to be stored in a custom table.', $entityClass));
    }
}
