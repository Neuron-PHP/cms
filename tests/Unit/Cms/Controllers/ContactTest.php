<?php

namespace Tests\Unit\Cms\Controllers;

use Neuron\Cms\Auth\SessionManager;
use Neuron\Cms\Controllers\Contact;
use Neuron\Cms\Controllers\Content;
use Neuron\Cms\Repositories\IContactSubmissionRepository;
use Neuron\Cms\Services\Contact\ContactFormValidator;
use Neuron\Cms\Services\Contact\ContactService;
use Neuron\Data\Settings\SettingManager;
use Neuron\Data\Settings\Source\Memory;
use Neuron\Mvc\IMvcApplication;
use Neuron\Mvc\Requests\Request;
use PHPUnit\Framework\TestCase;

class ContactTest extends TestCase
{
	private Contact $controller;

	protected function setUp(): void
	{
		parent::setUp();

		$settings = new SettingManager( new Memory( [
			'site'    => [ 'name' => 'Test Site' ],
			'contact' => [
				'default_form' => 'general',
				'forms'        => [
					'general' => [
						'to'     => 'info@example.com',
						'fields' => [
							[ 'name' => 'name', 'label' => 'Name', 'type' => 'text', 'required' => true ],
							[ 'name' => 'email', 'label' => 'Email', 'type' => 'email', 'required' => true ],
							[ 'name' => 'subscribe', 'label' => 'Subscribe', 'type' => 'checkbox', 'required' => false ]
						]
					]
				]
			]
		] ) );

		$this->controller = new Contact(
			$this->createMock( IMvcApplication::class ),
			$settings,
			$this->createMock( SessionManager::class ),
			$this->createMock( IContactSubmissionRepository::class ),
			new ContactService( $settings ),
			new ContactFormValidator()
		);
	}

	public function testExtendsContentController(): void
	{
		$this->assertInstanceOf( Content::class, $this->controller );
	}

	public function testExpectedActionsExist(): void
	{
		$this->assertTrue( method_exists( $this->controller, 'submit' ) );
		$this->assertTrue( method_exists( $this->controller, 'token' ) );
		$this->assertTrue( method_exists( $this->controller, 'index' ) );
	}

	public function testSubmitRouteHasCsrfFilter(): void
	{
		$method     = new \ReflectionMethod( Contact::class, 'submit' );
		$attributes = $method->getAttributes( \Neuron\Routing\Attributes\Post::class );

		$this->assertNotEmpty( $attributes, 'submit() should have a Post route attribute' );

		$args = $attributes[0]->getArguments();
		$this->assertContains( 'csrf', $args['filters'] ?? [] );
	}

	public function testCollectValuesOnlyIncludesConfiguredFieldsAndHandlesCheckbox(): void
	{
		$_POST = [
			'name'      => '  Alice  ',
			'email'     => 'alice@example.com',
			'subscribe' => 'on',
			'evil'      => 'injected'
		];

		$fields = [
			[ 'name' => 'name', 'type' => 'text' ],
			[ 'name' => 'email', 'type' => 'email' ],
			[ 'name' => 'subscribe', 'type' => 'checkbox' ]
		];

		$method = new \ReflectionMethod( Contact::class, 'collectValues' );

		$values = $method->invoke( $this->controller, $fields, new Request() );

		$this->assertSame( 'Alice', $values['name'] );          // trimmed
		$this->assertSame( 'alice@example.com', $values['email'] );
		$this->assertSame( '1', $values['subscribe'] );         // checkbox normalized
		$this->assertArrayNotHasKey( 'evil', $values );         // unconfigured field ignored

		$_POST = [];
	}

	public function testSuccessMessageDefaultAndOverride(): void
	{
		$method = new \ReflectionMethod( Contact::class, 'successMessage' );

		$this->assertStringContainsString( 'Thank you', $method->invoke( $this->controller, [] ) );
		$this->assertSame( 'Custom thanks', $method->invoke( $this->controller, [ 'success_message' => 'Custom thanks' ] ) );
	}
}
