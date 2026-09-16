<div class="container-fluid py-4">
	<div class="mb-4">
		<h1 class="h3">Add Carousel</h1>
		<nav aria-label="breadcrumb">
			<ol class="breadcrumb">
				<li class="breadcrumb-item"><a href="<?= route_path('admin_carousels') ?>">Carousels</a></li>
				<li class="breadcrumb-item active">Add</li>
			</ol>
		</nav>
	</div>

	<div class="row">
		<div class="col-md-8">
			<div class="card">
				<div class="card-body">
					<form action="<?= route_path('admin_carousels_store') ?>" method="POST">
						<?= csrf_field() ?>
						<?php $carousel = null; include __DIR__ . '/_form.php'; ?>
						<div class="d-flex gap-2">
							<button type="submit" class="btn btn-primary">Create Carousel</button>
							<a href="<?= route_path('admin_carousels') ?>" class="btn btn-secondary">Cancel</a>
						</div>
					</form>
				</div>
			</div>
		</div>
	</div>
</div>
