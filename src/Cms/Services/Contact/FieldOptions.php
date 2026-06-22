<?php

namespace Neuron\Cms\Services\Contact;

/**
 * Normalizes the option sets for choice-style contact fields (select,
 * checkboxes, multiselect).
 *
 * Options may be declared flat or grouped, and each option may be a plain
 * string (value === label) or a { value, label } pair:
 *
 *   options:
 *     - "Sarasota"
 *     - { value: south, label: "South County" }
 *
 *   groups:
 *     - label: "Teen Court Sessions: Sarasota"
 *       options:
 *         - { value: sarasota_judge, label: "Judge" }
 *
 * Centralizing this here keeps the widget (rendering), validator (allowed
 * values) and views (value -> label display) in agreement.
 *
 * @package Neuron\Cms\Services\Contact
 */
class FieldOptions
{
	/**
	 * Normalize a field's options into a list of groups.
	 *
	 * A field without groups is returned as a single group with an empty
	 * label, so callers can iterate uniformly.
	 *
	 * @param array $field Field definition
	 * @return array<int, array{label: string, options: array<int, array{value: string, label: string}>}>
	 */
	public static function groups( array $field ): array
	{
		if( !empty( $field['groups'] ) && is_array( $field['groups'] ) )
		{
			$groups = [];

			foreach( $field['groups'] as $group )
			{
				if( !is_array( $group ) )
				{
					continue;
				}

				$groups[] = [
					'label'   => (string) ( $group['label'] ?? '' ),
					'options' => self::normalizeOptions( $group['options'] ?? [] )
				];
			}

			return $groups;
		}

		return [
			[
				'label'   => '',
				'options' => self::normalizeOptions( $field['options'] ?? [] )
			]
		];
	}

	/**
	 * Flatten a field's options to the list of allowed values.
	 *
	 * @param array $field Field definition
	 * @return array<int, string>
	 */
	public static function allowedValues( array $field ): array
	{
		$values = [];

		foreach( self::groups( $field ) as $group )
		{
			foreach( $group['options'] as $option )
			{
				$values[] = $option['value'];
			}
		}

		return $values;
	}

	/**
	 * Map a list of selected values to human-readable labels, prefixing the
	 * group label when present so options reused across groups stay distinct.
	 *
	 * @param array $field Field definition
	 * @param array $selected Selected values
	 * @return array<int, string>
	 */
	public static function labelsFor( array $field, array $selected ): array
	{
		$map = [];

		foreach( self::groups( $field ) as $group )
		{
			$prefix = $group['label'] !== '' ? $group['label'] . ' - ' : '';

			foreach( $group['options'] as $option )
			{
				$map[ $option['value'] ] = $prefix . $option['label'];
			}
		}

		$labels = [];

		foreach( $selected as $value )
		{
			$value = is_scalar( $value ) ? (string) $value : '';

			if( $value === '' )
			{
				continue;
			}

			$labels[] = $map[ $value ] ?? $value;
		}

		return $labels;
	}

	/**
	 * Normalize a raw option list into { value, label } pairs.
	 *
	 * @param mixed $options
	 * @return array<int, array{value: string, label: string}>
	 */
	private static function normalizeOptions( mixed $options ): array
	{
		if( !is_array( $options ) )
		{
			return [];
		}

		$normalized = [];

		foreach( $options as $option )
		{
			if( is_array( $option ) )
			{
				$value = $option['value'] ?? ( $option['label'] ?? null );
				$label = $option['label'] ?? $value;
			}
			else
			{
				$value = $option;
				$label = $option;
			}

			if( $value === null || $value === '' )
			{
				continue;
			}

			$normalized[] = [ 'value' => (string) $value, 'label' => (string) $label ];
		}

		return $normalized;
	}
}
