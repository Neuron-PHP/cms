<?php
$money = static function( $cents, $currency ): string {
	$symbols  = [ 'usd' => '$', 'eur' => '€', 'gbp' => '£', 'cad' => '$', 'aud' => '$' ];
	$currency = strtolower( (string) $currency );
	$symbol   = $symbols[ $currency ] ?? '';

	return $symbol . number_format( ( (int) $cents ) / 100, 2 ) . ( $symbol === '' ? ' ' . strtoupper( $currency ) : '' );
};

$statusBadge = static function( string $status ): string {
	return match( $status )
	{
		'completed' => 'bg-success',
		'failed'    => 'bg-danger',
		'refunded'  => 'bg-secondary',
		default     => 'bg-warning text-dark'
	};
};

$orders   = $orders ?? [];
$statuses = [ 'pending', 'completed', 'failed', 'refunded' ];
?>
<div class="container-fluid">
	<div class="d-flex justify-content-between align-items-center mb-4">
		<h2>Orders</h2>
		<form method="GET" action="<?= route_path('admin_orders') ?>" class="d-flex align-items-center gap-2">
			<select name="status" class="form-select form-select-sm" onchange="this.form.submit()">
				<option value="">All statuses</option>
				<?php foreach( $statuses as $st ): ?>
					<option value="<?= htmlspecialchars( $st ) ?>" <?= ( ( $activeStatus ?? null ) === $st ) ? 'selected' : '' ?>><?= ucfirst( $st ) ?></option>
				<?php endforeach; ?>
			</select>
		</form>
	</div>

	<div class="card">
		<div class="card-body">
			<?php if( empty( $orders ) ): ?>
				<p class="text-muted text-center py-4">No orders found.</p>
			<?php else: ?>
				<div class="table-responsive">
					<table class="table table-hover align-middle">
						<thead>
							<tr>
								<th>#</th>
								<th>Customer</th>
								<th>Amount</th>
								<th>Status</th>
								<th>Date</th>
								<th width="120">Actions</th>
							</tr>
						</thead>
						<tbody>
							<?php foreach( $orders as $o ): ?>
								<tr>
									<td><?= (int) ( $o['id'] ?? 0 ) ?></td>
									<td>
										<?php if( !empty( $o['payer_name'] ) || !empty( $o['payer_email'] ) ): ?>
											<?= htmlspecialchars( trim( ( $o['payer_name'] ?? '' ) . ( !empty( $o['payer_email'] ) ? ' <' . $o['payer_email'] . '>' : '' ) ) ) ?>
										<?php else: ?>
											<span class="text-muted">-</span>
										<?php endif; ?>
									</td>
									<td><?= htmlspecialchars( $money( $o['amount_cents'] ?? 0, $o['currency'] ?? 'usd' ) ) ?></td>
									<td><span class="badge <?= $statusBadge( (string) ( $o['status'] ?? '' ) ) ?>"><?= htmlspecialchars( ucfirst( (string) ( $o['status'] ?? '' ) ) ) ?></span></td>
									<td><?= htmlspecialchars( (string) ( $o['created_at'] ?? '' ) ) ?></td>
									<td>
										<a href="<?= route_path('admin_order_show', ['id' => $o['id']]) ?>" class="btn btn-sm btn-outline-primary" title="View"><i class="bi bi-eye"></i></a>
										<form action="<?= route_path('admin_order_delete', ['id' => $o['id']]) ?>" method="POST" class="d-inline" onsubmit="return confirm('Delete this order record?');">
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
							<?php
							$filterQs = '';
							if( !empty( $activeStatus ) ) { $filterQs .= '&status=' . urlencode( $activeStatus ); }
							for( $p = 1; $p <= $pages; $p++ ):
							?>
								<li class="page-item <?= ( $p === ( $page ?? 1 ) ) ? 'active' : '' ?>">
									<a class="page-link" href="<?= route_path('admin_orders') ?>?page=<?= $p . $filterQs ?>"><?= $p ?></a>
								</li>
							<?php endfor; ?>
						</ul>
					</nav>
				<?php endif; ?>

				<p class="text-muted small mt-2 mb-0"><?= (int) ( $total ?? 0 ) ?> total order(s)</p>
			<?php endif; ?>
		</div>
	</div>
</div>
