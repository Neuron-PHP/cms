<?php
/**
 * Single revision preview.
 *
 * Expected variables:
 * @var string $contentTitle
 * @var int    $contentId
 * @var \Neuron\Cms\Models\Revision $revision
 * @var array  $snapshot     Decoded snapshot data
 * @var string $contentHtml  Rendered content preview HTML
 * @var string $routePrefix  e.g. 'admin_pages'
 */
?>
<div class="container-fluid">
	<div class="d-flex justify-content-between align-items-center mb-4">
		<div>
			<h2 class="mb-1">Revision Preview</h2>
			<p class="text-muted mb-0"><?= htmlspecialchars( $contentTitle ) ?></p>
		</div>
		<a href="<?= route_path( $routePrefix . '_history', ['id' => $contentId] ) ?>" class="btn btn-secondary">
			<i class="bi bi-arrow-left"></i> Back to History
		</a>
	</div>

	<div class="row">
		<div class="col-md-8">
			<div class="card mb-4">
				<div class="card-header">
					<h5 class="mb-0"><?= htmlspecialchars( $snapshot['title'] ?? $contentTitle ) ?></h5>
				</div>
				<div class="card-body">
					<?= $contentHtml ?>
				</div>
			</div>
		</div>

		<div class="col-md-4">
			<div class="card mb-4">
				<div class="card-header">
					<h5 class="mb-0">Revision Details</h5>
				</div>
				<div class="card-body">
					<dl class="row mb-0 small">
						<dt class="col-5">Change</dt>
						<dd class="col-7"><?= htmlspecialchars( ucfirst( $revision->getAction() ) ) ?></dd>

						<dt class="col-5">Edited by</dt>
						<dd class="col-7"><?= htmlspecialchars( $revision->getEditorLabel() ) ?></dd>

						<dt class="col-5">When</dt>
						<dd class="col-7"><?= $revision->getCreatedAt()?->format( 'M j, Y \a\t g:i A' ) ?? 'Unknown' ?></dd>

						<dt class="col-5">Status</dt>
						<dd class="col-7"><?= htmlspecialchars( ucfirst( (string)( $snapshot['status'] ?? $revision->getStatus() ) ) ) ?></dd>

						<?php if( isset( $snapshot['slug'] ) ): ?>
							<dt class="col-5">Slug</dt>
							<dd class="col-7"><code><?= htmlspecialchars( $snapshot['slug'] ) ?></code></dd>
						<?php endif; ?>

						<?php if( !empty( $snapshot['template'] ) ): ?>
							<dt class="col-5">Template</dt>
							<dd class="col-7"><?= htmlspecialchars( $snapshot['template'] ) ?></dd>
						<?php endif; ?>
					</dl>
				</div>
			</div>

			<form method="POST"
				  action="<?= route_path( $routePrefix . '_history_restore', ['id' => $contentId, 'revision' => $revision->getId()] ) ?>"
				  onsubmit="return confirm('Restore this version? The current content will be saved as a new revision first.');">
				<?= csrf_field() ?>
				<button type="submit" class="btn btn-warning w-100">
					<i class="bi bi-arrow-counterclockwise"></i> Restore This Version
				</button>
			</form>
		</div>
	</div>
</div>
