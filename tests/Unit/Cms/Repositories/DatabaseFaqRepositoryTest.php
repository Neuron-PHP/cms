<?php

namespace Tests\Unit\Cms\Repositories;

use Neuron\Cms\Models\Faq;
use Neuron\Cms\Models\FaqItem;
use Neuron\Cms\Repositories\DatabaseFaqRepository;
use Neuron\Data\Settings\SettingManager;
use PHPUnit\Framework\TestCase;
use PDO;

/**
 * @runTestsInSeparateProcesses
 * @preserveGlobalState disabled
 */
class DatabaseFaqRepositoryTest extends TestCase
{
	private PDO $pdo;
	private DatabaseFaqRepository $repository;

	protected function setUp(): void
	{
		$this->pdo = new PDO( 'sqlite::memory:' );
		$this->pdo->setAttribute( PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION );

		$this->pdo->exec( "
			CREATE TABLE faqs (
				id INTEGER PRIMARY KEY AUTOINCREMENT,
				name VARCHAR(255) NOT NULL,
				slug VARCHAR(255) UNIQUE NOT NULL,
				description TEXT,
				created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
				updated_at TIMESTAMP
			)
		" );

		$this->pdo->exec( "
			CREATE TABLE faq_items (
				id INTEGER PRIMARY KEY AUTOINCREMENT,
				faq_id INTEGER NOT NULL,
				question VARCHAR(512) NOT NULL,
				answer TEXT NOT NULL,
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

		$this->repository = new DatabaseFaqRepository( $settings );
		$reflection = new \ReflectionClass( $this->repository );
		$property = $reflection->getProperty( '_pdo' );
		$property->setValue( $this->repository, $this->pdo );
	}

	private function makeFaq( string $name = 'General', string $slug = 'general' ): Faq
	{
		$faq = new Faq();
		$faq->setName( $name );
		$faq->setSlug( $slug );
		$faq->setDescription( 'Common questions' );

		return $this->repository->create( $faq );
	}

	/**
	 * @param array<string, mixed> $overrides
	 */
	private function makeItem( int $faqId, array $overrides = [] ): FaqItem
	{
		$item = new FaqItem();
		$item->setFaqId( $faqId );
		$item->setQuestion( $overrides['question'] ?? 'How does Teen Court work?' );
		$item->setAnswer( $overrides['answer'] ?? 'Teens hear cases referred by the court.' );
		$item->setSortOrder( $overrides['sort_order'] ?? 0 );

		return $this->repository->createItem( $item );
	}

	public function testAllReturnsEmptyArrayWhenNoneExist(): void
	{
		$this->assertSame( [], $this->repository->all() );
	}

	public function testCreateAndFindById(): void
	{
		$faq = $this->makeFaq();

		$found = $this->repository->findById( $faq->getId() );

		$this->assertNotNull( $found );
		$this->assertSame( 'General', $found->getName() );
		$this->assertSame( 'general', $found->getSlug() );
		$this->assertSame( 'Common questions', $found->getDescription() );
	}

	public function testFindBySlug(): void
	{
		$this->makeFaq( 'Intake', 'intake' );

		$found = $this->repository->findBySlug( 'intake' );

		$this->assertNotNull( $found );
		$this->assertSame( 'Intake', $found->getName() );
		$this->assertNull( $this->repository->findBySlug( 'missing' ) );
	}

	public function testAllReturnsGroupsSortedByName(): void
	{
		$this->makeFaq( 'Intake', 'intake' );
		$this->makeFaq( 'General', 'general' );

		$faqs = $this->repository->all();

		$this->assertCount( 2, $faqs );
		$this->assertSame( 'General', $faqs[0]->getName() );
		$this->assertSame( 'Intake', $faqs[1]->getName() );
	}

	public function testUpdateChangesFields(): void
	{
		$faq = $this->makeFaq();
		$faq->setName( 'Common' );
		$faq->setDescription( null );

		$this->repository->update( $faq );

		$found = $this->repository->findById( $faq->getId() );
		$this->assertSame( 'Common', $found->getName() );
		$this->assertNull( $found->getDescription() );
	}

	public function testSlugExists(): void
	{
		$faq = $this->makeFaq();

		$this->assertTrue( $this->repository->slugExists( 'general' ) );
		$this->assertFalse( $this->repository->slugExists( 'general', $faq->getId() ) );
		$this->assertFalse( $this->repository->slugExists( 'missing' ) );
	}

	public function testDeleteRemovesGroupAndItems(): void
	{
		$faq = $this->makeFaq();
		$this->makeItem( $faq->getId() );

		$this->assertTrue( $this->repository->delete( $faq ) );
		$this->assertNull( $this->repository->findById( $faq->getId() ) );
		$this->assertSame( 0, $this->repository->countItems( $faq->getId() ) );
	}

	public function testCreateAndFindItem(): void
	{
		$faq = $this->makeFaq();
		$item = $this->makeItem( $faq->getId(), [
			'question' => 'What should I wear?',
			'answer' => 'Business casual.',
			'sort_order' => 3
		] );

		$found = $this->repository->findItemById( $item->getId() );

		$this->assertNotNull( $found );
		$this->assertSame( 'What should I wear?', $found->getQuestion() );
		$this->assertSame( 'Business casual.', $found->getAnswer() );
		$this->assertSame( 3, $found->getSortOrder() );
	}

	public function testGetItemsOrdersBySortThenId(): void
	{
		$faq = $this->makeFaq();
		$this->makeItem( $faq->getId(), [ 'question' => 'Third', 'sort_order' => 2 ] );
		$first = $this->makeItem( $faq->getId(), [ 'question' => 'First', 'sort_order' => 1 ] );
		$second = $this->makeItem( $faq->getId(), [ 'question' => 'Second', 'sort_order' => 1 ] );

		$items = $this->repository->getItems( $faq->getId() );

		$this->assertCount( 3, $items );
		$this->assertSame( 'First', $items[0]->getQuestion() );
		$this->assertSame( 'Second', $items[1]->getQuestion() );
		$this->assertSame( 'Third', $items[2]->getQuestion() );
		$this->assertSame( $first->getId(), $items[0]->getId() );
		$this->assertSame( $second->getId(), $items[1]->getId() );
	}

	public function testUpdateItemChangesFields(): void
	{
		$faq = $this->makeFaq();
		$item = $this->makeItem( $faq->getId() );
		$item->setQuestion( 'Updated question' );
		$item->setAnswer( 'Updated answer' );

		$this->repository->updateItem( $item );

		$found = $this->repository->findItemById( $item->getId() );
		$this->assertSame( 'Updated question', $found->getQuestion() );
		$this->assertSame( 'Updated answer', $found->getAnswer() );
	}

	public function testDeleteItemRemovesRow(): void
	{
		$faq = $this->makeFaq();
		$item = $this->makeItem( $faq->getId() );

		$this->assertTrue( $this->repository->deleteItem( $item ) );
		$this->assertNull( $this->repository->findItemById( $item->getId() ) );
	}

	public function testCountItemsAndNextSortOrder(): void
	{
		$faq = $this->makeFaq();

		$this->assertSame( 0, $this->repository->countItems( $faq->getId() ) );
		$this->assertSame( 0, $this->repository->nextItemSortOrder( $faq->getId() ) );

		$this->makeItem( $faq->getId(), [ 'sort_order' => 0 ] );
		$this->makeItem( $faq->getId(), [ 'sort_order' => 4 ] );

		$this->assertSame( 2, $this->repository->countItems( $faq->getId() ) );
		$this->assertSame( 5, $this->repository->nextItemSortOrder( $faq->getId() ) );
	}

	public function testFindItemByIdReturnsNullWhenMissing(): void
	{
		$this->assertNull( $this->repository->findItemById( 999 ) );
	}
}
