<div class="container-fluid">
	<div class="d-flex justify-content-between align-items-center mb-4">
		<h2>Pages</h2>
		<a href="<?= route_path('admin_pages_create') ?>" class="btn btn-primary">Create New Page</a>
	</div>

	<?php if(isset($success) && $success): ?>
		<div class="alert alert-success alert-dismissible fade show" role="alert">
			<?= htmlspecialchars($success) ?>
			<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
		</div>
	<?php endif; ?>

	<?php if(isset($error) && $error): ?>
		<div class="alert alert-danger alert-dismissible fade show" role="alert">
			<?= htmlspecialchars($error) ?>
			<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
		</div>
	<?php endif; ?>

	<div class="card">
		<div class="card-body">
			<?php if(empty($pages)): ?>
				<div class="text-center py-5">
					<p class="text-muted mb-3">No pages yet.</p>
					<a href="<?= route_path('admin_pages_create') ?>" class="btn btn-primary">Create Your First Page</a>
				</div>
			<?php else: ?>
				<div class="row g-2 mb-3 align-items-center">
					<div class="col-12 col-md-6 col-lg-5">
						<div class="input-group">
							<span class="input-group-text"><i class="bi bi-search"></i></span>
							<input type="search" id="pageSearch" class="form-control" placeholder="Search by title or slug..." autocomplete="off">
						</div>
					</div>
					<div class="col-6 col-md-3 col-lg-3">
						<select id="pageStatusFilter" class="form-select">
							<option value="">All statuses</option>
							<option value="published">Published</option>
							<option value="draft">Draft</option>
						</select>
					</div>
					<div class="col-6 col-md-3 col-lg-2">
						<select id="pageSize" class="form-select" aria-label="Rows per page">
							<option value="10">10 / page</option>
							<option value="25" selected>25 / page</option>
							<option value="50">50 / page</option>
							<option value="100">100 / page</option>
							<option value="all">Show all</option>
						</select>
					</div>
				</div>

				<div class="table-responsive">
					<table class="table table-hover align-middle" id="pagesTable">
						<thead>
							<tr>
								<th class="sortable" data-col="0" role="button">Title</th>
								<th class="sortable" data-col="1" role="button">Slug</th>
								<th class="sortable" data-col="2" role="button">Status</th>
								<th class="sortable" data-col="3" role="button">Author</th>
								<th class="sortable" data-col="4" role="button">Template</th>
								<th class="sortable" data-col="5" role="button">Views</th>
								<th class="sortable" data-col="6" role="button">Updated</th>
								<th>Actions</th>
							</tr>
						</thead>
						<tbody id="pagesTableBody">
							<?php foreach($pages as $page): ?>
								<tr data-title="<?= htmlspecialchars(strtolower($page->getTitle()), ENT_QUOTES, 'UTF-8') ?>"
									data-slug="<?= htmlspecialchars(strtolower($page->getSlug()), ENT_QUOTES, 'UTF-8') ?>"
									data-status="<?= htmlspecialchars($page->getStatus(), ENT_QUOTES, 'UTF-8') ?>">
									<td>
										<strong><?= htmlspecialchars($page->getTitle()) ?></strong>
									</td>
									<td>
										<code class="text-muted">/pages/<?= htmlspecialchars($page->getSlug()) ?></code>
									</td>
									<td>
										<span class="badge bg-<?= $page->getStatus() === 'published' ? 'success' : 'secondary' ?>">
											<?= htmlspecialchars(ucfirst($page->getStatus()), ENT_QUOTES, 'UTF-8') ?>
										</span>
									</td>
									<td>
										<?= $page->getAuthor()
											? htmlspecialchars($page->getAuthor()->getUsername(), ENT_QUOTES, 'UTF-8')
											: 'N/A' ?>
									</td>
									<td><span class="badge bg-info"><?= htmlspecialchars($page->getTemplate()) ?></span></td>
									<td data-sort="<?= (int)$page->getViewCount() ?>"><?= $page->getViewCount() ?></td>
									<td data-sort="<?= $page->getUpdatedAt() ? $page->getUpdatedAt()->getTimestamp() : 0 ?>">
										<?php if($page->getUpdatedAt()): ?>
											<?= $page->getUpdatedAt()->format('M j, Y') ?>
										<?php else: ?>
											<span class="text-muted">Never</span>
										<?php endif; ?>
									</td>
									<td>
										<div class="btn-group btn-group-sm" role="group">
											<a href="<?= route_path('admin_pages_edit', ['id' => $page->getId()]) ?>"
											   class="btn btn-outline-primary"
											   title="Edit">
												<i class="bi bi-pencil"></i> Edit
											</a>
											<?php if($page->isPublished()): ?>
												<a href="<?= route_path('page', ['slug' => $page->getSlug()]) ?>"
												   class="btn btn-outline-secondary"
												   target="_blank"
												   title="View">
													<i class="bi bi-eye"></i> View
												</a>
											<?php endif; ?>
											<form method="POST"
												  action="<?= route_path('admin_pages_destroy', ['id' => $page->getId()]) ?>"
												  style="display:inline;"
												  onsubmit="return confirm('Are you sure you want to delete this page? This action cannot be undone.')">
												<input type="hidden" name="_method" value="DELETE">
												<?= csrf_field() ?>
												<button type="submit" class="btn btn-outline-danger" title="Delete">
													<i class="bi bi-trash"></i> Delete
												</button>
											</form>
										</div>
									</td>
								</tr>
							<?php endforeach; ?>
						</tbody>
					</table>
				</div>

				<div id="pagesNoResults" class="text-center py-4 text-muted d-none">
					No pages match your search.
				</div>

				<div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mt-3">
					<div class="text-muted small" id="pagesSummary"></div>
					<nav aria-label="Pages pagination">
						<ul class="pagination pagination-sm mb-0" id="pagesPager"></ul>
					</nav>
				</div>
			<?php endif; ?>
		</div>
	</div>
