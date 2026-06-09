<div class="container-fluid">
	<div class="d-flex justify-content-between align-items-center mb-4">
		<h2>
			Event Registrations
			<?php if( !empty( $activeEvent ) ): ?>
				<small class="text-muted">&mdash; <?= htmlspecialchars( $activeEvent->getTitle() ) ?></small>
			<?php endif; ?>
		</h2>
		<form method="GET" action="<?= route_path('admin_event_registrations') ?>" class="d-flex align-items-center gap-2">
			<label for="eventFilter" class="form-label mb-0 small text-muted">Filter:</label>
			<select id="eventFilter" name="event" class="form-select form-select-sm" onchange="this.form.submit()">
				<option value="">All events</option>
				<?php foreach( ( $events ?? [] ) as $event ): ?>
					<option value="<?= (int) $event->getId() ?>" <?= ( ( $activeEventId ?? null ) === $event->getId() ) ? 'selected' : '' ?>>
						<?= htmlspecialchars( $event->getTitle() ) ?>
					</option>
				<?php endforeach; ?>
			</select>
		</form>
	</div>

	<div class="card">
		<div class="card-body">
			<?php if( empty( $registrations ) ): ?>
				<p class="text-muted text-center py-4">No registrations found.</p>
			<?php else: ?>
				<div class="table-responsive">
					<table class="table table-hover align-middle">
						<thead>
							<tr>
								<th>#</th>
								<th>Name</th>
								<th>Email</th>
								<th>Event</th>
								<th>Member</th>
								<th>Registered</th>
								<th width="120">Actions</th>
							</tr>
						</thead>
						<tbody>
							<?php foreach( $registrations as $r ): ?>
								<tr>
									<td><?= (int) $r->getId() ?></td>
									<td><?= htmlspecialchars( $r->getName() ) ?></td>
									<td><?= htmlspecialchars( $r->getEmail() ) ?></td>
									<td><?= htmlspecialchars( (string) ( $eventTitles[ $r->getEventId() ] ?? ( '#' . $r->getEventId() ) ) ) ?></td>
									<td>
										<?php if( $r->getUserId() ): ?>
											<span class="badge bg-info text-dark">Member</span>
										<?php else: ?>
											<span class="text-muted">Guest</span>
										<?php endif; ?>
									</td>
									<td><?= htmlspecialchars( (string) ( $r->getCreatedAt()?->format( 'M j, Y g:i A' ) ?? '' ) ) ?></td>
									<td>
										<a href="<?= route_path('admin_event_registration_show', ['id' => $r->getId()]) ?>" class="btn btn-sm btn-outline-primary" title="View">
											<i class="bi bi-eye"></i>
										</a>
										<form action="<?= route_path('admin_event_registration_delete', ['id' => $r->getId()]) ?>" method="POST" class="d-inline" onsubmit="return confirm('Delete this registration?');">
											<input type="hidden" name="_method" value="DELETE">
											<?= csrf_field() ?>
											<button type="submit" class="btn btn-sm btn-outline-danger" title="Delete">
												<i class="bi bi-trash"></i>
											</button>
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
							$filterQs = !empty( $activeEventId ) ? '&event=' . urlencode( (string) $activeEventId ) : '';
							for( $p = 1; $p <= $pages; $p++ ):
							?>
								<li class="page-item <?= ( $p === ( $page ?? 1 ) ) ? 'active' : '' ?>">
									<a class="page-link" href="<?= route_path('admin_event_registrations') ?>?page=<?= $p . $filterQs ?>"><?= $p ?></a>
								</li>
							<?php endfor; ?>
						</ul>
					</nav>
				<?php endif; ?>

				<p class="text-muted small mt-2 mb-0"><?= (int) ( $total ?? 0 ) ?> total registration(s)</p>
			<?php endif; ?>
		</div>
	</div>
</div>
