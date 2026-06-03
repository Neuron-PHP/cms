<?php

namespace Neuron\Cms\Services\Revision;

use Neuron\Cms\Models\Page;
use Neuron\Cms\Models\Post;
use Neuron\Cms\Models\Revision;
use Neuron\Cms\Repositories\IRevisionRepository;

/**
 * Records and retrieves content revisions for pages and posts.
 *
 * Each save produces an immutable snapshot of the content together with the
 * user who made the change. Snapshots are never modified or deleted, so the
 * full edit history is preserved.
 *
 * @package Neuron\Cms\Services\Revision
 */
class RevisionService implements IRevisionService
{
	private IRevisionRepository $_repository;

	public function __construct( IRevisionRepository $repository )
	{
		$this->_repository = $repository;
	}

	/**
	 * Record a revision for a page.
	 */
	public function recordPage( Page $page, string $action ): ?Revision
	{
		if( !$page->getId() )
		{
			return null;
		}

		return $this->record(
			Revision::TYPE_PAGE,
			$page->getId(),
			$page->getTitle(),
			$page->getStatus(),
			$page->toArray(),
			$action
		);
	}

	/**
	 * Record a revision for a post.
	 */
	public function recordPost( Post $post, string $action ): ?Revision
	{
		if( !$post->getId() )
		{
			return null;
		}

		return $this->record(
			Revision::TYPE_POST,
			$post->getId(),
			$post->getTitle(),
			$post->getStatus(),
			$post->toArray(),
			$action
		);
	}

	/**
	 * List revisions for a page, newest first.
	 *
	 * @return Revision[]
	 */
	public function listForPage( int $pageId ): array
	{
		return $this->_repository->getForContent( Revision::TYPE_PAGE, $pageId );
	}

	/**
	 * List revisions for a post, newest first.
	 *
	 * @return Revision[]
	 */
	public function listForPost( int $postId ): array
	{
		return $this->_repository->getForContent( Revision::TYPE_POST, $postId );
	}

	/**
	 * Find a single revision by ID.
	 */
	public function find( int $id ): ?Revision
	{
		return $this->_repository->findById( $id );
	}

	/**
	 * Build and persist a revision snapshot.
	 *
	 * @param string $contentType
	 * @param int $contentId
	 * @param string $title
	 * @param string $status
	 * @param array $snapshot
	 * @param string $action
	 * @return Revision
	 */
	private function record(
		string $contentType,
		int $contentId,
		string $title,
		string $status,
		array $snapshot,
		string $action
	): Revision
	{
		$revision = new Revision();
		$revision->setContentType( $contentType );
		$revision->setContentId( $contentId );
		$revision->setTitle( $title );
		$revision->setStatus( $status );
		$revision->setAction( $action );
		$revision->setSnapshotArray( $snapshot );
		$revision->setEditedBy( $this->currentUserId() );
		$revision->setEditedByName( $this->currentUserName() );
		$revision->setCreatedAt( new \DateTimeImmutable() );

		return $this->_repository->create( $revision );
	}

	/**
	 * Resolve the current authenticated user id, if available.
	 */
	private function currentUserId(): ?int
	{
		if( function_exists( 'user_id' ) )
		{
			return user_id();
		}

		return null;
	}

	/**
	 * Resolve a display name for the current user, if available.
	 */
	private function currentUserName(): ?string
	{
		if( function_exists( 'user' ) )
		{
			$user = user();

			if( $user !== null && method_exists( $user, 'getUsername' ) )
			{
				return $user->getUsername();
			}
		}

		return null;
	}
}
