<?php

namespace Tests\Unit\Cms\Services\Email;

use Neuron\Cms\Services\Email\Sender;
use Neuron\Data\Settings\SettingManager;
use PHPUnit\Framework\TestCase;

class SenderTest extends TestCase
{
	private Sender $sender;
	private SettingManager $settings;
	private string $basePath;

	protected function setUp(): void
	{
		parent::setUp();

		$this->basePath = __DIR__ . '/../../../../..';

		// Mock settings
		$this->settings = $this->createMock( SettingManager::class );
		$this->sender = new Sender( $this->settings, $this->basePath );
	}

	public function testToAddsRecipient(): void
	{
		$result = $this->sender->to( 'test@example.com', 'Test User' );

		$this->assertSame( $this->sender, $result, 'Should return self for fluent interface' );
	}

	public function testCcAddsRecipient(): void
	{
		$result = $this->sender->cc( 'cc@example.com', 'CC User' );

		$this->assertSame( $this->sender, $result, 'Should return self for fluent interface' );
	}

	public function testBccAddsRecipient(): void
	{
		$result = $this->sender->bcc( 'bcc@example.com', 'BCC User' );

		$this->assertSame( $this->sender, $result, 'Should return self for fluent interface' );
	}

	public function testSubjectSetsSubject(): void
	{
		$result = $this->sender->subject( 'Test Subject' );

		$this->assertSame( $this->sender, $result, 'Should return self for fluent interface' );
	}

	public function testBodySetsBodyWithDefaultHtml(): void
	{
		$result = $this->sender->body( '<p>Test Body</p>' );

		$this->assertSame( $this->sender, $result, 'Should return self for fluent interface' );
	}

	public function testBodyCanSetPlainText(): void
	{
		$result = $this->sender->body( 'Plain text body', false );

		$this->assertSame( $this->sender, $result, 'Should return self for fluent interface' );
	}

	public function testReplyToSetsReplyAddress(): void
	{
		$result = $this->sender->replyTo( 'reply@example.com', 'Reply User' );

		$this->assertSame( $this->sender, $result, 'Should return self for fluent interface' );
	}

	public function testAttachAddsAttachment(): void
	{
		$result = $this->sender->attach( '/path/to/file.pdf', 'document.pdf' );

		$this->assertSame( $this->sender, $result, 'Should return self for fluent interface' );
	}

	public function testFluentChaining(): void
	{
		$result = $this->sender
			->to( 'recipient@example.com', 'Recipient' )
			->cc( 'cc@example.com' )
			->bcc( 'bcc@example.com' )
			->subject( 'Test Email' )
			->body( '<p>Test content</p>' )
			->replyTo( 'reply@example.com' );

		$this->assertSame( $this->sender, $result, 'Should support fluent chaining' );
	}

	public function testTemplateRendersValidTemplate(): void
	{
		// Create test template in resources/views/email
		$templateDir = $this->basePath . '/resources/views/email';
		if( !is_dir( $templateDir ) )
		{
			mkdir( $templateDir, 0777, true );
		}

		$templatePath = $templateDir . '/test-email.php';
		file_put_contents( $templatePath, '<h1>Hello <?= $name ?></h1>' );

		try
		{
			$result = $this->sender->template( 'email/test-email', [ 'name' => 'John' ] );

			$this->assertSame( $this->sender, $result, 'Should return self for fluent interface' );
		}
		finally
		{
			// Cleanup
			if( file_exists( $templatePath ) )
			{
				unlink( $templatePath );
			}
			if( is_dir( $templateDir ) && count( scandir( $templateDir ) ) === 2 )
			{
				rmdir( $templateDir );
			}
		}
	}

	public function testTemplateThrowsExceptionForMissingTemplate(): void
	{
		$this->expectException( \RuntimeException::class );
		$this->expectExceptionMessage( 'Failed to render email template' );

		$this->sender->template( 'email/nonexistent' );
	}

	public function testSendInTestModeLogsEmail(): void
	{
		// Configure test mode
		$this->settings->method( 'get' )->willReturnCallback( function( $section, $key ) {
			if( $section === 'email' && $key === 'test_mode' )
			{
				return true;
			}
			return null;
		});

		$result = $this->sender
			->to( 'test@example.com' )
			->subject( 'Test Subject' )
			->body( 'Test body' )
			->send();

		$this->assertTrue( $result, 'Should return true in test mode' );
	}

	public function testSendWithMultipleRecipients(): void
	{
		// Configure test mode
		$this->settings->method( 'get' )->willReturnCallback( function( $section, $key ) {
			if( $section === 'email' && $key === 'test_mode' )
			{
				return true;
			}
			return null;
		});

		$result = $this->sender
			->to( 'recipient1@example.com', 'User 1' )
			->to( 'recipient2@example.com', 'User 2' )
			->cc( 'cc@example.com' )
			->bcc( 'bcc@example.com' )
			->subject( 'Multi-recipient Test' )
			->body( 'Test body' )
			->send();

		$this->assertTrue( $result, 'Should send to multiple recipients' );
	}

	public function testConstructorWithoutBasePath(): void
	{
		$sender = new Sender( $this->settings );

		// Should use getcwd() as default base path
		$this->assertInstanceOf( Sender::class, $sender );
	}

	public function testConstructorWithoutSettings(): void
	{
		$sender = new Sender();

		// Should work without settings
		$this->assertInstanceOf( Sender::class, $sender );
	}
}
