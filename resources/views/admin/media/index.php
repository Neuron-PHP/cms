<div class="container-fluid">
	<div class="d-flex justify-content-between align-items-center mb-4">
		<h2>Media Library</h2>
		<button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#uploadModal">
			<i class="bi bi-upload"></i> Upload Image
		</button>
	</div>

	<?php if(isset($error)): ?>
		<div class="alert alert-danger alert-dismissible fade show" role="alert">
			<?= htmlspecialchars($error) ?>
			<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
		</div>
	<?php endif; ?>

	<div class="card">
		<div class="card-body">
			<?php if(empty($resources)): ?>
				<div class="text-center py-5">
					<i class="bi bi-images" style="font-size: 4rem; color: #ccc;"></i>
					<p class="text-muted mb-3 mt-3">No images uploaded yet.</p>
					<button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#uploadModal">
						<i class="bi bi-upload"></i> Upload Your First Image
					</button>
				</div>
			<?php else: ?>
				<div class="row row-cols-1 row-cols-sm-2 row-cols-md-3 row-cols-lg-4 row-cols-xl-5 g-4">
					<?php foreach($resources as $resource): ?>
						<div class="col">
							<div class="card h-100 media-item">
								<div class="position-relative" style="padding-top: 100%; overflow: hidden;">
									<img src="<?= htmlspecialchars($resource['url']) ?>"
										 class="position-absolute top-0 start-0 w-100 h-100"
										 style="object-fit: cover;"
										 alt="<?= htmlspecialchars($resource['public_id']) ?>"
										 loading="lazy">
								</div>
								<div class="card-body p-2">
									<div class="d-flex flex-column gap-1">
										<small class="text-muted text-truncate" title="<?= htmlspecialchars($resource['public_id']) ?>">
											<?= htmlspecialchars(basename($resource['public_id'])) ?>
										</small>
										<small class="text-muted">
											<?= $resource['width'] ?>x<?= $resource['height'] ?>
											<?= strtoupper($resource['format']) ?>
										</small>
										<small class="text-muted">
											<?= number_format($resource['bytes'] / 1024, 1) ?> KB
										</small>
									</div>
								</div>
								<div class="card-footer p-2 bg-light">
									<div class="btn-group btn-group-sm w-100" role="group">
										<button type="button"
												class="btn btn-outline-primary copy-url-btn"
												data-url="<?= htmlspecialchars($resource['url']) ?>"
												title="Copy URL">
											<i class="bi bi-clipboard"></i>
										</button>
										<a href="<?= htmlspecialchars($resource['url']) ?>"
										   target="_blank"
										   class="btn btn-outline-secondary"
										   title="View Full Size">
											<i class="bi bi-eye"></i>
										</a>
										<button type="button"
												class="btn btn-outline-danger delete-btn"
												data-public-id="<?= htmlspecialchars($resource['public_id']) ?>"
												title="Delete">
											<i class="bi bi-trash"></i>
										</button>
									</div>
								</div>
							</div>
						</div>
					<?php endforeach; ?>
				</div>

				<?php if($nextCursor): ?>
					<div class="d-flex justify-content-center mt-4">
						<a href="?cursor=<?= urlencode($nextCursor) ?>" class="btn btn-outline-primary">
							Load More
						</a>
					</div>
				<?php endif; ?>

				<div class="mt-3 text-muted text-center">
					<small>Total: <?= $totalCount ?> images</small>
				</div>
			<?php endif; ?>
		</div>
	</div>
</div>

<!-- Upload Modal -->
<div class="modal fade" id="uploadModal" tabindex="-1" aria-labelledby="uploadModalLabel" aria-hidden="true">
	<div class="modal-dialog">
		<div class="modal-content">
			<div class="modal-header">
				<h5 class="modal-title" id="uploadModalLabel">Upload Image</h5>
				<button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
			</div>
			<div class="modal-body">
				<form id="uploadForm" enctype="multipart/form-data">
					<div class="mb-3">
						<label for="imageFile" class="form-label">Select Image</label>
						<input type="file"
							   class="form-control"
							   id="imageFile"
							   name="image"
							   accept="image/jpeg,image/png,image/gif,image/webp"
							   required>
						<div class="form-text">Accepted formats: JPG, PNG, GIF, WebP. Max size: 5MB</div>
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

<style>
.media-item {
	transition: transform 0.2s, box-shadow 0.2s;
}

.media-item:hover {
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
	// Copy URL functionality
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

	// Delete functionality
	document.querySelectorAll('.delete-btn').forEach(btn => {
		btn.addEventListener('click', function() {
			if (!confirm('Are you sure you want to delete this image? This action cannot be undone.')) {
				return;
			}

			const publicId = this.dataset.publicId;
			// TODO: Implement delete API endpoint
			alert('Delete functionality will be implemented in the next step.');
		});
	});

	// Upload functionality
	const uploadBtn = document.getElementById('uploadBtn');
	const uploadForm = document.getElementById('uploadForm');
	const imageFile = document.getElementById('imageFile');
	const uploadProgress = document.getElementById('uploadProgress');
	const uploadError = document.getElementById('uploadError');
	const uploadSuccess = document.getElementById('uploadSuccess');

	uploadBtn.addEventListener('click', function() {
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

			if (data.success) {
				uploadSuccess.textContent = 'Image uploaded successfully!';
				uploadSuccess.classList.remove('d-none');
				// Reload page after 1 second to show new image
				setTimeout(() => {
					window.location.reload();
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
});
</script>
