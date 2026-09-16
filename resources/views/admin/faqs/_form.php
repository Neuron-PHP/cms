<?php
/**
 * Shared FAQ group form fields.
 *
 * Expects $faq ( Faq|null ).
 */
$faq = $faq ?? null;
?>
<div class="mb-3">
	<label for="name" class="form-label">Name <span class="text-danger">*</span></label>
	<input type="text" class="form-control" id="name" name="name" value="<?= htmlspecialchars( $faq?->getName() ?? '' ) ?>" required>
	<div class="form-text">Display name, e.g. General or Intake.</div>
</div>

<?php if( $faq !== null ): ?>
	<div class="mb-3">
		<label for="slug" class="form-label">Slug</label>
		<input type="text" class="form-control" id="slug" name="slug" value="<?= htmlspecialchars( $faq->getSlug() ) ?>" pattern="[a-z0-9-]+">
		<div class="form-text">
			Used in the shortcode:
			<code>[faq slug="<?= htmlspecialchars( $faq->getSlug() ) ?>"]</code>
		</div>
	</div>
<?php endif; ?>

<div class="mb-3">
	<label for="description" class="form-label">Description</label>
	<textarea class="form-control" id="description" name="description" rows="3"><?= htmlspecialchars( $faq?->getDescription() ?? '' ) ?></textarea>
	<div class="form-text">Optional internal note. Not shown on the public page.</div>
</div>
