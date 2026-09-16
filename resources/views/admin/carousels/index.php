<?php
$carousels = $carousels ?? [];
$counts    = $counts ?? [];
?>
<div class="container-fluid py-4">
	<div class="d-flex justify-content-between align-items-center mb-4">
		<h1 class="h3 mb-0">Carousels</h1>
		<a href="<?= route_path('admin_carousels_create') ?>" class="btn btn-primary">
			<i class="bi bi-plus-lg"></i> Add Carousel
		</a>
	</div>

	<p class="text-muted">
		Create named image collections (hero sliders, photo galleries, partner logos)
		and embed them on any page with
		<code>[carousel slug="hero"]</code>.
	</p>

	<div class="card">
		<div class="card-body">
			<?php if( empty( $carousels ) ): ?>
				<p class="text-muted text-center py-4 mb-0">
					No carousels yet. <a href="<?= route_path('admin_carousels_create') ?>">Add your first carousel</a>.
				</p>
			<?php else: ?>
				<div class="table-responsive">
					<table class="table table-hover align-middle">
						<thead>
							<tr>
								<th>Name</th>
								<th>Shortcode</th>
								<th>Display</th>
								<th>Slides</th>
								<th width="140">Actions</th>
							</tr>
						</thead>
						<tbody>
							<?php foreach( $carousels as $carousel ): ?>
								<tr>
									<td>
										<a href="<?= route_path('admin_carousels_edit', ['id' => $carousel->getId()]) ?>">
											<?= htmlspecialchars( $carousel->getName() ) ?>
										</a>
									</td>
									<td>
										<code>[carousel slug="<?= htmlspecialchars( $carousel->getSlug() ) ?>"]</code>
									</td>
									<td><?= htmlspecialchars( $carousel->getDisplayMode()->label() ) ?></td>
									<td><?= (int) ( $counts[ $carousel->getId() ] ?? 0 ) ?></td>
									<td>
										<a href="<?= route_path('admin_carousels_edit', ['id' => $carousel->getId()]) ?>" class="btn btn-sm btn-outline-primary" title="Edit">
											<i class="bi bi-pencil"></i>
										</a>
										<form action="<?= route_path('admin_carousels_destroy', ['id' => $carousel->getId()]) ?>" method="POST" class="d-inline" onsubmit="return confirm('Delete this carousel and all of its slides?');">
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
