<?php
$money = static function( $cents, $currency ): string {
	$symbols  = [ 'usd' => '$', 'eur' => '€', 'gbp' => '£', 'cad' => '$', 'aud' => '$' ];
	$currency = strtolower( (string) $currency );
	$symbol   = $symbols[ $currency ] ?? '';

	return $symbol . number_format( ( (int) $cents ) / 100, 2 ) . ( $symbol === '' ? ' ' . strtoupper( $currency ) : '' );
};

$p = $Product ?? [];
?>
<section class="py-4">
	<div class="container">
		<nav class="mb-3"><a href="/store" class="text-decoration-none">&larr; Back to shop</a></nav>

		<div class="row g-4">
			<?php if( !empty( $p['image_url'] ) ): ?>
				<div class="col-md-6">
					<img src="<?= htmlspecialchars( (string) $p['image_url'] ) ?>" class="img-fluid rounded" alt="<?= htmlspecialchars( (string) $p['name'] ) ?>">
				</div>
			<?php endif; ?>
			<div class="<?= !empty( $p['image_url'] ) ? 'col-md-6' : 'col-12' ?>">
				<h1 class="mb-2"><?= htmlspecialchars( (string) ( $p['name'] ?? '' ) ) ?></h1>
				<p class="fs-4 fw-bold text-primary"><?= htmlspecialchars( $money( $p['price_cents'] ?? 0, $p['currency'] ?? 'usd' ) ) ?></p>
				<?php if( !empty( $p['sku'] ) ): ?>
					<p class="text-muted small">SKU: <?= htmlspecialchars( (string) $p['sku'] ) ?></p>
				<?php endif; ?>
				<?php if( !empty( $p['description'] ) ): ?>
					<div class="mb-4"><?= nl2br( htmlspecialchars( (string) $p['description'] ) ) ?></div>
				<?php endif; ?>

				<form method="POST" action="/cart/add" class="d-flex align-items-center gap-2" style="max-width:18rem;">
					<?= csrf_field() ?>
					<input type="hidden" name="product_id" value="<?= (int) ( $p['id'] ?? 0 ) ?>">
					<input type="number" name="quantity" value="1" min="1" class="form-control" style="max-width:6rem;" aria-label="Quantity">
					<button type="submit" class="btn btn-primary">Add to cart</button>
				</form>
			</div>
		</div>
	</div>
</section>
