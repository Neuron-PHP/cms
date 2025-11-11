<?php

namespace Tests\Cms\Listeners;

use Neuron\Cms\Events\UserCreatedEvent;
use Neuron\Cms\Events\UserUpdatedEvent;
use Neuron\Cms\Events\PostPublishedEvent;
use Neuron\Cms\Listeners\SendWelcomeEmailListener;
use Neuron\Cms\Listeners\LogUserActivityListener;
use Neuron\Cms\Listeners\ClearCacheListener;
use Neuron\Cms\Models\User;
use Neuron\Cms\Models\Post;
use PHPUnit\Framework\TestCase;

class ListenersTest extends TestCase
{
	public function testSendWelcomeEmailListenerHandlesUserCreatedEvent(): void
	{
		$user = new User();
		$user->setId( 1 );
		$user->setUsername( 'newuser' );
		$user->setEmail( 'newuser@example.com' );

		$event = new UserCreatedEvent( $user );
		$listener = new SendWelcomeEmailListener();

		// Should not throw exception
		$listener->event( $event );

		$this->assertTrue( true );
	}

	public function testSendWelcomeEmailListenerIgnoresOtherEvents(): void
	{
		$user = new User();
		$user->setId( 1 );

		$event = new UserUpdatedEvent( $user );
		$listener = new SendWelcomeEmailListener();

		// Should handle gracefully without errors
		$listener->event( $event );

		$this->assertTrue( true );
	}

	public function testLogUserActivityListenerHandlesUserCreatedEvent(): void
	{
		$user = new User();
		$user->setId( 1 );
		$user->setUsername( 'testuser' );

		$event = new UserCreatedEvent( $user );
		$listener = new LogUserActivityListener();

		// Should not throw exception
		$listener->event( $event );

		$this->assertTrue( true );
	}

	public function testLogUserActivityListenerHandlesUserUpdatedEvent(): void
	{
		$user = new User();
		$user->setId( 2 );
		$user->setUsername( 'updateduser' );

		$event = new UserUpdatedEvent( $user );
		$listener = new LogUserActivityListener();

		// Should not throw exception
		$listener->event( $event );

		$this->assertTrue( true );
	}

	public function testLogUserActivityListenerIgnoresOtherEvents(): void
	{
		$post = new Post();
		$post->setId( 1 );

		$event = new PostPublishedEvent( $post );
		$listener = new LogUserActivityListener();

		// Should handle gracefully
		$listener->event( $event );

		$this->assertTrue( true );
	}

	public function testClearCacheListenerHandlesPostPublishedEvent(): void
	{
		$post = new Post();
		$post->setId( 1 );
		$post->setTitle( 'Published Post' );
		$post->setStatus( Post::STATUS_PUBLISHED );

		$event = new PostPublishedEvent( $post );
		$listener = new ClearCacheListener();

		// Should not throw exception
		$listener->event( $event );

		$this->assertTrue( true );
	}

	public function testClearCacheListenerIgnoresOtherEvents(): void
	{
		$user = new User();
		$user->setId( 1 );

		$event = new UserCreatedEvent( $user );
		$listener = new ClearCacheListener();

		// Should handle gracefully
		$listener->event( $event );

		$this->assertTrue( true );
	}
}
