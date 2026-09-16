<?php
$faqs = $faqs ?? [];
$counts = $counts ?? [];
?>
<div class="container-fluid py-4">
	<div class="d-flex justify-content-between align-items-center mb-4">
		<h1 class="h3 mb-0">FAQs</h1>
		<a href="<?= route_path('admin_faqs_create') ?>" class="btn btn-primary">
			<i class="bi bi-plus-lg"></i> Add Group
		</a>
	</div>

	<p class="text-muted">
		Create named FAQ groups and embed them on any page with
		<code>[faq slug="general"]</code>.
	</p>

	<div class="card">
		<div class="card-body">
			<?php if( empty( $faqs ) ): ?>
				<p class="text-muted text-center py-4 mb-0">
					No FAQ groups yet. <a href="<?= route_path('admin_faqs_create') ?>">Add your first group</a>.
				</p>
			<?php else: ?>
				<div class="table-responsive">
					<table class="table table-hover align-middle">
						<thead>
							<tr>
								<th>Name</th>
								<th>Shortcode</th>
								<th>Questions</th>
								<th width="140">Actions</th>
							</tr>
						</thead>
						<tbody>
							<?php foreach( $faqs as $faq ): ?>
								<tr>
									<td>
										<a href="<?= route_path('admin_faqs_edit', ['id' => $faq->getId()]) ?>">
											<?= htmlspecialchars( $faq->getName() ) ?>
										</a>
									</td>
									<td>
										<code>[faq slug="<?= htmlspecialchars( $faq->getSlug() ) ?>"]</code>
									</td>
									<td><?= (int) ( $counts[ $faq->getId() ] ?? 0 ) ?></td>
									<td>
										<a href="<?= route_path('admin_faqs_edit', ['id' => $faq->getId()]) ?>" class="btn btn-sm btn-outline-primary" title="Edit">
											<i class="bi bi-pencil"></i>
										</a>
										<form action="<?= route_path('admin_faqs_destroy', ['id' => $faq->getId()]) ?>" method="POST" class="d-inline" onsubmit="return confirm('Delete this FAQ group and all of its questions?');">
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
