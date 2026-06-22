<?php

namespace Neuron\Cms\Services\Widget;

use Neuron\Cms\Auth\SessionManager;
use Neuron\Cms\Services\Contact\ContactService;
use Neuron\Cms\Services\Contact\FieldOptions;

/**
 * Contact form widget / shortcode.
 *
 * Renders a contact form whose fields come entirely from configuration, so a
 * single shortcode powers every contact form on the site:
 *
 *   [contact]                 -> default form
 *   [contact form="intake"]   -> the "intake" form
 *   [contact form="volunteer" title="Join Us" button="Apply"]
 *
 * The form posts to /contact/submit. A CSRF token is fetched client-side from
 * /contact/token so the markup remains valid even when the page is cached.
 *
 * @package Neuron\Cms\Services\Widget
 */
class ContactFormWidget implements IWidget
{
	private ContactService $_contactService;
	private ?SessionManager $_sessionManager;

	public function __construct( ContactService $contactService, ?SessionManager $sessionManager = null )
	{
		$this->_contactService = $contactService;
		$this->_sessionManager = $sessionManager;
	}

	/**
	 * Shortcode name.
	 */
	public function getName(): string
	{
		return 'contact';
	}

	/**
	 * Widget description (for documentation).
	 */
	public function getDescription(): string
	{
		return 'Render a configurable contact form that emails a per-form recipient.';
	}

	/**
	 * Supported shortcode attributes.
	 *
	 * @return array<string, string>
	 */
	public function getAttributes(): array
	{
		return [
			'form'   => 'Form key from the contact config (default: configured default_form)',
			'title'  => 'Optional heading override',
			'button' => 'Optional submit button label override'
		];
	}

	/**
	 * Render the contact form.
	 *
	 * @param array<string, mixed> $attrs Shortcode attributes
	 * @return string Rendered HTML
	 */
	public function render( array $attrs ): string
	{
		$key = (string) ( $attrs['form'] ?? $this->_contactService->getDefaultFormKey() );

		$config = $this->_contactService->getFormConfig( $key );

		if( $config === null )
		{
			return "<!-- contact widget: unknown form '" . htmlspecialchars( $key, ENT_QUOTES, 'UTF-8' ) . "' -->";
		}

		$fields = $config['fields'] ?? [];
		$title  = $attrs['title'] ?? ( $config['label'] ?? 'Contact Us' );
		$button = $attrs['button'] ?? ( $config['button'] ?? 'Send' );

		$idSuffix = preg_replace( '/[^a-z0-9_]/i', '_', $key );

		[ $successMessage, $errorMessage ] = $this->consumeFlash( $key );

		$html  = '<div class="contact-form-widget mb-4">';

		if( $title !== '' )
		{
			$html .= '<h3 class="contact-form-title mb-3">' . $this->esc( (string) $title ) . '</h3>';
		}

		if( $successMessage !== null )
		{
			$html .= '<div class="alert alert-success">' . $this->esc( $successMessage ) . '</div>';
		}

		if( $errorMessage !== null )
		{
			$html .= '<div class="alert alert-danger">' . $this->esc( $errorMessage ) . '</div>';
		}

		$html .= '<form class="contact-form" method="POST" action="/contact/submit" data-contact-form>';
		$html .= '<input type="hidden" name="form" value="' . $this->esc( $key ) . '">';
		$html .= '<input type="hidden" name="csrf_token" value="">';

		// Honeypot: hidden from humans; bots that fill it are rejected.
		$html .= '<div style="position:absolute;left:-5000px;height:0;overflow:hidden;" aria-hidden="true">';
		$html .= '<label>Company<input type="text" name="company_website" tabindex="-1" autocomplete="off"></label>';
		$html .= '</div>';

		foreach( $fields as $field )
		{
			$html .= $this->renderField( $field, $idSuffix );
		}

		$html .= '<button type="submit" class="btn btn-primary">' . $this->esc( (string) $button ) . '</button>';
		$html .= '</form>';
		$html .= '</div>';

		$html .= $this->tokenScript();

		return $html;
	}

