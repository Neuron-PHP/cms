<?php
	$resources = $resources ?? [];
	$folders = $folders ?? [];
	$moveFolders = $moveFolders ?? [];
	$tags = $tags ?? [];
	$currentFolder = $currentFolder ?? ($rootFolder ?? '');
	$rootFolder = $rootFolder ?? '';
	$currentTag = $currentTag ?? '';
	$totalCount = $totalCount ?? 0;
	$nextCursor = $nextCursor ?? null;

	$folderQuery = function( string $folder = '', string $tag = '' ) use ( $rootFolder ): string {
		$params = [];
		if( $folder !== '' && $folder !== $rootFolder )
		{
			$params['folder'] = $folder;
		}
		if( $tag !== '' )
		{
			$params['tag'] = $tag;
		}
		return $params === [] ? '' : '?' . http_build_query( $params );
	};

	$relativeFolder = $rootFolder !== '' && str_starts_with( $currentFolder, $rootFolder )
		? ltrim( substr( $currentFolder, strlen( $rootFolder ) ), '/' )
		: $currentFolder;

	$crumbs = [];
	$crumbs[] = [ 'label' => 'All', 'path' => $rootFolder ];
	if( $relativeFolder !== '' )
	{
		$parts = explode( '/', $relativeFolder );
		$accum = $rootFolder;
		foreach( $parts as $part )
		{
			$accum = $accum === '' ? $part : $accum . '/' . $part;
			$crumbs[] = [ 'label' => $part, 'path' => $accum ];
		}
	}

	$parentFolder = $currentFolder !== $rootFolder
		? ( dirname( $currentFolder ) !== '.' ? dirname( $currentFolder ) : $rootFolder )
		: '';
