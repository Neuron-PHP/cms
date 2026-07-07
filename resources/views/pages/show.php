<?php
/**
 * Public page view.
 *
 * Template-aware: the page's template controls the content container and chrome.
 *  - default     : centered, narrow article (max 800px)
 *  - full-width  : edge-to-edge content, no width cap
 *  - sidebar     : content column with a page-info sidebar
 *  - landing     : full-width content only (no header meta or footer)
 *
 * Falls back to the page model's template when no Template var is provided
 * (keeps the view self-contained regardless of the calling controller).
 *
 * $ShowDates (pages.show_dates setting) controls whether the published/updated
 * dates are rendered; defaults to true when the controller doesn't provide it.
 */
$template = $Template ?? ( isset( $Page ) ? $Page->getTemplate() : 'default' );
$showDates = $ShowDates ?? true;

$isLanding   = ( $template === 'landing' );
$isFullWidth = ( $template === 'full-width' );
$isSidebar   = ( $template === 'sidebar' );

$containerClass = ( $isFullWidth || $isLanding ) ? 'container-fluid px-lg-5' : 'container';
$contentColClass = $isSidebar ? 'col-lg-8' : 'col-12';
?>
<div class="<?= $containerClass ?> py-5 page-template-<?= htmlspecialchars( $template ) ?>">
	<div class="row gx-5">
		<div class="<?= $contentColClass ?>">
			<article class="page-content">
				<?php if( !$isLanding ): ?>
					<header class="page-header mb-5">
						<h1 class="display-4 mb-3"><?= htmlspecialchars( $Page->getTitle() ) ?></h1>

						<?php if( $showDates && $Page->getPublishedAt() ): ?>
							<div class="text-muted mb-3">
								<small>
									<i class="bi bi-calendar3"></i>
									Published on <?= $Page->getPublishedAt()->format( 'F j, Y' ) ?>
								</small>
							</div>
						<?php endif; ?>

						<?php if( $showDates && $Page->getUpdatedAt() ): ?>
							<div class="text-muted mb-3">
								<small>
									<i class="bi bi-clock"></i>
									Last updated <?= $Page->getUpdatedAt()->format( 'F j, Y' ) ?>
								</small>
							</div>
						<?php endif; ?>

						<hr class="my-4">
					</header>
				<?php endif; ?>

				<div class="page-body">
					<?= $ContentHtml ?>
				</div>

				<?php if( !$isLanding && !$isSidebar ): ?>
					<footer class="page-footer mt-5 pt-4 border-top">
						<div class="row">
							<div class="col-md-6">
								<?php if( $Page->getAuthor() ): ?>
									<p class="text-muted small mb-0">
										<i class="bi bi-person"></i>
										By <?= htmlspecialchars( $Page->getAuthor()->getUsername() ) ?>
									</p>
								<?php endif; ?>
							</div>
							<div class="col-md-6 text-md-end">
								<p class="text-muted small mb-0">
									<i class="bi bi-eye"></i>
									<?= $Page->getViewCount() ?> <?= $Page->getViewCount() === 1 ? 'view' : 'views' ?>
								</p>
							</div>
						</div>
					</footer>
				<?php endif; ?>
			</article>
		</div>

		<?php if( $isSidebar ): ?>
			<aside class="col-lg-4">
				<div class="card border-0 shadow-sm">
					<div class="card-body">
						<h2 class="h6 text-uppercase text-muted mb-3">Page Info</h2>
						<ul class="list-unstyled small mb-0">
							<?php if( $showDates && $Page->getPublishedAt() ): ?>
								<li class="mb-2">
									<i class="bi bi-calendar3 me-1"></i>
									Published <?= $Page->getPublishedAt()->format( 'F j, Y' ) ?>
								</li>
							<?php endif; ?>
							<?php if( $showDates && $Page->getUpdatedAt() ): ?>
								<li class="mb-2">
									<i class="bi bi-clock me-1"></i>
									Updated <?= $Page->getUpdatedAt()->format( 'F j, Y' ) ?>
								</li>
							<?php endif; ?>
							<?php if( $Page->getAuthor() ): ?>
								<li class="mb-2">
									<i class="bi bi-person me-1"></i>
									By <?= htmlspecialchars( $Page->getAuthor()->getUsername() ) ?>
								</li>
							<?php endif; ?>
							<li class="mb-0">
								<i class="bi bi-eye me-1"></i>
								<?= $Page->getViewCount() ?> <?= $Page->getViewCount() === 1 ? 'view' : 'views' ?>
							</li>
						</ul>
					</div>
				</div>
			</aside>
		<?php endif; ?>
	</div>
</div>

<?php if( $MetaKeywords ): ?>
	<!-- SEO Keywords: <?= htmlspecialchars( $MetaKeywords ) ?> -->
<?php endif; ?>

<style>
/* Page-specific styles */

/* Default template keeps a readable, centered measure. */
.page-template-default .page-content {
	max-width: 800px;
	margin: 0 auto;
}

.page-content h2 {
	margin-top: 2rem;
	margin-bottom: 1rem;
	font-weight: 600;
}

.page-content h3 {
	margin-top: 1.5rem;
	margin-bottom: 0.75rem;
	font-weight: 600;
}

.page-content h4 {
	margin-top: 1.25rem;
	margin-bottom: 0.5rem;
	font-weight: 600;
}

.page-content p {
	margin-bottom: 1rem;
	line-height: 1.7;
}

.page-content ul,
.page-content ol {
	margin-bottom: 1rem;
	padding-left: 2rem;
}

.page-content li {
	margin-bottom: 0.5rem;
	line-height: 1.6;
}

.page-content blockquote {
	padding-left: 1.5rem;
	border-left: 4px solid #dee2e6;
	margin: 1.5rem 0;
	color: #6c757d;
}

.page-content img {
	max-width: 100%;
	height: auto;
	border-radius: 0.25rem;
}

.page-content figure {
	margin: 2rem 0;
}

.page-content figcaption {
	margin-top: 0.5rem;
	font-size: 0.875rem;
}

.page-content pre {
	background: #f8f9fa;
	padding: 1rem;
	border-radius: 0.25rem;
	overflow-x: auto;
}

.page-content code {
	background: #f8f9fa;
	padding: 0.2rem 0.4rem;
	border-radius: 0.25rem;
	font-size: 0.875em;
}

.page-content pre code {
	background: transparent;
	padding: 0;
}

.page-content hr {
	margin: 2rem 0;
	border: 0;
	border-top: 1px solid #dee2e6;
}

/* Widget styles */
.page-content .latest-posts-widget {
	margin: 2rem 0;
	padding: 1.5rem;
	background: #f8f9fa;
	border-radius: 0.5rem;
}

.page-content .contact-form-widget {
	margin: 2rem 0;
	padding: 1.5rem;
	background: #fff;
	border: 1px solid #dee2e6;
	border-radius: 0.5rem;
}

.page-content .map-widget {
	margin: 2rem 0;
	border-radius: 0.5rem;
	overflow: hidden;
}
</style>
