<?php
/**
 * Internal monthly payment report email.
 *
 * Available variables (extracted by the Sender):
 *   $periodLabel string Period heading (e.g. "July 2026")
 *   $count       int    Number of completed payments
 *   $totals      array  Totals grouped by currency
 *   $byPurpose   array  Counts/totals grouped by purpose
 *   $byForm      array  Counts/totals grouped by form key
 *   $payments    array  Completed payment rows with display fields
 */
$periodLabel = $periodLabel ?? '';
$count       = (int) ( $count ?? 0 );
$totals      = $totals ?? [];
$byPurpose   = $byPurpose ?? [];
$byForm      = $byForm ?? [];
$payments    = $payments ?? [];

$formatGroupTotals = static function( array $groupTotals ): string {
	$parts = [];

	foreach( $groupTotals as $total )
	{
		$parts[] = (string) ( $total['formatted'] ?? '' );
	}

	return implode( ', ', array_filter( $parts ) );
};
?>
<!DOCTYPE html>
<html lang="en">
<head>
	<meta charset="UTF-8">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<title><?= htmlspecialchars( $periodLabel !== '' ? $periodLabel : 'Payment Report' ) ?></title>
	<style>
		body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Arial, sans-serif; line-height: 1.6; color: #333; margin: 0; padding: 0; background: #f4f4f4; }
		.email-container { max-width: 720px; margin: 20px auto; background: #fff; border-radius: 8px; overflow: hidden; box-shadow: 0 2px 8px rgba(0,0,0,0.08); }
		.email-header { background: #1e7e34; color: #fff; padding: 24px 28px; }
		.email-header h1 { margin: 0; font-size: 20px; }
		.email-body { padding: 24px 28px; }
		.amount { font-size: 24px; font-weight: bold; color: #1e7e34; margin: 0 0 8px; }
		.meta { color: #555; margin: 0 0 20px; }
		table.fields { width: 100%; border-collapse: collapse; margin: 0 0 20px; }
		table.fields th { text-align: left; vertical-align: top; padding: 10px 12px; background: #f7f9fb; border-bottom: 1px solid #e5e7eb; color: #555; }
		table.fields td { padding: 10px 12px; border-bottom: 1px solid #e5e7eb; }
		table.payments { width: 100%; border-collapse: collapse; font-size: 13px; }
		table.payments th { text-align: left; padding: 8px 10px; background: #f7f9fb; border-bottom: 1px solid #e5e7eb; color: #555; }
		table.payments td { padding: 8px 10px; border-bottom: 1px solid #e5e7eb; vertical-align: top; }
		.empty { color: #555; }
		.email-footer { padding: 16px 28px; color: #888; font-size: 12px; background: #f9fafb; border-top: 1px solid #e5e7eb; }
	</style>
</head>
<body>
	<div class="email-container">
		<div class="email-header">
			<h1>Payment Report<?= $periodLabel !== '' ? ': ' . htmlspecialchars( $periodLabel ) : '' ?></h1>
		</div>
		<div class="email-body">
			<?php if( $totals === [] ): ?>
				<p class="amount">$0.00</p>
			<?php else: ?>
				<?php foreach( $totals as $total ): ?>
					<p class="amount"><?= htmlspecialchars( (string) ( $total['formatted'] ?? '' ) ) ?></p>
				<?php endforeach; ?>
			<?php endif; ?>
			<p class="meta"><?= (int) $count ?> completed payment<?= $count === 1 ? '' : 's' ?></p>

			<?php if( $byPurpose !== [] || $byForm !== [] ): ?>
				<table class="fields">
					<tbody>
					<?php foreach( $byPurpose as $group ): ?>
						<tr>
							<th>Purpose: <?= htmlspecialchars( (string) ( $group['key'] ?? '' ) ) ?></th>
							<td><?= (int) ( $group['count'] ?? 0 ) ?> &mdash; <?= htmlspecialchars( $formatGroupTotals( $group['totals'] ?? [] ) ) ?></td>
						</tr>
					<?php endforeach; ?>
					<?php foreach( $byForm as $group ): ?>
						<tr>
							<th>Form: <?= htmlspecialchars( (string) ( $group['key'] ?? '' ) ) ?></th>
							<td><?= (int) ( $group['count'] ?? 0 ) ?> &mdash; <?= htmlspecialchars( $formatGroupTotals( $group['totals'] ?? [] ) ) ?></td>
						</tr>
					<?php endforeach; ?>
					</tbody>
				</table>
			<?php endif; ?>

			<?php if( $payments === [] ): ?>
				<p class="empty">No completed payments.</p>
			<?php else: ?>
				<table class="payments">
					<thead>
						<tr>
							<th>Date</th>
							<th>Payer</th>
							<th>Amount</th>
							<th>Purpose</th>
							<th>Form</th>
							<th>Frequency</th>
						</tr>
					</thead>
					<tbody>
					<?php foreach( $payments as $payment ): ?>
						<tr>
							<td><?= htmlspecialchars( (string) ( $payment['completedLabel'] ?? '' ) ) ?></td>
							<td>
								<?= htmlspecialchars( (string) ( $payment['payer_name'] ?? '' ) ) ?>
								<?php if( !empty( $payment['payer_email'] ) ): ?>
									<br><span style="color:#666;"><?= htmlspecialchars( (string) $payment['payer_email'] ) ?></span>
								<?php endif; ?>
							</td>
							<td><?= htmlspecialchars( (string) ( $payment['amountFormatted'] ?? '' ) ) ?></td>
							<td><?= htmlspecialchars( (string) ( $payment['purpose'] ?? '' ) ) ?></td>
							<td><?= htmlspecialchars( (string) ( $payment['form_key'] ?? '' ) ) ?></td>
							<td><?= htmlspecialchars( (string) ( $payment['frequencyLabel'] ?? '' ) ) ?></td>
						</tr>
					<?php endforeach; ?>
					</tbody>
				</table>
			<?php endif; ?>
		</div>
		<div class="email-footer">
			This report was generated from completed payments for <?= htmlspecialchars( $periodLabel !== '' ? $periodLabel : 'the selected period' ) ?>.
		</div>
	</div>
</body>
</html>
