<div class="container-fluid">
	<div class="d-flex justify-content-between align-items-center mb-4">
		<h2>Categories</h2>
		<a href="/admin/categories/create" class="btn btn-primary">Create New Category</a>
	</div>

	<div class="card">
		<div class="card-body">
			<table class="table table-striped">
				<thead>
					<tr>
						<th>ID</th>
						<th>Name</th>
						<th>Slug</th>
						<th>Description</th>
						<th>Post Count</th>
						<th>Actions</th>
					</tr>
				</thead>
				<tbody>
					<?php if( isset( $categories ) && !empty( $categories ) ): ?>
						<?php foreach( $categories as $category ): ?>
							<tr>
								<td><?= $category->getId() ?></td>
								<td><?= htmlspecialchars( $category->getName() ) ?></td>
								<td><?= htmlspecialchars( $category->getSlug() ) ?></td>
								<td><?= htmlspecialchars( substr( $category->getDescription() ?? '', 0, 50 ) ) ?><?= strlen( $category->getDescription() ?? '' ) > 50 ? '...' : '' ?></td>
								<td><?= $category->getPostCount() ?? 0 ?></td>
								<td>
									<a href="/blog/category/<?= htmlspecialchars( $category->getSlug() ) ?>" class="btn btn-sm btn-outline-secondary" target="_blank">View</a>
									<a href="/admin/categories/<?= $category->getId() ?>/edit" class="btn btn-sm btn-outline-primary">Edit</a>
									<form method="POST" action="/admin/categories/<?= $category->getId() ?>" class="d-inline">
										<input type="hidden" name="_method" value="DELETE">
										<?= csrf_field() ?>
										<button type="submit" class="btn btn-sm btn-outline-danger" onclick="return confirm('Are you sure?')">Delete</button>
									</form>
								</td>
							</tr>
						<?php endforeach; ?>
					<?php else: ?>
						<tr>
							<td colspan="6" class="text-center">No categories found</td>
						</tr>
					<?php endif; ?>
				</tbody>
			</table>
		</div>
	</div>
</div>
