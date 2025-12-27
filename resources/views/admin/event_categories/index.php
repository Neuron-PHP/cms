
<div class="container-fluid py-4">
	<div class="d-flex justify-content-between align-items-center mb-4">
		<h1 class="h3">Event Categories</h1>
		<a href="<?= route_path('admin_event_categories_create') ?>" class="btn btn-primary">
			<i class="bi bi-plus-circle"></i> Create Category
		</a>
	</div>

	<?php if(isset($Success) && $Success): ?>
		<div class="alert alert-success alert-dismissible fade show">
			<?= htmlspecialchars($Success) ?>
			<button type="button" class="btn-close" data-bs-dismiss="alert"></button>
		</div>
	<?php endif; ?>

	<?php if(isset($Error) && $Error): ?>
		<div class="alert alert-danger alert-dismissible fade show">
			<?= htmlspecialchars($Error) ?>
			<button type="button" class="btn-close" data-bs-dismiss="alert"></button>
		</div>
	<?php endif; ?>

	<div class="card">
		<div class="card-body">
			<?php if(empty($categories)): ?>
				<p class="text-muted text-center py-4">No event categories found. Create your first category to get started.</p>
			<?php else: ?>
				<div class="table-responsive">
					<table class="table table-hover">
						<thead>
							<tr>
								<th>Name</th>
								<th>Slug</th>
								<th>Color</th>
								<th>Description</th>
								<th width="150">Actions</th>
							</tr>
						</thead>
						<tbody>
							<?php foreach($categories as $category): ?>
								<tr>
									<td><?= htmlspecialchars($category->getName()) ?></td>
									<td><code><?= htmlspecialchars($category->getSlug()) ?></code></td>
									<td>
										<span class="badge" style="background-color: <?= htmlspecialchars($category->getColor()) ?>">
											<?= htmlspecialchars($category->getColor()) ?>
										</span>
									</td>
									<td><?= htmlspecialchars($category->getDescription() ?? '-') ?></td>
									<td>
										<a href="<?= route_path('admin_event_categories_edit', ['id' => $category->getId()]) ?>" class="btn btn-sm btn-outline-primary" title="Edit">
											<i class="bi bi-pencil"></i>
										</a>
										<form action="<?= route_path('admin_event_categories_destroy', ['id' => $category->getId()]) ?>" method="POST" class="d-inline" onsubmit="return confirm('Are you sure you want to delete this category?');">
											<input type="hidden" name="_method" value="DELETE">
											<?= csrf_field() ?>
											<button type="submit" class="btn btn-sm btn-outline-danger" title="Delete">
												<i class="bi bi-trash"></i>
											</button>
										</form>
									</td>
								</tr>
							<?php endforeach; ?>
						</tbody>
					</table>
				</div>
			<?php endif; ?>
		</div>
	</div>
</div>