	/**
	 * Render a single configured field as a Bootstrap form control.
	 *
	 * @param array $field
	 * @param string $idSuffix
	 * @return string
	 */
	private function renderField( array $field, string $idSuffix ): string
	{
		$name = $field['name'] ?? null;

		if( $name === null )
		{
			return '';
		}

		$type     = $field['type'] ?? 'text';
		$label    = $field['label'] ?? $name;
		$required = !empty( $field['required'] );
		$id       = 'contact_' . $idSuffix . '_' . preg_replace( '/[^a-z0-9_]/i', '_', $name );
		$reqAttr  = $required ? ' required' : '';
		$reqMark  = $required ? ' <span class="text-danger">*</span>' : '';

		if( $type === 'checkbox' )
		{
			return '<div class="form-check mb-3">'
				. '<input type="checkbox" class="form-check-input" id="' . $this->esc( $id ) . '" name="' . $this->esc( $name ) . '" value="1"' . $reqAttr . '>'
				. '<label class="form-check-label" for="' . $this->esc( $id ) . '">' . $this->labelWithLink( $label, $field ) . $reqMark . '</label>'
				. '</div>';
		}

		if( $type === 'checkboxes' )
		{
			return $this->renderCheckboxes( $field, $idSuffix, $name, $label, $reqMark );
		}

		if( $type === 'radio' )
		{
			return $this->renderRadios( $field, $idSuffix, $name, $label, $required, $reqMark );
		}

		$control = '<div class="mb-3">';
		$control .= '<label class="form-label" for="' . $this->esc( $id ) . '">' . $this->labelWithLink( $label, $field ) . $reqMark . '</label>';

		switch( $type )
		{
			case 'textarea':
				$control .= '<textarea class="form-control" id="' . $this->esc( $id ) . '" name="' . $this->esc( $name ) . '" rows="5"' . $reqAttr . '></textarea>';
				break;

			case 'select':
				$control .= '<select class="form-select" id="' . $this->esc( $id ) . '" name="' . $this->esc( $name ) . '"' . $reqAttr . '>';
				$control .= '<option value="">-- Select --</option>';

				foreach( ( $field['options'] ?? [] ) as $option )
				{
					$optionValue = is_array( $option ) ? ( $option['value'] ?? '' ) : $option;
					$optionLabel = is_array( $option ) ? ( $option['label'] ?? $optionValue ) : $option;
					$control .= '<option value="' . $this->esc( (string) $optionValue ) . '">' . $this->esc( (string) $optionLabel ) . '</option>';
				}

				$control .= '</select>';
				break;

			case 'email':
			case 'tel':
			case 'date':
			case 'number':
			case 'url':
			case 'text':
			default:
				$inputType = in_array( $type, [ 'email', 'tel', 'date', 'number', 'url' ], true ) ? $type : 'text';
				$control .= '<input type="' . $this->esc( $inputType ) . '" class="form-control" id="' . $this->esc( $id ) . '" name="' . $this->esc( $name ) . '"' . $reqAttr . '>';
				break;
		}

		$control .= '</div>';

		return $control;
	}

	/**
	 * Render a grouped/flat multi-select as a set of checkboxes.
	 *
	 * Values post as name[] so several selections arrive as an array. The
	 * group-level required flag is enforced server-side (at least one box),
	 * since a native HTML "required" on each box would force every option.
	 *
	 * @param array $field
	 * @param string $idSuffix
	 * @param string $name
	 * @param string $label
	 * @param string $reqMark
	 * @return string
	 */
	private function renderCheckboxes( array $field, string $idSuffix, string $name, string $label, string $reqMark ): string
	{
		$groups = FieldOptions::groups( $field );

		$html  = '<fieldset class="mb-3 contact-checkboxes">';
		$html .= '<legend class="form-label fs-6">' . $this->labelWithLink( $label, $field ) . $reqMark . '</legend>';

		foreach( $groups as $group )
		{
			$groupLabel = $group['label'] ?? '';

			if( $groupLabel !== '' )
			{
				$html .= '<div class="contact-checkbox-group-label fw-semibold mt-2 mb-1">' . $this->esc( (string) $groupLabel ) . '</div>';
			}

			foreach( $group['options'] as $option )
			{
				$value = $option['value'];
				$id    = 'contact_' . $idSuffix . '_' . preg_replace( '/[^a-z0-9_]/i', '_', $name . '_' . $value );

				$html .= '<div class="form-check">';
				$html .= '<input type="checkbox" class="form-check-input" id="' . $this->esc( $id ) . '" name="' . $this->esc( $name ) . '[]" value="' . $this->esc( $value ) . '">';
				$html .= '<label class="form-check-label" for="' . $this->esc( $id ) . '">' . $this->esc( $option['label'] ) . '</label>';
				$html .= '</div>';
			}
		}

		$html .= '</fieldset>';

		return $html;
	}

