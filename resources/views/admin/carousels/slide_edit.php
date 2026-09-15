<div class="container-fluid py-4">
	<div class="mb-4">
		<h1 class="h3">Edit Slide</h1>
		<nav aria-label="breadcrumb">
			<ol class="breadcrumb">
				<li class="breadcrumb-item"><a href="<?= route_path('admin_carousels') ?>">Carousels</a></li>
				<li class="breadcrumb-item"><a href="<?= route_path('admin_carousels_edit', ['id' => $carousel->getId()]) ?>"><?= htmlspecialchars( $carousel->getName() ) ?></a></li>
				<li class="breadcrumb-item active">Edit slide</li>
			</ol>
		</nav>
	</div>

	<div class="row">
		<div class="col-md-8">
			<div class="card">
				<div class="card-body">
					<form action="<?= route_path('admin_carousels_slides_update', ['id' => $carousel->getId(), 'slideId' => $slide->getId()]) ?>" method="POST">
						<input type="hidden" name="_method" value="PUT">
						<?= csrf_field() ?>
						<?php include __DIR__ . '/_slide_form.php'; ?>
						<div class="d-flex gap-2">
							<button type="submit" class="btn btn-primary">Update Slide</button>
							<a href="<?= route_path('admin_carousels_edit', ['id' => $carousel->getId()]) ?>" class="btn btn-secondary">Cancel</a>
						</div>
					</form>
				</div>
			</div>
		</div>
	</div>
</div>

<?php include __DIR__ . '/../../partials/media-picker-modal.php'; ?>
