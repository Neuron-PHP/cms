<?php

namespace Tests\Unit\Cms\Services\Email;

use Neuron\Cms\Jobs\SendEmailJob;
use Neuron\Cms\Services\Email\Sender;
use Neuron\Data\Settings\SettingManager;
use Neuron\Data\Settings\Source\Memory;
use Neuron\Jobs\Queue\QueueManager;
use Neuron\Jobs\Testing\QueueAssertions;
use PHPUnit\Framework\TestCase;

/**
 * Sender::send() with `email.queue` configured hands the rendered message to
 * SendEmailJob instead of delivering it in-process.
 */
class QueuedSenderTest extends TestCase
{
	use QueueAssertions;

	protected function setUp(): void
	{
		parent::setUp();
		$this->fakeQueue();
	}

	private function settings( array $email ): SettingManager
	{
		return new SettingManager( new Memory( [ 'email' => $email ] ) );
	}

	private function message( SettingManager $settings ): Sender
	{
		return ( new Sender( $settings ) )
			->to( 'user@example.com', 'User' )
			->cc( 'copy@example.com' )
			->bcc( 'hidden@example.com', 'Hidden' )
			->replyTo( 'reply@example.com', 'Reply' )
			->subject( 'Hello' )
			->body( '<p>Hi</p>' )
			->attach( '/tmp/invoice.pdf', 'invoice.pdf' );
	}

	public function testSendWithoutQueueSettingDeliversInline(): void
	{
		// test_mode makes inline delivery a log line, so this is observable.
		$sender = $this->message( $this->settings( [ 'test_mode' => true ] ) );

		$this->assertTrue( $sender->send() );
		$this->assertNothingDispatched();
	}

	public function testSendWithEmptyQueueSettingDeliversInline(): void
	{
		$sender = $this->message( $this->settings( [ 'test_mode' => true, 'queue' => '' ] ) );

		$this->assertTrue( $sender->send() );
		$this->assertNothingDispatched();
	}

	public function testSendWithQueueSettingDispatchesRenderedMessage(): void
	{
		$sender = $this->message( $this->settings( [ 'queue' => 'emails' ] ) );

		$this->assertTrue( $sender->send() );

		$this->assertDispatchedOn( 'emails', SendEmailJob::class );
		$this->assertDispatchedTimes( SendEmailJob::class, 1 );
		$this->assertDispatched( SendEmailJob::class, function( array $args ) {
			return $args['to'] === [ [ 'email' => 'user@example.com', 'name' => 'User' ] ]
				&& $args['cc'] === [ [ 'email' => 'copy@example.com', 'name' => '' ] ]
				&& $args['bcc'] === [ [ 'email' => 'hidden@example.com', 'name' => 'Hidden' ] ]
				&& $args['reply_to'] === 'reply@example.com'
				&& $args['reply_to_name'] === 'Reply'
				&& $args['subject'] === 'Hello'
				&& $args['body'] === '<p>Hi</p>'
				&& $args['is_html'] === true
				&& $args['attachments'] === [ [ 'path' => '/tmp/invoice.pdf', 'name' => 'invoice.pdf' ] ];
		} );
	}

	public function testLegacyMailQueueSettingIsHonoured(): void
	{
		$settings = new SettingManager( new Memory( [ 'mail' => [ 'queue' => 'mail-out' ] ] ) );

		$this->assertTrue( $this->message( $settings )->send() );
		$this->assertDispatchedOn( 'mail-out', SendEmailJob::class );
	}

	public function testOnQueueForcesQueueingWithoutSetting(): void
	{
		$sender = $this->message( $this->settings( [ 'test_mode' => true ] ) )->onQueue( 'urgent' );

		$this->assertTrue( $sender->send() );
		$this->assertDispatchedOn( 'urgent', SendEmailJob::class );
	}

	public function testSendNowBypassesConfiguredQueue(): void
	{
		$sender = $this->message( $this->settings( [ 'test_mode' => true, 'queue' => 'emails' ] ) );

		$this->assertTrue( $sender->sendNow() );
		$this->assertNothingDispatched();
	}

