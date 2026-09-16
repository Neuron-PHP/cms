<div class="container-fluid py-4">
	<div class="mb-4">
		<h1 class="h3">Add Question</h1>
		<nav aria-label="breadcrumb">
			<ol class="breadcrumb">
				<li class="breadcrumb-item"><a href="<?= route_path('admin_faqs') ?>">FAQs</a></li>
				<li class="breadcrumb-item"><a href="<?= route_path('admin_faqs_edit', ['id' => $faq->getId()]) ?>"><?= htmlspecialchars( $faq->getName() ) ?></a></li>
				<li class="breadcrumb-item active">Add Question</li>
			</ol>
		</nav>
	</div>

	<div class="row">
		<div class="col-md-8">
			<div class="card">
				<div class="card-body">
					<form action="<?= route_path('admin_faqs_items_store', ['id' => $faq->getId()]) ?>" method="POST">
						<?= csrf_field() ?>
						<?php $item = null; include __DIR__ . '/_item_form.php'; ?>
						<div class="d-flex gap-2">
							<button type="submit" class="btn btn-primary">Add Question</button>
							<a href="<?= route_path('admin_faqs_edit', ['id' => $faq->getId()]) ?>" class="btn btn-secondary">Cancel</a>
						</div>
					</form>
				</div>
			</div>
		</div>
	</div>
</div>
