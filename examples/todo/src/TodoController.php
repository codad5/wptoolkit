<?php

declare(strict_types=1);

namespace WptkTodo;

use Codad5\WPToolkit\Adapters\Repository\RepositoryFactory;
use Codad5\WPToolkit\Contracts\Data\Repository;
use Codad5\WPToolkit\Data\Query\Query;
use Codad5\WPToolkit\Data\Search\Search;
use Codad5\WPToolkit\Data\ValidationException;
use Codad5\WPToolkit\Foundation\Identity;
use Codad5\WPToolkit\Http\Request;
use Codad5\WPToolkit\Http\Response;

/**
 * The todo API. Input arrives validated (only the route's declared args); the repository validates
 * the entity again against its fields before anything is written.
 */
final class TodoController
{
    /** @var Repository<Todo> */
    private readonly Repository $todos;

    public function __construct(RepositoryFactory $repositories, private readonly Identity $identity)
    {
        $this->todos = $repositories->for(Todo::class);
    }

    public function index(Request $request): Response
    {
        $query = Query::create()->orderBy('due_date')->page((int) $request->input('page', 1));
        $status = $request->input('status');
        if (is_string($status) && $status !== '') {
            $query = $query->where('status', $status);
        }

        return Response::json([
            'items' => array_map(static fn (Todo $todo): array => $todo->toArray(), $this->todos->query($query)),
            'total' => $this->todos->count($query),
        ]);
    }

    public function store(Request $request): Response
    {
        $todo = new Todo(array_filter($request->validated(), static fn ($v) => $v !== null));

        try {
            $this->todos->save($todo);
        } catch (ValidationException $e) {
            return Response::error(422, 'invalid_todo', $e->getMessage(), ['fields' => $e->errors()]);
        }

        return Response::created($todo->toArray());
    }

    public function search(Request $request): Response
    {
        $result = (new Search(Todo::class, $this->todos, $this->identity))
            ->in('title', 'notes')
            ->expose('priority', 'status', 'due_date')
            ->run((string) $request->input('q'));

        return Response::json($result->toArray());
    }
}
