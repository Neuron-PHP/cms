<?php
use Neuron\Patterns\Registry;
?>
<html lang="en">
<head>
	<meta charset="utf-8">
	<meta http-equiv="X-UA-Compatible" content="IE=edge,chrome=1">
	<meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no">
	<link rel="icon" type="image/png" sizes="32x32" href="/favicon.png">

	<title><?= isset( $Title ) ? $Title : $route ?></title>
	<meta name="description" content="<?= isset( $Description ) ? $Description : '' ?>">
	<?php
	if( isset( $CanonicalUrl ) && $CanonicalUrl != "" )
	{
		?>
		<link rel="canonical" href="<?= $CanonicalUrl ?>" />
		<?php
	}
	?>
	<link rel="alternate" type="application/rss+xml" title="RSS Feed" href="<?= route_path('rss') ?>">
	<meta name="application-name" content="">

	<link href="https://cdn.jsdelivr.net/npm/bootswatch@5.3.7/dist/sandstone/bootstrap.min.css" rel="stylesheet">
	<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
	<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.7/dist/js/bootstrap.min.js"></script>
	<script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.11.6/dist/umd/popper.min.js"></script>
</head>
<body class="pb-5">
<nav class="navbar navbar-expand-lg navbar-dark bg-primary fixed-top" role="navigation">
	<div class="container">
		<a class="navbar-brand d-flex align-items-center" href="<?= route_path('home') ?>">
			<img src="/icon.png" alt="Logo" height="28" class="me-2" />
			<span><?= Registry::getInstance()->get( 'name' ) ?></span>
		</a>
		<button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav" aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation">
			<span class="navbar-toggler-icon"></span>
		</button>
	</div>
</nav>
<div class="container pt-5 mt-4">
	<?= $content ?>
	<hr>
	<footer class="footer" role="presentation">
		<div class="text-center small mb-2">
			<a href="<?= route_path('rss') ?>" class="text-decoration-none" title="Subscribe to RSS Feed">
				<i class="bi bi-rss-fill text-warning"></i> Subscribe via RSS
			</a>
		</div>
		<div class="text-center small">
			<?= Registry::getInstance()->get( 'version' ) ?>
		</div>
		<div class="text-center small">
			Powered by <a href="https://neuronphp.com" target="_blank">NeuronPHP</a>.
		</div>
	</footer>
</div>
</body>
</html>
