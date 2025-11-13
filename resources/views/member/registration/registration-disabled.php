<div class="row justify-content-center">
	<div class="col-md-6 col-lg-5">
		<div class="card shadow">
			<div class="card-body p-5 text-center">
				<div class="mb-4">
					<i class="bi bi-lock-fill text-warning" style="font-size: 4rem;"></i>
				</div>

				<h2 class="card-title mb-3">Registration Disabled</h2>

				<p class="text-muted mb-4">
					User registration is currently disabled. Please contact the administrator if you need access.
				</p>

				<div class="d-grid gap-2">
					<a href="<?= route_path('login') ?>" class="btn btn-primary">
						Go to Login
					</a>
					<a href="<?= route_path('home') ?>" class="btn btn-outline-secondary">
						Return to Homepage
					</a>
				</div>
			</div>
		</div>
	</div>
</div>
