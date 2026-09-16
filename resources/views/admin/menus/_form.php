<?php
use Neuron\Cms\Enums\MenuLocation;

$menu      = $menu ?? null;
$locations = $locations ?? MenuLocation::cases();
$current   = $menu?->getLocation() ?? MenuLocation::HEADER->value;
?>
<div class="mb-3">
	<label for="name" class="form-label">Name <span class="text-danger">*</span></label>
	<input type="text" class="form-control" id="name" name="name" value="<?= htmlspecialchars( $menu?->getName() ?? '' ) ?>" required>
	<div class="form-text">Display name, e.g. Main Header or Footer.</div>
</div>

<?php if( $menu !== null ): ?>
	<div class="mb-3">
		<label for="slug" class="form-label">Slug</label>
		<input type="text" class="form-control" id="slug" name="slug" value="<?= htmlspecialchars( $menu->getSlug() ) ?>" pattern="[a-z0-9-]+">
	</div>
<?php endif; ?>

<div class="mb-3">
	<label for="location" class="form-label">Location</label>
	<select class="form-select" id="location" name="location">
		<?php foreach( $locations as $location ): ?>
			<option value="<?= htmlspecialchars( $location->value ) ?>" <?= $current === $location->value ? 'selected' : '' ?>>
				<?= htmlspecialchars( $location->label() ) ?> — <?= htmlspecialchars( $location->description() ) ?>
			</option>
		<?php endforeach; ?>
	</select>
</div>

<div class="mb-3">
	<label for="description" class="form-label">Description</label>
	<textarea class="form-control" id="description" name="description" rows="3"><?= htmlspecialchars( $menu?->getDescription() ?? '' ) ?></textarea>
	<div class="form-text">Optional internal note. Not shown on the public site.</div>
</div>
