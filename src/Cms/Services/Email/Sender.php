<?php

namespace Neuron\Cms\Services\Email;

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception as PHPMailerException;
use Neuron\Data\Settings\SettingManager;
use Neuron\Log\Log;

/**
 * Email sending service.
 *
 * Sends emails with templates using PHPMailer.
 *
 * @package Neuron\Cms\Services\Email
 */
class Sender
{
	private ?SettingManager $_settings;
	private string $_basePath;
	private array $_to = [];
	private array $_cc = [];
	private array $_bcc = [];
	private string $_subject = '';
	private string $_body = '';
	private bool $_isHtml = true;
	private array $_attachments = [];
	private ?string $_replyTo = null;
	private ?string $_replyToName = null;

	public function __construct( ?SettingManager $settings = null, string $basePath = '' )
	{
		$this->_settings = $settings;
		$this->_basePath = $basePath ?: getcwd();
	}

	/**
	 * Add recipient
	 */
	public function to( string $email, string $name = '' ): self
	{
		$this->_to[] = [ 'email' => $email, 'name' => $name ];
		return $this;
	}

	/**
	 * Add CC recipient
	 */
	public function cc( string $email, string $name = '' ): self
	{
		$this->_cc[] = [ 'email' => $email, 'name' => $name ];
		return $this;
	}

	/**
	 * Add BCC recipient
	 */
	public function bcc( string $email, string $name = '' ): self
	{
		$this->_bcc[] = [ 'email' => $email, 'name' => $name ];
		return $this;
	}

	/**
	 * Set subject
	 */
	public function subject( string $subject ): self
	{
		$this->_subject = $subject;
		return $this;
	}

	/**
	 * Set body content
	 */
	public function body( string $body, bool $isHtml = true ): self
	{
		$this->_body = $body;
		$this->_isHtml = $isHtml;
		return $this;
	}

	/**
	 * Set reply-to address
	 */
	public function replyTo( string $email, string $name = '' ): self
	{
		$this->_replyTo = $email;
		$this->_replyToName = $name;
		return $this;
	}

	/**
	 * Attach file
	 */
	public function attach( string $filePath, string $name = '' ): self
	{
		$this->_attachments[] = [ 'path' => $filePath, 'name' => $name ];
		return $this;
	}

	/**
	 * Render email template
	 */
	public function template( string $templatePath, array $data = [] ): self
	{
		try
		{
			$templateFile = $this->_basePath . '/resources/views/' . $templatePath . '.php';

			if( !file_exists( $templateFile ) )
			{
				throw new \RuntimeException( "Email template not found: {$templateFile}" );
			}

			// Extract data variables into local scope
			extract( $data, EXTR_SKIP );

			// Render the template
			ob_start();
			require $templateFile;
			$this->_body = ob_get_clean();
			$this->_isHtml = true;

			return $this;
		}
		catch( \Exception $exception )
		{
			Log::error( "Email template error: " . $exception->getMessage() );
			throw new \RuntimeException( "Failed to render email template: {$templatePath}" );
		}
	}

	/**
	 * Send the email
	 */
	public function send(): bool
	{
		// Check for test mode or log driver
		$driver = $this->_settings?->get( 'mail', 'driver' );
		if( ($this->_settings && $this->_settings->get( 'email', 'test_mode' )) || $driver === 'log' )
		{
			return $this->logEmail();
		}

		try
		{
			$mail = $this->createMailer();

			// Recipients
			foreach( $this->_to as $recipient )
			{
				$mail->addAddress( $recipient['email'], $recipient['name'] );
			}

			foreach( $this->_cc as $recipient )
			{
				$mail->addCC( $recipient['email'], $recipient['name'] );
			}

			foreach( $this->_bcc as $recipient )
			{
				$mail->addBCC( $recipient['email'], $recipient['name'] );
			}

			// Reply-To
			if( $this->_replyTo )
			{
				$mail->addReplyTo( $this->_replyTo, $this->_replyToName ?? '' );
			}

			// Attachments
			foreach( $this->_attachments as $attachment )
			{
				$mail->addAttachment( $attachment['path'], $attachment['name'] );
			}

			// Content
			$mail->Subject = $this->_subject;
			$mail->Body = $this->_body;
			$mail->isHTML( $this->_isHtml );

			// Send
			$result = $mail->send();

			if( $result )
			{
				Log::info( "Email sent to: " . $this->_to[0]['email'] );
			}

			return $result;
		}
		catch( PHPMailerException $exception )
		{
			Log::error( "Email send failed: " . $exception->getMessage() );
			return false;
		}
	}

	/**
	 * Create and configure PHPMailer instance
	 */
	private function createMailer(): PHPMailer
	{
		$mail = new PHPMailer( true );

		// Get config - check both 'mail' and 'email' for backward compatibility
		$driver = $this->_settings?->get( 'mail', 'driver' ) ?? $this->_settings?->get( 'email', 'driver' ) ?? 'mail';
		$host = $this->_settings?->get( 'mail', 'host' ) ?? $this->_settings?->get( 'email', 'host' ) ?? '';
		$port = $this->_settings?->get( 'mail', 'port' ) ?? $this->_settings?->get( 'email', 'port' ) ?? 587;
		$username = $this->_settings?->get( 'mail', 'username' ) ?? $this->_settings?->get( 'email', 'username' ) ?? '';
		$password = $this->_settings?->get( 'mail', 'password' ) ?? $this->_settings?->get( 'email', 'password' ) ?? '';
		$encryption = $this->_settings?->get( 'mail', 'encryption' ) ?? $this->_settings?->get( 'email', 'encryption' ) ?? 'tls';
		$fromAddress = $this->_settings?->get( 'mail', 'from_address' ) ?? $this->_settings?->get( 'email', 'from_address' ) ?? 'noreply@example.com';
		$fromName = $this->_settings?->get( 'mail', 'from_name' ) ?? $this->_settings?->get( 'email', 'from_name' ) ?? 'Neuron CMS';

		// Configure based on driver
		if( $driver === 'smtp' )
		{
			$mail->isSMTP();
			$mail->Host = $host;
			$mail->Port = $port;
			$mail->SMTPAuth = !empty( $username );

			if( $mail->SMTPAuth )
			{
				$mail->Username = $username;
				$mail->Password = $password;
			}

			if( $encryption === 'tls' )
			{
				$mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
			}
			elseif( $encryption === 'ssl' )
			{
				$mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
			}
		}
		elseif( $driver === 'sendmail' )
		{
			$mail->isSendmail();
		}
		else
		{
			$mail->isMail();
		}

		// From
		$mail->setFrom( $fromAddress, $fromName );

		// Encoding
		$mail->CharSet = 'UTF-8';

		return $mail;
	}

	/**
	 * Log email instead of sending (test mode)
	 */
	private function logEmail(): bool
	{
		$recipients = array_map( fn($recipient) => $recipient['email'], $this->_to );

		Log::info( "TEST MODE - Email not sent" );
		Log::info( "  To: " . implode( ', ', $recipients ) );
		Log::info( "  Subject: " . $this->_subject );
		Log::info( "  Body: " . substr( strip_tags( $this->_body ), 0, 100 ) . '...' );

		return true;
	}
}
