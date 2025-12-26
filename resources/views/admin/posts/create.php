<div class="container-fluid">
	<div class="d-flex justify-content-between align-items-center mb-4">
		<h2>Create New Post</h2>
		<a href="<?= route_path('admin_posts') ?>" class="btn btn-secondary">Back to Posts</a>
	</div>

	<div class="card">
		<div class="card-body">
			<form method="POST" action="<?= route_path('admin_posts_store') ?>" id="post-form">
				<?= csrf_field() ?>

				<div class="mb-3">
					<label for="title" class="form-label">Title</label>
					<input type="text" class="form-control" id="title" name="title" required>
				</div>

				<div class="mb-3">
					<label for="slug" class="form-label">Slug</label>
					<input type="text" class="form-control" id="slug" name="slug" required>
					<small class="form-text text-muted">URL-friendly version of the title</small>
				</div>

				<div class="mb-3">
					<label class="form-label">Content</label>
					<div id="editorjs" style="border: 1px solid #ddd; border-radius: 0.25rem; padding: 20px; min-height: 400px; background: #fff;"></div>
					<input type="hidden" name="content" id="content-json">
					<small class="form-text text-muted">
						Use shortcodes for dynamic content: <code>[latest-posts limit="5"]</code>
					</small>
				</div>

				<div class="mb-3">
					<label for="excerpt" class="form-label">Excerpt</label>
					<textarea class="form-control" id="excerpt" name="excerpt" rows="3"></textarea>
				</div>

				<div class="mb-3">
					<label for="status" class="form-label">Status</label>
					<select class="form-select" id="status" name="status" required>
						<option value="draft">Draft</option>
						<option value="published">Published</option>
					</select>
				</div>

				<div class="mb-3">
					<label for="featured_image" class="form-label">Featured Image</label>
					<div class="input-group">
						<input type="url" class="form-control" id="featured_image" name="featured_image" placeholder="Enter URL or browse from media library">
						<button type="button" class="btn btn-outline-secondary" onclick="openMediaPicker('featured_image')">
							<i class="bi bi-images"></i> Browse
						</button>
					</div>
					<div class="mt-2">
						<img id="featured_image_preview" class="img-thumbnail d-none" style="max-width: 300px;" alt="Featured image preview">
					</div>
				</div>

				<div class="mb-3">
					<label for="categories" class="form-label">Categories</label>
					<select class="form-select" id="categories" name="categories[]" multiple>
						<?php if( isset( $categories ) ): ?>
							<?php foreach( $categories as $category ): ?>
								<option value="<?= $category->getId() ?>"><?= htmlspecialchars( $category->getName() ) ?></option>
							<?php endforeach; ?>
						<?php endif; ?>
					</select>
					<small class="form-text text-muted">Hold Ctrl/Cmd to select multiple</small>
				</div>

				<div class="mb-3">
					<label for="tags" class="form-label">Tags</label>
					<input type="text" class="form-control" id="tags" name="tags" placeholder="comma, separated, tags">
				</div>

				<button type="submit" class="btn btn-primary">Create Post</button>
				<a href="<?= route_path('admin_posts') ?>" class="btn btn-secondary">Cancel</a>
			</form>
		</div>
	</div>
</div>

<!-- Load Editor.js -->
<script src="https://cdn.jsdelivr.net/npm/@editorjs/editorjs@2.29.1/dist/editorjs.umd.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/@editorjs/header@2.8.1/dist/header.umd.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/@editorjs/list@1.9.0/dist/list.umd.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/@editorjs/image@2.9.0/dist/image.umd.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/@editorjs/quote@2.6.0/dist/quote.umd.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/@editorjs/code@2.9.0/dist/code.umd.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/@editorjs/delimiter@1.4.0/dist/delimiter.umd.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/@editorjs/raw@2.5.0/dist/raw.umd.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/@editorjs/embed@2.7.4/dist/embed.umd.min.js"></script>

<script>
let editor;

// Wait for all scripts to load before initializing editor
window.addEventListener('load', function() {
	console.log('Page loaded, checking for Editor.js...');

	// Check if all required classes are loaded
	const checkPluginsLoaded = setInterval(() => {
		console.log('Checking plugins...', {
			EditorJS: typeof EditorJS !== 'undefined',
			Header: typeof Header !== 'undefined',
			List: typeof List !== 'undefined',
			Embed: typeof Embed !== 'undefined'
		});

		if (typeof EditorJS !== 'undefined' &&
		    typeof Header !== 'undefined' &&
		    typeof List !== 'undefined' &&
		    typeof Embed !== 'undefined') {
			clearInterval(checkPluginsLoaded);
			console.log('All plugins loaded, initializing editor...');
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

			placeholder: 'Click here to start writing your post content...',

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
				raw: {
					class: RawTool,
					config: {
						placeholder: 'Enter HTML or shortcodes like [latest-posts limit="5"]'
					}
				},
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

			onChange: async (api, event) => {
				console.log('Content changed');
				try {
					const savedData = await editor.save();
					document.getElementById('content-json').value = JSON.stringify(savedData);
				} catch (error) {
					console.error('Error saving content:', error);
				}
			}
		});

		// Save content before submit
		document.getElementById('post-form').addEventListener('submit', async (e) => {
			e.preventDefault();

			try {
				const savedData = await editor.save();
				console.log('Saved data:', savedData);
				document.getElementById('content-json').value = JSON.stringify(savedData);
				e.target.submit();
			} catch (error) {
				console.error('Error saving editor content:', error);
				alert('Error preparing content. Please try again.');
			}
		});
	} catch (error) {
		console.error('Error initializing editor:', error);
		alert('Failed to initialize editor: ' + error.message);
	}
}

// Featured image preview
document.getElementById('featured_image').addEventListener('change', function() {
	const preview = document.getElementById('featured_image_preview');
	const url = this.value.trim();

	if (url) {
		preview.src = url;
		preview.classList.remove('d-none');
	} else {
		preview.classList.add('d-none');
	}
});
</script>

<?php include __DIR__ . '/../../partials/media-picker-modal.php'; ?>
