<div class="container-fluid">
	<div class="d-flex justify-content-between align-items-center mb-4">
		<h2>Registration #<?= (int) $registration->getId() ?></h2>
		<a href="<?= route_path('admin_event_registrations') ?>" class="btn btn-outline-secondary">
			<i class="bi bi-arrow-left"></i> Back
		</a>
	</div>

	<div class="row g-4">
		<div class="col-lg-5">
			<div class="card">
				<div class="card-header">Event</div>
				<div class="card-body">
					<?php if( !empty( $event ) ): ?>
						<dl class="row mb-0 small">
							<dt class="col-4">Title</dt>
							<dd class="col-8"><?= htmlspecialchars( $event->getTitle() ) ?></dd>

							<dt class="col-4">Date</dt>
							<dd class="col-8"><?= htmlspecialchars( $event->getStartDate()->format( 'l, F j, Y g:i A' ) ) ?></dd>

							<?php if( $event->getLocation() ): ?>
								<dt class="col-4">Location</dt>
								<dd class="col-8"><?= htmlspecialchars( $event->getLocation() ) ?></dd>
							<?php endif; ?>

							<dt class="col-4">Visibility</dt>
							<dd class="col-8"><?= htmlspecialchars( ucfirst( $event->getRegistrationVisibility() ) ) ?></dd>

							<dt class="col-4">Capacity</dt>
							<dd class="col-8"><?= $event->hasCapacityLimit() ? (int) $event->getCapacity() : 'Unlimited' ?></dd>
						</dl>
						<a href="<?= route_path('admin_event_registrations') ?>?event=<?= (int) $event->getId() ?>" class="btn btn-sm btn-outline-primary mt-3">
							<i class="bi bi-people"></i> All registrations for this event
						</a>
					<?php else: ?>
						<p class="text-muted mb-0">Event #<?= (int) $registration->getEventId() ?> (deleted or unavailable)</p>
					<?php endif; ?>
				</div>
			</div>
		</div>

		<div class="col-lg-7">
			<div class="card">
				<div class="card-header">Registrant</div>
				<div class="card-body">
					<dl class="row mb-0">
						<dt class="col-sm-3">Name</dt>
						<dd class="col-sm-9"><?= htmlspecialchars( $registration->getName() ) ?></dd>

						<dt class="col-sm-3">Email</dt>
						<dd class="col-sm-9">
							<a href="mailto:<?= htmlspecialchars( $registration->getEmail() ) ?>"><?= htmlspecialchars( $registration->getEmail() ) ?></a>
						</dd>

						<dt class="col-sm-3">Account</dt>
						<dd class="col-sm-9">
							<?php if( $registration->getUserId() ): ?>
								Member #<?= (int) $registration->getUserId() ?>
							<?php else: ?>
								<span class="text-muted">Guest registration</span>
							<?php endif; ?>
						</dd>

						<?php if( $registration->getNotes() ): ?>
							<dt class="col-sm-3">Notes</dt>
							<dd class="col-sm-9" style="white-space: pre-wrap;"><?= htmlspecialchars( $registration->getNotes() ) ?></dd>
						<?php endif; ?>

						<dt class="col-sm-3">Status</dt>
						<dd class="col-sm-9"><?= htmlspecialchars( ucfirst( $registration->getStatus() ) ) ?></dd>

						<dt class="col-sm-3">Registered</dt>
						<dd class="col-sm-9"><?= htmlspecialchars( (string) ( $registration->getCreatedAt()?->format( 'M j, Y g:i A' ) ?? '' ) ) ?></dd>

						<dt class="col-sm-3">IP</dt>
						<dd class="col-sm-9"><?= htmlspecialchars( (string) ( $registration->getIpAddress() ?? '-' ) ) ?></dd>

						<dt class="col-sm-3">User Agent</dt>
						<dd class="col-sm-9"><small class="text-muted"><?= htmlspecialchars( (string) ( $registration->getUserAgent() ?? '-' ) ) ?></small></dd>
					</dl>
				</div>
			</div>
		</div>
	</div>
</div>
