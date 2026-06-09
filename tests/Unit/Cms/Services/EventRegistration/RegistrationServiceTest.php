<?php

namespace Tests\Unit\Cms\Services\EventRegistration;

use Neuron\Cms\Models\Event;
use Neuron\Cms\Models\EventRegistration;
use Neuron\Cms\Services\Email\Sender;
use Neuron\Cms\Services\EventRegistration\RegistrationService;
use Neuron\Data\Settings\SettingManager;
use Neuron\Data\Settings\Source\Memory;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

class RegistrationServiceTest extends TestCase
{
	private function settings( array $registration = [], array $email = [] ): SettingManager
	{
		return new SettingManager( new Memory( [
			'system' => [ 'base_path' => sys_get_temp_dir() ],
			'email'  => $email,
			'events' => [ 'registration' => $registration ]
		] ) );
	}

	private function event(): Event
	{
		$event = new Event();
		$event->setTitle( 'Annual Workshop' );
		$event->setSlug( 'annual-workshop' );
		$event->setStartDate( new DateTimeImmutable( '2030-01-01 10:00:00' ) );
		$event->setLocation( 'Main Hall' );
		$event->setId( 7 );

		return $event;
	}

	private function registration(): EventRegistration
	{
		$registration = new EventRegistration();
		$registration->setEventId( 7 );
		$registration->setName( 'Alice' );
		$registration->setEmail( 'alice@example.com' );

		return $registration;
	}

	public function testGetNotifyEmailUsesConfiguredRecipient(): void
	{
		$service = new RegistrationService( $this->settings( [ 'notify_email' => 'events@example.com' ] ) );

		$this->assertSame( 'events@example.com', $service->getNotifyEmail() );
	}

	public function testGetNotifyEmailFallsBackToFromAddress(): void
	{
		$service = new RegistrationService( $this->settings( [], [ 'from_address' => 'noreply@example.com' ] ) );

		$this->assertSame( 'noreply@example.com', $service->getNotifyEmail() );
	}

	public function testGetNotifyEmailReturnsNullWhenUnset(): void
	{
		$service = new RegistrationService( $this->settings() );

		$this->assertNull( $service->getNotifyEmail() );
	}

	public function testIsConfirmationEnabledReflectsConfig(): void
	{
		$enabled  = new RegistrationService( $this->settings( [ 'confirmation_enabled' => true ] ) );
		$disabled = new RegistrationService( $this->settings( [ 'confirmation_enabled' => false ] ) );

		$this->assertTrue( $enabled->isConfirmationEnabled() );
		$this->assertFalse( $disabled->isConfirmationEnabled() );
	}

	public function testGetSuccessMessageDefaultAndOverride(): void
	{
		$default  = new RegistrationService( $this->settings() );
		$override = new RegistrationService( $this->settings( [ 'success_message' => 'You are in!' ] ) );

		$this->assertStringContainsString( 'Thank you', $default->getSuccessMessage() );
		$this->assertSame( 'You are in!', $override->getSuccessMessage() );
	}

	public function testNotifyAdminDispatchesViaSender(): void
	{
		$sender = $this->createMock( Sender::class );

		$sender->expects( $this->once() )
			->method( 'to' )
			->with( 'events@example.com' )
			->willReturnSelf();

		$sender->expects( $this->once() )
			->method( 'replyTo' )
			->with( 'alice@example.com', 'Alice' )
			->willReturnSelf();

		$sender->method( 'subject' )->willReturnSelf();
		$sender->method( 'template' )->willReturnSelf();
		$sender->method( 'body' )->willReturnSelf();
		$sender->expects( $this->once() )->method( 'send' )->willReturn( true );

		$service = new RegistrationService( $this->settings( [ 'notify_email' => 'events@example.com' ] ), $sender );

		$this->assertTrue( $service->notifyAdmin( $this->registration(), $this->event() ) );
	}

	public function testNotifyAdminReturnsFalseWithoutRecipient(): void
	{
		$sender = $this->createMock( Sender::class );
		$sender->expects( $this->never() )->method( 'send' );

		$service = new RegistrationService( $this->settings(), $sender );

		$this->assertFalse( $service->notifyAdmin( $this->registration(), $this->event() ) );
	}

	public function testSendConfirmationSkippedWhenDisabled(): void
	{
		$sender = $this->createMock( Sender::class );
		$sender->expects( $this->never() )->method( 'send' );

		$service = new RegistrationService( $this->settings( [ 'confirmation_enabled' => false ] ), $sender );

		$this->assertTrue( $service->sendConfirmation( $this->registration(), $this->event() ) );
	}

	public function testSendConfirmationDispatchesWhenEnabled(): void
	{
		$sender = $this->createMock( Sender::class );
		$sender->method( 'to' )->willReturnSelf();
		$sender->method( 'subject' )->willReturnSelf();
		$sender->method( 'template' )->willReturnSelf();
		$sender->method( 'body' )->willReturnSelf();
		$sender->expects( $this->once() )->method( 'send' )->willReturn( true );

		$service = new RegistrationService( $this->settings( [ 'confirmation_enabled' => true ] ), $sender );

		$this->assertTrue( $service->sendConfirmation( $this->registration(), $this->event() ) );
	}
}