?>
<div class="container-fluid">
	<div class="d-flex justify-content-between align-items-center mb-4">
		<h2>Media Library</h2>
		<div class="d-flex gap-2">
			<button type="button" class="btn btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#folderModal">
				<i class="bi bi-folder-plus"></i> New Folder
			</button>
			<button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#uploadModal">
				<i class="bi bi-upload"></i> Upload Images
			</button>
		</div>
	</div>

	<?php if(isset($error)): ?>
		<div class="alert alert-danger alert-dismissible fade show" role="alert">
			<?= htmlspecialchars($error) ?>
			<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
		</div>
	<?php endif; ?>

	<nav aria-label="Folder breadcrumb" class="mb-3">
		<ol class="breadcrumb mb-0">
			<?php foreach( $crumbs as $index => $crumb ): ?>
				<?php if( $index === array_key_last( $crumbs ) ): ?>
					<li class="breadcrumb-item active" aria-current="page"><?= htmlspecialchars( $crumb['label'] ) ?></li>
				<?php else: ?>
					<li class="breadcrumb-item">
						<a href="<?= htmlspecialchars( $folderQuery( $crumb['path'], $currentTag ) ) ?>"><?= htmlspecialchars( $crumb['label'] ) ?></a>
					</li>
				<?php endif; ?>
			<?php endforeach; ?>
		</ol>
	</nav>

	<?php if( $tags ): ?>
		<div class="mb-3 d-flex flex-wrap gap-2 align-items-center">
			<span class="text-muted small">Tags:</span>
			<a href="<?= htmlspecialchars( $folderQuery( $currentFolder, '' ) ) ?>" class="btn btn-sm <?= $currentTag === '' ? 'btn-secondary' : 'btn-outline-secondary' ?>">All</a>
			<?php foreach( $tags as $tag ): ?>
				<a href="<?= htmlspecialchars( $folderQuery( $currentFolder, $tag ) ) ?>" class="btn btn-sm <?= $currentTag === $tag ? 'btn-primary' : 'btn-outline-primary' ?>"><?= htmlspecialchars( $tag ) ?></a>
			<?php endforeach; ?>
		</div>
	<?php endif; ?>

	<div class="card">
		<div class="card-body">
			<?php if( empty( $resources ) && empty( $folders ) ): ?>
				<div class="text-center py-5">
					<i class="bi bi-images" style="font-size: 4rem; color: #ccc;"></i>
					<p class="text-muted mb-3 mt-3">No images in this folder yet.</p>
					<button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#uploadModal">
						<i class="bi bi-upload"></i> Upload Images
					</button>
				</div>
			<?php else: ?>
				<div class="row row-cols-1 row-cols-sm-2 row-cols-md-3 row-cols-lg-4 row-cols-xl-5 g-4">
					<?php if( $parentFolder !== '' && $currentFolder !== $rootFolder ): ?>
						<div class="col">
							<a href="<?= htmlspecialchars( $folderQuery( $parentFolder, $currentTag ) ) ?>" class="card h-100 text-decoration-none media-folder">
								<div class="card-body d-flex flex-column align-items-center justify-content-center py-5">
									<i class="bi bi-arrow-up-left-square" style="font-size: 2.5rem;"></i>
									<div class="mt-2 fw-semibold">Parent folder</div>
								</div>
							</a>
						</div>
					<?php endif; ?>

					<?php foreach( $folders as $folder ): ?>
						<div class="col">
							<a href="<?= htmlspecialchars( $folderQuery( $folder['path'], $currentTag ) ) ?>" class="card h-100 text-decoration-none media-folder">
								<div class="card-body d-flex flex-column align-items-center justify-content-center py-5">
									<i class="bi bi-folder-fill text-warning" style="font-size: 2.5rem;"></i>
									<div class="mt-2 fw-semibold text-truncate w-100 text-center" title="<?= htmlspecialchars( $folder['name'] ) ?>">
										<?= htmlspecialchars( $folder['name'] ) ?>
									</div>
								</div>
							</a>
						</div>
					<?php endforeach; ?>

					<?php foreach( $resources as $resource ): ?>
						<?php
							$displayName = $resource['name'] ?? basename( $resource['public_id'] ?? '' );
							$resourceTags = $resource['tags'] ?? [];
							$assetFolder = $resource['asset_folder'] ?? '';
						?>
						<div class="col">
							<div class="card h-100 media-item"
								 data-public-id="<?= htmlspecialchars( $resource['public_id'] ) ?>"
								 data-url="<?= htmlspecialchars( $resource['url'] ) ?>"
								 data-name="<?= htmlspecialchars( $displayName ) ?>"
								 data-tags="<?= htmlspecialchars( implode( ',', $resourceTags ) ) ?>"
								 data-asset-folder="<?= htmlspecialchars( $assetFolder ) ?>"
								 data-url-change-on-move="<?= !empty( $resource['url_change_on_move'] ) ? '1' : '0' ?>">
								<div class="position-relative" style="padding-top: 100%; overflow: hidden;">
									<img src="<?= htmlspecialchars( $resource['url'] ) ?>"
										 class="position-absolute top-0 start-0 w-100 h-100"
										 style="object-fit: cover;"
										 alt="<?= htmlspecialchars( $displayName ) ?>"
										 loading="lazy">
								</div>
								<div class="card-body p-2">
									<div class="d-flex flex-column gap-1">
										<small class="fw-semibold text-truncate" title="<?= htmlspecialchars( $displayName ) ?>">
											<?= htmlspecialchars( $displayName ) ?>
										</small>
										<?php if( $resourceTags ): ?>
											<div class="d-flex flex-wrap gap-1">
												<?php foreach( $resourceTags as $tag ): ?>
													<span class="badge text-bg-light border"><?= htmlspecialchars( $tag ) ?></span>
												<?php endforeach; ?>
											</div>
										<?php endif; ?>
										<small class="text-muted">
											<?= (int) $resource['width'] ?>x<?= (int) $resource['height'] ?>
											<?= strtoupper( (string) $resource['format'] ) ?>
										</small>
										<small class="text-muted">
											<?= number_format( ( (int) $resource['bytes'] ) / 1024, 1 ) ?> KB
										</small>
									</div>
								</div>
								<div class="card-footer p-2 bg-light">
									<div class="btn-group btn-group-sm w-100" role="group">
										<button type="button"
												class="btn btn-outline-primary copy-url-btn"
												data-url="<?= htmlspecialchars( $resource['url'] ) ?>"
												title="Copy URL">
											<i class="bi bi-clipboard"></i>
										</button>
										<button type="button"
												class="btn btn-outline-secondary edit-btn"
												title="Edit name, tags, or folder">
											<i class="bi bi-pencil"></i>
										</button>
										<a href="<?= htmlspecialchars( $resource['url'] ) ?>"
										   target="_blank"
										   class="btn btn-outline-secondary"
										   title="View Full Size">
											<i class="bi bi-eye"></i>
										</a>
										<button type="button"
												class="btn btn-outline-danger delete-btn"
												data-public-id="<?= htmlspecialchars( $resource['public_id'] ) ?>"
												data-asset-folder="<?= htmlspecialchars( $assetFolder ) ?>"
												title="Delete">
											<i class="bi bi-trash"></i>
										</button>
									</div>
								</div>
							</div>
						</div>
					<?php endforeach; ?>
				</div>

				<?php if( $nextCursor ): ?>
					<div class="d-flex justify-content-center mt-4">
						<?php
							$moreParams = [ 'cursor' => $nextCursor ];
							if( $currentFolder !== $rootFolder )
							{
								$moreParams['folder'] = $currentFolder;
							}
							if( $currentTag !== '' )
							{
								$moreParams['tag'] = $currentTag;
							}
						?>
						<a href="?<?= htmlspecialchars( http_build_query( $moreParams ) ) ?>" class="btn btn-outline-primary">
							Load More
						</a>
					</div>
				<?php endif; ?>

				<div class="mt-3 text-muted text-center" id="mediaTotal">
					<small>Total: <?= (int) $totalCount ?> images</small>
				</div>
			<?php endif; ?>
		</div>
	</div>
