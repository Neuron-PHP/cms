<?php

use Neuron\Cms\Services\EmailService;
use Neuron\Data\Setting\ISettingSource;

if( !function_exists( 'sendEmail' ) )
{
	/**
	 * Send a simple email
	 *
	 * @param string $to Recipient email address
	 * @param string $subject Email subject
	 * @param string $body Email body content
	 * @param bool $isHtml Whether the body is HTML (default: true)
	 * @param ISettingSource|null $settings Optional settings source for email configuration
	 * @return bool True if email was sent successfully
	 *
	 * @example
	 * sendEmail('user@example.com', 'Welcome!', '<p>Welcome to our site!</p>');
	 */
	function sendEmail( string $to, string $subject, string $body, bool $isHtml = true, ?ISettingSource $settings = null ): bool
	{
		try
		{
			$email = new EmailService( $settings );

			return $email
				->to( $to )
				->subject( $subject )
				->body( $body, $isHtml )
				->send();
		}
		catch( \Exception $e )
		{
			return false;
		}
	}
}

if( !function_exists( 'sendEmailTemplate' ) )
{
	/**
	 * Send an email using a template
	 *
	 * @param string $to Recipient email address
	 * @param string $subject Email subject
	 * @param string $template Template path relative to resources/views
	 * @param array $data Data to pass to the template
	 * @param ISettingSource|null $settings Optional settings source for email configuration
	 * @param string $basePath Base path for template resolution (default: current directory)
	 * @return bool True if email was sent successfully
	 *
	 * @example
	 * sendEmailTemplate(
	 *     'user@example.com',
	 *     'Welcome!',
	 *     'emails/welcome',
	 *     ['userName' => 'John', 'activationLink' => 'https://...']
	 * );
	 */
	function sendEmailTemplate( string $to, string $subject, string $template, array $data = [], ?ISettingSource $settings = null, string $basePath = '' ): bool
	{
		try
		{
			$email = new EmailService( $settings, $basePath );

			return $email
				->to( $to )
				->subject( $subject )
				->template( $template, $data )
				->send();
		}
		catch( \Exception $e )
		{
			return false;
		}
	}
}

if( !function_exists( 'email' ) )
{
	/**
	 * Create a new EmailService instance with fluent interface
	 *
	 * @param ISettingSource|null $settings Optional settings source for email configuration
	 * @param string $basePath Base path for template resolution (default: current directory)
	 * @return EmailService
	 *
	 * @example
	 * email()
	 *     ->to('user@example.com')
	 *     ->cc('admin@example.com')
	 *     ->subject('Order Confirmation')
	 *     ->template('emails/order-confirmation', ['order' => $order])
	 *     ->attach('/path/to/invoice.pdf', 'invoice.pdf')
	 *     ->send();
	 */
	function email( ?ISettingSource $settings = null, string $basePath = '' ): EmailService
	{
		return new EmailService( $settings, $basePath );
	}
}
