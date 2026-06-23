<?php
$money = static function( $cents, $currency ): string {
	$symbols  = [ 'usd' => '$', 'eur' => '€', 'gbp' => '£', 'cad' => '$', 'aud' => '$' ];
	$currency = strtolower( (string) $currency );
	$symbol   = $symbols[ $currency ] ?? '';

	return $symbol . number_format( ( (int) $cents ) / 100, 2 ) . ( $symbol === '' ? ' ' . strtoupper( $currency ) : '' );
};

$products = $products ?? [];
?>
<div class="container-fluid">
	<div class="d-flex justify-content-between align-items-center mb-4">
		<h2>Products</h2>
		<a href="<?= route_path('admin_products_create') ?>" class="btn btn-primary"><i class="bi bi-plus-lg"></i> Add Product</a>
	</div>

	<div class="card">
		<div class="card-body">
			<?php if( empty( $products ) ): ?>
				<p class="text-muted text-center py-4">No products yet. <a href="<?= route_path('admin_products_create') ?>">Add your first product</a>.</p>
			<?php else: ?>
				<div class="table-responsive">
					<table class="table table-hover align-middle">
						<thead>
							<tr>
								<th>#</th>
								<th>Name</th>
								<th>SKU</th>
								<th>Price</th>
								<th>Active</th>
								<th>Sort</th>
								<th width="140">Actions</th>
							</tr>
						</thead>
						<tbody>
							<?php foreach( $products as $p ): ?>
								<tr>
									<td><?= (int) ( $p['id'] ?? 0 ) ?></td>
									<td>
										<a href="/store/product/<?= htmlspecialchars( (string) ( $p['slug'] ?? '' ) ) ?>" target="_blank"><?= htmlspecialchars( (string) ( $p['name'] ?? '' ) ) ?></a>
									</td>
									<td><?= htmlspecialchars( (string) ( $p['sku'] ?? '' ) ) ?: '<span class="text-muted">-</span>' ?></td>
									<td><?= htmlspecialchars( $money( $p['price_cents'] ?? 0, $p['currency'] ?? 'usd' ) ) ?></td>
									<td>
										<?php if( (int) ( $p['active'] ?? 0 ) === 1 ): ?>
											<span class="badge bg-success">Active</span>
										<?php else: ?>
											<span class="badge bg-secondary">Hidden</span>
										<?php endif; ?>
									</td>
									<td><?= (int) ( $p['sort_order'] ?? 0 ) ?></td>
									<td>
										<a href="<?= route_path('admin_products_edit', ['id' => $p['id']]) ?>" class="btn btn-sm btn-outline-primary" title="Edit"><i class="bi bi-pencil"></i></a>
										<form action="<?= route_path('admin_products_destroy', ['id' => $p['id']]) ?>" method="POST" class="d-inline" onsubmit="return confirm('Delete this product?');">
											<input type="hidden" name="_method" value="DELETE">
											<?= csrf_field() ?>
											<button type="submit" class="btn btn-sm btn-outline-danger" title="Delete"><i class="bi bi-trash"></i></button>
										</form>
									</td>
								</tr>
							<?php endforeach; ?>
						</tbody>
					</table>
				</div>

				<?php if( ( $pages ?? 1 ) > 1 ): ?>
					<nav class="mt-3">
						<ul class="pagination pagination-sm mb-0">
							<?php for( $p = 1; $p <= $pages; $p++ ): ?>
								<li class="page-item <?= ( $p === ( $page ?? 1 ) ) ? 'active' : '' ?>">
									<a class="page-link" href="<?= route_path('admin_products') ?>?page=<?= $p ?>"><?= $p ?></a>
								</li>
							<?php endfor; ?>
						</ul>
					</nav>
				<?php endif; ?>

				<p class="text-muted small mt-2 mb-0"><?= (int) ( $total ?? 0 ) ?> product(s)</p>
			<?php endif; ?>
		</div>
	</div>
</div>
