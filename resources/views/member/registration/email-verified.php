<div class="row justify-content-center">
	<div class="col-md-6 col-lg-5">
		<div class="card shadow">
			<div class="card-body p-5 text-center">
				<?php if( isset( $Success ) && $Success ): ?>
					<div class="mb-4">
						<i class="bi bi-check-circle-fill text-success" style="font-size: 4rem;"></i>
					</div>

					<h2 class="card-title mb-3">Email Verified!</h2>

					<p class="text-muted mb-4">
						<?= htmlspecialchars( $Message ?? 'Your email has been verified successfully!' ) ?>
					</p>

					<a href="<?= route_path('login') ?>" class="btn btn-primary w-100">
						Continue to Login
					</a>
				<?php else: ?>
					<div class="mb-4">
						<i class="bi bi-x-circle-fill text-danger" style="font-size: 4rem;"></i>
					</div>

					<h2 class="card-title mb-3">Verification Failed</h2>

					<p class="text-muted mb-4">
						<?= htmlspecialchars( $Message ?? 'This verification link is invalid or has expired.' ) ?>
					</p>

					<div class="d-grid gap-2">
						<a href="<?= route_path('verify_email_sent') ?>" class="btn btn-primary">
							Request New Verification Email
						</a>
						<a href="<?= route_path('register') ?>" class="btn btn-outline-secondary">
							Back to Registration
						</a>
					</div>
				<?php endif; ?>
			</div>
		</div>
	</div>
</div>
