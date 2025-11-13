<div class="row justify-content-center">
	<div class="col-md-6 col-lg-5">
		<div class="card shadow">
			<div class="card-body p-5">
				<h2 class="card-title text-center mb-4">Create Account</h2>

				<?php if( isset( $Error ) && $Error ): ?>
					<div class="alert alert-danger"><?= htmlspecialchars( $Error ) ?></div>
				<?php endif; ?>

				<?php if( isset( $Success ) && $Success ): ?>
					<div class="alert alert-success"><?= htmlspecialchars( $Success ) ?></div>
				<?php endif; ?>

				<form method="POST" action="<?= route_path('register_post') ?>">
					<?= csrf_field() ?>

					<div class="mb-3">
						<label for="username" class="form-label">Username</label>
						<input
							type="text"
							id="username"
							name="username"
							class="form-control"
							placeholder="Choose a username"
							required
							autofocus
							pattern="[a-zA-Z0-9_]+"
							minlength="3"
							maxlength="50"
						>
						<small class="form-text text-muted">3-50 characters, letters, numbers, and underscores only</small>
					</div>

					<div class="mb-3">
						<label for="email" class="form-label">Email Address</label>
						<input
							type="email"
							id="email"
							name="email"
							class="form-control"
							placeholder="your.email@example.com"
							required
						>
					</div>

					<div class="mb-3">
						<label for="password" class="form-label">Password</label>
						<input
							type="password"
							id="password"
							name="password"
							class="form-control"
							placeholder="Choose a strong password"
							required
							minlength="8"
						>
						<small class="form-text text-muted">Minimum 8 characters, include uppercase, lowercase, and numbers</small>
					</div>

					<div class="mb-4">
						<label for="password_confirmation" class="form-label">Confirm Password</label>
						<input
							type="password"
							id="password_confirmation"
							name="password_confirmation"
							class="form-control"
							placeholder="Re-enter your password"
							required
							minlength="8"
						>
					</div>

					<button type="submit" class="btn btn-primary w-100 mb-3">
						Create Account
					</button>

					<div class="text-center">
						<small>Already have an account? <a href="<?= route_path('login') ?>">Sign in</a></small>
					</div>
				</form>
			</div>
		</div>
	</div>
</div>
