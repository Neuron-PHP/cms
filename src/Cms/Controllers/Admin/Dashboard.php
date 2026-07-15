<?php

namespace Neuron\Cms\Controllers\Admin;

use Neuron\Cms\Auth\SessionManager;
use Neuron\Cms\Controllers\Content;
use Neuron\Cms\Enums\FlashMessageType;
use Neuron\Cms\Repositories\DatabaseContactSubmissionRepository;
use Neuron\Cms\Repositories\IContactSubmissionRepository;
use Neuron\Core\Exceptions\NotFound;
use Neuron\Data\Settings\SettingManager;
use Neuron\Log\Log;
use Neuron\Mvc\IMvcApplication;
use Neuron\Mvc\Requests\Request;
use Neuron\Mvc\Responses\HttpResponseStatus;
use Neuron\Routing\Attributes\Get;
use Neuron\Routing\Attributes\RouteGroup;

/**
 * Admin dashboard controller.
 *
 * @package Neuron\Cms\Controllers\Admin
 */
#[RouteGroup(prefix: '/admin', filters: ['auth'])]
class Dashboard extends Content
{
	private const RECENT_SUBMISSIONS = 5;

	private ?IContactSubmissionRepository $_submissions;

	/**
	 * @param IMvcApplication $app
	 * @param SettingManager $settings
	 * @param SessionManager $sessionManager
	 * @param IContactSubmissionRepository|null $submissions
	 * @return void
	 * @throws \Exception
	 */
	public function __construct(
		IMvcApplication $app,
		SettingManager $settings,
		SessionManager $sessionManager,
		?IContactSubmissionRepository $submissions = null
	)
	{
		parent::__construct( $app, $settings, $sessionManager );

		$this->_submissions = $submissions;
	}


	/**
	 * Show admin dashboard
	 * @param Request $request
	 * @return string
	 * @throws NotFound
	 */
	#[Get('/dashboard', name: 'admin_dashboard')]
	#[Get('/', name: 'admin')]
	public function index( Request $request ): string
	{
		$this->initializeCsrfToken();

		$recent = $this->getRecentSubmissions();

		return $this->view()
			->title( 'Dashboard' )
			->description( 'Admin Dashboard' )
			->withCurrentUser()
			->withCsrfToken()
			->with( 'Error', $this->getSessionManager()->getFlash( FlashMessageType::ERROR->value ) )
			->with( 'Success', $this->getSessionManager()->getFlash( FlashMessageType::SUCCESS->value ) )
			->with( 'RecentSubmissions', $recent['items'] )
			->with( 'TotalSubmissions', $recent['total'] )
			->render( 'index', 'admin' );
	}

	/**
	 * Latest contact submissions for the dashboard panel.
	 *
	 * Failures are logged and swallowed so a storage problem can never
	 * prevent the dashboard itself from rendering.
	 *
	 * @return array{items: array, total: int}
	 */
	private function getRecentSubmissions(): array
	{
		try
		{
			$repository = $this->_submissions
				?? new DatabaseContactSubmissionRepository( $this->_settings );

			$result = $repository->paginate( 1, self::RECENT_SUBMISSIONS );

			return [
				'items' => $result['items'] ?? [],
				'total' => (int) ( $result['total'] ?? 0 )
			];
		}
		catch( \Throwable $e )
		{
			Log::warning( 'Dashboard: unable to load contact submissions: ' . $e->getMessage() );

			return [ 'items' => [], 'total' => 0 ];
		}
	}
}
