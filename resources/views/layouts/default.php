<!DOCTYPE html>
<html lang="en">
<head>
	<meta charset="utf-8">
	<meta http-equiv="X-UA-Compatible" content="IE=edge,chrome=1">
	<meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no">
	<link rel="icon" type="image/png" sizes="32x32" href="/favicon.png">

	<?php
	$pageTitle = $Title ?? ( $route ?? '' );
	$pageDescription = $Description ?? '';
	$canonical = $CanonicalUrl ?? '';
	$ogTitle = $OgTitle ?? $pageTitle;
	$ogDescription = $OgDescription ?? $pageDescription;
	$ogImage = $OgImage ?? '';
	$ogType = $OgType ?? 'website';
	$ogUrl = $OgUrl ?? $canonical;
	$keywords = $MetaKeywords ?? '';
	?>

	<title><?= htmlspecialchars( $pageTitle ) ?></title>
	<meta name="description" content="<?= htmlspecialchars( $pageDescription ) ?>">
	<?php if( $keywords !== '' ): ?>
		<meta name="keywords" content="<?= htmlspecialchars( $keywords ) ?>">
	<?php endif; ?>
	<?php if( $canonical !== '' ): ?>
		<link rel="canonical" href="<?= htmlspecialchars( $canonical ) ?>" />
	<?php endif; ?>
	<link rel="alternate" type="application/rss+xml" title="RSS Feed" href="<?= route_path('rss_feed') ?>">
	<meta name="application-name" content="<?= htmlspecialchars( $siteName ?? '' ) ?>">

	<?php if( $ogTitle !== '' ): ?>
		<meta property="og:title" content="<?= htmlspecialchars( $ogTitle ) ?>">
		<meta name="twitter:title" content="<?= htmlspecialchars( $ogTitle ) ?>">
	<?php endif; ?>
	<?php if( $ogDescription !== '' ): ?>
		<meta property="og:description" content="<?= htmlspecialchars( $ogDescription ) ?>">
		<meta name="twitter:description" content="<?= htmlspecialchars( $ogDescription ) ?>">
	<?php endif; ?>
	<?php if( $ogUrl !== '' ): ?>
		<meta property="og:url" content="<?= htmlspecialchars( $ogUrl ) ?>">
	<?php endif; ?>
	<meta property="og:type" content="<?= htmlspecialchars( $ogType ) ?>">
	<meta property="og:site_name" content="<?= htmlspecialchars( $siteName ?? '' ) ?>">
	<?php if( $ogImage !== '' ): ?>
		<meta property="og:image" content="<?= htmlspecialchars( $ogImage ) ?>">
		<meta name="twitter:card" content="summary_large_image">
		<meta name="twitter:image" content="<?= htmlspecialchars( $ogImage ) ?>">
	<?php else: ?>
		<meta name="twitter:card" content="summary">
	<?php endif; ?>
	<?php
	$showBreadcrumbs = !empty( $Breadcrumbs ) && ( $Template ?? '' ) !== 'landing';
	if( $showBreadcrumbs )
	{
		echo cms_breadcrumb_json_ld( $Breadcrumbs, $canonical );
	}
	?>

	<link href="https://cdn.jsdelivr.net/npm/bootswatch@5.3.7/dist/<?= htmlspecialchars($theme) ?>/bootstrap.min.css" rel="stylesheet">
	<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
	<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.7/dist/js/bootstrap.min.js"></script>
	<script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.11.6/dist/umd/popper.min.js"></script>
</head>
<body class="pb-5">
<nav class="navbar navbar-expand-lg navbar-dark bg-primary fixed-top" role="navigation">
	<div class="container">
		<a class="navbar-brand d-flex align-items-center" href="<?= route_path('home') ?: '/' ?>">
			<img src="/icon.png" alt="Logo" height="28" class="me-2" />
			<span><?= htmlspecialchars($siteName) ?></span>
		</a>
		<button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav" aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation">
			<span class="navbar-toggler-icon"></span>
		</button>
		<?php include dirname( __DIR__ ) . '/partials/navigation.php'; ?>
	</div>
</nav>
<div class="container pt-5 mt-4">
	<?php if( !empty( $showBreadcrumbs ) ): ?>
		<?= cms_breadcrumbs( $Breadcrumbs ) ?>
	<?php endif; ?>
	<?= $content ?>
	<hr>
	<footer class="footer" role="contentinfo">
		<div class="text-center small mb-2">
			<?php include dirname( __DIR__ ) . '/partials/footer-navigation.php'; ?>
		</div>
		<div class="text-center small mb-2">
			<a href="<?= route_path('rss_feed') ?>" class="text-decoration-none" title="Subscribe to RSS Feed">
				<i class="bi bi-rss-fill text-warning"></i> Subscribe via RSS
			</a>
		</div>
		<?php if( $appVersion ): ?>
		<div class="text-center small">
			<?= htmlspecialchars($appVersion) ?>
		</div>
		<?php endif; ?>
		<div class="text-center small">
			Powered by <a href="https://neuronphp.com" target="_blank">NeuronCMS</a>.
		</div>
	</footer>
</div>
</body>
</html>
