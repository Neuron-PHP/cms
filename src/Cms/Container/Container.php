<?php

namespace Neuron\Cms\Container;

use DI\ContainerBuilder;
use Neuron\Cms\Services\SlugGenerator;
use Neuron\Cms\Auth\SessionManager;
use Neuron\Cms\Auth\PasswordHasher;
use Neuron\Cms\Services\Auth\CsrfToken;
use Neuron\Cms\Services\Auth\Authentication;
use Neuron\Cms\Services\Auth\IAuthenticationService;
use Neuron\Cms\Services\Auth\PasswordResetter;
use Neuron\Cms\Services\Auth\IPasswordResetter;
use Neuron\Cms\Services\Auth\EmailVerifier;
use Neuron\Cms\Services\Auth\IEmailVerifier;
use Neuron\Cms\Services\Member\RegistrationService;
use Neuron\Cms\Services\Member\IRegistrationService;
use Neuron\Cms\Repositories\DatabaseUserRepository;
use Neuron\Cms\Repositories\DatabasePostRepository;
use Neuron\Cms\Repositories\DatabasePageRepository;
use Neuron\Cms\Repositories\DatabaseCategoryRepository;
use Neuron\Cms\Repositories\DatabaseTagRepository;
use Neuron\Cms\Repositories\DatabaseEventRepository;
use Neuron\Cms\Repositories\DatabaseEventCategoryRepository;
use Neuron\Cms\Repositories\DatabasePasswordResetTokenRepository;
use Neuron\Cms\Repositories\IUserRepository;
use Neuron\Cms\Repositories\IPostRepository;
use Neuron\Cms\Repositories\IPageRepository;
use Neuron\Cms\Repositories\ICategoryRepository;
use Neuron\Cms\Repositories\ITagRepository;
use Neuron\Cms\Repositories\IEventRepository;
use Neuron\Cms\Repositories\IEventCategoryRepository;
use Neuron\Cms\Repositories\IPasswordResetTokenRepository;
use Neuron\Data\Settings\SettingManager;
use Neuron\Patterns\Registry;
use Neuron\Patterns\Container\IContainer;
use Psr\Container\ContainerInterface;

// Service Interfaces
use Neuron\Cms\Services\User\{IUserCreator, IUserUpdater, IUserDeleter};
use Neuron\Cms\Services\Post\{IPostCreator, IPostUpdater, IPostDeleter};
use Neuron\Cms\Services\Page\{IPageCreator, IPageUpdater};
use Neuron\Cms\Services\Event\{IEventCreator, IEventUpdater};
use Neuron\Cms\Services\EventCategory\{IEventCategoryCreator, IEventCategoryUpdater};
use Neuron\Cms\Services\Category\{ICategoryCreator, ICategoryUpdater};
use Neuron\Cms\Services\Tag\ITagCreator;

// Service Implementations
use Neuron\Cms\Services\User\{Creator as UserCreator, Updater as UserUpdater, Deleter as UserDeleter};
use Neuron\Cms\Services\Post\{Creator as PostCreator, Updater as PostUpdater, Deleter as PostDeleter};
use Neuron\Cms\Services\Page\{Creator as PageCreator, Updater as PageUpdater};
use Neuron\Cms\Services\Event\{Creator as EventCreator, Updater as EventUpdater};
use Neuron\Cms\Services\EventCategory\{Creator as EventCategoryCreator, Updater as EventCategoryUpdater};
use Neuron\Cms\Services\Category\{Creator as CategoryCreator, Updater as CategoryUpdater};
use Neuron\Cms\Services\Tag\Creator as TagCreator;

/**
 * CMS Dependency Injection Container
 *
 * Builds and configures the PSR-11 compliant container for the CMS.
 * Manages service definitions, dependencies, and lifecycle.
 *
 * @package Neuron\Cms\Container
 */
class Container
{
	private static ?IContainer $instance = null;

