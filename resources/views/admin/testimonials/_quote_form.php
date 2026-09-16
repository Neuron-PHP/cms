<?php
/**
 * Shared testimonial quote form fields.
 *
 * Expects $quote ( TestimonialQuote|null ).
 */
$quote    = $quote ?? null;
$imageUrl = $quote?->getImageUrl() ?? '';
?>
<div class="mb-3">
	<label for="quote" class="form-label">Quote <span class="text-danger">*</span></label>
	<textarea class="form-control" id="quote" name="quote" rows="4" required><?= htmlspecialchars( $quote?->getQuote() ?? '' ) ?></textarea>
</div>

<div class="mb-3">
	<label for="attribution" class="form-label">Name</label>
	<input type="text" class="form-control" id="attribution" name="attribution" value="<?= htmlspecialchars( $quote?->getAttribution() ?? '' ) ?>">
	<div class="form-text">Who said it. Optional for anonymous quotes.</div>
</div>

<div class="mb-3">
	<label for="role" class="form-label">Role</label>
	<input type="text" class="form-control" id="role" name="role" value="<?= htmlspecialchars( $quote?->getRole() ?? '' ) ?>">
	<div class="form-text">Job title, parent, volunteer, etc. Optional.</div>
</div>

<div class="mb-3">
	<label for="organization" class="form-label">Organization</label>
	<input type="text" class="form-control" id="organization" name="organization" value="<?= htmlspecialchars( $quote?->getOrganization() ?? '' ) ?>">
</div>

<div class="mb-3">
	<label for="image_url" class="form-label">Photo</label>
	<div class="input-group">
		<input type="text" class="form-control" id="image_url" name="image_url" value="<?= htmlspecialchars( $imageUrl ) ?>" placeholder="Enter URL or browse from media library">
		<button type="button" class="btn btn-outline-secondary" onclick="openMediaPicker('image_url')">
			<i class="bi bi-images"></i> Browse
		</button>
	</div>
	<div class="mt-2">
		<img id="image_url_preview" class="img-thumbnail <?= $imageUrl === '' ? 'd-none' : '' ?>" style="max-width: 180px;" src="<?= htmlspecialchars( $imageUrl ) ?>" alt="Quote photo preview">
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
	<label for="sort_order" class="form-label">Sort order</label>
	<input type="number" class="form-control" id="sort_order" name="sort_order" value="<?= $quote ? (int) $quote->getSortOrder() : '' ?>" placeholder="Leave blank to append">
	<div class="form-text">Lower numbers appear first. Leave blank when adding to place at the end.</div>
</div>
