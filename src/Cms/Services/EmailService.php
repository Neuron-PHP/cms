<?php

namespace Neuron\Cms\Services;

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception as PHPMailerException;
use Neuron\Data\Setting\ISettingSource;
use Neuron\Log\Log;
use Neuron\Mvc\Views\Html;

/**
 * Email service for sending emails with templates
 */
class EmailService
{
	private ?ISettingSource $_Settings;
	private string $_BasePath;
	private array $_To = [];
	private array $_Cc = [];
	private array $_Bcc = [];
	private string $_Subject = '';
	private string $_Body = '';
	private bool $_IsHtml = true;
	private array $_Attachments = [];
	private ?string $_ReplyTo = null;
	private ?string $_ReplyToName = null;

	public function __construct( ?ISettingSource $Settings = null, string $BasePath = '' )
	{
		$this->_Settings = $Settings;
		$this->_BasePath = $BasePath ?: getcwd();
	}

	/**
	 * Add recipient
	 */
	public function to( string $email, string $name = '' ): self
	{
		$this->_To[] = [ 'email' => $email, 'name' => $name ];
		return $this;
	}

	/**
	 * Add CC recipient
	 */
	public function cc( string $email, string $name = '' ): self
	{
		$this->_Cc[] = [ 'email' => $email, 'name' => $name ];
		return $this;
	}

	/**
	 * Add BCC recipient
	 */
	public function bcc( string $email, string $name = '' ): self
	{
		$this->_Bcc[] = [ 'email' => $email, 'name' => $name ];
		return $this;
	}

	/**
	 * Set subject
	 */
	public function subject( string $subject ): self
	{
		$this->_Subject = $subject;
		return $this;
	}

	/**
	 * Set body content
	 */
	public function body( string $body, bool $isHtml = true ): self
	{
		$this->_Body = $body;
		$this->_IsHtml = $isHtml;
		return $this;
	}

	/**
	 * Set reply-to address
	 */
	public function replyTo( string $email, string $name = '' ): self
	{
		$this->_ReplyTo = $email;
		$this->_ReplyToName = $name;
		return $this;
	}

	/**
	 * Attach file
	 */
	public function attach( string $filePath, string $name = '' ): self
	{
		$this->_Attachments[] = [ 'path' => $filePath, 'name' => $name ];
		return $this;
	}

	/**
	 * Render email template
	 */
	public function template( string $templatePath, array $data = [] ): self
	{
		try
		{
			$view = new Html();
			$view->setViewPath( $this->_BasePath . '/resources/views' );
			$view->setPage( $templatePath );

			// Render the template
			ob_start();
			foreach( $data as $key => $value )
			{
				$$key = $value;
			}
			require $view->getViewPath() . '/' . $templatePath . '.php';
			$this->_Body = ob_get_clean();
			$this->_IsHtml = true;

			return $this;
		}
		catch( \Exception $e )
		{
			Log::error( "Email template error: " . $e->getMessage() );
			throw new \RuntimeException( "Failed to render email template: {$templatePath}" );
		}
	}

	/**
	 * Send the email
	 */
	public function send(): bool
	{
		// Check for test mode
		if( $this->_Settings && $this->_Settings->get( 'email.test_mode' ) )
		{
			return $this->logEmail();
		}

		try
		{
			$mail = $this->createMailer();

			// Recipients
			foreach( $this->_To as $recipient )
			{
				$mail->addAddress( $recipient['email'], $recipient['name'] );
			}

			foreach( $this->_Cc as $recipient )
			{
				$mail->addCC( $recipient['email'], $recipient['name'] );
			}

			foreach( $this->_Bcc as $recipient )
			{
				$mail->addBCC( $recipient['email'], $recipient['name'] );
			}

			// Reply-To
			if( $this->_ReplyTo )
			{
				$mail->addReplyTo( $this->_ReplyTo, $this->_ReplyToName ?? '' );
			}

			// Attachments
			foreach( $this->_Attachments as $attachment )
			{
				$mail->addAttachment( $attachment['path'], $attachment['name'] );
			}

			// Content
			$mail->Subject = $this->_Subject;
			$mail->Body = $this->_Body;
			$mail->isHTML( $this->_IsHtml );

			// Send
			$result = $mail->send();

			if( $result )
			{
				Log::info( "Email sent to: " . $this->_To[0]['email'] );
			}

			return $result;
		}
		catch( PHPMailerException $e )
		{
			Log::error( "Email send failed: " . $e->getMessage() );
			return false;
		}
	}

	/**
	 * Create and configure PHPMailer instance
	 */
	private function createMailer(): PHPMailer
	{
		$mail = new PHPMailer( true );

		// Get config
		$driver = $this->_Settings?->get( 'email.driver' ) ?? 'mail';
		$host = $this->_Settings?->get( 'email.host' ) ?? '';
		$port = $this->_Settings?->get( 'email.port' ) ?? 587;
		$username = $this->_Settings?->get( 'email.username' ) ?? '';
		$password = $this->_Settings?->get( 'email.password' ) ?? '';
		$encryption = $this->_Settings?->get( 'email.encryption' ) ?? 'tls';
		$fromAddress = $this->_Settings?->get( 'email.from_address' ) ?? 'noreply@example.com';
		$fromName = $this->_Settings?->get( 'email.from_name' ) ?? 'Neuron CMS';

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
		$recipients = array_map( fn($r) => $r['email'], $this->_To );

		Log::info( "TEST MODE - Email not sent" );
		Log::info( "  To: " . implode( ', ', $recipients ) );
		Log::info( "  Subject: " . $this->_Subject );
		Log::info( "  Body: " . substr( strip_tags( $this->_Body ), 0, 100 ) . '...' );

		return true;
	}
}
