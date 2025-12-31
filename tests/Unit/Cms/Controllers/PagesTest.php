<?php

namespace Tests\Cms\Controllers;

use Neuron\Cms\Controllers\Pages;
use Neuron\Cms\Models\Page;
use Neuron\Cms\Repositories\IPageRepository;
use Neuron\Cms\Services\Content\EditorJsRenderer;
use Neuron\Core\Exceptions\NotFound;
use Neuron\Data\Settings\Source\Memory;
use Neuron\Data\Settings\SettingManager;
use Neuron\Mvc\Application;
use Neuron\Mvc\Requests\Request;
use Neuron\Patterns\Registry;
use PHPUnit\Framework\TestCase;

class PagesTest extends TestCase
{
	private SettingManager $_settingManager;
	private string $_versionFilePath;

	protected function setUp(): void
	{
		parent::setUp();

		// Create version file in temp directory
		$this->_versionFilePath = sys_get_temp_dir() . '/neuron-test-version-' . uniqid() . '.json';
		$versionContent = json_encode([
			'major' => 1,
			'minor' => 0,
			'patch' => 0
		]);
		file_put_contents( $this->_versionFilePath, $versionContent );

		// Create mock settings
		$settings = new Memory();
		$settings->set( 'site', 'name', 'Test Site' );
		$settings->set( 'site', 'title', 'Test Title' );
		$settings->set( 'site', 'description', 'Test Description' );
		$settings->set( 'site', 'url', 'http://test.com' );
		$settings->set( 'paths', 'version_file', $this->_versionFilePath );

		// Wrap in SettingManager
		$this->_settingManager = new SettingManager( $settings );

		// Store settings in registry
		Registry::getInstance()->set( 'Settings', $this->_settingManager );
	}

	protected function tearDown(): void
	{
		// Clear registry
		Registry::getInstance()->set( 'Settings', null );
		Registry::getInstance()->set( 'version', null );
		Registry::getInstance()->set( 'name', null );
		Registry::getInstance()->set( 'rss_url', null );
		Registry::getInstance()->set( 'DtoFactoryService', null );

		// Clean up temp version file
		if( isset( $this->_versionFilePath ) && file_exists( $this->_versionFilePath ) )
		{
			unlink( $this->_versionFilePath );
		}

		parent::tearDown();
	}

	public function testConstructorWithDependencies(): void
	{
		$mockPageRepository = $this->createMock( IPageRepository::class );
		$mockRenderer = $this->createMock( EditorJsRenderer::class );
		$mockSettingManager = Registry::getInstance()->get( 'Settings' );
		$mockSessionManager = $this->createMock( \Neuron\Cms\Auth\SessionManager::class );

		$controller = new Pages( null, $mockPageRepository, $mockRenderer, $mockSettingManager, $mockSessionManager );

		$this->assertInstanceOf( Pages::class, $controller );
	}

	public function testShowRendersPublishedPage(): void
	{
		$mockPage = $this->createMock( Page::class );
		$mockPage->method( 'getId' )->willReturn( 1 );
		$mockPage->method( 'getTitle' )->willReturn( 'Test Page' );
		$mockPage->method( 'isPublished' )->willReturn( true );
		$mockPage->method( 'getContent' )->willReturn( [ 'blocks' => [] ] );
		$mockPage->method( 'getMetaTitle' )->willReturn( 'Test Meta Title' );
		$mockPage->method( 'getMetaDescription' )->willReturn( 'Test meta description' );
		$mockPage->method( 'getMetaKeywords' )->willReturn( 'test, keywords' );

		$mockPageRepository = $this->createMock( IPageRepository::class );
		$mockPageRepository->method( 'findBySlug' )->with( 'test-page' )->willReturn( $mockPage );
		$mockPageRepository->expects( $this->once() )
			->method( 'incrementViewCount' )
			->with( 1 );

		$mockRenderer = $this->createMock( EditorJsRenderer::class );
		$mockRenderer->method( 'render' )->willReturn( '<p>Rendered content</p>' );

		$mockSettingManager = Registry::getInstance()->get( 'Settings' );
		$mockSessionManager = $this->createMock( \Neuron\Cms\Auth\SessionManager::class );

		$controller = $this->getMockBuilder( Pages::class )
			->setConstructorArgs( [ null, $mockPageRepository, $mockRenderer, $mockSettingManager, $mockSessionManager ] )
			->onlyMethods( [ 'renderHtml' ] )
			->getMock();

		$controller->expects( $this->once() )
			->method( 'renderHtml' )
			->with(
				$this->anything(),
				$this->callback( function( $data ) use ( $mockPage ) {
					return $data['Page'] === $mockPage &&
					       $data['ContentHtml'] === '<p>Rendered content</p>' &&
					       isset( $data['Title'] ) &&
					       isset( $data['Description'] ) &&
					       isset( $data['MetaKeywords'] );
				} ),
				'show'
			)
			->willReturn( '<html>Page content</html>' );

		$request = new Request();
		$request->setRouteParameters( [ 'slug' => 'test-page' ] );
		$result = $controller->show( $request );

		$this->assertEquals( '<html>Page content</html>', $result );
	}

