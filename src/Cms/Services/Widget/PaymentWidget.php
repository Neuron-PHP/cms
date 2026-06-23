<?php

namespace Neuron\Cms\Services\Widget;

use Neuron\Cms\Auth\SessionManager;
use Neuron\Cms\Services\Payment\PaymentService;

/**
 * Payment form widget / shortcode.
 *
 * Renders a configurable payment form powered entirely by the `payments`
 * config section, so a single shortcode drives every payment form regardless
 * of purpose ( donation, membership, ... ):
 *
 *   [payment]                          -> default form
 *   [payment form="membership"]        -> the "membership" form
 *   [donation]                         -> default donation form ( preset )
 *   [donation form="general" title="Support Us" button="Give"]
 *
 * The form posts to /payments/checkout, which creates a hosted Stripe Checkout
 * session ( one-time or subscription ) and redirects the payer. A CSRF token is
 * fetched client-side from /payments/token so the markup stays valid when the
 * page is cached.
 *
 * @package Neuron\Cms\Services\Widget
 */
class PaymentWidget implements IWidget
{
	/**
	 * Labels for the known recurrence values, kept local so the widget stays
	 * decoupled from the optional payments package.
	 */
	private const FREQUENCY_LABELS = [
		'one_time'   => 'One-time',
		'monthly'    => 'Monthly',
		'quarterly'  => 'Quarterly',
		'semiannual' => 'Semi-annually',
		'annual'     => 'Annually'
	];

	private const CURRENCY_SYMBOLS = [
		'usd' => '$',
		'eur' => '€',
		'gbp' => '£',
		'cad' => '$',
		'aud' => '$'
	];

	private PaymentService $_paymentService;
	private ?SessionManager $_sessionManager;

	public function __construct( PaymentService $paymentService, ?SessionManager $sessionManager = null )
	{
		$this->_paymentService = $paymentService;
		$this->_sessionManager = $sessionManager;
	}

	/**
	 * Shortcode name.
	 */
	public function getName(): string
	{
		return 'payment';
	}

	/**
	 * Widget description (for documentation).
	 */
	public function getDescription(): string
	{
		return 'Render a configurable payment / donation form processed via hosted Stripe Checkout.';
	}

	/**
	 * Supported shortcode attributes.
	 *
	 * @return array<string, string>
	 */
	public function getAttributes(): array
	{
		return [
			'form'   => 'Form key from the payments config (default: configured default_form)',
			'title'  => 'Optional heading override',
			'button' => 'Optional submit button label override'
		];
	}

