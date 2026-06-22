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

$labelFor = static function( string $name ) use ( $fields ): string {
	foreach( ( $fields ?? [] ) as $field )
	{
		if( ( $field['name'] ?? null ) === $name )
		{
			return $field['label'] ?? $name;
		}
	}
	return $name;
};

$orderedKeys = [];
foreach( ( $fields ?? [] ) as $field )
{
	if( isset( $field['name'] ) && array_key_exists( $field['name'], $payload ?? [] ) )
	{
		$orderedKeys[] = $field['name'];
	}
}
foreach( array_keys( $payload ?? [] ) as $key )
{
	if( !in_array( $key, $orderedKeys, true ) )
	{
		$orderedKeys[] = $key;
	}
}
?>
<div class="container-fluid">
	<div class="d-flex justify-content-between align-items-center mb-4">
		<h2>Donation #<?= htmlspecialchars( (string) ( $donation['id'] ?? '' ) ) ?></h2>
		<a href="<?= route_path('admin_donations') ?>" class="btn btn-outline-secondary">
			<i class="bi bi-arrow-left"></i> Back
		</a>
	</div>

	<div class="row g-4">
		<div class="col-lg-5">
			<div class="card">
				<div class="card-header">Transaction</div>
				<div class="card-body">
					<dl class="row mb-0 small">
						<dt class="col-5">Form</dt>
						<dd class="col-7"><?= htmlspecialchars( (string) ( $donation['form_key'] ?? '' ) ) ?></dd>

						<dt class="col-5">Amount</dt>
						<dd class="col-7"><strong><?= htmlspecialchars( $money( $donation['amount_cents'] ?? 0, $donation['currency'] ?? 'usd' ) ) ?></strong></dd>

						<dt class="col-5">Frequency</dt>
						<dd class="col-7"><?= htmlspecialchars( $freqLabel( (string) ( $donation['frequency'] ?? 'one_time' ) ) ) ?></dd>

						<dt class="col-5">Status</dt>
						<dd class="col-7"><?= htmlspecialchars( ucfirst( (string) ( $donation['status'] ?? '' ) ) ) ?></dd>

						<dt class="col-5">Donor</dt>
						<dd class="col-7">
							<?php if( !empty( $donation['donor_name'] ) || !empty( $donation['donor_email'] ) ): ?>
								<?= htmlspecialchars( trim( ( $donation['donor_name'] ?? '' ) . ( !empty( $donation['donor_email'] ) ? ' <' . $donation['donor_email'] . '>' : '' ) ) ) ?>
							<?php else: ?>
								-
							<?php endif; ?>
						</dd>

						<dt class="col-5">Provider</dt>
						<dd class="col-7"><?= htmlspecialchars( (string) ( $donation['provider'] ?? '-' ) ) ?></dd>

						<dt class="col-5">Session</dt>
						<dd class="col-7"><small class="text-muted"><?= htmlspecialchars( (string) ( $donation['session_id'] ?? '-' ) ) ?></small></dd>

						<dt class="col-5">Payment Intent</dt>
						<dd class="col-7"><small class="text-muted"><?= htmlspecialchars( (string) ( $donation['payment_intent_id'] ?? '-' ) ) ?></small></dd>

						<dt class="col-5">Subscription</dt>
						<dd class="col-7"><small class="text-muted"><?= htmlspecialchars( (string) ( $donation['subscription_id'] ?? '-' ) ) ?></small></dd>

						<dt class="col-5">Created</dt>
						<dd class="col-7"><?= htmlspecialchars( (string) ( $donation['created_at'] ?? '' ) ) ?></dd>

						<dt class="col-5">Completed</dt>
						<dd class="col-7"><?= htmlspecialchars( (string) ( $donation['completed_at'] ?? '-' ) ) ?></dd>

						<dt class="col-5">IP</dt>
						<dd class="col-7"><?= htmlspecialchars( (string) ( $donation['ip_address'] ?? '-' ) ) ?></dd>
					</dl>
				</div>
			</div>
		</div>

		<div class="col-lg-7">
			<div class="card">
				<div class="card-header">Donor Details</div>
				<div class="card-body">
					<?php if( empty( $orderedKeys ) ): ?>
						<p class="text-muted mb-0">No field data.</p>
					<?php else: ?>
						<dl class="row mb-0">
							<?php foreach( $orderedKeys as $name ): ?>
								<dt class="col-sm-3"><?= htmlspecialchars( $labelFor( $name ) ) ?></dt>
								<dd class="col-sm-9" style="white-space: pre-wrap;"><?= htmlspecialchars( is_array( $payload[ $name ] ?? '' ) ? implode( ', ', $payload[ $name ] ) : (string) ( $payload[ $name ] ?? '' ) ) ?></dd>
							<?php endforeach; ?>
						</dl>
					<?php endif; ?>
				</div>
			</div>
		</div>
	</div>
</div>
