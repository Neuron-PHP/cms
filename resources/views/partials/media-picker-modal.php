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
							<!-- Library Tab -->
							<div class="tab-pane fade show active" id="library" role="tabpanel" aria-labelledby="library-tab">
								<div id="mediaLibraryLoading" class="text-center py-5">
									<div class="spinner-border text-primary" role="status">
										<span class="visually-hidden">Loading...</span>
									</div>
									<p class="mt-2 text-muted">Loading media...</p>
								</div>

								<div id="mediaLibraryError" class="alert alert-danger d-none"></div>

								<div id="mediaLibraryGrid" class="row row-cols-2 row-cols-sm-3 row-cols-md-4 row-cols-lg-5 g-3 d-none">
									<!-- Media items will be loaded here dynamically -->
								</div>

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

							<!-- Upload Tab -->
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
	let targetInputId = null;

	// Open media picker
	window.openMediaPicker = function(inputId) {
		targetInputId = inputId;
		selectedMediaUrl = null;
		nextCursor = null;

		// Show the modal
		const modal = new bootstrap.Modal(document.getElementById('mediaPickerModal'));
		modal.show();

		// Load media library
		loadMediaLibrary();
	};

	// Load media library
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
		}

		const url = cursor
			? '<?= route_path('admin_media') ?>?cursor=' + encodeURIComponent(cursor)
			: '<?= route_path('admin_media') ?>';

		// Fetch media from the media library endpoint
		fetch(url)
			.then(response => response.text())
			.then(html => {
				// Parse the HTML to extract media data
				const parser = new DOMParser();
				const doc = parser.parseFromString(html, 'text/html');
				const mediaItems = doc.querySelectorAll('.media-item');

				loading.classList.add('d-none');

				if (mediaItems.length === 0 && !cursor) {
					empty.classList.remove('d-none');
					return;
				}

				// Build grid items
				if (!cursor) {
					grid.innerHTML = '';
				}

				mediaItems.forEach(item => {
					const img = item.querySelector('img');
					if (!img) return;

					const url = img.src;
					const publicId = img.alt;
					const cardBody = item.querySelector('.card-body small');
					const dimensions = cardBody?.textContent.trim() || '';

					const col = document.createElement('div');
					col.className = 'col';

					col.innerHTML = `
						<div class="card h-100 media-picker-item" data-url="${url}">
							<div class="position-relative" style="padding-top: 100%; overflow: hidden;">
								<img src="${url}"
									 class="position-absolute top-0 start-0 w-100 h-100"
									 style="object-fit: cover;"
									 alt="${publicId}"
									 loading="lazy">
								<div class="check-overlay">
									<i class="bi bi-check-lg"></i>
								</div>
							</div>
							<div class="card-body p-2">
								<small class="text-muted">${dimensions}</small>
							</div>
						</div>
					`;

					col.querySelector('.media-picker-item').addEventListener('click', function() {
						selectMediaItem(this);
					});

					grid.appendChild(col);
				});

				grid.classList.remove('d-none');

				// Check for next cursor
				const loadMoreBtn = doc.querySelector('a[href*="cursor="]');
				if (loadMoreBtn) {
					const cursorMatch = loadMoreBtn.href.match(/cursor=([^&]+)/);
					nextCursor = cursorMatch ? decodeURIComponent(cursorMatch[1]) : null;
					pagination.classList.remove('d-none');
				} else {
					nextCursor = null;
					pagination.classList.add('d-none');
				}
			})
			.catch(err => {
				loading.classList.add('d-none');
				error.textContent = 'Failed to load media library: ' + err.message;
				error.classList.remove('d-none');
			});
	}

	// Select media item
	function selectMediaItem(element) {
		// Deselect all
		document.querySelectorAll('.media-picker-item.selected').forEach(item => {
			item.classList.remove('selected');
		});

		// Select this one
		element.classList.add('selected');
		selectedMediaUrl = element.dataset.url;

		// Show select button
		document.getElementById('selectMediaBtn').classList.remove('d-none');
	}

	// Load more button
	document.getElementById('loadMoreMediaBtn')?.addEventListener('click', function() {
		if (nextCursor) {
			loadMediaLibrary(nextCursor);
		}
	});

	// Select button
	document.getElementById('selectMediaBtn')?.addEventListener('click', function() {
		if (selectedMediaUrl && targetInputId) {
			const input = document.getElementById(targetInputId);
			if (input) {
				input.value = selectedMediaUrl;

				// Trigger change event for any listeners
				input.dispatchEvent(new Event('change'));

				// Update preview if it exists
				const preview = document.getElementById(targetInputId + '_preview');
				if (preview) {
					preview.src = selectedMediaUrl;
					preview.classList.remove('d-none');
				}
			}

			// Close modal
			bootstrap.Modal.getInstance(document.getElementById('mediaPickerModal')).hide();
		}
	});

	// Tab switching
	document.querySelectorAll('#mediaPickerTabs button').forEach(tab => {
		tab.addEventListener('shown.bs.tab', function(e) {
			const target = e.target.getAttribute('data-bs-target');

			if (target === '#library') {
				document.getElementById('selectMediaBtn').classList.remove('d-none');
				document.getElementById('mediaPickerUploadBtn').classList.add('d-none');
			} else if (target === '#upload') {
				document.getElementById('selectMediaBtn').classList.add('d-none');
				document.getElementById('mediaPickerUploadBtn').classList.remove('d-none');
			}
		});
	});

	// Upload functionality
	const uploadBtn = document.getElementById('mediaPickerUploadBtn');
	const uploadForm = document.getElementById('mediaPickerUploadForm');
	const imageFile = document.getElementById('mediaPickerFile');
	const uploadProgress = document.getElementById('mediaPickerUploadProgress');
	const uploadError = document.getElementById('mediaPickerUploadError');
	const uploadSuccess = document.getElementById('mediaPickerUploadSuccess');

	uploadBtn?.addEventListener('click', function() {
		if (!imageFile.files.length) {
			uploadError.textContent = 'Please select a file';
			uploadError.classList.remove('d-none');
			return;
		}

		const formData = new FormData();
		formData.append('image', imageFile.files[0]);

		// Hide previous messages
		uploadError.classList.add('d-none');
		uploadSuccess.classList.add('d-none');
		uploadProgress.classList.remove('d-none');

		// Disable button during upload
		uploadBtn.disabled = true;

		fetch('<?= route_path('admin_media_featured_upload') ?>', {
			method: 'POST',
			body: formData,
			headers: {
				'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || ''
			}
		})
		.then(response => response.json())
		.then(data => {
			uploadProgress.classList.add('d-none');
			uploadBtn.disabled = false;

			if (data.success && data.data) {
				uploadSuccess.textContent = 'Image uploaded successfully!';
				uploadSuccess.classList.remove('d-none');

				// Set the URL in the target input
				if (targetInputId) {
					const input = document.getElementById(targetInputId);
					if (input) {
						input.value = data.data.url;
						input.dispatchEvent(new Event('change'));

						// Update preview
						const preview = document.getElementById(targetInputId + '_preview');
						if (preview) {
							preview.src = data.data.url;
							preview.classList.remove('d-none');
						}
					}
				}

				// Close modal after 1 second
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
