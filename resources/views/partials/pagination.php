<?php
/**
 * Public pagination links.
 *
 * Expects $page, $pages, and $PaginationBase (path without ?page=).
 */
$page  = (int) ( $page ?? 1 );
$pages = (int) ( $pages ?? 1 );
$base  = $PaginationBase ?? '';

if( $pages <= 1 || $base === '' )
{
	return;
}

$queryStart = str_contains( $base, '?' ) ? '&' : '?';
?>
<nav aria-label="Pagination" class="mt-4">
	<ul class="pagination justify-content-center">
		<li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>">
			<?php if( $page <= 1 ): ?>
				<span class="page-link">Previous</span>
			<?php else: ?>
				<a class="page-link" href="<?= htmlspecialchars( $base . $queryStart . 'page=' . ( $page - 1 ) ) ?>">Previous</a>
			<?php endif; ?>
		</li>
		<?php for( $p = 1; $p <= $pages; $p++ ): ?>
			<li class="page-item <?= $p === $page ? 'active' : '' ?>">
				<a class="page-link" href="<?= htmlspecialchars( $base . $queryStart . 'page=' . $p ) ?>"><?= $p ?></a>
			</li>
		<?php endfor; ?>
		<li class="page-item <?= $page >= $pages ? 'disabled' : '' ?>">
			<?php if( $page >= $pages ): ?>
				<span class="page-link">Next</span>
			<?php else: ?>
				<a class="page-link" href="<?= htmlspecialchars( $base . $queryStart . 'page=' . ( $page + 1 ) ) ?>">Next</a>
			<?php endif; ?>
		</li>
	</ul>
</nav>
