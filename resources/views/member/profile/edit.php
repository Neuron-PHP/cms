<div class="container-fluid">
	<div class="row">
		<div class="col-lg-8">
			<h1 class="mb-4">Edit Profile</h1>

			<?php if( isset( $success ) && $success ): ?>
				<div class="alert alert-success alert-dismissible fade show">
					<?= htmlspecialchars( $success ) ?>
					<button type="button" class="btn-close" data-bs-dismiss="alert"></button>
				</div>
			<?php endif; ?>

			<?php if( isset( $error ) && $error ): ?>
				<div class="alert alert-danger alert-dismissible fade show">
					<?= htmlspecialchars( $error ) ?>
					<button type="button" class="btn-close" data-bs-dismiss="alert"></button>
				</div>
			<?php endif; ?>

			<div class="card">
				<div class="card-body">
					<form method="POST" action="<?= route_path('member_profile_update') ?>">
						<?= csrf_field() ?>
						<input type="hidden" name="_method" value="PUT">

						<div class="mb-3">
							<label for="username" class="form-label">Username</label>
							<input
								type="text"
								id="username"
								class="form-control"
								value="<?= htmlspecialchars( $User->getUsername() ) ?>"
								disabled
							>
							<small class="form-text text-muted">Username cannot be changed</small>
						</div>

						<div class="mb-3">
							<label for="email" class="form-label">Email Address</label>
							<input
								type="email"
								id="email"
								name="email"
								class="form-control"
								value="<?= htmlspecialchars( $User->getEmail() ) ?>"
								required
							>
						</div>

						<div class="mb-3">
							<label for="timezone" class="form-label">Timezone</label>
							<select id="timezone" name="timezone" class="form-select">
								<?php foreach( $timezones as $tz ): ?>
									<option
										value="<?= htmlspecialchars( $tz ) ?>"
										<?= $tz === $User->getTimezone() ? 'selected' : '' ?>
									>
										<?= htmlspecialchars( $tz ) ?>
									</option>
								<?php endforeach; ?>
							</select>
						</div>

						<hr class="my-4">

						<h5 class="mb-3">Change Password</h5>
						<p class="text-muted small">Leave these fields blank if you don't want to change your password.</p>

						<div class="mb-3">
							<label for="current_password" class="form-label">Current Password</label>
							<input
								type="password"
								id="current_password"
								name="current_password"
								class="form-control"
								autocomplete="current-password"
							>
						</div>

						<div class="mb-3">
							<label for="new_password" class="form-label">New Password</label>
							<input
								type="password"
								id="new_password"
								name="new_password"
								class="form-control"
								minlength="8"
								autocomplete="new-password"
							>
							<small class="form-text text-muted">Minimum 8 characters</small>
						</div>

						<div class="mb-4">
							<label for="confirm_password" class="form-label">Confirm New Password</label>
							<input
								type="password"
								id="confirm_password"
								name="confirm_password"
								class="form-control"
								minlength="8"
								autocomplete="new-password"
							>
						</div>

						<div class="d-flex justify-content-between">
							<a href="<?= route_path('member_dashboard') ?>" class="btn btn-secondary">
								Cancel
							</a>
							<button type="submit" class="btn btn-primary">
								Save Changes
							</button>
						</div>
					</form>
				</div>
			</div>
		</div>
	</div>
</div>
