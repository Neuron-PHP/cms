<?php
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

// Order values by configured fields first, then any extra payload keys.
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
		<h2>Contact Submission #<?= htmlspecialchars( (string) ( $submission['id'] ?? '' ) ) ?></h2>
		<a href="<?= route_path('admin_contact_submissions') ?>" class="btn btn-outline-secondary">
			<i class="bi bi-arrow-left"></i> Back
		</a>
	</div>

	<div class="row g-4">
		<div class="col-lg-4">
			<div class="card">
				<div class="card-header">Metadata</div>
				<div class="card-body">
					<dl class="row mb-0 small">
						<dt class="col-5">Form</dt>
						<dd class="col-7"><?= htmlspecialchars( (string) ( $submission['form_key'] ?? '' ) ) ?></dd>

						<dt class="col-5">Recipient</dt>
						<dd class="col-7"><?= htmlspecialchars( (string) ( $submission['recipient'] ?? '' ) ) ?></dd>

						<dt class="col-5">Subject</dt>
						<dd class="col-7"><?= htmlspecialchars( (string) ( $submission['subject'] ?? '-' ) ) ?></dd>

						<dt class="col-5">Reply-To</dt>
						<dd class="col-7">
							<?php if( !empty( $submission['reply_to_email'] ) ): ?>
								<?= htmlspecialchars( trim( ( $submission['reply_to_name'] ?? '' ) . ' <' . $submission['reply_to_email'] . '>' ) ) ?>
							<?php else: ?>
								-
							<?php endif; ?>
						</dd>

						<dt class="col-5">Delivered</dt>
						<dd class="col-7">
							<?php if( !empty( $submission['delivered'] ) ): ?>
								<span class="badge bg-success">Yes</span>
								<?php if( !empty( $submission['delivered_at'] ) ): ?>
									<div class="text-muted"><?= htmlspecialchars( (string) $submission['delivered_at'] ) ?></div>
								<?php endif; ?>
							<?php else: ?>
								<span class="badge bg-warning text-dark">No</span>
							<?php endif; ?>
						</dd>

						<dt class="col-5">Received</dt>
						<dd class="col-7"><?= htmlspecialchars( (string) ( $submission['created_at'] ?? '' ) ) ?></dd>

						<dt class="col-5">IP</dt>
						<dd class="col-7"><?= htmlspecialchars( (string) ( $submission['ip_address'] ?? '-' ) ) ?></dd>

						<dt class="col-5">User Agent</dt>
						<dd class="col-7"><small class="text-muted"><?= htmlspecialchars( (string) ( $submission['user_agent'] ?? '-' ) ) ?></small></dd>
					</dl>
				</div>
			</div>
		</div>

		<div class="col-lg-8">
			<div class="card">
				<div class="card-header">Submitted Fields</div>
				<div class="card-body">
					<?php if( empty( $orderedKeys ) ): ?>
						<p class="text-muted mb-0">No field data.</p>
					<?php else: ?>
						<dl class="row mb-0">
							<?php foreach( $orderedKeys as $name ): ?>
								<dt class="col-sm-3"><?= htmlspecialchars( $labelFor( $name ) ) ?></dt>
								<dd class="col-sm-9" style="white-space: pre-wrap;"><?= htmlspecialchars( (string) ( $payload[ $name ] ?? '' ) ) ?></dd>
							<?php endforeach; ?>
						</dl>
					<?php endif; ?>
				</div>
			</div>
		</div>
	</div>
</div>
