<div class="container-fluid">
	<h2 class="mb-4">My Profile</h2>

	<?php if( isset( $success ) && $success ): ?>
		<div class="alert alert-success"><?= htmlspecialchars( $success ) ?></div>
	<?php endif; ?>

	<?php if( isset( $error ) && $error ): ?>
		<div class="alert alert-danger"><?= htmlspecialchars( $error ) ?></div>
	<?php endif; ?>

	<div class="row mb-4">
		<div class="col-md-12">
			<div class="card">
				<div class="card-body text-center">
					<img src="<?= gravatar_url($User->getEmail(), 128) ?>" class="rounded-circle mb-3" width="128" height="128" alt="Profile Picture">
					<h5 class="card-title">Profile Picture</h5>
					<p class="card-text text-muted">
						Your profile picture is provided by <a href="https://gravatar.com" target="_blank" rel="noopener">Gravatar</a> and linked to your email address.
					</p>
					<a href="https://gravatar.com" target="_blank" rel="noopener" class="btn btn-sm btn-outline-primary">Change Avatar at Gravatar.com</a>
				</div>
			</div>
		</div>
	</div>

	<div class="row">
		<div class="col-md-6">
			<div class="card mb-4">
				<div class="card-header">
					<h5>Account Information</h5>
				</div>
				<div class="card-body">
					<form method="POST" action="<?= route_path('admin_profile_update') ?>">
						<input type="hidden" name="_method" value="PUT">
						<?= csrf_field() ?>

						<div class="mb-3">
							<label for="username" class="form-label">Username</label>
							<input type="text" class="form-control" id="username" value="<?= htmlspecialchars( $User->getUsername() ) ?>" disabled>
							<small class="form-text text-muted">Username cannot be changed</small>
						</div>

						<div class="mb-3">
							<label for="email" class="form-label">Email</label>
							<input type="email" class="form-control" id="email" name="email" value="<?= htmlspecialchars( $User->getEmail() ) ?>" required>
						</div>

						<div class="mb-3">
							<label for="timezone" class="form-label">Timezone</label>
							<select class="form-select" id="timezone" name="timezone">
								<?php
								$currentTimezone = $User->getTimezone();
								$grouped = [];

								// Group timezones by region
								$grouped['Other'] = [];
								foreach( $timezones as $timezone )
								{
									$parts = explode( '/', $timezone, 2 );
									if( count( $parts ) === 2 )
									{
										$region = $parts[0];
										if( !isset( $grouped[$region] ) )
										{
											$grouped[$region] = [];
										}
										$grouped[$region][] = $timezone;
									}
									else
									{
										$grouped['Other'][] = $timezone;
									}
								}

								// Display grouped timezones
								foreach( $grouped as $region => $tzList )
								{
									echo '<optgroup label="' . htmlspecialchars( $region ) . '">';
									foreach( $tzList as $timezone )
									{
										$selected = $timezone === $currentTimezone ? ' selected' : '';
										echo '<option value="' . htmlspecialchars( $timezone ) . '"' . $selected . '>' . htmlspecialchars( $timezone ) . '</option>';
									}
									echo '</optgroup>';
								}
								?>
							</select>
							<small class="form-text text-muted">All dates and times will be displayed in your selected timezone</small>
						</div>

						<div class="mb-3">
							<label class="form-label">Role</label>
							<input type="text" class="form-control" value="<?= htmlspecialchars( ucfirst( $User->getRole() ) ) ?>" disabled>
						</div>

						<button type="submit" class="btn btn-primary">Update Profile</button>
					</form>
				</div>
			</div>
		</div>

		<div class="col-md-6">
			<div class="card">
				<div class="card-header">
					<h5>Change Password</h5>
				</div>
				<div class="card-body">
					<form method="POST" action="<?= route_path('admin_profile_update') ?>">
						<input type="hidden" name="_method" value="PUT">
						<?= csrf_field() ?>

						<input type="hidden" name="email" value="<?= htmlspecialchars( $User->getEmail() ) ?>">

						<div class="mb-3">
							<label for="current_password" class="form-label">Current Password</label>
							<input type="password" class="form-control" id="current_password" name="current_password">
						</div>

						<div class="mb-3">
							<label for="new_password" class="form-label">New Password</label>
							<input type="password" class="form-control" id="new_password" name="new_password">
							<small class="form-text text-muted">
								Password requirements: min 8 chars, uppercase, lowercase, number, special character
							</small>
						</div>

						<div class="mb-3">
							<label for="confirm_password" class="form-label">Confirm New Password</label>
							<input type="password" class="form-control" id="confirm_password" name="confirm_password">
						</div>

						<button type="submit" class="btn btn-primary">Change Password</button>
					</form>
				</div>
			</div>
		</div>
	</div>
</div>
