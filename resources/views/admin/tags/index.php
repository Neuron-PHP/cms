<div class="container-fluid">
	<div class="d-flex justify-content-between align-items-center mb-4">
		<h2>Tags</h2>
		<a href="/admin/tags/create" class="btn btn-primary">Create New Tag</a>
	</div>

	<div class="card">
		<div class="card-body">
			<table class="table table-striped">
				<thead>
					<tr>
						<th>ID</th>
						<th>Name</th>
						<th>Slug</th>
						<th>Post Count</th>
						<th>Actions</th>
					</tr>
				</thead>
				<tbody>
					<?php if( isset( $tags ) && !empty( $tags ) ): ?>
						<?php foreach( $tags as $tag ): ?>
							<tr>
								<td><?= $tag->getId() ?></td>
								<td><?= htmlspecialchars( $tag->getName() ) ?></td>
								<td><?= htmlspecialchars( $tag->getSlug() ) ?></td>
								<td><?= $tag->getPostCount() ?? 0 ?></td>
								<td>
									<a href="/blog/tag/<?= htmlspecialchars( $tag->getSlug() ) ?>" class="btn btn-sm btn-outline-secondary" target="_blank">View</a>
									<a href="/admin/tags/<?= $tag->getId() ?>/edit" class="btn btn-sm btn-outline-primary">Edit</a>
									<form method="POST" action="/admin/tags/<?= $tag->getId() ?>" class="d-inline">
										<input type="hidden" name="_method" value="DELETE">
										<?= csrf_field() ?>
										<button type="submit" class="btn btn-sm btn-outline-danger" onclick="return confirm('Are you sure?')">Delete</button>
									</form>
								</td>
							</tr>
						<?php endforeach; ?>
					<?php else: ?>
						<tr>
							<td colspan="5" class="text-center">No tags found</td>
						</tr>
					<?php endif; ?>
				</tbody>
			</table>
		</div>
	</div>
</div>
