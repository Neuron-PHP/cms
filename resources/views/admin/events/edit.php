<?php
	// Parse the stored RRULE into the structured fields the form uses.
	$rParts = [];
	$rrule = $event->getRrule();
	if( $rrule )
	{
		foreach( explode( ';', $rrule ) as $segment )
		{
			$kv = explode( '=', $segment, 2 );
			if( count( $kv ) === 2 )
			{
				$rParts[ strtoupper( trim( $kv[0] ) ) ] = trim( $kv[1] );
			}
		}
	}

	$rFreq = strtolower( $rParts['FREQ'] ?? 'none' );
	$rInterval = max( 1, (int)( $rParts['INTERVAL'] ?? 1 ) );
	$bydaySelected = isset( $rParts['BYDAY'] ) && $rParts['BYDAY'] !== '' ? explode( ',', $rParts['BYDAY'] ) : [];
	$rEnd = isset( $rParts['UNTIL'] ) ? 'until' : ( isset( $rParts['COUNT'] ) ? 'count' : 'never' );
	$rUntil = '';
	if( isset( $rParts['UNTIL'] ) )
	{
		try { $rUntil = ( new DateTimeImmutable( $rParts['UNTIL'] ) )->format( 'Y-m-d' ); }
		catch( \Throwable $e ) { $rUntil = ''; }
	}
	$rCount = $rParts['COUNT'] ?? '';
	$isRecurring = $event->isRecurring();
	$hasOccurrence = !empty( $occurrence_date );
	$occurrenceLabel = '';
	if( $hasOccurrence )
	{
		try { $occurrenceLabel = ( new DateTimeImmutable( $occurrence_date ) )->format( 'l, F j, Y g:i A' ); }
		catch( \Throwable $e ) { $occurrenceLabel = $occurrence_date; }
	}
