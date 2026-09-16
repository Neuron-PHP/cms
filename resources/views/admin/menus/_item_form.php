<?php
use Neuron\Cms\Enums\MenuItemType;

$item    = $item ?? null;
$types   = $types ?? MenuItemType::cases();
$routes  = $routes ?? [];
$pages   = $pages ?? [];
$parents = $parents ?? [];
$currentType = $item?->getItemType() ?? MenuItemType::URL->value;
?>
<div class="mb-3">
	<label for="label" class="form-label">Label <span class="text-danger">*</span></label>
	<input type="text" class="form-control" id="label" name="label" value="<?= htmlspecialchars( $item?->getLabel() ?? '' ) ?>" required>
</div>

<div class="mb-3">
	<label for="item_type" class="form-label">Link type</label>
	<select class="form-select" id="item_type" name="item_type">
		<?php foreach( $types as $type ): ?>
			<option value="<?= htmlspecialchars( $type->value ) ?>" <?= $currentType === $type->value ? 'selected' : '' ?>>
				<?= htmlspecialchars( $type->label() ) ?>
			</option>
		<?php endforeach; ?>
	</select>
</div>

<div class="mb-3 menu-type-fields" data-type="page">
	<label for="page_id" class="form-label">Page</label>
	<select class="form-select" id="page_id" name="page_id">
		<option value="">Select a page</option>
		<?php foreach( $pages as $page ): ?>
			<option value="<?= (int) $page->getId() ?>" <?= (int) ( $item?->getPageId() ?? 0 ) === (int) $page->getId() ? 'selected' : '' ?>>
				<?= htmlspecialchars( $page->getTitle() ) ?>
			</option>
		<?php endforeach; ?>
	</select>
</div>

<div class="mb-3 menu-type-fields" data-type="route">
	<label for="route_name" class="form-label">Site section</label>
	<select class="form-select" id="route_name" name="route_name">
		<option value="">Select a section</option>
		<?php foreach( $routes as $routeName => $routeLabel ): ?>
			<option value="<?= htmlspecialchars( $routeName ) ?>" <?= ( $item?->getRouteName() ?? '' ) === $routeName ? 'selected' : '' ?>>
				<?= htmlspecialchars( $routeLabel ) ?>
			</option>
		<?php endforeach; ?>
	</select>
</div>

<div class="mb-3 menu-type-fields" data-type="url">
	<label for="url" class="form-label">URL</label>
	<input type="text" class="form-control" id="url" name="url" value="<?= htmlspecialchars( $item?->getUrl() ?? '' ) ?>" placeholder="/about or https://…">
</div>

<div class="mb-3">
	<label for="parent_id" class="form-label">Parent item</label>
	<select class="form-select" id="parent_id" name="parent_id">
		<option value="">Top level</option>
		<?php foreach( $parents as $parent ): ?>
			<option value="<?= (int) $parent->getId() ?>" <?= (int) ( $item?->getParentId() ?? 0 ) === (int) $parent->getId() ? 'selected' : '' ?>>
				<?= htmlspecialchars( $parent->getLabel() ) ?>
			</option>
		<?php endforeach; ?>
	</select>
	<div class="form-text">Optional. Nest under another top-level item to create a dropdown.</div>
</div>

<div class="mb-3">
	<label for="target" class="form-label">Open in</label>
	<select class="form-select" id="target" name="target">
		<option value="_self" <?= ( $item?->getTarget() ?? '_self' ) === '_self' ? 'selected' : '' ?>>Same tab</option>
		<option value="_blank" <?= ( $item?->getTarget() ?? '' ) === '_blank' ? 'selected' : '' ?>>New tab</option>
	</select>
</div>

<div class="mb-3">
	<div class="form-check">
		<input class="form-check-input" type="hidden" name="is_visible" value="0">
		<input class="form-check-input" type="checkbox" id="is_visible" name="is_visible" value="1" <?= ( $item === null || $item->isVisible() ) ? 'checked' : '' ?>>
		<label class="form-check-label" for="is_visible">Visible</label>
	</div>
</div>

<div class="mb-3">
	<label for="sort_order" class="form-label">Sort order</label>
	<input type="number" class="form-control" id="sort_order" name="sort_order" value="<?= $item ? (int) $item->getSortOrder() : '' ?>" placeholder="Leave blank to append">
</div>
<script>
(function() {
	const select = document.getElementById('item_type');
	const fields = document.querySelectorAll('.menu-type-fields');
	function sync() {
		fields.forEach(function(el) {
			el.classList.toggle('d-none', el.getAttribute('data-type') !== select.value);
		});
	}
	select.addEventListener('change', sync);
	sync();
})();
</script>
