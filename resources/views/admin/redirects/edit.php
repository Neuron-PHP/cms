<div class="container-fluid py-4">
	<div class="mb-4">
		<h1 class="h3">Edit Redirect</h1>
		<nav aria-label="breadcrumb">
			<ol class="breadcrumb">
				<li class="breadcrumb-item"><a href="<?= route_path('admin_redirects') ?>">Redirects</a></li>
				<li class="breadcrumb-item active"><?= htmlspecialchars( $redirect->getFromPath() ) ?></li>
			</ol>
		</nav>
	</div>

	<div class="row">
		<div class="col-md-8">
			<div class="card">
				<div class="card-body">
					<form action="<?= route_path('admin_redirects_update', ['id' => $redirect->getId()]) ?>" method="POST">
						<input type="hidden" name="_method" value="PUT">
						<?= csrf_field() ?>
						<?php include __DIR__ . '/_form.php'; ?>
						<div class="d-flex gap-2">
							<button type="submit" class="btn btn-primary">Update Redirect</button>
							<a href="<?= route_path('admin_redirects') ?>" class="btn btn-secondary">Back</a>
						</div>
					</form>
				</div>
			</div>
		</div>
	</div>
</div>