</div>

<div class="modal fade" id="uploadModal" tabindex="-1" aria-labelledby="uploadModalLabel" aria-hidden="true">
	<div class="modal-dialog">
		<div class="modal-content">
			<div class="modal-header">
				<h5 class="modal-title" id="uploadModalLabel">Upload Images</h5>
				<button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
			</div>
			<div class="modal-body">
				<form id="uploadForm" enctype="multipart/form-data">
					<div class="mb-3">
						<label for="imageFile" class="form-label">Select Images</label>
						<input type="file"
							   class="form-control"
							   id="imageFile"
							   name="image"
							   accept="image/jpeg,image/png,image/gif,image/webp"
							   multiple
							   required>
						<div class="form-text">Accepted formats: JPG, PNG, GIF, WebP. Max size: 5MB each. You can select multiple files.</div>
					</div>
					<div class="mb-3">
						<label for="imageName" class="form-label">Name</label>
						<input type="text" class="form-control" id="imageName" name="name" placeholder="Optional. Defaults to the original filename.">
					</div>
					<div class="mb-3">
						<label for="imageTags" class="form-label">Tags</label>
						<input type="text" class="form-control" id="imageTags" name="tags" placeholder="comma, separated, tags">
					</div>
					<div class="mb-3">
						<label for="imageFolder" class="form-label">Folder</label>
						<input type="text" class="form-control" id="imageFolder" name="folder" value="<?= htmlspecialchars( $currentFolder ) ?>">
						<div class="form-text">Uploads go into the folder you are viewing unless you change this path.</div>
					</div>
					<div id="uploadProgress" class="progress d-none mb-3">
						<div class="progress-bar" role="progressbar" style="width: 0%"></div>
					</div>
					<div id="uploadError" class="alert alert-danger d-none"></div>
					<div id="uploadSuccess" class="alert alert-success d-none"></div>
				</form>
			</div>
			<div class="modal-footer">
				<button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
				<button type="button" class="btn btn-primary" id="uploadBtn">Upload</button>
			</div>
		</div>
	</div>
