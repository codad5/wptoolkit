<?php

/**
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

namespace Codad5\WPToolkit\Data\Search;

use Codad5\WPToolkit\Data\Entity;
use WP_Post;

/**
 * How relevant one match is to the search term (Strategy). Scores from every scorer are added;
 * higher ranks first. Add your own with `Search::scoreWith()`.
 */
interface Scorer
{
    public function score(string $term, WP_Post $post, Entity $entity): float;
}
