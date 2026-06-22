<?php

namespace Neuron\Cms\Controllers;

use Neuron\Cms\Auth\SessionManager;
use Neuron\Cms\Repositories\IContactSubmissionRepository;
use Neuron\Cms\Services\Auth\CsrfToken;
use Neuron\Cms\Services\Contact\ContactFormValidator;
use Neuron\Cms\Services\Contact\ContactService;
use Neuron\Cms\Services\Contact\FieldOptions;
use Neuron\Cms\Services\Widget\ContactFormWidget;
use Neuron\Data\Settings\SettingManager;
use Neuron\Log\Log;
use Neuron\Mvc\IMvcApplication;
use Neuron\Mvc\Requests\Request;
use Neuron\Mvc\Responses\HttpResponseStatus;
use Neuron\Routing\Attributes\Get;
use Neuron\Routing\Attributes\Post;

/**
 * Public contact form controller.
 *
 * Handles submissions from the [contact] shortcode. Submissions are persisted
 * before the notification email is sent so nothing is lost on delivery failure.
 *
 * @package Neuron\Cms\Controllers
 */
class Contact extends Content
{
	private ContactService $_contactService;
	private ContactFormValidator $_validator;
	private IContactSubmissionRepository $_repository;

	/**
	 * Honeypot field name; matches ContactFormWidget.
	 */
	private const HONEYPOT_FIELD = 'company_website';

	/**
	 * @param IMvcApplication $app
	 * @param SettingManager $settings
	 * @param SessionManager $sessionManager
	 * @param IContactSubmissionRepository $repository
	 * @param ContactService|null $contactService
	 * @param ContactFormValidator|null $validator
	 */
	public function __construct(
		IMvcApplication              $app,
		SettingManager               $settings,
		SessionManager               $sessionManager,
		IContactSubmissionRepository $repository,
		?ContactService              $contactService = null,
		?ContactFormValidator        $validator = null
	)
	{
		parent::__construct( $app, $settings, $sessionManager );

		$this->_repository     = $repository;
		$this->_contactService = $contactService ?? new ContactService( $settings );
		$this->_validator      = $validator ?? new ContactFormValidator();
	}

	/**
	 * Issue a fresh CSRF token for contact forms (used by the widget's script).
	 * Kept separate so cached page markup can still obtain a valid token.
	 */
	#[Get('/contact/token', name: 'contact_token')]
	public function token( Request $request ): string
	{
		$csrf  = new CsrfToken( $this->getSessionManager() );
		$token = $csrf->getToken();

		return $this->renderJson( HttpResponseStatus::OK, [ 'token' => $token ] );
	}

	/**
	 * Standalone contact page rendering the default form.
	 */
	#[Get('/contact', name: 'contact')]
	public function index( Request $request ): string
	{
		$widget = new ContactFormWidget( $this->_contactService, $this->getSessionManager() );

		return $this->renderHtml(
			HttpResponseStatus::OK,
			[
				'Title'       => $this->getName() . ' | Contact',
				'Description' => $this->getDescription(),
				'ContactForm' => $widget->render( [] )
			],
			'index',
			'default'
		);
	}

