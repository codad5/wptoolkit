# Data: fields, meta boxes, entities, queries, search and migrations

## Fields

Fields are immutable definitions built with `FieldFactory`. Their type decides how a value is
sanitized, validated, rendered and read back.

```php
$f->text('isbn')->label(__('ISBN', 'my-books'))->required()->rules('max:17');
$f->select('format', ['paperback' => __('Paperback', 'my-books'), 'hardcover' => __('Hardcover', 'my-books')])->default('paperback');
$f->number('pages')->rules('min:1');
$f->checkbox('featured');
$f->media('gallery')->multiple();
$f->password('api_key')->sensitive();          // never rendered back; left out of bulk reads
$f->date('due')->quickEdit();                  // editable from the list table
```

Types: `text`, `textarea`, `email`, `url`, `tel`, `number`, `date`, `color`, `hidden`, `password`,
`select`, `radio`, `checkbox`, `media` (alias `wp_media`), `wysiwyg`. Add your own without touching
the library: implement `FieldType` (or `wp {slug} make:field StarRating`) and
`$fieldTypes->register('stars', new StarRatingFieldType())`.

## Meta boxes

```php
$box = $registrar->metaBox('details', __('Details', 'my-books'), 'book', [$f->text('isbn'), $f->media('cover')]);
$box->value($postId, 'isbn');
```

Saving checks autosave, revisions, the post type, the nonce and `edit_post` before writing; invalid
fields are skipped and reported in an admin notice. Keys are `{box}_{post_type}_{field}`, or
`->prefix('_my_prefix_')` — exactly 0.x's.

## Entities and repositories

An entity holds typed values and declares where it lives; a repository loads and saves it
([ADR-0009](../adr/0009-entity-and-repository-not-active-record.md)).

```php
#[PostType('book', public: true, columns: ['title' => 'post_title', 'summary' => 'post_excerpt'])]
#[Taxonomy('genre', hierarchical: true)]
final class Book extends Entity
{
    public static function fields(FieldFactory $f): array
    {
        return [$f->text('title')->required(), $f->text('summary'), $f->text('isbn'), $f->number('pages')];
    }
}

$registrar->entity(Book::class, __('Book details', 'my-books'));   // post type, taxonomy, meta box

$books = $repositories->for(Book::class);
$book = $books->save(new Book(['title' => 'Dune', 'pages' => 412]));
$book->setTerms('genre', ['sci-fi']);
$book->pages = 414;
$books->save($book);                                             // post types and tables write only changed fields
$books->delete($book);
```

| Storage | Attribute | Use for |
| --- | --- | --- |
| Post type | `#[PostType('book', columns: [...])]` | Content editors manage in wp-admin |
| Custom table | `#[Table('books')]` | High-volume data; create it with `TableSchema::install()` in a migration |
| Options | `#[OptionStorage('presets')]` | Tens of records |
| Memory | `new ArrayRepository(Book::class)` | Your unit tests |

All four pass the same contract tests, so moving an entity between them is one attribute.
Fields are post meta unless `columns:` maps them to a post column — so a meta field that happens to
be called `title` stays meta. `save()` validates first and throws `ValidationException` with
`errors()` per field.

## Queries

```php
$page = $books->query(
    Query::create()->where('pages', '>=', 300)->whereIn('format', ['paperback'])
        ->whereTerm('genre', ['sci-fi'])->orderBy('title')->perPage(20)->page(2)
);
$total = $books->count(Query::create()->where('featured', true));
```

Operators: `=`, `!=`, `>`, `>=`, `<`, `<=`, `in`, `not in`, `like` (contains, case-insensitive).
Pages are capped at 100 rows; there is no "everything" query. Sensitive fields can't be queried.

## Search

```php
$result = (new Search(Book::class, $books, $identity))
    ->in('title', 'content', 'isbn', 'genre')     // columns, fields, taxonomies
    ->expose('isbn')                              // what toArray() may return
    ->run($term, page: 1);

return $result->toArray();   // {items: [{id, title, score, isbn}], total, page, pages}
```

A term matches **any** listed source. Editors see what they may read; everyone else sees published
posts of viewable types only — decided by capability, never by `is_admin()`.

## Migrations

```php
Application::create(__FILE__, $config)
    ->migrations([
        RenameRating::class,
        new RenameMetaKey('2026_10_08_120000_rename_rating', 'book', 'book_rating', 'book_score'),
        new RenameOption('2026_10_08_130000_rename_mode', 'my-books_mode', 'my-books_display_mode'),
    ])
    ->boot();
```

Migrations run once each, in id order, on activation and on the next admin page load; batched ones
continue on WP-Cron. A lock stops two runs at once; a failure stops the run, is logged and shown to
administrators. `wp {slug} migrate` and `migrate:rollback` (both with `--dry-run`), `migrate:status`. Change
stored data with *expand → migrate → contract*
([ADR-0018](../adr/0018-versioned-data-migrations-expand-contract.md)): never two read paths.