</div>

<style>
#pagesTable th.sortable {
	cursor: pointer;
	white-space: nowrap;
	user-select: none;
}
#pagesTable th.sortable:hover {
	color: #0d6efd;
}
#pagesTable th.sortable::after {
	content: '\2195';
	opacity: 0.3;
	margin-left: 0.35rem;
	font-size: 0.85em;
}
#pagesTable th.sortable.sort-asc::after {
	content: '\2191';
	opacity: 0.9;
}
#pagesTable th.sortable.sort-desc::after {
	content: '\2193';
	opacity: 0.9;
}
</style>

<script>
(function() {
	const table = document.getElementById('pagesTable');
	if (!table) {
		return;
	}

	const body = document.getElementById('pagesTableBody');
	const searchInput = document.getElementById('pageSearch');
	const statusFilter = document.getElementById('pageStatusFilter');
	const sizeSelect = document.getElementById('pageSize');
	const summaryEl = document.getElementById('pagesSummary');
	const pager = document.getElementById('pagesPager');
	const noResults = document.getElementById('pagesNoResults');
	const allRows = Array.from(body.querySelectorAll('tr'));

	let currentPage = 1;

	function pageSize() {
		return sizeSelect.value === 'all' ? Infinity : parseInt(sizeSelect.value, 10);
	}

	// Rows that match the current search + status filters, in current DOM (sorted) order
	function getFilteredRows() {
		const term = (searchInput.value || '').trim().toLowerCase();
		const status = statusFilter.value;

		return Array.from(body.querySelectorAll('tr')).filter(row => {
			const matchesText = !term
				|| (row.dataset.title || '').includes(term)
				|| (row.dataset.slug || '').includes(term);
			const matchesStatus = !status || row.dataset.status === status;
			return matchesText && matchesStatus;
		});
	}

	function buildPager(pageCount) {
		pager.innerHTML = '';
		if (pageCount <= 1) {
			return;
		}

		const addItem = (label, page, opts = {}) => {
			const li = document.createElement('li');
			li.className = 'page-item'
				+ (opts.active ? ' active' : '')
				+ (opts.disabled ? ' disabled' : '');
			const a = document.createElement('a');
			a.className = 'page-link';
			a.href = '#';
			a.innerHTML = label;
			if (page !== null) {
				a.dataset.page = page;
			}
			if (opts.disabled) {
				a.setAttribute('aria-disabled', 'true');
				a.tabIndex = -1;
			}
			li.appendChild(a);
			pager.appendChild(li);
		};

		addItem('&laquo;', currentPage - 1, { disabled: currentPage === 1 });

		// Windowed page numbers: first, last, and current +/- 2, with ellipses
		const pagesToShow = new Set([1, pageCount, currentPage, currentPage - 1, currentPage - 2, currentPage + 1, currentPage + 2]);
		let last = 0;
		for (let p = 1; p <= pageCount; p++) {
			if (!pagesToShow.has(p)) {
				continue;
			}
			if (p - last > 1) {
				addItem('&hellip;', null, { disabled: true });
			}
			addItem(String(p), p, { active: p === currentPage });
			last = p;
		}

		addItem('&raquo;', currentPage + 1, { disabled: currentPage === pageCount });
	}

	function render() {
		const filtered = getFilteredRows();
		const total = filtered.length;
		const size = pageSize();
		const pageCount = Math.max(1, Math.ceil(total / size));

		if (currentPage > pageCount) {
			currentPage = pageCount;
		}
		if (currentPage < 1) {
			currentPage = 1;
		}

		const start = size === Infinity ? 0 : (currentPage - 1) * size;
		const end = size === Infinity ? total : start + size;

		// Hide everything, then reveal just this page's slice of the filtered set
		allRows.forEach(row => row.classList.add('d-none'));
		const visible = filtered.slice(start, end);
		visible.forEach(row => row.classList.remove('d-none'));

		noResults.classList.toggle('d-none', total !== 0);

		if (total === 0) {
			summaryEl.textContent = '0 of ' + allRows.length;
		} else {
			summaryEl.textContent = 'Showing ' + (start + 1) + '\u2013' + Math.min(end, total)
				+ ' of ' + total
				+ (total !== allRows.length ? ' (filtered from ' + allRows.length + ')' : '');
		}

		buildPager(pageCount);
	}

	function sortBy(colIndex, ascending) {
		const rows = Array.from(body.querySelectorAll('tr'));
		rows.sort((a, b) => {
			const cellA = a.children[colIndex];
			const cellB = b.children[colIndex];
			const rawA = cellA.dataset.sort ?? cellA.textContent.trim();
			const rawB = cellB.dataset.sort ?? cellB.textContent.trim();
			const numA = parseFloat(rawA);
			const numB = parseFloat(rawB);
			let cmp;
			if (!isNaN(numA) && !isNaN(numB)) {
				cmp = numA - numB;
			} else {
				cmp = rawA.toLowerCase().localeCompare(rawB.toLowerCase());
			}
			return ascending ? cmp : -cmp;
		});
		rows.forEach(row => body.appendChild(row));
	}

	searchInput.addEventListener('input', function() { currentPage = 1; render(); });
	statusFilter.addEventListener('change', function() { currentPage = 1; render(); });
	sizeSelect.addEventListener('change', function() { currentPage = 1; render(); });

	pager.addEventListener('click', function(e) {
		const link = e.target.closest('a.page-link');
		if (!link || link.dataset.page === undefined) {
			e.preventDefault();
			return;
		}
		e.preventDefault();
		const page = parseInt(link.dataset.page, 10);
		if (!isNaN(page)) {
			currentPage = page;
			render();
		}
	});

	table.querySelectorAll('th.sortable').forEach(th => {
		th.addEventListener('click', function() {
			const colIndex = parseInt(this.dataset.col, 10);
			const ascending = !this.classList.contains('sort-asc');

			table.querySelectorAll('th.sortable').forEach(h => {
				h.classList.remove('sort-asc', 'sort-desc');
			});
			this.classList.add(ascending ? 'sort-asc' : 'sort-desc');

			sortBy(colIndex, ascending);
			currentPage = 1;
			render();
		});
	});

	// Default: sort by Title ascending for a predictable starting order
	const titleHeader = table.querySelector('th.sortable[data-col="0"]');
	if (titleHeader) {
		titleHeader.classList.add('sort-asc');
		sortBy(0, true);
	}

	render();
})();
</script>
