<div class="container py-5">
	<div class="row">
		<div class="col-12">
			<div class="d-flex justify-content-between align-items-center mb-4">
				<h1 class="display-5">Event Calendar</h1>

				<!-- Month Navigation -->
				<div class="btn-group" role="group">
					<?php
					$prevMonth = $currentMonth - 1;
					$prevYear = $currentYear;
					if($prevMonth < 1) {
						$prevMonth = 12;
						$prevYear--;
					}

					$nextMonth = $currentMonth + 1;
					$nextYear = $currentYear;
					if($nextMonth > 12) {
						$nextMonth = 1;
						$nextYear++;
					}
					?>
					<a href="<?= route_path('calendar') ?>?month=<?= $prevMonth ?>&year=<?= $prevYear ?>"
					   class="btn btn-outline-primary">
						<i class="bi bi-chevron-left"></i> Previous
					</a>
					<button type="button" class="btn btn-outline-primary disabled">
						<?= $startDate->format('F Y') ?>
					</button>
					<a href="<?= route_path('calendar') ?>?month=<?= $nextMonth ?>&year=<?= $nextYear ?>"
					   class="btn btn-outline-primary">
						Next <i class="bi bi-chevron-right"></i>
					</a>
				</div>
			</div>

			<!-- Category Filter -->
			<?php if(!empty($categories)): ?>
				<div class="mb-4">
					<div class="d-flex flex-wrap gap-2">
						<a href="<?= route_path('calendar') ?>?month=<?= $currentMonth ?>&year=<?= $currentYear ?>"
						   class="btn btn-sm btn-outline-secondary">
							All Events
						</a>
						<?php foreach($categories as $category): ?>
							<a href="<?= route_path('calendar_category', ['slug' => $category->getSlug()]) ?>"
							   class="btn btn-sm text-white"
							   style="background-color: <?= htmlspecialchars($category->getColor()) ?>">
								<?= htmlspecialchars($category->getName()) ?>
							</a>
						<?php endforeach; ?>
					</div>
				</div>
			<?php endif; ?>

			<!-- Calendar Grid -->
			<div class="card mb-4">
				<div class="card-body p-0">
					<div class="table-responsive">
						<table class="table table-bordered mb-0">
							<thead>
								<tr>
									<th class="text-center">Sunday</th>
									<th class="text-center">Monday</th>
									<th class="text-center">Tuesday</th>
									<th class="text-center">Wednesday</th>
									<th class="text-center">Thursday</th>
									<th class="text-center">Friday</th>
									<th class="text-center">Saturday</th>
								</tr>
							</thead>
							<tbody>
								<?php
								// Create calendar grid
								$firstDayOfMonth = new DateTimeImmutable("$currentYear-$currentMonth-01");
								$lastDayOfMonth = $firstDayOfMonth->modify('last day of this month');
								$daysInMonth = (int)$lastDayOfMonth->format('d');
								$firstDayOfWeek = (int)$firstDayOfMonth->format('w'); // 0 = Sunday

								// Group events by day
								$eventsByDay = [];
								foreach($events as $event) {
									$day = (int)$event->getStartDate()->format('d');
									if(!isset($eventsByDay[$day])) {
										$eventsByDay[$day] = [];
									}
									$eventsByDay[$day][] = $event;
								}

								$currentDay = 1;
								$totalCells = ceil(($daysInMonth + $firstDayOfWeek) / 7) * 7;

								for($cell = 0; $cell < $totalCells; $cell++) {
									if($cell % 7 === 0) {
										echo '<tr>';
									}

									if($cell < $firstDayOfWeek || $currentDay > $daysInMonth) {
										echo '<td class="bg-light" style="height: 100px;"></td>';
									} else {
										$isToday = ($currentDay === (int)date('d') && $currentMonth === (int)date('n') && $currentYear === (int)date('Y'));
										$bgClass = $isToday ? 'bg-primary bg-opacity-10' : '';

										echo '<td class="align-top ' . $bgClass . '" style="height: 100px; vertical-align: top;">';
										echo '<div class="p-2">';
										echo '<div class="fw-bold mb-1">' . $currentDay . '</div>';

										if(isset($eventsByDay[$currentDay])) {
											foreach($eventsByDay[$currentDay] as $event) {
												$categoryColor = $event->getCategory() ? $event->getCategory()->getColor() : '#6c757d';
												echo '<div class="mb-1">';
												echo '<a href="' . route_path('calendar_event', ['slug' => $event->getSlug()]) . '" ';
												echo 'class="badge text-decoration-none text-white d-block text-truncate" ';
												echo 'style="background-color: ' . htmlspecialchars($categoryColor) . '" ';
												echo 'title="' . htmlspecialchars($event->getTitle()) . '">';
												if(!$event->isAllDay()) {
													echo $event->getStartDate()->format('g:ia') . ' ';
												}
												echo htmlspecialchars($event->getTitle());
												echo '</a>';
												echo '</div>';
											}
										}

										echo '</div>';
										echo '</td>';
										$currentDay++;
									}

									if($cell % 7 === 6) {
										echo '</tr>';
									}
								}
								?>
							</tbody>
						</table>
					</div>
				</div>
			</div>

			<!-- Event List -->
			<div class="row">
				<div class="col-12">
					<h3 class="mb-3">Events This Month</h3>

					<?php if(empty($events)): ?>
						<div class="alert alert-info">
							<i class="bi bi-info-circle"></i> No events scheduled for <?= $startDate->format('F Y') ?>.
						</div>
					<?php else: ?>
						<div class="list-group">
							<?php foreach($events as $event): ?>
								<a href="<?= route_path('calendar_event', ['slug' => $event->getSlug()]) ?>"
								   class="list-group-item list-group-item-action">
									<div class="d-flex w-100 justify-content-between align-items-start">
										<div class="flex-grow-1">
											<div class="d-flex align-items-center gap-2 mb-1">
												<?php if($event->getCategory()): ?>
													<span class="badge"
													      style="background-color: <?= htmlspecialchars($event->getCategory()->getColor()) ?>">
														<?= htmlspecialchars($event->getCategory()->getName()) ?>
													</span>
												<?php endif; ?>
												<h5 class="mb-0"><?= htmlspecialchars($event->getTitle()) ?></h5>
											</div>

											<div class="text-muted small mb-2">
												<i class="bi bi-calendar-event"></i>
												<?= $event->getStartDate()->format('l, F j, Y') ?>
												<?php if(!$event->isAllDay()): ?>
													at <?= $event->getStartDate()->format('g:i A') ?>
												<?php else: ?>
													(All Day)
												<?php endif; ?>

												<?php if($event->getLocation()): ?>
													<span class="ms-2">
														<i class="bi bi-geo-alt"></i>
														<?= htmlspecialchars($event->getLocation()) ?>
													</span>
												<?php endif; ?>
											</div>

											<?php if($event->getDescription()): ?>
												<p class="mb-0 text-muted">
													<?= htmlspecialchars(substr($event->getDescription(), 0, 150)) ?>
													<?= strlen($event->getDescription()) > 150 ? '...' : '' ?>
												</p>
											<?php endif; ?>
										</div>

										<?php if($event->getFeaturedImage()): ?>
											<img src="<?= htmlspecialchars($event->getFeaturedImage()) ?>"
											     alt="<?= htmlspecialchars($event->getTitle()) ?>"
											     class="img-thumbnail ms-3"
											     style="width: 120px; height: 80px; object-fit: cover;">
										<?php endif; ?>
									</div>
								</a>
							<?php endforeach; ?>
						</div>
					<?php endif; ?>
				</div>
			</div>
		</div>
	</div>
</div>
