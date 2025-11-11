<!DOCTYPE html>
<html lang="en">
<head>
	<meta charset="UTF-8">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<title><?= htmlspecialchars($Title ?? 'Admin') ?></title>
	<meta name="description" content="<?= htmlspecialchars($Description ?? '') ?>">

	<link href="https://cdn.jsdelivr.net/npm/bootswatch@5.3.7/dist/vapor/bootstrap.min.css" rel="stylesheet">
	<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
	<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.7/dist/js/bootstrap.bundle.min.js"></script>
	<style>
		.dropdown-menu {
			z-index: 9999 !important;
		}
	</style>
</head>
<body class="pt-5 bg-light">
	<nav class="navbar navbar-expand-lg navbar-dark bg-dark fixed-top">
		<div class="container-fluid">
			<a class="navbar-brand" href="/admin/dashboard">
				<?= htmlspecialchars($name ?? 'Neuron CMS') ?> Admin
			</a>
			<button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav" aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation">
				<span class="navbar-toggler-icon"></span>
			</button>
			<div class="collapse navbar-collapse" id="navbarNav">
				<ul class="navbar-nav me-auto">
					<li class="nav-item">
						<a class="nav-link" href="/admin/dashboard">Dashboard</a>
					</li>
					<li class="nav-item">
						<a class="nav-link" href="/">View Site</a>
					</li>
				</ul>
				<ul class="navbar-nav">
					<li class="nav-item dropdown">
						<button class="btn nav-link d-flex align-items-center border-0" type="button" id="userDropdown" data-bs-toggle="dropdown" aria-expanded="false">
							<img src="<?= gravatar_url($User->getEmail(), 32) ?>" class="rounded-circle" width="32" height="32" alt="<?= htmlspecialchars($User->getUsername()) ?>" onerror="this.onerror=null; this.src='data:image/svg+xml,<svg xmlns=%22http://www.w3.org/2000/svg%22 width=%2232%22 height=%2232%22 fill=%22%23ffffff%22 viewBox=%220 0 16 16%22><circle cx=%228%22 cy=%228%22 r=%228%22 fill=%22%236c757d%22/><path d=%22M8 8a3 3 0 1 0 0-6 3 3 0 0 0 0 6zm2-3a2 2 0 1 1-4 0 2 2 0 0 1 4 0zm4 8c0 1-1 1-1 1H3s-1 0-1-1 1-4 6-4 6 3 6 4zm-1-.004c-.001-.246-.154-.986-.832-1.664C11.516 10.68 10.289 10 8 10c-2.29 0-3.516.68-4.168 1.332-.678.678-.83 1.418-.832 1.664h10z%22 fill=%22%23ffffff%22/></svg>';">
						</button>
						<ul class="dropdown-menu dropdown-menu-end" aria-labelledby="userDropdown">
							<li><h6 class="dropdown-header"><?= htmlspecialchars($User->getUsername()) ?></h6></li>
							<li><hr class="dropdown-divider"></li>
							<li><a class="dropdown-item" href="/admin/profile"><i class="bi bi-person me-2"></i>Profile &amp; Settings</a></li>
							<li><hr class="dropdown-divider"></li>
							<li>
								<form method="POST" action="/logout" class="px-2">
									<?= csrf_field() ?>
									<button type="submit" class="dropdown-item"><i class="bi bi-box-arrow-right me-2"></i>Logout</button>
								</form>
							</li>
						</ul>
					</li>
				</ul>
			</div>
		</div>
	</nav>

	<div class="container mt-4">
		<?php if(isset($Success) && $Success): ?>
			<div class="alert alert-success alert-dismissible fade show" role="alert">
				<?= htmlspecialchars($Success) ?>
				<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
			</div>
		<?php endif; ?>

		<?php if(isset($Error) && $Error): ?>
			<div class="alert alert-danger alert-dismissible fade show" role="alert">
				<?= htmlspecialchars($Error) ?>
				<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
			</div>
		<?php endif; ?>

		<?= $Content ?? '' ?>
	</div>
</body>
</html>
