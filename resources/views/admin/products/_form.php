<?php
/**
 * Shared product form fields.
 *
 * Expects $product ( array|null ).
 */
$product = $product ?? null;
$price   = $product !== null ? number_format( ( (int) ( $product['price_cents'] ?? 0 ) ) / 100, 2, '.', '' ) : '';
$active  = $product === null ? true : ( (int) ( $product['active'] ?? 0 ) === 1 );
?>
<div class="mb-3">
	<label for="name" class="form-label">Name <span class="text-danger">*</span></label>
	<input type="text" class="form-control" id="name" name="name" value="<?= htmlspecialchars( (string) ( $product['name'] ?? '' ) ) ?>" required>
</div>

<div class="row">
	<div class="col-md-6 mb-3">
		<label for="price" class="form-label">Price <span class="text-danger">*</span></label>
		<input type="number" step="0.01" min="0" class="form-control" id="price" name="price" value="<?= htmlspecialchars( $price ) ?>" required>
	</div>
	<div class="col-md-3 mb-3">
		<label for="currency" class="form-label">Currency</label>
		<input type="text" class="form-control" id="currency" name="currency" value="<?= htmlspecialchars( (string) ( $product['currency'] ?? '' ) ) ?>" placeholder="usd">
	</div>
	<div class="col-md-3 mb-3">
		<label for="sort_order" class="form-label">Sort order</label>
		<input type="number" class="form-control" id="sort_order" name="sort_order" value="<?= (int) ( $product['sort_order'] ?? 0 ) ?>">
	</div>
</div>

<div class="mb-3">
	<label for="sku" class="form-label">SKU</label>
	<input type="text" class="form-control" id="sku" name="sku" value="<?= htmlspecialchars( (string) ( $product['sku'] ?? '' ) ) ?>">
</div>

<div class="mb-3">
	<label for="image_url" class="form-label">Image URL</label>
	<input type="text" class="form-control" id="image_url" name="image_url" value="<?= htmlspecialchars( (string) ( $product['image_url'] ?? '' ) ) ?>" placeholder="https://...">
	<div class="form-text">Paste a media URL or external image link.</div>
</div>

<div class="mb-3">
	<label for="description" class="form-label">Description</label>
	<textarea class="form-control" id="description" name="description" rows="4"><?= htmlspecialchars( (string) ( $product['description'] ?? '' ) ) ?></textarea>
</div>

<div class="form-check mb-4">
	<input type="checkbox" class="form-check-input" id="active" name="active" value="1" <?= $active ? 'checked' : '' ?>>
	<label class="form-check-label" for="active">Active (visible in the store)</label>
</div>
