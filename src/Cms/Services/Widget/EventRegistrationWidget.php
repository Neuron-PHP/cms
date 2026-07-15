<?php

namespace Neuron\Cms\Services\Widget;

use Neuron\Cms\Auth\SessionManager;
use Neuron\Cms\Models\Event;
use Neuron\Cms\Repositories\IEventRepository;
use Neuron\Cms\Repositories\IEventCategoryRepository;
use Neuron\Cms\Repositories\IEventRegistrationRepository;
use DateTimeImmutable;

/**
 * Event registration widget / shortcode.
 *
 * Renders a registration form for one or more calendar events. Two modes:
 *
 *   [event-registration event="my-event-slug"]      -> register for one event
 *   [event-registration category="workshops"]        -> pick from the next
 *                                                        upcoming events of a type
 *
 * In category mode the next upcoming events (default 3) are offered as a
 * selectable list so a single form powers an entire event type. The form posts
 * to /events/register. A CSRF token is fetched client-side from
 * /events/register/token so the markup stays valid even when the page is cached.
 *
 * Private events require a logged-in member: in single-event mode anonymous
 * visitors see a login prompt; in category mode private events are hidden from
 * anonymous visitors. The submit handler re-checks visibility authoritatively.
 *
 * @package Neuron\Cms\Services\Widget
 */
class EventRegistrationWidget implements IWidget
{
	private IEventRepository $_eventRepository;
	private IEventCategoryRepository $_categoryRepository;
	private ?IEventRegistrationRepository $_registrationRepository;
	private ?SessionManager $_sessionManager;

	private const DEFAULT_LIMIT = 3;

	public function __construct(
		IEventRepository $eventRepository,
		IEventCategoryRepository $categoryRepository,
		?IEventRegistrationRepository $registrationRepository = null,
		?SessionManager $sessionManager = null
	)
	{
		$this->_eventRepository        = $eventRepository;
		$this->_categoryRepository     = $categoryRepository;
		$this->_registrationRepository = $registrationRepository;
		$this->_sessionManager         = $sessionManager;
	}

	/**
	 * Shortcode name.
	 */
	public function getName(): string
	{
		return 'event-registration';
	}

	/**
	 * Widget description (for documentation).
	 */
	public function getDescription(): string
	{
		return 'Render a registration form for a specific event or for the next upcoming events of an event type.';
	}

	/**
	 * Supported shortcode attributes.
	 *
	 * @return array<string, string>
	 */
	public function getAttributes(): array
	{
		return [
			'event'    => 'Event slug to register for a single event',
			'category' => 'Event category slug to offer the next upcoming events of that type',
			'limit'    => 'Number of upcoming dates to offer in category mode (default: 3)',
			'title'    => 'Optional heading override',
			'button'   => 'Optional submit button label override'
		];
	}

	/**
	 * Render the registration form.
	 *
	 * @param array<string, mixed> $attrs Shortcode attributes
	 * @return string Rendered HTML
	 */
	public function render( array $attrs ): string
	{
		$loggedIn = $this->isLoggedIn();

		if( !empty( $attrs['event'] ) )
		{
			return $this->renderSingle( (string)$attrs['event'], $attrs, $loggedIn );
		}

		if( !empty( $attrs['category'] ) )
		{
			return $this->renderCategory( (string)$attrs['category'], $attrs, $loggedIn );
		}

		return '<!-- event-registration widget: provide an "event" or "category" attribute -->';
	}