</div>

<div class="modal fade" id="folderModal" tabindex="-1" aria-labelledby="folderModalLabel" aria-hidden="true">
	<div class="modal-dialog">
		<div class="modal-content">
			<div class="modal-header">
				<h5 class="modal-title" id="folderModalLabel">New Folder</h5>
				<button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
			</div>
			<div class="modal-body">
				<div class="mb-3">
					<label for="newFolderName" class="form-label">Folder name</label>
					<input type="text" class="form-control" id="newFolderName" placeholder="blog">
					<div class="form-text">Created under <?= htmlspecialchars( $currentFolder !== '' ? $currentFolder : $rootFolder ) ?>.</div>
				</div>
				<div id="folderError" class="alert alert-danger d-none"></div>
			</div>
			<div class="modal-footer">
				<button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
				<button type="button" class="btn btn-primary" id="createFolderBtn">Create</button>
			</div>
		</div>
	</div>
</div>

<div class="modal fade" id="editModal" tabindex="-1" aria-labelledby="editModalLabel" aria-hidden="true">
	<div class="modal-dialog">
		<div class="modal-content">
			<div class="modal-header">
				<h5 class="modal-title" id="editModalLabel">Edit Image</h5>
				<button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
			</div>
			<div class="modal-body">
				<input type="hidden" id="editPublicId">
				<input type="hidden" id="editAssetFolder">
				<input type="hidden" id="editUrlChangeOnMove" value="0">
				<div class="mb-3">
					<label for="editName" class="form-label">Name</label>
					<input type="text" class="form-control" id="editName">
				</div>
				<div class="mb-3">
					<label for="editTags" class="form-label">Tags</label>
					<input type="text" class="form-control" id="editTags" placeholder="comma, separated, tags">
				</div>
				<div class="mb-3">
					<label for="editFolder" class="form-label">Folder</label>
					<select class="form-select" id="editFolder">
						<?php if( $moveFolders ): ?>
							<?php foreach( $moveFolders as $path => $label ): ?>
								<option value="<?= htmlspecialchars( (string) $path ) ?>"><?= htmlspecialchars( (string) $label ) ?></option>
							<?php endforeach; ?>
						<?php else: ?>
							<option value="<?= htmlspecialchars( $rootFolder ) ?>">Library root</option>
						<?php endif; ?>
					</select>
					<input type="text" class="form-control mt-2" id="editNewFolder" placeholder="Or new subfolder name">
					<div class="form-text">Choose an existing folder, or type a name to create a subfolder under the selection. Moving on older Cloudinary accounts can change the image URL.</div>
				</div>
				<div id="editError" class="alert alert-danger d-none"></div>
			</div>
			<div class="modal-footer">
				<button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
				<button type="button" class="btn btn-primary" id="saveEditBtn">Save</button>
			</div>
		</div>
	</div>
</div>

<style>
.media-item, .media-folder {
	transition: transform 0.2s, box-shadow 0.2s;
	color: inherit;
}

.media-item:hover, .media-folder:hover {
	transform: translateY(-2px);
	box-shadow: 0 4px 8px rgba(0,0,0,0.1);
}

