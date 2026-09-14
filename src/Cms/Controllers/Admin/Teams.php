<?php

namespace Neuron\Cms\Controllers\Admin;

use Neuron\Cms\Auth\SessionManager;
use Neuron\Cms\Controllers\Content;
use Neuron\Cms\Enums\FlashMessageType;
use Neuron\Cms\Models\Team;
use Neuron\Cms\Models\TeamMember;
use Neuron\Cms\Repositories\ITeamRepository;
use Neuron\Cms\Services\SlugGenerator;
use Neuron\Data\Settings\SettingManager;
use Neuron\Mvc\IMvcApplication;
use Neuron\Mvc\Requests\Request;
use Neuron\Routing\Attributes\Delete;
use Neuron\Routing\Attributes\Get;
use Neuron\Routing\Attributes\Post;
use Neuron\Routing\Attributes\Put;
use Neuron\Routing\Attributes\RouteGroup;

/**
 * Admin named-team management.
 *
 * CRUD for teams and their members. Rosters are shown on pages via
 * the [team slug="staff"] shortcode.
 *
 * @package Neuron\Cms\Controllers\Admin
 */
#[RouteGroup(prefix: '/admin', filters: ['auth'])]
class Teams extends Content
{
	private ITeamRepository $_repository;
	private SlugGenerator $_slugs;

	/**
	 * @param IMvcApplication $app
	 * @param SettingManager $settings
	 * @param SessionManager $sessionManager
	 * @param ITeamRepository $repository
	 * @param SlugGenerator|null $slugs
	 */
	public function __construct(
		IMvcApplication $app,
		SettingManager $settings,
		SessionManager $sessionManager,
		ITeamRepository $repository,
		?SlugGenerator $slugs = null
	)
	{
		parent::__construct( $app, $settings, $sessionManager );

		$this->_repository = $repository;
		$this->_slugs      = $slugs ?? new SlugGenerator();
	}

	/**
	 * List teams.
	 */
	#[Get('/teams', name: 'admin_teams')]
	public function index( Request $request ): string
	{
		$this->initializeCsrfToken();

		$session = $this->getSessionManager();
		$teams   = $this->_repository->all();
		$counts  = [];

		foreach( $teams as $team )
		{
			$counts[ $team->getId() ] = $this->_repository->countMembers( $team->getId() );
		}

		return $this->view()
			->title( 'Teams | Admin' )
			->description( 'Manage teams' )
			->withCurrentUser()
			->withCsrfToken()
			->with( [
				'teams'  => $teams,
				'counts' => $counts,
				FlashMessageType::SUCCESS->viewKey() => $session->getFlash( FlashMessageType::SUCCESS->value ),
				FlashMessageType::ERROR->viewKey()   => $session->getFlash( FlashMessageType::ERROR->value )
			] )
			->render( 'index', 'admin' );
	}

	/**
	 * Show the create-team form.
	 */
	#[Get('/teams/create', name: 'admin_teams_create')]
	public function create( Request $request ): string
	{
		$this->initializeCsrfToken();

		return $this->view()
			->title( 'Add Team | Admin' )
			->description( 'Add a team' )
			->withCurrentUser()
			->withCsrfToken()
			->with( [ 'team' => null ] )
			->render( 'create', 'admin' );
	}

	/**
	 * Persist a new team.
	 */
	#[Post('/teams', name: 'admin_teams_store', filters: ['csrf'])]
	public function store( Request $request ): never
	{
		$name = trim( (string) ( $request->post( 'name', '' ) ?? '' ) );

		if( $name === '' )
		{
			$this->redirect( 'admin_teams_create', [], [ FlashMessageType::ERROR->value, 'Name is required.' ] );
		}

		$team = new Team();
		$team->setName( $name );
		$team->setSlug( $this->uniqueSlug( $name ) );
		$team->setDescription( $this->optional( $request, 'description' ) );

		try
		{
			$this->_repository->create( $team );
			$this->redirect( 'admin_teams_edit', [ 'id' => $team->getId() ], [ FlashMessageType::SUCCESS->value, 'Team created. Add members below.' ] );
		}
		catch( \Throwable $e )
		{
			$this->redirect( 'admin_teams_create', [], [ FlashMessageType::ERROR->value, 'Failed to create team: ' . $e->getMessage() ] );
		}
	}

