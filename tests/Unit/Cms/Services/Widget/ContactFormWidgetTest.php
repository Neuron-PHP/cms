<?php

namespace Tests\Unit\Cms\Services\Widget;

use Neuron\Cms\Auth\SessionManager;
use Neuron\Cms\Services\Contact\ContactService;
use Neuron\Cms\Services\Widget\ContactFormWidget;
use Neuron\Data\Settings\SettingManager;
use Neuron\Data\Settings\Source\Memory;
use PHPUnit\Framework\TestCase;

class ContactFormWidgetTest extends TestCase
{
	private function widget(): ContactFormWidget
	{
		$config = [
			'contact' => [
				'default_form' => 'general',
				'forms'        => [
					'general' => [
						'to'     => 'info@example.com',
						'label'  => 'Contact Us',
						'button' => 'Send Message',
						'fields' => [
							[ 'name' => 'name', 'label' => 'Your Name', 'type' => 'text', 'required' => true ],
							[ 'name' => 'email', 'label' => 'Email', 'type' => 'email', 'required' => true ],
							[ 'name' => 'county', 'label' => 'County', 'type' => 'select', 'required' => true, 'options' => [ 'Sarasota', 'Manatee' ] ],
							[ 'name' => 'message', 'label' => 'Message', 'type' => 'textarea', 'required' => true ],
							[ 'name' => 'subscribe', 'label' => 'Subscribe', 'type' => 'checkbox', 'required' => false ]
						]
					]
				]
			]
		];

		$settings = new SettingManager( new Memory( $config ) );

		$session = $this->createMock( SessionManager::class );
		$session->method( 'isStarted' )->willReturn( true );
		$session->method( 'getFlash' )->willReturn( null );

		return new ContactFormWidget( new ContactService( $settings ), $session );
	}

	public function testGetNameIsContact(): void
	{
		$this->assertSame( 'contact', $this->widget()->getName() );
	}

	public function testRendersFormWithConfiguredFields(): void
	{
		$html = $this->widget()->render( [] );

		$this->assertStringContainsString( 'action="/contact/submit"', $html );
		$this->assertStringContainsString( 'name="form" value="general"', $html );
		$this->assertStringContainsString( 'name="csrf_token"', $html );
		$this->assertStringContainsString( 'company_website', $html ); // honeypot
		$this->assertStringContainsString( 'name="name"', $html );
		$this->assertStringContainsString( 'name="email"', $html );
		$this->assertStringContainsString( '<textarea', $html );
		$this->assertStringContainsString( '<select', $html );
		$this->assertStringContainsString( 'type="checkbox"', $html );
		$this->assertStringContainsString( 'Send Message', $html );
	}

	public function testSelectRendersConfiguredOptions(): void
	{
		$html = $this->widget()->render( [] );

		$this->assertStringContainsString( '>Sarasota<', $html );
		$this->assertStringContainsString( '>Manatee<', $html );
	}

	public function testTitleAndButtonCanBeOverriddenByAttributes(): void
	{
		$html = $this->widget()->render( [ 'title' => 'Custom Heading', 'button' => 'Go' ] );

		$this->assertStringContainsString( 'Custom Heading', $html );
		$this->assertStringContainsString( '>Go<', $html );
	}

	public function testUnknownFormRendersComment(): void
	{
		$html = $this->widget()->render( [ 'form' => 'missing' ] );

		$this->assertStringContainsString( '<!-- contact widget: unknown form', $html );
		$this->assertStringNotContainsString( '<form', $html );
	}
}
