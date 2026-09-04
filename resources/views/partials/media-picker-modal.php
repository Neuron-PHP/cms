<!-- Media Picker Modal -->
<div class="modal fade" id="mediaPickerModal" tabindex="-1" aria-labelledby="mediaPickerModalLabel" aria-hidden="true">
	<div class="modal-dialog modal-xl">
		<div class="modal-content">
			<div class="modal-header">
				<h5 class="modal-title" id="mediaPickerModalLabel">Select Image</h5>
				<button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
			</div>
			<div class="modal-body">
				<div class="row">
					<div class="col-12">
						<ul class="nav nav-tabs mb-3" id="mediaPickerTabs" role="tablist">
							<li class="nav-item" role="presentation">
								<button class="nav-link active" id="library-tab" data-bs-toggle="tab" data-bs-target="#library" type="button" role="tab" aria-controls="library" aria-selected="true">
									<i class="bi bi-images me-1"></i> Media Library
								</button>
							</li>
							<li class="nav-item" role="presentation">
								<button class="nav-link" id="upload-tab" data-bs-toggle="tab" data-bs-target="#upload" type="button" role="tab" aria-controls="upload" aria-selected="false">
									<i class="bi bi-upload me-1"></i> Upload New
								</button>
							</li>
						</ul>

						<div class="tab-content" id="mediaPickerTabContent">
							<div class="tab-pane fade show active" id="library" role="tabpanel" aria-labelledby="library-tab">
								<nav aria-label="Picker folder breadcrumb" class="mb-2">
									<ol class="breadcrumb mb-2" id="mediaPickerBreadcrumb"></ol>
								</nav>
								<div id="mediaPickerTags" class="mb-3 d-flex flex-wrap gap-2 align-items-center"></div>

								<div id="mediaLibraryLoading" class="text-center py-5">
									<div class="spinner-border text-primary" role="status">
										<span class="visually-hidden">Loading...</span>
									</div>
									<p class="mt-2 text-muted">Loading media...</p>
								</div>

								<div id="mediaLibraryError" class="alert alert-danger d-none"></div>

								<div id="mediaLibraryGrid" class="row row-cols-2 row-cols-sm-3 row-cols-md-4 row-cols-lg-5 g-3 d-none"></div>

								<div id="mediaLibraryEmpty" class="text-center py-5 d-none">
									<i class="bi bi-images" style="font-size: 4rem; color: #ccc;"></i>
									<p class="text-muted mt-3">No images found</p>
									<button type="button" class="btn btn-primary" onclick="document.getElementById('upload-tab').click()">
										Upload Your First Image
									</button>
								</div>

								<div id="mediaLibraryPagination" class="d-flex justify-content-center mt-4 d-none">
									<button type="button" class="btn btn-outline-primary" id="loadMoreMediaBtn">
										Load More
									</button>
								</div>
							</div>

							<div class="tab-pane fade" id="upload" role="tabpanel" aria-labelledby="upload-tab">
								<form id="mediaPickerUploadForm" enctype="multipart/form-data">
									<div class="mb-3">
										<label for="mediaPickerFile" class="form-label">Select Image</label>
										<input type="file"
											   class="form-control"
											   id="mediaPickerFile"
											   name="image"
											   accept="image/jpeg,image/png,image/gif,image/webp"
											   required>
										<div class="form-text">Accepted formats: JPG, PNG, GIF, WebP. Max size: 5MB</div>
									</div>
									<div class="mb-3">
										<label for="mediaPickerName" class="form-label">Name</label>
										<input type="text" class="form-control" id="mediaPickerName" placeholder="Optional. Defaults to the original filename.">
									</div>
									<div class="mb-3">
										<label for="mediaPickerTagsInput" class="form-label">Tags</label>
										<input type="text" class="form-control" id="mediaPickerTagsInput" placeholder="comma, separated, tags">
									</div>
									<div class="mb-3">
										<label for="mediaPickerFolder" class="form-label">Folder</label>
										<input type="text" class="form-control" id="mediaPickerFolder">
									</div>
									<div id="mediaPickerUploadProgress" class="progress d-none mb-3">
										<div class="progress-bar" role="progressbar" style="width: 0%"></div>
									</div>
									<div id="mediaPickerUploadError" class="alert alert-danger d-none"></div>
									<div id="mediaPickerUploadSuccess" class="alert alert-success d-none"></div>
								</form>
							</div>
						</div>
					</div>
				</div>
			</div>
			<div class="modal-footer">
				<button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
				<button type="button" class="btn btn-primary d-none" id="selectMediaBtn">Select Image</button>
				<button type="button" class="btn btn-primary d-none" id="mediaPickerUploadBtn">Upload</button>
			</div>
		</div>
	</div>