	/**
	 * Show the edit-team form and member list.
	 */
	#[Get('/teams/:id/edit', name: 'admin_teams_edit')]
	public function edit( Request $request ): string
	{
		$team = $this->requireTeam( $request );

		$this->initializeCsrfToken();

		$session = $this->getSessionManager();

		return $this->view()
			->title( 'Edit Team | Admin' )
			->description( 'Edit a team' )
			->withCurrentUser()
			->withCsrfToken()
			->with( [
				'team'    => $team,
				'members' => $this->_repository->getMembers( $team->getId() ),
				FlashMessageType::SUCCESS->viewKey() => $session->getFlash( FlashMessageType::SUCCESS->value ),
				FlashMessageType::ERROR->viewKey()   => $session->getFlash( FlashMessageType::ERROR->value )
			] )
			->render( 'edit', 'admin' );
	}

	/**
	 * Update a team.
	 */
	#[Put('/teams/:id', name: 'admin_teams_update', filters: ['csrf'])]
	public function update( Request $request ): never
	{
		$team = $this->requireTeam( $request );
		$name = trim( (string) ( $request->post( 'name', '' ) ?? '' ) );

		if( $name === '' )
		{
			$this->redirect( 'admin_teams_edit', [ 'id' => $team->getId() ], [ FlashMessageType::ERROR->value, 'Name is required.' ] );
		}

		$originalName = $team->getName();
		$slugInput    = trim( (string) ( $request->post( 'slug', '' ) ?? '' ) );

		$team->setName( $name );
		$team->setDescription( $this->optional( $request, 'description' ) );

		if( $slugInput !== '' && $slugInput !== $team->getSlug() )
		{
			$team->setSlug( $this->uniqueSlug( $slugInput, $team->getId() ) );
		}
		elseif( $slugInput === '' && $name !== $originalName )
		{
			$team->setSlug( $this->uniqueSlug( $name, $team->getId() ) );
		}

		try
		{
			$this->_repository->update( $team );
			$this->redirect( 'admin_teams_edit', [ 'id' => $team->getId() ], [ FlashMessageType::SUCCESS->value, 'Team updated.' ] );
		}
		catch( \Throwable $e )
		{
			$this->redirect( 'admin_teams_edit', [ 'id' => $team->getId() ], [ FlashMessageType::ERROR->value, 'Failed to update team: ' . $e->getMessage() ] );
		}
	}

	/**
	 * Delete a team and its members.
	 */
	#[Delete('/teams/:id', name: 'admin_teams_destroy', filters: ['csrf'])]
	public function destroy( Request $request ): never
	{
		$team = $this->requireTeam( $request );

		try
		{
			$this->_repository->delete( $team );
			$this->redirect( 'admin_teams', [], [ FlashMessageType::SUCCESS->value, 'Team deleted.' ] );
		}
		catch( \Throwable $e )
		{
			$this->redirect( 'admin_teams', [], [ FlashMessageType::ERROR->value, 'Failed to delete team: ' . $e->getMessage() ] );
		}
	}

	/**
	 * Show the add-member form.
	 */
	#[Get('/teams/:id/members/create', name: 'admin_teams_members_create')]
	public function createMember( Request $request ): string
	{
		$team = $this->requireTeam( $request );

		$this->initializeCsrfToken();

		return $this->view()
			->title( 'Add Team Member | Admin' )
			->description( 'Add a team member' )
			->withCurrentUser()
			->withCsrfToken()
			->with( [
				'team'   => $team,
				'member' => null
			] )
			->render( 'member_create', 'admin' );
	}

	/**
	 * Persist a new member.
	 */
	#[Post('/teams/:id/members', name: 'admin_teams_members_store', filters: ['csrf'])]
	public function storeMember( Request $request ): never
	{
		$team = $this->requireTeam( $request );
		$name = trim( (string) ( $request->post( 'name', '' ) ?? '' ) );

		if( $name === '' )
		{
			$this->redirect( 'admin_teams_members_create', [ 'id' => $team->getId() ], [ FlashMessageType::ERROR->value, 'Name is required.' ] );
		}

		$member = $this->memberFromRequest( $request, $team->getId() );

		if( $request->post( 'sort_order', null ) === null || trim( (string) $request->post( 'sort_order', '' ) ) === '' )
		{
			$member->setSortOrder( $this->_repository->nextMemberSortOrder( $team->getId() ) );
		}

		try
		{
			$this->_repository->createMember( $member );
			$this->redirect( 'admin_teams_edit', [ 'id' => $team->getId() ], [ FlashMessageType::SUCCESS->value, 'Member added.' ] );
		}
		catch( \Throwable $e )
		{
			$this->redirect( 'admin_teams_members_create', [ 'id' => $team->getId() ], [ FlashMessageType::ERROR->value, 'Failed to add member: ' . $e->getMessage() ] );
		}
	}

