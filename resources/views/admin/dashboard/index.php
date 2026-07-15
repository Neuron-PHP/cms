<div class="card mb-4">
	<div class="card-body bg-primary text-white rounded p-4 mb-4">
		<h2 class="h4 mb-2">Dashboard</h2>
	</div>

	<div class="card-body">
		<h5 class="mb-3">Quick Access</h5>

		<h6 class="text-muted mt-4 mb-2">Content Management</h6>
		<div class="row g-3 mb-4">
			<div class="col-12 col-sm-6 col-lg-3">
				<a href="<?= route_path('admin_posts') ?>" class="btn btn-outline-primary w-100 py-3">
					<i class="bi bi-file-text d-block mb-2" style="font-size: 1.5rem;"></i>
					Posts
				</a>
			</div>
			<div class="col-12 col-sm-6 col-lg-3">
				<a href="<?= route_path('admin_pages') ?>" class="btn btn-outline-primary w-100 py-3">
					<i class="bi bi-file-earmark d-block mb-2" style="font-size: 1.5rem;"></i>
					Pages
				</a>
			</div>
			<div class="col-12 col-sm-6 col-lg-3">
				<a href="<?= route_path('admin_media') ?>" class="btn btn-outline-primary w-100 py-3">
					<i class="bi bi-images d-block mb-2" style="font-size: 1.5rem;"></i>
					Media
				</a>
			</div>
			<div class="col-12 col-sm-6 col-lg-3">
				<a href="<?= route_path('admin_events') ?>" class="btn btn-outline-primary w-100 py-3">
					<i class="bi bi-calendar-event d-block mb-2" style="font-size: 1.5rem;"></i>
					Events
				</a>
			</div>
		</div>

		<h6 class="text-muted mt-4 mb-2">Organization</h6>
		<div class="row g-3 mb-4">
			<div class="col-12 col-sm-6 col-lg-3">
				<a href="<?= route_path('admin_categories') ?>" class="btn btn-outline-secondary w-100 py-3">
					<i class="bi bi-folder d-block mb-2" style="font-size: 1.5rem;"></i>
					Categories
				</a>
			</div>
			<div class="col-12 col-sm-6 col-lg-3">
				<a href="<?= route_path('admin_tags') ?>" class="btn btn-outline-secondary w-100 py-3">
					<i class="bi bi-tags d-block mb-2" style="font-size: 1.5rem;"></i>
					Tags
				</a>
			</div>
			<div class="col-12 col-sm-6 col-lg-3">
				<a href="<?= route_path('admin_event_categories') ?>" class="btn btn-outline-secondary w-100 py-3">
					<i class="bi bi-calendar3 d-block mb-2" style="font-size: 1.5rem;"></i>
					Event Categories
				</a>
			</div>
		</div>

		<h6 class="text-muted mt-4 mb-2">Commerce</h6>
		<div class="row g-3 mb-4">
			<div class="col-12 col-sm-6 col-lg-3">
				<a href="<?= route_path('admin_products') ?>" class="btn btn-outline-success w-100 py-3">
					<i class="bi bi-box-seam d-block mb-2" style="font-size: 1.5rem;"></i>
					Products
				</a>
			</div>
			<div class="col-12 col-sm-6 col-lg-3">
				<a href="<?= route_path('admin_orders') ?>" class="btn btn-outline-success w-100 py-3">
					<i class="bi bi-bag-check d-block mb-2" style="font-size: 1.5rem;"></i>
					Orders
				</a>
			</div>
			<div class="col-12 col-sm-6 col-lg-3">
				<a href="<?= route_path('admin_payments') ?>" class="btn btn-outline-success w-100 py-3">
					<i class="bi bi-cash-coin d-block mb-2" style="font-size: 1.5rem;"></i>
					Payments
				</a>
			</div>
			<div class="col-12 col-sm-6 col-lg-3">
				<a href="<?= route_path('admin_subscriptions') ?>" class="btn btn-outline-success w-100 py-3">
					<i class="bi bi-arrow-repeat d-block mb-2" style="font-size: 1.5rem;"></i>
					Subscriptions
				</a>
			</div>
		</div>

		<h6 class="text-muted mt-4 mb-2">Users & Settings</h6>
		<div class="row g-3 mb-4">
			<div class="col-12 col-sm-6 col-lg-3">
				<a href="<?= route_path('admin_users') ?>" class="btn btn-outline-info w-100 py-3">
					<i class="bi bi-people d-block mb-2" style="font-size: 1.5rem;"></i>
					Users
				</a>
			</div>
			<div class="col-12 col-sm-6 col-lg-3">
				<a href="<?= route_path('admin_jobs') ?>" class="btn btn-outline-info w-100 py-3">
					<i class="bi bi-clock-history d-block mb-2" style="font-size: 1.5rem;"></i>
					Scheduled Jobs
				</a>
			</div>
			<div class="col-12 col-sm-6 col-lg-3">
				<a target="_blank" href="<?= route_path('home') ?>" class="btn btn-outline-success w-100 py-3">
					<i class="bi bi-eye d-block mb-2" style="font-size: 1.5rem;"></i>
					View Site
				</a>
			</div>
		</div>

		<hr>

		<div class="d-flex justify-content-between align-items-center mb-3">
			<h5 class="mb-0">Recent Contact Submissions</h5>
			<a href="<?= route_path('admin_contact_submissions') ?>" class="btn btn-sm btn-outline-primary">
				View All<?= !empty( $TotalSubmissions ) ? ' (' . (int) $TotalSubmissions . ')' : '' ?>
			</a>
		</div>

		<?php if( empty( $RecentSubmissions ) ): ?>
			<p class="text-muted mb-4">No contact submissions yet.</p>
		<?php else: ?>
			<div class="table-responsive mb-4">
				<table class="table table-hover align-middle mb-0">
					<thead>
						<tr>
							<th>Form</th>
							<th>From</th>
							<th>Delivered</th>
							<th>Received</th>
							<th width="60"></th>
						</tr>
					</thead>
					<tbody>
						<?php foreach( $RecentSubmissions as $s ): ?>
							<tr>
								<td><span class="badge bg-secondary"><?= htmlspecialchars( (string) ( $s['form_key'] ?? '' ) ) ?></span></td>
								<td>
									<?php if( !empty( $s['reply_to_email'] ) ): ?>
										<?= htmlspecialchars( trim( ( $s['reply_to_name'] ?? '' ) . ' <' . $s['reply_to_email'] . '>' ) ) ?>
									<?php else: ?>
										<span class="text-muted">-</span>
									<?php endif; ?>
								</td>
								<td>
									<?php if( !empty( $s['delivered'] ) ): ?>
										<span class="badge bg-success">Delivered</span>
									<?php else: ?>
										<span class="badge bg-warning text-dark">Pending</span>
									<?php endif; ?>
								</td>
								<td><?= htmlspecialchars( (string) ( $s['created_at'] ?? '' ) ) ?></td>
								<td>
									<a href="<?= route_path('admin_contact_submission_show', ['id' => $s['id']]) ?>" class="btn btn-sm btn-outline-primary" title="View">
										<i class="bi bi-eye"></i>
									</a>
								</td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			</div>
		<?php endif; ?>

		<hr>
		<?php if($User->getLastLoginAt()): ?>
			<p class="mb-0"><strong>Last Login:</strong> <?= format_user_datetime($User->getLastLoginAt(), 'F j, Y g:i A') ?></p>
		<?php endif; ?>
	</div>
</div>
