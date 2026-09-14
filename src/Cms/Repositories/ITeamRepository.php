<?php

namespace Neuron\Cms\Repositories;

use Neuron\Cms\Models\Team;
use Neuron\Cms\Models\TeamMember;

/**
 * Repository interface for named teams and their members.
 *
 * @package Neuron\Cms\Repositories
 */
interface ITeamRepository
{
	/**
	 * @return Team[]
	 */
	public function all(): array;

	public function findById( int $id ): ?Team;

	public function findBySlug( string $slug ): ?Team;

	public function create( Team $team ): Team;

	public function update( Team $team ): Team;

	public function delete( Team $team ): bool;

	public function slugExists( string $slug, ?int $excludeId = null ): bool;

	/**
	 * Members of a team, ordered for display.
	 *
	 * @return TeamMember[]
	 */
	public function getMembers( int $teamId ): array;

	public function findMemberById( int $id ): ?TeamMember;

	public function createMember( TeamMember $member ): TeamMember;

	public function updateMember( TeamMember $member ): TeamMember;

	public function deleteMember( TeamMember $member ): bool;

	public function countMembers( int $teamId ): int;

	public function nextMemberSortOrder( int $teamId ): int;
}