	/**
	 * Build and return the DI container
	 *
	 * @param SettingManager $settings Application settings
	 * @return IContainer
	 * @throws \Exception
	 */
	public static function build( SettingManager $settings ): IContainer
	{
		if( self::$instance !== null )
		{
			return self::$instance;
		}

		$builder = new ContainerBuilder();

		// Enable compilation for production (caching)
		// $builder->enableCompilation( __DIR__ . '/../../../var/cache/container' );

		// Add definitions
		$builder->addDefinitions([
			// Settings
			SettingManager::class => $settings,

			// Core Services - Singletons
			SlugGenerator::class => \DI\create( SlugGenerator::class ),

			SessionManager::class => \DI\factory( function() use ( $settings ) {
				$config = [];
				try
				{
					$lifetime = $settings->get( 'session', 'lifetime' );
					if( $lifetime )
					{
						$config['lifetime'] = (int)$lifetime;
					}
				}
				catch( \Exception $e )
				{
					// Use defaults if settings not found
				}
				return new SessionManager( $config );
			}),

			PasswordHasher::class => \DI\factory( function() use ( $settings ) {
				$hasher = new PasswordHasher();
				try
				{
					$minLength = $settings->get( 'password', 'min_length' );
					if( $minLength )
					{
						$hasher->setMinLength( (int)$minLength );
					}
				}
				catch( \Exception $e )
				{
					// Use defaults
				}
				return $hasher;
			}),

			// Repositories
			DatabaseUserRepository::class => \DI\create( DatabaseUserRepository::class )
				->constructor( \DI\get( SettingManager::class ) ),

			// Auth Services
			CsrfToken::class => \DI\create( CsrfToken::class )
				->constructor( \DI\get( SessionManager::class ) ),

			Authentication::class => \DI\create( Authentication::class )
				->constructor(
					\DI\get( DatabaseUserRepository::class ),
					\DI\get( SessionManager::class ),
					\DI\get( PasswordHasher::class )
				),

			EmailVerifier::class => \DI\factory( function( ContainerInterface $c ) use ( $settings ) {
				$userRepository = $c->get( DatabaseUserRepository::class );
				return new EmailVerifier( $userRepository, $settings );
			}),

			RegistrationService::class => \DI\factory( function( ContainerInterface $c ) use ( $settings ) {
				$userRepository = $c->get( DatabaseUserRepository::class );
				$emailVerifier = $c->get( EmailVerifier::class );
				$passwordHasher = $c->get( PasswordHasher::class );
				return new RegistrationService( $userRepository, $emailVerifier, $passwordHasher, $settings );
			}),

			PasswordResetter::class => \DI\factory( function( ContainerInterface $c ) use ( $settings ) {
				$tokenRepository = $c->get( IPasswordResetTokenRepository::class );
				$userRepository = $c->get( IUserRepository::class );
				$passwordHasher = $c->get( PasswordHasher::class );

				// Get base path and site URL from settings
				$basePath = Registry::getInstance()->get( 'Base.Path' ) ?? getcwd();
				$siteUrl = $settings->get( 'site', 'url' ) ?? 'http://localhost';
				$resetUrl = rtrim( $siteUrl, '/' ) . '/reset-password';

				$passwordResetter = new PasswordResetter(
					$tokenRepository,
					$userRepository,
					$passwordHasher,
					$settings,
					$basePath,
					$resetUrl
				);

				// Set token expiration if configured
				try {
					$tokenExpiration = $settings->get( 'password_reset', 'token_expiration' );
					if( $tokenExpiration ) {
						$passwordResetter->setTokenExpirationMinutes( (int)$tokenExpiration );
					}
				} catch( \Exception $e ) {
					// Use default expiration
				}

				return $passwordResetter;
			}),

			// Interface Bindings - Auth
			IAuthenticationService::class => \DI\get( Authentication::class ),
			IPasswordResetter::class => \DI\get( PasswordResetter::class ),
			IEmailVerifier::class => \DI\get( EmailVerifier::class ),
			IRegistrationService::class => \DI\get( RegistrationService::class ),

			// Interface Bindings - Repositories
			IUserRepository::class => \DI\get( DatabaseUserRepository::class ),
			IPostRepository::class => \DI\autowire( DatabasePostRepository::class ),
			IPageRepository::class => \DI\autowire( DatabasePageRepository::class ),
			ICategoryRepository::class => \DI\autowire( DatabaseCategoryRepository::class ),
			ITagRepository::class => \DI\autowire( DatabaseTagRepository::class ),
			IEventRepository::class => \DI\autowire( DatabaseEventRepository::class ),
			IEventCategoryRepository::class => \DI\autowire( DatabaseEventCategoryRepository::class ),
			IPasswordResetTokenRepository::class => \DI\autowire( DatabasePasswordResetTokenRepository::class ),

			// Interface Bindings - User Services
			IUserCreator::class => \DI\autowire( UserCreator::class ),
			IUserUpdater::class => \DI\autowire( UserUpdater::class ),
			IUserDeleter::class => \DI\autowire( UserDeleter::class ),

			// Interface Bindings - Post Services
			IPostCreator::class => \DI\autowire( PostCreator::class ),
			IPostUpdater::class => \DI\autowire( PostUpdater::class ),
			IPostDeleter::class => \DI\autowire( PostDeleter::class ),

			// Interface Bindings - Page Services
			IPageCreator::class => \DI\autowire( PageCreator::class ),
			IPageUpdater::class => \DI\autowire( PageUpdater::class ),

			// Interface Bindings - Event Services
			IEventCreator::class => \DI\autowire( EventCreator::class ),
			IEventUpdater::class => \DI\autowire( EventUpdater::class ),

			// Interface Bindings - Category Services
			ICategoryCreator::class => \DI\autowire( CategoryCreator::class ),
			ICategoryUpdater::class => \DI\autowire( CategoryUpdater::class ),

			// Interface Bindings - Tag Services
			ITagCreator::class => \DI\autowire( TagCreator::class ),

			// Interface Bindings - EventCategory Services
			IEventCategoryCreator::class => \DI\autowire( EventCategoryCreator::class ),
			IEventCategoryUpdater::class => \DI\autowire( EventCategoryUpdater::class ),
		]);

		$psr11Container = $builder->build();

		// Wrap PSR-11 container with Neuron IContainer adapter
		self::$instance = new ContainerAdapter( $psr11Container );

		// Store container in Registry for backward compatibility
		Registry::getInstance()->set( 'Container', self::$instance );

		return self::$instance;
	}

	/**
	 * Get the container instance
	 *
	 * @return ContainerInterface|null
	 */
	public static function getInstance(): ?IContainer
	{
		return self::$instance;
	}

	/**
	 * Reset the container instance (useful for testing)
	 */
	public static function reset(): void
	{
		self::$instance = null;
	}
}
