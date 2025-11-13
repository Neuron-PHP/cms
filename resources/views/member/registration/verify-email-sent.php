<div class="row justify-content-center">
	<div class="col-md-6 col-lg-5">
		<div class="card shadow">
			<div class="card-body p-5 text-center">
				<div class="mb-4">
					<i class="bi bi-envelope-check-fill text-success" style="font-size: 4rem;"></i>
				</div>

				<h2 class="card-title mb-3">Check Your Email</h2>

				<?php if( isset( $Success ) && $Success ): ?>
					<div class="alert alert-success text-start"><?= htmlspecialchars( $Success ) ?></div>
				<?php endif; ?>

				<p class="text-muted mb-4">
					We've sent a verification email to your address. Please click the link in the email to activate your account.
				</p>

				<p class="text-muted small mb-4">
					<strong>Didn't receive the email?</strong> Check your spam folder or request a new verification email below.
				</p>

				<form method="POST" action="<?= route_path('resend_verification') ?>" class="mb-3">
					<?= csrf_field() ?>
					<div class="input-group">
						<input
							type="email"
							name="email"
							class="form-control"
							placeholder="Enter your email"
							required
						>
						<button type="submit" class="btn btn-primary">
							Resend Email
						</button>
					</div>
				</form>

				<div class="text-center mt-4">
					<a href="<?= route_path('login') ?>" class="small">Return to Login</a>
				</div>
			</div>
		</div>
	</div>
</div>
