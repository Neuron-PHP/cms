<?php

namespace Tests\Unit\Cms\Repositories;

use Neuron\Cms\Models\Team;
use Neuron\Cms\Models\TeamMember;
use Neuron\Cms\Repositories\DatabaseTeamRepository;
use Neuron\Data\Settings\SettingManager;
use PHPUnit\Framework\TestCase;
use PDO;

/**
 * @runTestsInSeparateProcesses
 * @preserveGlobalState disabled
 */
class DatabaseTeamRepositoryTest extends TestCase
{
	private PDO $pdo;
	private DatabaseTeamRepository $repository;

	protected function setUp(): void
	{
		$this->pdo = new PDO( 'sqlite::memory:' );
		$this->pdo->setAttribute( PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION );

		$this->pdo->exec( "
			CREATE TABLE teams (
				id INTEGER PRIMARY KEY AUTOINCREMENT,
				name VARCHAR(255) NOT NULL,
				slug VARCHAR(255) UNIQUE NOT NULL,
				description TEXT,
				created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
				updated_at TIMESTAMP
			)
		" );

		$this->pdo->exec( "
			CREATE TABLE team_members (
				id INTEGER PRIMARY KEY AUTOINCREMENT,
				team_id INTEGER NOT NULL,
				name VARCHAR(255) NOT NULL,
				title VARCHAR(255),
				bio TEXT,
				contact VARCHAR(512),
				image_url VARCHAR(512),
				sort_order INTEGER NOT NULL DEFAULT 0,
				created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
				updated_at TIMESTAMP
			)
		" );

		$settings = $this->createMock( SettingManager::class );
		$settings->method( 'getSection' )
			->willReturn( [
				'adapter' => 'sqlite',
				'name' => ':memory:'
			] );

		$this->repository = new DatabaseTeamRepository( $settings );
		$reflection = new \ReflectionClass( $this->repository );
		$property = $reflection->getProperty( '_pdo' );
		$property->setValue( $this->repository, $this->pdo );
	}

	private function makeTeam( string $name = 'Staff', string $slug = 'staff' ): Team
	{
		$team = new Team();
		$team->setName( $name );
		$team->setSlug( $slug );
		$team->setDescription( 'The staff roster' );

		return $this->repository->create( $team );
	}

	private function makeMember( int $teamId, string $name = 'Ada Lovelace', array $overrides = [] ): TeamMember
	{
		$member = new TeamMember();
		$member->setTeamId( $teamId );
		$member->setName( $name );
		$member->setTitle( $overrides['title'] ?? 'Mathematician' );
		$member->setBio( $overrides['bio'] ?? null );
		$member->setContact( $overrides['contact'] ?? 'ada@example.com' );
		$member->setImageUrl( $overrides['image_url'] ?? null );
		$member->setSortOrder( $overrides['sort_order'] ?? 0 );

		return $this->repository->createMember( $member );
	}

	public function testAllReturnsEmptyArrayWhenNoTeams(): void
	{
		$this->assertSame( [], $this->repository->all() );
	}

	public function testCreateAndFindById(): void
	{
		$team = $this->makeTeam();

		$found = $this->repository->findById( $team->getId() );

		$this->assertNotNull( $found );
		$this->assertSame( 'Staff', $found->getName() );
		$this->assertSame( 'staff', $found->getSlug() );
		$this->assertSame( 'The staff roster', $found->getDescription() );
	}

	public function testFindBySlug(): void
	{
		$this->makeTeam( 'Board', 'board' );

		$this->assertNotNull( $this->repository->findBySlug( 'board' ) );
		$this->assertNull( $this->repository->findBySlug( 'missing' ) );
	}

	public function testAllReturnsTeamsSortedByName(): void
	{
		$this->makeTeam( 'Staff', 'staff' );
		$this->makeTeam( 'Board', 'board' );

		$teams = $this->repository->all();

		$this->assertCount( 2, $teams );
		$this->assertSame( 'Board', $teams[0]->getName() );
		$this->assertSame( 'Staff', $teams[1]->getName() );
	}

	public function testUpdateChangesFields(): void
	{
		$team = $this->makeTeam();
		$team->setName( 'Leadership' );
		$team->setDescription( null );

		$this->repository->update( $team );

		$found = $this->repository->findById( $team->getId() );
		$this->assertSame( 'Leadership', $found->getName() );
		$this->assertNull( $found->getDescription() );
	}

	public function testSlugExists(): void
	{
		$team = $this->makeTeam();

		$this->assertTrue( $this->repository->slugExists( 'staff' ) );
		$this->assertFalse( $this->repository->slugExists( 'staff', $team->getId() ) );
		$this->assertFalse( $this->repository->slugExists( 'missing' ) );
	}

	public function testDeleteRemovesTeamAndMembers(): void
	{
		$team = $this->makeTeam();
		$this->makeMember( $team->getId() );

		$this->assertTrue( $this->repository->delete( $team ) );
		$this->assertNull( $this->repository->findById( $team->getId() ) );
		$this->assertSame( 0, $this->repository->countMembers( $team->getId() ) );
	}

	public function testCreateAndFindMember(): void
	{
		$team = $this->makeTeam();
		$member = $this->makeMember( $team->getId(), 'Grace Hopper', [
			'title' => 'Rear Admiral',
			'bio' => 'COBOL pioneer',
			'contact' => 'https://example.com/grace',
			'image_url' => 'https://cdn.example.com/grace.jpg',
			'sort_order' => 3
		] );

		$found = $this->repository->findMemberById( $member->getId() );

		$this->assertNotNull( $found );
		$this->assertSame( 'Grace Hopper', $found->getName() );
		$this->assertSame( 'Rear Admiral', $found->getTitle() );
		$this->assertSame( 'COBOL pioneer', $found->getBio() );
		$this->assertSame( 'https://example.com/grace', $found->getContact() );
		$this->assertSame( 'https://cdn.example.com/grace.jpg', $found->getImageUrl() );
		$this->assertSame( 3, $found->getSortOrder() );
	}

	public function testGetMembersOrdersBySortThenName(): void
	{
		$team = $this->makeTeam();
		$this->makeMember( $team->getId(), 'Charlie', [ 'sort_order' => 2 ] );
		$this->makeMember( $team->getId(), 'Alice', [ 'sort_order' => 1 ] );
		$this->makeMember( $team->getId(), 'Bob', [ 'sort_order' => 1 ] );

		$members = $this->repository->getMembers( $team->getId() );

		$this->assertCount( 3, $members );
		$this->assertSame( 'Alice', $members[0]->getName() );
		$this->assertSame( 'Bob', $members[1]->getName() );
		$this->assertSame( 'Charlie', $members[2]->getName() );
	}

	public function testUpdateMemberChangesFields(): void
	{
		$team = $this->makeTeam();
		$member = $this->makeMember( $team->getId() );
		$member->setTitle( 'Updated title' );
		$member->setContact( null );

		$this->repository->updateMember( $member );

		$found = $this->repository->findMemberById( $member->getId() );
		$this->assertSame( 'Updated title', $found->getTitle() );
		$this->assertNull( $found->getContact() );
	}

	public function testDeleteMemberRemovesRow(): void
	{
		$team = $this->makeTeam();
		$member = $this->makeMember( $team->getId() );

		$this->assertTrue( $this->repository->deleteMember( $member ) );
		$this->assertNull( $this->repository->findMemberById( $member->getId() ) );
	}

	public function testCountMembersAndNextSortOrder(): void
	{
		$team = $this->makeTeam();

		$this->assertSame( 0, $this->repository->countMembers( $team->getId() ) );
		$this->assertSame( 0, $this->repository->nextMemberSortOrder( $team->getId() ) );

		$this->makeMember( $team->getId(), 'A', [ 'sort_order' => 0 ] );
		$this->makeMember( $team->getId(), 'B', [ 'sort_order' => 4 ] );

		$this->assertSame( 2, $this->repository->countMembers( $team->getId() ) );
		$this->assertSame( 5, $this->repository->nextMemberSortOrder( $team->getId() ) );
	}

	public function testFindMemberByIdReturnsNullWhenMissing(): void
	{
		$this->assertNull( $this->repository->findMemberById( 999 ) );
	}
}
