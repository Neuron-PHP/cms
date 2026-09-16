<?php
$slides = $slides ?? [];
?>
<div class="container-fluid py-4">
	<div class="mb-4">
		<h1 class="h3">Edit Carousel</h1>
		<nav aria-label="breadcrumb">
			<ol class="breadcrumb">
				<li class="breadcrumb-item"><a href="<?= route_path('admin_carousels') ?>">Carousels</a></li>
				<li class="breadcrumb-item active">Edit: <?= htmlspecialchars( $carousel->getName() ) ?></li>
			</ol>
		</nav>
	</div>

	<div class="alert alert-light border">
		<div class="fw-semibold mb-1">Shortcode</div>
		<code id="carousel-shortcode">[carousel slug="<?= htmlspecialchars( $carousel->getSlug() ) ?>"]</code>
		<button type="button" class="btn btn-sm btn-outline-secondary ms-2" onclick="navigator.clipboard.writeText(document.getElementById('carousel-shortcode').textContent.trim())">
			Copy
		</button>
	</div>

	<div class="row">
		<div class="col-lg-5 mb-4">
			<div class="card">
				<div class="card-header">
					<h5 class="mb-0">Carousel details</h5>
				</div>
				<div class="card-body">
					<form action="<?= route_path('admin_carousels_update', ['id' => $carousel->getId()]) ?>" method="POST">
						<input type="hidden" name="_method" value="PUT">
						<?= csrf_field() ?>
						<?php include __DIR__ . '/_form.php'; ?>
						<div class="d-flex gap-2">
							<button type="submit" class="btn btn-primary">Update Carousel</button>
							<a href="<?= route_path('admin_carousels') ?>" class="btn btn-secondary">Back</a>
						</div>
					</form>
				</div>
			</div>
		</div>

		<div class="col-lg-7 mb-4">
			<div class="card">
				<div class="card-header d-flex justify-content-between align-items-center">
					<h5 class="mb-0">Slides</h5>
					<a href="<?= route_path('admin_carousels_slides_create', ['id' => $carousel->getId()]) ?>" class="btn btn-sm btn-primary">
						<i class="bi bi-plus-lg"></i> Add Slide
					</a>
				</div>
				<div class="card-body">
					<?php if( empty( $slides ) ): ?>
						<p class="text-muted text-center py-4 mb-0">
							No slides yet.
							<a href="<?= route_path('admin_carousels_slides_create', ['id' => $carousel->getId()]) ?>">Add the first slide</a>.
						</p>
					<?php else: ?>
						<div class="table-responsive">
							<table class="table table-hover align-middle">
								<thead>
									<tr>
										<th width="80">Image</th>
										<th>Heading</th>
										<th>Caption</th>
										<th>Sort</th>
										<th width="120">Actions</th>
									</tr>
								</thead>
								<tbody>
									<?php foreach( $slides as $slide ): ?>
										<tr>
											<td>
												<?php if( $slide->getImageUrl() ): ?>
													<img src="<?= htmlspecialchars( $slide->getImageUrl() ) ?>" alt="" class="rounded" width="64" height="48" style="object-fit: cover;">
												<?php else: ?>
													<span class="d-inline-flex align-items-center justify-content-center rounded bg-light text-muted" style="width: 64px; height: 48px;">
														<i class="bi bi-image"></i>
													</span>
												<?php endif; ?>
											</td>
											<td><?= htmlspecialchars( $slide->getHeading() ?? '' ) ?: '<span class="text-muted">-</span>' ?></td>
											<td class="text-truncate" style="max-width: 180px;"><?= htmlspecialchars( $slide->getCaption() ?? '' ) ?: '<span class="text-muted">-</span>' ?></td>
											<td><?= (int) $slide->getSortOrder() ?></td>
											<td>
												<a href="<?= route_path('admin_carousels_slides_edit', ['id' => $carousel->getId(), 'slideId' => $slide->getId()]) ?>" class="btn btn-sm btn-outline-primary" title="Edit">
													<i class="bi bi-pencil"></i>
												</a>
												<form action="<?= route_path('admin_carousels_slides_destroy', ['id' => $carousel->getId(), 'slideId' => $slide->getId()]) ?>" method="POST" class="d-inline" onsubmit="return confirm('Remove this slide?');">
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
	</div>
</div>
