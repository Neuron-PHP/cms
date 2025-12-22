<?php

namespace Tests\Unit\Cms\Repositories\Traits;

use Neuron\Cms\Repositories\Traits\ManagesTimestamps;
use Neuron\Cms\Exceptions\RepositoryException;
use PHPUnit\Framework\TestCase;
use DateTimeImmutable;

class ManagesTimestampsTest extends TestCase
{
	private $repository;

	protected function setUp(): void
	{
		// Create anonymous class that uses the trait
		$this->repository = new class {
			use ManagesTimestamps {
				ensureTimestamps as public;
				saveAndRefresh as public;
				createEntity as public;
			}
		};
	}

	public function test_ensure_timestamps_sets_created_at_when_null(): void
	{
		$entity = new class {
			private $createdAt = null;
			private $updatedAt = null;

			public function getCreatedAt() { return $this->createdAt; }
			public function setCreatedAt( $value ) { $this->createdAt = $value; }
			public function getUpdatedAt() { return $this->updatedAt; }
			public function setUpdatedAt( $value ) { $this->updatedAt = $value; }
		};

		$this->repository->ensureTimestamps( $entity );

		$this->assertInstanceOf( DateTimeImmutable::class, $entity->getCreatedAt() );
	}

	public function test_ensure_timestamps_sets_updated_at_when_null(): void
	{
		$entity = new class {
			private $createdAt = null;
			private $updatedAt = null;

			public function getCreatedAt() { return $this->createdAt; }
			public function setCreatedAt( $value ) { $this->createdAt = $value; }
			public function getUpdatedAt() { return $this->updatedAt; }
			public function setUpdatedAt( $value ) { $this->updatedAt = $value; }
		};

		$this->repository->ensureTimestamps( $entity );

		$this->assertInstanceOf( DateTimeImmutable::class, $entity->getUpdatedAt() );
	}

	public function test_ensure_timestamps_does_not_overwrite_existing_created_at(): void
	{
		$existingDate = new DateTimeImmutable( '2024-01-01 12:00:00' );

		$entity = new class( $existingDate ) {
			private $createdAt;
			private $updatedAt = null;

			public function __construct( $createdAt ) {
				$this->createdAt = $createdAt;
			}

			public function getCreatedAt() { return $this->createdAt; }
			public function setCreatedAt( $value ) { $this->createdAt = $value; }
			public function getUpdatedAt() { return $this->updatedAt; }
			public function setUpdatedAt( $value ) { $this->updatedAt = $value; }
		};

		$this->repository->ensureTimestamps( $entity );

		$this->assertSame( $existingDate, $entity->getCreatedAt() );
	}

	public function test_ensure_timestamps_handles_entity_without_timestamp_methods(): void
	{
		$entity = new class {
			// No timestamp methods
		};

		// Should not throw exception
		$this->repository->ensureTimestamps( $entity );

		$this->assertTrue( true ); // If we get here, test passed
	}

	public function test_save_and_refresh_calls_save_on_entity(): void
	{
		$entity = new class {
			public $saveCalled = false;
			private $id = 123;

			public function save() {
				$this->saveCalled = true;
			}

			public function getId() { return $this->id; }
		};

		$finder = fn( $id ) => $entity;

		$this->repository->saveAndRefresh( $entity, $finder, 'TestEntity' );

		$this->assertTrue( $entity->saveCalled );
	}

	public function test_save_and_refresh_calls_finder_with_entity_id(): void
	{
		$finderCalledWithId = null;

		$entity = new class {
			private $id = 456;

			public function save() {}
			public function getId() { return $this->id; }
		};

		$finder = function( $id ) use ( &$finderCalledWithId, $entity ) {
			$finderCalledWithId = $id;
			return $entity;
		};

		$this->repository->saveAndRefresh( $entity, $finder, 'TestEntity' );

		$this->assertEquals( 456, $finderCalledWithId );
	}

	public function test_save_and_refresh_returns_refreshed_entity(): void
	{
		$originalEntity = new class {
			private $id = 1;
			public function save() {}
			public function getId() { return $this->id; }
		};

		$refreshedEntity = new class {
			private $id = 1;
			public function getId() { return $this->id; }
		};

		$finder = fn( $id ) => $refreshedEntity;

		$result = $this->repository->saveAndRefresh( $originalEntity, $finder, 'TestEntity' );

		$this->assertSame( $refreshedEntity, $result );
	}

