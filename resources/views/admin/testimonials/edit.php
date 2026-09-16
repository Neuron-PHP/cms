<?php
$quotes = $quotes ?? [];
?>
<div class="container-fluid py-4">
	<div class="mb-4">
		<h1 class="h3">Edit Testimonial Collection</h1>
		<nav aria-label="breadcrumb">
			<ol class="breadcrumb">
				<li class="breadcrumb-item"><a href="<?= route_path('admin_testimonials') ?>">Testimonials</a></li>
				<li class="breadcrumb-item active">Edit: <?= htmlspecialchars( $testimonial->getName() ) ?></li>
			</ol>
		</nav>
	</div>

	<div class="row">
		<div class="col-lg-5 mb-4">
			<div class="card">
				<div class="card-header">
					<h5 class="mb-0">Collection details</h5>
				</div>
				<div class="card-body">
					<form action="<?= route_path('admin_testimonials_update', ['id' => $testimonial->getId()]) ?>" method="POST">
						<input type="hidden" name="_method" value="PUT">
						<?= csrf_field() ?>
						<?php include __DIR__ . '/_form.php'; ?>
						<div class="d-flex gap-2">
							<button type="submit" class="btn btn-primary">Update Collection</button>
							<a href="<?= route_path('admin_testimonials') ?>" class="btn btn-secondary">Back</a>
						</div>
					</form>
				</div>
			</div>
		</div>

		<div class="col-lg-7 mb-4">
			<div class="card">
				<div class="card-header d-flex justify-content-between align-items-center">
					<h5 class="mb-0">Quotes</h5>
					<a href="<?= route_path('admin_testimonials_quotes_create', ['id' => $testimonial->getId()]) ?>" class="btn btn-sm btn-primary">
						<i class="bi bi-plus-lg"></i> Add Quote
					</a>
				</div>
				<div class="card-body">
					<?php if( empty( $quotes ) ): ?>
						<p class="text-muted text-center py-4 mb-0">
							No quotes yet.
							<a href="<?= route_path('admin_testimonials_quotes_create', ['id' => $testimonial->getId()]) ?>">Add the first quote</a>.
						</p>
					<?php else: ?>
						<div class="table-responsive">
							<table class="table table-hover align-middle">
								<thead>
									<tr>
										<th width="64">Photo</th>
										<th>Quote</th>
										<th>Attribution</th>
										<th>Sort</th>
										<th width="120">Actions</th>
									</tr>
								</thead>
								<tbody>
									<?php foreach( $quotes as $quote ): ?>
										<tr>
											<td>
												<?php if( $quote->getImageUrl() ): ?>
													<img src="<?= htmlspecialchars( $quote->getImageUrl() ) ?>" alt="" class="rounded-circle" width="48" height="48" style="object-fit: cover;">
												<?php else: ?>
													<span class="d-inline-flex align-items-center justify-content-center rounded-circle bg-light text-muted" style="width: 48px; height: 48px;">
														<i class="bi bi-chat-quote"></i>
													</span>
												<?php endif; ?>
											</td>
											<td><?= htmlspecialchars( strlen( $quote->getQuote() ) > 80 ? substr( $quote->getQuote(), 0, 77 ) . '…' : $quote->getQuote() ) ?></td>
											<td><?= htmlspecialchars( $quote->citation() ) ?: '<span class="text-muted">-</span>' ?></td>
											<td><?= (int) $quote->getSortOrder() ?></td>
											<td>
												<a href="<?= route_path('admin_testimonials_quotes_edit', ['id' => $testimonial->getId(), 'quoteId' => $quote->getId()]) ?>" class="btn btn-sm btn-outline-primary" title="Edit">
													<i class="bi bi-pencil"></i>
												</a>
												<form action="<?= route_path('admin_testimonials_quotes_destroy', ['id' => $testimonial->getId(), 'quoteId' => $quote->getId()]) ?>" method="POST" class="d-inline" onsubmit="return confirm('Remove this quote?');">
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
