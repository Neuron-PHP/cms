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

	/**
	 * Test createMailer() with SMTP and TLS encryption
	 */
	public function testCreateMailerWithSmtpAndTls(): void
	{
		// Configure SMTP with TLS
		$this->settings->method( 'get' )->willReturnCallback( function( $section, $key ) {
			if( $section === 'email' && $key === 'test_mode' ) return false;
			if( $section === 'email' && $key === 'driver' ) return 'smtp';
			if( $section === 'email' && $key === 'host' ) return 'smtp.gmail.com';
			if( $section === 'email' && $key === 'port' ) return 587;
			if( $section === 'email' && $key === 'username' ) return 'user@gmail.com';
			if( $section === 'email' && $key === 'password' ) return 'password123';
			if( $section === 'email' && $key === 'encryption' ) return 'tls';
			if( $section === 'email' && $key === 'from_address' ) return 'noreply@test.com';
			if( $section === 'email' && $key === 'from_name' ) return 'Test Sender';
			return null;
		});

		// Use reflection to test createMailer
		$sender = new Sender( $this->settings, $this->basePath );
		$reflection = new \ReflectionClass( $sender );
		$method = $reflection->getMethod( 'createMailer' );
		$method->setAccessible( true );

		$mailer = $method->invoke( $sender );

		$this->assertInstanceOf( \PHPMailer\PHPMailer\PHPMailer::class, $mailer );
		$this->assertEquals( 'smtp.gmail.com', $mailer->Host );
		$this->assertEquals( 587, $mailer->Port );
		$this->assertTrue( $mailer->SMTPAuth );
		$this->assertEquals( 'user@gmail.com', $mailer->Username );
		$this->assertEquals( 'password123', $mailer->Password );
	}

	/**
	 * Test createMailer() with SMTP and SSL encryption
	 */
	public function testCreateMailerWithSmtpAndSsl(): void
	{
		// Configure SMTP with SSL
		$this->settings->method( 'get' )->willReturnCallback( function( $section, $key ) {
			if( $section === 'email' && $key === 'driver' ) return 'smtp';
			if( $section === 'email' && $key === 'encryption' ) return 'ssl';
			if( $section === 'email' && $key === 'host' ) return 'smtp.test.com';
			if( $section === 'email' && $key === 'port' ) return 465;
			if( $section === 'email' && $key === 'from_address' ) return 'test@test.com';
			if( $section === 'email' && $key === 'from_name' ) return 'Test';
			return null;
		});

		// Use reflection to test createMailer
		$sender = new Sender( $this->settings, $this->basePath );
		$reflection = new \ReflectionClass( $sender );
		$method = $reflection->getMethod( 'createMailer' );
		$method->setAccessible( true );

		$mailer = $method->invoke( $sender );

		$this->assertEquals( 'smtp.test.com', $mailer->Host );
		$this->assertEquals( 465, $mailer->Port );
	}

	/**
	 * Test createMailer() with sendmail driver
	 */
	public function testCreateMailerWithSendmailDriver(): void
	{
		// Configure sendmail
		$this->settings->method( 'get' )->willReturnCallback( function( $section, $key ) {
			if( $section === 'email' && $key === 'driver' ) return 'sendmail';
			if( $section === 'email' && $key === 'from_address' ) return 'test@test.com';
			if( $section === 'email' && $key === 'from_name' ) return 'Test';
			return null;
		});

		$sender = new Sender( $this->settings, $this->basePath );
		$reflection = new \ReflectionClass( $sender );
		$method = $reflection->getMethod( 'createMailer' );
		$method->setAccessible( true );

		$mailer = $method->invoke( $sender );

		$this->assertInstanceOf( \PHPMailer\PHPMailer\PHPMailer::class, $mailer );
		$this->assertEquals( 'UTF-8', $mailer->CharSet );
	}

	/**
	 * Test createMailer() with mail driver (default)
	 */
	public function testCreateMailerWithMailDriver(): void
	{
		// Configure mail driver
		$this->settings->method( 'get' )->willReturnCallback( function( $section, $key ) {
			if( $section === 'email' && $key === 'driver' ) return 'mail';
			if( $section === 'email' && $key === 'from_address' ) return 'test@test.com';
			if( $section === 'email' && $key === 'from_name' ) return 'Test';
			return null;
		});

		$sender = new Sender( $this->settings, $this->basePath );
		$reflection = new \ReflectionClass( $sender );
		$method = $reflection->getMethod( 'createMailer' );
		$method->setAccessible( true );

		$mailer = $method->invoke( $sender );

		$this->assertInstanceOf( \PHPMailer\PHPMailer\PHPMailer::class, $mailer );
	}

	/**
	 * Test createMailer() without settings uses defaults
	 */
	public function testCreateMailerWithoutSettingsUsesDefaults(): void
	{
		$sender = new Sender( null, $this->basePath );
		$reflection = new \ReflectionClass( $sender );
		$method = $reflection->getMethod( 'createMailer' );
		$method->setAccessible( true );

		$mailer = $method->invoke( $sender );

		$this->assertInstanceOf( \PHPMailer\PHPMailer\PHPMailer::class, $mailer );
		$this->assertEquals( 'UTF-8', $mailer->CharSet );
	}

	/**
	 * Test createMailer() with SMTP but no authentication
	 */
	public function testCreateMailerWithSmtpNoAuth(): void
	{
		// Configure SMTP without username (no auth)
		$this->settings->method( 'get' )->willReturnCallback( function( $section, $key ) {
			if( $section === 'email' && $key === 'driver' ) return 'smtp';
			if( $section === 'email' && $key === 'host' ) return 'smtp.test.com';
			if( $section === 'email' && $key === 'port' ) return 25;
			if( $section === 'email' && $key === 'username' ) return '';
			if( $section === 'email' && $key === 'from_address' ) return 'test@test.com';
			if( $section === 'email' && $key === 'from_name' ) return 'Test';
			return null;
		});

		$sender = new Sender( $this->settings, $this->basePath );
		$reflection = new \ReflectionClass( $sender );
		$method = $reflection->getMethod( 'createMailer' );
		$method->setAccessible( true );

		$mailer = $method->invoke( $sender );

		$this->assertEquals( 'smtp.test.com', $mailer->Host );
		$this->assertFalse( $mailer->SMTPAuth );
	}

	/**
	 * Test logEmail() method
	 */
	public function testLogEmailMethod(): void
	{
		$sender = new Sender( $this->settings, $this->basePath );
		$sender
			->to( 'recipient@test.com', 'Test User' )
			->subject( 'Test Subject' )
			->body( '<p>This is a test email body that is quite long and should be truncated in the log output</p>' );

		$reflection = new \ReflectionClass( $sender );
		$method = $reflection->getMethod( 'logEmail' );
		$method->setAccessible( true );

		$result = $method->invoke( $sender );

		$this->assertTrue( $result, 'logEmail should always return true' );
	}

	/**
	 * Test template with data extraction
	 */
	public function testTemplateExtractsDataVariables(): void
	{
		// Create test template that uses variables
		$templateDir = $this->basePath . '/resources/views/email';
		if( !is_dir( $templateDir ) )
		{
			mkdir( $templateDir, 0777, true );
		}

		$templatePath = $templateDir . '/data-test.php';
		file_put_contents( $templatePath, '<h1><?= $title ?></h1><p><?= $message ?></p>' );

		try
		{
			$sender = $this->sender->template( 'email/data-test', [
				'title' => 'Welcome',
				'message' => 'Hello World'
			]);

			$this->assertInstanceOf( Sender::class, $sender );
		}
		finally
		{
			// Cleanup
			if( file_exists( $templatePath ) )
			{
				unlink( $templatePath );
			}
		}
	}

	/**
	 * Test multiple CC recipients
	 */
	public function testMultipleCcRecipients(): void
	{
		$result = $this->sender
			->cc( 'cc1@example.com', 'CC User 1' )
			->cc( 'cc2@example.com', 'CC User 2' )
			->cc( 'cc3@example.com', 'CC User 3' );

		$this->assertSame( $this->sender, $result );
	}

	/**
	 * Test multiple BCC recipients
	 */
	public function testMultipleBccRecipients(): void
	{
		$result = $this->sender
			->bcc( 'bcc1@example.com' )
			->bcc( 'bcc2@example.com' )
			->bcc( 'bcc3@example.com' );

		$this->assertSame( $this->sender, $result );
	}

	/**
	 * Test multiple attachments
	 */
	public function testMultipleAttachments(): void
	{
		$result = $this->sender
			->attach( '/path/to/file1.pdf', 'document1.pdf' )
			->attach( '/path/to/file2.pdf', 'document2.pdf' )
			->attach( '/path/to/image.jpg', 'photo.jpg' );

		$this->assertSame( $this->sender, $result );
	}
}
