<?php

namespace Tests\Unit\Cms\Services\Widget;

use Neuron\Cms\Models\Team;
use Neuron\Cms\Models\TeamMember;
use Neuron\Cms\Repositories\ITeamRepository;
use Neuron\Cms\Services\Widget\TeamWidget;
use PHPUnit\Framework\TestCase;

class TeamWidgetTest extends TestCase
{
	private $repository;
	private TeamWidget $widget;

	protected function setUp(): void
	{
		$this->repository = $this->createMock( ITeamRepository::class );
		$this->widget = new TeamWidget( $this->repository );
	}

	public function testGetNameReturnsTeam(): void
	{
		$this->assertSame( 'team', $this->widget->getName() );
	}

	public function testGetDescriptionReturnsString(): void
	{
		$description = $this->widget->getDescription();

		$this->assertIsString( $description );
		$this->assertNotEmpty( $description );
	}

	public function testGetAttributesIncludesSlugAndTitle(): void
	{
		$attrs = $this->widget->getAttributes();

		$this->assertArrayHasKey( 'slug', $attrs );
		$this->assertArrayHasKey( 'title', $attrs );
	}

	public function testRenderWithoutSlugReturnsComment(): void
	{
		$this->repository->expects( $this->never() )->method( 'findBySlug' );

		$result = $this->widget->render( [] );

		$this->assertStringContainsString( '<!-- Team widget: slug is required -->', $result );
	}

	public function testRenderWithUnknownSlugReturnsComment(): void
	{
		$this->repository->method( 'findBySlug' )->with( 'missing' )->willReturn( null );

		$result = $this->widget->render( [ 'slug' => 'missing' ] );

		$this->assertStringContainsString( '<!-- Team widget: team not found -->', $result );
	}

	public function testRenderWithEmptyTeamShowsEmptyState(): void
	{
		$team = $this->team( 1, 'Staff', 'staff' );

		$this->repository->method( 'findBySlug' )->with( 'staff' )->willReturn( $team );
		$this->repository->method( 'getMembers' )->with( 1 )->willReturn( [] );

		$result = $this->widget->render( [ 'slug' => 'staff' ] );

		$this->assertStringContainsString( 'cms-team', $result );
		$this->assertStringContainsString( 'No team members are listed yet.', $result );
	}

	public function testRenderWithMembersGeneratesCards(): void
	{
		$team = $this->team( 2, 'Board', 'board' );
		$member = $this->member( [
			'name' => 'Megan Leaf',
			'title' => 'Board Chair',
			'bio' => 'Community leader',
			'contact' => 'megan@example.com',
			'image_url' => 'https://cdn.example.com/megan.jpg'
		] );

		$this->repository->method( 'findBySlug' )->willReturn( $team );
		$this->repository->method( 'getMembers' )->willReturn( [ $member ] );

		$result = $this->widget->render( [ 'slug' => 'board', 'title' => 'Board of Directors' ] );

		$this->assertStringContainsString( 'cms-team-title', $result );
		$this->assertStringContainsString( 'Board of Directors', $result );
		$this->assertStringContainsString( 'Megan Leaf', $result );
		$this->assertStringContainsString( 'Board Chair', $result );
		$this->assertStringContainsString( 'Community leader', $result );
		$this->assertStringContainsString( 'mailto:megan@example.com', $result );
		$this->assertStringContainsString( 'https://cdn.example.com/megan.jpg', $result );
		$this->assertStringContainsString( 'data-team="board"', $result );
	}

	public function testRenderOmitsHeadingWhenTitleEmpty(): void
	{
		$team = $this->team( 1, 'Staff', 'staff' );

		$this->repository->method( 'findBySlug' )->willReturn( $team );
		$this->repository->method( 'getMembers' )->willReturn( [] );

		$result = $this->widget->render( [ 'slug' => 'staff' ] );

		$this->assertStringNotContainsString( 'cms-team-title', $result );
	}

	public function testRenderContactUrlIsNotMailto(): void
	{
		$team = $this->team( 1, 'Staff', 'staff' );
		$member = $this->member( [
			'name' => 'Pat',
			'contact' => 'https://example.com/pat'
		] );

		$this->repository->method( 'findBySlug' )->willReturn( $team );
		$this->repository->method( 'getMembers' )->willReturn( [ $member ] );

		$result = $this->widget->render( [ 'slug' => 'staff' ] );

		$this->assertStringContainsString( 'href="https://example.com/pat"', $result );
		$this->assertStringNotContainsString( 'mailto:', $result );
	}

	public function testRenderShowsPlaceholderWhenNoPhoto(): void
	{
		$team = $this->team( 1, 'Staff', 'staff' );
		$member = $this->member( [ 'name' => 'No Photo' ] );

		$this->repository->method( 'findBySlug' )->willReturn( $team );
		$this->repository->method( 'getMembers' )->willReturn( [ $member ] );

		$result = $this->widget->render( [ 'slug' => 'staff' ] );

		$this->assertStringContainsString( 'cms-team-photo--placeholder', $result );
		$this->assertStringNotContainsString( '<img', $result );
	}

	public function testRenderEscapesHtmlInMemberData(): void
	{
		$team = $this->team( 1, 'Staff', 'staff' );
		$member = $this->member( [
			'name' => '<script>alert("XSS")</script>',
			'title' => '<img src=x onerror=alert(1)>',
			'bio' => '<b>bold</b>',
			'contact' => 'https://example.com/"onclick="alert(1)'
		] );

		$this->repository->method( 'findBySlug' )->willReturn( $team );
		$this->repository->method( 'getMembers' )->willReturn( [ $member ] );

		$result = $this->widget->render( [ 'slug' => 'staff' ] );

		$this->assertStringNotContainsString( '<script>', $result );
		$this->assertStringNotContainsString( '<img src=x', $result );
		$this->assertStringContainsString( '&lt;script&gt;', $result );
	}

	private function team( int $id, string $name, string $slug ): Team
	{
		$team = new Team();
		$team->setId( $id );
		$team->setName( $name );
		$team->setSlug( $slug );

		return $team;
	}

	/**
	 * @param array<string, mixed> $data
	 */
	private function member( array $data ): TeamMember
	{
		$member = new TeamMember();
		$member->setName( $data['name'] ?? 'Name' );
		$member->setTitle( $data['title'] ?? null );
		$member->setBio( $data['bio'] ?? null );
		$member->setContact( $data['contact'] ?? null );
		$member->setImageUrl( $data['image_url'] ?? null );

		return $member;
	}
}
