<!DOCTYPE html>
<html lang="en">
<head>
	<meta charset="UTF-8">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<title><?= htmlspecialchars($Title ?? 'Login') ?></title>
	<meta name="description" content="<?= htmlspecialchars($Description ?? '') ?>">
	<style>
		* { margin: 0; padding: 0; box-sizing: border-box; }
		body {
			font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
			background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
			min-height: 100vh;
			display: flex;
			align-items: center;
			justify-content: center;
			padding: 2rem;
		}
		.auth-container {
			width: 100%;
			max-width: 400px;
		}
		.auth-card {
			background: white;
			border-radius: 12px;
			padding: 2.5rem;
			box-shadow: 0 10px 40px rgba(0,0,0,0.2);
		}
		.auth-header {
			text-align: center;
			margin-bottom: 2rem;
		}
		.auth-header h1 {
			font-size: 1.75rem;
			color: #2c3e50;
			margin-bottom: 0.5rem;
		}
		.auth-header p {
			color: #7f8c8d;
			font-size: 0.9rem;
		}
		.alert {
			padding: 0.75rem;
			margin-bottom: 1rem;
			border-radius: 6px;
			font-size: 0.9rem;
		}
		.alert-success {
			background: #d4edda;
			border: 1px solid #c3e6cb;
			color: #155724;
		}
		.alert-error {
			background: #f8d7da;
			border: 1px solid #f5c6cb;
			color: #721c24;
		}
	</style>
</head>
<body>
	<div class="auth-container">
		<div class="auth-card">
			<div class="auth-header">
				<h1><?= htmlspecialchars($name ?? 'Neuron CMS') ?></h1>
				<p>Admin Login</p>
			</div>

			<?php if(isset($Success) && $Success): ?>
				<div class="alert alert-success"><?= htmlspecialchars($Success) ?></div>
			<?php endif; ?>

			<?php if(isset($Error) && $Error): ?>
				<div class="alert alert-error"><?= htmlspecialchars($Error) ?></div>
			<?php endif; ?>

			<?= $Content ?? '' ?>
		</div>
	</div>
</body>
</html>