	/**
	 * Render a registration form for a single event.
	 *
	 * @param string $slug
	 * @param array<string, mixed> $attrs
	 * @param bool $loggedIn
	 * @return string
	 */
	private function renderSingle( string $slug, array $attrs, bool $loggedIn ): string
	{
		$event = $this->_eventRepository->findBySlug( $slug );

		if( !$event || !$event->isPublished() || !$event->isRegistrationEnabled() )
		{
			return '<!-- event-registration widget: event not available for registration -->';
		}

		if( $event->isPrivate() && !$loggedIn )
		{
			return $this->loginPrompt();
		}

		// Resolve the occurrence being registered for (recurring events only).
		$occurrence = $this->resolveOccurrence( $event, $attrs['occurrence'] ?? null );

		if( $this->isFull( $event, $occurrence ) )
		{
			return $this->fullPrompt();
		}

		$displayDate = $occurrence ?? $event->getStartDate();

		$title  = $attrs['title'] ?? ( 'Register for ' . $event->getTitle() );
		$button = $attrs['button'] ?? 'Register';

		$select = '<input type="hidden" name="event_id" value="' . (int)$event->getId() . '">';

		if( $occurrence !== null )
		{
			$select .= '<input type="hidden" name="occurrence_date" value="'
				. $this->esc( $occurrence->format( 'Y-m-d H:i:s' ) ) . '">';
		}

		$summary = '<p class="text-muted mb-3"><i class="bi bi-calendar-event"></i> '
			. $this->esc( $displayDate->format( 'l, F j, Y g:i A' ) ) . '</p>';

		return $this->renderForm( (string)$title, (string)$button, $summary . $select );
	}

	/**
	 * Resolve and validate an occurrence date for a recurring event.
	 *
	 * @param Event $event
	 * @param mixed $occurrenceRaw
	 * @return DateTimeImmutable|null
	 */
	private function resolveOccurrence( Event $event, mixed $occurrenceRaw ): ?DateTimeImmutable
	{
		if( !$event->isRecurring() )
		{
			return null;
		}

		$raw = trim( (string)( $occurrenceRaw ?? '' ) );

		if( $raw === '' )
		{
			return $event->getStartDate();
		}

		try
		{
			$occurrence = new DateTimeImmutable( $raw );

			if( \Neuron\Cms\Services\Event\RecurrenceRule::occursAt( (string)$event->getRrule(), $event->getStartDate(), $occurrence ) )
			{
				return $occurrence;
			}
		}
		catch( \Throwable $e )
		{
			// Fall through to the series start.
		}

		return $event->getStartDate();
	}

	/**
	 * Render a registration form offering the next upcoming events of a category.
	 *
	 * @param string $slug
	 * @param array<string, mixed> $attrs
	 * @param bool $loggedIn
	 * @return string
	 */
	private function renderCategory( string $slug, array $attrs, bool $loggedIn ): string
	{
		$category = $this->_categoryRepository->findBySlug( $slug );

		if( !$category )
		{
			return '<!-- event-registration widget: unknown category "' . $this->esc( $slug ) . '" -->';
		}

		$limit  = isset( $attrs['limit'] ) ? max( 1, (int)$attrs['limit'] ) : self::DEFAULT_LIMIT;
		$events = $this->_eventRepository->getUpcomingByCategory( $category->getId(), $limit );

		// Only events that have registration enabled, and (for anonymous
		// visitors) only public events, can be offered.
		$events = array_filter( $events, function( Event $event ) use ( $loggedIn )
		{
			if( !$event->isRegistrationEnabled() )
			{
				return false;
			}

			if( $this->isFull( $event, $event->getOccurrenceDate() ) )
			{
				return false;
			}

			return $loggedIn || !$event->isPrivate();
		} );

		if( empty( $events ) )
		{
			return '<div class="event-registration-widget mb-4"><p class="text-muted">'
				. 'There are no upcoming events open for registration right now.</p></div>';
		}

		$title  = $attrs['title'] ?? ( 'Register for ' . $category->getName() );
		$button = $attrs['button'] ?? 'Register';

		$options = '<div class="mb-3">'
			. '<label class="form-label" for="event_registration_event">Select a date <span class="text-danger">*</span></label>'
			. '<select class="form-select" id="event_registration_event" name="event_id" data-occurrence-select required>';

		foreach( $events as $event )
		{
			$label = $event->getStartDate()->format( 'l, F j, Y g:i A' );

			if( $event->getTitle() !== $category->getName() )
			{
				$label = $event->getTitle() . ' - ' . $label;
			}

			// Recurring occurrences carry their occurrence start so the hidden
			// occurrence_date field can be synced on selection.
			$occurrenceAttr = $event->isOccurrence()
				? ' data-occurrence="' . $this->esc( $event->getOccurrenceDate()->format( 'Y-m-d H:i:s' ) ) . '"'
				: '';

			$options .= '<option value="' . (int)$event->getId() . '"' . $occurrenceAttr . '>'
				. $this->esc( $label ) . '</option>';
		}

		$options .= '</select></div>';

		// Hidden field synced from the selected option's occurrence (if any).
		$options .= '<input type="hidden" name="occurrence_date" value="" data-occurrence-input>';
		$options .= $this->occurrenceSyncScript();

		return $this->renderForm( (string)$title, (string)$button, $options );
	}

