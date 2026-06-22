<?php
/**
 * Donor receipt email for a completed donation.
 *
 * Available variables (extracted by the Sender):
 *   $formLabel       string Human label for the form
 *   $amountFormatted string Formatted amount (e.g. "$50.00")
 *   $frequencyLabel  string Human cadence label (e.g. "Monthly")
 *   $values          array  Submitted donor values keyed by field name
 *   $donation        array  The donation row
 */
$formLabel       = $formLabel ?? 'Donation';
$amountFormatted = $amountFormatted ?? '';
$frequencyLabel  = $frequencyLabel ?? '';
$values          = $values ?? [];
$donation        = $donation ?? [];
$donorName       = $values['name'] ?? ( $donation['donor_name'] ?? '' );
?>
<!DOCTYPE html>
<html lang="en">
<head>
	<meta charset="UTF-8">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<title>Thank you for your donation</title>
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
			<h1>Thank You for Your Donation</h1>
		</div>
		<div class="email-body">
			<p>Dear <?= htmlspecialchars( $donorName !== '' ? (string) $donorName : 'Friend' ) ?>,</p>
			<p>Thank you for your generous support. This message confirms your donation:</p>
			<p class="amount"><?= htmlspecialchars( $amountFormatted ) ?><?= $frequencyLabel !== '' ? ' <span style="font-size:14px;font-weight:normal;color:#555;">(' . htmlspecialchars( $frequencyLabel ) . ')</span>' : '' ?></p>
			<p>
				Reference:
				<?= htmlspecialchars( (string) ( $donation['payment_intent_id'] ?? $donation['subscription_id'] ?? $donation['session_id'] ?? '' ) ) ?>
			</p>
			<p>We are grateful for your contribution.</p>
		</div>
		<div class="email-footer">
			Please keep this receipt for your records.
		</div>
	</div>
</body>
</html>
