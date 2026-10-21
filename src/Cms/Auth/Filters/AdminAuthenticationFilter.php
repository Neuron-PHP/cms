<?php

namespace Neuron\Cms\Auth\Filters;

use Closure;
use Neuron\Cms\Exceptions\UnauthenticatedException;
use Neuron\Cms\Models\User;
use Neuron\Cms\Services\Auth\Authentication;
use Neuron\Core\Exceptions\Forbidden;
use Neuron\Core\Registry\RegistryKeys;
use Neuron\Log\Log;
use Neuron\Patterns\Registry;
use Neuron\Routing\Filter;
use Neuron\Routing\RouteMap;

/**
 * Admin area filter: authenticated *and* holding a staff role.
 *
 * `auth` only proves a session exists, so a subscriber who logs in would
 * otherwise reach every /admin screen. This filter redirects anonymous
 * visitors to login exactly like AuthenticationFilter, then refuses anyone
 * whose role is not in the allowed list with a 403 (Forbidden, rendered by
 * the MVC application's error controller).
 *
 * Authors are allowed by default because the Posts, Pages and Events
 * controllers already scope their work to the author's own content.
 *
 * Registered as `admin` by Neuron\Cms\boot(); a site initializer may
 * re-register it with a different role list.
 *
 * @package Neuron\Cms\Auth\Filters
 */
class AdminAuthenticationFilter extends Filter
{
	public const DEFAULT_ROLES = [ User::ROLE_ADMIN, User::ROLE_EDITOR, User::ROLE_AUTHOR ];

	/** @var Authentication|Closure():Authentication */
	private Authentication|Closure $_authentication;

	/** @var list<string> */
	private array $_roles;

	private string $_loginUrl;
	private string $_redirectParam = 'redirect';

	/**
	 * @param Authentication|Closure $authentication Service, or a resolver
	 *        called on first use so boot() need not build it per request.
	 * @param list<string> $roles Roles allowed into the admin area
	 * @param string $loginUrl
	 */
	public function __construct(
		Authentication|Closure $authentication,
		array $roles = self::DEFAULT_ROLES,
		string $loginUrl = '/login'
	)
	{
		$this->_authentication = $authentication;
		$this->_roles = array_values( array_map( 'strval', $roles ) );
		$this->_loginUrl = $loginUrl;

		parent::__construct(
			function( RouteMap $route ) { $this->check( $route ); },
			null
		);
	}

	/**
	 * @return list<string>
	 */
	public function getRoles(): array
	{
		return $this->_roles;
	}

	/**
	 * @param string $loginUrl
	 * @return self
	 */
	public function setLoginUrl( string $loginUrl ): self
	{
		$this->_loginUrl = $loginUrl;
		return $this;
	}

	/**
	 * @param RouteMap $route
	 * @return void
	 * @throws UnauthenticatedException Anonymous visitor; carries the login redirect
	 * @throws Forbidden Authenticated but not a staff role
	 */
	protected function check( RouteMap $route ): void
	{
		$user = $this->authentication()->user();

		if( !$user )
		{
			$intendedUrl = $_SERVER['REQUEST_URI'] ?? $route->getPath();
			$separator = ( strpos( $this->_loginUrl, '?' ) === false ) ? '?' : '&';
			$redirectUrl = $this->_loginUrl . $separator . http_build_query( [ $this->_redirectParam => $intendedUrl ] );

			throw new UnauthenticatedException(
				$redirectUrl,
				$intendedUrl,
				'User not authenticated, redirect required to: ' . $redirectUrl
			);
		}

		if( !in_array( $user->getRole(), $this->_roles, true ) )
		{
			Log::warning( "Admin access refused for user {$user->getId()} with role '{$user->getRole()}' on {$route->getPath()}" );

			throw new Forbidden(
				'The admin area requires a staff account.',
				$route->getPath(),
				'admin'
			);
		}

		$registry = Registry::getInstance();
		$registry->set( RegistryKeys::AUTH_USER, $user );
		$registry->set( RegistryKeys::AUTH_USER_ID, $user->getId() );
		$registry->set( RegistryKeys::AUTH_USER_ROLE, $user->getRole() );
	}

	/**
	 * @return Authentication
	 */
	private function authentication(): Authentication
	{
		if( $this->_authentication instanceof Closure )
		{
			$this->_authentication = ( $this->_authentication )();
		}

		return $this->_authentication;
	}
}
