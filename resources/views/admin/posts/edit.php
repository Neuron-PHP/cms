<div class="container-fluid">
	<div class="d-flex justify-content-between align-items-center mb-4">
		<h2>Edit Post: <?= htmlspecialchars( $post->getTitle() ) ?></h2>
		<a href="/admin/posts" class="btn btn-secondary">Back to Posts</a>
	</div>

	<div class="card">
		<div class="card-body">
			<form method="POST" action="/admin/posts/<?= $post->getId() ?>">
				<input type="hidden" name="_method" value="PUT">
				<?= csrf_field() ?>

				<div class="mb-3">
					<label for="title" class="form-label">Title</label>
					<input type="text" class="form-control" id="title" name="title" value="<?= htmlspecialchars( $post->getTitle() ) ?>" required>
				</div>

				<div class="mb-3">
					<label for="slug" class="form-label">Slug</label>
					<input type="text" class="form-control" id="slug" name="slug" value="<?= htmlspecialchars( $post->getSlug() ) ?>" required>
					<small class="form-text text-muted">URL-friendly version of the title</small>
				</div>

				<div class="mb-3">
					<label for="content" class="form-label">Content</label>
					<textarea class="form-control" id="content" name="content" rows="15" required><?= htmlspecialchars( $post->getBody() ) ?></textarea>
					<small class="form-text text-muted">Markdown supported</small>
				</div>

				<div class="mb-3">
					<label for="excerpt" class="form-label">Excerpt</label>
					<textarea class="form-control" id="excerpt" name="excerpt" rows="3"><?= htmlspecialchars( $post->getExcerpt() ?? '' ) ?></textarea>
				</div>

				<div class="mb-3">
					<label for="status" class="form-label">Status</label>
					<select class="form-select" id="status" name="status" required>
						<option value="draft" <?= $post->getStatus() === 'draft' ? 'selected' : '' ?>>Draft</option>
						<option value="published" <?= $post->getStatus() === 'published' ? 'selected' : '' ?>>Published</option>
					</select>
				</div>

				<div class="mb-3">
					<label for="featured_image" class="form-label">Featured Image URL</label>
					<input type="url" class="form-control" id="featured_image" name="featured_image" value="<?= htmlspecialchars( $post->getFeaturedImage() ?? '' ) ?>">
				</div>

				<div class="mb-3">
					<label for="categories" class="form-label">Categories</label>
					<select class="form-select" id="categories" name="categories[]" multiple>
						<?php if( isset( $categories ) ): ?>
							<?php
							$postCategoryIds = array_map( fn( $c ) => $c->getId(), $post->getCategories() );
							foreach( $categories as $category ):
							?>
								<option value="<?= $category->getId() ?>" <?= in_array( $category->getId(), $postCategoryIds ) ? 'selected' : '' ?>><?= htmlspecialchars( $category->getName() ) ?></option>
							<?php endforeach; ?>
						<?php endif; ?>
					</select>
					<small class="form-text text-muted">Hold Ctrl/Cmd to select multiple</small>
				</div>

				<div class="mb-3">
					<label for="tags" class="form-label">Tags</label>
					<?php
					$tagNames = array_map( fn( $t ) => $t->getName(), $post->getTags() );
					$tagsString = implode( ', ', $tagNames );
					?>
					<input type="text" class="form-control" id="tags" name="tags" value="<?= htmlspecialchars( $tagsString ) ?>" placeholder="comma, separated, tags">
				</div>

				<button type="submit" class="btn btn-primary">Update Post</button>
				<a href="/admin/posts" class="btn btn-secondary">Cancel</a>
			</form>
		</div>
	</div>
</div>
