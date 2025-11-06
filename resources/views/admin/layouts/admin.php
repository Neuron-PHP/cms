<!DOCTYPE html>
<html lang="en">
<head>
	<meta charset="UTF-8">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<title><?= htmlspecialchars($Title ?? 'Admin') ?></title>
	<meta name="description" content="<?= htmlspecialchars($Description ?? '') ?>">
	<style>
		* { margin: 0; padding: 0; box-sizing: border-box; }
		body {
			font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
			background: #f5f5f5;
			color: #333;
		}
		.header {
			background: #2c3e50;
			color: white;
			padding: 1rem 2rem;
			display: flex;
			justify-content: space-between;
			align-items: center;
			box-shadow: 0 2px 4px rgba(0,0,0,0.1);
		}
		.header h1 { font-size: 1.5rem; font-weight: 600; }
		.header nav { display: flex; gap: 2rem; align-items: center; }
		.header nav a {
			color: white;
			text-decoration: none;
			padding: 0.5rem 1rem;
			border-radius: 4px;
			transition: background 0.2s;
		}
		.header nav a:hover { background: rgba(255,255,255,0.1); }
		.container {
			max-width: 1200px;
			margin: 2rem auto;
			padding: 0 2rem;
		}
		.alert {
			padding: 1rem;
			margin-bottom: 1rem;
			border-radius: 4px;
			border-left: 4px solid;
		}
		.alert-success {
			background: #d4edda;
			border-color: #28a745;
			color: #155724;
		}
		.alert-error {
			background: #f8d7da;
			border-color: #dc3545;
			color: #721c24;
		}
		.card {
			background: white;
			border-radius: 8px;
			padding: 2rem;
			box-shadow: 0 2px 4px rgba(0,0,0,0.1);
		}
	</style>
</head>
<body>
	<header class="header">
		<h1><?= htmlspecialchars($name ?? 'Neuron CMS') ?> Admin</h1>
		<nav>
			<a href="/admin/dashboard">Dashboard</a>
			<a href="/blog">View Site</a>
			<span style="color: #95a5a6;">|</span>
			<span><?= htmlspecialchars($User->getUsername() ?? 'User') ?></span>
			<form method="POST" action="/logout" style="display: inline;">
				<?= csrf_field() ?>
				<button type="submit" style="background: none; border: none; color: white; cursor: pointer; padding: 0.5rem 1rem; border-radius: 4px;">
					Logout
				</button>
			</form>
		</nav>
	</header>

	<div class="container">
		<?php if(isset($Success) && $Success): ?>
			<div class="alert alert-success"><?= htmlspecialchars($Success) ?></div>
		<?php endif; ?>

		<?php if(isset($Error) && $Error): ?>
			<div class="alert alert-error"><?= htmlspecialchars($Error) ?></div>
		<?php endif; ?>

		<?= $Content ?? '' ?>
	</div>
</body>
</html>
