<?php

namespace Neuron\Cms\Events;

use Neuron\Events\IEvent;

/**
 * Event fired when a payment fails.
 *
 * Emitted after a renewal invoice payment fails and the subscription
 * is marked past_due. The origin payment is included when it can be
 * resolved from the subscription id.
 *
 * Use cases:
 * - Notify staff of failed renewals
 * - Flag memberships as past due
 * - Trigger dunning or retry workflows
 *
 * @package Neuron\Cms\Events
 */
class PaymentFailedEvent implements IEvent
{
	/**
	 * @param array<string, mixed>|null $payment Origin payment when available
	 * @param string|null $subscriptionId Gateway subscription id
	 */
	public function __construct(
		public readonly ?array $payment,
		public readonly ?string $subscriptionId
	)
	{
	}

	public function getName(): string
	{
		return 'payment.failed';
	}
}
