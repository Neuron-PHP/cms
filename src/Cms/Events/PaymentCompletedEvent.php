<?php

namespace Neuron\Cms\Events;

use Neuron\Events\IEvent;

/**
 * Event fired when a payment is completed.
 *
 * Emitted after the webhook persists a successful one-time checkout
 * or a recurring renewal. Listeners see the saved payment row before
 * default receipt and notification emails are sent.
 *
 * Use cases:
 * - Activate memberships or site-specific entitlements
 * - Send extra notifications beyond the default CMS receipts
 * - Record analytics or accounting entries
 * - Trigger fulfillment workflows
 *
 * @package Neuron\Cms\Events
 */
class PaymentCompletedEvent implements IEvent
{
	/**
	 * @param array<string, mixed> $payment Completed payment row
	 * @param bool $isRenewal Whether this is a recurring renewal charge
	 */
	public function __construct(
		public readonly array $payment,
		public readonly bool $isRenewal = false
	)
	{
	}

	public function getName(): string
	{
		return 'payment.completed';
	}
}
