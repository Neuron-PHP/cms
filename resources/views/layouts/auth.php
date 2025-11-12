<!DOCTYPE html>
<html lang="en">
<head>
	<meta charset="UTF-8">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<title><?= htmlspecialchars($Title ?? 'Login') ?></title>
	<meta name="description" content="<?= htmlspecialchars($Description ?? '') ?>">

	<link href="https://cdn.jsdelivr.net/npm/bootswatch@5.3.7/dist/sandstone/bootstrap.min.css" rel="stylesheet">
	<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.7/dist/js/bootstrap.bundle.min.js"></script>
</head>
<body class="bg-primary min-vh-100 d-flex align-items-center justify-content-center p-4">
	<div class="container">
		<div class="row justify-content-center">
			<div class="col-12 col-sm-10 col-md-8 col-lg-6 col-xl-4">
				<div class="card shadow-lg p-4">
					<div class="text-center mb-4">
			<h1 class="h3 mb-2"><?= htmlspecialchars($name ?? 'Neuron CMS') ?></h1>
			<p class="text-muted"><?= htmlspecialchars($PageSubtitle ?? 'Admin Login') ?></p>
		</div>

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

					<?= $content ?? '' ?>
				</div>
			</div>
		</div>
	</div>
</body>
</html>
