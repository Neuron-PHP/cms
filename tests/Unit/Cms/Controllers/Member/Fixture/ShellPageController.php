<?php

namespace Tests\Cms\Controllers\Member\Fixture;

use Neuron\Cms\Controllers\Member\MemberController;
use Neuron\Mvc\Responses\HttpResponseStatus;

/**
 * Minimal application page used to exercise MemberController.
 * Views resolve from member/fixture/shell_page/.
 */
class ShellPageController extends MemberController
{
	public function show( array $data, ?string $title = null, HttpResponseStatus $status = HttpResponseStatus::OK ): string
	{
		return $this->renderPage( 'show', $data, $title, null, $status );
	}

	public function showView( string $view, array $data = [] ): string
	{
		return $this->renderView( $view, $data, null, null, HttpResponseStatus::NOT_FOUND );
	}

	public function memberHome(): ?string
	{
		return $this->configuredMemberHome();
	}

	public function userId(): ?int
	{
		return $this->currentUserId();
	}
}
