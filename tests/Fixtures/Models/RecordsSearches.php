<?php

declare(strict_types=1);

namespace Codad5\WPToolkit\Tests\Fixtures\Models;

/**
 * Replaces the WP_Query-backed search methods with recorders, so a unit test
 * can assert what the Ajax handler asked for.
 */
trait RecordsSearches
{
    /** @var list<array{term: string, fields: array, args: array, config: array}> */
    public array $searches = [];

    /** @var list<array{term: string, limit: int, fields: array}> */
    public array $autocompletes = [];

    public ?\Throwable $search_throws = null;

    public function search(string $search_term, array $search_fields = ['title', 'content'], array $args = [], array $config = []): array
    {
        if ($this->search_throws) {
            throw $this->search_throws;
        }

        $this->searches[] = ['term' => $search_term, 'fields' => $search_fields, 'args' => $args, 'config' => $config];
        return [];
    }

    public function search_autocomplete(string $search_term, int $limit = 10, array $search_fields = ['title']): array
    {
        $this->autocompletes[] = ['term' => $search_term, 'limit' => $limit, 'fields' => $search_fields];
        return [];
    }

    public function register_hooks(): void
    {
        $this->setup_hooks();
    }

    public function reset_recorders(): void
    {
        $this->searches = [];
        $this->autocompletes = [];
        $this->search_throws = null;
    }
}
