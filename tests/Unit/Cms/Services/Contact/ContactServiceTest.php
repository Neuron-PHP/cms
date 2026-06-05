<?php

namespace Tests\Unit\Cms\Services\Contact;

use Neuron\Cms\Services\Contact\ContactService;
use Neuron\Cms\Services\Email\Sender;
use Neuron\Data\Settings\SettingManager;
use Neuron\Data\Settings\Source\Memory;
use PHPUnit\Framework\TestCase;

class ContactServiceTest extends TestCase
{
	private function settings(): SettingManager
	{
		$config = [
			'system'  => [ 'base_path' => sys_get_temp_dir() ],
			'contact' => [
				'default_form' => 'general',
				'forms'        => [
					'general' => [
						'to'      => 'info@example.com',
						'subject' => 'Website Contact: General',
						'label'   => 'Contact Us',
						'fields'  => [
							[ 'name' => 'name', 'label' => 'Name', 'type' => 'text', 'required' => true, 'sender_name' => true ],
							[ 'name' => 'email', 'label' => 'Email', 'type' => 'email', 'required' => true, 'reply_to' => true ],
							[ 'name' => 'message', 'label' => 'Message', 'type' => 'textarea', 'required' => true ]
						]
					],
					'intake' => [
						'to'     => 'intake@example.com',
						'fields' => [
							[ 'name' => 'email', 'label' => 'Email', 'type' => 'email', 'required' => true ],
							[ 'name' => 'name', 'label' => 'Name', 'type' => 'text', 'required' => true ]
						]
					]
				]
			]
		];

		return new SettingManager( new Memory( $config ) );
	}

	public function testGetDefaultFormKey(): void
	{
		$service = new ContactService( $this->settings() );
		$this->assertSame( 'general', $service->getDefaultFormKey() );
	}

	public function testGetFormConfigReturnsConfigForKnownKey(): void
	{
		$service = new ContactService( $this->settings() );
		$config  = $service->getFormConfig( 'general' );

		$this->assertIsArray( $config );
		$this->assertSame( 'info@example.com', $config['to'] );
	}

	public function testGetFormConfigReturnsNullForUnknownKey(): void
	{
		$service = new ContactService( $this->settings() );
		$this->assertNull( $service->getFormConfig( 'does-not-exist' ) );
	}

	public function testGetFieldsReturnsConfiguredFields(): void
	{
		$service = new ContactService( $this->settings() );
		$fields  = $service->getFields( 'general' );

		$this->assertCount( 3, $fields );
		$this->assertSame( 'name', $fields[0]['name'] );
	}

	public function testResolveReplyToUsesRoleFlags(): void
	{
		$service = new ContactService( $this->settings() );
		$fields  = $service->getFields( 'general' );

		$replyTo = $service->resolveReplyTo( $fields, [
			'name'  => 'Alice',
			'email' => 'alice@example.com'
		] );

		$this->assertSame( 'alice@example.com', $replyTo['email'] );
		$this->assertSame( 'Alice', $replyTo['name'] );
	}

	public function testResolveReplyToFallsBackToConventionalNames(): void
	{
		$service = new ContactService( $this->settings() );
		// intake form fields have no role flags
		$fields = $service->getFields( 'intake' );

		$replyTo = $service->resolveReplyTo( $fields, [
			'name'  => 'Bob',
			'email' => 'bob@example.com'
		] );

		$this->assertSame( 'bob@example.com', $replyTo['email'] );
		$this->assertSame( 'Bob', $replyTo['name'] );
	}

	public function testSendResolvesRecipientAndDispatchesViaSender(): void
	{
		$sender = $this->createMock( Sender::class );

		$sender->expects( $this->once() )
			->method( 'to' )
			->with( 'info@example.com' )
			->willReturnSelf();

		$sender->expects( $this->once() )
			->method( 'replyTo' )
			->with( 'alice@example.com', 'Alice' )
			->willReturnSelf();

		$sender->expects( $this->once() )
			->method( 'subject' )
			->with( 'Website Contact: General' )
			->willReturnSelf();

		$sender->method( 'template' )->willReturnSelf();
		$sender->method( 'body' )->willReturnSelf();
		$sender->expects( $this->once() )->method( 'send' )->willReturn( true );

		$service = new ContactService( $this->settings(), $sender );

		$result = $service->send( 'general', [
			'name'    => 'Alice',
			'email'   => 'alice@example.com',
			'message' => 'Hello there'
		] );

		$this->assertTrue( $result );
	}

	public function testSendReturnsFalseForUnknownForm(): void
	{
		$sender = $this->createMock( Sender::class );
		$sender->expects( $this->never() )->method( 'send' );

		$service = new ContactService( $this->settings(), $sender );

		$this->assertFalse( $service->send( 'nope', [ 'email' => 'x@example.com' ] ) );
	}
}
