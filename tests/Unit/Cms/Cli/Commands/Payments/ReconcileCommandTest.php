<?php

namespace Tests\Unit\Cms\Cli\Commands\Payments;

use Neuron\Cms\Cli\Commands\Payments\ReconcileCommand;
use PHPUnit\Framework\TestCase;

class ReconcileCommandTest extends TestCase
{
	public function testGetName(): void
	{
		$this->assertSame( 'cms:payments:reconcile', ( new ReconcileCommand() )->getName() );
	}
}
