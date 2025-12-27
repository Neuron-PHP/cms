<div class="container py-5">
	<div class="row">
		<div class="col-lg-8 mx-auto">
			<!-- Event Header -->
			<article class="event-detail">
				<?php if($event->getFeaturedImage()): ?>
					<div class="mb-4">
						<img src="<?= htmlspecialchars($event->getFeaturedImage()) ?>"
						     alt="<?= htmlspecialchars($event->getTitle()) ?>"
						     class="img-fluid rounded">
					</div>
				<?php endif; ?>

				<header class="mb-4">
					<?php if($event->getCategory()): ?>
						<div class="mb-2">
							<a href="<?= route_path('calendar_category', ['slug' => $event->getCategory()->getSlug()]) ?>"
							   class="badge text-decoration-none"
							   style="background-color: <?= htmlspecialchars($event->getCategory()->getColor()) ?>">
								<?= htmlspecialchars($event->getCategory()->getName()) ?>
							</a>
						</div>
					<?php endif; ?>

					<h1 class="display-4 mb-3"><?= htmlspecialchars($event->getTitle()) ?></h1>

					<!-- Event Meta -->
					<div class="d-flex flex-wrap gap-3 text-muted mb-3">
						<div>
							<i class="bi bi-calendar-event"></i>
							<strong>
								<?= $event->getStartDate()->format('l, F j, Y') ?>
								<?php if(!$event->isAllDay()): ?>
									at <?= $event->getStartDate()->format('g:i A') ?>
								<?php endif; ?>
							</strong>
						</div>

						<?php if($event->getEndDate() && $event->getEndDate() != $event->getStartDate()): ?>
							<div>
								<i class="bi bi-arrow-right"></i>
								<?= $event->getEndDate()->format('F j, Y') ?>
								<?php if(!$event->isAllDay()): ?>
									at <?= $event->getEndDate()->format('g:i A') ?>
								<?php endif; ?>
							</div>
						<?php endif; ?>

						<?php if($event->getLocation()): ?>
							<div>
								<i class="bi bi-geo-alt"></i>
								<?= htmlspecialchars($event->getLocation()) ?>
							</div>
						<?php endif; ?>
					</div>

					<?php if($event->getDescription()): ?>
						<p class="lead"><?= htmlspecialchars($event->getDescription()) ?></p>
					<?php endif; ?>
				</header>

				<!-- Event Content -->
				<?php if($event->getContentRaw()): ?>
					<div class="event-content mb-4">
						<?php
						try {
							$content = json_decode($event->getContentRaw(), true);
							if($content && isset($content['blocks'])) {
								foreach($content['blocks'] as $block) {
									switch($block['type']) {
										case 'header':
											$level = $block['data']['level'] ?? 2;
											echo "<h{$level}>" . htmlspecialchars($block['data']['text']) . "</h{$level}>";
											break;
										case 'paragraph':
											echo "<p>" . htmlspecialchars($block['data']['text']) . "</p>";
											break;
										case 'list':
											$tag = $block['data']['style'] === 'ordered' ? 'ol' : 'ul';
											echo "<{$tag}>";
											foreach($block['data']['items'] as $item) {
												echo "<li>" . htmlspecialchars($item) . "</li>";
											}
											echo "</{$tag}>";
											break;
										case 'quote':
											echo "<blockquote class='blockquote'>";
											echo "<p>" . htmlspecialchars($block['data']['text']) . "</p>";
											if(isset($block['data']['caption'])) {
												echo "<footer class='blockquote-footer'>" . htmlspecialchars($block['data']['caption']) . "</footer>";
											}
											echo "</blockquote>";
											break;
										case 'code':
											echo "<pre><code>" . htmlspecialchars($block['data']['code']) . "</code></pre>";
											break;
										case 'delimiter':
											echo "<hr class='my-4'>";
											break;
										case 'image':
											echo "<figure class='figure'>";
											echo "<img src='" . htmlspecialchars($block['data']['file']['url']) . "' class='figure-img img-fluid rounded' alt=''>";
											if(isset($block['data']['caption'])) {
												echo "<figcaption class='figure-caption'>" . htmlspecialchars($block['data']['caption']) . "</figcaption>";
											}
											echo "</figure>";
											break;
										case 'embed':
											echo "<div class='ratio ratio-16x9 mb-3'>";
											echo "<iframe src='" . htmlspecialchars($block['data']['embed']) . "' allowfullscreen></iframe>";
											echo "</div>";
											break;
									}
								}
							}
						} catch(\Exception $e) {
							// Fallback to raw display if parsing fails
							echo "<div class='alert alert-warning'>Content format error</div>";
						}
						?>
					</div>
				<?php endif; ?>

				<!-- Event Details Card -->
				<?php if($event->getOrganizer() || $event->getContactEmail() || $event->getContactPhone()): ?>
					<div class="card mb-4">
						<div class="card-header">
							<h5 class="mb-0">Event Details</h5>
						</div>
						<div class="card-body">
							<dl class="row mb-0">
								<?php if($event->getOrganizer()): ?>
									<dt class="col-sm-3">Organizer</dt>
									<dd class="col-sm-9"><?= htmlspecialchars($event->getOrganizer()) ?></dd>
								<?php endif; ?>

								<?php if($event->getContactEmail()): ?>
									<dt class="col-sm-3">Contact Email</dt>
									<dd class="col-sm-9">
										<a href="mailto:<?= htmlspecialchars($event->getContactEmail()) ?>">
											<?= htmlspecialchars($event->getContactEmail()) ?>
										</a>
									</dd>
								<?php endif; ?>

								<?php if($event->getContactPhone()): ?>
									<dt class="col-sm-3">Contact Phone</dt>
									<dd class="col-sm-9">
										<a href="tel:<?= htmlspecialchars($event->getContactPhone()) ?>">
											<?= htmlspecialchars($event->getContactPhone()) ?>
										</a>
									</dd>
								<?php endif; ?>
							</dl>
						</div>
					</div>
				<?php endif; ?>

				<!-- Back to Calendar Link -->
				<div class="text-center">
					<a href="<?= route_path('calendar') ?>" class="btn btn-outline-primary">
						<i class="bi bi-arrow-left"></i> Back to Calendar
					</a>
				</div>
			</article>
		</div>
	</div>
</div>
