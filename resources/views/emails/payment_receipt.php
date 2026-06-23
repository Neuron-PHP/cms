<?php
/**
 * Payer receipt email for a completed payment.
 *
 * Available variables (extracted by the Sender):
 *   $formLabel       string Human label for the form
 *   $purpose         string Payment purpose (e.g. donation, membership)
 *   $isRenewal       bool   Whether this is a recurring renewal charge
 *   $amountFormatted string Formatted amount (e.g. "$50.00")
 *   $frequencyLabel  string Human cadence label (e.g. "Monthly")
 *   $values          array  Submitted payer values keyed by field name
 *   $payment         array  The payment row
 */
$formLabel       = $formLabel ?? 'Payment';
$purpose         = $purpose ?? 'donation';
$isRenewal       = !empty( $isRenewal );
$amountFormatted = $amountFormatted ?? '';
$frequencyLabel  = $frequencyLabel ?? '';
$values          = $values ?? [];
$payment         = $payment ?? [];
$payerName       = $values['name'] ?? ( $payment['payer_name'] ?? '' );
$isDonation      = $purpose === 'donation';
$noun            = $isDonation ? 'donation' : 'payment';
$title           = $isDonation ? 'Thank you for your donation' : 'Your payment receipt';
$intro           = $isDonation
	? 'Thank you for your generous support. This message confirms your donation:'
	: 'Thank you. This message confirms your payment:';
?>
<!DOCTYPE html>
<html lang="en">
<head>
	<meta charset="UTF-8">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<title><?= htmlspecialchars( $title ) ?></title>
	<style>
		body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Arial, sans-serif; line-height: 1.6; color: #333; margin: 0; padding: 0; background: #f4f4f4; }
		.email-container { max-width: 640px; margin: 20px auto; background: #fff; border-radius: 8px; overflow: hidden; box-shadow: 0 2px 8px rgba(0,0,0,0.08); }
		.email-header { background: #1e7e34; color: #fff; padding: 24px 28px; }
		.email-header h1 { margin: 0; font-size: 20px; }
		.email-body { padding: 24px 28px; }
		.amount { font-size: 24px; font-weight: bold; color: #1e7e34; }
		.email-footer { padding: 16px 28px; color: #888; font-size: 12px; background: #f9fafb; border-top: 1px solid #e5e7eb; }
	</style>
</head>
<body>
	<div class="email-container">
		<div class="email-header">
			<h1><?= htmlspecialchars( $isDonation ? 'Thank You for Your Donation' : 'Payment Receipt' ) ?></h1>
		</div>
		<div class="email-body">
			<p>Dear <?= htmlspecialchars( $payerName !== '' ? (string) $payerName : 'Friend' ) ?>,</p>
			<p><?= htmlspecialchars( $intro ) ?></p>
			<p class="amount"><?= htmlspecialchars( $amountFormatted ) ?><?= $frequencyLabel !== '' ? ' <span style="font-size:14px;font-weight:normal;color:#555;">(' . htmlspecialchars( $frequencyLabel ) . ')</span>' : '' ?></p>
			<p>
				Reference:
				<?= htmlspecialchars( (string) ( $payment['payment_intent_id'] ?? $payment['subscription_id'] ?? $payment['session_id'] ?? '' ) ) ?>
			</p>
			<p>We are grateful for your <?= htmlspecialchars( $noun ) ?>.</p>
		</div>
		<div class="email-footer">
			Please keep this receipt for your records.
		</div>
	</div>
</body>
</html>
