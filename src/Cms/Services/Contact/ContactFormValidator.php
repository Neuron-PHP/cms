<?php

namespace Neuron\Cms\Services\Contact;

use Neuron\Validation\IsEmail;
use Neuron\Validation\IsInSet;
use Neuron\Validation\IsRegExPattern;
use Neuron\Validation\IsStringLength;

/**
 * Validates contact form submissions against a form's configured field
 * definitions.
 *
 * Validation rules are built dynamically from each field's config (type,
 * required, options, rules) using the Neuron\Validation validators, so any
 * configurable field set is supported without per-form code.
 *
 * @package Neuron\Cms\Services\Contact
 */
class ContactFormValidator
{
	/**
	 * Validate submitted values against field definitions.
	 *
	 * @param array $fieldDefs List of field definition arrays from config
	 * @param array $values Submitted values keyed by field name
	 * @return array<string, string> Map of field name => error message (empty when valid)
	 */
	public function validate( array $fieldDefs, array $values ): array
	{
		$errors = [];

		foreach( $fieldDefs as $field )
		{
			$name = $field['name'] ?? null;

			if( $name === null )
			{
				continue;
			}

			$label    = $field['label'] ?? $name;
			$required = (bool) ( $field['required'] ?? false );
			$raw      = $values[ $name ] ?? null;
			$value    = is_string( $raw ) ? trim( $raw ) : $raw;
			$isEmpty  = ( $value === null || $value === '' || $value === [] );

			if( $isEmpty )
			{
				if( $required )
				{
					$errors[ $name ] = "{$label} is required.";
				}

				// Skip remaining rules for empty optional fields.
				continue;
			}

			$error = $this->validateField( $field, $label, (string) $value );

			if( $error !== null )
			{
				$errors[ $name ] = $error;
			}
		}

		return $errors;
	}

	/**
	 * Validate a single non-empty field value.
	 *
	 * @param array $field Field definition
	 * @param string $label Display label
	 * @param string $value Submitted value
	 * @return string|null Error message, or null when valid
	 */
	private function validateField( array $field, string $label, string $value ): ?string
	{
		$type  = $field['type'] ?? 'text';
		$rules = $field['rules'] ?? [];

		if( $type === 'email' && !new IsEmail()->isValid( $value ) )
		{
			return "{$label} must be a valid email address.";
		}

		if( $type === 'tel' && !$this->isValidPhone( $value ) )
		{
			return "{$label} must be a valid phone number.";
		}

		if( $type === 'select' )
		{
			$options = $field['options'] ?? [];

			if( !empty( $options ) && !new IsInSet( $options )->isValid( $value ) )
			{
				return "{$label} is not a valid selection.";
			}
		}

		if( isset( $rules['length'] ) )
		{
			$min = (int) ( $rules['length']['min'] ?? 0 );
			$max = (int) ( $rules['length']['max'] ?? PHP_INT_MAX );

			if( !new IsStringLength( $min, $max )->isValid( $value ) )
			{
				return "{$label} must be between {$min} and {$max} characters.";
			}
		}

		if( isset( $rules['pattern'] ) && !new IsRegExPattern( $rules['pattern'] )->isValid( $value ) )
		{
			return "{$label} is not in the expected format.";
		}

		return null;
	}

	/**
	 * Lenient phone validation suitable for a public contact form.
	 *
	 * Accepts common formats (parentheses, spaces, dashes, dots, optional
	 * leading +/country code) by checking the digit count rather than enforcing
	 * one rigid layout.
	 *
	 * @param string $value
	 * @return bool
	 */
	private function isValidPhone( string $value ): bool
	{
		$digits = preg_replace( '/\D+/', '', $value );
		$length = strlen( (string) $digits );

		return $length >= 7 && $length <= 15;
	}
}
