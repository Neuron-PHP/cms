<?php

namespace Neuron\Cms\Repositories;

use Neuron\Cms\Models\Redirect;

/**
 * Repository interface for HTTP redirects.
 *
 * @package Neuron\Cms\Repositories
 */
interface IRedirectRepository
{
	/**
	 * @return Redirect[]
	 */
	public function all(): array;

	public function findById( int $id ): ?Redirect;

	public function findActiveByFromPath( string $fromPath ): ?Redirect;

	public function create( Redirect $redirect ): Redirect;

	public function update( Redirect $redirect ): Redirect;

	public function delete( Redirect $redirect ): bool;

	public function fromPathExists( string $fromPath, ?int $excludeId = null ): bool;
}
