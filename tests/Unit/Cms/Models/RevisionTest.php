<?php

namespace Tests\Cms\Models;

use DateTimeImmutable;
use Neuron\Cms\Models\Revision;
use PHPUnit\Framework\TestCase;

class RevisionTest extends TestCase
{
	public function testCanCreateRevision(): void
	{
		$revision = new Revision();
		$this->assertInstanceOf( Revision::class, $revision );
		$this->assertNull( $revision->getId() );
		$this->assertInstanceOf( DateTimeImmutable::class, $revision->getCreatedAt() );
		$this->assertEquals( Revision::ACTION_UPDATED, $revision->getAction() );
	}

	public function testSettersAndGetters(): void
	{
		$revision = new Revision();
		$revision->setContentType( Revision::TYPE_PAGE )
			->setContentId( 42 )
			->setTitle( 'My Page' )
			->setStatus( 'published' )
			->setAction( Revision::ACTION_CREATED )
			->setEditedBy( 7 )
			->setEditedByName( 'alice' );

		$this->assertEquals( Revision::TYPE_PAGE, $revision->getContentType() );
		$this->assertEquals( 42, $revision->getContentId() );
		$this->assertEquals( 'My Page', $revision->getTitle() );
		$this->assertEquals( 'published', $revision->getStatus() );
		$this->assertEquals( Revision::ACTION_CREATED, $revision->getAction() );
		$this->assertEquals( 7, $revision->getEditedBy() );
		$this->assertEquals( 'alice', $revision->getEditedByName() );
	}

	public function testSnapshotArrayRoundTrip(): void
	{
		$revision = new Revision();
		$revision->setSnapshotArray( [ 'title' => 'Hello', 'content' => '{"blocks":[]}' ] );

		$data = $revision->getSnapshotData();
		$this->assertEquals( 'Hello', $data['title'] );
		$this->assertEquals( '{"blocks":[]}', $data['content'] );
	}

	public function testGetSnapshotDataReturnsEmptyArrayForInvalidJson(): void
	{
		$revision = new Revision();
		$revision->setSnapshot( 'not-json' );
		$this->assertSame( [], $revision->getSnapshotData() );
	}

	public function testEditorLabelPrefersName(): void
	{
		$revision = new Revision();
		$revision->setEditedBy( 5 )->setEditedByName( 'bob' );
		$this->assertEquals( 'bob', $revision->getEditorLabel() );
	}

	public function testEditorLabelFallsBackToUserId(): void
	{
		$revision = new Revision();
		$revision->setEditedBy( 5 );
		$this->assertEquals( 'User #5', $revision->getEditorLabel() );
	}

	public function testEditorLabelUnknownWhenNoUser(): void
	{
		$revision = new Revision();
		$this->assertEquals( 'Unknown', $revision->getEditorLabel() );
	}

	public function testFromArrayHydratesAllFields(): void
	{
		$revision = Revision::fromArray( [
			'id'             => 3,
			'content_type'   => Revision::TYPE_POST,
			'content_id'     => 10,
			'title'          => 'Post Title',
			'status'         => 'draft',
			'action'         => Revision::ACTION_RESTORED,
			'snapshot'       => '{"title":"Post Title"}',
			'edited_by'      => 2,
			'edited_by_name' => 'carol',
			'created_at'     => '2026-06-03 10:00:00',
		] );

		$this->assertEquals( 3, $revision->getId() );
		$this->assertEquals( Revision::TYPE_POST, $revision->getContentType() );
		$this->assertEquals( 10, $revision->getContentId() );
		$this->assertEquals( 'Post Title', $revision->getTitle() );
		$this->assertEquals( 'draft', $revision->getStatus() );
		$this->assertEquals( Revision::ACTION_RESTORED, $revision->getAction() );
		$this->assertEquals( 2, $revision->getEditedBy() );
		$this->assertEquals( 'carol', $revision->getEditedByName() );
		$this->assertEquals( 'Post Title', $revision->getSnapshotData()['title'] );
	}

	public function testToArrayOmitsIdWhenNull(): void
	{
		$revision = new Revision();
		$revision->setContentType( Revision::TYPE_PAGE )->setContentId( 1 );

		$data = $revision->toArray();
		$this->assertArrayNotHasKey( 'id', $data );
		$this->assertEquals( Revision::TYPE_PAGE, $data['content_type'] );
		$this->assertArrayHasKey( 'snapshot', $data );
		$this->assertArrayHasKey( 'edited_by', $data );
	}

	public function testToArrayIncludesIdWhenSet(): void
	{
		$revision = new Revision();
		$revision->setId( 9 )->setContentType( Revision::TYPE_PAGE )->setContentId( 1 );
		$this->assertEquals( 9, $revision->toArray()['id'] );
	}
}
