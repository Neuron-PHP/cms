<?php

namespace Neuron\Cms\Cli\Commands\Payments;

use Neuron\Cli\Commands\Command;
use Neuron\Cms\Repositories\DatabasePaymentRepository;
use Neuron\Cms\Services\Payment\PaymentGatewayFactory;
use Neuron\Cms\Services\Payment\PaymentReconciler;
use Neuron\Cms\Services\Payment\PaymentService;
use Neuron\Core\Registry\RegistryKeys;
use Neuron\Data\Settings\SettingManager;
use Neuron\Patterns\Registry;

/**
 * Look up pending payments against the gateway and complete those that paid.
 */
class ReconcileCommand extends Command
{
	/**
	 * @inheritDoc
	 */
	public function getName(): string
	{
		return 'cms:payments:reconcile';
	}

	/**
	 * @inheritDoc
	 */
	public function getDescription(): string
	{
		return 'Mark pending payments completed when Stripe reports them paid';
	}

	/**
	 * @inheritDoc
	 */
	public function execute(): int
	{
		$settings = Registry::getInstance()->get( RegistryKeys::SETTINGS );

		if( !$settings instanceof SettingManager )
		{
			$this->output->error( 'Application not initialized: Settings not found in Registry' );

			return 1;
		}

		$repository = new DatabasePaymentRepository( $settings );
		$reconciler = $this->createReconciler( $settings, $repository );
		$pending    = $repository->paginate( 1, 500, 'pending' );
		$completed  = 0;
		$canceled   = 0;
		$skipped    = 0;
		$errors     = 0;

		$this->output->title( 'Reconcile Pending Payments' );
		$this->output->info( 'Pending: ' . $pending['total'] );

		foreach( $pending['items'] as $payment )
		{
			$result = $reconciler->sync( $payment );
			$id     = (int) ( $payment['id'] ?? 0 );

			match( $result )
			{
				PaymentReconciler::COMPLETED => $this->countCompleted( $id, $completed ),
				PaymentReconciler::CANCELED  => $this->countCanceled( $id, $canceled ),
				PaymentReconciler::ERROR, PaymentReconciler::UNAVAILABLE => $this->countError( $id, $result, $errors ),
				default => ++$skipped
			};
		}

		$this->output->success(
			"Done. completed={$completed} canceled={$canceled} unchanged={$skipped} errors={$errors}"
		);

		return $errors > 0 ? 1 : 0;
	}

	/**
	 * @param SettingManager $settings
	 * @param DatabasePaymentRepository $repository
	 * @return PaymentReconciler
	 */
	protected function createReconciler(
		SettingManager $settings,
		DatabasePaymentRepository $repository
	): PaymentReconciler
	{
		return new PaymentReconciler(
			$repository,
			new PaymentGatewayFactory( $settings ),
			new PaymentService( $settings ),
			$settings
		);
	}

	/**
	 * @param int $id
	 * @param int $completed
	 * @return void
	 */
	private function countCompleted( int $id, int &$completed ): void
	{
		++$completed;
		$this->output->info( "Payment #{$id}: completed" );
	}

	/**
	 * @param int $id
	 * @param int $canceled
	 * @return void
	 */
	private function countCanceled( int $id, int &$canceled ): void
	{
		++$canceled;
		$this->output->info( "Payment #{$id}: canceled (expired checkout)" );
	}

	/**
	 * @param int $id
	 * @param string $result
	 * @param int $errors
	 * @return void
	 */
	private function countError( int $id, string $result, int &$errors ): void
	{
		++$errors;
		$this->output->error( "Payment #{$id}: {$result}" );
	}
}