	/**
	 * Inline script that mirrors the selected option's occurrence date into the
	 * hidden occurrence_date input for category-mode forms.
	 *
	 * @return string
	 */
	private function occurrenceSyncScript(): string
	{
		return <<<'HTML'
<script>
(function() {
	if( window.__eventRegistrationOccurrenceInit ) { return; }
	window.__eventRegistrationOccurrenceInit = true;
	document.addEventListener('DOMContentLoaded', function() {
		document.querySelectorAll('select[data-occurrence-select]').forEach(function(select) {
			var form = select.closest('form');
			if( !form ) { return; }
			var input = form.querySelector('input[data-occurrence-input]');
			if( !input ) { return; }
			var sync = function() {
				var option = select.options[select.selectedIndex];
				input.value = option ? ( option.getAttribute('data-occurrence') || '' ) : '';
			};
			select.addEventListener('change', sync);
			sync();
		});
	});
})();
</script>
HTML;
	}

	/**
	 * Render the shared form shell around the event-selection markup.
	 *
	 * @param string $title
	 * @param string $button
	 * @param string $eventSelection Event id input/select markup
	 * @return string
	 */
	private function renderForm( string $title, string $button, string $eventSelection ): string
	{
		[ $successMessage, $errorMessage ] = $this->consumeFlash();

		$html = '<div class="event-registration-widget mb-4">';

		if( $title !== '' )
		{
			$html .= '<h3 class="event-registration-title mb-3">' . $this->esc( $title ) . '</h3>';
		}

		if( $successMessage !== null )
		{
			$html .= '<div class="alert alert-success">' . $this->esc( $successMessage ) . '</div>';
		}

		if( $errorMessage !== null )
		{
			$html .= '<div class="alert alert-danger">' . $this->esc( $errorMessage ) . '</div>';
		}

		$html .= '<form class="event-registration-form" method="POST" action="/events/register" data-event-registration-form>';
		$html .= '<input type="hidden" name="csrf_token" value="">';

		// Honeypot: hidden from humans; bots that fill it are rejected.
		$html .= '<div style="position:absolute;left:-5000px;height:0;overflow:hidden;" aria-hidden="true">';
		// Name and label are deliberately meaningless: autofill/password managers
		// classify fields by name and label text, and will fill recognizable ones
		// (e.g. "company", "website") even inside hidden containers.
		$html .= '<label>Leave this field empty<input type="text" name="form_extra_field" tabindex="-1" autocomplete="off"></label>';
		$html .= '</div>';

		$html .= $eventSelection;

		$html .= '<div class="mb-3">'
			. '<label class="form-label" for="event_registration_name">Your Name <span class="text-danger">*</span></label>'
			. '<input type="text" class="form-control" id="event_registration_name" name="name" required>'
			. '</div>';

		$html .= '<div class="mb-3">'
			. '<label class="form-label" for="event_registration_email">Email <span class="text-danger">*</span></label>'
			. '<input type="email" class="form-control" id="event_registration_email" name="email" required>'
			. '</div>';

		$html .= '<div class="mb-3">'
			. '<label class="form-label" for="event_registration_notes">Notes</label>'
			. '<textarea class="form-control" id="event_registration_notes" name="notes" rows="3"></textarea>'
			. '</div>';

		$html .= '<button type="submit" class="btn btn-primary">' . $this->esc( $button ) . '</button>';
		$html .= '</form>';
		$html .= '</div>';

		$html .= $this->tokenScript();

		return $html;
	}

