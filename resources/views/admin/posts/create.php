<div class="container-fluid">
	<div class="d-flex justify-content-between align-items-center mb-4">
		<h2>Create New Post</h2>
		<a href="/admin/posts" class="btn btn-secondary">Back to Posts</a>
	</div>

	<div class="card">
		<div class="card-body">
			<form method="POST" action="/admin/posts">
				<?= csrf_field() ?>

				<div class="mb-3">
					<label for="title" class="form-label">Title</label>
					<input type="text" class="form-control" id="title" name="title" required>
				</div>

				<div class="mb-3">
					<label for="slug" class="form-label">Slug</label>
					<input type="text" class="form-control" id="slug" name="slug" required>
					<small class="form-text text-muted">URL-friendly version of the title</small>
				</div>

				<div class="mb-3">
					<label for="content" class="form-label">Content</label>
					<textarea class="form-control" id="content" name="content" rows="15" required></textarea>
					<small class="form-text text-muted">Markdown supported</small>
				</div>

				<div class="mb-3">
					<label for="excerpt" class="form-label">Excerpt</label>
					<textarea class="form-control" id="excerpt" name="excerpt" rows="3"></textarea>
				</div>

				<div class="mb-3">
					<label for="status" class="form-label">Status</label>
					<select class="form-select" id="status" name="status" required>
						<option value="draft">Draft</option>
						<option value="published">Published</option>
					</select>
				</div>

				<div class="mb-3">
					<label for="featured_image" class="form-label">Featured Image URL</label>
					<input type="url" class="form-control" id="featured_image" name="featured_image">
				</div>

				<div class="mb-3">
					<label for="categories" class="form-label">Categories</label>
					<select class="form-select" id="categories" name="categories[]" multiple>
						<?php if( isset( $categories ) ): ?>
							<?php foreach( $categories as $category ): ?>
								<option value="<?= $category->getId() ?>"><?= htmlspecialchars( $category->getName() ) ?></option>
							<?php endforeach; ?>
						<?php endif; ?>
					</select>
					<small class="form-text text-muted">Hold Ctrl/Cmd to select multiple</small>
				</div>

				<div class="mb-3">
					<label for="tags" class="form-label">Tags</label>
					<input type="text" class="form-control" id="tags" name="tags" placeholder="comma, separated, tags">
				</div>

				<button type="submit" class="btn btn-primary">Create Post</button>
				<a href="/admin/posts" class="btn btn-secondary">Cancel</a>
			</form>
		</div>
	</div>
</div>
