<?php
/**
 * Internal notification email: a donation completed.
 *
 * Available variables (extracted by the Sender):
 *   $fields          array  Donor field definitions
 *   $values          array  Submitted donor values keyed by field name
 *   $formLabel       string Human label for the form
 *   $amountFormatted string Formatted amount (e.g. "$50.00")
 *   $frequencyLabel  string Human cadence label (e.g. "Monthly")
 *   $donation        array  The donation row
 */
$fields          = $fields ?? [];
$values          = $values ?? [];
$formLabel       = $formLabel ?? 'Donation';
$amountFormatted = $amountFormatted ?? '';
$frequencyLabel  = $frequencyLabel ?? '';
$donation        = $donation ?? [];
?>
<!DOCTYPE html>
<html lang="en">
<head>
	<meta charset="UTF-8">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<title><?= htmlspecialchars( $formLabel ) ?></title>
	<style>
		body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Arial, sans-serif; line-height: 1.6; color: #333; margin: 0; padding: 0; background: #f4f4f4; }
		.email-container { max-width: 640px; margin: 20px auto; background: #fff; border-radius: 8px; overflow: hidden; box-shadow: 0 2px 8px rgba(0,0,0,0.08); }
		.email-header { background: #1e7e34; color: #fff; padding: 24px 28px; }
		.email-header h1 { margin: 0; font-size: 20px; }
		.email-body { padding: 24px 28px; }
		.amount { font-size: 24px; font-weight: bold; color: #1e7e34; margin: 0 0 16px; }
		table.fields { width: 100%; border-collapse: collapse; }
		table.fields th { text-align: left; vertical-align: top; padding: 10px 12px; width: 30%; background: #f7f9fb; border-bottom: 1px solid #e5e7eb; color: #555; }
		table.fields td { padding: 10px 12px; border-bottom: 1px solid #e5e7eb; white-space: pre-wrap; }
		.email-footer { padding: 16px 28px; color: #888; font-size: 12px; background: #f9fafb; border-top: 1px solid #e5e7eb; }
	</style>
</head>
<body>
	<div class="email-container">
		<div class="email-header">
			<h1>New Donation: <?= htmlspecialchars( $formLabel ) ?></h1>
		</div>
		<div class="email-body">
			<p class="amount"><?= htmlspecialchars( $amountFormatted ) ?><?= $frequencyLabel !== '' ? ' <span style="font-size:14px;font-weight:normal;color:#555;">(' . htmlspecialchars( $frequencyLabel ) . ')</span>' : '' ?></p>
			<table class="fields">
				<tbody>
				<?php foreach( $fields as $field ): ?>
					<?php
					$name = $field['name'] ?? null;
					if( $name === null )
					{
						continue;
					}
					$label = $field['label'] ?? $name;
					$value = $values[ $name ] ?? '';
					if( is_array( $value ) )
					{
						$value = implode( "\n", array_map( 'strval', $value ) );
					}
					elseif( !is_scalar( $value ) )
					{
						$value = json_encode( $value );
					}
					?>
					<tr>
						<th><?= htmlspecialchars( (string) $label ) ?></th>
						<td><?= nl2br( htmlspecialchars( (string) $value ) ) ?></td>
					</tr>
				<?php endforeach; ?>
					<tr>
						<th>Reference</th>
						<td><?= htmlspecialchars( (string) ( $donation['payment_intent_id'] ?? $donation['subscription_id'] ?? $donation['session_id'] ?? '' ) ) ?></td>
					</tr>
				</tbody>
			</table>
		</div>
		<div class="email-footer">
			This notification was generated when the donation payment completed.
		</div>
	</div>
</body>
</html>