	/**
	 * Show the edit-member form.
	 */
	#[Get('/teams/:id/members/:memberId/edit', name: 'admin_teams_members_edit')]
	public function editMember( Request $request ): string
	{
		$team   = $this->requireTeam( $request );
		$member = $this->requireMember( $request, $team );

		$this->initializeCsrfToken();

		return $this->view()
			->title( 'Edit Team Member | Admin' )
			->description( 'Edit a team member' )
			->withCurrentUser()
			->withCsrfToken()
			->with( [
				'team'   => $team,
				'member' => $member
			] )
			->render( 'member_edit', 'admin' );
	}

	/**
	 * Update a member.
	 */
	#[Put('/teams/:id/members/:memberId', name: 'admin_teams_members_update', filters: ['csrf'])]
	public function updateMember( Request $request ): never
	{
		$team   = $this->requireTeam( $request );
		$member = $this->requireMember( $request, $team );
		$name   = trim( (string) ( $request->post( 'name', '' ) ?? '' ) );

		if( $name === '' )
		{
			$this->redirect(
				'admin_teams_members_edit',
				[ 'id' => $team->getId(), 'memberId' => $member->getId() ],
				[ FlashMessageType::ERROR->value, 'Name is required.' ]
			);
		}

		$updated = $this->memberFromRequest( $request, $team->getId(), $member );

		try
		{
			$this->_repository->updateMember( $updated );
			$this->redirect( 'admin_teams_edit', [ 'id' => $team->getId() ], [ FlashMessageType::SUCCESS->value, 'Member updated.' ] );
		}
		catch( \Throwable $e )
		{
			$this->redirect(
				'admin_teams_members_edit',
				[ 'id' => $team->getId(), 'memberId' => $member->getId() ],
				[ FlashMessageType::ERROR->value, 'Failed to update member: ' . $e->getMessage() ]
			);
		}
	}

	/**
	 * Delete a member.
	 */
	#[Delete('/teams/:id/members/:memberId', name: 'admin_teams_members_destroy', filters: ['csrf'])]
	public function destroyMember( Request $request ): never
	{
		$team   = $this->requireTeam( $request );
		$member = $this->requireMember( $request, $team );

		try
		{
			$this->_repository->deleteMember( $member );
			$this->redirect( 'admin_teams_edit', [ 'id' => $team->getId() ], [ FlashMessageType::SUCCESS->value, 'Member removed.' ] );
		}
		catch( \Throwable $e )
		{
			$this->redirect( 'admin_teams_edit', [ 'id' => $team->getId() ], [ FlashMessageType::ERROR->value, 'Failed to remove member: ' . $e->getMessage() ] );
		}
	}

	private function requireTeam( Request $request ): Team
	{
		$id   = (int) $request->getRouteParameter( 'id' );
		$team = $this->_repository->findById( $id );

		if( $team === null )
		{
			$this->redirect( 'admin_teams', [], [ FlashMessageType::ERROR->value, 'Team not found.' ] );
		}

		return $team;
	}

	private function requireMember( Request $request, Team $team ): TeamMember
	{
		$memberId = (int) $request->getRouteParameter( 'memberId' );
		$member   = $this->_repository->findMemberById( $memberId );

		if( $member === null || $member->getTeamId() !== $team->getId() )
		{
			$this->redirect( 'admin_teams_edit', [ 'id' => $team->getId() ], [ FlashMessageType::ERROR->value, 'Member not found.' ] );
		}

		return $member;
	}

	private function uniqueSlug( string $text, ?int $excludeId = null ): string
	{
		return $this->_slugs->generateUnique(
			$text,
			fn( string $slug ): bool => $this->_repository->slugExists( $slug, $excludeId ),
			'team'
		);
	}

	private function optional( Request $request, string $field ): ?string
	{
		$value = trim( (string) ( $request->post( $field, '' ) ?? '' ) );

		return $value === '' ? null : $value;
	}

	private function memberFromRequest( Request $request, int $teamId, ?TeamMember $existing = null ): TeamMember
	{
		$member = $existing ?? new TeamMember();
		$member->setTeamId( $teamId );
		$member->setName( trim( (string) ( $request->post( 'name', '' ) ?? '' ) ) );
		$member->setTitle( $this->optional( $request, 'title' ) );
		$member->setBio( $this->optional( $request, 'bio' ) );
		$member->setContact( $this->optional( $request, 'contact' ) );
		$member->setImageUrl( $this->optional( $request, 'image_url' ) );
		$member->setSortOrder( (int) ( $request->post( 'sort_order', 0 ) ?? 0 ) );

		return $member;
	}
}
