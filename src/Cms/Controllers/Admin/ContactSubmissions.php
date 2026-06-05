<?php

namespace Neuron\Cms\Controllers\Admin;

use Neuron\Cms\Auth\SessionManager;
use Neuron\Cms\Controllers\Content;
use Neuron\Cms\Enums\FlashMessageType;
use Neuron\Cms\Repositories\IContactSubmissionRepository;
use Neuron\Cms\Services\Contact\ContactService;
use Neuron\Data\Settings\SettingManager;
use Neuron\Mvc\IMvcApplication;
use Neuron\Mvc\Requests\Request;
use Neuron\Routing\Attributes\Delete;
use Neuron\Routing\Attributes\Get;
use Neuron\Routing\Attributes\RouteGroup;

/**
 * Admin screen for browsing stored contact form submissions.
 *
 * @package Neuron\Cms\Controllers\Admin
 */
#[RouteGroup(prefix: '/admin', filters: ['auth'])]
class ContactSubmissions extends Content
{
	private const PER_PAGE = 25;

	private IContactSubmissionRepository $_repository;
	private ContactService $_contactService;

	/**
	 * @param IMvcApplication $app
	 * @param SettingManager $settings
	 * @param SessionManager $sessionManager
	 * @param IContactSubmissionRepository $repository
	 * @param ContactService|null $contactService
	 */
	public function __construct(
		IMvcApplication              $app,
		SettingManager               $settings,
		SessionManager               $sessionManager,
		IContactSubmissionRepository $repository,
		?ContactService              $contactService = null
	)
	{
		parent::__construct( $app, $settings, $sessionManager );

		$this->_repository     = $repository;
		$this->_contactService = $contactService ?? new ContactService( $settings );
	}

	/**
	 * List submissions (paginated, optional ?form= filter).
	 */
	#[Get('/contact-submissions', name: 'admin_contact_submissions')]
	public function index( Request $request ): string
	{
		$this->initializeCsrfToken();

		$page    = max( 1, (int) ( $request->get( 'page', 1 ) ?? 1 ) );
		$formKey = $request->get( 'form', '' );
		$formKey = is_string( $formKey ) && $formKey !== '' ? $formKey : null;

		$result = $this->_repository->paginate( $page, self::PER_PAGE, $formKey );

		return $this->view()
			->title( 'Contact Submissions | Admin' )
			->description( 'Review contact form submissions' )
			->withCurrentUser()
			->withCsrfToken()
			->with( [
				'submissions'   => $result['items'],
				'total'         => $result['total'],
				'page'          => $result['page'],
				'pages'         => $result['pages'],
				'perPage'       => $result['per_page'],
				'formKeys'      => $this->_repository->formKeys(),
				'activeFormKey' => $formKey
			] )
			->render( 'index', 'admin' );
	}

	/**
	 * Show a single submission with its decoded field values.
	 */
	#[Get('/contact-submissions/:id', name: 'admin_contact_submission_show')]
	public function show( Request $request ): string
	{
		$this->initializeCsrfToken();

		$id         = (int) $request->getRouteParameter( 'id' );
		$submission = $this->_repository->findById( $id );

		if( $submission === null )
		{
			$this->redirect( 'admin_contact_submissions', [], [ FlashMessageType::ERROR->value, 'Submission not found' ] );
		}

		$payload = json_decode( (string) ( $submission['payload'] ?? '{}' ), true );
		if( !is_array( $payload ) )
		{
			$payload = [];
		}

		// Prefer the form's configured field labels/order; fall back to payload keys.
		$fields = $this->_contactService->getFields( (string) ( $submission['form_key'] ?? '' ) );

		return $this->view()
			->title( 'Contact Submission | Admin' )
			->description( 'Contact form submission detail' )
			->withCurrentUser()
			->withCsrfToken()
			->with( [
				'submission' => $submission,
				'payload'    => $payload,
				'fields'     => $fields
			] )
			->render( 'show', 'admin' );
	}

	/**
	 * Delete a submission.
	 */
	#[Delete('/contact-submissions/:id', name: 'admin_contact_submission_delete', filters: ['csrf'])]
	public function destroy( Request $request ): never
	{
		$id = (int) $request->getRouteParameter( 'id' );

		try
		{
			$this->_repository->delete( $id );
			$this->redirect( 'admin_contact_submissions', [], [ FlashMessageType::SUCCESS->value, 'Submission deleted' ] );
		}
		catch( \Throwable $e )
		{
			$this->redirect( 'admin_contact_submissions', [], [ FlashMessageType::ERROR->value, 'Failed to delete submission: ' . $e->getMessage() ] );
		}
	}
}
