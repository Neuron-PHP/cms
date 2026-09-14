<?php
/**
 * Shared team form fields.
 *
 * Expects $team ( Team|null ).
 */
$team = $team ?? null;
?>
<div class="mb-3">
	<label for="name" class="form-label">Name <span class="text-danger">*</span></label>
	<input type="text" class="form-control" id="name" name="name" value="<?= htmlspecialchars( $team?->getName() ?? '' ) ?>" required>
	<div class="form-text">Display name, e.g. Staff or Board of Directors.</div>
</div>

<?php if( $team !== null ): ?>
	<div class="mb-3">
		<label for="slug" class="form-label">Slug</label>
		<input type="text" class="form-control" id="slug" name="slug" value="<?= htmlspecialchars( $team->getSlug() ) ?>" pattern="[a-z0-9-]+">
		<div class="form-text">
			Used in the shortcode:
			<code>[team slug="<?= htmlspecialchars( $team->getSlug() ) ?>"]</code>
		</div>
	</div>
<?php endif; ?>

<div class="mb-3">
	<label for="description" class="form-label">Description</label>
	<textarea class="form-control" id="description" name="description" rows="3"><?= htmlspecialchars( $team?->getDescription() ?? '' ) ?></textarea>
	<div class="form-text">Optional internal note. Not shown on the public roster.</div>
</div>
