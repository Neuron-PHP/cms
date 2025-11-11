<div class="container">
	<div class="mb-4">
		<h1><?= htmlspecialchars( $category->getName() ) ?></h1>
		<?php if( $category->getDescription() ): ?>
			<p class="lead"><?= htmlspecialchars( $category->getDescription() ) ?></p>
		<?php endif; ?>
		<p class="text-muted">
			<small><?= $category->getPostCount() ?? 0 ?> posts in this category</small>
		</p>
	</div>

	<?php if( isset( $posts ) && !empty( $posts ) ): ?>
		<div class="row">
			<?php foreach( $posts as $post ): ?>
				<div class="col-md-12 mb-4">
					<article class="card">
						<?php if( $post->getFeaturedImage() ): ?>
							<img src="<?= htmlspecialchars( $post->getFeaturedImage() ) ?>" class="card-img-top" alt="<?= htmlspecialchars( $post->getTitle() ) ?>">
						<?php endif; ?>
						<div class="card-body">
							<h2 class="card-title">
								<a href="/blog/post/<?= htmlspecialchars( $post->getSlug() ) ?>"><?= htmlspecialchars( $post->getTitle() ) ?></a>
							</h2>
							<p class="text-muted">
								<small>
									By <?= $post->getAuthor() ? htmlspecialchars( $post->getAuthor()->getUsername() ) : 'Unknown' ?>
									on <?= $post->getCreatedAt() ? $post->getCreatedAt()->format( 'F j, Y' ) : '' ?>
								</small>
							</p>
							<?php if( $post->getExcerpt() ): ?>
								<p class="card-text"><?= htmlspecialchars( $post->getExcerpt() ) ?></p>
							<?php endif; ?>
							<a href="/blog/post/<?= htmlspecialchars( $post->getSlug() ) ?>" class="btn btn-primary">Read More</a>
						</div>
					</article>
				</div>
			<?php endforeach; ?>
		</div>
	<?php else: ?>
		<div class="alert alert-info">
			<p>No posts found in this category.</p>
		</div>
	<?php endif; ?>

	<div class="mt-4">
		<a href="/blog" class="btn btn-secondary">← Back to Blog</a>
	</div>
</div>
