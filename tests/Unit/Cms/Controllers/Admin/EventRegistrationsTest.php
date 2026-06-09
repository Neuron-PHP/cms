<?php

namespace Tests\Unit\Cms\Controllers\Admin;

use Neuron\Cms\Controllers\Admin\EventRegistrations;
use Neuron\Cms\Controllers\Content;
use Neuron\Routing\Attributes\Delete;
use Neuron\Routing\Attributes\Get;
use Neuron\Routing\Attributes\RouteGroup;
use PHPUnit\Framework\TestCase;

class EventRegistrationsTest extends TestCase
{
	public function testExtendsContentController(): void
	{
		$this->assertTrue( is_subclass_of( EventRegistrations::class, Content::class ) );
	}

	public function testExpectedActionsExist(): void
	{
		$this->assertTrue( method_exists( EventRegistrations::class, 'index' ) );
		$this->assertTrue( method_exists( EventRegistrations::class, 'show' ) );
		$this->assertTrue( method_exists( EventRegistrations::class, 'destroy' ) );
	}

	public function testRouteGroupGuardedByAuth(): void
	{
		$class      = new \ReflectionClass( EventRegistrations::class );
		$attributes = $class->getAttributes( RouteGroup::class );

		$this->assertNotEmpty( $attributes, 'Controller should declare a RouteGroup' );

		$args = $attributes[0]->getArguments();
		$this->assertSame( '/admin', $args['prefix'] ?? null );
		$this->assertContains( 'auth', $args['filters'] ?? [] );
	}

	public function testDestroyRouteHasCsrfFilter(): void
	{
		$method     = new \ReflectionMethod( EventRegistrations::class, 'destroy' );
		$attributes = $method->getAttributes( Delete::class );

		$this->assertNotEmpty( $attributes, 'destroy() should have a Delete route attribute' );

		$args = $attributes[0]->getArguments();
		$this->assertContains( 'csrf', $args['filters'] ?? [] );
	}

	public function testIndexRouteIsGet(): void
	{
		$method     = new \ReflectionMethod( EventRegistrations::class, 'index' );
		$attributes = $method->getAttributes( Get::class );

		$this->assertNotEmpty( $attributes );
	}
}
