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
					<label for="featured_image" class="form-label">Featured Image URL</label>
					<input type="url" class="form-control" id="featured_image" name="featured_image">
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
<script src="https://cdn.jsdelivr.net/npm/@editorjs/editorjs@latest"></script>
<script src="https://cdn.jsdelivr.net/npm/@editorjs/header@latest"></script>
<script src="https://cdn.jsdelivr.net/npm/@editorjs/list@latest"></script>
<script src="https://cdn.jsdelivr.net/npm/@editorjs/image@latest"></script>
<script src="https://cdn.jsdelivr.net/npm/@editorjs/quote@latest"></script>
<script src="https://cdn.jsdelivr.net/npm/@editorjs/code@latest"></script>
<script src="https://cdn.jsdelivr.net/npm/@editorjs/delimiter@latest"></script>
<script src="https://cdn.jsdelivr.net/npm/@editorjs/raw@latest"></script>

<script>
const editor = new EditorJS({
	holder: 'editorjs',

	placeholder: 'Start writing your post content...',

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

	onChange: async () => {
		const savedData = await editor.save();
		document.getElementById('content-json').value = JSON.stringify(savedData);
	}
});

// Save content before submit
document.getElementById('post-form').addEventListener('submit', async (e) => {
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
