<?php

namespace Neuron\Cms\Services\Payment;

/**
 * Gateway-agnostic snapshot of a hosted checkout session used to reconcile
 * a pending payment. Built from either the payments-package DTO or a raw
 * Stripe Checkout Session so CMS works with current and future packages.
 *
 * @package Neuron\Cms\Services\Payment
 */
final class RetrievedCheckout
{
	/**
	 * @param string $id
	 * @param string $status open|complete|expired
	 * @param string $paymentStatus paid|unpaid|no_payment_required
	 * @param string|null $paymentIntentId
	 * @param string|null $subscriptionId
	 * @param int|null $amountTotal
	 */
	public function __construct(
		public readonly string $id,
		public readonly string $status = '',
		public readonly string $paymentStatus = '',
		public readonly ?string $paymentIntentId = null,
		public readonly ?string $subscriptionId = null,
		public readonly ?int $amountTotal = null
	)
	{
	}

	/**
	 * @return bool
	 */
	public function isPaid(): bool
	{
		return $this->paymentStatus === 'paid'
			|| $this->paymentStatus === 'no_payment_required';
	}

	/**
	 * @return bool
	 */
	public function isExpired(): bool
	{
		return $this->status === 'expired';
	}

	/**
	 * Normalize a gateway DTO or Stripe object/array into this snapshot.
	 *
	 * @param object|array<string, mixed> $session
	 * @return self
	 */
	public static function from( object|array $session ): self
	{
		if( $session instanceof self )
		{
			return $session;
		}

		$get = static function( string $camel, string $snake ) use ( $session )
		{
			if( is_array( $session ) )
			{
				return $session[ $camel ] ?? $session[ $snake ] ?? null;
			}

			if( isset( $session->$camel ) )
			{
				return $session->$camel;
			}

			if( isset( $session->$snake ) )
			{
				return $session->$snake;
			}

			return null;
		};

		return new self(
			id:              (string) ( $get( 'id', 'id' ) ?? '' ),
			status:          (string) ( $get( 'status', 'status' ) ?? '' ),
			paymentStatus:   (string) ( $get( 'paymentStatus', 'payment_status' ) ?? '' ),
			paymentIntentId: self::idFrom( $get( 'paymentIntentId', 'payment_intent' ) ),
			subscriptionId:  self::idFrom( $get( 'subscriptionId', 'subscription' ) ),
			amountTotal:     $get( 'amountTotal', 'amount_total' ) === null
				? null
				: (int) $get( 'amountTotal', 'amount_total' )
		);
	}

	/**
	 * @param mixed $value
	 * @return string|null
	 */
	private static function idFrom( mixed $value ): ?string
	{
		if( is_string( $value ) && $value !== '' )
		{
			return $value;
		}

		if( is_object( $value ) )
		{
			$id = $value->id ?? null;

			return is_string( $id ) && $id !== '' ? $id : null;
		}

		if( is_array( $value ) )
		{
			$id = $value['id'] ?? null;

			return is_string( $id ) && $id !== '' ? $id : null;
		}

		return null;
	}
}
