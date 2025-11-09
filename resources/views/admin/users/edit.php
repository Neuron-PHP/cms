<div class="container-fluid">
	<div class="d-flex justify-content-between align-items-center mb-4">
		<h2>Edit User: <?= htmlspecialchars( $user->getUsername() ) ?></h2>
		<a href="/admin/users" class="btn btn-secondary">Back to Users</a>
	</div>

	<div class="card">
		<div class="card-body">
			<form method="POST" action="/admin/users/<?= $user->getId() ?>">
				<input type="hidden" name="_method" value="PUT">
				<?= csrf_field() ?>

				<div class="mb-3">
					<label for="username" class="form-label">Username</label>
					<input type="text" class="form-control" id="username" value="<?= htmlspecialchars( $user->getUsername() ) ?>" disabled>
					<small class="form-text text-muted">Username cannot be changed</small>
				</div>

				<div class="mb-3">
					<label for="email" class="form-label">Email</label>
					<input type="email" class="form-control" id="email" name="email" value="<?= htmlspecialchars( $user->getEmail() ) ?>" required>
				</div>

				<div class="mb-3">
					<label for="password" class="form-label">New Password (leave blank to keep current)</label>
					<input type="password" class="form-control" id="password" name="password">
					<small class="form-text text-muted">
						Password requirements: min 8 chars, uppercase, lowercase, number, special character
					</small>
				</div>

				<div class="mb-3">
					<label for="role" class="form-label">Role</label>
					<select class="form-select" id="role" name="role" required>
						<?php foreach( $roles as $role ): ?>
							<option value="<?= htmlspecialchars( $role ) ?>" <?= $user->getRole() === $role ? 'selected' : '' ?>><?= htmlspecialchars( ucfirst( $role ) ) ?></option>
						<?php endforeach; ?>
					</select>
				</div>

				<button type="submit" class="btn btn-primary">Update User</button>
				<a href="/admin/users" class="btn btn-secondary">Cancel</a>
			</form>
		</div>
	</div>
</div>