	/**
	 * Render the payment form.
	 *
	 * @param array<string, mixed> $attrs Shortcode attributes
	 * @return string Rendered HTML
	 */
	public function render( array $attrs ): string
	{
		$key = (string) ( $attrs['form'] ?? $this->_paymentService->getDefaultFormKey() );

		$config = $this->_paymentService->getFormConfig( $key );

		if( $config === null )
		{
			return "<!-- payment widget: unknown form '" . $this->esc( $key ) . "' -->";
		}

		$isDonation = $this->_paymentService->purpose( $key ) === 'donation';
		$fields     = $config['fields'] ?? [];
		$title      = $attrs['title'] ?? ( $config['label'] ?? ( $isDonation ? 'Make a Donation' : 'Make a Payment' ) );
		$button     = $attrs['button'] ?? ( $config['button'] ?? ( $isDonation ? 'Donate' : 'Pay' ) );

		$idSuffix = preg_replace( '/[^a-z0-9_]/i', '_', $key );

		[ $successMessage, $errorMessage ] = $this->consumeFlash( $key );

		$html = '<div class="payment-form-widget mb-4">';

		if( $title !== '' )
		{
			$html .= '<h3 class="payment-form-title mb-3">' . $this->esc( (string) $title ) . '</h3>';
		}

		if( $successMessage !== null )
		{
			$html .= '<div class="alert alert-success">' . $this->esc( $successMessage ) . '</div>';
		}

		if( $errorMessage !== null )
		{
			$html .= '<div class="alert alert-danger">' . $this->esc( $errorMessage ) . '</div>';
		}

		$html .= '<form class="payment-form" method="POST" action="/payments/checkout" data-payment-form>';
		$html .= '<input type="hidden" name="form" value="' . $this->esc( $key ) . '">';
		$html .= '<input type="hidden" name="csrf_token" value="">';

		// Honeypot: hidden from humans; bots that fill it are rejected.
		$html .= '<div style="position:absolute;left:-5000px;height:0;overflow:hidden;" aria-hidden="true">';
		$html .= '<label>Company<input type="text" name="company_website" tabindex="-1" autocomplete="off"></label>';
		$html .= '</div>';

		$html .= $this->renderAmounts( $key, $idSuffix );
		$html .= $this->renderFrequencies( $key, $idSuffix );

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
	 * Render the amount selector: preset tiers plus an optional custom amount.
	 *
	 * @param string $key
	 * @param string $idSuffix
	 * @return string
	 */
	private function renderAmounts( string $key, string $idSuffix ): string
	{
		$symbol  = self::CURRENCY_SYMBOLS[ $this->_paymentService->getCurrency() ] ?? '';
		$presets = $this->_paymentService->presetAmounts( $key );

		$html  = '<fieldset class="mb-3 payment-amounts">';
		$html .= '<legend class="form-label fs-6">Amount <span class="text-danger">*</span></legend>';

		$first = true;

		foreach( $presets as $amount )
		{
			$value = $this->formatAmountValue( $amount );
			$id    = 'payment_' . $idSuffix . '_amount_' . preg_replace( '/[^a-z0-9_]/i', '_', $value );

			$html .= '<div class="form-check form-check-inline">';
			$html .= '<input type="radio" class="form-check-input" id="' . $this->esc( $id ) . '" name="amount" value="' . $this->esc( $value ) . '"' . ( $first ? ' checked' : '' ) . '>';
			$html .= '<label class="form-check-label" for="' . $this->esc( $id ) . '">' . $this->esc( $symbol . $value ) . '</label>';
			$html .= '</div>';

			$first = false;
		}

		if( $this->_paymentService->allowsCustomAmount( $key ) )
		{
			$customRadioId = 'payment_' . $idSuffix . '_amount_custom';
			$customInputId = 'payment_' . $idSuffix . '_custom_amount';

			$html .= '<div class="form-check form-check-inline">';
			$html .= '<input type="radio" class="form-check-input" id="' . $this->esc( $customRadioId ) . '" name="amount" value="custom"' . ( $presets === [] ? ' checked' : '' ) . '>';
			$html .= '<label class="form-check-label" for="' . $this->esc( $customRadioId ) . '">Other</label>';
			$html .= '</div>';

			$html .= '<div class="input-group mt-2 payment-custom-amount">';

			if( $symbol !== '' )
			{
				$html .= '<span class="input-group-text">' . $this->esc( $symbol ) . '</span>';
			}

			$html .= '<input type="number" class="form-control" id="' . $this->esc( $customInputId ) . '" name="custom_amount" min="' . $this->esc( $this->formatAmountValue( $this->_paymentService->minimumAmount( $key ) ) ) . '" step="0.01" placeholder="Enter amount">';
			$html .= '</div>';
		}

		$html .= '</fieldset>';

		return $html;
	}

	/**
	 * Render the recurrence selector when more than one cadence is allowed.
	 *
	 * @param string $key
	 * @param string $idSuffix
	 * @return string
	 */
	private function renderFrequencies( string $key, string $idSuffix ): string
	{
		$frequencies = $this->_paymentService->allowedFrequencies( $key );

		if( count( $frequencies ) <= 1 )
		{
			// Single cadence: submit it as a hidden value, no selector needed.
			$only = $frequencies[0] ?? 'one_time';

			return '<input type="hidden" name="frequency" value="' . $this->esc( $only ) . '">';
		}

		$html  = '<fieldset class="mb-3 payment-frequencies">';
		$html .= '<legend class="form-label fs-6">Frequency</legend>';

		$first = true;

		foreach( $frequencies as $frequency )
		{
			$label = self::FREQUENCY_LABELS[ $frequency ] ?? ucfirst( $frequency );
			$id    = 'payment_' . $idSuffix . '_freq_' . preg_replace( '/[^a-z0-9_]/i', '_', $frequency );

			$html .= '<div class="form-check form-check-inline">';
			$html .= '<input type="radio" class="form-check-input" id="' . $this->esc( $id ) . '" name="frequency" value="' . $this->esc( $frequency ) . '"' . ( $first ? ' checked' : '' ) . '>';
			$html .= '<label class="form-check-label" for="' . $this->esc( $id ) . '">' . $this->esc( $label ) . '</label>';
			$html .= '</div>';

			$first = false;
		}

		$html .= '</fieldset>';

		return $html;
	}

	/**
	 * Render a single configured payer field as a Bootstrap control.
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
		$id       = 'payment_' . $idSuffix . '_' . preg_replace( '/[^a-z0-9_]/i', '_', $name );
		$reqAttr  = $required ? ' required' : '';
		$reqMark  = $required ? ' <span class="text-danger">*</span>' : '';

		if( $type === 'checkbox' )
		{
			return '<div class="form-check mb-3">'
				. '<input type="checkbox" class="form-check-input" id="' . $this->esc( $id ) . '" name="' . $this->esc( $name ) . '" value="1"' . $reqAttr . '>'
				. '<label class="form-check-label" for="' . $this->esc( $id ) . '">' . $this->esc( $label ) . $reqMark . '</label>'
				. '</div>';
		}

		$control  = '<div class="mb-3">';
		$control .= '<label class="form-label" for="' . $this->esc( $id ) . '">' . $this->esc( $label ) . $reqMark . '</label>';

		switch( $type )
		{
			case 'textarea':
				$control .= '<textarea class="form-control" id="' . $this->esc( $id ) . '" name="' . $this->esc( $name ) . '" rows="3"' . $reqAttr . '></textarea>';
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
	 * Format an amount for use as a form value, dropping the trailing .00 on
	 * whole amounts for cleaner labels.
	 *
	 * @param float $amount
	 * @return string
	 */
	private function formatAmountValue( float $amount ): string
	{
		if( floor( $amount ) === $amount )
		{
			return (string) (int) $amount;
		}

		return rtrim( rtrim( number_format( $amount, 2, '.', '' ), '0' ), '.' );
	}

	/**
	 * Inline script (once per page) that fetches a fresh CSRF token and injects
	 * it into every payment form, keeping cached markup valid.
	 *
	 * @return string
	 */
	private function tokenScript(): string
	{
		return <<<'HTML'
<script>
(function() {
	if( window.__paymentFormTokenInit ) { return; }
	window.__paymentFormTokenInit = true;
	document.addEventListener('DOMContentLoaded', function() {
		fetch('/payments/token', { headers: { 'Accept': 'application/json' }, credentials: 'same-origin' })
			.then(function(r) { return r.json(); })
			.then(function(data) {
				if( !data || !data.token ) { return; }
				document.querySelectorAll('form[data-payment-form] input[name="csrf_token"]').forEach(function(input) {
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

		$success = $session->getFlash( 'payment.' . $key . '.success' );
		$error   = $session->getFlash( 'payment.' . $key . '.error' );

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
