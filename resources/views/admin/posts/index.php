<div class="container-fluid">
	<div class="d-flex justify-content-between align-items-center mb-4">
		<h2>Blog Posts</h2>
		<a href="/admin/posts/create" class="btn btn-primary">Create New Post</a>
	</div>

	<div class="card">
		<div class="card-body">
			<table class="table table-striped">
				<thead>
					<tr>
						<th>ID</th>
						<th>Title</th>
						<th>Author</th>
						<th>Status</th>
						<th>Views</th>
						<th>Created</th>
						<th>Actions</th>
					</tr>
				</thead>
				<tbody>
					<?php if( isset( $posts ) && !empty( $posts ) ): ?>
						<?php foreach( $posts as $post ): ?>
							<tr>
								<td><?= $post->getId() ?></td>
								<td><?= htmlspecialchars( $post->getTitle() ) ?></td>
								<td><?= $post->getAuthor() ? htmlspecialchars( $post->getAuthor()->getUsername() ) : 'Unknown' ?></td>
								<td><span class="badge bg-<?= $post->getStatus() === 'published' ? 'success' : 'secondary' ?>"><?= htmlspecialchars( $post->getStatus() ) ?></span></td>
								<td><?= $post->getViewCount() ?></td>
								<td><?= $post->getCreatedAt() ? $post->getCreatedAt()->format( 'Y-m-d H:i' ) : 'N/A' ?></td>
								<td>
									<a href="/blog/post/<?= htmlspecialchars( $post->getSlug() ) ?>" class="btn btn-sm btn-outline-secondary" target="_blank">View</a>
									<a href="/admin/posts/<?= $post->getId() ?>/edit" class="btn btn-sm btn-outline-primary">Edit</a>
									<form method="POST" action="/admin/posts/<?= $post->getId() ?>" class="d-inline">
										<input type="hidden" name="_method" value="DELETE">
										<?= csrf_field() ?>
										<button type="submit" class="btn btn-sm btn-outline-danger" onclick="return confirm('Are you sure?')">Delete</button>
									</form>
								</td>
							</tr>
						<?php endforeach; ?>
					<?php else: ?>
						<tr>
							<td colspan="7" class="text-center">No posts found</td>
						</tr>
					<?php endif; ?>
				</tbody>
			</table>
		</div>
	</div>
</div>
