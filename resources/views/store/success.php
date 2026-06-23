<?php
$money = static function( $cents, $currency ): string {
	$symbols  = [ 'usd' => '$', 'eur' => '€', 'gbp' => '£', 'cad' => '$', 'aud' => '$' ];
	$currency = strtolower( (string) $currency );
	$symbol   = $symbols[ $currency ] ?? '';

	return $symbol . number_format( ( (int) $cents ) / 100, 2 ) . ( $symbol === '' ? ' ' . strtoupper( $currency ) : '' );
};

$order    = $Order ?? null;
$items    = $Items ?? [];
$currency = $order['currency'] ?? 'usd';
?>
<section class="py-5">
	<div class="container">
		<div class="row justify-content-center">
			<div class="col-lg-7 text-center">
				<div class="mb-3" style="font-size:3rem;line-height:1;">&#10003;</div>
				<h1 class="mb-3">Thank You</h1>
				<p class="lead"><?= htmlspecialchars( (string) ( $Message ?? 'Thank you for your order!' ) ) ?></p>

				<?php if( !empty( $items ) ): ?>
					<div class="card text-start mt-4">
						<div class="card-header">Order #<?= htmlspecialchars( (string) ( $order['id'] ?? '' ) ) ?></div>
						<ul class="list-group list-group-flush">
							<?php foreach( $items as $item ): ?>
								<li class="list-group-item d-flex justify-content-between">
									<span><?= (int) $item['quantity'] ?> &times; <?= htmlspecialchars( (string) $item['name'] ) ?></span>
									<span><?= htmlspecialchars( $money( ( (int) $item['unit_amount_cents'] ) * ( (int) $item['quantity'] ), $currency ) ) ?></span>
								</li>
							<?php endforeach; ?>
							<li class="list-group-item d-flex justify-content-between fw-bold">
								<span>Total</span>
								<span><?= htmlspecialchars( $money( $order['amount_cents'] ?? 0, $currency ) ) ?></span>
							</li>
						</ul>
					</div>
					<p class="text-muted mt-3">A receipt has been sent to your email.</p>
				<?php endif; ?>

				<a href="/store" class="btn btn-primary mt-3">Continue shopping</a>
			</div>
		</div>
	</div>
</section>
