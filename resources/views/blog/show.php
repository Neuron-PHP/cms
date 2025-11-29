<div class="container">
	<?php if (!isset($Post)): ?>
		<p>Post not found</p>
	<?php else: ?>
	<article>
		<header class="mb-4">
			<h1><?= htmlspecialchars( $Post->getTitle() ) ?></h1>
			<p class="text-muted">
				<?php $Author = $Post->getAuthor(); ?>
				By <?= htmlspecialchars( $Author ? $Author->getUsername() : 'Unknown' ) ?>
				on <?= format_user_date( $Post->getCreatedAt(), 'F j, Y' ) ?>
				<?php if( $Post->getViewCount() > 0 ): ?>
					· <?= $Post->getViewCount() ?> views
				<?php endif; ?>
			</p>

			<?php if( !empty( $Post->getCategories() ) ): ?>
				<div class="mb-2">
					<?php foreach( $Post->getCategories() as $category ): ?>
						<a href="<?= route_path('blog_category', ['slug' => $category->getSlug()]) ?>" class="badge bg-primary text-decoration-none"><?= htmlspecialchars( $category->getName() ) ?></a>
					<?php endforeach; ?>
				</div>
			<?php endif; ?>

			<?php if( !empty( $Post->getTags() ) ): ?>
				<div class="mb-3">
					<?php foreach( $Post->getTags() as $tag ): ?>
						<a href="<?= route_path('blog_tag', ['slug' => $tag->getSlug()]) ?>" class="badge bg-secondary text-decoration-none"><?= htmlspecialchars( $tag->getName() ) ?></a>
					<?php endforeach; ?>
				</div>
			<?php endif; ?>
		</header>

		<?php if( $Post->getFeaturedImage() ): ?>
			<img src="<?= htmlspecialchars( $Post->getFeaturedImage() ) ?>" class="img-fluid mb-4" alt="<?= htmlspecialchars( $Post->getTitle() ) ?>">
		<?php endif; ?>

		<div class="post-content">
			<?= $renderedContent ?>
		</div>
	</article>

	<hr class="my-5">

	<div class="text-center">
		<a href="<?= route_path('blog') ?>" class="btn btn-secondary">← Back to Blog</a>
	</div>
	<?php endif; ?>
</div>

<style>
/* Post content styles */
.post-content {
	max-width: 800px;
	margin: 0 auto;
}

.post-content h2 {
	margin-top: 2rem;
	margin-bottom: 1rem;
	font-weight: 600;
}

.post-content h3 {
	margin-top: 1.5rem;
	margin-bottom: 0.75rem;
	font-weight: 600;
}

.post-content h4 {
	margin-top: 1.25rem;
	margin-bottom: 0.5rem;
	font-weight: 600;
}

.post-content p {
	margin-bottom: 1rem;
	line-height: 1.7;
}

.post-content ul,
.post-content ol {
	margin-bottom: 1rem;
	padding-left: 2rem;
}

.post-content li {
	margin-bottom: 0.5rem;
	line-height: 1.6;
}

.post-content blockquote {
	padding-left: 1.5rem;
	border-left: 4px solid #dee2e6;
	margin: 1.5rem 0;
	color: #6c757d;
}

.post-content img {
	max-width: 100%;
	height: auto;
	border-radius: 0.25rem;
}

.post-content figure {
	margin: 2rem 0;
}

.post-content figcaption {
	margin-top: 0.5rem;
	font-size: 0.875rem;
}

.post-content pre {
	background: #f8f9fa;
	padding: 1rem;
	border-radius: 0.25rem;
	overflow-x: auto;
}

.post-content code {
	background: #f8f9fa;
	padding: 0.2rem 0.4rem;
	border-radius: 0.25rem;
	font-size: 0.875em;
}

.post-content pre code {
	background: transparent;
	padding: 0;
}

.post-content hr {
	margin: 2rem 0;
	border: 0;
	border-top: 1px solid #dee2e6;
}

/* Widget styles */
.post-content .latest-posts-widget {
	margin: 2rem 0;
	padding: 1.5rem;
	background: #f8f9fa;
	border-radius: 0.5rem;
}

.post-content .contact-form-widget {
	margin: 2rem 0;
	padding: 1.5rem;
	background: #fff;
	border: 1px solid #dee2e6;
	border-radius: 0.5rem;
}

.post-content .map-widget {
	margin: 2rem 0;
	border-radius: 0.5rem;
	overflow: hidden;
}
</style>
