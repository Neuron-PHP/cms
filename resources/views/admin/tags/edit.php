<div class="container-fluid">
	<div class="d-flex justify-content-between align-items-center mb-4">
		<h2>Edit Tag: <?= htmlspecialchars( $tag->getName() ) ?></h2>
		<a href="/admin/tags" class="btn btn-secondary">Back to Tags</a>
	</div>

	<div class="card">
		<div class="card-body">
			<form method="POST" action="/admin/tags/<?= $tag->getId() ?>">
				<input type="hidden" name="_method" value="PUT">
				<?= csrf_field() ?>

				<div class="mb-3">
					<label for="name" class="form-label">Name</label>
					<input type="text" class="form-control" id="name" name="name" value="<?= htmlspecialchars( $tag->getName() ) ?>" required>
				</div>

				<div class="mb-3">
					<label for="slug" class="form-label">Slug</label>
					<input type="text" class="form-control" id="slug" name="slug" value="<?= htmlspecialchars( $tag->getSlug() ) ?>" required>
					<small class="form-text text-muted">URL-friendly version of the name</small>
				</div>

				<button type="submit" class="btn btn-primary">Update Tag</button>
				<a href="/admin/tags" class="btn btn-secondary">Cancel</a>
			</form>
		</div>
	</div>
</div>
