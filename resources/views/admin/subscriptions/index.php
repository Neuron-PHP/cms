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
		'active'   => 'bg-success',
		'past_due' => 'bg-warning text-dark',
		'canceled' => 'bg-secondary',
		'unpaid'   => 'bg-danger',
		default    => 'bg-light text-dark'
	};
};

$freqLabel = static function( string $f ): string {
	return [
		'one_time'   => 'One-time',
		'monthly'    => 'Monthly',
		'quarterly'  => 'Quarterly',
		'semiannual' => 'Semi-annually',
		'annual'     => 'Annually'
	][ $f ] ?? ucfirst( $f );
};

$statuses = [ 'active', 'past_due', 'canceled', 'unpaid' ];
?>
<div class="container-fluid">
	<div class="d-flex justify-content-between align-items-center mb-4">
		<h2>Subscriptions</h2>
		<form method="GET" action="<?= route_path('admin_subscriptions') ?>" class="d-flex align-items-center gap-2">
			<select name="status" class="form-select form-select-sm" onchange="this.form.submit()">
				<option value="">All statuses</option>
				<?php foreach( $statuses as $st ): ?>
					<option value="<?= htmlspecialchars( $st ) ?>" <?= ( ( $activeStatus ?? null ) === $st ) ? 'selected' : '' ?>><?= ucfirst( str_replace( '_', ' ', $st ) ) ?></option>
				<?php endforeach; ?>
			</select>
			<select name="form" class="form-select form-select-sm" onchange="this.form.submit()">
				<option value="">All forms</option>
				<?php foreach( ( $formKeys ?? [] ) as $key ): ?>
					<option value="<?= htmlspecialchars( $key ) ?>" <?= ( ( $activeFormKey ?? null ) === $key ) ? 'selected' : '' ?>><?= htmlspecialchars( $key ) ?></option>
				<?php endforeach; ?>
			</select>
		</form>
	</div>

	<div class="card">
		<div class="card-body">
			<?php if( empty( $subscriptions ) ): ?>
				<p class="text-muted text-center py-4">No subscriptions found.</p>
			<?php else: ?>
				<div class="table-responsive">
					<table class="table table-hover align-middle">
						<thead>
							<tr>
								<th>#</th>
								<th>Form</th>
								<th>Subscriber</th>
								<th>Amount</th>
								<th>Frequency</th>
								<th>Status</th>
								<th>Renews</th>
								<th width="80">View</th>
							</tr>
						</thead>
						<tbody>
							<?php foreach( $subscriptions as $s ): ?>
								<tr>
									<td><?= htmlspecialchars( (string) ( $s['id'] ?? '' ) ) ?></td>
									<td><span class="badge bg-secondary"><?= htmlspecialchars( (string) ( $s['form_key'] ?? '' ) ) ?></span></td>
									<td>
										<?php if( !empty( $s['payer_name'] ) || !empty( $s['payer_email'] ) ): ?>
											<?= htmlspecialchars( trim( ( $s['payer_name'] ?? '' ) . ( !empty( $s['payer_email'] ) ? ' <' . $s['payer_email'] . '>' : '' ) ) ) ?>
										<?php else: ?>
											<span class="text-muted">-</span>
										<?php endif; ?>
									</td>
									<td><?= htmlspecialchars( $money( $s['amount_cents'] ?? 0, $s['currency'] ?? 'usd' ) ) ?></td>
									<td><?= htmlspecialchars( $freqLabel( (string) ( $s['frequency'] ?? 'monthly' ) ) ) ?></td>
									<td><span class="badge <?= $statusBadge( (string) ( $s['status'] ?? '' ) ) ?>"><?= htmlspecialchars( ucfirst( str_replace( '_', ' ', (string) ( $s['status'] ?? '' ) ) ) ) ?></span></td>
									<td><?= htmlspecialchars( (string) ( $s['current_period_end'] ?? '-' ) ) ?></td>
									<td>
										<a href="<?= route_path('admin_subscription_show', ['id' => $s['id']]) ?>" class="btn btn-sm btn-outline-primary" title="View">
											<i class="bi bi-eye"></i>
										</a>
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
							if( !empty( $activeFormKey ) ) { $filterQs .= '&form=' . urlencode( $activeFormKey ); }
							if( !empty( $activeStatus ) ) { $filterQs .= '&status=' . urlencode( $activeStatus ); }
							for( $p = 1; $p <= $pages; $p++ ):
							?>
								<li class="page-item <?= ( $p === ( $page ?? 1 ) ) ? 'active' : '' ?>">
									<a class="page-link" href="<?= route_path('admin_subscriptions') ?>?page=<?= $p . $filterQs ?>"><?= $p ?></a>
								</li>
							<?php endfor; ?>
						</ul>
					</nav>
				<?php endif; ?>

				<p class="text-muted small mt-2 mb-0"><?= (int) ( $total ?? 0 ) ?> total subscription(s)</p>
			<?php endif; ?>
		</div>
	</div>
</div>
