<?php
/**
 * Shared carousel slide form fields.
 *
 * Expects $slide ( CarouselSlide|null ).
 */
$slide    = $slide ?? null;
$imageUrl = $slide?->getImageUrl() ?? '';
?>
<div class="mb-3">
	<label for="image_url" class="form-label">Image <span class="text-danger">*</span></label>
	<div class="input-group">
		<input type="text" class="form-control" id="image_url" name="image_url" value="<?= htmlspecialchars( $imageUrl ) ?>" required placeholder="Enter URL or browse from media library">
		<button type="button" class="btn btn-outline-secondary" onclick="openMediaPicker('image_url')">
			<i class="bi bi-images"></i> Browse
		</button>
	</div>
	<div class="mt-2">
		<img id="image_url_preview" class="img-thumbnail <?= $imageUrl === '' ? 'd-none' : '' ?>" style="max-width: 240px;" src="<?= htmlspecialchars( $imageUrl ) ?>" alt="Slide image preview">
	</div>
	<div class="form-text">Pick from the media library, upload a new image, or paste a URL.</div>
</div>
<script>
document.getElementById('image_url').addEventListener('change', function() {
	const preview = document.getElementById('image_url_preview');
	const url = this.value.trim();
	if( url ) { preview.src = url; preview.classList.remove('d-none'); }
	else { preview.classList.add('d-none'); }
});
</script>

<div class="mb-3">
	<label for="alt_text" class="form-label">Alt text</label>
	<input type="text" class="form-control" id="alt_text" name="alt_text" value="<?= htmlspecialchars( $slide?->getAltText() ?? '' ) ?>">
	<div class="form-text">Optional. Falls back to heading, then caption, then the carousel name.</div>
</div>

<div class="mb-3">
	<label for="heading" class="form-label">Heading</label>
	<input type="text" class="form-control" id="heading" name="heading" value="<?= htmlspecialchars( $slide?->getHeading() ?? '' ) ?>">
</div>

<div class="mb-3">
	<label for="caption" class="form-label">Caption</label>
	<textarea class="form-control" id="caption" name="caption" rows="3"><?= htmlspecialchars( $slide?->getCaption() ?? '' ) ?></textarea>
</div>

<div class="mb-3">
	<label for="link_url" class="form-label">Link URL</label>
	<input type="text" class="form-control" id="link_url" name="link_url" value="<?= htmlspecialchars( $slide?->getLinkUrl() ?? '' ) ?>" placeholder="/pages/about or https://…">
	<div class="form-text">Optional. Makes the slide or logo clickable.</div>
</div>

<div class="mb-3">
	<label for="link_label" class="form-label">Link label</label>
	<input type="text" class="form-control" id="link_label" name="link_label" value="<?= htmlspecialchars( $slide?->getLinkLabel() ?? '' ) ?>" placeholder="Learn more">
	<div class="form-text">Optional button text on slider captions. Defaults to the heading or “Learn more”.</div>
</div>

<div class="mb-3">
	<label for="sort_order" class="form-label">Sort order</label>
	<input type="number" class="form-control" id="sort_order" name="sort_order" value="<?= $slide ? (int) $slide->getSortOrder() : '' ?>" placeholder="Leave blank to append">
	<div class="form-text">Lower numbers appear first. Leave blank when adding to place at the end.</div>
</div>
