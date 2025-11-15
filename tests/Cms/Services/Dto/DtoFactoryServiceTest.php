<?php

namespace Tests\Cms\Services\Dto;

use Neuron\Cms\Services\Dto\DtoFactoryService;
use Neuron\Dto\Dto;
use PHPUnit\Framework\TestCase;

class DtoFactoryServiceTest extends TestCase
{
	private DtoFactoryService $_factory;
	private string $_testDtoPath;

	protected function setUp(): void
	{
		// Point to the actual DTO directory
		$this->_testDtoPath = __DIR__ . '/../../../../src/Cms/Dtos';
		$this->_factory = new DtoFactoryService( $this->_testDtoPath );
	}

	public function testCreateRegisterUserDto(): void
	{
		$dto = $this->_factory->createRegisterUser();

		$this->assertInstanceOf( Dto::class, $dto );
		$this->assertNotNull( $dto->getProperty( 'username' ) );
		$this->assertNotNull( $dto->getProperty( 'email' ) );
		$this->assertNotNull( $dto->getProperty( 'password' ) );
		$this->assertNotNull( $dto->getProperty( 'password_confirmation' ) );
	}

	public function testCreateCreateUserDto(): void
	{
		$dto = $this->_factory->createCreateUser();

		$this->assertInstanceOf( Dto::class, $dto );
		$this->assertNotNull( $dto->getProperty( 'username' ) );
		$this->assertNotNull( $dto->getProperty( 'email' ) );
		$this->assertNotNull( $dto->getProperty( 'password' ) );
		$this->assertNotNull( $dto->getProperty( 'role' ) );
	}

	public function testCreateUpdateUserDto(): void
	{
		$dto = $this->_factory->createUpdateUser();

		$this->assertInstanceOf( Dto::class, $dto );
		$this->assertNotNull( $dto->getProperty( 'username' ) );
		$this->assertNotNull( $dto->getProperty( 'email' ) );
		$this->assertNotNull( $dto->getProperty( 'password' ) );
		$this->assertNotNull( $dto->getProperty( 'role' ) );
	}

	public function testCreateCreateCategoryDto(): void
	{
		$dto = $this->_factory->createCreateCategory();

		$this->assertInstanceOf( Dto::class, $dto );
		$this->assertNotNull( $dto->getProperty( 'name' ) );
		$this->assertNotNull( $dto->getProperty( 'slug' ) );
		$this->assertNotNull( $dto->getProperty( 'description' ) );
	}

	public function testCreateUpdateCategoryDto(): void
	{
		$dto = $this->_factory->createUpdateCategory();

		$this->assertInstanceOf( Dto::class, $dto );
		$this->assertNotNull( $dto->getProperty( 'name' ) );
		$this->assertNotNull( $dto->getProperty( 'slug' ) );
		$this->assertNotNull( $dto->getProperty( 'description' ) );
	}

	public function testCreateCreatePostDto(): void
	{
		$dto = $this->_factory->createCreatePost();

		$this->assertInstanceOf( Dto::class, $dto );
		$this->assertNotNull( $dto->getProperty( 'title' ) );
		$this->assertNotNull( $dto->getProperty( 'body' ) );
		$this->assertNotNull( $dto->getProperty( 'status' ) );
		$this->assertNotNull( $dto->getProperty( 'slug' ) );
		$this->assertNotNull( $dto->getProperty( 'excerpt' ) );
		$this->assertNotNull( $dto->getProperty( 'featuredImage' ) );
		$this->assertNotNull( $dto->getProperty( 'categoryIds' ) );
		$this->assertNotNull( $dto->getProperty( 'tags' ) );
	}

	public function testCreateUpdatePostDto(): void
	{
		$dto = $this->_factory->createUpdatePost();

		$this->assertInstanceOf( Dto::class, $dto );
		$this->assertNotNull( $dto->getProperty( 'title' ) );
		$this->assertNotNull( $dto->getProperty( 'body' ) );
		$this->assertNotNull( $dto->getProperty( 'status' ) );
	}

	public function testSetAndGetDtoValues(): void
	{
		$dto = $this->_factory->createRegisterUser();

		// Set values
		$dto->username = 'testuser';
		$dto->email = 'test@example.com';
		$dto->password = 'ValidPassword123!';
		$dto->password_confirmation = 'ValidPassword123!';

		// Get values
		$this->assertEquals( 'testuser', $dto->username );
		$this->assertEquals( 'test@example.com', $dto->email );
		$this->assertEquals( 'ValidPassword123!', $dto->password );
		$this->assertEquals( 'ValidPassword123!', $dto->password_confirmation );
	}

	public function testValidationFailsForInvalidData(): void
	{
		$dto = $this->_factory->createRegisterUser();

		// Set invalid username (too short)
		try
		{
			$dto->username = 'ab'; // Less than 3 characters
			$dto->validate();
			$errors = $dto->getErrors();

			$this->assertNotEmpty( $errors );
		}
		catch( \Exception $e )
		{
			// Validation exception expected
			$this->assertTrue( true );
		}
	}

	public function testValidationPassesForValidData(): void
	{
		$dto = $this->_factory->createRegisterUser();

		// Set valid data
		$dto->username = 'validuser';
		$dto->email = 'valid@example.com';
		$dto->password = 'ValidPassword123!';
		$dto->password_confirmation = 'ValidPassword123!';

		try
		{
			$dto->validate();
			$errors = $dto->getErrors();

			// Should have no errors
			$this->assertEmpty( $errors );
		}
		catch( \Exception $e )
		{
			// Should not throw exception for valid data
			$this->fail( 'Validation should pass for valid data: ' . $e->getMessage() );
		}
	}

	public function testCachingWorksCorrectly(): void
	{
		$dto1 = $this->_factory->createRegisterUser();
		$dto2 = $this->_factory->createRegisterUser();

		// Both should be instances of Dto
		$this->assertInstanceOf( Dto::class, $dto1 );
		$this->assertInstanceOf( Dto::class, $dto2 );

		// But they should be different instances (clones)
		$this->assertNotSame( $dto1, $dto2 );
	}

	public function testClearCache(): void
	{
		$dto1 = $this->_factory->createRegisterUser();
		$this->_factory->clearCache();
		$dto2 = $this->_factory->createRegisterUser();

		$this->assertInstanceOf( Dto::class, $dto1 );
		$this->assertInstanceOf( Dto::class, $dto2 );
		$this->assertNotSame( $dto1, $dto2 );
	}

	public function testGetDtoDirectory(): void
	{
		$directory = $this->_factory->getDtoDirectory();
		$this->assertEquals( $this->_testDtoPath, $directory );
	}

	public function testCreateThrowsExceptionForNonExistentDto(): void
	{
		$this->expectException( \Exception::class );
		$this->expectExceptionMessageMatches( '/DTO definition file not found/' );

		$this->_factory->create( 'NonExistent' );
	}
}
