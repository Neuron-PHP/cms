<?php
$menus  = $menus ?? [];
$counts = $counts ?? [];
?>
<div class="container-fluid py-4">
	<div class="d-flex justify-content-between align-items-center mb-4">
		<h1 class="h3 mb-0">Menus</h1>
		<a href="<?= route_path('admin_menus_create') ?>" class="btn btn-primary">
			<i class="bi bi-plus-lg"></i> Add Menu
		</a>
	</div>

	<p class="text-muted">
		Create header and footer menus. The public navbar and footer pick up the
		first menu assigned to each location.
	</p>

	<div class="card">
		<div class="card-body">
			<?php if( empty( $menus ) ): ?>
				<p class="text-muted text-center py-4 mb-0">
					No menus yet. <a href="<?= route_path('admin_menus_create') ?>">Add your first menu</a>.
				</p>
			<?php else: ?>
				<div class="table-responsive">
					<table class="table table-hover align-middle">
						<thead>
							<tr>
								<th>Name</th>
								<th>Location</th>
								<th>Items</th>
								<th width="140">Actions</th>
							</tr>
						</thead>
						<tbody>
							<?php foreach( $menus as $menu ): ?>
								<tr>
									<td>
										<a href="<?= route_path('admin_menus_edit', ['id' => $menu->getId()]) ?>">
											<?= htmlspecialchars( $menu->getName() ) ?>
										</a>
									</td>
									<td><?= htmlspecialchars( $menu->getLocationMode()->label() ) ?></td>
									<td><?= (int) ( $counts[ $menu->getId() ] ?? 0 ) ?></td>
									<td>
										<a href="<?= route_path('admin_menus_edit', ['id' => $menu->getId()]) ?>" class="btn btn-sm btn-outline-primary" title="Edit">
											<i class="bi bi-pencil"></i>
										</a>
										<form action="<?= route_path('admin_menus_destroy', ['id' => $menu->getId()]) ?>" method="POST" class="d-inline" onsubmit="return confirm('Delete this menu and all of its items?');">
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
