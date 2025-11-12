<div class="container">
	<h1><?= htmlspecialchars( $Title ?? 'Blog' ) ?></h1>

	<?php if( isset( $Posts ) && !empty( $Posts ) ): ?>
		<div class="row">
			<?php foreach( $Posts as $Post ): ?>
				<div class="col-md-12 mb-4">
					<article class="card">
						<?php if( $Post->getFeaturedImage() ): ?>
							<img src="<?= htmlspecialchars( $Post->getFeaturedImage() ) ?>" class="card-img-top" alt="<?= htmlspecialchars( $Post->getTitle() ) ?>">
						<?php endif; ?>
						<div class="card-body">
							<h2 class="card-title">
								<a href="<?= route_path('blog_post', ['slug' => $Post->getSlug()]) ?>"><?= htmlspecialchars( $Post->getTitle() ) ?></a>
							</h2>
							<p class="text-muted">
								<small>
									<?php $Author = $Post->getAuthor(); ?>
									By <?= htmlspecialchars( $Author ? $Author->getUsername() : 'Unknown' ) ?>
									on <?= format_user_date( $Post->getCreatedAt(), 'F j, Y' ) ?>
									<?php if( $Post->getViewCount() > 0 ): ?>
										· <?= $Post->getViewCount() ?> views
									<?php endif; ?>
								</small>
							</p>
							<?php if( $Post->getExcerpt() ): ?>
								<p class="card-text"><?= htmlspecialchars( $Post->getExcerpt() ) ?></p>
							<?php endif; ?>

							<?php if( !empty( $Post->getCategories() ) ): ?>
								<div class="mb-2">
									<?php foreach( $Post->getCategories() as $category ): ?>
										<a href="<?= route_path('blog_category', ['slug' => $category->getSlug()]) ?>" class="badge bg-primary text-decoration-none"><?= htmlspecialchars( $category->getName() ) ?></a>
									<?php endforeach; ?>
								</div>
							<?php endif; ?>

							<?php if( !empty( $Post->getTags() ) ): ?>
								<div>
									<?php foreach( $Post->getTags() as $tag ): ?>
										<a href="<?= route_path('blog_tag', ['slug' => $tag->getSlug()]) ?>" class="badge bg-secondary text-decoration-none"><?= htmlspecialchars( $tag->getName() ) ?></a>
									<?php endforeach; ?>
								</div>
							<?php endif; ?>

							<a href="<?= route_path('blog_post', ['slug' => $Post->getSlug()]) ?>" class="btn btn-primary mt-3">Read More</a>
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
