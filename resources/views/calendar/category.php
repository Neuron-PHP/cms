<div class="container py-5">
	<div class="row">
		<div class="col-lg-10 mx-auto">
			<!-- Category Header -->
			<div class="mb-4">
				<nav aria-label="breadcrumb">
					<ol class="breadcrumb">
						<li class="breadcrumb-item">
							<a href="<?= route_path('calendar') ?>">Calendar</a>
						</li>
						<li class="breadcrumb-item active" aria-current="page">
							<?= htmlspecialchars($category->getName()) ?>
						</li>
					</ol>
				</nav>

				<div class="d-flex align-items-center gap-3 mb-3">
					<span class="badge fs-5"
					      style="background-color: <?= htmlspecialchars($category->getColor()) ?>">
						<?= htmlspecialchars($category->getName()) ?>
					</span>
					<h1 class="display-5 mb-0">Events</h1>
				</div>

				<?php if($category->getDescription()): ?>
					<p class="lead text-muted"><?= htmlspecialchars($category->getDescription()) ?></p>
				<?php endif; ?>
			</div>

			<!-- Events List -->
			<?php if(empty($events)): ?>
				<div class="alert alert-info">
					<i class="bi bi-info-circle"></i>
					No events found in this category.
					<a href="<?= route_path('calendar') ?>" class="alert-link">View all events</a>
				</div>
			<?php else: ?>
				<div class="row g-4">
					<?php foreach($events as $event): ?>
						<div class="col-md-6">
							<div class="card h-100 shadow-sm">
								<?php if($event->getFeaturedImage()): ?>
									<img src="<?= htmlspecialchars($event->getFeaturedImage()) ?>"
									     class="card-img-top"
									     alt="<?= htmlspecialchars($event->getTitle()) ?>"
									     style="height: 200px; object-fit: cover;">
								<?php endif; ?>

								<div class="card-body">
									<h5 class="card-title">
										<a href="<?= route_path('calendar_event', ['slug' => $event->getSlug()]) ?>"
										   class="text-decoration-none text-dark">
											<?= htmlspecialchars($event->getTitle()) ?>
										</a>
									</h5>

									<div class="text-muted small mb-3">
										<div class="mb-1">
											<i class="bi bi-calendar-event"></i>
											<?= $event->getStartDate()->format('l, F j, Y') ?>
										</div>

										<?php if(!$event->isAllDay()): ?>
											<div class="mb-1">
												<i class="bi bi-clock"></i>
												<?= $event->getStartDate()->format('g:i A') ?>
												<?php if($event->getEndDate() && $event->getEndDate() != $event->getStartDate()): ?>
													- <?= $event->getEndDate()->format('g:i A') ?>
												<?php endif; ?>
											</div>
										<?php else: ?>
											<div class="mb-1">
												<i class="bi bi-clock"></i>
												All Day Event
											</div>
										<?php endif; ?>

										<?php if($event->getLocation()): ?>
											<div class="mb-1">
												<i class="bi bi-geo-alt"></i>
												<?= htmlspecialchars($event->getLocation()) ?>
											</div>
										<?php endif; ?>
									</div>

									<?php if($event->getDescription()): ?>
										<p class="card-text">
											<?= htmlspecialchars(substr($event->getDescription(), 0, 120)) ?>
											<?= strlen($event->getDescription()) > 120 ? '...' : '' ?>
										</p>
									<?php endif; ?>
								</div>

								<div class="card-footer bg-white border-top-0">
									<a href="<?= route_path('calendar_event', ['slug' => $event->getSlug()]) ?>"
									   class="btn btn-sm btn-outline-primary">
										View Details <i class="bi bi-arrow-right"></i>
									</a>
								</div>
							</div>
						</div>
					<?php endforeach; ?>
				</div>
			<?php endif; ?>

			<!-- Back to Calendar -->
			<div class="text-center mt-5">
				<a href="<?= route_path('calendar') ?>" class="btn btn-outline-secondary">
					<i class="bi bi-arrow-left"></i> Back to Calendar
				</a>
			</div>
		</div>
	</div>
</div>
