<?php

namespace Neuron\Cms\Services\Revision;

use Neuron\Cms\Models\Page;
use Neuron\Cms\Models\Post;
use Neuron\Cms\Models\Revision;

/**
 * Contract for recording and retrieving content revisions.
 *
 * @package Neuron\Cms\Services\Revision
 */
interface IRevisionService
{
	/**
	 * Record a revision for a page.
	 *
	 * @param Page $page
	 * @param string $action One of Revision::ACTION_*
	 * @return Revision|null Null when the page has no id yet
	 */
	public function recordPage( Page $page, string $action ): ?Revision;

	/**
	 * Record a revision for a post.
	 *
	 * @param Post $post
	 * @param string $action One of Revision::ACTION_*
	 * @return Revision|null Null when the post has no id yet
	 */
	public function recordPost( Post $post, string $action ): ?Revision;

	/**
	 * List revisions for a page, newest first.
	 *
	 * @param int $pageId
	 * @return Revision[]
	 */
	public function listForPage( int $pageId ): array;

	/**
	 * List revisions for a post, newest first.
	 *
	 * @param int $postId
	 * @return Revision[]
	 */
	public function listForPost( int $postId ): array;

	/**
	 * Find a single revision by ID.
	 *
	 * @param int $id
	 * @return Revision|null
	 */
	public function find( int $id ): ?Revision;
}
