<div class="container-fluid py-4">
	<div class="mb-4">
		<h1 class="h3">Add Menu Item</h1>
		<nav aria-label="breadcrumb">
			<ol class="breadcrumb">
				<li class="breadcrumb-item"><a href="<?= route_path('admin_menus') ?>">Menus</a></li>
				<li class="breadcrumb-item"><a href="<?= route_path('admin_menus_edit', ['id' => $menu->getId()]) ?>"><?= htmlspecialchars( $menu->getName() ) ?></a></li>
				<li class="breadcrumb-item active">Add Item</li>
			</ol>
		</nav>
	</div>

	<div class="row">
		<div class="col-md-8">
			<div class="card">
				<div class="card-body">
					<form action="<?= route_path('admin_menus_items_store', ['id' => $menu->getId()]) ?>" method="POST">
						<?= csrf_field() ?>
						<?php $item = null; include __DIR__ . '/_item_form.php'; ?>
						<div class="d-flex gap-2">
							<button type="submit" class="btn btn-primary">Add Item</button>
							<a href="<?= route_path('admin_menus_edit', ['id' => $menu->getId()]) ?>" class="btn btn-secondary">Cancel</a>
						</div>
					</form>
				</div>
			</div>
		</div>
	</div>
</div>
