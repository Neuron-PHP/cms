<div class="container-fluid py-4">
	<div class="mb-4">
		<h1 class="h3">Edit Quote</h1>
		<nav aria-label="breadcrumb">
			<ol class="breadcrumb">
				<li class="breadcrumb-item"><a href="<?= route_path('admin_testimonials') ?>">Testimonials</a></li>
				<li class="breadcrumb-item"><a href="<?= route_path('admin_testimonials_edit', ['id' => $testimonial->getId()]) ?>"><?= htmlspecialchars( $testimonial->getName() ) ?></a></li>
				<li class="breadcrumb-item active">Edit quote</li>
			</ol>
		</nav>
	</div>

	<div class="row">
		<div class="col-md-8">
			<div class="card">
				<div class="card-body">
					<form action="<?= route_path('admin_testimonials_quotes_update', ['id' => $testimonial->getId(), 'quoteId' => $quote->getId()]) ?>" method="POST">
						<input type="hidden" name="_method" value="PUT">
						<?= csrf_field() ?>
						<?php include __DIR__ . '/_quote_form.php'; ?>
						<div class="d-flex gap-2">
							<button type="submit" class="btn btn-primary">Update Quote</button>
							<a href="<?= route_path('admin_testimonials_edit', ['id' => $testimonial->getId()]) ?>" class="btn btn-secondary">Cancel</a>
						</div>
					</form>
				</div>
			</div>
		</div>
	</div>
</div>

<?php include __DIR__ . '/../../partials/media-picker-modal.php'; ?>
