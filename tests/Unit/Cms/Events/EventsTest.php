<?php

namespace Tests\Cms\Events;

use Neuron\Cms\Events\UserCreatedEvent;
use Neuron\Cms\Events\UserUpdatedEvent;
use Neuron\Cms\Events\UserDeletedEvent;
use Neuron\Cms\Events\PostCreatedEvent;
use Neuron\Cms\Events\PostPublishedEvent;
use Neuron\Cms\Events\PostDeletedEvent;
use Neuron\Cms\Events\CategoryCreatedEvent;
use Neuron\Cms\Events\CategoryUpdatedEvent;
use Neuron\Cms\Events\CategoryDeletedEvent;
use Neuron\Cms\Events\PaymentCompletedEvent;
use Neuron\Cms\Events\PaymentFailedEvent;
use Neuron\Cms\Models\User;
use Neuron\Cms\Models\Post;
use Neuron\Cms\Models\Category;
use PHPUnit\Framework\TestCase;

class EventsTest extends TestCase
{
	public function testUserCreatedEvent(): void
	{
		$user = new User();
		$user->setId( 1 );
		$user->setUsername( 'testuser' );

		$event = new UserCreatedEvent( $user );

		$this->assertEquals( 'user.created', $event->getName() );
		$this->assertSame( $user, $event->user );
		$this->assertEquals( 1, $event->user->getId() );
	}

	public function testUserUpdatedEvent(): void
	{
		$user = new User();
		$user->setId( 2 );
		$user->setUsername( 'updateduser' );

		$event = new UserUpdatedEvent( $user );

		$this->assertEquals( 'user.updated', $event->getName() );
		$this->assertSame( $user, $event->user );
		$this->assertEquals( 'updateduser', $event->user->getUsername() );
	}

	public function testUserDeletedEvent(): void
	{
		$event = new UserDeletedEvent( 5 );

		$this->assertEquals( 'user.deleted', $event->getName() );
		$this->assertEquals( 5, $event->userId );
	}

	public function testPostCreatedEvent(): void
	{
		$post = new Post();
		$post->setId( 10 );
		$post->setTitle( 'New Post' );

		$event = new PostCreatedEvent( $post );

		$this->assertEquals( 'post.created', $event->getName() );
		$this->assertSame( $post, $event->post );
		$this->assertEquals( 'New Post', $event->post->getTitle() );
	}

	public function testPostPublishedEvent(): void
	{
		$post = new Post();
		$post->setId( 15 );
		$post->setTitle( 'Published Post' );
		$post->setStatus( Post::STATUS_PUBLISHED );

		$event = new PostPublishedEvent( $post );

		$this->assertEquals( 'post.published', $event->getName() );
		$this->assertSame( $post, $event->post );
		$this->assertEquals( Post::STATUS_PUBLISHED, $event->post->getStatus() );
	}

	public function testPostDeletedEvent(): void
	{
		$event = new PostDeletedEvent( 20 );

		$this->assertEquals( 'post.deleted', $event->getName() );
		$this->assertEquals( 20, $event->postId );
	}

	public function testCategoryCreatedEvent(): void
	{
		$category = new Category();
		$category->setId( 3 );
		$category->setName( 'Technology' );

		$event = new CategoryCreatedEvent( $category );

		$this->assertEquals( 'category.created', $event->getName() );
		$this->assertSame( $category, $event->category );
		$this->assertEquals( 'Technology', $event->category->getName() );
	}

	public function testCategoryUpdatedEvent(): void
	{
		$category = new Category();
		$category->setId( 4 );
		$category->setName( 'Updated Category' );

		$event = new CategoryUpdatedEvent( $category );

		$this->assertEquals( 'category.updated', $event->getName() );
		$this->assertSame( $category, $event->category );
	}

	public function testCategoryDeletedEvent(): void
	{
		$event = new CategoryDeletedEvent( 7 );

		$this->assertEquals( 'category.deleted', $event->getName() );
		$this->assertEquals( 7, $event->categoryId );
	}

	public function testPaymentCompletedEvent(): void
	{
		$payment = [ 'id' => 11, 'amount_cents' => 2500, 'purpose' => 'donation' ];

		$event = new PaymentCompletedEvent( $payment, false );

		$this->assertEquals( 'payment.completed', $event->getName() );
		$this->assertSame( $payment, $event->payment );
		$this->assertFalse( $event->isRenewal );
	}

	public function testPaymentCompletedEventRenewal(): void
	{
		$payment = [ 'id' => 12, 'amount_cents' => 2500, 'purpose' => 'membership' ];

		$event = new PaymentCompletedEvent( $payment, true );

		$this->assertEquals( 'payment.completed', $event->getName() );
		$this->assertTrue( $event->isRenewal );
	}

	public function testPaymentFailedEvent(): void
	{
		$payment = [ 'id' => 13, 'subscription_id' => 'sub_1' ];

		$event = new PaymentFailedEvent( $payment, 'sub_1' );

		$this->assertEquals( 'payment.failed', $event->getName() );
		$this->assertSame( $payment, $event->payment );
		$this->assertEquals( 'sub_1', $event->subscriptionId );
	}

	public function testPaymentFailedEventWithoutPayment(): void
	{
		$event = new PaymentFailedEvent( null, null );

		$this->assertEquals( 'payment.failed', $event->getName() );
		$this->assertNull( $event->payment );
		$this->assertNull( $event->subscriptionId );
	}
}
