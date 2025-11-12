<div class="container-fluid">
	<div class="d-flex justify-content-between align-items-center mb-4">
		<h2>Users</h2>
		<a href="<?= route_path('admin_users_create') ?>" class="btn btn-primary">Create New User</a>
	</div>

	<div class="card">
		<div class="card-body">
			<table class="table table-striped">
				<thead>
					<tr>
						<th>ID</th>
						<th>Username</th>
						<th>Email</th>
						<th>Role</th>
						<th>Status</th>
						<th>Created</th>
						<th>Actions</th>
					</tr>
				</thead>
				<tbody>
					<?php if( isset( $users ) && !empty( $users ) ): ?>
						<?php foreach( $users as $user ): ?>
							<tr>
								<td><?= $user->getId() ?></td>
								<td><?= htmlspecialchars( $user->getUsername() ) ?></td>
								<td><?= htmlspecialchars( $user->getEmail() ) ?></td>
								<td><span class="badge bg-primary"><?= htmlspecialchars( $user->getRole() ) ?></span></td>
								<td><span class="badge bg-<?= $user->getStatus() === 'active' ? 'success' : 'secondary' ?>"><?= htmlspecialchars( $user->getStatus() ) ?></span></td>
								<td><?= format_user_datetime( $user->getCreatedAt() ) ?></td>
								<td>
									<a href="<?= route_path('admin_users_edit', ['id' => $user->getId()]) ?>" class="btn btn-sm btn-outline-primary">Edit</a>
									<?php if( $User->getId() !== $user->getId() ): ?>
										<form method="POST" action="<?= route_path('admin_users_destroy', ['id' => $user->getId()]) ?>" class="d-inline">
											<input type="hidden" name="_method" value="DELETE">
											<?= csrf_field() ?>
											<button type="submit" class="btn btn-sm btn-outline-danger" onclick="return confirm('Are you sure?')">Delete</button>
										</form>
									<?php endif; ?>
								</td>
							</tr>
						<?php endforeach; ?>
					<?php else: ?>
						<tr>
							<td colspan="7" class="text-center">No users found</td>
						</tr>
					<?php endif; ?>
				</tbody>
			</table>
		</div>
	</div>
</div>
