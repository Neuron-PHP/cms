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
							[ 'name' => 'subscribe', 'label' => 'Subscribe', 'type' => 'checkbox', 'required' => false ],
							[
								'name'   => 'opportunities',
								'label'  => 'Opportunities',
								'type'   => 'checkboxes',
								'groups' => [
									[ 'label' => 'Sessions', 'options' => [ [ 'value' => 'judge', 'label' => 'Judge' ], [ 'value' => 'jury', 'label' => 'Jury Monitor' ] ] ],
									[ 'label' => 'Committee', 'options' => [ 'Fundraising' ] ]
								]
							],
							[ 'name' => 'contact_pref', 'label' => 'Preferred Contact', 'type' => 'radio', 'required' => true, 'options' => [ 'Email', 'Phone' ] ],
							[ 'name' => 'start_date', 'label' => 'Start Date', 'type' => 'date', 'required' => false ],
							[ 'name' => 'agree', 'label' => 'I agree to the {link}', 'type' => 'checkbox', 'required' => true, 'link' => [ 'text' => 'Release Form', 'url' => '/pages/release-form' ] ]
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
		$this->assertStringContainsString( 'form_extra_field', $html ); // honeypot
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

	public function testCheckboxesRenderAsArrayInputsWithGroupLabels(): void
	{
		$html = $this->widget()->render( [] );

		// Multi-select posts as an array.
		$this->assertStringContainsString( 'name="opportunities[]"', $html );

		// Group headings render.
		$this->assertStringContainsString( '>Sessions<', $html );
		$this->assertStringContainsString( '>Committee<', $html );

		// Option values and labels render (explicit and string-shorthand).
		$this->assertStringContainsString( 'value="judge"', $html );
		$this->assertStringContainsString( '>Judge<', $html );
		$this->assertStringContainsString( 'value="Fundraising"', $html );
	}

	public function testRadioAndDateFieldsRender(): void
	{
		$html = $this->widget()->render( [] );

		$this->assertStringContainsString( 'type="radio"', $html );
		$this->assertStringContainsString( 'name="contact_pref"', $html );
		$this->assertStringContainsString( 'value="Email"', $html );
		$this->assertStringContainsString( 'type="date"', $html );
		$this->assertStringContainsString( 'name="start_date"', $html );
	}

	public function testCheckboxLabelEmbedsConfiguredLinkAtToken(): void
	{
		$html = $this->widget()->render( [] );

		$this->assertStringContainsString( 'href="/pages/release-form"', $html );
		$this->assertStringContainsString( 'target="_blank"', $html );
		$this->assertStringContainsString( '>Release Form</a>', $html );
		// The {link} token is replaced, not left as literal text.
		$this->assertStringNotContainsString( '{link}', $html );
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