.copy-url-btn.copied {
	background-color: #198754;
	color: white;
	border-color: #198754;
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
	const currentFolder = <?= json_encode( $currentFolder ) ?>;
	const rootFolder = <?= json_encode( $rootFolder ) ?>;

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

	function postForm(url, fields) {
		const formData = new FormData();
		Object.keys(fields).forEach(key => formData.append(key, fields[key]));

		return freshCsrfToken().then(token => fetch(url, {
			method: 'POST',
			body: formData,
			headers: { 'X-CSRF-TOKEN': token }
		})).then(parseJsonResponse);
	}

	document.querySelectorAll('.copy-url-btn').forEach(btn => {
		btn.addEventListener('click', function() {
			const url = this.dataset.url;
			navigator.clipboard.writeText(url).then(() => {
				const originalHtml = this.innerHTML;
				this.innerHTML = '<i class="bi bi-check"></i>';
				this.classList.add('copied');
				setTimeout(() => {
					this.innerHTML = originalHtml;
					this.classList.remove('copied');
				}, 2000);
			});
		});
	});

	document.querySelectorAll('.delete-btn').forEach(btn => {
		btn.addEventListener('click', function() {
			if (!confirm('Are you sure you want to delete this image? This action cannot be undone.')) {
				return;
			}

			const button = this;
			const card = button.closest('.col');
			const originalHtml = button.innerHTML;

			button.disabled = true;
			button.innerHTML = '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span>';

			postForm('<?= route_path('admin_media_delete') ?>', {
				public_id: button.dataset.publicId,
				asset_folder: button.dataset.assetFolder || ''
			})
			.then(data => {
				if (data.success) {
					if (card) {
						card.remove();
					}
				} else {
					button.disabled = false;
					button.innerHTML = originalHtml;
					alert(data.error || 'Failed to delete image.');
				}
			})
			.catch(error => {
				button.disabled = false;
				button.innerHTML = originalHtml;
				alert('Failed to delete image: ' + error.message);
			});
		});
	});

	const editModalEl = document.getElementById('editModal');
	const editModal = editModalEl ? new bootstrap.Modal(editModalEl) : null;

	document.querySelectorAll('.edit-btn').forEach(btn => {
		btn.addEventListener('click', function() {
			const item = this.closest('.media-item');
			document.getElementById('editPublicId').value = item.dataset.publicId;
			document.getElementById('editAssetFolder').value = item.dataset.assetFolder || '';
			document.getElementById('editUrlChangeOnMove').value = item.dataset.urlChangeOnMove || '0';
			document.getElementById('editName').value = item.dataset.name || '';
			document.getElementById('editTags').value = item.dataset.tags || '';
			const folderSelect = document.getElementById('editFolder');
			const currentAssetFolder = item.dataset.assetFolder || currentFolder;
			if (folderSelect) {
				folderSelect.value = currentAssetFolder;
				if (folderSelect.value !== currentAssetFolder && currentAssetFolder) {
					const extra = document.createElement('option');
					extra.value = currentAssetFolder;
					extra.textContent = currentAssetFolder;
					folderSelect.appendChild(extra);
					folderSelect.value = currentAssetFolder;
				}
			}
			const newFolderInput = document.getElementById('editNewFolder');
			if (newFolderInput) {
				newFolderInput.value = '';
			}
			document.getElementById('editError').classList.add('d-none');
			editModal?.show();
		});
	});

	document.getElementById('saveEditBtn')?.addEventListener('click', function() {
		const publicId = document.getElementById('editPublicId').value;
		const assetFolder = document.getElementById('editAssetFolder').value;
		const name = document.getElementById('editName').value;
		const tags = document.getElementById('editTags').value;
		const selectedFolder = document.getElementById('editFolder').value.trim();
		const newFolder = (document.getElementById('editNewFolder')?.value || '').trim().replace(/[^a-zA-Z0-9_\-/]+/g, '-').replace(/^-+|-+$/g, '');
		const folder = newFolder
			? (selectedFolder ? selectedFolder + '/' + newFolder : newFolder)
			: selectedFolder;
		const error = document.getElementById('editError');
		const button = this;
		const originalFolder = (assetFolder || currentFolder || '').replace(/^\/+|\/+$/g, '');
		const normalizeFolder = (value) => (value || '').replace(/^\/+|\/+$/g, '');

		error.classList.add('d-none');
		button.disabled = true;

		const saveMeta = () => postForm('<?= route_path('admin_media_update') ?>', {
			public_id: publicId,
			asset_folder: assetFolder,
			name: name,
			tags: tags
		});

		const maybeMove = (data) => {
			if (!folder || normalizeFolder(folder) === originalFolder) {
				return data;
			}

			const urlChanges = document.getElementById('editUrlChangeOnMove').value === '1';
			const warning = urlChanges
				? 'Moving this image will change its URL. Existing pages, posts, and products that use it will break unless those URLs are updated. Continue?'
				: 'Move this image to ' + folder + '?';

			if (!confirm(warning)) {
				return data;
			}

			return postForm('<?= route_path('admin_media_move') ?>', {
				public_id: publicId,
				asset_folder: assetFolder,
				folder: folder
			});
		};

		saveMeta()
			.then(data => {
				if (!data.success) {
					throw new Error(data.error || 'Update failed');
				}
				return maybeMove(data);
			})
			.then(data => {
				if (data && data.success === false) {
					throw new Error(data.error || 'Move failed');
				}
				const dest = data?.data?.asset_folder || folder;
				if (dest && normalizeFolder(dest) !== normalizeFolder(currentFolder)) {
					const params = new URLSearchParams();
					if (dest !== rootFolder) {
						params.set('folder', dest);
					}
					window.location.href = '<?= route_path('admin_media') ?>' + (params.toString() ? '?' + params.toString() : '');
					return;
				}
				window.location.reload();
			})
			.catch(err => {
				button.disabled = false;
				error.textContent = err.message;
				error.classList.remove('d-none');
			});
	});

	document.getElementById('createFolderBtn')?.addEventListener('click', function() {
		const name = document.getElementById('newFolderName').value.trim();
		const error = document.getElementById('folderError');
		const button = this;
		error.classList.add('d-none');

		if (!name) {
			error.textContent = 'A folder name is required';
			error.classList.remove('d-none');
			return;
		}

		const path = (currentFolder || rootFolder) + '/' + name;
		button.disabled = true;

		postForm('<?= route_path('admin_media_folders') ?>', { name: path })
			.then(data => {
				if (!data.success) {
					throw new Error(data.error || 'Folder could not be created');
				}
				window.location.reload();
			})
			.catch(err => {
				button.disabled = false;
				error.textContent = err.message;
				error.classList.remove('d-none');
			});
	});

	const uploadBtn = document.getElementById('uploadBtn');
	const imageFile = document.getElementById('imageFile');
	const uploadProgress = document.getElementById('uploadProgress');
	const uploadError = document.getElementById('uploadError');
	const uploadSuccess = document.getElementById('uploadSuccess');

	uploadBtn?.addEventListener('click', function() {
		if (!imageFile.files.length) {
			uploadError.textContent = 'Please select at least one file';
			uploadError.classList.remove('d-none');
			return;
		}

		const formData = new FormData();
		Array.from(imageFile.files).forEach(file => formData.append('image[]', file));
		formData.append('name', document.getElementById('imageName').value);
		formData.append('tags', document.getElementById('imageTags').value);
		formData.append('folder', document.getElementById('imageFolder').value || currentFolder);

		uploadError.classList.add('d-none');
		uploadSuccess.classList.add('d-none');
		uploadProgress.classList.remove('d-none');
		uploadBtn.disabled = true;

		freshCsrfToken().then(token => fetch('<?= route_path('admin_media_upload') ?>', {
			method: 'POST',
			body: formData,
			headers: { 'X-CSRF-TOKEN': token }
		}))
		.then(parseJsonResponse)
		.then(data => {
			uploadProgress.classList.add('d-none');
			uploadBtn.disabled = false;

			if (data.success) {
				uploadSuccess.textContent = 'Image uploaded successfully!';
				uploadSuccess.classList.remove('d-none');
				setTimeout(() => window.location.reload(), 1000);
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
});
</script>
