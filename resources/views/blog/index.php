<div class="container">
	<h1><?= htmlspecialchars( $Title ?? 'Blog' ) ?></h1>

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
									By <?= htmlspecialchars( $post->getAuthor() ) ?>
									on <?= $post->getCreatedAt() ? $post->getCreatedAt()->format( 'F j, Y' ) : '' ?>
									<?php if( $post->getViews() > 0 ): ?>
										· <?= $post->getViews() ?> views
									<?php endif; ?>
								</small>
							</p>
							<?php if( $post->getExcerpt() ): ?>
								<p class="card-text"><?= htmlspecialchars( $post->getExcerpt() ) ?></p>
							<?php endif; ?>

							<?php if( !empty( $post->getCategories() ) ): ?>
								<div class="mb-2">
									<?php foreach( $post->getCategories() as $category ): ?>
										<a href="/blog/category/<?= htmlspecialchars( $category->getSlug() ) ?>" class="badge bg-primary text-decoration-none"><?= htmlspecialchars( $category->getName() ) ?></a>
									<?php endforeach; ?>
								</div>
							<?php endif; ?>

							<?php if( !empty( $post->getTags() ) ): ?>
								<div>
									<?php foreach( $post->getTags() as $tag ): ?>
										<a href="/blog/tag/<?= htmlspecialchars( $tag->getSlug() ) ?>" class="badge bg-secondary text-decoration-none"><?= htmlspecialchars( $tag->getName() ) ?></a>
									<?php endforeach; ?>
								</div>
							<?php endif; ?>

							<a href="/blog/post/<?= htmlspecialchars( $post->getSlug() ) ?>" class="btn btn-primary mt-3">Read More</a>
						</div>
					</article>
				</div>
			<?php endforeach; ?>
		</div>
	<?php else: ?>
		<div class="alert alert-info">
			<p>No posts found. Check back soon!</p>
		</div>
	<?php endif; ?>
</div>