?>
<div class="container-fluid">
	<div class="d-flex justify-content-between align-items-center mb-4">
		<h2>Edit Event: <?= htmlspecialchars($event->getTitle()) ?></h2>
		<a href="<?= route_path('admin_events') ?>" class="btn btn-secondary">Back to Events</a>
	</div>

	<?php if( $isRecurring ): ?>
		<div class="card border-danger mb-4">
			<div class="card-header bg-danger text-white">
				<h5 class="mb-0"><i class="bi bi-calendar-x"></i> Cancel Occurrence</h5>
			</div>
			<div class="card-body">
				<?php if( $hasOccurrence ): ?>
					<p class="mb-3">
						Cancel only the occurrence on
						<strong><?= htmlspecialchars( $occurrenceLabel ) ?></strong>.
						The rest of the series stays on the calendar.
					</p>
					<form method="POST"
						  action="<?= route_path('admin_events_cancel_occurrence', ['id' => $event->getId()]) ?>"
						  onsubmit="return confirm('Cancel this occurrence? It will no longer appear on the calendar. The rest of the series is unchanged.');">
						<?= csrf_field() ?>
						<input type="hidden" name="occurrence_date" value="<?= htmlspecialchars( $occurrence_date ) ?>">
						<button type="submit" class="btn btn-danger">
							<i class="bi bi-calendar-x"></i> Cancel This Occurrence
						</button>
					</form>
				<?php else: ?>
					<p class="mb-3">
						Remove one date from this series without deleting the whole event.
						The date is combined with the series start time.
					</p>
					<form method="POST"
						  action="<?= route_path('admin_events_cancel_occurrence', ['id' => $event->getId()]) ?>"
						  class="row g-2 align-items-end"
						  onsubmit="return confirm('Cancel this occurrence? It will no longer appear on the calendar. The rest of the series is unchanged.');">
						<?= csrf_field() ?>
						<div class="col-md-4">
							<label for="cancel_occurrence_date" class="form-label">Occurrence date</label>
							<input type="date"
								   class="form-control"
								   id="cancel_occurrence_date"
								   name="occurrence_date"
								   required>
						</div>
						<div class="col-md-auto">
							<button type="submit" class="btn btn-danger">
								<i class="bi bi-calendar-x"></i> Cancel Occurrence
							</button>
						</div>
					</form>
				<?php endif; ?>
			</div>
		</div>
	<?php endif; ?>

	<form method="POST" action="<?= route_path('admin_events_update', ['id' => $event->getId()]) ?>" id="event-form">
		<input type="hidden" name="_method" value="PUT">
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
							<input type="text" class="form-control" id="title" name="title" value="<?= htmlspecialchars($event->getTitle()) ?>" required>
							<small class="form-text text-muted">The name of your event</small>
						</div>

						<div class="mb-3">
							<label for="slug" class="form-label">Slug</label>
							<div class="input-group">
								<span class="input-group-text">/calendar/event/</span>
								<input type="text" class="form-control" id="slug" name="slug" value="<?= htmlspecialchars($event->getSlug()) ?>" pattern="[a-z0-9-]+" required>
							</div>
							<small class="form-text text-muted">URL-friendly version</small>
						</div>

						<div class="mb-3">
							<label for="description" class="form-label">Short Description</label>
							<textarea class="form-control" id="description" name="description" rows="3" maxlength="300"><?= htmlspecialchars($event->getDescription() ?? '') ?></textarea>
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
								<input type="datetime-local" class="form-control" id="start_date" name="start_date" value="<?= $event->getStartDate()->format('Y-m-d\TH:i') ?>" required>
							</div>

							<div class="col-md-6 mb-3">
								<label for="end_date" class="form-label">End Date/Time</label>
								<input type="datetime-local" class="form-control" id="end_date" name="end_date" value="<?= $event->getEndDate()?->format('Y-m-d\TH:i') ?? '' ?>">
								<small class="form-text text-muted">Optional. Leave blank for single-time events</small>
							</div>
						</div>

						<div class="mb-3">
							<div class="form-check">
								<input class="form-check-input" type="checkbox" id="all_day" name="all_day" value="1" <?= $event->isAllDay() ? 'checked' : '' ?>>
								<label class="form-check-label" for="all_day">
									All Day Event
								</label>
							</div>
						</div>

						<fieldset class="border rounded p-3 mb-3">
							<legend class="float-none w-auto px-2 fs-6 text-muted">Repeat</legend>

							<?php if( $isRecurring ): ?>
								<div class="mb-3">
									<label for="recurrence_edit_scope" class="form-label">Apply changes to</label>
									<select class="form-select" id="recurrence_edit_scope" name="recurrence_edit_scope">
										<option value="all" selected>All events in the series</option>
										<option value="single">This occurrence only</option>
										<option value="this_and_following">This and following events</option>
									</select>
									<small class="form-text text-muted">Choose how edits affect the recurring series.</small>
								</div>
								<input type="hidden" name="occurrence_date" value="<?= htmlspecialchars($occurrence_date ?? '') ?>">
							<?php endif; ?>

							<div class="mb-3">
								<label for="repeat_freq" class="form-label">Repeats</label>
								<select class="form-select" id="repeat_freq" name="repeat_freq" data-recurrence-freq>
									<?php foreach( [ 'none' => 'Does not repeat', 'daily' => 'Daily', 'weekly' => 'Weekly', 'monthly' => 'Monthly', 'yearly' => 'Yearly' ] as $value => $label ): ?>
										<option value="<?= $value ?>" <?= $rFreq === $value ? 'selected' : '' ?>><?= $label ?></option>
									<?php endforeach; ?>
								</select>
							</div>

							<div data-recurrence-options style="display: <?= $rFreq !== 'none' ? 'block' : 'none' ?>;">
								<div class="mb-3">
									<label for="repeat_interval" class="form-label">Every</label>
									<div class="input-group">
										<input type="number" class="form-control" id="repeat_interval" name="repeat_interval" min="1" value="<?= $rInterval ?>">
										<span class="input-group-text" data-recurrence-unit>day(s)</span>
									</div>
								</div>

								<div class="mb-3" data-recurrence-byday-group style="display: <?= $rFreq === 'weekly' ? 'block' : 'none' ?>;">
									<label class="form-label d-block">Repeat on</label>
									<div class="btn-group flex-wrap" role="group" aria-label="Weekdays">
										<?php foreach( [ 'MO' => 'Mon', 'TU' => 'Tue', 'WE' => 'Wed', 'TH' => 'Thu', 'FR' => 'Fri', 'SA' => 'Sat', 'SU' => 'Sun' ] as $code => $label ): ?>
											<input type="checkbox" class="btn-check" id="byday_<?= $code ?>" value="<?= $code ?>" data-recurrence-byday autocomplete="off" <?= in_array( $code, $bydaySelected, true ) ? 'checked' : '' ?>>
											<label class="btn btn-outline-secondary btn-sm" for="byday_<?= $code ?>"><?= $label ?></label>
										<?php endforeach; ?>
									</div>
									<input type="hidden" name="repeat_byday" id="repeat_byday" value="<?= htmlspecialchars(implode(',', $bydaySelected)) ?>">
								</div>

								<div class="mb-3">
									<label for="repeat_end" class="form-label">Ends</label>
									<select class="form-select" id="repeat_end" name="repeat_end" data-recurrence-end>
										<option value="never" <?= $rEnd === 'never' ? 'selected' : '' ?>>Never</option>
										<option value="until" <?= $rEnd === 'until' ? 'selected' : '' ?>>On date</option>
										<option value="count" <?= $rEnd === 'count' ? 'selected' : '' ?>>After number of occurrences</option>
									</select>
								</div>

								<div class="mb-3" data-recurrence-until-group style="display: <?= $rEnd === 'until' ? 'block' : 'none' ?>;">
									<label for="repeat_until" class="form-label">End date</label>
									<input type="date" class="form-control" id="repeat_until" name="repeat_until" value="<?= htmlspecialchars($rUntil) ?>">
								</div>

								<div class="mb-3" data-recurrence-count-group style="display: <?= $rEnd === 'count' ? 'block' : 'none' ?>;">
									<label for="repeat_count" class="form-label">Number of occurrences</label>
									<input type="number" class="form-control" id="repeat_count" name="repeat_count" min="1" value="<?= htmlspecialchars((string)$rCount) ?>">
								</div>
							</div>
						</fieldset>

						<div class="mb-3">
							<label for="location" class="form-label">Location</label>
							<input type="text" class="form-control" id="location" name="location" value="<?= htmlspecialchars($event->getLocation() ?? '') ?>" placeholder="e.g., Main Auditorium, 123 Main St, Sarasota, FL">
							<small class="form-text text-muted">Physical location or virtual meeting link</small>
						</div>

						<div class="mb-3">
							<label for="external_url" class="form-label">External Link</label>
							<input type="url" class="form-control" id="external_url" name="external_url" value="<?= htmlspecialchars($event->getExternalUrl() ?? '') ?>" placeholder="https://example.com/event">
							<small class="form-text text-muted">If this event is managed on another platform, paste its full URL (including https://). When set, links to this event open the external site in a new tab.</small>
						</div>

						<div class="mb-3">
							<label for="organizer" class="form-label">Organizer</label>
							<input type="text" class="form-control" id="organizer" name="organizer" value="<?= htmlspecialchars($event->getOrganizer() ?? '') ?>" placeholder="e.g., Sarasota Teen Court">
						</div>

						<div class="row">
							<div class="col-md-6 mb-3">
								<label for="contact_email" class="form-label">Contact Email</label>
								<input type="email" class="form-control" id="contact_email" name="contact_email" value="<?= htmlspecialchars($event->getContactEmail() ?? '') ?>">
							</div>

							<div class="col-md-6 mb-3">
								<label for="contact_phone" class="form-label">Contact Phone</label>
								<input type="tel" class="form-control" id="contact_phone" name="contact_phone" value="<?= htmlspecialchars($event->getContactPhone() ?? '') ?>">
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
								<option value="draft" <?= $event->getStatus() === 'draft' ? 'selected' : '' ?>>Draft</option>
								<option value="published" <?= $event->getStatus() === 'published' ? 'selected' : '' ?>>Published</option>
							</select>
							<small class="form-text text-muted">Only published events are visible to visitors</small>
						</div>

						<div class="mb-3">
							<div class="form-check">
								<input class="form-check-input" type="checkbox" id="featured" name="featured" value="1" <?= $event->isFeatured() ? 'checked' : '' ?>>
								<label class="form-check-label" for="featured">
									Featured event
								</label>
							</div>
							<small class="form-text text-muted">The next available featured event is shown by the <code>[featured-event]</code> shortcode.</small>
						</div>

						<div class="mb-3">
							<label for="category_id" class="form-label">Category</label>
							<select class="form-select" id="category_id" name="category_id">
								<option value="">Uncategorized</option>
								<?php foreach($categories as $category): ?>
									<option value="<?= $category->getId() ?>" <?= $event->getCategoryId() === $category->getId() ? 'selected' : '' ?>>
										<?= htmlspecialchars($category->getName()) ?>
									</option>
								<?php endforeach; ?>
							</select>
						</div>

						<div class="alert alert-secondary small">
							<strong>Created:</strong> <?= $event->getCreatedAt()?->format('M j, Y') ?? 'N/A' ?><br>
							<strong>Updated:</strong> <?= $event->getUpdatedAt()?->format('M j, Y') ?? 'Never' ?><br>
							<strong>Views:</strong> <?= $event->getViewCount() ?>
						</div>
					</div>
				</div>

				<div class="card mb-4">
					<div class="card-header">
						<h5 class="mb-0">Registration</h5>
					</div>
					<div class="card-body">
						<div class="mb-3">
							<div class="form-check">
								<input class="form-check-input" type="checkbox" id="registration_enabled" name="registration_enabled" value="1" <?= $event->isRegistrationEnabled() ? 'checked' : '' ?>>
								<label class="form-check-label" for="registration_enabled">
									Enable registration
								</label>
							</div>
							<small class="form-text text-muted">Show a registration form on this event and via the <code>[event-registration]</code> shortcode.</small>
						</div>

						<div class="mb-3">
							<label for="registration_visibility" class="form-label">Visibility</label>
							<select class="form-select" id="registration_visibility" name="registration_visibility">
								<option value="public" <?= $event->getRegistrationVisibility() === 'private' ? '' : 'selected' ?>>Public (anyone can register)</option>
								<option value="private" <?= $event->getRegistrationVisibility() === 'private' ? 'selected' : '' ?>>Private (members only)</option>
							</select>
							<small class="form-text text-muted">Private events require visitors to log in before registering.</small>
						</div>

						<div class="mb-3">
							<label for="capacity" class="form-label">Capacity</label>
							<input type="number" class="form-control" id="capacity" name="capacity" min="1" value="<?= $event->getCapacity() !== null ? (int)$event->getCapacity() : '' ?>" placeholder="Unlimited">
							<small class="form-text text-muted">Maximum number of registrations. Leave blank for unlimited.</small>
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
							<input type="url" class="form-control" id="featured_image" name="featured_image" value="<?= htmlspecialchars($event->getFeaturedImage() ?? '') ?>" placeholder="https://">
							<small class="form-text text-muted">Upload via Media Library or paste URL</small>
						</div>

						<div id="featured-image-preview" class="mb-2" <?= $event->getFeaturedImage() ? '' : 'style="display: none;"' ?>>
							<img src="<?= htmlspecialchars($event->getFeaturedImage() ?? '') ?>" alt="Preview" class="img-thumbnail" style="max-width: 100%;">
						</div>

						<a href="<?= route_path('admin_media') ?>" class="btn btn-sm btn-outline-secondary w-100" target="_blank">
							<i class="bi bi-images"></i> Open Media Library
						</a>
					</div>
				</div>

				<button type="submit" class="btn btn-primary w-100 mb-2">
					<i class="bi bi-check-circle"></i> Update Event
				</button>
				<?php if($event->isPublished()): ?>
					<a href="<?= route_path('calendar_event', ['slug' => $event->getSlug()]) ?>" class="btn btn-outline-secondary w-100 mb-2" target="_blank">
						<i class="bi bi-eye"></i> View Event
					</a>
				<?php endif; ?>
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
// Load existing content safely
const existingContentJson = <?= json_encode($event->getContentRaw(), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;

let existingContent;
try {
	existingContent = JSON.parse(existingContentJson);
} catch (error) {
	console.error('Failed to parse existing content:', error);
	existingContent = { blocks: [] };
}

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

			data: existingContent,

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
						},
						additionalRequestHeaders: {
							'X-CSRF-Token': '<?= htmlspecialchars( csrf_token() ) ?>'
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

<script>
// Recurrence controls: toggle option visibility and sync BYDAY selection.
(function() {
	var freq = document.querySelector('[data-recurrence-freq]');
	if( !freq ) { return; }

	var options = document.querySelector('[data-recurrence-options]');
	var bydayGroup = document.querySelector('[data-recurrence-byday-group]');
	var unit = document.querySelector('[data-recurrence-unit]');
	var endSelect = document.querySelector('[data-recurrence-end]');
	var untilGroup = document.querySelector('[data-recurrence-until-group]');
	var countGroup = document.querySelector('[data-recurrence-count-group]');
	var bydayInput = document.getElementById('repeat_byday');
	var bydayChecks = document.querySelectorAll('[data-recurrence-byday]');

	var units = { daily: 'day(s)', weekly: 'week(s)', monthly: 'month(s)', yearly: 'year(s)' };

	function syncByday() {
		if( !bydayInput ) { return; }
		var selected = [];
		bydayChecks.forEach(function(box) { if( box.checked ) { selected.push(box.value); } });
		bydayInput.value = selected.join(',');
	}

	function refresh() {
		var value = freq.value;
		var repeats = value !== 'none';
		if( options ) { options.style.display = repeats ? 'block' : 'none'; }
		if( bydayGroup ) { bydayGroup.style.display = value === 'weekly' ? 'block' : 'none'; }
		if( unit && units[value] ) { unit.textContent = units[value]; }
		if( endSelect ) {
			if( untilGroup ) { untilGroup.style.display = endSelect.value === 'until' ? 'block' : 'none'; }
			if( countGroup ) { countGroup.style.display = endSelect.value === 'count' ? 'block' : 'none'; }
		}
	}

	freq.addEventListener('change', refresh);
	if( endSelect ) { endSelect.addEventListener('change', refresh); }
	bydayChecks.forEach(function(box) { box.addEventListener('change', syncByday); });

	refresh();
	syncByday();
})();
</script>
