<div class="container-fluid">
	<div class="d-flex justify-content-between align-items-center mb-4">
		<h2>Contact Submissions</h2>
		<form method="GET" action="<?= route_path('admin_contact_submissions') ?>" class="d-flex align-items-center gap-2">
			<label for="formFilter" class="form-label mb-0 small text-muted">Filter:</label>
			<select id="formFilter" name="form" class="form-select form-select-sm" onchange="this.form.submit()">
				<option value="">All forms</option>
				<?php foreach( ( $formKeys ?? [] ) as $key ): ?>
					<option value="<?= htmlspecialchars( $key ) ?>" <?= ( ( $activeFormKey ?? null ) === $key ) ? 'selected' : '' ?>>
						<?= htmlspecialchars( $key ) ?>
					</option>
				<?php endforeach; ?>
			</select>
		</form>
	</div>

	<div class="card">
		<div class="card-body">
			<?php if( empty( $submissions ) ): ?>
				<p class="text-muted text-center py-4">No contact submissions found.</p>
			<?php else: ?>
				<div class="table-responsive">
					<table class="table table-hover align-middle">
						<thead>
							<tr>
								<th>#</th>
								<th>Form</th>
								<th>Recipient</th>
								<th>Reply-To</th>
								<th>Delivered</th>
								<th>Received</th>
								<th width="120">Actions</th>
							</tr>
						</thead>
						<tbody>
							<?php foreach( $submissions as $s ): ?>
								<tr>
									<td><?= htmlspecialchars( (string) ( $s['id'] ?? '' ) ) ?></td>
									<td><span class="badge bg-secondary"><?= htmlspecialchars( (string) ( $s['form_key'] ?? '' ) ) ?></span></td>
									<td><?= htmlspecialchars( (string) ( $s['recipient'] ?? '' ) ) ?></td>
									<td>
										<?php if( !empty( $s['reply_to_email'] ) ): ?>
											<?= htmlspecialchars( trim( ( $s['reply_to_name'] ?? '' ) . ' <' . $s['reply_to_email'] . '>' ) ) ?>
										<?php else: ?>
											<span class="text-muted">-</span>
										<?php endif; ?>
									</td>
									<td>
										<?php if( !empty( $s['delivered'] ) ): ?>
											<span class="badge bg-success">Delivered</span>
										<?php else: ?>
											<span class="badge bg-warning text-dark">Pending</span>
										<?php endif; ?>
									</td>
									<td><?= htmlspecialchars( (string) ( $s['created_at'] ?? '' ) ) ?></td>
									<td>
										<a href="<?= route_path('admin_contact_submission_show', ['id' => $s['id']]) ?>" class="btn btn-sm btn-outline-primary" title="View">
											<i class="bi bi-eye"></i>
										</a>
										<form action="<?= route_path('admin_contact_submission_delete', ['id' => $s['id']]) ?>" method="POST" class="d-inline" onsubmit="return confirm('Delete this submission?');">
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

				<?php if( ( $pages ?? 1 ) > 1 ): ?>
					<nav class="mt-3">
						<ul class="pagination pagination-sm mb-0">
							<?php
							$filterQs = !empty( $activeFormKey ) ? '&form=' . urlencode( $activeFormKey ) : '';
							for( $p = 1; $p <= $pages; $p++ ):
							?>
								<li class="page-item <?= ( $p === ( $page ?? 1 ) ) ? 'active' : '' ?>">
									<a class="page-link" href="<?= route_path('admin_contact_submissions') ?>?page=<?= $p . $filterQs ?>"><?= $p ?></a>
								</li>
							<?php endfor; ?>
						</ul>
					</nav>
				<?php endif; ?>

				<p class="text-muted small mt-2 mb-0"><?= (int) ( $total ?? 0 ) ?> total submission(s)</p>
			<?php endif; ?>
		</div>
	</div>
</div>