</div>

<style>
.media-picker-item {
	cursor: pointer;
	transition: transform 0.2s, box-shadow 0.2s;
	border: 3px solid transparent;
}

.media-picker-item:hover {
	transform: translateY(-2px);
	box-shadow: 0 4px 8px rgba(0,0,0,0.15);
}

.media-picker-item.selected {
	border-color: #0d6efd;
	box-shadow: 0 4px 12px rgba(13, 110, 253, 0.3);
}

.media-picker-item .check-overlay {
	position: absolute;
	top: 10px;
	right: 10px;
	background: #0d6efd;
	color: white;
	border-radius: 50%;
	width: 30px;
	height: 30px;
	display: none;
	align-items: center;
	justify-content: center;
}

.media-picker-item.selected .check-overlay {
	display: flex;
}
</style>

<script>
(function() {
	let selectedMediaUrl = null;
	let nextCursor = null;
	let pickerTarget = null;
	let currentFolder = '';
	let rootFolder = '';
	let currentTag = '';

	window.openMediaPicker = function(target) {
		pickerTarget = target;
		selectedMediaUrl = null;
		nextCursor = null;
		currentFolder = '';
		currentTag = '';

		const modal = new bootstrap.Modal(document.getElementById('mediaPickerModal'));
		modal.show();

		loadMediaLibrary();
	};

	function listUrl(cursor) {
		const params = new URLSearchParams();
		if (currentFolder) {
			params.set('folder', currentFolder);
		}
		if (currentTag) {
			params.set('tag', currentTag);
		}
		if (cursor) {
			params.set('cursor', cursor);
		}
		const query = params.toString();
		return '<?= route_path('admin_media_list') ?>' + (query ? '?' + query : '');
	}

	function renderBreadcrumb(folder, root) {
		const ol = document.getElementById('mediaPickerBreadcrumb');
		ol.innerHTML = '';

		const addCrumb = (label, path, active) => {
			const li = document.createElement('li');
			li.className = 'breadcrumb-item' + (active ? ' active' : '');
			if (active) {
				li.textContent = label;
			} else {
				const a = document.createElement('a');
				a.href = '#';
				a.textContent = label;
				a.addEventListener('click', function(e) {
					e.preventDefault();
					currentFolder = path;
					loadMediaLibrary();
				});
				li.appendChild(a);
			}
			ol.appendChild(li);
		};

		addCrumb('All', root, folder === root || folder === '');

		if (folder && folder !== root) {
			const relative = folder.startsWith(root + '/') ? folder.slice(root.length + 1) : folder;
			let accum = root;
			const parts = relative.split('/').filter(Boolean);
			parts.forEach((part, index) => {
				accum = accum ? accum + '/' + part : part;
				addCrumb(part, accum, index === parts.length - 1);
			});
		}
	}

	function renderTags(tags) {
		const wrap = document.getElementById('mediaPickerTags');
		wrap.innerHTML = '';
		if (!tags || !tags.length) {
			return;
		}

		const label = document.createElement('span');
		label.className = 'text-muted small';
		label.textContent = 'Tags:';
		wrap.appendChild(label);

		['', ...tags].forEach(tag => {
			const btn = document.createElement('button');
			btn.type = 'button';
			btn.className = 'btn btn-sm ' + ((tag === currentTag || (tag === '' && currentTag === '')) ? 'btn-primary' : 'btn-outline-primary');
			btn.textContent = tag === '' ? 'All' : tag;
			btn.addEventListener('click', function() {
				currentTag = tag;
				loadMediaLibrary();
			});
			wrap.appendChild(btn);
		});
	}

	function appendFolderCard(grid, folder, isParent) {
		const col = document.createElement('div');
		col.className = 'col';
		col.innerHTML = `
			<div class="card h-100 media-picker-item" data-folder="${folder.path}">
				<div class="card-body d-flex flex-column align-items-center justify-content-center py-4">
					<i class="bi ${isParent ? 'bi-arrow-up-left-square' : 'bi-folder-fill text-warning'}" style="font-size: 2rem;"></i>
					<small class="mt-2 fw-semibold text-truncate w-100 text-center">${folder.name}</small>
				</div>
			</div>
		`;
		col.querySelector('.media-picker-item').addEventListener('click', function() {
			currentFolder = folder.path;
			loadMediaLibrary();
		});
		grid.appendChild(col);
	}

	function loadMediaLibrary(cursor = null) {
		const loading = document.getElementById('mediaLibraryLoading');
		const error = document.getElementById('mediaLibraryError');
		const grid = document.getElementById('mediaLibraryGrid');
		const empty = document.getElementById('mediaLibraryEmpty');
		const pagination = document.getElementById('mediaLibraryPagination');

		if (!cursor) {
			loading.classList.remove('d-none');
			error.classList.add('d-none');
			grid.classList.add('d-none');
			empty.classList.add('d-none');
			pagination.classList.add('d-none');
			selectedMediaUrl = null;
			document.getElementById('selectMediaBtn').classList.add('d-none');
		}

		fetch(listUrl(cursor), {
			headers: { 'Accept': 'application/json' },
			credentials: 'same-origin'
		})
			.then(response => response.json())
			.then(data => {
				loading.classList.add('d-none');

				if (!data.success) {
					throw new Error(data.error || 'Failed to load media library');
				}

				rootFolder = data.root_folder || rootFolder;
				currentFolder = data.current_folder || currentFolder || rootFolder;
				document.getElementById('mediaPickerFolder').value = currentFolder;

				if (!cursor) {
					grid.innerHTML = '';
					renderBreadcrumb(currentFolder, rootFolder);
					renderTags(data.tags || []);

					if (currentFolder && currentFolder !== rootFolder) {
						const parent = currentFolder.includes('/') ? currentFolder.substring(0, currentFolder.lastIndexOf('/')) : rootFolder;
						appendFolderCard(grid, { name: 'Parent folder', path: parent || rootFolder }, true);
					}

					(data.folders || []).forEach(folder => appendFolderCard(grid, folder, false));
				}

				(data.resources || []).forEach(resource => {
					const col = document.createElement('div');
					col.className = 'col';
					const name = resource.name || resource.public_id || '';
					const dims = (resource.width || 0) + 'x' + (resource.height || 0);
					col.innerHTML = `
						<div class="card h-100 media-picker-item" data-url="${resource.url}">
							<div class="position-relative" style="padding-top: 100%; overflow: hidden;">
								<img src="${resource.url}"
									 class="position-absolute top-0 start-0 w-100 h-100"
									 style="object-fit: cover;"
									 alt="${name}"
									 loading="lazy">
								<div class="check-overlay">
									<i class="bi bi-check-lg"></i>
								</div>
							</div>
							<div class="card-body p-2">
								<small class="fw-semibold d-block text-truncate">${name}</small>
								<small class="text-muted">${dims}</small>
							</div>
						</div>
					`;
					col.querySelector('.media-picker-item').addEventListener('click', function() {
						selectMediaItem(this);
					});
					grid.appendChild(col);
				});

				const hasItems = grid.children.length > 0;
				if (!hasItems && !cursor) {
					empty.classList.remove('d-none');
					return;
				}

				grid.classList.remove('d-none');
				nextCursor = data.next_cursor || null;
				if (nextCursor) {
					pagination.classList.remove('d-none');
				} else {
					pagination.classList.add('d-none');
				}
			})
			.catch(err => {
				loading.classList.add('d-none');
				error.textContent = 'Failed to load media library: ' + err.message;
				error.classList.remove('d-none');
			});
	}

	function selectMediaItem(element) {
		document.querySelectorAll('.media-picker-item.selected').forEach(item => {
			item.classList.remove('selected');
		});

		element.classList.add('selected');
		selectedMediaUrl = element.dataset.url || null;
		document.getElementById('selectMediaBtn').classList.toggle('d-none', !selectedMediaUrl);
	}

	document.getElementById('loadMoreMediaBtn')?.addEventListener('click', function() {
		if (nextCursor) {
			loadMediaLibrary(nextCursor);
		}
	});

	function applyPickedUrl(url) {
		if (typeof pickerTarget === 'function') {
			pickerTarget(url);
			return;
		}

		const input = document.getElementById(pickerTarget);
		if (input) {
			input.value = url;
			input.dispatchEvent(new Event('change'));

			const preview = document.getElementById(pickerTarget + '_preview');
			if (preview) {
				preview.src = url;
				preview.classList.remove('d-none');
			}
		}
	}

	document.getElementById('selectMediaBtn')?.addEventListener('click', function() {
		if (selectedMediaUrl && pickerTarget) {
			applyPickedUrl(selectedMediaUrl);
			bootstrap.Modal.getInstance(document.getElementById('mediaPickerModal')).hide();
		}
	});

	document.querySelectorAll('#mediaPickerTabs button').forEach(tab => {
		tab.addEventListener('shown.bs.tab', function(e) {
			const target = e.target.getAttribute('data-bs-target');

			if (target === '#library') {
				document.getElementById('selectMediaBtn').classList.toggle('d-none', !selectedMediaUrl);
				document.getElementById('mediaPickerUploadBtn').classList.add('d-none');
			} else if (target === '#upload') {
				document.getElementById('selectMediaBtn').classList.add('d-none');
				document.getElementById('mediaPickerUploadBtn').classList.remove('d-none');
			}
		});
	});

	const uploadBtn = document.getElementById('mediaPickerUploadBtn');
	const imageFile = document.getElementById('mediaPickerFile');
	const uploadProgress = document.getElementById('mediaPickerUploadProgress');
	const uploadError = document.getElementById('mediaPickerUploadError');
	const uploadSuccess = document.getElementById('mediaPickerUploadSuccess');

	function freshCsrfToken() {
		return fetch('<?= route_path('admin_csrf_token') ?>', {
			headers: { 'Accept': 'application/json' },
			credentials: 'same-origin'
		})
		.then(r => r.json())
		.then(d => (d && d.token) ? d.token : '')
		.catch(() => document.querySelector('meta[name="csrf-token"]')?.content || '');
	}

	function parseJsonResponse(response) {
		return response.text().then(text => {
			try {
				return JSON.parse(text);
			} catch (e) {
				throw new Error('Your session may have expired. Please refresh the page and try again.');
			}
		});
	}

	uploadBtn?.addEventListener('click', function() {
		if (!imageFile.files.length) {
			uploadError.textContent = 'Please select a file';
			uploadError.classList.remove('d-none');
			return;
		}

		const formData = new FormData();
		formData.append('image', imageFile.files[0]);
		formData.append('name', document.getElementById('mediaPickerName').value);
		formData.append('tags', document.getElementById('mediaPickerTagsInput').value);
		formData.append('folder', document.getElementById('mediaPickerFolder').value || currentFolder || rootFolder);

		uploadError.classList.add('d-none');
		uploadSuccess.classList.add('d-none');
		uploadProgress.classList.remove('d-none');
		uploadBtn.disabled = true;

		freshCsrfToken().then(token => fetch('<?= route_path('admin_media_upload') ?>', {
			method: 'POST',
			body: formData,
			headers: { 'X-CSRF-Token': token }
		}))
		.then(parseJsonResponse)
		.then(data => {
			uploadProgress.classList.add('d-none');
			uploadBtn.disabled = false;

			if (data.success && data.data) {
				uploadSuccess.textContent = 'Image uploaded successfully!';
				uploadSuccess.classList.remove('d-none');

				if (pickerTarget) {
					applyPickedUrl(data.data.url);
				}

				setTimeout(() => {
					bootstrap.Modal.getInstance(document.getElementById('mediaPickerModal')).hide();
				}, 1000);
			} else {
				uploadError.textContent = data.error || 'Upload failed';
				uploadError.classList.remove('d-none');
			}
		})
		.catch(error => {
			uploadProgress.classList.add('d-none');
			uploadBtn.disabled = false;
			uploadError.textContent = 'Upload failed: ' + error.message;
			uploadError.classList.remove('d-none');
		});
	});
})();
</script>
