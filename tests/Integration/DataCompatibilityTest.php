<?php

declare(strict_types=1);

namespace Codad5\WPToolkit\Tests\Integration;

use Codad5\WPToolkit\Adapters\Cache\ArrayStore;
use Codad5\WPToolkit\Adapters\Clock\SystemClock;
use Codad5\WPToolkit\Adapters\Repository\PostTypeRepository;
use Codad5\WPToolkit\Data\Field\FieldFactory;
use Codad5\WPToolkit\Data\Field\FieldTypes;
use Codad5\WPToolkit\Data\MetaBox;
use Codad5\WPToolkit\Foundation\Identity;
use Codad5\WPToolkit\Tests\Support\Entities\PauExecutive;
use Codad5\WPToolkit\Tests\Support\Entities\SilverbirdMovie;
use PHPUnit\Framework\TestCase;

/**
 * ADR-0016 on a real database: rows exactly as 0.x MetaBox::save_field() stored them (shapes taken
 * from pau-alumni-manager and silverbird) read identically through 1.0, and 1.0 writes the same
 * rows back — so 0.x, which reads with get_post_meta($id, $key, $single), still reads them after a
 * downgrade.
 */
final class DataCompatibilityTest extends TestCase
{
    private const PAU = 'pau-executive';

    private const SILVERBIRD = 'silverbird_movies';

    private FieldFactory $f;

    /** @var list<int> */
    private array $posts = [];

    protected function setUp(): void
    {
        register_post_type(self::PAU);
        register_post_type(self::SILVERBIRD);
        $this->f = new FieldFactory();
    }

    protected function tearDown(): void
    {
        foreach ($this->posts as $id) {
            wp_delete_post($id, true);
        }
    }

    public function test_pau_shaped_rows_read_and_write_identically(): void
    {
        $id = $this->post(self::PAU);
        // 0.x: key {box}_{post_type}_{field}; text through sanitize_text_field; number through absint;
        // an unticked-then-ticked checkbox as the submitted "on".
        add_post_meta($id, 'executive_role_pau-executive_title', 'President', true);
        add_post_meta($id, 'executive_role_pau-executive_year', '2024', true);
        add_post_meta($id, 'executive_role_pau-executive_active', 'on', true);
        add_post_meta($id, 'executive_role_pau-executive_photo', 77);

        $box = $this->box('executive_role', self::PAU, [
            $this->f->text('title'),
            $this->f->number('year'),
            $this->f->checkbox('active'),
            $this->f->of('photo', 'wp_media'),
        ]);

        self::assertSame(['title' => 'President', 'year' => 2024, 'active' => true, 'photo' => 77], $box->values($id));

        $box->save($id, 'title', 'Vice President');
        $box->save($id, 'photo', '78');
        self::assertSame('Vice President', get_post_meta($id, 'executive_role_pau-executive_title', true));
        self::assertSame(['78'], get_post_meta($id, 'executive_role_pau-executive_photo', false));
    }

    public function test_a_meta_field_named_title_stays_meta_in_the_repository(): void
    {
        $id = $this->post(self::PAU);
        add_post_meta($id, 'executive_role_pau-executive_title', 'Treasurer', true);
        $repository = new PostTypeRepository(PauExecutive::class, new FieldTypes(), new Identity('pau'));

        $executive = $repository->find($id);
        self::assertSame('Treasurer', $executive?->get('title'), 'not the post title "Fixture"');

        $executive?->set('title', 'Secretary');
        $repository->save($executive ?? new PauExecutive());
        self::assertSame('Secretary', get_post_meta($id, 'executive_role_pau-executive_title', true));
        self::assertSame('Fixture', get_post($id)?->post_title);
    }

    public function test_silverbird_shaped_rows_read_and_write_identically(): void
    {
        $id = $this->post(self::SILVERBIRD);
        // 0.x: set_prefix('_silverbird_movies_'); wp_media multiple = one row per ID; an array from a
        // sanitize callback = one serialized row.
        add_post_meta($id, '_silverbird_movies_availability', 'now_showing', true);
        foreach ([11, 12, 13] as $media) {
            add_post_meta($id, '_silverbird_movies_gallery', $media);
        }
        add_post_meta($id, '_silverbird_movies_showtimes', ['18:00', '21:00'], true);

        $repository = new PostTypeRepository(SilverbirdMovie::class, new FieldTypes(), new Identity('silverbird'));
        $movie = $repository->find($id);

        self::assertNotNull($movie);
        self::assertSame('now_showing', $movie->get('availability'));
        self::assertSame([11, 12, 13], $movie->get('gallery'));
        self::assertSame(['18:00', '21:00'], $movie->get('showtimes'));

        $movie->set('gallery', [12, 14])->set('showtimes', ['20:00']);
        $repository->save($movie);

        self::assertSame(['12', '14'], get_post_meta($id, '_silverbird_movies_gallery', false), 'still one row per ID');
        self::assertSame(['20:00'], get_post_meta($id, '_silverbird_movies_showtimes', true), 'still one serialized row');
        self::assertCount(1, get_post_meta($id, '_silverbird_movies_showtimes', false));
    }

    public function test_the_meta_box_and_the_repository_agree_on_every_key(): void
    {
        $id = $this->post(self::SILVERBIRD);
        $box = $this->box('movie_details', self::SILVERBIRD, SilverbirdMovie::fields($this->f))->prefix('_silverbird_movies_');
        $box->save($id, 'availability', 'coming_soon');
        $box->save($id, 'gallery', [5]);

        $movie = (new PostTypeRepository(SilverbirdMovie::class, new FieldTypes(), new Identity('silverbird')))->find($id);

        self::assertSame('coming_soon', $movie?->get('availability'));
        self::assertSame([5], $movie?->get('gallery'));
    }

    /**
     * @param list<\Codad5\WPToolkit\Data\Field\Field> $fields
     */
    private function box(string $id, string $postType, array $fields): MetaBox
    {
        return new MetaBox($id, 'Details', $postType, $fields, new FieldTypes(), new Identity('compat'), new ArrayStore(new SystemClock()));
    }

    private function post(string $type): int
    {
        $id = wp_insert_post(['post_type' => $type, 'post_title' => 'Fixture', 'post_status' => 'publish']);
        $this->posts[] = $id;

        return $id;
    }
}