	public function test_save_and_refresh_throws_exception_when_id_is_null(): void
	{
		$entity = new class {
			public function save() {}
			public function getId() { return null; }
		};

		$finder = fn( $id ) => $entity;

		$this->expectException( RepositoryException::class );
		$this->expectExceptionMessage( 'Failed to save TestEntity' );
		$this->expectExceptionMessage( 'Entity ID is null' );

		$this->repository->saveAndRefresh( $entity, $finder, 'TestEntity' );
	}

	public function test_save_and_refresh_throws_exception_when_finder_returns_null(): void
	{
		$entity = new class {
			private $id = 123;
			public function save() {}
			public function getId() { return $this->id; }
		};

		$finder = fn( $id ) => null; // Simulates entity not found

		$this->expectException( RepositoryException::class );
		$this->expectExceptionMessage( 'Failed to retrieve TestEntity' );
		$this->expectExceptionMessage( '123' );

		$this->repository->saveAndRefresh( $entity, $finder, 'TestEntity' );
	}

	public function test_save_and_refresh_handles_entity_without_get_id_method(): void
	{
		$entity = new class {
			public function save() {}
			// No getId method
		};

		$finder = fn( $id ) => null;

		$this->expectException( RepositoryException::class );
		$this->expectExceptionMessage( 'Entity ID is null' );

		$this->repository->saveAndRefresh( $entity, $finder, 'TestEntity' );
	}

	public function test_create_entity_sets_timestamps_and_saves(): void
	{
		$entity = new class {
			public $saveCalled = false;
			private $id = 789;
			private $createdAt = null;
			private $updatedAt = null;

			public function save() { $this->saveCalled = true; }
			public function getId() { return $this->id; }
			public function getCreatedAt() { return $this->createdAt; }
			public function setCreatedAt( $value ) { $this->createdAt = $value; }
			public function getUpdatedAt() { return $this->updatedAt; }
			public function setUpdatedAt( $value ) { $this->updatedAt = $value; }
		};

		$finder = fn( $id ) => $entity;

		$result = $this->repository->createEntity( $entity, $finder, 'TestEntity' );

		$this->assertTrue( $entity->saveCalled );
		$this->assertInstanceOf( DateTimeImmutable::class, $entity->getCreatedAt() );
		$this->assertInstanceOf( DateTimeImmutable::class, $entity->getUpdatedAt() );
		$this->assertSame( $entity, $result );
	}

	public function test_create_entity_returns_refreshed_entity(): void
	{
		$originalEntity = new class {
			private $id = 123;
			private $createdAt = null;
			private $updatedAt = null;

			public function save() {}
			public function getId() { return $this->id; }
			public function getCreatedAt() { return $this->createdAt; }
			public function setCreatedAt( $value ) { $this->createdAt = $value; }
			public function getUpdatedAt() { return $this->updatedAt; }
			public function setUpdatedAt( $value ) { $this->updatedAt = $value; }
		};

		$refreshedEntity = new class {
			private $id = 123;
			public function getId() { return $this->id; }
		};

		$finder = fn( $id ) => $refreshedEntity;

		$result = $this->repository->createEntity( $originalEntity, $finder, 'TestEntity' );

		$this->assertSame( $refreshedEntity, $result );
	}

	public function test_create_entity_throws_exception_when_id_is_null(): void
	{
		$entity = new class {
			private $createdAt = null;
			private $updatedAt = null;

			public function save() {}
			public function getId() { return null; }
			public function getCreatedAt() { return $this->createdAt; }
			public function setCreatedAt( $value ) { $this->createdAt = $value; }
			public function getUpdatedAt() { return $this->updatedAt; }
			public function setUpdatedAt( $value ) { $this->updatedAt = $value; }
		};

		$finder = fn( $id ) => $entity;

		$this->expectException( RepositoryException::class );
		$this->expectExceptionMessage( 'Failed to save TestEntity' );
		$this->expectExceptionMessage( 'Entity ID is null' );

		$this->repository->createEntity( $entity, $finder, 'TestEntity' );
	}

	public function test_create_entity_throws_exception_when_finder_returns_null(): void
	{
		$entity = new class {
			private $id = 456;
			private $createdAt = null;
			private $updatedAt = null;

			public function save() {}
			public function getId() { return $this->id; }
			public function getCreatedAt() { return $this->createdAt; }
			public function setCreatedAt( $value ) { $this->createdAt = $value; }
			public function getUpdatedAt() { return $this->updatedAt; }
			public function setUpdatedAt( $value ) { $this->updatedAt = $value; }
		};

		$finder = fn( $id ) => null; // Simulates entity not found

		$this->expectException( RepositoryException::class );
		$this->expectExceptionMessage( 'Failed to retrieve TestEntity' );
		$this->expectExceptionMessage( '456' );

		$this->repository->createEntity( $entity, $finder, 'TestEntity' );
	}
}
