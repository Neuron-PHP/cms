<?php
$items = $items ?? [];
$byId  = [];
foreach( $items as $item )
{
	$byId[ $item->getId() ] = $item;
}
?>
<div class="container-fluid py-4">
	<div class="mb-4">
		<h1 class="h3">Edit Menu</h1>
		<nav aria-label="breadcrumb">
			<ol class="breadcrumb">
				<li class="breadcrumb-item"><a href="<?= route_path('admin_menus') ?>">Menus</a></li>
				<li class="breadcrumb-item active">Edit: <?= htmlspecialchars( $menu->getName() ) ?></li>
			</ol>
		</nav>
	</div>

	<div class="row">
		<div class="col-lg-5 mb-4">
			<div class="card">
				<div class="card-header">
					<h5 class="mb-0">Menu details</h5>
				</div>
				<div class="card-body">
					<form action="<?= route_path('admin_menus_update', ['id' => $menu->getId()]) ?>" method="POST">
						<input type="hidden" name="_method" value="PUT">
						<?= csrf_field() ?>
						<?php include __DIR__ . '/_form.php'; ?>
						<div class="d-flex gap-2">
							<button type="submit" class="btn btn-primary">Update Menu</button>
							<a href="<?= route_path('admin_menus') ?>" class="btn btn-secondary">Back</a>
						</div>
					</form>
				</div>
			</div>
		</div>

		<div class="col-lg-7 mb-4">
			<div class="card">
				<div class="card-header d-flex justify-content-between align-items-center">
					<h5 class="mb-0">Items</h5>
					<a href="<?= route_path('admin_menus_items_create', ['id' => $menu->getId()]) ?>" class="btn btn-sm btn-primary">
						<i class="bi bi-plus-lg"></i> Add Item
					</a>
				</div>
				<div class="card-body">
					<?php if( empty( $items ) ): ?>
						<p class="text-muted text-center py-4 mb-0">
							No items yet.
							<a href="<?= route_path('admin_menus_items_create', ['id' => $menu->getId()]) ?>">Add the first item</a>.
						</p>
					<?php else: ?>
						<div class="table-responsive">
							<table class="table table-hover align-middle">
								<thead>
									<tr>
										<th>Label</th>
										<th>Type</th>
										<th>Parent</th>
										<th>Sort</th>
										<th width="120">Actions</th>
									</tr>
								</thead>
								<tbody>
									<?php foreach( $items as $item ): ?>
										<tr class="<?= $item->isVisible() ? '' : 'text-muted' ?>">
											<td><?= htmlspecialchars( $item->getLabel() ) ?><?= $item->isVisible() ? '' : ' <span class="badge bg-secondary">hidden</span>' ?></td>
											<td><?= htmlspecialchars( $item->getItemTypeMode()->label() ) ?></td>
											<td>
												<?php
												$parent = $item->getParentId() ? ( $byId[ $item->getParentId() ] ?? null ) : null;
												echo $parent ? htmlspecialchars( $parent->getLabel() ) : '<span class="text-muted">—</span>';
												?>
											</td>
											<td><?= (int) $item->getSortOrder() ?></td>
											<td>
												<a href="<?= route_path('admin_menus_items_edit', ['id' => $menu->getId(), 'itemId' => $item->getId()]) ?>" class="btn btn-sm btn-outline-primary" title="Edit">
													<i class="bi bi-pencil"></i>
												</a>
												<form action="<?= route_path('admin_menus_items_destroy', ['id' => $menu->getId(), 'itemId' => $item->getId()]) ?>" method="POST" class="d-inline" onsubmit="return confirm('Remove this item?');">
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
