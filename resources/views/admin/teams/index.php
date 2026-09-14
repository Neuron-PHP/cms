<?php
$teams  = $teams ?? [];
$counts = $counts ?? [];
?>
<div class="container-fluid py-4">
	<div class="d-flex justify-content-between align-items-center mb-4">
		<h1 class="h3 mb-0">Teams</h1>
		<a href="<?= route_path('admin_teams_create') ?>" class="btn btn-primary">
			<i class="bi bi-plus-lg"></i> Add Team
		</a>
	</div>

	<p class="text-muted">
		Create named teams (Staff, Board, etc.) and embed them on any page with
		<code>[team slug="staff"]</code>.
	</p>

	<div class="card">
		<div class="card-body">
			<?php if( empty( $teams ) ): ?>
				<p class="text-muted text-center py-4 mb-0">
					No teams yet. <a href="<?= route_path('admin_teams_create') ?>">Add your first team</a>.
				</p>
			<?php else: ?>
				<div class="table-responsive">
					<table class="table table-hover align-middle">
						<thead>
							<tr>
								<th>Name</th>
								<th>Shortcode</th>
								<th>Members</th>
								<th width="140">Actions</th>
							</tr>
						</thead>
						<tbody>
							<?php foreach( $teams as $team ): ?>
								<tr>
									<td>
										<a href="<?= route_path('admin_teams_edit', ['id' => $team->getId()]) ?>">
											<?= htmlspecialchars( $team->getName() ) ?>
										</a>
									</td>
									<td>
										<code>[team slug="<?= htmlspecialchars( $team->getSlug() ) ?>"]</code>
									</td>
									<td><?= (int) ( $counts[ $team->getId() ] ?? 0 ) ?></td>
									<td>
										<a href="<?= route_path('admin_teams_edit', ['id' => $team->getId()]) ?>" class="btn btn-sm btn-outline-primary" title="Edit">
											<i class="bi bi-pencil"></i>
										</a>
										<form action="<?= route_path('admin_teams_destroy', ['id' => $team->getId()]) ?>" method="POST" class="d-inline" onsubmit="return confirm('Delete this team and all of its members?');">
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
