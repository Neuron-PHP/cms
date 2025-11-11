<div class="text-center mb-3">
	<p class="text-muted small">
		Enter a new password for your account.
	</p>
</div>

<form method="POST" action="/reset-password">
	<input type="hidden" name="csrf_token" value="<?= htmlspecialchars($CsrfToken) ?>">
	<input type="hidden" name="token" value="<?= htmlspecialchars($Token ?? '') ?>">

	<div class="mb-3">
		<label for="email" class="form-label">Email Address</label>
		<input
			type="email"
			id="email"
			name="email"
			class="form-control"
			value="<?= htmlspecialchars($Email ?? '') ?>"
			disabled
			readonly
		>
	</div>

	<div class="mb-3">
		<label for="password" class="form-label">New Password</label>
		<input
			type="password"
			id="password"
			name="password"
			class="form-control"
			placeholder="Enter new password"
			required
			autofocus
		>
	</div>

	<div class="mb-3">
		<label for="password_confirmation" class="form-label">Confirm Password</label>
		<input
			type="password"
			id="password_confirmation"
			name="password_confirmation"
			class="form-control"
			placeholder="Confirm new password"
			required
		>
	</div>

	<div class="alert alert-info small mb-3">
		<strong>Password Requirements:</strong>
		<ul class="mb-0 mt-2">
			<li>At least 8 characters long</li>
			<li>Contains uppercase and lowercase letters</li>
			<li>Contains at least one number</li>
		</ul>
	</div>

	<button type="submit" class="btn btn-primary w-100">
		Reset Password
	</button>
</form>
