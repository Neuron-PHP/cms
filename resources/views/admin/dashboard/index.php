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

		<h6 class="text-muted mt-4 mb-2">Users & Settings</h6>
		<div class="row g-3 mb-4">
			<div class="col-12 col-sm-6 col-lg-3">
				<a href="<?= route_path('admin_users') ?>" class="btn btn-outline-info w-100 py-3">
					<i class="bi bi-people d-block mb-2" style="font-size: 1.5rem;"></i>
					Users
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
		<?php if($User->getLastLoginAt()): ?>
			<p class="mb-0"><strong>Last Login:</strong> <?= format_user_datetime($User->getLastLoginAt(), 'F j, Y g:i A') ?></p>
		<?php endif; ?>
	</div>
</div>
