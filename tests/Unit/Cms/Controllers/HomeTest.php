<?php

namespace Tests\Cms\Controllers;

use Neuron\Cms\Auth\SessionManager;
use Neuron\Cms\Controllers\Home;
use Neuron\Cms\Enums\ContentStatus;
use Neuron\Cms\Models\Page;
use Neuron\Cms\Models\Post;
use Neuron\Cms\Repositories\ICategoryRepository;
use Neuron\Cms\Repositories\IPageRepository;
use Neuron\Cms\Repositories\IPostRepository;
use Neuron\Cms\Repositories\ITagRepository;
use Neuron\Cms\Services\Content\EditorJsRenderer;
use Neuron\Core\Registry\RegistryKeys;
use Neuron\Data\Settings\SettingManager;
use Neuron\Mvc\IMvcApplication;
use Neuron\Mvc\Requests\Request;
use Neuron\Mvc\Responses\HttpResponseStatus;
use Neuron\Patterns\Registry;
use Neuron\Routing\Router;
use PHPUnit\Framework\TestCase;

class HomeTest extends TestCase
{
	private array $settingsMap = [];

	protected function setUp(): void
	{
		parent::setUp();

		$this->settingsMap = [
			'site' => [
				'name' => 'Test Site',
				'title' => 'Test Title',
				'description' => 'Test Description',
				'url' => 'https://example.test',
			],
			'homepage' => [
				'mode' => 'blog',
				'page' => 'welcome',
			],
			'blog' => [
				'posts_per_page' => 2,
			],
			'member' => [
				'registration_enabled' => true,
			],
		];

		Registry::getInstance()->set( RegistryKeys::SETTINGS, $this->settings() );
	}

	public function testBlogModePaginatesPublishedPosts(): void
	{
		$first = new Post();
		$first->setTitle( 'First' );
		$second = new Post();
		$second->setTitle( 'Second' );

		$posts = $this->createMock( IPostRepository::class );
		$posts->expects( $this->once() )
			->method( 'count' )
			->with( ContentStatus::PUBLISHED->value )
			->willReturn( 3 );
		$posts->expects( $this->once() )
			->method( 'getPublished' )
			->with( 2, 2 )
			->willReturn( [ $second ] );

		$_GET['page'] = 2;
		$home = $this->home( [ IPostRepository::class => $posts ] );
		$result = $home->index( new Request() );
		unset( $_GET['page'] );

		$this->assertSame( 'blog', $home->lastView );
		$this->assertSame( [ $second ], $home->lastData['Posts'] );
		$this->assertSame( 2, $home->lastData['page'] );
		$this->assertSame( 2, $home->lastData['pages'] );
		$this->assertSame( 3, $home->lastData['total'] );
		$this->assertSame( '/', $home->lastData['PaginationBase'] );
		$this->assertStringContainsString( 'Test Site', $home->lastData['Title'] );
		$this->assertSame( $result, 'blog' );
	}

	public function testPageModeRendersPublishedPage(): void
	{
		$this->settingsMap['homepage']['mode'] = 'page';

		$page = new Page();
		$page->setTitle( 'Welcome' );
		$page->setSlug( 'welcome' );
		$page->setStatus( ContentStatus::PUBLISHED->value );
		$page->setMetaTitle( 'Welcome meta' );
		$page->setMetaDescription( 'Welcome description' );
		$page->setMetaKeywords( 'home, welcome' );
		$page->setContent( '{"blocks":[]}' );

		$pages = $this->createMock( IPageRepository::class );
		$pages->method( 'findBySlug' )->with( 'welcome' )->willReturn( $page );

		$renderer = $this->createMock( EditorJsRenderer::class );
		$renderer->method( 'render' )->willReturn( '<p>Hello</p>' );

		$home = $this->home( [
			IPageRepository::class => $pages,
			EditorJsRenderer::class => $renderer,
		] );

		$home->index( new Request() );

		$this->assertSame( 'page', $home->lastView );
		$this->assertSame( $page, $home->lastData['Page'] );
		$this->assertSame( '<p>Hello</p>', $home->lastData['ContentHtml'] );
		$this->assertSame( 'Welcome meta | Test Site', $home->lastData['Title'] );
		$this->assertSame( 'Welcome description', $home->lastData['Description'] );
		$this->assertSame( 'home, welcome', $home->lastData['MetaKeywords'] );
	}

	public function testPageModeFallsBackToLandingWhenPageMissing(): void
	{
		$this->settingsMap['homepage']['mode'] = 'page';

		$pages = $this->createMock( IPageRepository::class );
		$pages->method( 'findBySlug' )->willReturn( null );

		$home = $this->home( [ IPageRepository::class => $pages ] );
		$home->index( new Request() );

		$this->assertSame( 'index', $home->lastView );
		$this->assertTrue( $home->lastData['RegistrationEnabled'] );
	}

	public function testLandingModeRendersMarketingView(): void
	{
		$this->settingsMap['homepage']['mode'] = 'landing';

		$home = $this->home();
		$home->index( new Request() );

		$this->assertSame( 'index', $home->lastView );
		$this->assertSame( 'website', $home->lastData['OgType'] );
		$this->assertTrue( $home->lastData['RegistrationEnabled'] );
	}

	/**
	 * @param array<class-string, object> $overrides
	 */
	private function home( array $overrides = [] ): TestableHome
	{
		$app = $this->createMock( IMvcApplication::class );
		$app->method( 'getRouter' )->willReturn( $this->createMock( Router::class ) );

		return new TestableHome(
			$app,
			$this->settings(),
			$this->createMock( SessionManager::class ),
			$overrides[ IPostRepository::class ] ?? $this->createMock( IPostRepository::class ),
			$overrides[ IPageRepository::class ] ?? $this->createMock( IPageRepository::class ),
			$overrides[ ICategoryRepository::class ] ?? $this->createMock( ICategoryRepository::class ),
			$overrides[ ITagRepository::class ] ?? $this->createMock( ITagRepository::class ),
			$overrides[ EditorJsRenderer::class ] ?? $this->createMock( EditorJsRenderer::class )
		);
	}

	private function settings(): SettingManager
	{
		$settings = $this->createMock( SettingManager::class );
		$map = $this->settingsMap;
		$settings->method( 'get' )->willReturnCallback(
			function( $section, $key ) use ( $map ) {
				return $map[ $section ][ $key ] ?? null;
			}
		);

		return $settings;
	}
}

class TestableHome extends Home
{
	/** @var array<string, mixed> */
	public array $lastData = [];
	public string $lastView = '';

	public function renderHtml( HttpResponseStatus $responseCode, array $data = [], string $page = 'index', string $layout = 'default', ?bool $cacheEnabled = null ): string
	{
		$this->lastData = $data;
		$this->lastView = $page;

		return $page;
	}
}
