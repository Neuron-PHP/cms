<?php

namespace Neuron\Cms\Services\Contact;

use Neuron\Cms\Services\Email\Sender;
use Neuron\Data\Settings\SettingManager;
use Neuron\Log\Log;

/**
 * Resolves contact form definitions from configuration and delivers
 * submissions to the per-form recipient using the CMS email Sender.
 *
 * Form definitions live under the `contact` settings section; each form
 * declares its recipient plus its own configurable field list.
 *
 * @package Neuron\Cms\Services\Contact
 */
class ContactService
{
	private SettingManager $_settings;
	private ?Sender $_sender;
	private string $_basePath;

	/**
	 * @param SettingManager $settings
	 * @param Sender|null $sender Optional Sender (injectable for testing)
	 * @param string|null $basePath Base path for email template resolution
	 */
	public function __construct( SettingManager $settings, ?Sender $sender = null, ?string $basePath = null )
	{
		$this->_settings = $settings;
		$this->_sender   = $sender;
		$this->_basePath = $basePath
			?? ( $settings->get( 'system', 'base_path' ) ?: getcwd() );
	}

	/**
	 * Get the default form key.
	 *
	 * @return string
	 */
	public function getDefaultFormKey(): string
	{
		return (string) ( $this->_settings->get( 'contact', 'default_form' ) ?? 'general' );
	}

	/**
	 * Get the configuration for a single form.
	 *
	 * @param string $key
	 * @return array|null Null when the form key is unknown
	 */
	public function getFormConfig( string $key ): ?array
	{
		$forms = $this->_settings->get( 'contact', 'forms' ) ?? [];

		if( !is_array( $forms ) || !isset( $forms[ $key ] ) || !is_array( $forms[ $key ] ) )
		{
			return null;
		}

		return $forms[ $key ];
	}

	/**
	 * Get the field definitions for a form.
	 *
	 * @param string $key
	 * @return array
	 */
	public function getFields( string $key ): array
	{
		$config = $this->getFormConfig( $key );

		return $config['fields'] ?? [];
	}

	/**
	 * Determine the reply-to email and name from the submitted values based on
	 * field role flags (reply_to / sender_name), falling back to fields named
	 * email / name.
	 *
	 * @param array $fieldDefs
	 * @param array $values
	 * @return array{email: ?string, name: ?string}
	 */
	public function resolveReplyTo( array $fieldDefs, array $values ): array
	{
		$email = null;
		$name  = null;

		foreach( $fieldDefs as $field )
		{
			$fieldName = $field['name'] ?? null;

			if( $fieldName === null )
			{
				continue;
			}

			$value = $values[ $fieldName ] ?? null;

			if( !empty( $field['reply_to'] ) && $email === null )
			{
				$email = $value;
			}

			if( !empty( $field['sender_name'] ) && $name === null )
			{
				$name = $value;
			}
		}

		// Convention fallback when no role flags are set.
		$email ??= ( $values['email'] ?? null );
		$name  ??= ( $values['name'] ?? null );

		return [ 'email' => $email, 'name' => $name ];
	}

	/**
	 * Send a contact submission for the given form key.
	 *
	 * @param string $key Form key
	 * @param array $values Submitted field values keyed by field name
	 * @return bool True when the email was sent (or logged in test/log mode)
	 */
	public function send( string $key, array $values ): bool
	{
		$config = $this->getFormConfig( $key );

		if( $config === null )
		{
			Log::error( "ContactService: unknown form key '{$key}'" );
			return false;
		}

		$recipient = $config['to'] ?? null;

		if( empty( $recipient ) )
		{
			Log::error( "ContactService: no recipient configured for form '{$key}'" );
			return false;
		}

		$fields  = $config['fields'] ?? [];
		$subject = $config['subject'] ?? ( 'Website Contact: ' . ( $config['label'] ?? $key ) );
		$replyTo = $this->resolveReplyTo( $fields, $values );

		$sender = $this->_sender ?? new Sender( $this->_settings, $this->_basePath );

		try
		{
			$sender->to( $recipient );

			if( !empty( $replyTo['email'] ) )
			{
				$sender->replyTo( $replyTo['email'], $replyTo['name'] ?? '' );
			}

			$sender->subject( $subject );

			$templateData = [
				'fields'    => $fields,
				'values'    => $values,
				'formLabel' => $config['label'] ?? $key,
				'formKey'   => $key
			];

			try
			{
				$sender->template( 'emails/contact', $templateData );
			}
			catch( \Throwable $templateError )
			{
				// Fall back to a plain-text body if the template cannot render,
				// so a misconfigured template never drops a submission silently.
				Log::warning( 'ContactService: template render failed, using plain body: ' . $templateError->getMessage() );
				$sender->body( $this->buildPlainBody( $fields, $values ), false );
			}

			return $sender->send();
		}
		catch( \Throwable $e )
		{
			Log::error( 'ContactService: failed to send submission for form ' . $key . ': ' . $e->getMessage() );
			return false;
		}
	}

	/**
	 * Build a simple plain-text email body from the configured fields.
	 *
	 * @param array $fieldDefs
	 * @param array $values
	 * @return string
	 */
	private function buildPlainBody( array $fieldDefs, array $values ): string
	{
		$lines = [];

		foreach( $fieldDefs as $field )
		{
			$name = $field['name'] ?? null;

			if( $name === null )
			{
				continue;
			}

			$label = $field['label'] ?? $name;
			$value = $values[ $name ] ?? '';
			$lines[] = $label . ': ' . ( is_scalar( $value ) ? (string) $value : json_encode( $value ) );
		}

		return implode( "\n", $lines );
	}
}
