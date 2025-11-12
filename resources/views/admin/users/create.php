<div class="container-fluid">
	<div class="d-flex justify-content-between align-items-center mb-4">
		<h2>Create New User</h2>
		<a href="<?= route_path('admin_users') ?>" class="btn btn-secondary">Back to Users</a>
	</div>

	<div class="card">
		<div class="card-body">
			<form method="POST" action="<?= route_path('admin_users_store') ?>">
				<?= csrf_field() ?>

				<div class="mb-3">
					<label for="username" class="form-label">Username</label>
					<input type="text" class="form-control" id="username" name="username" required>
				</div>

				<div class="mb-3">
					<label for="email" class="form-label">Email</label>
					<input type="email" class="form-control" id="email" name="email" required>
				</div>

				<div class="mb-3">
					<label for="password" class="form-label">Password</label>
					<input type="password" class="form-control" id="password" name="password" required>
					<small class="form-text text-muted">
						Password requirements: min 8 chars, uppercase, lowercase, number, special character
					</small>
				</div>

				<div class="mb-3">
					<label for="role" class="form-label">Role</label>
					<select class="form-select" id="role" name="role" required>
						<?php foreach( $roles as $role ): ?>
							<option value="<?= htmlspecialchars( $role ) ?>"><?= htmlspecialchars( ucfirst( $role ) ) ?></option>
						<?php endforeach; ?>
					</select>
				</div>

				<button type="submit" class="btn btn-primary">Create User</button>
				<a href="<?= route_path('admin_users') ?>" class="btn btn-secondary">Cancel</a>
			</form>
		</div>
	</div>
</div>
