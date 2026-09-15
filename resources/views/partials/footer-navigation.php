<?php
/**
 * Public footer links.
 */
$items = $footerNavigation ?? ( function_exists( 'cms_menu' ) ? cms_menu( 'footer' ) : [] );

if( $items === [] )
{
	return;
}

$flat = [];

foreach( $items as $item )
{
	$flat[] = $item;
	foreach( $item['children'] ?? [] as $child )
	{
		$flat[] = $child;
	}
}
?>
<ul class="list-inline mb-2">
	<?php foreach( $flat as $item ): ?>
		<li class="list-inline-item">
			<a href="<?= htmlspecialchars( $item['href'] ) ?>" class="text-decoration-none"<?= ( $item['target'] ?? '_self' ) === '_blank' ? ' target="_blank" rel="noopener"' : '' ?>>
				<?= htmlspecialchars( $item['label'] ) ?>
			</a>
		</li>
	<?php endforeach; ?>
</ul>
