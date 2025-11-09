<div class="text-center mb-3">
	<p class="text-muted small">
		Enter your email address and we'll send you a link to reset your password.
	</p>
</div>

<form method="POST" action="/forgot-password">
	<input type="hidden" name="csrf_token" value="<?= htmlspecialchars($CsrfToken) ?>">

	<div class="mb-3">
		<label for="email" class="form-label">Email Address</label>
		<input
			type="email"
			id="email"
			name="email"
			class="form-control"
			placeholder="Enter your email address"
			required
			autofocus
		>
	</div>

	<button type="submit" class="btn btn-primary w-100">
		Send Reset Link
	</button>
</form>

<div class="text-center mt-3">
	<a href="/login" class="small">← Back to Login</a>
</div>
