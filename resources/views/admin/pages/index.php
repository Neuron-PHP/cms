<div class="container-fluid">
	<div class="d-flex justify-content-between align-items-center mb-4">
		<h2>Pages</h2>
		<a href="<?= route_path('admin_pages_create') ?>" class="btn btn-primary">Create New Page</a>
	</div>

	<?php if($Success): ?>
		<div class="alert alert-success alert-dismissible fade show" role="alert">
			<?= htmlspecialchars($Success) ?>
			<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
		</div>
	<?php endif; ?>

	<?php if($Error): ?>
		<div class="alert alert-danger alert-dismissible fade show" role="alert">
			<?= htmlspecialchars($Error) ?>
			<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
		</div>
	<?php endif; ?>

	<div class="card">
		<div class="card-body">
			<?php if(empty($pages)): ?>
				<div class="text-center py-5">
					<p class="text-muted mb-3">No pages yet.</p>
					<a href="<?= route_path('admin_pages_create') ?>" class="btn btn-primary">Create Your First Page</a>
				</div>
			<?php else: ?>
				<div class="table-responsive">
					<table class="table table-hover">
						<thead>
							<tr>
								<th>Title</th>
								<th>Slug</th>
								<th>Status</th>
								<th>Author</th>
								<th>Template</th>
								<th>Views</th>
								<th>Updated</th>
								<th>Actions</th>
							</tr>
						</thead>
						<tbody>
							<?php foreach($pages as $page): ?>
								<tr>
									<td>
										<strong><?= htmlspecialchars($page->getTitle()) ?></strong>
									</td>
									<td>
										<code class="text-muted">/pages/<?= htmlspecialchars($page->getSlug()) ?></code>
									</td>
									<td>
										<span class="badge bg-<?= $page->getStatus() === 'published' ? 'success' : 'secondary' ?>">
											<?= ucfirst($page->getStatus()) ?>
										</span>
									</td>
									<td><?= $page->getAuthor()?->getUsername() ?? 'N/A' ?></td>
									<td><span class="badge bg-info"><?= htmlspecialchars($page->getTemplate()) ?></span></td>
									<td><?= $page->getViewCount() ?></td>
									<td>
										<?php if($page->getUpdatedAt()): ?>
											<?= $page->getUpdatedAt()->format('M j, Y') ?>
										<?php else: ?>
											<span class="text-muted">Never</span>
										<?php endif; ?>
									</td>
									<td>
										<div class="btn-group btn-group-sm" role="group">
											<a href="<?= route_path('admin_pages_edit', ['id' => $page->getId()]) ?>"
											   class="btn btn-outline-primary"
											   title="Edit">
												<i class="bi bi-pencil"></i> Edit
											</a>
											<?php if($page->isPublished()): ?>
												<a href="<?= route_path('page_show', ['slug' => $page->getSlug()]) ?>"
												   class="btn btn-outline-secondary"
												   target="_blank"
												   title="View">
													<i class="bi bi-eye"></i> View
												</a>
											<?php endif; ?>
											<form method="POST"
												  action="<?= route_path('admin_pages_destroy', ['id' => $page->getId()]) ?>"
												  style="display:inline;"
												  onsubmit="return confirm('Are you sure you want to delete this page? This action cannot be undone.')">
												<input type="hidden" name="_method" value="DELETE">
												<?= csrf_field() ?>
												<button type="submit" class="btn btn-outline-danger" title="Delete">
													<i class="bi bi-trash"></i> Delete
												</button>
											</form>
										</div>
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
