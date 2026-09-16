<?php
/**
 * Shared FAQ item form fields.
 *
 * Expects $item ( FaqItem|null ).
 */
$item = $item ?? null;
?>
<div class="mb-3">
	<label for="question" class="form-label">Question <span class="text-danger">*</span></label>
	<input type="text" class="form-control" id="question" name="question" value="<?= htmlspecialchars( $item?->getQuestion() ?? '' ) ?>" required>
</div>

<div class="mb-3">
	<label for="answer" class="form-label">Answer <span class="text-danger">*</span></label>
	<textarea class="form-control" id="answer" name="answer" rows="5" required><?= htmlspecialchars( $item?->getAnswer() ?? '' ) ?></textarea>
	<div class="form-text">Plain text. Line breaks are preserved on the public page.</div>
</div>

<div class="mb-3">
	<label for="sort_order" class="form-label">Sort order</label>
	<input type="number" class="form-control" id="sort_order" name="sort_order" value="<?= $item ? (int) $item->getSortOrder() : '' ?>" placeholder="Leave blank to append">
	<div class="form-text">Lower numbers appear first. Leave blank when adding to place at the end.</div>
</div>
