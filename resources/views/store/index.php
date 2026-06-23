<?php
$money = static function( $cents, $currency ): string {
	$symbols  = [ 'usd' => '$', 'eur' => '€', 'gbp' => '£', 'cad' => '$', 'aud' => '$' ];
	$currency = strtolower( (string) $currency );
	$symbol   = $symbols[ $currency ] ?? '';

	return $symbol . number_format( ( (int) $cents ) / 100, 2 ) . ( $symbol === '' ? ' ' . strtoupper( $currency ) : '' );
};

$products  = $Products ?? [];
$cartCount = (int) ( ( $Cart['count'] ?? 0 ) );
?>
<section class="py-4">
	<div class="container">
		<div class="d-flex justify-content-between align-items-center mb-4">
			<h1 class="mb-0"><?= htmlspecialchars( (string) ( $StoreTitle ?? 'Shop' ) ) ?></h1>
			<a href="/cart" class="btn btn-outline-primary">
				<i class="bi bi-cart"></i> Cart
				<?php if( $cartCount > 0 ): ?><span class="badge bg-primary"><?= $cartCount ?></span><?php endif; ?>
			</a>
		</div>

		<?php if( !empty( $Success ) ): ?>
			<div class="alert alert-success"><?= htmlspecialchars( (string) $Success ) ?></div>
		<?php endif; ?>
		<?php if( !empty( $Error ) ): ?>
			<div class="alert alert-danger"><?= htmlspecialchars( (string) $Error ) ?></div>
		<?php endif; ?>

		<?php if( empty( $products ) ): ?>
			<p class="text-muted">No products are available right now.</p>
		<?php else: ?>
			<div class="row row-cols-1 row-cols-sm-2 row-cols-md-3 g-4">
				<?php foreach( $products as $p ): ?>
					<div class="col">
						<div class="card h-100">
							<?php if( !empty( $p['image_url'] ) ): ?>
								<a href="/store/product/<?= htmlspecialchars( (string) $p['slug'] ) ?>">
									<img src="<?= htmlspecialchars( (string) $p['image_url'] ) ?>" class="card-img-top" alt="<?= htmlspecialchars( (string) $p['name'] ) ?>" style="object-fit:cover;height:12rem;">
								</a>
							<?php endif; ?>
							<div class="card-body d-flex flex-column">
								<h5 class="card-title">
									<a href="/store/product/<?= htmlspecialchars( (string) $p['slug'] ) ?>" class="text-decoration-none"><?= htmlspecialchars( (string) $p['name'] ) ?></a>
								</h5>
								<div class="mt-auto">
									<p class="fw-bold mb-2"><?= htmlspecialchars( $money( $p['price_cents'] ?? 0, $p['currency'] ?? 'usd' ) ) ?></p>
									<form method="POST" action="/cart/add">
										<?= csrf_field() ?>
										<input type="hidden" name="product_id" value="<?= (int) ( $p['id'] ?? 0 ) ?>">
										<div class="input-group input-group-sm">
											<input type="number" name="quantity" value="1" min="1" class="form-control" style="max-width:5rem;" aria-label="Quantity">
											<button type="submit" class="btn btn-primary">Add to cart</button>
										</div>
									</form>
								</div>
							</div>
						</div>
					</div>
				<?php endforeach; ?>
			</div>
		<?php endif; ?>
	</div>
</section>
