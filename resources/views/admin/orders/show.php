<?php
$money = static function( $cents, $currency ): string {
	$symbols  = [ 'usd' => '$', 'eur' => '€', 'gbp' => '£', 'cad' => '$', 'aud' => '$' ];
	$currency = strtolower( (string) $currency );
	$symbol   = $symbols[ $currency ] ?? '';

	return $symbol . number_format( ( (int) $cents ) / 100, 2 ) . ( $symbol === '' ? ' ' . strtoupper( $currency ) : '' );
};

$order    = $order ?? [];
$items    = $items ?? [];
$currency = $order['currency'] ?? 'usd';
?>
<div class="container-fluid">
	<div class="d-flex justify-content-between align-items-center mb-4">
		<h2>Order #<?= htmlspecialchars( (string) ( $order['id'] ?? '' ) ) ?></h2>
		<a href="<?= route_path('admin_orders') ?>" class="btn btn-outline-secondary btn-sm">&larr; Back to orders</a>
	</div>

	<div class="row g-4">
		<div class="col-md-5">
			<div class="card">
				<div class="card-header">Details</div>
				<ul class="list-group list-group-flush">
					<li class="list-group-item d-flex justify-content-between"><span class="text-muted">Status</span><span><?= htmlspecialchars( ucfirst( (string) ( $order['status'] ?? '' ) ) ) ?></span></li>
					<li class="list-group-item d-flex justify-content-between"><span class="text-muted">Customer</span><span><?= htmlspecialchars( (string) ( $order['payer_name'] ?? '' ) ) ?></span></li>
					<li class="list-group-item d-flex justify-content-between"><span class="text-muted">Email</span><span><?= htmlspecialchars( (string) ( $order['payer_email'] ?? '' ) ) ?></span></li>
					<li class="list-group-item d-flex justify-content-between"><span class="text-muted">Total</span><span class="fw-bold"><?= htmlspecialchars( $money( $order['amount_cents'] ?? 0, $currency ) ) ?></span></li>
					<li class="list-group-item d-flex justify-content-between"><span class="text-muted">Date</span><span><?= htmlspecialchars( (string) ( $order['created_at'] ?? '' ) ) ?></span></li>
					<li class="list-group-item d-flex justify-content-between"><span class="text-muted">Reference</span><span class="small"><?= htmlspecialchars( (string) ( $order['payment_intent_id'] ?? $order['session_id'] ?? '' ) ) ?></span></li>
				</ul>
			</div>
		</div>
		<div class="col-md-7">
			<div class="card">
				<div class="card-header">Items</div>
				<div class="card-body">
					<?php if( empty( $items ) ): ?>
						<p class="text-muted mb-0">No line items recorded.</p>
					<?php else: ?>
						<table class="table align-middle mb-0">
							<thead>
								<tr><th>Item</th><th>SKU</th><th>Price</th><th>Qty</th><th class="text-end">Total</th></tr>
							</thead>
							<tbody>
								<?php foreach( $items as $item ): ?>
									<tr>
										<td><?= htmlspecialchars( (string) ( $item['name'] ?? '' ) ) ?></td>
										<td><?= htmlspecialchars( (string) ( $item['sku'] ?? '' ) ) ?: '<span class="text-muted">-</span>' ?></td>
										<td><?= htmlspecialchars( $money( $item['unit_amount_cents'] ?? 0, $currency ) ) ?></td>
										<td><?= (int) ( $item['quantity'] ?? 1 ) ?></td>
										<td class="text-end"><?= htmlspecialchars( $money( ( (int) ( $item['unit_amount_cents'] ?? 0 ) ) * ( (int) ( $item['quantity'] ?? 1 ) ), $currency ) ) ?></td>
									</tr>
								<?php endforeach; ?>
							</tbody>
						</table>
					<?php endif; ?>
				</div>
			</div>
		</div>
	</div>
</div>
