
<div class="container-fluid py-4">
	<div class="mb-4">
		<h1 class="h3">Create Event Category</h1>
		<nav aria-label="breadcrumb">
			<ol class="breadcrumb">
				<li class="breadcrumb-item"><a href="<?= route_path('admin_event_categories') ?>">Event Categories</a></li>
				<li class="breadcrumb-item active">Create</li>
			</ol>
		</nav>
	</div>

	<div class="row">
		<div class="col-md-8">
			<div class="card">
				<div class="card-body">
					<?php if(!empty($errors)): ?>
						<div class="alert alert-danger alert-dismissible fade show" role="alert">
							<strong>Error:</strong> Please correct the following issues:
							<ul class="mb-0 mt-2">
								<?php foreach($errors as $error): ?>
									<li><?= htmlspecialchars($error) ?></li>
								<?php endforeach; ?>
							</ul>
							<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
						</div>
					<?php endif; ?>

					<form action="<?= route_path('admin_event_categories_store') ?>" method="POST">
						<?= csrf_field() ?>

						<div class="mb-3">
							<label for="name" class="form-label">Name <span class="text-danger">*</span></label>
							<input type="text" class="form-control" id="name" name="name" value="<?= htmlspecialchars($old['name'] ?? '') ?>" required>
							<div class="form-text">The display name for this category</div>
						</div>

						<div class="mb-3">
							<label for="slug" class="form-label">Slug</label>
							<input type="text" class="form-control" id="slug" name="slug" value="<?= htmlspecialchars($old['slug'] ?? '') ?>">
							<div class="form-text">URL-friendly version (leave blank to auto-generate from name)</div>
						</div>

						<div class="mb-3">
							<label for="color" class="form-label">Color</label>
							<input type="color" class="form-control form-control-color" id="color" name="color" value="<?= htmlspecialchars($old['color'] ?? '#3b82f6') ?>">
							<div class="form-text">Color for calendar display</div>
						</div>

						<div class="mb-3">
							<label for="description" class="form-label">Description</label>
							<textarea class="form-control" id="description" name="description" rows="3"><?= htmlspecialchars($old['description'] ?? '') ?></textarea>
							<div class="form-text">Optional description of this category</div>
						</div>

						<div class="d-flex gap-2">
							<button type="submit" class="btn btn-primary">Create Category</button>
							<a href="<?= route_path('admin_event_categories') ?>" class="btn btn-secondary">Cancel</a>
						</div>
					</form>
				</div>
			</div>
		</div>
	</div>
</div>