	public function testSendWithQueueButNoRecipientsFails(): void
	{
		$sender = ( new Sender( $this->settings( [ 'queue' => 'emails' ] ) ) )->subject( 'x' )->body( 'y' );

		$this->assertFalse( $sender->send() );
		$this->assertNothingDispatched();
	}

	public function testTemplateIsRenderedBeforeQueueing(): void
	{
		$dir = sys_get_temp_dir() . '/queued_sender_' . uniqid();
		mkdir( $dir . '/resources/views/emails', 0777, true );
		file_put_contents( $dir . '/resources/views/emails/hello.php', 'Hello <?= $name ?>!' );

		try
		{
			$sender = ( new Sender( $this->settings( [ 'queue' => 'emails' ] ), $dir ) )
				->to( 'user@example.com' )
				->subject( 'Tpl' )
				->template( 'emails/hello', [ 'name' => 'Ada' ] );

			$this->assertTrue( $sender->send() );
			$this->assertDispatched( SendEmailJob::class, fn( array $args ) => $args['body'] === 'Hello Ada!' );
		}
		finally
		{
			unlink( $dir . '/resources/views/emails/hello.php' );
			rmdir( $dir . '/resources/views/emails' );
			rmdir( $dir . '/resources/views' );
			rmdir( $dir . '/resources' );
			rmdir( $dir );
		}
	}

	public function testRoundTripThroughArrayPreservesMessage(): void
	{
		$original = $this->message( $this->settings( [] ) );

		$rebuilt = Sender::fromArray( $original->toArray() );

		$this->assertEquals( $original->toArray(), $rebuilt->toArray() );
	}

	public function testWorkerDeliversQueuedEmail(): void
	{
		// Queue in "web", then process as a worker would. The worker side uses
		// the job's settings (test_mode) so delivery is a log line.
		$sender = $this->message( $this->settings( [ 'queue' => 'emails' ] ) );
		$this->assertTrue( $sender->send() );

		$job = new SendEmailJob( $this->settings( [ 'test_mode' => true ] ) );
		$pending = $this->getFakeQueue()->getPending( 'emails' );

		$this->assertCount( 1, $pending );
		$this->assertTrue( $job->run( $pending[0]->getArguments() ) );
	}

	public function testSendEmailJobThrowsWhenDeliveryFails(): void
	{
		// The message references an attachment that does not exist, so
		// PHPMailer refuses it and sendNow() returns false; the job must turn
		// that into an exception so the queue retries it.
		$job = new SendEmailJob( $this->settings( [ 'driver' => 'mail' ] ) );

		$this->expectException( \RuntimeException::class );
		$this->expectExceptionMessage( 'user@example.com' );

		$job->run( $this->message( $this->settings( [] ) )->toArray() );
	}

	public function testSendEmailJobIsQueueableWithDefaults(): void
	{
		$job = new SendEmailJob();

		$this->assertEquals( 'send_email', $job->getName() );
		$this->assertNull( $job->getQueue() );
		$this->assertNull( $job->getMaxAttempts() );
		$this->assertNull( $job->getBackoff() );
		$this->assertNull( $job->getTimeout() );

		// Must not throw.
		$job->failed( new \RuntimeException( 'x' ) );
	}

	public function testQueuedEmailProcessedByQueueManagerEndToEnd(): void
	{
		$this->fakeQueue( [ 'max_attempts' => 1 ] );

		$this->message( $this->settings( [ 'queue' => 'emails' ] ) )->send();

		// No application context in this test, so SendEmailJob falls back to
		// Registry settings; with none it uses PHPMailer's mail() which cannot
		// work here, so the job fails and lands in failed storage.
		QueueManager::fromRegistry()->processNextJob( 'emails' );

		$failed = $this->getFakeQueue()->getFailedJobs();
		$this->assertCount( 1, $failed );
		$this->assertStringContainsString( 'Email delivery failed', $failed[0]['exception'] );
	}
}
