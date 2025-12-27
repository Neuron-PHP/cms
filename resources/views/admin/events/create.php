<div class="container-fluid">
	<div class="d-flex justify-content-between align-items-center mb-4">
		<h2>Create Event</h2>
		<a href="<?= route_path('admin_events') ?>" class="btn btn-secondary">Back to Events</a>
	</div>

	<form method="POST" action="<?= route_path('admin_events_store') ?>" id="event-form">
		<?= csrf_field() ?>

		<div class="row">
			<div class="col-md-8">
				<div class="card mb-4">
					<div class="card-header">
						<h5 class="mb-0">Event Details</h5>
					</div>
					<div class="card-body">
						<div class="mb-3">
							<label for="title" class="form-label">Title <span class="text-danger">*</span></label>
							<input type="text" class="form-control" id="title" name="title" required>
							<small class="form-text text-muted">The name of your event</small>
						</div>

						<div class="mb-3">
							<label for="slug" class="form-label">Slug</label>
							<div class="input-group">
								<span class="input-group-text">/calendar/event/</span>
								<input type="text" class="form-control" id="slug" name="slug" pattern="[a-z0-9-]+">
							</div>
							<small class="form-text text-muted">URL-friendly version. Leave blank to auto-generate from title.</small>
						</div>

						<div class="mb-3">
							<label for="description" class="form-label">Short Description</label>
							<textarea class="form-control" id="description" name="description" rows="3" maxlength="300"></textarea>
							<small class="form-text text-muted">A brief summary (300 chars max). Displayed in event listings.</small>
						</div>

						<div class="mb-3">
							<label class="form-label">Full Description</label>
							<div id="editorjs" style="border: 1px solid #ddd; border-radius: 0.25rem; padding: 20px; min-height: 300px; background: #fff;"></div>
							<input type="hidden" name="content" id="content-json">
							<small class="form-text text-muted">Detailed event information</small>
						</div>

						<div class="row">
							<div class="col-md-6 mb-3">
								<label for="start_date" class="form-label">Start Date/Time <span class="text-danger">*</span></label>
								<input type="datetime-local" class="form-control" id="start_date" name="start_date" required>
							</div>

							<div class="col-md-6 mb-3">
								<label for="end_date" class="form-label">End Date/Time</label>
								<input type="datetime-local" class="form-control" id="end_date" name="end_date">
								<small class="form-text text-muted">Optional. Leave blank for single-time events</small>
							</div>
						</div>

						<div class="mb-3">
							<div class="form-check">
								<input class="form-check-input" type="checkbox" id="all_day" name="all_day" value="1">
								<label class="form-check-label" for="all_day">
									All Day Event
								</label>
							</div>
						</div>

						<div class="mb-3">
							<label for="location" class="form-label">Location</label>
							<input type="text" class="form-control" id="location" name="location" placeholder="e.g., Main Auditorium, 123 Main St, Sarasota, FL">
							<small class="form-text text-muted">Physical location or virtual meeting link</small>
						</div>

						<div class="mb-3">
							<label for="organizer" class="form-label">Organizer</label>
							<input type="text" class="form-control" id="organizer" name="organizer" placeholder="e.g., Sarasota Teen Court">
						</div>

						<div class="row">
							<div class="col-md-6 mb-3">
								<label for="contact_email" class="form-label">Contact Email</label>
								<input type="email" class="form-control" id="contact_email" name="contact_email">
							</div>

							<div class="col-md-6 mb-3">
								<label for="contact_phone" class="form-label">Contact Phone</label>
								<input type="tel" class="form-control" id="contact_phone" name="contact_phone">
							</div>
						</div>
					</div>
				</div>
			</div>

			<div class="col-md-4">
				<div class="card mb-4">
					<div class="card-header">
						<h5 class="mb-0">Publish Settings</h5>
					</div>
					<div class="card-body">
						<div class="mb-3">
							<label for="status" class="form-label">Status</label>
							<select class="form-select" id="status" name="status">
								<option value="draft" selected>Draft</option>
								<option value="published">Published</option>
							</select>
							<small class="form-text text-muted">Only published events are visible to visitors</small>
						</div>

						<div class="mb-3">
							<label for="category_id" class="form-label">Category</label>
							<select class="form-select" id="category_id" name="category_id">
								<option value="">Uncategorized</option>
								<?php foreach($categories as $category): ?>
									<option value="<?= $category->getId() ?>">
										<?= htmlspecialchars($category->getName()) ?>
									</option>
								<?php endforeach; ?>
							</select>
						</div>
					</div>
				</div>

				<div class="card mb-4">
					<div class="card-header">
						<h5 class="mb-0">Featured Image</h5>
					</div>
					<div class="card-body">
						<div class="mb-3">
							<label for="featured_image" class="form-label">Image URL</label>
							<input type="url" class="form-control" id="featured_image" name="featured_image" placeholder="https://">
							<small class="form-text text-muted">Upload via Media Library or paste URL</small>
						</div>

						<div id="featured-image-preview" class="mb-2" style="display: none;">
							<img src="" alt="Preview" class="img-thumbnail" style="max-width: 100%;">
						</div>

						<a href="<?= route_path('admin_media') ?>" class="btn btn-sm btn-outline-secondary w-100" target="_blank">
							<i class="bi bi-images"></i> Open Media Library
						</a>
					</div>
				</div>

				<button type="submit" class="btn btn-primary w-100 mb-2">
					<i class="bi bi-check-circle"></i> Create Event
				</button>
				<a href="<?= route_path('admin_events') ?>" class="btn btn-outline-secondary w-100">
					Cancel
				</a>
			</div>
		</div>
	</form>