	/**
	 * Render a single-choice field as a set of radio buttons.
	 *
	 * Posts a single scalar value (name=field). Options may be flat or grouped
	 * and are validated server-side against the configured option set.
	 *
	 * @param array $field
	 * @param string $idSuffix
	 * @param string $name
	 * @param string $label
	 * @param bool $required
	 * @param string $reqMark
	 * @return string
	 */
	private function renderRadios( array $field, string $idSuffix, string $name, string $label, bool $required, string $reqMark ): string
	{
		$reqAttr = $required ? ' required' : '';

		$html  = '<fieldset class="mb-3 contact-radios">';
		$html .= '<legend class="form-label fs-6">' . $this->labelWithLink( $label, $field ) . $reqMark . '</legend>';

		foreach( FieldOptions::groups( $field ) as $group )
		{
			$groupLabel = $group['label'] ?? '';

			if( $groupLabel !== '' )
			{
				$html .= '<div class="contact-radio-group-label fw-semibold mt-2 mb-1">' . $this->esc( (string) $groupLabel ) . '</div>';
			}

			foreach( $group['options'] as $option )
			{
				$value = $option['value'];
				$id    = 'contact_' . $idSuffix . '_' . preg_replace( '/[^a-z0-9_]/i', '_', $name . '_' . $value );

				$html .= '<div class="form-check">';
				$html .= '<input type="radio" class="form-check-input" id="' . $this->esc( $id ) . '" name="' . $this->esc( $name ) . '" value="' . $this->esc( $value ) . '"' . $reqAttr . '>';
				$html .= '<label class="form-check-label" for="' . $this->esc( $id ) . '">' . $this->esc( $option['label'] ) . '</label>';
				$html .= '</div>';
			}
		}

		$html .= '</fieldset>';

		return $html;
	}

	/**
	 * Inline script (rendered once per page) that fetches a fresh CSRF token
	 * and injects it into every contact form, keeping cached markup valid.
	 *
	 * @return string
	 */
	private function tokenScript(): string
	{
		return <<<'HTML'
<script>
(function() {
	if( window.__contactFormTokenInit ) { return; }
	window.__contactFormTokenInit = true;
	document.addEventListener('DOMContentLoaded', function() {
		fetch('/contact/token', { headers: { 'Accept': 'application/json' }, credentials: 'same-origin' })
			.then(function(r) { return r.json(); })
			.then(function(data) {
				if( !data || !data.token ) { return; }
				document.querySelectorAll('form[data-contact-form] input[name="csrf_token"]').forEach(function(input) {
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
	 * Read and clear per-form flash messages set by the controller.
	 *
	 * @param string $key
	 * @return array{0: ?string, 1: ?string} [success, error]
	 */
	private function consumeFlash( string $key ): array
	{
		$session = $this->session();

		if( $session === null )
		{
			return [ null, null ];
		}

		$success = $session->getFlash( 'contact.' . $key . '.success' );
		$error   = $session->getFlash( 'contact.' . $key . '.error' );

		return [
			$success !== null ? (string) $success : null,
			$error !== null ? (string) $error : null
		];
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
	 * Build a field's (escaped) label, optionally embedding a safe hyperlink.
	 *
	 * A field may declare a link via config:
	 *
	 *   link: { text: "Volunteer Agreement", url: "/pages/release-form" }
	 *
	 * Use the literal token "{link}" in the label to control placement; if the
	 * token is absent the link is appended. Label text, link text and URL are
	 * all escaped, so only this controlled anchor is ever injected as HTML.
	 *
	 * @param string $label
	 * @param array $field
	 * @return string
	 */
	private function labelWithLink( string $label, array $field ): string
	{
		$escaped = $this->esc( $label );
		$link    = $field['link'] ?? null;

		if( !is_array( $link ) || empty( $link['url'] ) )
		{
			return $escaped;
		}

		$url    = $this->esc( (string) $link['url'] );
		$text   = $this->esc( (string) ( $link['text'] ?? $link['url'] ) );
		$anchor = '<a href="' . $url . '" target="_blank" rel="noopener noreferrer">' . $text . '</a>';

		if( str_contains( $escaped, '{link}' ) )
		{
			return str_replace( '{link}', $anchor, $escaped );
		}

		return $escaped . ' ' . $anchor;
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
