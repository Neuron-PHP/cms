<?php
$money = static function( $cents, $currency ): string {
	$symbols  = [ 'usd' => '$', 'eur' => '€', 'gbp' => '£', 'cad' => '$', 'aud' => '$' ];
	$currency = strtolower( (string) $currency );
	$symbol   = $symbols[ $currency ] ?? '';

	return $symbol . number_format( ( (int) $cents ) / 100, 2 ) . ( $symbol === '' ? ' ' . strtoupper( $currency ) : '' );
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

$status   = (string) ( $subscription['status'] ?? '' );
$isActive = in_array( $status, [ 'active', 'past_due' ], true );
?>
<div class="container-fluid">
	<div class="d-flex justify-content-between align-items-center mb-4">
		<h2>Subscription #<?= htmlspecialchars( (string) ( $subscription['id'] ?? '' ) ) ?></h2>
		<a href="<?= route_path('admin_subscriptions') ?>" class="btn btn-outline-secondary">
			<i class="bi bi-arrow-left"></i> Back
		</a>
	</div>

	<div class="row g-4">
		<div class="col-lg-5">
			<div class="card">
				<div class="card-header d-flex justify-content-between align-items-center">
					<span>Subscription</span>
					<?php if( $isActive && !empty( $canCancel ) ): ?>
						<form action="<?= route_path('admin_subscription_cancel', ['id' => $subscription['id']]) ?>" method="POST" onsubmit="return confirm('Cancel this subscription? This stops future charges.');">
							<?= csrf_field() ?>
							<button type="submit" class="btn btn-sm btn-outline-danger">Cancel subscription</button>
						</form>
					<?php endif; ?>
				</div>
				<div class="card-body">
					<dl class="row mb-0 small">
						<dt class="col-5">Form</dt>
						<dd class="col-7"><?= htmlspecialchars( (string) ( $subscription['form_key'] ?? '' ) ) ?></dd>

						<dt class="col-5">Purpose</dt>
						<dd class="col-7"><?= htmlspecialchars( ucfirst( (string) ( $subscription['purpose'] ?? '' ) ) ) ?></dd>

						<dt class="col-5">Amount</dt>
						<dd class="col-7"><strong><?= htmlspecialchars( $money( $subscription['amount_cents'] ?? 0, $subscription['currency'] ?? 'usd' ) ) ?></strong> / <?= htmlspecialchars( $freqLabel( (string) ( $subscription['frequency'] ?? 'monthly' ) ) ) ?></dd>

						<dt class="col-5">Status</dt>
						<dd class="col-7"><?= htmlspecialchars( ucfirst( str_replace( '_', ' ', $status ) ) ) ?></dd>

						<dt class="col-5">Subscriber</dt>
						<dd class="col-7">
							<?php if( !empty( $subscription['payer_name'] ) || !empty( $subscription['payer_email'] ) ): ?>
								<?= htmlspecialchars( trim( ( $subscription['payer_name'] ?? '' ) . ( !empty( $subscription['payer_email'] ) ? ' <' . $subscription['payer_email'] . '>' : '' ) ) ) ?>
							<?php else: ?>
								-
							<?php endif; ?>
						</dd>

						<dt class="col-5">Provider</dt>
						<dd class="col-7"><?= htmlspecialchars( (string) ( $subscription['provider'] ?? '-' ) ) ?></dd>

						<dt class="col-5">Subscription ID</dt>
						<dd class="col-7"><small class="text-muted"><?= htmlspecialchars( (string) ( $subscription['subscription_id'] ?? '-' ) ) ?></small></dd>

						<dt class="col-5">Renews</dt>
						<dd class="col-7"><?= htmlspecialchars( (string) ( $subscription['current_period_end'] ?? '-' ) ) ?></dd>

						<dt class="col-5">Created</dt>
						<dd class="col-7"><?= htmlspecialchars( (string) ( $subscription['created_at'] ?? '' ) ) ?></dd>

						<dt class="col-5">Canceled</dt>
						<dd class="col-7"><?= htmlspecialchars( (string) ( $subscription['canceled_at'] ?? '-' ) ) ?></dd>
					</dl>
				</div>
			</div>
		</div>

		<div class="col-lg-7">
			<div class="card">
				<div class="card-header">Charges (<?= count( $charges ?? [] ) ?>)</div>
				<div class="card-body">
					<?php if( empty( $charges ) ): ?>
						<p class="text-muted mb-0">No charges recorded yet.</p>
					<?php else: ?>
						<div class="table-responsive">
							<table class="table table-sm align-middle mb-0">
								<thead>
									<tr>
										<th>#</th>
										<th>Amount</th>
										<th>Status</th>
										<th>Date</th>
									</tr>
								</thead>
								<tbody>
									<?php foreach( $charges as $c ): ?>
										<tr>
											<td>
												<a href="<?= route_path('admin_payment_show', ['id' => $c['id']]) ?>"><?= htmlspecialchars( (string) ( $c['id'] ?? '' ) ) ?></a>
											</td>
											<td><?= htmlspecialchars( $money( $c['amount_cents'] ?? 0, $c['currency'] ?? 'usd' ) ) ?></td>
											<td><?= htmlspecialchars( ucfirst( (string) ( $c['status'] ?? '' ) ) ) ?></td>
											<td><?= htmlspecialchars( (string) ( $c['completed_at'] ?? $c['created_at'] ?? '' ) ) ?></td>
										</tr>
									<?php endforeach; ?>
								</tbody>
							</table>
						</div>
					<?php endif; ?>
				</div>
			</div>
		</div>
	</div>
</div>
