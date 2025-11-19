<div class="container py-5">
	<article class="page-content">
		<header class="page-header mb-5">
			<h1 class="display-4 mb-3"><?= htmlspecialchars($Page->getTitle()) ?></h1>

			<?php if($Page->getPublishedAt()): ?>
				<div class="text-muted mb-3">
					<small>
						<i class="bi bi-calendar3"></i>
						Published on <?= $Page->getPublishedAt()->format('F j, Y') ?>
					</small>
				</div>
			<?php endif; ?>

			<?php if($Page->getUpdatedAt()): ?>
				<div class="text-muted mb-3">
					<small>
						<i class="bi bi-clock"></i>
						Last updated <?= $Page->getUpdatedAt()->format('F j, Y') ?>
					</small>
				</div>
			<?php endif; ?>

			<hr class="my-4">
		</header>

		<div class="page-body">
			<?= $ContentHtml ?>
		</div>

		<footer class="page-footer mt-5 pt-4 border-top">
			<div class="row">
				<div class="col-md-6">
					<?php if($Page->getAuthor()): ?>
						<p class="text-muted small mb-0">
							<i class="bi bi-person"></i>
							By <?= htmlspecialchars($Page->getAuthor()->getUsername()) ?>
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
	</article>
</div>

<?php if($MetaKeywords): ?>
	<!-- SEO Keywords: <?= htmlspecialchars($MetaKeywords) ?> -->
<?php endif; ?>

<style>
/* Page-specific styles */
.page-content {
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
