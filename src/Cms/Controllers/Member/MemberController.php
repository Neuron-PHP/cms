<?php

namespace Neuron\Cms\Controllers\Member;

use Neuron\Cms\Controllers\Content;
use Neuron\Cms\Models\User;
use Neuron\Core\Registry\RegistryKeys;
use Neuron\Mvc\Responses\HttpResponseStatus;
use Neuron\Patterns\Registry;

/**
 * Base controller for application pages that render in the member shell.
 *
 * Extend this for the pages of an application built on the CMS. It carries no
 * route group or filters, so subclasses choose their own URLs and filters
 * (`auth`, `member`, or site-specific ones):
 *
 * ```php
 * class ControlController extends MemberController
 * {
 *     #[Get( '/controls', name: 'controls', filters: ['auth'] )]
 *     public function index( Request $request ): string
 *     {
 *         return $this->renderPage( 'index', [ 'controls' => $controls ], 'Controls' );
 *     }
 * }
 * ```
 *
 * Views resolve from the controller's view directory (ControlController →
 * resources/views/control/) and render inside layouts/member.php, which also
 * shows navigation from MemberNavigation and pending flash messages.
 *
 * @package Neuron\Cms\Controllers\Member
 */
abstract class MemberController extends Content
{
	private ?string $_viewPathOverride = null;

	/**
	 * Render a page from this controller's view directory in the member shell.
	 *
	 * The current user and CSRF token are always available to the view as
	 * `$User` and `$csrfToken`; shared ViewDataProvider data is merged in.
	 *
	 * @param array<string, mixed> $data
	 */
	protected function renderPage(
		string $page,
		array $data = [],
		?string $title = null,
		?string $description = null,
		HttpResponseStatus $status = HttpResponseStatus::OK,
		string $layout = 'member'
	): string
	{
		$this->initializeCsrfToken();

		$view = $this->view()
			->status( $status )
			->withCurrentUser()
			->withCsrfToken()
			->with( $data );

		if( $title !== null )
		{
			$view->title( $title );
		}

		if( $description !== null )
		{
			$view->description( $description );
		}

		return $view->render( $page, $layout );
	}

	/**
	 * Render a view by its path under the views root, in the member shell.
	 *
	 * For views outside this controller's directory, such as shared error
	 * pages: renderView( 'errors/404', status: HttpResponseStatus::NOT_FOUND ).
	 *
	 * @param string $view View path without extension, e.g. 'errors/404'.
	 * @param array<string, mixed> $data
	 */
	protected function renderView(
		string $view,
		array $data = [],
		?string $title = null,
		?string $description = null,
		HttpResponseStatus $status = HttpResponseStatus::OK,
		string $layout = 'member'
	): string
	{
		$view = trim( $view, '/' );
		$separator = strrpos( $view, '/' );

		if( $separator === false )
		{
			return $this->renderPage( $view, $data, $title, $description, $status, $layout );
		}

		$this->_viewPathOverride = substr( $view, 0, $separator );

		try
		{
			return $this->renderPage( substr( $view, $separator + 1 ), $data, $title, $description, $status, $layout );
		}
		finally
		{
			$this->_viewPathOverride = null;
		}
	}

	/**
	 * Uses the directory chosen by renderView() when one is active.
	 */
	protected function getControllerViewPath(): string
	{
		return $this->_viewPathOverride ?? parent::getControllerViewPath();
	}

	/**
	 * The authenticated user, if any.
	 */
	protected function currentUser(): ?User
	{
		// Set by the auth/member filters; fall back to the session for routes
		// without an authentication filter.
		$user = Registry::getInstance()->get( RegistryKeys::AUTH_USER ) ?? auth();

		return $user instanceof User ? $user : null;
	}

	protected function currentUserId(): ?int
	{
		$user = $this->currentUser();

		return $user !== null ? (int)$user->getId() : null;
	}
}
