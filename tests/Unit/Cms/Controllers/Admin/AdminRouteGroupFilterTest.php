<?php

namespace Tests\Cms\Controllers\Admin;

use Neuron\Routing\Attributes\RouteGroup;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Every controller under Controllers/Admin must be gated by the `admin`
 * filter (authenticated + staff role). `auth` alone lets a subscriber in.
 */
class AdminRouteGroupFilterTest extends TestCase
{
	#[DataProvider( 'adminControllers' )]
	public function testAdminControllersUseTheAdminFilter( string $class ): void
	{
		$attributes = ( new \ReflectionClass( $class ) )->getAttributes( RouteGroup::class );

		$this->assertNotEmpty( $attributes, "$class should declare a RouteGroup" );

		$args = $attributes[0]->getArguments();
		$filters = (array)( $args['filters'] ?? [] );

		$this->assertSame( '/admin', $args['prefix'] ?? null, "$class should live under /admin" );
		$this->assertContains( 'admin', $filters, "$class must require the admin filter" );
		$this->assertNotContains( 'auth', $filters, "$class must not fall back to the authentication-only filter" );
	}

	public static function adminControllers(): array
	{
		$dir = dirname( __DIR__, 5 ) . '/src/Cms/Controllers/Admin';
		$cases = [];

		foreach( glob( $dir . '/*.php' ) as $file )
		{
			$class = 'Neuron\\Cms\\Controllers\\Admin\\' . basename( $file, '.php' );
			$cases[ basename( $file, '.php' ) ] = [ $class ];
		}

		return $cases;
	}
}
