<?php

namespace Neuron\Cms\Services\Widget;

use Neuron\Cms\Models\Faq;
use Neuron\Cms\Models\FaqItem;
use Neuron\Cms\Repositories\IFaqRepository;

/**
 * Named FAQ accordion widget / shortcode.
 *
 *   [faq slug="general"]
 *   [faq slug="intake" title="Intake FAQs" open="first"]
 *
 * @package Neuron\Cms\Services\Widget
 */
class FaqWidget implements IWidget
{
	private IFaqRepository $_faqs;

	public function __construct( IFaqRepository $faqs )
	{
		$this->_faqs = $faqs;
	}

	public function getName(): string
	{
		return 'faq';
	}

	/**
	 * @param array<string, mixed> $attrs
	 */
	public function render( array $attrs ): string
	{
		$slug = trim( (string) ( $attrs['slug'] ?? '' ) );

		if( $slug === '' )
		{
			return '<!-- FAQ widget: slug is required -->';
		}

		$faq = $this->_faqs->findBySlug( $slug );

		if( !$faq )
		{
			return '<!-- FAQ widget: group not found -->';
		}

		$items = $this->_faqs->getItems( $faq->getId() );
		$title = isset( $attrs['title'] ) ? trim( (string) $attrs['title'] ) : '';
		$open  = strtolower( trim( (string) ( $attrs['open'] ?? '' ) ) );

		return $this->renderAccordion( $faq, $items, $title, $open );
	}

	public function getDescription(): string
	{
		return 'Display a named FAQ accordion';
	}

	/**
	 * @return array<string, string>
	 */
	public function getAttributes(): array
	{
		return [
			'slug' => 'FAQ group slug (required), e.g. "general"',
			'title' => 'Optional heading. Omit to render the accordion only.',
			'open' => 'none, first, or all. Defaults to none.',
		];
	}

	/**
	 * @param FaqItem[] $items
	 */
	private function renderAccordion( Faq $faq, array $items, string $title, string $open ): string
	{
		$id = 'cms-faq-' . preg_replace( '/[^a-z0-9-]/', '', strtolower( $faq->getSlug() ) );
		$html = '<div class="cms-faq" data-faq="' . $this->esc( $faq->getSlug() ) . '">';

		if( $title !== '' )
		{
			$html .= '<h3 class="cms-faq-title mb-4">' . $this->esc( $title ) . '</h3>';
		}

		if( $items === [] )
		{
			$html .= '<p class="text-muted mb-0">No questions are listed yet.</p></div>';

			return $html;
		}

		$html .= '<div class="accordion cms-faq-accordion" id="' . $this->esc( $id ) . '">';

		foreach( $items as $index => $item )
		{
			$expanded = $open === 'all' || ( $open === 'first' && $index === 0 );
			$itemId = $id . '-' . ( $item->getId() ?? $index );
			$headingId = $itemId . '-heading';
			$collapseId = $itemId . '-collapse';

			$html .= '<div class="accordion-item">';
			$html .= '<h2 class="accordion-header" id="' . $this->esc( $headingId ) . '">';
			$html .= '<button class="accordion-button' . ( $expanded ? '' : ' collapsed' ) . '" type="button" data-bs-toggle="collapse" data-bs-target="#' . $this->esc( $collapseId ) . '" aria-expanded="' . ( $expanded ? 'true' : 'false' ) . '" aria-controls="' . $this->esc( $collapseId ) . '">';
			$html .= $this->esc( $item->getQuestion() );
			$html .= '</button></h2>';
			$parent = $open === 'all' ? '' : ' data-bs-parent="#' . $this->esc( $id ) . '"';
			$html .= '<div id="' . $this->esc( $collapseId ) . '" class="accordion-collapse collapse' . ( $expanded ? ' show' : '' ) . '" aria-labelledby="' . $this->esc( $headingId ) . '"' . $parent . '">';
			$html .= '<div class="accordion-body">' . nl2br( $this->esc( $item->getAnswer() ) ) . '</div>';
			$html .= '</div></div>';
		}

		$html .= '</div></div>';

		return $html;
	}

	private function esc( string $value ): string
	{
		return htmlspecialchars( $value, ENT_QUOTES, 'UTF-8' );
	}
}
