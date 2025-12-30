<?php

namespace Neuron\Cms\Container;

use Neuron\Patterns\Container\IContainer;
use Neuron\Patterns\Container\IServiceProvider;
use Neuron\Cms\Repositories\IUserRepository;
use Neuron\Cms\Repositories\IPostRepository;
use Neuron\Cms\Repositories\IPageRepository;
use Neuron\Cms\Repositories\ICategoryRepository;
use Neuron\Cms\Repositories\ITagRepository;
use Neuron\Cms\Repositories\IEventRepository;
use Neuron\Cms\Repositories\IEventCategoryRepository;
use Neuron\Cms\Repositories\DatabaseUserRepository;
use Neuron\Cms\Repositories\DatabasePostRepository;
use Neuron\Cms\Repositories\DatabasePageRepository;
use Neuron\Cms\Repositories\DatabaseCategoryRepository;
use Neuron\Cms\Repositories\DatabaseTagRepository;
use Neuron\Cms\Repositories\DatabaseEventRepository;
use Neuron\Cms\Repositories\DatabaseEventCategoryRepository;
use Neuron\Cms\Services\User\IUserCreator;
use Neuron\Cms\Services\User\IUserUpdater;
use Neuron\Cms\Services\User\IUserDeleter;
use Neuron\Cms\Services\User\Creator;
use Neuron\Cms\Services\User\Updater;
use Neuron\Cms\Services\User\Deleter;
use Neuron\Cms\Auth\PasswordHasher;
use Neuron\Cms\Auth\SessionManager;
use Neuron\Cms\Auth\ResendVerificationThrottle;
use Neuron\Cms\Services\Content\EditorJsRenderer;
use Neuron\Cms\Services\Content\ShortcodeParser;
use Neuron\Cms\Services\Widget\WidgetRenderer;
use Neuron\Cms\Services\Dto\DtoFactoryService;
use Neuron\Data\Settings\SettingManager;
use Neuron\Events\Emitter;
use Neuron\Routing\IIpResolver;
use Neuron\Routing\DefaultIpResolver;
use Neuron\Patterns\Registry;

/**
 * CMS service provider
 *
 * Registers all CMS-specific services and bindings with the container.
 *
 * @package Neuron\Cms\Container
 */
class CmsServiceProvider implements IServiceProvider
{
	/**
	 * Register CMS services in the container
	 *
	 * @param IContainer $container
	 * @return void
	 */
	public function register( IContainer $container ): void
	{
		$this->registerRepositories( $container );
		$this->registerUserServices( $container );
		$this->registerAuthServices( $container );
		$this->registerContentServices( $container );
		$this->registerSharedServices( $container );
	}

	/**
	 * Register repository bindings
	 *
	 * @param IContainer $container
	 * @return void
	 */
	private function registerRepositories( IContainer $container ): void
	{
		// Bind repository interfaces to database implementations
		$container->bind( IUserRepository::class, DatabaseUserRepository::class );
		$container->bind( IPostRepository::class, DatabasePostRepository::class );
		$container->bind( IPageRepository::class, DatabasePageRepository::class );
		$container->bind( ICategoryRepository::class, DatabaseCategoryRepository::class );
		$container->bind( ITagRepository::class, DatabaseTagRepository::class );
		$container->bind( IEventRepository::class, DatabaseEventRepository::class );
		$container->bind( IEventCategoryRepository::class, DatabaseEventCategoryRepository::class );
	}

	/**
	 * Register user service bindings
	 *
	 * @param IContainer $container
	 * @return void
	 */
	private function registerUserServices( IContainer $container ): void
	{
		// User CRUD services
		$container->bind( IUserCreator::class, Creator::class );
		$container->bind( IUserUpdater::class, Updater::class );
		$container->bind( IUserDeleter::class, Deleter::class );
	}

	/**
	 * Register authentication services
	 *
	 * @param IContainer $container
	 * @return void
	 */
	private function registerAuthServices( IContainer $container ): void
	{
		// Password hasher as singleton (stateless, can be shared)
		$container->singleton( PasswordHasher::class, function( $c ) {
			return new PasswordHasher();
		});

		// Session manager as singleton (manages session state)
		$container->singleton( SessionManager::class, function( $c ) {
			return new SessionManager();
		});

		// Resend verification throttle
		$container->singleton( ResendVerificationThrottle::class, function( $c ) {
			return new ResendVerificationThrottle();
		});

		// IP resolver
		$container->bind( IIpResolver::class, DefaultIpResolver::class );
	}

	/**
	 * Register content rendering services
	 *
	 * @param IContainer $container
	 * @return void
	 */
	private function registerContentServices( IContainer $container ): void
	{
		// Widget renderer (singleton - stateless service)
		$container->singleton( WidgetRenderer::class, function( $c ) {
			return new WidgetRenderer(
				$c->get( IPostRepository::class )
			);
		});

		// Shortcode parser (singleton - stateless service)
		$container->singleton( ShortcodeParser::class, function( $c ) {
			return new ShortcodeParser(
				$c->get( WidgetRenderer::class )
			);
		});

		// EditorJS renderer (singleton - stateless service)
		$container->singleton( EditorJsRenderer::class, function( $c ) {
			return new EditorJsRenderer(
				$c->get( ShortcodeParser::class )
			);
		});

		// DTO factory service
		$container->singleton( DtoFactoryService::class, function( $c ) {
			return new DtoFactoryService();
		});
	}

	/**
	 * Register shared framework services
	 *
	 * These services might come from Registry in a transitional period,
	 * but should eventually be fully managed by the container.
	 *
	 * @param IContainer $container
	 * @return void
	 */
	private function registerSharedServices( IContainer $container ): void
	{
		// Settings manager - transition from Registry
		$container->singleton( SettingManager::class, function( $c ) {
			// During transition, still get from Registry
			// Later: create directly
			return Registry::getInstance()->get( 'Settings' );
		});

		// Event emitter - transition from Registry
		$container->singleton( Emitter::class, function( $c ) {
			// During transition, get from Registry if available
			$emitter = Registry::getInstance()->get( 'EventEmitter' );
			if( !$emitter )
			{
				$emitter = new Emitter();
				Registry::getInstance()->set( 'EventEmitter', $emitter );
			}
			return $emitter;
		});
	}
}
