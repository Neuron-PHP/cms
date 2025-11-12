<form method="POST" action="<?= route_path('login_post') ?>">
	<?= csrf_field() ?>
	<input type="hidden" name="redirect_url" value="<?= htmlspecialchars($RedirectUrl ?? route_path('admin_dashboard')) ?>">

	<div class="mb-3">
		<label for="username" class="form-label">Username</label>
		<input
			type="text"
			id="username"
			name="username"
			class="form-control"
			placeholder="Enter your username"
			required
			autofocus
		>
	</div>

	<div class="mb-3">
		<label for="password" class="form-label">Password</label>
		<input
			type="password"
			id="password"
			name="password"
			class="form-control"
			placeholder="Enter your password"
			required
		>
		<div class="text-end mt-1 mb-2">
			<a href="<?= route_path('forgot_password') ?>" class="small">Forgot password?</a>
		</div>
	</div>

	<div class="form-check mb-3">
		<input type="checkbox" id="remember" name="remember" value="1" class="form-check-input">
		<label for="remember" class="form-check-label">Remember me for 30 days</label>
	</div>

	<button type="submit" class="btn btn-primary w-100">
		Sign In
	</button>
</form>
