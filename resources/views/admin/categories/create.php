<div class="container-fluid">
	<div class="d-flex justify-content-between align-items-center mb-4">
		<h2>Create New Category</h2>
		<a href="<?= route_path('admin_categories') ?>" class="btn btn-secondary">Back to Categories</a>
	</div>

	<div class="card">
		<div class="card-body">
			<form method="POST" action="<?= route_path('admin_categories') ?>">
				<?= csrf_field() ?>

				<div class="mb-3">
					<label for="name" class="form-label">Name</label>
					<input type="text" class="form-control" id="name" name="name" required>
				</div>

				<div class="mb-3">
					<label for="slug" class="form-label">Slug</label>
					<input type="text" class="form-control" id="slug" name="slug" required>
					<small class="form-text text-muted">URL-friendly version of the name</small>
				</div>

				<div class="mb-3">
					<label for="description" class="form-label">Description</label>
					<textarea class="form-control" id="description" name="description" rows="4"></textarea>
				</div>

				<button type="submit" class="btn btn-primary">Create Category</button>
				<a href="<?= route_path('admin_categories') ?>" class="btn btn-secondary">Cancel</a>
			</form>
		</div>
	</div>
</div>
