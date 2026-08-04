<?php
/** @var \Neuron\Cms\Models\Post $Post */
?>
<div class="alert alert-warning mb-4">
	<strong>Preview</strong> &mdash; this post is <strong><?= htmlspecialchars( $Post->getStatus() ) ?></strong> and not visible to the public yet.
	<a href="<?= route_path( 'admin_posts_edit', ['id' => $Post->getId()] ) ?>">Back to edit</a>
</div>
<?php
// Reuse this site's own blog/show.php so the preview matches exactly what
// the post will look like once published, instead of duplicating that markup
// here and letting the two drift apart.
require __DIR__ . '/../../blog/show.php';
