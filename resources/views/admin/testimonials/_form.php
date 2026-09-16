<?php
/**
 * Shared testimonial collection form fields.
 *
 * Expects $testimonial ( Testimonial|null ) and $displays ( TestimonialDisplay[] ).
 */
use Neuron\Cms\Enums\TestimonialDisplay;

$testimonial = $testimonial ?? null;
$displays = $displays ?? TestimonialDisplay::cases();
$current  = $testimonial?->getDisplay() ?? TestimonialDisplay::CARDS->value;
?>
<div class="mb-3">
	<label for="name" class="form-label">Name <span class="text-danger">*</span></label>
	<input type="text" class="form-control" id="name" name="name" value="<?= htmlspecialchars( $testimonial?->getName() ?? '' ) ?>" required>
	<div class="form-text">Display name, e.g. Success Stories or Homepage Quotes.</div>
</div>

<?php if( $testimonial !== null ): ?>
	<div class="mb-3">
		<label for="slug" class="form-label">Slug</label>
		<input type="text" class="form-control" id="slug" name="slug" value="<?= htmlspecialchars( $testimonial->getSlug() ) ?>" pattern="[a-z0-9-]+">
		<div class="form-text">
			Used in the shortcode:
			<code>[testimonial slug="<?= htmlspecialchars( $testimonial->getSlug() ) ?>"]</code>
		</div>
	</div>
<?php endif; ?>

<div class="mb-3">
	<label for="display" class="form-label">Default display</label>
	<select class="form-select" id="display" name="display">
		<?php foreach( $displays as $mode ): ?>
			<option value="<?= htmlspecialchars( $mode->value ) ?>" <?= $current === $mode->value ? 'selected' : '' ?>>
				<?= htmlspecialchars( $mode->label() ) ?> — <?= htmlspecialchars( $mode->description() ) ?>
			</option>
		<?php endforeach; ?>
	</select>
	<div class="form-text">Can be overridden per shortcode with <code>display="list"</code> or <code>display="featured"</code>.</div>
</div>

<div class="mb-3">
	<label for="description" class="form-label">Description</label>
	<textarea class="form-control" id="description" name="description" rows="3"><?= htmlspecialchars( $testimonial?->getDescription() ?? '' ) ?></textarea>
	<div class="form-text">Optional internal note. Not shown on the public page.</div>
</div>
