<?php
/**
 * Shared redirect form fields.
 *
 * Expects $redirect ( Redirect|null ).
 */
$redirect = $redirect ?? null;
$isActive = $redirect ? $redirect->isActive() : true;
$preserveQuery = $redirect ? $redirect->getPreserveQuery() : true;
$status = $redirect?->getStatusCode() ?? 301;
?>
<div class="mb-3">
	<label for="from_path" class="form-label">From path <span class="text-danger">*</span></label>
	<input type="text" class="form-control" id="from_path" name="from_path" value="<?= htmlspecialchars( $redirect?->getFromPath() ?? '' ) ?>" placeholder="/old-page" required>
	<div class="form-text">The old URL path. Trailing slashes are stripped. Query strings are ignored when matching.</div>
</div>

<div class="mb-3">
	<label for="to_url" class="form-label">To URL <span class="text-danger">*</span></label>
	<input type="text" class="form-control" id="to_url" name="to_url" value="<?= htmlspecialchars( $redirect?->getToUrl() ?? '' ) ?>" placeholder="/new-page or https://example.com/page" required>
	<div class="form-text">A site path or a full <code>https://</code> URL.</div>
</div>

<div class="mb-3">
	<label for="status_code" class="form-label">Status</label>
	<select class="form-select" id="status_code" name="status_code">
		<option value="301"<?= $status === 301 ? ' selected' : '' ?>>301 Permanent</option>
		<option value="302"<?= $status === 302 ? ' selected' : '' ?>>302 Temporary</option>
	</select>
	<div class="form-text">Use 301 for moved pages. Use 302 for short-lived campaigns.</div>
</div>

<div class="mb-3 form-check">
	<input type="checkbox" class="form-check-input" id="is_active" name="is_active" value="1"<?= $isActive ? ' checked' : '' ?>>
	<label class="form-check-label" for="is_active">Active</label>
</div>

<div class="mb-3 form-check">
	<input type="checkbox" class="form-check-input" id="preserve_query" name="preserve_query" value="1"<?= $preserveQuery ? ' checked' : '' ?>>
	<label class="form-check-label" for="preserve_query">Preserve query string</label>
	<div class="form-text">Appends the original query (UTM tags, etc.) when the destination has none.</div>
</div>

<div class="mb-3">
	<label for="notes" class="form-label">Notes</label>
	<textarea class="form-control" id="notes" name="notes" rows="2" maxlength="500"><?= htmlspecialchars( $redirect?->getNotes() ?? '' ) ?></textarea>
	<div class="form-text">Optional internal note. Not shown to visitors.</div>
</div>
