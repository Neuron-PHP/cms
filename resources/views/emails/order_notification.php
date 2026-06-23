<?php
/**
 * Internal notification email for a newly placed store order.
 *
 * Available variables (extracted by the Sender):
 *   $orderId        int|string Order ( payment ) id
 *   $payerName      string     Buyer name
 *   $payerEmail     string     Buyer email
 *   $items          array      [ name, sku, quantity, unitFormatted, totalFormatted ]
 *   $totalFormatted string     Formatted order total
 *   $order          array      The payment row
 */
$orderId        = $orderId ?? '';
$payerName      = $payerName ?? '';
$payerEmail     = $payerEmail ?? '';
$items          = $items ?? [];
$totalFormatted = $totalFormatted ?? '';
$order          = $order ?? [];
?>
<!DOCTYPE html>
<html lang="en">
<head>
	<meta charset="UTF-8">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<title>New order</title>
	<style>
		body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Arial, sans-serif; line-height: 1.6; color: #333; margin: 0; padding: 0; background: #f4f4f4; }
		.email-container { max-width: 640px; margin: 20px auto; background: #fff; border-radius: 8px; overflow: hidden; box-shadow: 0 2px 8px rgba(0,0,0,0.08); }
		.email-header { background: #0d6efd; color: #fff; padding: 24px 28px; }
		.email-header h1 { margin: 0; font-size: 20px; }
		.email-body { padding: 24px 28px; }
		table { width: 100%; border-collapse: collapse; margin: 16px 0; }
		th, td { text-align: left; padding: 8px 0; border-bottom: 1px solid #eee; }
		td.amount, th.amount { text-align: right; }
		.total { font-size: 18px; font-weight: bold; }
		.email-footer { padding: 16px 28px; color: #888; font-size: 12px; background: #f9fafb; border-top: 1px solid #e5e7eb; }
	</style>
</head>
<body>
	<div class="email-container">
		<div class="email-header">
			<h1>New Order #<?= htmlspecialchars( (string) $orderId ) ?></h1>
		</div>
		<div class="email-body">
			<p><strong>Customer:</strong> <?= htmlspecialchars( $payerName ) ?> &lt;<?= htmlspecialchars( $payerEmail ) ?>&gt;</p>
			<table>
				<thead>
					<tr>
						<th>Item</th>
						<th>SKU</th>
						<th>Qty</th>
						<th class="amount">Total</th>
					</tr>
				</thead>
				<tbody>
					<?php foreach( $items as $item ): ?>
						<tr>
							<td><?= htmlspecialchars( (string) ( $item['name'] ?? '' ) ) ?></td>
							<td><?= htmlspecialchars( (string) ( $item['sku'] ?? '' ) ) ?></td>
							<td><?= (int) ( $item['quantity'] ?? 1 ) ?></td>
							<td class="amount"><?= htmlspecialchars( (string) ( $item['totalFormatted'] ?? '' ) ) ?></td>
						</tr>
					<?php endforeach; ?>
				</tbody>
				<tfoot>
					<tr>
						<td colspan="3" class="total">Total</td>
						<td class="amount total"><?= htmlspecialchars( $totalFormatted ) ?></td>
					</tr>
				</tfoot>
			</table>
			<p>
				Reference:
				<?= htmlspecialchars( (string) ( $order['payment_intent_id'] ?? $order['session_id'] ?? '' ) ) ?>
			</p>
		</div>
		<div class="email-footer">
			Sent by your store.
		</div>
	</div>
</body>
</html>
