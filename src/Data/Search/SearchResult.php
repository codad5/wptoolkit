<?php

/**
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

namespace Codad5\WPToolkit\Data\Search;

use Codad5\WPToolkit\Data\Entity;

/**
 * One page of ranked matches. `toArray()` is what an endpoint should return: id, title, score and
 * only the fields the search exposes — never the whole entity. Titles are raw `post_title`: escape
 * them where they are printed.
 */
final class SearchResult
{
    /**
     * @param list<array{entity: Entity, title: string, score: float}> $items
     * @param list<string> $exposed
     */
    public function __construct(
        public readonly array $items,
        public readonly int $total,
        public readonly int $page,
        public readonly int $perPage,
        private readonly array $exposed
    ) {
    }

    /**
     * @return list<Entity>
     */
    public function entities(): array
    {
        return array_map(static fn (array $item): Entity => $item['entity'], $this->items);
    }

    public function pages(): int
    {
        return (int) ceil($this->total / max(1, $this->perPage));
    }

    /**
     * @return array{items: list<array<string, mixed>>, total: int, page: int, pages: int}
     */
    public function toArray(): array
    {
        $items = [];
        foreach ($this->items as $item) {
            $row = ['id' => $item['entity']->id(), 'title' => $item['title'], 'score' => $item['score']];
            foreach ($this->exposed as $field) {
                $row[$field] = $item['entity']->get($field);
            }
            $items[] = $row;
        }

        return ['items' => $items, 'total' => $this->total, 'page' => $this->page, 'pages' => $this->pages()];
    }
}
