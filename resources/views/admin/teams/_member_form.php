<?php
/**
 * Shared team member form fields.
 *
 * Expects $member ( TeamMember|null ).
 */
$member   = $member ?? null;
$imageUrl = $member?->getImageUrl() ?? '';
?>
<div class="mb-3">
	<label for="name" class="form-label">Name <span class="text-danger">*</span></label>
	<input type="text" class="form-control" id="name" name="name" value="<?= htmlspecialchars( $member?->getName() ?? '' ) ?>" required>
</div>

<div class="mb-3">
	<label for="title" class="form-label">Title</label>
	<input type="text" class="form-control" id="title" name="title" value="<?= htmlspecialchars( $member?->getTitle() ?? '' ) ?>">
	<div class="form-text">Job title or board role. Optional.</div>
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
		<img id="image_url_preview" class="img-thumbnail <?= $imageUrl === '' ? 'd-none' : '' ?>" style="max-width: 180px;" src="<?= htmlspecialchars( $imageUrl ) ?>" alt="Member photo preview">
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
	<label for="contact" class="form-label">Contact</label>
	<input type="text" class="form-control" id="contact" name="contact" value="<?= htmlspecialchars( $member?->getContact() ?? '' ) ?>" placeholder="email@example.com or /about or https://…">
	<div class="form-text">Optional email or link. Emails become mailto links on the public page.</div>
</div>

<div class="mb-3">
	<label for="bio" class="form-label">Bio</label>
	<textarea class="form-control" id="bio" name="bio" rows="4"><?= htmlspecialchars( $member?->getBio() ?? '' ) ?></textarea>
	<div class="form-text">Optional. Shown under the title on the public roster.</div>
</div>

<div class="mb-3">
	<label for="sort_order" class="form-label">Sort order</label>
	<input type="number" class="form-control" id="sort_order" name="sort_order" value="<?= $member ? (int) $member->getSortOrder() : '' ?>" placeholder="Leave blank to append">
	<div class="form-text">Lower numbers appear first. Leave blank when adding to place at the end.</div>
</div>
