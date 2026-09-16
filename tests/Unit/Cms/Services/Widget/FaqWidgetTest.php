<?php

namespace Tests\Unit\Cms\Services\Widget;

use Neuron\Cms\Models\Faq;
use Neuron\Cms\Models\FaqItem;
use Neuron\Cms\Repositories\IFaqRepository;
use Neuron\Cms\Services\Widget\FaqWidget;
use PHPUnit\Framework\TestCase;

class FaqWidgetTest extends TestCase
{
	private $repository;
	private FaqWidget $widget;

	protected function setUp(): void
	{
		$this->repository = $this->createMock( IFaqRepository::class );
		$this->widget = new FaqWidget( $this->repository );
	}

	public function testGetNameReturnsFaq(): void
	{
		$this->assertSame( 'faq', $this->widget->getName() );
	}

	public function testGetAttributesIncludesSlugTitleAndOpen(): void
	{
		$attrs = $this->widget->getAttributes();

		$this->assertArrayHasKey( 'slug', $attrs );
		$this->assertArrayHasKey( 'title', $attrs );
		$this->assertArrayHasKey( 'open', $attrs );
	}

	public function testRenderWithoutSlugReturnsComment(): void
	{
		$this->repository->expects( $this->never() )->method( 'findBySlug' );

		$result = $this->widget->render( [] );

		$this->assertStringContainsString( '<!-- FAQ widget: slug is required -->', $result );
	}

	public function testRenderWithUnknownSlugReturnsComment(): void
	{
		$this->repository->method( 'findBySlug' )->with( 'missing' )->willReturn( null );

		$result = $this->widget->render( [ 'slug' => 'missing' ] );

		$this->assertStringContainsString( '<!-- FAQ widget: group not found -->', $result );
	}

	public function testRenderWithEmptyGroupShowsEmptyState(): void
	{
		$faq = $this->faq( 1, 'General', 'general' );

		$this->repository->method( 'findBySlug' )->with( 'general' )->willReturn( $faq );
		$this->repository->method( 'getItems' )->with( 1 )->willReturn( [] );

		$result = $this->widget->render( [ 'slug' => 'general' ] );

		$this->assertStringContainsString( 'cms-faq', $result );
		$this->assertStringContainsString( 'No questions are listed yet.', $result );
	}

	public function testRenderAccordionIncludesQuestionsAndAnswers(): void
	{
		$faq = $this->faq( 2, 'General', 'general' );
		$item = $this->item( 10, 'How does it work?', "Teens hear cases.\nParents attend." );

		$this->repository->method( 'findBySlug' )->willReturn( $faq );
		$this->repository->method( 'getItems' )->willReturn( [ $item ] );

		$result = $this->widget->render( [ 'slug' => 'general', 'title' => 'Common questions' ] );

		$this->assertStringContainsString( 'Common questions', $result );
		$this->assertStringContainsString( 'accordion', $result );
		$this->assertStringContainsString( 'How does it work?', $result );
		$this->assertStringContainsString( 'Teens hear cases.', $result );
		$this->assertStringContainsString( '<br />', $result );
		$this->assertStringContainsString( 'collapsed', $result );
	}

	public function testOpenFirstExpandsFirstItem(): void
	{
		$faq = $this->faq( 3, 'Intake', 'intake' );
		$first = $this->item( 1, 'First?', 'First answer' );
		$second = $this->item( 2, 'Second?', 'Second answer' );

		$this->repository->method( 'findBySlug' )->willReturn( $faq );
		$this->repository->method( 'getItems' )->willReturn( [ $first, $second ] );

		$result = $this->widget->render( [ 'slug' => 'intake', 'open' => 'first' ] );

		$this->assertStringContainsString( 'aria-expanded="true"', $result );
		$this->assertStringContainsString( 'collapse show', $result );
		$this->assertStringContainsString( 'data-bs-parent="#cms-faq-intake"', $result );
	}

	public function testOpenAllOmitsParentSoMultipleCanStayOpen(): void
	{
		$faq = $this->faq( 4, 'All', 'all' );
		$first = $this->item( 1, 'One?', 'One' );
		$second = $this->item( 2, 'Two?', 'Two' );

		$this->repository->method( 'findBySlug' )->willReturn( $faq );
		$this->repository->method( 'getItems' )->willReturn( [ $first, $second ] );

		$result = $this->widget->render( [ 'slug' => 'all', 'open' => 'all' ] );

		$this->assertStringNotContainsString( 'data-bs-parent=', $result );
		$this->assertSame( 2, substr_count( $result, 'collapse show' ) );
	}

	private function faq( int $id, string $name, string $slug ): Faq
	{
		$faq = new Faq();
		$faq->setId( $id );
		$faq->setName( $name );
		$faq->setSlug( $slug );

		return $faq;
	}

	private function item( int $id, string $question, string $answer ): FaqItem
	{
		$item = new FaqItem();
		$item->setId( $id );
		$item->setQuestion( $question );
		$item->setAnswer( $answer );

		return $item;
	}
}
