<div class="container-fluid">
	<div class="d-flex justify-content-between align-items-center mb-4">
		<h2>Events</h2>
		<a href="<?= route_path('admin_events_create') ?>" class="btn btn-primary">Create New Event</a>
	</div>

	<?php if($Success): ?>
		<div class="alert alert-success alert-dismissible fade show" role="alert">
			<?= htmlspecialchars($Success) ?>
			<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
		</div>
	<?php endif; ?>

	<?php if($Error): ?>
		<div class="alert alert-danger alert-dismissible fade show" role="alert">
			<?= htmlspecialchars($Error) ?>
			<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
		</div>
	<?php endif; ?>

	<div class="card">
		<div class="card-body">
			<?php if(empty($events)): ?>
				<div class="text-center py-5">
					<p class="text-muted mb-3">No events yet.</p>
					<a href="<?= route_path('admin_events_create') ?>" class="btn btn-primary">Create Your First Event</a>
				</div>
			<?php else: ?>
				<div class="table-responsive">
					<table class="table table-hover">
						<thead>
							<tr>
								<th>Title</th>
								<th>Start Date</th>
								<th>Location</th>
								<th>Category</th>
								<th>Status</th>
								<th>Registrations</th>
								<th>Creator</th>
								<th>Views</th>
								<th>Actions</th>
							</tr>
						</thead>
						<tbody>
							<?php foreach($events as $event): ?>
								<tr>
									<td>
										<strong><?= htmlspecialchars($event->getTitle()) ?></strong>
										<?php if($event->isFeatured()): ?>
											<span class="badge bg-warning text-dark" title="Featured event"><i class="bi bi-star-fill"></i> Featured</span>
										<?php endif; ?>
										<?php if($event->getDescription()): ?>
											<br><small class="text-muted"><?= htmlspecialchars(substr($event->getDescription(), 0, 60)) ?><?= strlen($event->getDescription()) > 60 ? '...' : '' ?></small>
										<?php endif; ?>
									</td>
									<td>
										<?= $event->getStartDate()->format('M j, Y') ?>
										<?php if(!$event->isAllDay()): ?>
											<br><small class="text-muted"><?= $event->getStartDate()->format('g:i A') ?></small>
										<?php else: ?>
											<br><small class="text-muted">All Day</small>
										<?php endif; ?>
									</td>
									<td>
										<?php if($event->getLocation()): ?>
											<i class="bi bi-geo-alt"></i> <?= htmlspecialchars($event->getLocation()) ?>
										<?php else: ?>
											<span class="text-muted">N/A</span>
										<?php endif; ?>
									</td>
									<td>
										<?php if($event->getCategory()): ?>
											<span class="badge" style="background-color: <?= htmlspecialchars($event->getCategory()->getColor()) ?>">
												<?= htmlspecialchars($event->getCategory()->getName()) ?>
											</span>
										<?php else: ?>
											<span class="text-muted">Uncategorized</span>
										<?php endif; ?>
									</td>
									<td>
										<span class="badge bg-<?= $event->getStatus() === 'published' ? 'success' : 'secondary' ?>">
											<?= htmlspecialchars(ucfirst($event->getStatus()), ENT_QUOTES, 'UTF-8') ?>
										</span>
									</td>
									<td>
										<?php if($event->isRegistrationEnabled()): ?>
											<?php $count = (int)($registrationCounts[$event->getId()] ?? 0); ?>
											<a href="<?= route_path('admin_event_registrations') ?>?event=<?= (int)$event->getId() ?>"
											   class="text-decoration-none"
											   title="View registrations">
												<?php if($event->hasCapacityLimit()): ?>
													<span class="badge bg-<?= $event->isFull($count) ? 'danger' : 'info' ?> text-<?= $event->isFull($count) ? 'light' : 'dark' ?>">
														<i class="bi bi-person-check"></i> <?= $count ?> / <?= (int)$event->getCapacity() ?>
													</span>
												<?php else: ?>
													<span class="badge bg-info text-dark"><i class="bi bi-person-check"></i> <?= $count ?></span>
												<?php endif; ?>
											</a>
											<?php if($event->isFull($count)): ?>
												<span class="badge bg-danger" title="At capacity">Full</span>
											<?php endif; ?>
											<?php if($event->isPrivate()): ?>
												<span class="badge bg-secondary" title="Members only">Private</span>
											<?php endif; ?>
										<?php else: ?>
											<span class="text-muted">Off</span>
										<?php endif; ?>
									</td>
									<td>
										<?= $event->getCreator()
											? htmlspecialchars($event->getCreator()->getUsername(), ENT_QUOTES, 'UTF-8')
											: 'N/A' ?>
									</td>
									<td><?= $event->getViewCount() ?></td>
									<td>
										<div class="btn-group btn-group-sm" role="group">
											<a href="<?= route_path('admin_events_edit', ['id' => $event->getId()]) ?>"
											   class="btn btn-outline-primary"
											   title="Edit">
												<i class="bi bi-pencil"></i> Edit
											</a>
											<?php if($event->isPublished()): ?>
												<a href="<?= route_path('calendar_event', ['slug' => $event->getSlug()]) ?>"
												   class="btn btn-outline-secondary"
												   target="_blank"
												   title="View">
													<i class="bi bi-eye"></i> View
												</a>
											<?php endif; ?>
											<form method="POST"
												  action="<?= route_path('admin_events_destroy', ['id' => $event->getId()]) ?>"
												  style="display:inline;"
												  onsubmit="return confirm('Are you sure you want to delete this event? This action cannot be undone.')">
												<input type="hidden" name="_method" value="DELETE">
												<?= csrf_field() ?>
												<button type="submit" class="btn btn-outline-danger" title="Delete">
													<i class="bi bi-trash"></i> Delete
												</button>
											</form>
										</div>
									</td>
								</tr>
							<?php endforeach; ?>
						</tbody>
					</table>
				</div>
			<?php endif; ?>
		</div>
	</div>
</div>
