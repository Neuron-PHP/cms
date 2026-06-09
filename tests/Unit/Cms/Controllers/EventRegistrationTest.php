<?php

namespace Tests\Unit\Cms\Controllers;

use Neuron\Cms\Auth\SessionManager;
use Neuron\Cms\Controllers\Content;
use Neuron\Cms\Controllers\EventRegistration;
use Neuron\Cms\Repositories\IEventRegistrationRepository;
use Neuron\Cms\Repositories\IEventRepository;
use Neuron\Cms\Services\EventRegistration\RegistrationService;
use Neuron\Data\Settings\SettingManager;
use Neuron\Data\Settings\Source\Memory;
use Neuron\Mvc\IMvcApplication;
use PHPUnit\Framework\TestCase;

class EventRegistrationTest extends TestCase
{
	private EventRegistration $controller;

	protected function setUp(): void
	{
		parent::setUp();

		$settings = new SettingManager( new Memory( [
			'site'   => [ 'name' => 'Test Site' ],
			'events' => [ 'registration' => [ 'notify_email' => 'events@example.com' ] ]
		] ) );

		$this->controller = new EventRegistration(
			$this->createMock( IMvcApplication::class ),
			$settings,
			$this->createMock( SessionManager::class ),
			$this->createMock( IEventRepository::class ),
			$this->createMock( IEventRegistrationRepository::class ),
			new RegistrationService( $settings )
		);
	}

	public function testExtendsContentController(): void
	{
		$this->assertInstanceOf( Content::class, $this->controller );
	}

	public function testExpectedActionsExist(): void
	{
		$this->assertTrue( method_exists( $this->controller, 'token' ) );
		$this->assertTrue( method_exists( $this->controller, 'submit' ) );
	}

	public function testSubmitRouteHasCsrfFilter(): void
	{
		$method     = new \ReflectionMethod( EventRegistration::class, 'submit' );
		$attributes = $method->getAttributes( \Neuron\Routing\Attributes\Post::class );

		$this->assertNotEmpty( $attributes, 'submit() should have a Post route attribute' );

		$args = $attributes[0]->getArguments();
		$this->assertContains( 'csrf', $args['filters'] ?? [] );
	}

	public function testTokenRouteIsGet(): void
	{
		$method     = new \ReflectionMethod( EventRegistration::class, 'token' );
		$attributes = $method->getAttributes( \Neuron\Routing\Attributes\Get::class );

		$this->assertNotEmpty( $attributes );
	}

	public function testValidateFlagsMissingNameAndEmail(): void
	{
		$method = new \ReflectionMethod( EventRegistration::class, 'validate' );

		$errors = $method->invoke( $this->controller, '', '' );

		$this->assertNotEmpty( $errors );
		$this->assertCount( 2, $errors );
	}

	public function testValidateFlagsInvalidEmail(): void
	{
		$method = new \ReflectionMethod( EventRegistration::class, 'validate' );

		$errors = $method->invoke( $this->controller, 'Alice', 'not-an-email' );

		$this->assertNotEmpty( $errors );
	}

	public function testValidatePassesForValidInput(): void
	{
		$method = new \ReflectionMethod( EventRegistration::class, 'validate' );

		$errors = $method->invoke( $this->controller, 'Alice', 'alice@example.com' );

		$this->assertSame( [], $errors );
	}
}
