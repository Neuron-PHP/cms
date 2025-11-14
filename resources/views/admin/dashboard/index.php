<div class="card mb-4">
	<div class="card-body bg-primary text-white rounded p-4 mb-4">
		<h2 class="h4 mb-2">Dashboard</h2>
	</div>

	<div class="card-body">
		<div class="row g-3 mb-4">
			<div class="col-12 col-sm-6 col-lg-3">
				<a href="<?= route_path('admin_posts') ?>" class="btn btn-outline-secondary w-100">Manage Posts</a>
			</div>
			<div class="col-12 col-sm-6 col-lg-3">
				<a href="<?= route_path('admin_users') ?>" class="btn btn-outline-secondary w-100">Manage Users</a>
			</div>
			<div class="col-12 col-sm-6 col-lg-3">
				<a target="_blank" href="<?= route_path('home') ?>" class="btn btn-outline-secondary w-100">View Site</a>
			</div>
		</div>

		<hr>
		<?php if($User->getLastLoginAt()): ?>
			<p class="mb-0"><strong>Last Login:</strong> <?= format_user_datetime($User->getLastLoginAt(), 'F j, Y g:i A') ?></p>
		<?php endif; ?>
	</div>
</div>
