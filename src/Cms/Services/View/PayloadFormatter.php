<?php

namespace Neuron\Cms\Services\View;

/**
 * Turn a decoded JSON payload value into a display string.
 *
 * Payment and contact payloads can hold scalars, lists of scalars, or nested
 * structures ( store-order line items ). Views must not implode nested arrays
 * directly — that raises "Array to string conversion".
 *
 * @package Neuron\Cms\Services\View
 */
class PayloadFormatter
{
	/**
	 * Format a payload value for admin display.
	 *
	 * @param mixed $value
	 * @return string
	 */
	public static function format( mixed $value ): string
	{
		if( is_array( $value ) )
		{
			if( $value === [] )
			{
				return '';
			}

			if( self::isLineItem( $value ) )
			{
				return self::formatLineItem( $value );
			}

			$isList = array_is_list( $value );
			$parts  = [];

			foreach( $value as $key => $item )
			{
				$formatted = self::format( $item );

				if( $formatted === '' )
				{
					continue;
				}

				$parts[] = $isList ? $formatted : $key . ': ' . $formatted;
			}

			return implode( $isList ? "\n" : ', ', $parts );
		}

		if( is_bool( $value ) )
		{
			return $value ? 'Yes' : 'No';
		}

		if( $value === null )
		{
			return '';
		}

		return (string) $value;
	}

	/**
	 * Whether the array looks like a store-order line item.
	 *
	 * @param array<mixed> $value
	 * @return bool
	 */
	private static function isLineItem( array $value ): bool
	{
		return isset( $value['name'] )
			&& ( isset( $value['quantity'] ) || isset( $value['unit_amount_cents'] ) )
			&& !array_is_list( $value );
	}

	/**
	 * Human-readable line item, e.g. "T-Shirt (SKU) × 2 — $30.00".
	 *
	 * @param array<string, mixed> $item
	 * @return string
	 */
	private static function formatLineItem( array $item ): string
	{
		$name = (string) ( $item['name'] ?? '' );
		$sku  = trim( (string) ( $item['sku'] ?? '' ) );
		$qty  = max( 1, (int) ( $item['quantity'] ?? 1 ) );

		$label = $name . ( $sku !== '' ? ' (' . $sku . ')' : '' );

		if( $qty > 1 )
		{
			$label .= ' × ' . $qty;
		}

		if( isset( $item['unit_amount_cents'] ) )
		{
			$total = ( (int) $item['unit_amount_cents'] ) * $qty;
			$label .= ' — $' . number_format( $total / 100, 2 );
		}

		return $label;
	}
}
