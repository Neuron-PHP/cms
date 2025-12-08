<div class="container-fluid">
	<div class="d-flex justify-content-between align-items-center mb-4">
		<h2>Edit Post: <?= htmlspecialchars( $post->getTitle() ) ?></h2>
		<a href="<?= route_path('admin_posts') ?>" class="btn btn-secondary">Back to Posts</a>
	</div>

	<div class="card">
		<div class="card-body">
			<form method="POST" action="<?= route_path('admin_posts_update', ['id' => $post->getId()]) ?>" id="post-form">
				<input type="hidden" name="_method" value="PUT">
				<?= csrf_field() ?>

				<div class="mb-3">
					<label for="title" class="form-label">Title</label>
					<input type="text" class="form-control" id="title" name="title" value="<?= htmlspecialchars( $post->getTitle() ) ?>" required>
				</div>

				<div class="mb-3">
					<label for="slug" class="form-label">Slug</label>
					<input type="text" class="form-control" id="slug" name="slug" value="<?= htmlspecialchars( $post->getSlug() ) ?>" required>
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
					<textarea class="form-control" id="excerpt" name="excerpt" rows="3"><?= htmlspecialchars( $post->getExcerpt() ?? '' ) ?></textarea>
				</div>

				<div class="mb-3">
					<label for="status" class="form-label">Status</label>
					<select class="form-select" id="status" name="status" required>
						<option value="draft" <?= $post->getStatus() === 'draft' ? 'selected' : '' ?>>Draft</option>
						<option value="published" <?= $post->getStatus() === 'published' ? 'selected' : '' ?>>Published</option>
					</select>
				</div>

				<div class="mb-3">
					<label for="featured_image" class="form-label">Featured Image URL</label>
					<input type="url" class="form-control" id="featured_image" name="featured_image" value="<?= htmlspecialchars( $post->getFeaturedImage() ?? '' ) ?>">
				</div>

				<div class="mb-3">
					<label for="categories" class="form-label">Categories</label>
					<select class="form-select" id="categories" name="categories[]" multiple>
						<?php if( isset( $categories ) ): ?>
							<?php
							$postCategoryIds = array_map( fn( $c ) => $c->getId(), $post->getCategories() );
							foreach( $categories as $category ):
							?>
								<option value="<?= $category->getId() ?>" <?= in_array( $category->getId(), $postCategoryIds ) ? 'selected' : '' ?>><?= htmlspecialchars( $category->getName() ) ?></option>
							<?php endforeach; ?>
						<?php endif; ?>
					</select>
					<small class="form-text text-muted">Hold Ctrl/Cmd to select multiple</small>
				</div>

				<div class="mb-3">
					<label for="tags" class="form-label">Tags</label>
					<?php
					$tagNames = array_map( fn( $t ) => $t->getName(), $post->getTags() );
					$tagsString = implode( ', ', $tagNames );
					?>
					<input type="text" class="form-control" id="tags" name="tags" value="<?= htmlspecialchars( $tagsString ) ?>" placeholder="comma, separated, tags">
				</div>

				<button type="submit" class="btn btn-primary">Update Post</button>
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

<script>
// Load existing content safely
const existingContentJson = <?= json_encode($post->getContentRaw(), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;

let existingContent;
try {
	existingContent = JSON.parse(existingContentJson);
	console.log('Existing content loaded:', existingContent);
} catch (error) {
	console.error('Failed to parse existing content:', error);
	existingContent = { blocks: [] };
}

let editor;

// Wait for all scripts to load before initializing editor
window.addEventListener('load', function() {
	console.log('Page loaded, checking for Editor.js...');

	// Check if all required classes are loaded
	const checkPluginsLoaded = setInterval(() => {
		console.log('Checking plugins...', {
			EditorJS: typeof EditorJS !== 'undefined',
			Header: typeof Header !== 'undefined',
			List: typeof List !== 'undefined'
		});

		if (typeof EditorJS !== 'undefined' &&
		    typeof Header !== 'undefined' &&
		    typeof List !== 'undefined') {
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

			data: existingContent,

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
</script>
