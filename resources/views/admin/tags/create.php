<div class="container-fluid">
	<div class="d-flex justify-content-between align-items-center mb-4">
		<h2>Create New Tag</h2>
		<a href="<?= route_path('admin_tags') ?>" class="btn btn-secondary">Back to Tags</a>
	</div>

	<div class="card">
		<div class="card-body">
			<form method="POST" action="<?= route_path('admin_tags') ?>">
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

				<button type="submit" class="btn btn-primary">Create Tag</button>
				<a href="<?= route_path('admin_tags') ?>" class="btn btn-secondary">Cancel</a>
			</form>
		</div>
	</div>
</div>
