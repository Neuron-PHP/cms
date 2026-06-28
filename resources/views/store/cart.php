<?php
$money = static function( $cents, $currency ): string {
	$symbols  = [ 'usd' => '$', 'eur' => '€', 'gbp' => '£', 'cad' => '$', 'aud' => '$' ];
	$currency = strtolower( (string) $currency );
	$symbol   = $symbols[ $currency ] ?? '';

	return $symbol . number_format( ( (int) $cents ) / 100, 2 ) . ( $symbol === '' ? ' ' . strtoupper( $currency ) : '' );
};

$cart     = $Cart ?? [ 'items' => [], 'total_cents' => 0, 'currency' => 'usd' ];
$items    = $cart['items'] ?? [];
$currency = $cart['currency'] ?? 'usd';
?>
<section class="py-4">
	<div class="container">
		<h1 class="mb-4">Your Cart</h1>

		<?php if( !empty( $Success ) ): ?>
			<div class="alert alert-success"><?= htmlspecialchars( (string) $Success ) ?></div>
		<?php endif; ?>
		<?php if( !empty( $Error ) ): ?>
			<div class="alert alert-danger"><?= htmlspecialchars( (string) $Error ) ?></div>
		<?php endif; ?>

		<?php if( empty( $items ) ): ?>
			<p class="text-muted">Your cart is empty.</p>
			<a href="/store" class="btn btn-primary">Browse products</a>
		<?php else: ?>
			<form method="POST" action="/cart/update">
				<?= csrf_field() ?>
				<div class="table-responsive">
					<table class="table align-middle">
						<thead>
							<tr>
								<th>Product</th>
								<th>Price</th>
								<th style="width:8rem;">Qty</th>
								<th class="text-end">Total</th>
								<th></th>
							</tr>
						</thead>
						<tbody>
							<?php foreach( $items as $item ): ?>
								<tr>
									<td><?= htmlspecialchars( (string) $item['name'] ) ?></td>
									<td><?= htmlspecialchars( $money( $item['unit_amount_cents'], $currency ) ) ?></td>
									<td>
										<input type="number" name="quantities[<?= (int) $item['product_id'] ?>]" value="<?= (int) $item['quantity'] ?>" min="0" class="form-control form-control-sm">
									</td>
									<td class="text-end"><?= htmlspecialchars( $money( $item['line_total_cents'], $currency ) ) ?></td>
									<td class="text-end">
										<button type="submit" form="remove-<?= (int) $item['product_id'] ?>" class="btn btn-sm btn-outline-danger" title="Remove">
											<i class="bi bi-trash"></i>
										</button>
									</td>
								</tr>
							<?php endforeach; ?>
						</tbody>
						<tfoot>
							<tr>
								<th colspan="3" class="text-end">Total</th>
								<th class="text-end"><?= htmlspecialchars( $money( $cart['total_cents'], $currency ) ) ?></th>
								<th></th>
							</tr>
						</tfoot>
					</table>
				</div>
				<div class="d-flex gap-2">
					<button type="submit" class="btn btn-outline-secondary"><i class="bi bi-arrow-clockwise"></i> Update cart</button>
					<a href="/store" class="btn btn-link" onclick="if(history.length>1){history.back();return false;}"><i class="bi bi-arrow-left"></i> Continue shopping</a>
				</div>
			</form>

			<?php foreach( $items as $item ): ?>
				<form id="remove-<?= (int) $item['product_id'] ?>" method="POST" action="/cart/remove" class="d-none">
					<?= csrf_field() ?>
					<input type="hidden" name="product_id" value="<?= (int) $item['product_id'] ?>">
				</form>
			<?php endforeach; ?>

			<hr class="my-4">

			<div class="row justify-content-end">
				<div class="col-md-6 col-lg-5">
					<div class="card">
						<div class="card-body">
							<h5 class="card-title mb-3">Checkout</h5>
							<form method="POST" action="/store/checkout">
								<?= csrf_field() ?>
								<div class="mb-3">
									<label class="form-label" for="checkout_name">Name <span class="text-danger">*</span></label>
									<input type="text" class="form-control" id="checkout_name" name="name" required>
								</div>
								<div class="mb-3">
									<label class="form-label" for="checkout_email">Email <span class="text-danger">*</span></label>
									<input type="email" class="form-control" id="checkout_email" name="email" required>
								</div>
								<div class="d-flex justify-content-between align-items-center">
									<span class="fw-bold"><?= htmlspecialchars( $money( $cart['total_cents'], $currency ) ) ?></span>
									<button type="submit" class="btn btn-primary">Proceed to payment</button>
								</div>
							</form>
						</div>
					</div>
				</div>
			</div>
		<?php endif; ?>
	</div>
</section>
