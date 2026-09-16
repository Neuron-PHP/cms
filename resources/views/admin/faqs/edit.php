<?php
$items = $items ?? [];
?>
<div class="container-fluid py-4">
	<div class="mb-4">
		<h1 class="h3">Edit FAQ Group</h1>
		<nav aria-label="breadcrumb">
			<ol class="breadcrumb">
				<li class="breadcrumb-item"><a href="<?= route_path('admin_faqs') ?>">FAQs</a></li>
				<li class="breadcrumb-item active">Edit: <?= htmlspecialchars( $faq->getName() ) ?></li>
			</ol>
		</nav>
	</div>

	<div class="row">
		<div class="col-lg-5 mb-4">
			<div class="card">
				<div class="card-header">
					<h5 class="mb-0">Group details</h5>
				</div>
				<div class="card-body">
					<form action="<?= route_path('admin_faqs_update', ['id' => $faq->getId()]) ?>" method="POST">
						<input type="hidden" name="_method" value="PUT">
						<?= csrf_field() ?>
						<?php include __DIR__ . '/_form.php'; ?>
						<div class="d-flex gap-2">
							<button type="submit" class="btn btn-primary">Update Group</button>
							<a href="<?= route_path('admin_faqs') ?>" class="btn btn-secondary">Back</a>
						</div>
					</form>
				</div>
			</div>
		</div>

		<div class="col-lg-7 mb-4">
			<div class="card">
				<div class="card-header d-flex justify-content-between align-items-center">
					<h5 class="mb-0">Questions</h5>
					<a href="<?= route_path('admin_faqs_items_create', ['id' => $faq->getId()]) ?>" class="btn btn-sm btn-primary">
						<i class="bi bi-plus-lg"></i> Add Question
					</a>
				</div>
				<div class="card-body">
					<?php if( empty( $items ) ): ?>
						<p class="text-muted text-center py-4 mb-0">
							No questions yet.
							<a href="<?= route_path('admin_faqs_items_create', ['id' => $faq->getId()]) ?>">Add the first question</a>.
						</p>
					<?php else: ?>
						<div class="table-responsive">
							<table class="table table-hover align-middle">
								<thead>
									<tr>
										<th>Question</th>
										<th>Sort</th>
										<th width="120">Actions</th>
									</tr>
								</thead>
								<tbody>
									<?php foreach( $items as $item ): ?>
										<tr>
											<td><?= htmlspecialchars( $item->getQuestion() ) ?></td>
											<td><?= (int) $item->getSortOrder() ?></td>
											<td>
												<a href="<?= route_path('admin_faqs_items_edit', ['id' => $faq->getId(), 'itemId' => $item->getId()]) ?>" class="btn btn-sm btn-outline-primary" title="Edit">
													<i class="bi bi-pencil"></i>
												</a>
												<form action="<?= route_path('admin_faqs_items_destroy', ['id' => $faq->getId(), 'itemId' => $item->getId()]) ?>" method="POST" class="d-inline" onsubmit="return confirm('Remove this question?');">
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
