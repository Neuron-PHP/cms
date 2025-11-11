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
				on <?= $Post->getCreatedAt() ? $Post->getCreatedAt()->format( 'F j, Y' ) : '' ?>
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
			<?= $renderedContent ?? htmlspecialchars( $Post->getBody() ) ?>
		</div>
	</article>

	<hr class="my-5">

	<div class="text-center">
		<a href="<?= route_path('blog') ?>" class="btn btn-secondary">← Back to Blog</a>
	</div>
	<?php endif; ?>
</div>
