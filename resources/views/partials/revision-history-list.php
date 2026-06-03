<?php
/**
 * Revision history list.
 *
 * Expected variables:
 * @var string $contentTitle
 * @var int    $contentId
 * @var array  $revisions   Array of Neuron\Cms\Models\Revision (newest first)
 * @var string $routePrefix e.g. 'admin_pages' or 'admin_posts'
 * @var string $backRoute   e.g. 'admin_pages_edit'
 */

$actionBadge = static function( string $action ): string {
	return match( $action )
	{
		'created'  => 'bg-success',
		'restored' => 'bg-warning text-dark',
		default    => 'bg-secondary',
	};
};

$total = count( $revisions );
?>
<div class="container-fluid">
	<div class="d-flex justify-content-between align-items-center mb-4">
		<div>
			<h2 class="mb-1">Revision History</h2>
			<p class="text-muted mb-0"><?= htmlspecialchars( $contentTitle ) ?></p>
		</div>
		<a href="<?= route_path( $backRoute, ['id' => $contentId] ) ?>" class="btn btn-secondary">
			<i class="bi bi-arrow-left"></i> Back to Editor
		</a>
	</div>

	<div class="card">
		<div class="card-body">
			<?php if( $total === 0 ): ?>
				<p class="text-muted mb-0">No revisions recorded yet. Revisions are saved automatically each time this item is created, edited or restored.</p>
			<?php else: ?>
				<div class="table-responsive">
					<table class="table table-hover align-middle">
						<thead>
							<tr>
								<th style="width: 90px;">Version</th>
								<th>Change</th>
								<th>Edited by</th>
								<th>When</th>
								<th style="width: 180px;">Actions</th>
							</tr>
						</thead>
						<tbody>
							<?php foreach( $revisions as $i => $revision ): ?>
								<tr>
									<td>
										<span class="fw-bold">#<?= $total - $i ?></span>
										<?php if( $i === 0 ): ?>
											<span class="badge bg-primary ms-1">Current</span>
										<?php endif; ?>
									</td>
									<td>
										<span class="badge <?= $actionBadge( $revision->getAction() ) ?>">
											<?= htmlspecialchars( ucfirst( $revision->getAction() ) ) ?>
										</span>
										<span class="ms-2"><?= htmlspecialchars( $revision->getTitle() ) ?></span>
									</td>
									<td><?= htmlspecialchars( $revision->getEditorLabel() ) ?></td>
									<td>
										<?= $revision->getCreatedAt()?->format( 'M j, Y \a\t g:i A' ) ?? 'Unknown' ?>
									</td>
									<td>
										<div class="btn-group btn-group-sm" role="group">
											<a href="<?= route_path( $routePrefix . '_history_show', ['id' => $contentId, 'revision' => $revision->getId()] ) ?>" class="btn btn-outline-primary">
												<i class="bi bi-eye"></i> View
											</a>
											<?php if( $i !== 0 ): ?>
												<form method="POST"
													  action="<?= route_path( $routePrefix . '_history_restore', ['id' => $contentId, 'revision' => $revision->getId()] ) ?>"
													  onsubmit="return confirm('Restore this version? The current content will be saved as a new revision first.');"
													  class="d-inline">
													<?= csrf_field() ?>
													<button type="submit" class="btn btn-outline-warning">
														<i class="bi bi-arrow-counterclockwise"></i> Restore
													</button>
												</form>
											<?php endif; ?>
										</div>
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
