<?php
$testimonials = $testimonials ?? [];
$counts = $counts ?? [];
?>
<div class="container-fluid py-4">
	<div class="d-flex justify-content-between align-items-center mb-4">
		<h1 class="h3 mb-0">Testimonials</h1>
		<a href="<?= route_path('admin_testimonials_create') ?>" class="btn btn-primary">
			<i class="bi bi-plus-lg"></i> Add Collection
		</a>
	</div>

	<p class="text-muted">
		Create named quote collections and embed them on any page with
		<code>[testimonial slug="success-stories"]</code>.
	</p>

	<div class="card">
		<div class="card-body">
			<?php if( empty( $testimonials ) ): ?>
				<p class="text-muted text-center py-4 mb-0">
					No collections yet. <a href="<?= route_path('admin_testimonials_create') ?>">Add your first collection</a>.
				</p>
			<?php else: ?>
				<div class="table-responsive">
					<table class="table table-hover align-middle">
						<thead>
							<tr>
								<th>Name</th>
								<th>Shortcode</th>
								<th>Quotes</th>
								<th width="140">Actions</th>
							</tr>
						</thead>
						<tbody>
							<?php foreach( $testimonials as $testimonial ): ?>
								<tr>
									<td>
										<a href="<?= route_path('admin_testimonials_edit', ['id' => $testimonial->getId()]) ?>">
											<?= htmlspecialchars( $testimonial->getName() ) ?>
										</a>
									</td>
									<td>
										<code>[testimonial slug="<?= htmlspecialchars( $testimonial->getSlug() ) ?>"]</code>
									</td>
									<td><?= (int) ( $counts[ $testimonial->getId() ] ?? 0 ) ?></td>
									<td>
										<a href="<?= route_path('admin_testimonials_edit', ['id' => $testimonial->getId()]) ?>" class="btn btn-sm btn-outline-primary" title="Edit">
											<i class="bi bi-pencil"></i>
										</a>
										<form action="<?= route_path('admin_testimonials_destroy', ['id' => $testimonial->getId()]) ?>" method="POST" class="d-inline" onsubmit="return confirm('Delete this collection and all of its quotes?');">
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
