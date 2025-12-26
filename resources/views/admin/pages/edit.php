<div class="container-fluid">
	<div class="d-flex justify-content-between align-items-center mb-4">
		<h2>Edit Page: <?= htmlspecialchars($page->getTitle()) ?></h2>
		<a href="<?= route_path('admin_pages') ?>" class="btn btn-secondary">Back to Pages</a>
	</div>

	<form method="POST" action="<?= route_path('admin_pages_update', ['id' => $page->getId()]) ?>" id="page-form">
		<input type="hidden" name="_method" value="PUT">
		<?= csrf_field() ?>

		<div class="row">
			<div class="col-md-8">
				<div class="card mb-4">
					<div class="card-header">
						<h5 class="mb-0">Page Content</h5>
					</div>
					<div class="card-body">
						<div class="mb-3">
							<label for="title" class="form-label">Title <span class="text-danger">*</span></label>
							<input type="text" class="form-control" id="title" name="title" value="<?= htmlspecialchars($page->getTitle()) ?>" required>
							<small class="form-text text-muted">The main heading of your page</small>
						</div>

						<div class="mb-3">
							<label for="slug" class="form-label">Slug</label>
							<div class="input-group">
								<span class="input-group-text">/pages/</span>
								<input type="text" class="form-control" id="slug" name="slug" value="<?= htmlspecialchars($page->getSlug()) ?>" pattern="[a-z0-9-]+" required>
							</div>
							<small class="form-text text-muted">URL-friendly version</small>
						</div>

						<div class="mb-3">
							<label class="form-label">Content</label>
							<div id="editorjs" style="border: 1px solid #ddd; border-radius: 0.25rem; padding: 20px; min-height: 400px; background: #fff;"></div>
							<input type="hidden" name="content" id="content-json">
							<small class="form-text text-muted">
								Use shortcodes for dynamic content: <code>[latest-posts limit="5"]</code> or <code>[contact-form]</code>
							</small>
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
								<option value="draft" <?= $page->getStatus() === 'draft' ? 'selected' : '' ?>>Draft</option>
								<option value="published" <?= $page->getStatus() === 'published' ? 'selected' : '' ?>>Published</option>
							</select>
							<small class="form-text text-muted">Only published pages are visible to visitors</small>
						</div>

						<div class="mb-3">
							<label for="template" class="form-label">Template</label>
							<select class="form-select" id="template" name="template">
								<option value="default" <?= $page->getTemplate() === 'default' ? 'selected' : '' ?>>Default</option>
								<option value="full-width" <?= $page->getTemplate() === 'full-width' ? 'selected' : '' ?>>Full Width</option>
								<option value="sidebar" <?= $page->getTemplate() === 'sidebar' ? 'selected' : '' ?>>With Sidebar</option>
								<option value="landing" <?= $page->getTemplate() === 'landing' ? 'selected' : '' ?>>Landing Page</option>
							</select>
						</div>

						<?php if($page->getPublishedAt()): ?>
							<div class="alert alert-info small">
								<strong>Published:</strong><br>
								<?= $page->getPublishedAt()->format('M j, Y \a\t g:i A') ?>
							</div>
						<?php endif; ?>

						<div class="alert alert-secondary small">
							<strong>Created:</strong> <?= $page->getCreatedAt()?->format('M j, Y') ?? 'N/A' ?><br>
							<strong>Updated:</strong> <?= $page->getUpdatedAt()?->format('M j, Y') ?? 'Never' ?><br>
							<strong>Views:</strong> <?= $page->getViewCount() ?>
						</div>
					</div>
				</div>

				<div class="card mb-4">
					<div class="card-header">
						<h5 class="mb-0">SEO</h5>
					</div>
					<div class="card-body">
						<div class="mb-3">
							<label for="meta_title" class="form-label">Meta Title</label>
							<input type="text" class="form-control" id="meta_title" name="meta_title" value="<?= htmlspecialchars($page->getMetaTitle() ?? '') ?>" maxlength="60">
							<small class="form-text text-muted">60 chars max. Leave blank to use page title.</small>
						</div>

						<div class="mb-3">
							<label for="meta_description" class="form-label">Meta Description</label>
							<textarea class="form-control" id="meta_description" name="meta_description" rows="3" maxlength="160"><?= htmlspecialchars($page->getMetaDescription() ?? '') ?></textarea>
							<small class="form-text text-muted">160 chars max. Appears in search results.</small>
						</div>

						<div class="mb-3">
							<label for="meta_keywords" class="form-label">Meta Keywords</label>
							<input type="text" class="form-control" id="meta_keywords" name="meta_keywords" value="<?= htmlspecialchars($page->getMetaKeywords() ?? '') ?>">
							<small class="form-text text-muted">Comma-separated</small>
						</div>
					</div>
				</div>

				<button type="submit" class="btn btn-primary w-100 mb-2">
					<i class="bi bi-check-circle"></i> Update Page
				</button>
				<?php if($page->isPublished()): ?>
					<a href="<?= route_path('page_show', ['slug' => $page->getSlug()]) ?>" class="btn btn-outline-secondary w-100 mb-2" target="_blank">
						<i class="bi bi-eye"></i> View Page
					</a>
				<?php endif; ?>
				<a href="<?= route_path('admin_pages') ?>" class="btn btn-outline-secondary w-100">
					Cancel
				</a>
			</div>
		</div>
	</form>
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
<script src="https://cdn.jsdelivr.net/npm/@editorjs/embed@2.7.4/dist/embed.umd.min.js"></script>

<script>
// Load existing content safely
const existingContentJson = <?= json_encode($page->getContentRaw(), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;

let existingContent;
try {
	existingContent = JSON.parse(existingContentJson);
} catch (error) {
	console.error('Failed to parse existing content:', error);
	existingContent = { blocks: [] };
}

const editor = new EditorJS({
	holder: 'editorjs',

	data: existingContent,

	placeholder: 'Start writing your page content...',

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

	onChange: async () => {
		const savedData = await editor.save();
		document.getElementById('content-json').value = JSON.stringify(savedData);
	}
});

// Save content before submit
document.getElementById('page-form').addEventListener('submit', async (e) => {
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
