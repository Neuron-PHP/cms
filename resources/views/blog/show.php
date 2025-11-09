<div class="container">
	<article>
		<header class="mb-4">
			<h1><?= htmlspecialchars( $post->getTitle() ) ?></h1>
			<p class="text-muted">
				By <?= htmlspecialchars( $post->getAuthor() ) ?>
				on <?= $post->getCreatedAt() ? $post->getCreatedAt()->format( 'F j, Y' ) : '' ?>
				<?php if( $post->getViews() > 0 ): ?>
					· <?= $post->getViews() ?> views
				<?php endif; ?>
			</p>

			<?php if( !empty( $post->getCategories() ) ): ?>
				<div class="mb-2">
					<?php foreach( $post->getCategories() as $category ): ?>
						<a href="/blog/category/<?= htmlspecialchars( $category->getSlug() ) ?>" class="badge bg-primary text-decoration-none"><?= htmlspecialchars( $category->getName() ) ?></a>
					<?php endforeach; ?>
				</div>
			<?php endif; ?>

			<?php if( !empty( $post->getTags() ) ): ?>
				<div class="mb-3">
					<?php foreach( $post->getTags() as $tag ): ?>
						<a href="/blog/tag/<?= htmlspecialchars( $tag->getSlug() ) ?>" class="badge bg-secondary text-decoration-none"><?= htmlspecialchars( $tag->getName() ) ?></a>
					<?php endforeach; ?>
				</div>
			<?php endif; ?>
		</header>

		<?php if( $post->getFeaturedImage() ): ?>
			<img src="<?= htmlspecialchars( $post->getFeaturedImage() ) ?>" class="img-fluid mb-4" alt="<?= htmlspecialchars( $post->getTitle() ) ?>">
		<?php endif; ?>

		<div class="post-content">
			<?= $renderedContent ?? htmlspecialchars( $post->getContent() ) ?>
		</div>
	</article>

	<hr class="my-5">

	<div class="text-center">
		<a href="/blog" class="btn btn-secondary">← Back to Blog</a>
	</div>
</div>
