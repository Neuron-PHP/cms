<?php
/**
 * Public header navigation.
 *
 * Expects $headerNavigation as a nested array from MenuService, or loads
 * it via cms_menu('header') when the view data is not already shared.
 */
$items = $headerNavigation ?? ( function_exists( 'cms_menu' ) ? cms_menu( 'header' ) : [] );
?>
<?php if( !empty( $items ) ): ?>
	<div class="collapse navbar-collapse" id="navbarNav">
		<ul class="navbar-nav ms-auto">
			<?php foreach( $items as $item ): ?>
				<?php if( !empty( $item['children'] ) ): ?>
					<li class="nav-item dropdown">
						<a class="nav-link dropdown-toggle" href="<?= htmlspecialchars( $item['href'] ) ?>" id="nav-<?= htmlspecialchars( preg_replace( '/[^a-z0-9]+/', '-', strtolower( $item['label'] ) ) ) ?>" role="button" data-bs-toggle="dropdown" aria-expanded="false"<?= ( $item['target'] ?? '_self' ) === '_blank' ? ' target="_blank" rel="noopener"' : '' ?>>
							<?= htmlspecialchars( $item['label'] ) ?>
						</a>
						<ul class="dropdown-menu dropdown-menu-end">
							<?php foreach( $item['children'] as $child ): ?>
								<li>
									<a class="dropdown-item" href="<?= htmlspecialchars( $child['href'] ) ?>"<?= ( $child['target'] ?? '_self' ) === '_blank' ? ' target="_blank" rel="noopener"' : '' ?>>
										<?= htmlspecialchars( $child['label'] ) ?>
									</a>
								</li>
							<?php endforeach; ?>
						</ul>
					</li>
				<?php else: ?>
					<li class="nav-item">
						<a class="nav-link" href="<?= htmlspecialchars( $item['href'] ) ?>"<?= ( $item['target'] ?? '_self' ) === '_blank' ? ' target="_blank" rel="noopener"' : '' ?>>
							<?= htmlspecialchars( $item['label'] ) ?>
						</a>
					</li>
				<?php endif; ?>
			<?php endforeach; ?>
		</ul>
	</div>
<?php endif; ?>
