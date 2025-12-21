<?php
/**
 * Admin Event Category Edit View
 * Form for editing an existing event category
 */

$category = $Data->get( 'category' );
$csrfToken = Neuron\Patterns\Registry::getInstance()->get( 'Auth.CsrfToken' );
?>

<div class="container-fluid py-4">
	<div class="mb-4">
		<h1 class="h3">Edit Event Category</h1>
		<nav aria-label="breadcrumb">
			<ol class="breadcrumb">
				<li class="breadcrumb-item"><a href="/admin/event-categories">Event Categories</a></li>
				<li class="breadcrumb-item active">Edit: <?= htmlspecialchars( $category->getName() ) ?></li>
			</ol>
		</nav>
	</div>

	<div class="row">
		<div class="col-md-8">
			<div class="card">
				<div class="card-body">
					<form action="/admin/event-categories/<?= $category->getId() ?>" method="POST">
						<input type="hidden" name="_method" value="PUT">
						<input type="hidden" name="csrf_token" value="<?= htmlspecialchars( $csrfToken ) ?>">

						<div class="mb-3">
							<label for="name" class="form-label">Name <span class="text-danger">*</span></label>
							<input type="text" class="form-control" id="name" name="name" value="<?= htmlspecialchars( $category->getName() ) ?>" required>
							<div class="form-text">The display name for this category</div>
						</div>

						<div class="mb-3">
							<label for="slug" class="form-label">Slug <span class="text-danger">*</span></label>
							<input type="text" class="form-control" id="slug" name="slug" value="<?= htmlspecialchars( $category->getSlug() ) ?>" required>
							<div class="form-text">URL-friendly version</div>
						</div>

						<div class="mb-3">
							<label for="color" class="form-label">Color</label>
							<input type="color" class="form-control form-control-color" id="color" name="color" value="<?= htmlspecialchars( $category->getColor() ) ?>">
							<div class="form-text">Color for calendar display</div>
						</div>

						<div class="mb-3">
							<label for="description" class="form-label">Description</label>
							<textarea class="form-control" id="description" name="description" rows="3"><?= htmlspecialchars( $category->getDescription() ?? '' ) ?></textarea>
							<div class="form-text">Optional description of this category</div>
						</div>

						<div class="d-flex gap-2">
							<button type="submit" class="btn btn-primary">Update Category</button>
							<a href="/admin/event-categories" class="btn btn-secondary">Cancel</a>
						</div>
					</form>
				</div>
			</div>
		</div>
	</div>
</div>
