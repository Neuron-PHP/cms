<?php
$redirects = $redirects ?? [];
?>
<div class="container-fluid py-4">
	<div class="d-flex justify-content-between align-items-center mb-4">
		<h1 class="h3 mb-0">Redirects</h1>
		<a href="<?= route_path('admin_redirects_create') ?>" class="btn btn-primary">
			<i class="bi bi-plus-lg"></i> Add Redirect
		</a>
	</div>

	<p class="text-muted">
		Send visitors and search engines from an old path to a new one with a 301 or 302.
		Admin URLs are never redirected.
	</p>

	<div class="card">
		<div class="card-body">
			<?php if( empty( $redirects ) ): ?>
				<p class="text-muted text-center py-4 mb-0">
					No redirects yet. <a href="<?= route_path('admin_redirects_create') ?>">Add your first redirect</a>.
				</p>
			<?php else: ?>
				<div class="table-responsive">
					<table class="table table-hover align-middle">
						<thead>
							<tr>
								<th>From</th>
								<th>To</th>
								<th>Status</th>
								<th>Active</th>
								<th width="140">Actions</th>
							</tr>
						</thead>
						<tbody>
							<?php foreach( $redirects as $redirect ): ?>
								<tr>
									<td>
										<a href="<?= route_path('admin_redirects_edit', ['id' => $redirect->getId()]) ?>">
											<code><?= htmlspecialchars( $redirect->getFromPath() ) ?></code>
										</a>
									</td>
									<td><code><?= htmlspecialchars( $redirect->getToUrl() ) ?></code></td>
									<td><?= (int) $redirect->getStatusCode() ?></td>
									<td>
										<?php if( $redirect->isActive() ): ?>
											<span class="badge text-bg-success">Yes</span>
										<?php else: ?>
											<span class="badge text-bg-secondary">No</span>
										<?php endif; ?>
									</td>
									<td>
										<a href="<?= route_path('admin_redirects_edit', ['id' => $redirect->getId()]) ?>" class="btn btn-sm btn-outline-primary" title="Edit">
											<i class="bi bi-pencil"></i>
										</a>
										<form action="<?= route_path('admin_redirects_destroy', ['id' => $redirect->getId()]) ?>" method="POST" class="d-inline" onsubmit="return confirm('Delete this redirect?');">
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
