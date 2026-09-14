<?php

namespace Neuron\Cms\Services\Widget;

use Neuron\Cms\Models\Team;
use Neuron\Cms\Models\TeamMember;
use Neuron\Cms\Repositories\ITeamRepository;

/**
 * Team roster widget / shortcode.
 *
 * Renders a named team's members as a card grid. Photos come from the
 * media library URL stored on each member.
 *
 *   [team slug="staff"]
 *   [team slug="board" title="Board of Directors"]
 *
 * Missing or unknown slug renders nothing visible (an HTML comment).
 *
 * @package Neuron\Cms\Services\Widget
 */
class TeamWidget implements IWidget
{
	private ITeamRepository $_teams;

	public function __construct( ITeamRepository $teams )
	{
		$this->_teams = $teams;
	}

	public function getName(): string
	{
		return 'team';
	}

	/**
	 * @param array<string, mixed> $attrs
	 */
	public function render( array $attrs ): string
	{
		$slug = trim( (string) ( $attrs['slug'] ?? '' ) );

		if( $slug === '' )
		{
			return '<!-- Team widget: slug is required -->';
		}

		$team = $this->_teams->findBySlug( $slug );

		if( !$team )
		{
			return '<!-- Team widget: team not found -->';
		}

		$members = $this->_teams->getMembers( $team->getId() );
		$title   = isset( $attrs['title'] ) ? trim( (string) $attrs['title'] ) : '';

		return $this->renderGrid( $team, $members, $title );
	}

	public function getDescription(): string
	{
		return 'Display a named team roster';
	}

	/**
	 * @return array<string, string>
	 */
	public function getAttributes(): array
	{
		return [
			'slug'  => 'Team slug (required), e.g. "staff" or "board"',
			'title' => 'Optional heading. Omit to render the roster without a heading.',
		];
	}

	/**
	 * @param TeamMember[] $members
	 */
	private function renderGrid( Team $team, array $members, string $title ): string
	{
		$html = '<div class="cms-team" data-team="' . $this->esc( $team->getSlug() ) . '">';

		if( $title !== '' )
		{
			$html .= '<h3 class="cms-team-title mb-4">' . $this->esc( $title ) . '</h3>';
		}

		if( $members === [] )
		{
			$html .= '<p class="text-muted mb-0">No team members are listed yet.</p></div>';

			return $html;
		}

		$html .= '<div class="row g-4 justify-content-center cms-team-grid">';

		foreach( $members as $member )
		{
			$html .= '<div class="col-6 col-md-4 col-lg-3">';
			$html .= $this->card( $member );
			$html .= '</div>';
		}

		$html .= '</div></div>';
		$html .= $this->styles();

		return $html;
	}

	private function card( TeamMember $member ): string
	{
		$name    = $this->esc( $member->getName() );
		$title   = $member->getTitle();
		$bio     = $member->getBio();
		$contact = $member->getContact();
		$image   = $member->getImageUrl();

		$html = '<div class="card cms-team-member border-0 shadow-sm h-100">';
		$html .= '<div class="card-body text-center p-4">';

		if( $image )
		{
			$html .= '<img class="cms-team-photo mb-3" src="' . $this->esc( $image ) . '" alt="' . $name . '" loading="lazy">';
		}
		else
		{
			$html .= '<span class="cms-team-photo cms-team-photo--placeholder mb-3" aria-hidden="true"></span>';
		}

		$html .= '<h2 class="h6 card-title mb-1">' . $name . '</h2>';

		if( $title )
		{
			$html .= '<p class="small text-muted mb-2">' . $this->esc( $title ) . '</p>';
		}

		if( $bio )
		{
			$html .= '<p class="small cms-team-bio mb-2">' . nl2br( $this->esc( $bio ) ) . '</p>';
		}

		if( $contact )
		{
			[ $href, $label ] = $this->resolveContact( $contact );
			$html .= '<a class="small text-break cms-team-contact" href="' . $this->esc( $href ) . '">' . $this->esc( $label ) . '</a>';
		}

		$html .= '</div></div>';

		return $html;
	}

	/**
	 * @return array{0: string, 1: string} href and link label
	 */
	private function resolveContact( string $contact ): array
	{
		if( filter_var( $contact, FILTER_VALIDATE_EMAIL ) )
		{
			return [ 'mailto:' . $contact, $contact ];
		}

		return [ $contact, $contact ];
	}

	private function esc( string $value ): string
	{
		return htmlspecialchars( $value, ENT_QUOTES, 'UTF-8' );
	}

	private function styles(): string
	{
		return '<style>'
			. '.cms-team-photo{width:100%;max-width:180px;aspect-ratio:1/1;object-fit:cover;object-position:top center;border-radius:50%;}'
			. '.cms-team-photo--placeholder{display:inline-block;background:#e9ecef;}'
			. '</style>';
	}
}
