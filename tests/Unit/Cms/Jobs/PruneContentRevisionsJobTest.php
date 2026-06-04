<?php

namespace Tests\Unit\Cms\Jobs;

use Neuron\Cms\Jobs\PruneContentRevisionsJob;
use Neuron\Core\Registry\RegistryKeys;
use Neuron\Data\Settings\SettingManager;
use Neuron\Data\Settings\Source\Memory;
use Neuron\Patterns\Registry;
use PDO;
use PHPUnit\Framework\TestCase;

/**
 * @runTestsInSeparateProcesses
 * @preserveGlobalState disabled
 */
class PruneContentRevisionsJobTest extends TestCase
{
	private string $dbPath;
	private PDO $pdo;

	protected function setUp(): void
	{
		$this->dbPath = sys_get_temp_dir() . '/neuron_jobs_rev_' . uniqid() . '.db';

		$settings = new SettingManager( new Memory() );
		$settings->set( 'database', 'adapter', 'sqlite' );
		$settings->set( 'database', 'name', $this->dbPath );

		Registry::getInstance()->set( RegistryKeys::SETTINGS, $settings );

		$this->pdo = new PDO( "sqlite:{$this->dbPath}" );
		$this->pdo->setAttribute( PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION );

		$this->pdo->exec(
			"CREATE TABLE content_revisions (
				id INTEGER PRIMARY KEY AUTOINCREMENT,
				content_type VARCHAR(20) NOT NULL,
				content_id INTEGER NOT NULL,
				title VARCHAR(255) NOT NULL DEFAULT '',
				status VARCHAR(20) DEFAULT 'draft',
				action VARCHAR(20) DEFAULT 'updated',
				snapshot TEXT NOT NULL DEFAULT '{}',
				edited_by INTEGER,
				edited_by_name VARCHAR(150),
				created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
			)"
		);
	}

	protected function tearDown(): void
	{
		Registry::getInstance()->set( RegistryKeys::SETTINGS, null );

		unset( $this->pdo );

		if( file_exists( $this->dbPath ) )
		{
			unlink( $this->dbPath );
		}
	}

	private function insertRevision( string $type, int $contentId, string $createdAt ): void
	{
		$stmt = $this->pdo->prepare(
			"INSERT INTO content_revisions (content_type, content_id, title, snapshot, created_at)
			 VALUES (?, ?, 'T', '{}', ?)"
		);
		$stmt->execute( [ $type, $contentId, $createdAt ] );
	}

	private function revisionCount(): int
	{
		return (int)$this->pdo->query( "SELECT COUNT(*) FROM content_revisions" )->fetchColumn();
	}

	public function testGetName(): void
	{
		$this->assertEquals( 'prune_content_revisions', ( new PruneContentRevisionsJob() )->getName() );
	}

	public function testDeletesByAge(): void
	{
		$old    = ( new \DateTimeImmutable( '-400 days' ) )->format( 'Y-m-d H:i:s' );
		$recent = ( new \DateTimeImmutable( '-10 days' ) )->format( 'Y-m-d H:i:s' );

		$this->insertRevision( 'post', 1, $old );
		$this->insertRevision( 'post', 1, $recent );

		// keep_per_content high so only age applies
		$result = ( new PruneContentRevisionsJob() )->run( [ 'keep_per_content' => 1000, 'max_age_days' => 365 ] );

		$this->assertTrue( $result );
		$this->assertEquals( 1, $this->revisionCount() );
	}

	public function testKeepsNewestPerContent(): void
	{
		// 5 revisions for post/1, all recent
		for( $i = 0; $i < 5; $i++ )
		{
			$ts = ( new \DateTimeImmutable( "-$i hours" ) )->format( 'Y-m-d H:i:s' );
			$this->insertRevision( 'post', 1, $ts );
		}
		// 2 revisions for page/2
		$this->insertRevision( 'page', 2, ( new \DateTimeImmutable( '-1 hours' ) )->format( 'Y-m-d H:i:s' ) );
		$this->insertRevision( 'page', 2, ( new \DateTimeImmutable( '-2 hours' ) )->format( 'Y-m-d H:i:s' ) );

		// keep 2 per content, disable age pruning
		$result = ( new PruneContentRevisionsJob() )->run( [ 'keep_per_content' => 2, 'max_age_days' => 0 ] );

		$this->assertTrue( $result );

		$postCount = (int)$this->pdo->query( "SELECT COUNT(*) FROM content_revisions WHERE content_type='post' AND content_id=1" )->fetchColumn();
		$pageCount = (int)$this->pdo->query( "SELECT COUNT(*) FROM content_revisions WHERE content_type='page' AND content_id=2" )->fetchColumn();

		$this->assertEquals( 2, $postCount );
		$this->assertEquals( 2, $pageCount );
	}
}
