<?php

namespace Neuron\Cms\Jobs;

use Neuron\Cms\Repositories\DatabaseEmailVerificationTokenRepository;
use Neuron\Cms\Repositories\DatabasePasswordResetTokenRepository;
use Neuron\Cms\Repositories\DatabaseRememberTokenRepository;
use Neuron\Log\Log;
use Throwable;

/**
 * Deletes expired email verification, password reset and remember me tokens.
 *
 * The token tables retain rows until they are explicitly removed; tokens are
 * only deleted on use, so expired but unused tokens accumulate. This job purges
 * any token whose expires_at is in the past.
 *
 * @package Neuron\Cms\Jobs
 */
class CleanupExpiredTokensJob extends MaintenanceJob
{
	/**
	 * @inheritDoc
	 */
	public function getName(): string
	{
		return 'cleanup_expired_tokens';
	}

	/**
	 * Purge expired auth tokens.
	 *
	 * @param array $argv
	 * @return bool True on success.
	 */
	public function run( array $argv = [] ): mixed
	{
		$settings = $this->getSettings();

		if( $settings === null )
		{
			Log::warning( "{$this->getName()}: settings unavailable; skipping." );
			return false;
		}

		$total = 0;

		try
		{
			$emailTokens = new DatabaseEmailVerificationTokenRepository( $settings );
			$emailDeleted = $emailTokens->deleteExpired();
			$total += $emailDeleted;

			$resetTokens = new DatabasePasswordResetTokenRepository( $settings );
			$resetDeleted = $resetTokens->deleteExpired();
			$total += $resetDeleted;

			$rememberTokens = new DatabaseRememberTokenRepository( $settings );
			$rememberDeleted = $rememberTokens->deleteExpired();
			$total += $rememberDeleted;

			Log::info(
				"{$this->getName()}: removed $emailDeleted expired email verification token(s), " .
				"$resetDeleted expired password reset token(s) " .
				"and $rememberDeleted expired remember me token(s)."
			);
		}
		catch( Throwable $exception )
		{
			Log::error( "{$this->getName()}: failed - {$exception->getMessage()}" );
			return false;
		}

		return true;
	}
}
