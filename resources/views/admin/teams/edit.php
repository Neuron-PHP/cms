<?php
$members = $members ?? [];
?>
<div class="container-fluid py-4">
	<div class="mb-4">
		<h1 class="h3">Edit Team</h1>
		<nav aria-label="breadcrumb">
			<ol class="breadcrumb">
				<li class="breadcrumb-item"><a href="<?= route_path('admin_teams') ?>">Teams</a></li>
				<li class="breadcrumb-item active">Edit: <?= htmlspecialchars( $team->getName() ) ?></li>
			</ol>
		</nav>
	</div>

	<div class="row">
		<div class="col-lg-5 mb-4">
			<div class="card">
				<div class="card-header">
					<h5 class="mb-0">Team details</h5>
				</div>
				<div class="card-body">
					<form action="<?= route_path('admin_teams_update', ['id' => $team->getId()]) ?>" method="POST">
						<input type="hidden" name="_method" value="PUT">
						<?= csrf_field() ?>
						<?php include __DIR__ . '/_form.php'; ?>
						<div class="d-flex gap-2">
							<button type="submit" class="btn btn-primary">Update Team</button>
							<a href="<?= route_path('admin_teams') ?>" class="btn btn-secondary">Back</a>
						</div>
					</form>
				</div>
			</div>
		</div>

		<div class="col-lg-7 mb-4">
			<div class="card">
				<div class="card-header d-flex justify-content-between align-items-center">
					<h5 class="mb-0">Members</h5>
					<a href="<?= route_path('admin_teams_members_create', ['id' => $team->getId()]) ?>" class="btn btn-sm btn-primary">
						<i class="bi bi-plus-lg"></i> Add Member
					</a>
				</div>
				<div class="card-body">
					<?php if( empty( $members ) ): ?>
						<p class="text-muted text-center py-4 mb-0">
							No members yet.
							<a href="<?= route_path('admin_teams_members_create', ['id' => $team->getId()]) ?>">Add the first person</a>.
						</p>
					<?php else: ?>
						<div class="table-responsive">
							<table class="table table-hover align-middle">
								<thead>
									<tr>
										<th width="64">Photo</th>
										<th>Name</th>
										<th>Title</th>
										<th>Sort</th>
										<th width="120">Actions</th>
									</tr>
								</thead>
								<tbody>
									<?php foreach( $members as $member ): ?>
										<tr>
											<td>
												<?php if( $member->getImageUrl() ): ?>
													<img src="<?= htmlspecialchars( $member->getImageUrl() ) ?>" alt="" class="rounded-circle" width="48" height="48" style="object-fit: cover;">
												<?php else: ?>
													<span class="d-inline-flex align-items-center justify-content-center rounded-circle bg-light text-muted" style="width: 48px; height: 48px;">
														<i class="bi bi-person-fill"></i>
													</span>
												<?php endif; ?>
											</td>
											<td><?= htmlspecialchars( $member->getName() ) ?></td>
											<td><?= htmlspecialchars( $member->getTitle() ?? '' ) ?: '<span class="text-muted">-</span>' ?></td>
											<td><?= (int) $member->getSortOrder() ?></td>
											<td>
												<a href="<?= route_path('admin_teams_members_edit', ['id' => $team->getId(), 'memberId' => $member->getId()]) ?>" class="btn btn-sm btn-outline-primary" title="Edit">
													<i class="bi bi-pencil"></i>
												</a>
												<form action="<?= route_path('admin_teams_members_destroy', ['id' => $team->getId(), 'memberId' => $member->getId()]) ?>" method="POST" class="d-inline" onsubmit="return confirm('Remove this member?');">
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