	public function testShowThrowsNotFoundForNonexistentPage(): void
	{
		$mockPageRepository = $this->createMock( IPageRepository::class );
		$mockPageRepository->method( 'findBySlug' )->with( 'nonexistent' )->willReturn( null );

		$mockRenderer = $this->createMock( EditorJsRenderer::class );
		$mockSettingManager = Registry::getInstance()->get( 'Settings' );
		$mockSessionManager = $this->createMock( \Neuron\Cms\Auth\SessionManager::class );

		$controller = new Pages( null, $mockPageRepository, $mockRenderer, $mockSettingManager, $mockSessionManager );

		$this->expectException( NotFound::class );
		$this->expectExceptionMessage( 'Page not found' );

		$request = new Request();
		$request->setRouteParameters( [ 'slug' => 'nonexistent' ] );
		$controller->show( $request );
	}

	public function testShowThrowsNotFoundForUnpublishedPage(): void
	{
		$mockPage = $this->createMock( Page::class );
		$mockPage->method( 'isPublished' )->willReturn( false );

		$mockPageRepository = $this->createMock( IPageRepository::class );
		$mockPageRepository->method( 'findBySlug' )->with( 'unpublished' )->willReturn( $mockPage );

		$mockRenderer = $this->createMock( EditorJsRenderer::class );
		$mockSettingManager = Registry::getInstance()->get( 'Settings' );
		$mockSessionManager = $this->createMock( \Neuron\Cms\Auth\SessionManager::class );

		$controller = new Pages( null, $mockPageRepository, $mockRenderer, $mockSettingManager, $mockSessionManager );

		$this->expectException( NotFound::class );
		$this->expectExceptionMessage( 'Page not found' );

		$request = new Request();
		$request->setRouteParameters( [ 'slug' => 'unpublished' ] );
		$controller->show( $request );
	}

	public function testShowUsesPageTitleWhenMetaTitleNotSet(): void
	{
		$mockPage = $this->createMock( Page::class );
		$mockPage->method( 'getId' )->willReturn( 1 );
		$mockPage->method( 'getTitle' )->willReturn( 'Test Page' );
		$mockPage->method( 'isPublished' )->willReturn( true );
		$mockPage->method( 'getContent' )->willReturn( [ 'blocks' => [] ] );
		$mockPage->method( 'getMetaTitle' )->willReturn( '' );  // Empty meta title
		$mockPage->method( 'getMetaDescription' )->willReturn( '' );
		$mockPage->method( 'getMetaKeywords' )->willReturn( '' );

		$mockPageRepository = $this->createMock( IPageRepository::class );
		$mockPageRepository->method( 'findBySlug' )->willReturn( $mockPage );
		$mockPageRepository->method( 'incrementViewCount' );

		$mockRenderer = $this->createMock( EditorJsRenderer::class );
		$mockRenderer->method( 'render' )->willReturn( '<p>Content</p>' );

		$mockSettingManager = Registry::getInstance()->get( 'Settings' );
		$mockSessionManager = $this->createMock( \Neuron\Cms\Auth\SessionManager::class );

		$controller = $this->getMockBuilder( Pages::class )
			->setConstructorArgs( [ null, $mockPageRepository, $mockRenderer, $mockSettingManager, $mockSessionManager ] )
			->onlyMethods( [ 'renderHtml' ] )
			->getMock();

		$controller->expects( $this->once() )
			->method( 'renderHtml' )
			->with(
				$this->anything(),
				$this->callback( function( $data ) {
					// Title should use page title when meta title is empty
					return str_contains( $data['Title'], 'Test Page' );
				} ),
				'show'
			)
			->willReturn( '<html>Page</html>' );

		$request = new Request();
		$request->setRouteParameters( [ 'slug' => 'test-page' ] );
		$controller->show( $request );
	}

	public function testShowUsesDefaultDescriptionWhenMetaDescriptionNotSet(): void
	{
		$mockPage = $this->createMock( Page::class );
		$mockPage->method( 'getId' )->willReturn( 1 );
		$mockPage->method( 'getTitle' )->willReturn( 'Test Page' );
		$mockPage->method( 'isPublished' )->willReturn( true );
		$mockPage->method( 'getContent' )->willReturn( [ 'blocks' => [] ] );
		$mockPage->method( 'getMetaTitle' )->willReturn( 'Meta Title' );
		$mockPage->method( 'getMetaDescription' )->willReturn( '' );  // Empty meta description
		$mockPage->method( 'getMetaKeywords' )->willReturn( '' );

		$mockPageRepository = $this->createMock( IPageRepository::class );
		$mockPageRepository->method( 'findBySlug' )->willReturn( $mockPage );
		$mockPageRepository->method( 'incrementViewCount' );

		$mockRenderer = $this->createMock( EditorJsRenderer::class );
		$mockRenderer->method( 'render' )->willReturn( '<p>Content</p>' );

		$mockSettingManager = Registry::getInstance()->get( 'Settings' );
		$mockSessionManager = $this->createMock( \Neuron\Cms\Auth\SessionManager::class );

		$controller = $this->getMockBuilder( Pages::class )
			->setConstructorArgs( [ null, $mockPageRepository, $mockRenderer, $mockSettingManager, $mockSessionManager ] )
			->onlyMethods( [ 'renderHtml', 'getDescription' ] )
			->getMock();

		$controller->method( 'getDescription' )->willReturn( 'Default Description' );

		$controller->expects( $this->once() )
			->method( 'renderHtml' )
			->with(
				$this->anything(),
				$this->callback( function( $data ) {
					// Description should use default when meta description is empty
					return $data['Description'] === 'Default Description';
				} ),
				'show'
			)
			->willReturn( '<html>Page</html>' );

		$request = new Request();
		$request->setRouteParameters( [ 'slug' => 'test-page' ] );
		$controller->show( $request );
	}
}
