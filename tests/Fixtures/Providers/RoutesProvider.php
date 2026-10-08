<?php

declare(strict_types=1);

namespace Codad5\WPToolkit\Tests\Fixtures\Providers;

use Codad5\WPToolkit\Foundation\ServiceProvider;
use Codad5\WPToolkit\Http\Router;

final class RoutesProvider extends ServiceProvider
{
    public function boot(Router $router): void
    {
        $router->get('books', static fn () => [])->public();
        $router->post('books', static fn () => null)->can('edit_posts');
    }
}
