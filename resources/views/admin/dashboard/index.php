<div class="card mb-4">
	<div class="card-body bg-primary text-white rounded p-4 mb-4">
		<h2 class="h4 mb-2"><?= htmlspecialchars($WelcomeMessage) ?></h2>
		<p class="mb-0">You are logged in as <strong><?= htmlspecialchars($User->getRole()) ?></strong></p>
	</div>

	<div class="card-body">
		<h3 class="h5 mb-3">Quick Actions</h3>
		<div class="row g-3 mb-4">
			<div class="col-12 col-sm-6 col-lg-3">
				<a href="/" class="btn btn-outline-secondary w-100">View Site</a>
			</div>
			<div class="col-12 col-sm-6 col-lg-3">
				<a href="/admin/posts" class="btn btn-outline-secondary w-100">Manage Posts</a>
			</div>
			<div class="col-12 col-sm-6 col-lg-3">
				<a href="/admin/users" class="btn btn-outline-secondary w-100">Manage Users</a>
			</div>
			<div class="col-12 col-sm-6 col-lg-3">
				<a href="/admin/profile" class="btn btn-outline-secondary w-100">My Profile</a>
			</div>
		</div>

		<hr>

		<h3 class="h5 mb-3 mt-4">User Information</h3>
		<p><strong>Username:</strong> <?= htmlspecialchars($User->getUsername()) ?></p>
		<p><strong>Email:</strong> <?= htmlspecialchars($User->getEmail()) ?></p>
		<p><strong>Role:</strong> <?= htmlspecialchars($User->getRole()) ?></p>
		<p><strong>Account Status:</strong> <?= htmlspecialchars($User->getStatus()) ?></p>
		<?php if($User->getLastLoginAt()): ?>
			<p class="mb-0"><strong>Last Login:</strong> <?= $User->getLastLoginAt()->format('F j, Y g:i A') ?></p>
		<?php endif; ?>
	</div>
</div>
