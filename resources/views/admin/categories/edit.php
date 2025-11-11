<div class="container-fluid">
	<div class="d-flex justify-content-between align-items-center mb-4">
		<h2>Edit Category: <?= htmlspecialchars( $category->getName() ) ?></h2>
		<a href="<?= route_path('admin_categories') ?>" class="btn btn-secondary">Back to Categories</a>
	</div>

	<div class="card">
		<div class="card-body">
			<form method="POST" action="<?= route_path('admin_categories_update', ['id' => $category->getId()]) ?>">
				<input type="hidden" name="_method" value="PUT">
				<?= csrf_field() ?>

				<div class="mb-3">
					<label for="name" class="form-label">Name</label>
					<input type="text" class="form-control" id="name" name="name" value="<?= htmlspecialchars( $category->getName() ) ?>" required>
				</div>

				<div class="mb-3">
					<label for="slug" class="form-label">Slug</label>
					<input type="text" class="form-control" id="slug" name="slug" value="<?= htmlspecialchars( $category->getSlug() ) ?>" required>
					<small class="form-text text-muted">URL-friendly version of the name</small>
				</div>

				<div class="mb-3">
					<label for="description" class="form-label">Description</label>
					<textarea class="form-control" id="description" name="description" rows="4"><?= htmlspecialchars( $category->getDescription() ?? '' ) ?></textarea>
				</div>

				<button type="submit" class="btn btn-primary">Update Category</button>
				<a href="<?= route_path('admin_categories') ?>" class="btn btn-secondary">Cancel</a>
			</form>
		</div>
	</div>
</div>
