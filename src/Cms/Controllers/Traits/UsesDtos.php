<?php

namespace Neuron\Cms\Controllers\Traits;

use Neuron\Cms\Services\Dto\DtoFactoryService;
use Neuron\Core\Exceptions\Validation;
use Neuron\Dto\Dto;
use Neuron\Mvc\Requests\Request;
use Neuron\Patterns\Registry;

/**
 * Trait for using DTOs in controllers
 *
 * Provides helper methods for creating, populating, and validating DTOs.
 *
 * @package Neuron\Cms\Controllers\Traits
 */
trait UsesDtos
{
	/**
	 * Get the DtoFactoryService instance
	 *
	 * Retrieves from Registry if available, otherwise creates a new instance.
	 *
	 * @return DtoFactoryService
	 */
	protected function getDtoFactory(): DtoFactoryService
	{
		$factory = Registry::getInstance()->get( 'DtoFactoryService' );

		if( !$factory )
		{
			$factory = new DtoFactoryService();
			Registry::getInstance()->set( 'DtoFactoryService', $factory );
		}

		return $factory;
	}

	/**
	 * Populate a DTO from request data
	 *
	 * Sets DTO properties from POST data. Silently ignores properties
	 * that don't exist in the DTO.
	 *
	 * @param Dto $dto DTO to populate
	 * @param Request $request Request containing form data
	 * @param array $fields Array of field names to populate (defaults to all POST data)
	 * @return Dto The populated DTO
	 */
	protected function populateDtoFromRequest( Dto $dto, Request $request, array $fields = [] ): Dto
	{
		// If no specific fields provided, use all POST data keys
		if( empty( $fields ) )
		{
			$fields = array_keys( $_POST );
		}

		foreach( $fields as $field )
		{
			$value = $request->post( $field );

			// Get the property from the DTO
			$property = $dto->getProperty( $field );

			// Skip if property doesn't exist
			if( !$property )
			{
				continue;
			}

			// Set the value
			try
			{
				// Use magic setter which handles validation per property
				$dto->$field = $value;
			}
			catch( Validation $e )
			{
				// Property-level validation errors are collected
				// They'll be returned when validate() is called on the DTO
			}
		}

		return $dto;
	}

	/**
	 * Validate a DTO and return errors
	 *
	 * @param Dto $dto DTO to validate
	 * @return array Array of validation error messages (empty if valid)
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
			$message = implode( ', ', $errors );
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
	 * @param array $fields Optional array of field names to populate
	 * @return Dto
	 * @throws \Exception if DTO cannot be created
	 */
	protected function createDtoFromRequest( string $name, Request $request, array $fields = [] ): Dto
	{
		$dto = $this->getDtoFactory()->create( $name );
		return $this->populateDtoFromRequest( $dto, $request, $fields );
	}
}
