<?php

declare(strict_types=1);

namespace Codad5\WPToolkit\Tests\Unit\Foundation;

use Codad5\WPToolkit\Exceptions\CircularDependencyException;
use Codad5\WPToolkit\Exceptions\ContainerException;
use Codad5\WPToolkit\Exceptions\NotFoundException;
use Codad5\WPToolkit\Foundation\Container;
use Codad5\WPToolkit\Tests\Fixtures\Container\AbstractThing;
use Codad5\WPToolkit\Tests\Fixtures\Container\Clock;
use Codad5\WPToolkit\Tests\Fixtures\Container\CycleA;
use Codad5\WPToolkit\Tests\Fixtures\Container\FixedClock;
use Codad5\WPToolkit\Tests\Fixtures\Container\Logger;
use Codad5\WPToolkit\Tests\Fixtures\Container\Mailer;
use Codad5\WPToolkit\Tests\Fixtures\Container\NeedsScalar;
use Codad5\WPToolkit\Tests\Fixtures\Container\Newsletter;
use PHPUnit\Framework\TestCase;

final class ContainerTest extends TestCase
{
    private Container $container;

    protected function setUp(): void
    {
        $this->container = new Container();
    }

    public function test_autowires_a_concrete_class_and_its_dependencies(): void
    {
        $mailer = $this->container->get(Mailer::class);

        self::assertInstanceOf(Mailer::class, $mailer);
        self::assertInstanceOf(Logger::class, $mailer->logger);
        self::assertSame('noreply@example.com', $mailer->from);
    }

    public function test_unbound_autowired_classes_are_fresh_each_time(): void
    {
        self::assertNotSame($this->container->get(Logger::class), $this->container->get(Logger::class));
    }

    public function test_singleton_is_built_once(): void
    {
        $this->container->singleton(Logger::class);

        self::assertSame($this->container->get(Logger::class), $this->container->get(Logger::class));
    }

    public function test_bind_with_factory_builds_fresh_each_time_and_receives_the_container(): void
    {
        $received = null;
        $this->container->bind(Mailer::class, function (Container $c) use (&$received) {
            $received = $c;
            return new Mailer(new Logger(), 'team@example.com');
        });

        $first = $this->container->get(Mailer::class);

        self::assertSame($this->container, $received);
        self::assertSame('team@example.com', $first->from);
        self::assertNotSame($first, $this->container->get(Mailer::class));
    }

    public function test_interface_resolves_through_its_binding(): void
    {
        $this->container->singleton(Clock::class, fn () => new FixedClock());

        $newsletter = $this->container->get(Newsletter::class);

        self::assertInstanceOf(FixedClock::class, $newsletter->clock);
    }

    public function test_instance_is_returned_as_is(): void
    {
        $logger = new Logger();
        $this->container->instance(Logger::class, $logger);

        self::assertSame($logger, $this->container->get(Logger::class));
    }

    public function test_rebinding_replaces_a_built_singleton(): void
    {
        $this->container->singleton(Logger::class);
        $old = $this->container->get(Logger::class);

        $this->container->singleton(Logger::class);

        self::assertNotSame($old, $this->container->get(Logger::class));
    }

    public function test_unbound_interface_says_it_is_not_instantiable(): void
    {
        $this->expectException(ContainerException::class);
        $this->expectExceptionMessage(Clock::class . ' is not instantiable');

        $this->container->get(Clock::class);
    }

    public function test_abstract_class_says_it_is_not_instantiable(): void
    {
        $this->expectException(ContainerException::class);

        $this->container->get(AbstractThing::class);
    }

    public function test_unknown_id_throws_not_found(): void
    {
        $this->expectException(NotFoundException::class);

        $this->container->get('no.such.service');
    }

    public function test_has_reports_bindings_and_instantiable_classes_only(): void
    {
        $this->container->bind('mailer.alias', fn () => new Logger());

        self::assertTrue($this->container->has('mailer.alias'));
        self::assertTrue($this->container->has(Logger::class));
        self::assertFalse($this->container->has(Clock::class));
        self::assertFalse($this->container->has('no.such.service'));
    }

    public function test_circular_dependency_names_the_cycle(): void
    {
        $this->expectException(CircularDependencyException::class);
        $this->expectExceptionMessage('CycleA -> Codad5\WPToolkit\Tests\Fixtures\Container\CycleB -> Codad5\WPToolkit\Tests\Fixtures\Container\CycleA');

        $this->container->get(CycleA::class);
    }

    public function test_container_recovers_after_a_failed_resolution(): void
    {
        try {
            $this->container->get(CycleA::class);
        } catch (CircularDependencyException) {
        }

        // The resolving stack was unwound, so unrelated entries still resolve.
        self::assertInstanceOf(Logger::class, $this->container->get(Logger::class));
    }

    public function test_scalar_without_default_explains_what_is_missing(): void
    {
        $this->expectException(ContainerException::class);
        $this->expectExceptionMessage('$apiKey of ' . NeedsScalar::class . '::__construct()');

        $this->container->get(NeedsScalar::class);
    }

    public function test_call_injects_class_parameters_and_accepts_named_values(): void
    {
        $result = $this->container->call(
            fn (Logger $logger, string $greeting) => [$logger, $greeting],
            ['greeting' => 'hello']
        );

        self::assertInstanceOf(Logger::class, $result[0]);
        self::assertSame('hello', $result[1]);
    }

    public function test_call_resolves_methods_on_objects(): void
    {
        $target = new class {
            public function handle(Mailer $mailer): string
            {
                return $mailer->from;
            }
        };

        self::assertSame('noreply@example.com', $this->container->call([$target, 'handle']));
    }
}
