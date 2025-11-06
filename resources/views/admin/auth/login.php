<style>
	.form-group {
		margin-bottom: 1.25rem;
	}
	.form-label {
		display: block;
		margin-bottom: 0.5rem;
		color: #2c3e50;
		font-weight: 500;
		font-size: 0.9rem;
	}
	.form-input {
		width: 100%;
		padding: 0.75rem;
		border: 1px solid #ddd;
		border-radius: 6px;
		font-size: 1rem;
		transition: border-color 0.2s;
	}
	.form-input:focus {
		outline: none;
		border-color: #667eea;
		box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
	}
	.form-checkbox {
		display: flex;
		align-items: center;
		gap: 0.5rem;
		margin-bottom: 1.5rem;
	}
	.form-checkbox input {
		width: 1rem;
		height: 1rem;
	}
	.form-checkbox label {
		font-size: 0.9rem;
		color: #555;
	}
	.btn {
		width: 100%;
		padding: 0.875rem;
		background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
		color: white;
		border: none;
		border-radius: 6px;
		font-size: 1rem;
		font-weight: 600;
		cursor: pointer;
		transition: transform 0.2s, box-shadow 0.2s;
	}
	.btn:hover {
		transform: translateY(-2px);
		box-shadow: 0 4px 12px rgba(102, 126, 234, 0.4);
	}
	.btn:active {
		transform: translateY(0);
	}
</style>

<form method="POST" action="/login">
	<input type="hidden" name="csrf_token" value="<?= htmlspecialchars($CsrfToken) ?>">
	<input type="hidden" name="redirect_url" value="<?= htmlspecialchars($RedirectUrl ?? '/admin/dashboard') ?>">

	<div class="form-group">
		<label for="username" class="form-label">Username</label>
		<input
			type="text"
			id="username"
			name="username"
			class="form-input"
			placeholder="Enter your username"
			required
			autofocus
		>
	</div>

	<div class="form-group">
		<label for="password" class="form-label">Password</label>
		<input
			type="password"
			id="password"
			name="password"
			class="form-input"
			placeholder="Enter your password"
			required
		>
	</div>

	<div class="form-checkbox">
		<input type="checkbox" id="remember" name="remember" value="1">
		<label for="remember">Remember me for 30 days</label>
	</div>

	<button type="submit" class="btn">
		Sign In
	</button>
</form>
