<?php

namespace Neuron\Cms\Services\Dto;

use Neuron\Dto\Dto;
use Neuron\Dto\Factory;
use Exception;

/**
 * DTO Factory Service for CMS
 *
 * Centralized service for creating and caching DTOs from YAML definitions.
 *
 * @package Neuron\Cms\Services\Dto
 */
class DtoFactoryService
{
	private string $_dtoDirectory;
	private array $_dtoCache = [];

	/**
	 * Constructor
	 *
	 * @param string|null $dtoDirectory Path to DTO YAML files directory (defaults to cms/src/Cms/Dtos)
	 */
	public function __construct( ?string $dtoDirectory = null )
	{
		if( $dtoDirectory === null )
		{
			$dtoDirectory = __DIR__ . '/../../Dtos';
		}

		$this->_dtoDirectory = rtrim( $dtoDirectory, '/' );
	}

	/**
	 * Get a DTO instance by name
	 *
	 * @param string $name DTO name (e.g., 'RegisterUser', 'CreatePost')
	 * @return Dto
	 * @throws Exception if DTO file doesn't exist or cannot be loaded
	 */
	public function create( string $name ): Dto
	{
		// Check cache first
		if( isset( $this->_dtoCache[ $name ] ) )
		{
			return clone $this->_dtoCache[ $name ];
		}

		// Build file path
		$filePath = $this->_dtoDirectory . '/' . $name . 'Dto.yaml';

		// Check if file exists
		if( !file_exists( $filePath ) )
		{
			throw new Exception( "DTO definition file not found: {$filePath}" );
		}

		// Create DTO using Neuron\Dto\Factory
		$factory = new Factory( $filePath );
		$dto = $factory->create();

		// Cache for future use
		$this->_dtoCache[ $name ] = $dto;

		// Return a clone so callers can set values without affecting cache
		return clone $dto;
	}

	/**
	 * Create a RegisterUser DTO
	 *
	 * @return Dto
	 * @throws Exception
	 */
	public function createRegisterUser(): Dto
	{
		return $this->create( 'RegisterUser' );
	}

	/**
	 * Create a CreateUser DTO
	 *
	 * @return Dto
	 * @throws Exception
	 */
	public function createCreateUser(): Dto
	{
		return $this->create( 'CreateUser' );
	}

	/**
	 * Create an UpdateUser DTO
	 *
	 * @return Dto
	 * @throws Exception
	 */
	public function createUpdateUser(): Dto
	{
		return $this->create( 'UpdateUser' );
	}

	/**
	 * Create a CreateCategory DTO
	 *
	 * @return Dto
	 * @throws Exception
	 */
	public function createCreateCategory(): Dto
	{
		return $this->create( 'CreateCategory' );
	}

	/**
	 * Create an UpdateCategory DTO
	 *
	 * @return Dto
	 * @throws Exception
	 */
	public function createUpdateCategory(): Dto
	{
		return $this->create( 'UpdateCategory' );
	}

	/**
	 * Create a CreatePost DTO
	 *
	 * @return Dto
	 * @throws Exception
	 */
	public function createCreatePost(): Dto
	{
		return $this->create( 'CreatePost' );
	}

	/**
	 * Create an UpdatePost DTO
	 *
	 * @return Dto
	 * @throws Exception
	 */
	public function createUpdatePost(): Dto
	{
		return $this->create( 'UpdatePost' );
	}

	/**
	 * Clear the DTO cache
	 *
	 * @return void
	 */
	public function clearCache(): void
	{
		$this->_dtoCache = [];
	}

	/**
	 * Get the DTO directory path
	 *
	 * @return string
	 */
	public function getDtoDirectory(): string
	{
		return $this->_dtoDirectory;
	}
}
