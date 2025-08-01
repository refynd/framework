<?php

namespace Tests\Unit;

use Tests\TestCase;
use Refynd\Container\Container;

class ContainerTest extends TestCase
{
    public function test_container_can_bind_and_resolve_simple_class(): void
    {
        $container = new Container();
        
        $container->bind('test', fn() => 'test-value');
        
        $this->assertEquals('test-value', $container->make('test'));
    }

    public function test_container_can_make_class_with_automatic_resolution(): void
    {
        $container = new Container();
        
        $instance = $container->make(TestableClass::class);
        
        $this->assertInstanceOf(TestableClass::class, $instance);
    }

    public function test_container_singleton_returns_same_instance(): void
    {
        $container = new Container();
        
        $container->singleton(TestableClass::class);
        
        $instance1 = $container->make(TestableClass::class);
        $instance2 = $container->make(TestableClass::class);
        
        $this->assertSame($instance1, $instance2);
    }
}

class TestableClass
{
    public function getValue(): string
    {
        return 'testable';
    }
}
