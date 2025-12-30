<?php

namespace Neuron\Cms\Controllers\Traits;

use Neuron\Cms\Services\Dto\DtoFactoryService;
use Neuron\Core\Exceptions\Validation;
use Neuron\Dto\Dto;
use Neuron\Dto\Mapper\Request as RequestMapper;
use Neuron\Mvc\Requests\Request;

/**
 * Trait for using DTOs in controllers
 *
 * Provides helper methods for creating, populating, and validating DTOs
 * using proper input filtering through the RequestMapper.
 *
 * @package Neuron\Cms\Controllers\Traits
 */
trait UsesDtos
{
	/**
	 * Get the DtoFactoryService instance from the container
	 *
	 * @return DtoFactoryService
	 */
	protected function getDtoFactory(): DtoFactoryService
	{
		// Get from container (assumes trait is used in a controller with getApplication())
		return $this->getApplication()->getContainer()->get( DtoFactoryService::class );
	}

	/**
	 * Populate a DTO from request data using RequestMapper
	 *
	 * Maps filtered POST data to DTO properties using the RequestMapper
	 * which applies proper input sanitization via Post::filterScalar().
	 *
	 * @param Dto $dto DTO to populate
	 * @param Request $request Request containing form data (unused - kept for BC)
	 * @param array<int, string> $fields Array of field names to populate (defaults to all POST data)
	 * @return Dto The populated DTO
	 */
	protected function populateDtoFromRequest( Dto $dto, Request $request, array $fields = [] ): Dto
	{
		$mapper = new RequestMapper();

		// If specific fields provided, pass them to mapper
		// Otherwise mapper will use all POST keys
		if( !empty( $fields ) )
		{
			return $mapper->mapFiltered( $dto, $fields );
		}

		return $mapper->map( $dto );
	}

	/**
	 * Validate a DTO and return errors
	 *
	 * @param Dto $dto DTO to validate
	 * @return array<string, array<int, string>> Array of validation error messages (empty if valid)
	 */
	protected function validateDto( Dto $dto ): array
	{
		try
		{
			$dto->validate();
		}
		catch( Validation $e )
		{
			// Validation exception is thrown, but errors are also stored in DTO
		}

		return $dto->getErrors();
	}

	/**
	 * Validate a DTO and throw exception with formatted message if invalid
	 *
	 * @param Dto $dto DTO to validate
	 * @return void
	 * @throws \Exception if validation fails
	 */
	protected function validateDtoOrFail( Dto $dto ): void
	{
		$errors = $this->validateDto( $dto );

		if( !empty( $errors ) )
		{
			// Flatten multidimensional error array into single string
			$messages = [];
			foreach( $errors as $field => $fieldErrors )
			{
				foreach( $fieldErrors as $error )
				{
					$messages[] = $field . ': ' . $error;
				}
			}
			$message = implode( ', ', $messages );
			throw new \Exception( $message );
		}
	}

	/**
	 * Create and populate a DTO from request data
	 *
	 * Convenience method that creates a DTO by name and populates it from request.
	 *
	 * @param string $name DTO name (e.g., 'RegisterUser', 'CreatePost')
	 * @param Request $request Request containing form data
	 * @param array<int, string> $fields Optional array of field names to populate
	 * @return Dto
	 * @throws \Exception if DTO cannot be created
	 */
	protected function createDtoFromRequest( string $name, Request $request, array $fields = [] ): Dto
	{
		$dto = $this->getDtoFactory()->create( $name );
		return $this->populateDtoFromRequest( $dto, $request, $fields );
	}
}