</div>

<!-- Load Editor.js -->
<script src="https://cdn.jsdelivr.net/npm/@editorjs/editorjs@2.29.1/dist/editorjs.umd.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/@editorjs/header@2.8.1/dist/header.umd.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/@editorjs/list@1.9.0/dist/list.umd.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/@editorjs/image@2.9.0/dist/image.umd.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/@editorjs/quote@2.6.0/dist/quote.umd.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/@editorjs/code@2.9.0/dist/code.umd.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/@editorjs/delimiter@1.4.0/dist/delimiter.umd.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/@editorjs/embed@2.7.4/dist/embed.umd.min.js"></script>

<script>
let editor;

// Wait for all scripts to load before initializing editor
window.addEventListener('load', function() {
	// Check if all required classes are loaded
	const checkPluginsLoaded = setInterval(() => {
		if (typeof EditorJS !== 'undefined' &&
		    typeof Header !== 'undefined' &&
		    typeof List !== 'undefined' &&
		    typeof ImageTool !== 'undefined' &&
		    typeof Quote !== 'undefined' &&
		    typeof CodeTool !== 'undefined' &&
		    typeof Delimiter !== 'undefined' &&
		    typeof Embed !== 'undefined') {
			clearInterval(checkPluginsLoaded);
			initializeEditor();
		}
	}, 100);

	// Timeout after 5 seconds
	setTimeout(() => {
		clearInterval(checkPluginsLoaded);
		if (typeof EditorJS === 'undefined') {
			console.error('Failed to load Editor.js');
			alert('Editor failed to load. Please refresh the page.');
		}
	}, 5000);
});

function initializeEditor() {
	try {
		editor = new EditorJS({
			holder: 'editorjs',

			autofocus: true,

			placeholder: 'Provide detailed event information...',

			tools: {
				header: {
					class: Header,
					config: {
						levels: [2, 3, 4],
						defaultLevel: 2
					}
				},
				list: {
					class: List,
					inlineToolbar: true
				},
				image: {
					class: ImageTool,
					config: {
						endpoints: {
							byFile: '/admin/upload/image'
						}
					}
				},
				quote: {
					class: Quote,
					inlineToolbar: true
				},
				code: CodeTool,
				delimiter: Delimiter,
				embed: {
					class: Embed,
					config: {
						services: {
							youtube: true,
							vimeo: true,
							twitter: true,
							instagram: true,
							facebook: true,
							codepen: true,
							github: true
						}
					}
				}
			},

			onReady: () => {
				console.log('Editor.js is ready!');
			},

			onChange: async () => {
				try {
					const savedData = await editor.save();
					document.getElementById('content-json').value = JSON.stringify(savedData);
				} catch (error) {
					console.error('Error saving content:', error);
				}
			}
		});
	} catch (error) {
		console.error('Error initializing Editor.js:', error);
		alert('Failed to initialize editor. Please refresh the page.');
	}
}

// Auto-generate slug from title and date
function generateSlug() {
	const slugInput = document.getElementById('slug');
	const titleInput = document.getElementById('title');
	const startDateInput = document.getElementById('start_date');

	if (!slugInput.value || slugInput.dataset.autoGenerated === 'true') {
		let slug = '';

		// Add date prefix if available
		if (startDateInput.value) {
			const date = new Date(startDateInput.value);
			const year = date.getFullYear();
			const month = String(date.getMonth() + 1).padStart(2, '0');
			const day = String(date.getDate()).padStart(2, '0');
			slug = `${year}-${month}-${day}-`;
		}

		// Add title
		const titleSlug = titleInput.value.toLowerCase()
			.replace(/[^a-z0-9]+/g, '-')
			.replace(/^-+|-+$/g, '');
		slug += titleSlug;

		slugInput.value = slug;
		slugInput.dataset.autoGenerated = 'true';
	}
}

document.getElementById('title').addEventListener('input', generateSlug);
document.getElementById('start_date').addEventListener('change', generateSlug);

// Mark slug as manually edited
document.getElementById('slug').addEventListener('input', function() {
	if (this.value) {
		this.dataset.autoGenerated = 'false';
	}
});

// Featured image preview
document.getElementById('featured_image').addEventListener('input', function() {
	const preview = document.getElementById('featured-image-preview');
	const img = preview.querySelector('img');

	if (this.value) {
		img.src = this.value;
		preview.style.display = 'block';
	} else {
		preview.style.display = 'none';
	}
});

// Save content before submit
document.getElementById('event-form').addEventListener('submit', async (e) => {
	e.preventDefault();

	try {
		const savedData = await editor.save();
		document.getElementById('content-json').value = JSON.stringify(savedData);
		e.target.submit();
	} catch (error) {
		console.error('Error saving editor content:', error);
		alert('Error preparing content. Please try again.');
	}
});
</script>