	/**
	 * Handle a contact form submission.
	 *
	 * Persists first, then emails, then flags delivery. The CSRF filter runs
	 * before this method via the route filter.
	 */
	#[Post('/contact/submit', name: 'contact_submit', filters: ['csrf'])]
	public function submit( Request $request ): never
	{
		$key = (string) ( $request->post( 'form', '' ) ?? '' );

		$config = $this->_contactService->getFormConfig( $key );

		if( $config === null )
		{
			Log::warning( "Contact submission for unknown form key: '{$key}'" );
			$this->redirectBack( '/', [ 'error', 'Sorry, that form is not available.' ] );
		}

		// Honeypot: bots fill the hidden field. Pretend success without sending.
		$honeypot = $request->post( self::HONEYPOT_FIELD, '' );
		if( !empty( $honeypot ) )
		{
			Log::info( "Contact submission rejected by honeypot for form '{$key}'" );
			$this->redirectBack( '/', [ 'contact.' . $key . '.success', $this->successMessage( $config ) ] );
		}

		$fields = $config['fields'] ?? [];
		$values = $this->collectValues( $fields, $request );

		$errors = $this->_validator->validate( $fields, $values );

		if( !empty( $errors ) )
		{
			$message = 'Please correct the following: ' . implode( ' ', array_values( $errors ) );
			$this->redirectBack( '/', [ 'contact.' . $key . '.error', $message ] );
		}

		$replyTo = $this->_contactService->resolveReplyTo( $fields, $values );

		// Persist first so the submission survives even if email delivery fails.
		$submissionId = null;
		try
		{
			$submissionId = $this->_repository->create( [
				'form_key'       => $key,
				'recipient'      => $config['to'] ?? '',
				'subject'        => $config['subject'] ?? null,
				'reply_to_email' => $replyTo['email'] ?? null,
				'reply_to_name'  => $replyTo['name'] ?? null,
				'payload'        => json_encode( $values, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ),
				'ip_address'     => $request->getClientIp(),
				'user_agent'     => substr( (string) $request->server( 'HTTP_USER_AGENT', '' ), 0, 500 ),
				'delivered'      => false
			] );
		}
		catch( \Throwable $e )
		{
			Log::error( 'Contact submission persistence failed: ' . $e->getMessage() );
		}

		$sent = $this->_contactService->send( $key, $values );

		if( $sent && $submissionId !== null )
		{
			try
			{
				$this->_repository->markDelivered( $submissionId );
			}
			catch( \Throwable $e )
			{
				Log::error( 'Contact submission markDelivered failed: ' . $e->getMessage() );
			}
		}

		if( !$sent )
		{
			Log::error( "Contact email delivery failed for form '{$key}' (submission stored: " . ( $submissionId !== null ? 'yes' : 'no' ) . ')' );
		}

		// Always show success to the visitor: the submission is stored and can
		// be reviewed/retried from the admin even if email delivery failed.
		$this->redirectBack( '/', [ 'contact.' . $key . '.success', $this->successMessage( $config ) ] );
	}

	/**
	 * Collect only the configured field values from the request.
	 *
	 * @param array $fields
	 * @param Request $request
	 * @return array<string, mixed>
	 */
	private function collectValues( array $fields, Request $request ): array
	{
		$values = [];

		foreach( $fields as $field )
		{
			$name = $field['name'] ?? null;

			if( $name === null )
			{
				continue;
			}

			$type = $field['type'] ?? 'text';

			if( $type === 'checkbox' )
			{
				$raw = $request->post( $name, '' );
				$values[ $name ] = ( $raw === 'on' || $raw === '1' || $raw === 1 || $raw === true ) ? '1' : '';
				continue;
			}

			if( $type === 'checkboxes' || $type === 'multiselect' )
			{
				$values[ $name ] = $this->collectMultiValues( $field, $name, $request );
				continue;
			}

			$raw = $request->post( $name, '' );
			$values[ $name ] = is_string( $raw ) ? trim( $raw ) : $raw;
		}

		return $values;
	}

	/**
	 * Collect and sanitize the selected values for a multi-select field.
	 *
	 * Only values present in the field's configured option set are kept, so a
	 * crafted request cannot inject arbitrary selections into storage/email.
	 *
	 * @param array $field
	 * @param string $name
	 * @param Request $request
	 * @return array<int, string>
	 */
	private function collectMultiValues( array $field, string $name, Request $request ): array
	{
		$raw = $request->post( $name, [] );

		if( !is_array( $raw ) )
		{
			$raw = ( $raw === '' || $raw === null ) ? [] : [ $raw ];
		}

		$allowed  = FieldOptions::allowedValues( $field );
		$selected = [];

		foreach( $raw as $value )
		{
			if( !is_scalar( $value ) )
			{
				continue;
			}

			$value = trim( (string) $value );

			if( $value !== '' && in_array( $value, $allowed, true ) && !in_array( $value, $selected, true ) )
			{
				$selected[] = $value;
			}
		}

		return $selected;
	}

	/**
	 * Build the thank-you message for a form.
	 *
	 * @param array $config
	 * @return string
	 */
	private function successMessage( array $config ): string
	{
		return $config['success_message']
			?? 'Thank you for reaching out. Your message has been received.';
	}
}