	/**
	 * Whether an event has reached its registration capacity.
	 *
	 * Returns false when no registration repository is available (the submit
	 * controller still enforces capacity authoritatively).
	 *
	 * @param Event $event
	 * @return bool
	 */
	private function isFull( Event $event, ?DateTimeImmutable $occurrence = null ): bool
	{
		if( !$event->hasCapacityLimit() || $this->_registrationRepository === null )
		{
			return false;
		}

		return $event->isFull( $this->_registrationRepository->countByEvent( $event->getId(), $occurrence ) );
	}

	/**
	 * Message shown when an event has reached capacity.
	 *
	 * @return string
	 */
	private function fullPrompt(): string
	{
		return '<div class="event-registration-widget mb-4">'
			. '<div class="alert alert-warning mb-0">'
			. 'This event is full. Registration is now closed.'
			. '</div></div>';
	}

	/**
	 * Prompt shown to anonymous visitors for a private (members-only) event.
	 *
	 * @return string
	 */
	private function loginPrompt(): string
	{
		return '<div class="event-registration-widget mb-4">'
			. '<div class="alert alert-info mb-0">'
			. 'This event is open to members only. Please <a href="/login">log in</a> to register.'
			. '</div></div>';
	}

	/**
	 * Inline script (rendered once per page) that fetches a fresh CSRF token
	 * and injects it into every registration form, keeping cached markup valid.
	 *
	 * @return string
	 */
	private function tokenScript(): string
	{
		return <<<'HTML'
<script>
(function() {
	if( window.__eventRegistrationTokenInit ) { return; }
	window.__eventRegistrationTokenInit = true;
	document.addEventListener('DOMContentLoaded', function() {
		fetch('/events/register/token', { headers: { 'Accept': 'application/json' }, credentials: 'same-origin' })
			.then(function(r) { return r.json(); })
			.then(function(data) {
				if( !data || !data.token ) { return; }
				document.querySelectorAll('form[data-event-registration-form] input[name="csrf_token"]').forEach(function(input) {
					input.value = data.token;
				});
			})
			.catch(function() {});
	});
})();
</script>
HTML;
	}

	/**
	 * Read and clear registration flash messages set by the controller.
	 *
	 * @return array{0: ?string, 1: ?string} [success, error]
	 */
	private function consumeFlash(): array
	{
		$session = $this->session();

		if( $session === null )
		{
			return [ null, null ];
		}

		$success = $session->getFlash( 'event_registration.success' );
		$error   = $session->getFlash( 'event_registration.error' );

		return [
			$success !== null ? (string)$success : null,
			$error !== null ? (string)$error : null
		];
	}

	/**
	 * Whether a member is currently logged in (checks the session directly so
	 * it works on public pages where the auth filter has not run).
	 *
	 * @return bool
	 */
	private function isLoggedIn(): bool
	{
		$session = $this->session();

		return $session !== null && $session->has( 'user_id' );
	}

	/**
	 * Lazily resolve a started session manager.
	 *
	 * @return SessionManager|null
	 */
	private function session(): ?SessionManager
	{
		if( $this->_sessionManager === null )
		{
			$this->_sessionManager = new SessionManager();
		}

		try
		{
			if( !$this->_sessionManager->isStarted() )
			{
				$this->_sessionManager->start();
			}
		}
		catch( \Throwable $e )
		{
			return null;
		}

		return $this->_sessionManager;
	}

	/**
	 * HTML-escape helper.
	 *
	 * @param string $value
	 * @return string
	 */
	private function esc( string $value ): string
	{
		return htmlspecialchars( $value, ENT_QUOTES, 'UTF-8' );
	}
}
