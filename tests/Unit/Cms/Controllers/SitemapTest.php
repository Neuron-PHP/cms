<?php

namespace Tests\Cms\Controllers;

use DateTimeImmutable;
use Neuron\Cms\Auth\SessionManager;
use Neuron\Cms\Controllers\Sitemap;
use Neuron\Cms\Models\Event;
use Neuron\Cms\Models\Page;
use Neuron\Cms\Models\Post;
use Neuron\Cms\Repositories\IEventRepository;
use Neuron\Cms\Repositories\IPageRepository;
use Neuron\Cms\Repositories\IPostRepository;
use Neuron\Core\Registry\RegistryKeys;
use Neuron\Data\Settings\SettingManager;
use Neuron\Mvc\IMvcApplication;
use Neuron\Patterns\Registry;
use Neuron\Routing\Router;
use PHPUnit\Framework\TestCase;

class SitemapTest extends TestCase
{
	public function testDocumentIncludesPublishedContentAndSkipsDraftEvents(): void
	{
		$page = $this->createMock( Page::class );
		$page->method( 'getSlug' )->willReturn( 'about' );
		$page->method( 'getUpdatedAt' )->willReturn( new DateTimeImmutable( '2026-03-01' ) );
		$page->method( 'getPublishedAt' )->willReturn( new DateTimeImmutable( '2026-01-01' ) );

		$post = $this->createMock( Post::class );
		$post->method( 'getSlug' )->willReturn( 'hello' );
		$post->method( 'getUpdatedAt' )->willReturn( null );
		$post->method( 'getPublishedAt' )->willReturn( new DateTimeImmutable( '2026-02-01' ) );

		$publishedEvent = $this->createMock( Event::class );
		$publishedEvent->method( 'isPublished' )->willReturn( true );
		$publishedEvent->method( 'getSlug' )->willReturn( 'camp' );

		$draftEvent = $this->createMock( Event::class );
		$draftEvent->method( 'isPublished' )->willReturn( false );
		$draftEvent->method( 'getSlug' )->willReturn( 'hidden' );

		$pages = $this->createMock( IPageRepository::class );
		$pages->method( 'getPublished' )->willReturn( [ $page ] );

		$posts = $this->createMock( IPostRepository::class );
		$posts->method( 'getPublished' )->willReturn( [ $post ] );

		$events = $this->createMock( IEventRepository::class );
		$events->method( 'all' )->willReturn( [ $publishedEvent, $draftEvent ] );

		$settings = $this->createMock( SettingManager::class );
		$settings->method( 'get' )->willReturnCallback( function( $section, $key ) {
			$defaults = [
				'site' => [
					'name' => 'Test Site',
					'title' => 'Test Title',
					'description' => 'Test Description',
					'url' => 'https://example.test',
				]
			];

			return $defaults[ $section ][ $key ] ?? null;
		} );
		Registry::getInstance()->set( RegistryKeys::SETTINGS, $settings );

		$app = $this->createMock( IMvcApplication::class );
		$app->method( 'getRouter' )->willReturn( $this->createMock( Router::class ) );

		$sitemap = new Sitemap(
			$app,
			$settings,
			$this->createMock( SessionManager::class ),
			$pages,
			$posts,
			$events
		);

		$xml = $sitemap->document();

		$this->assertStringContainsString( '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">', $xml );
		$this->assertStringContainsString( '<loc>https://example.test/</loc>', $xml );
		$this->assertStringContainsString( '<lastmod>2026-03-01</lastmod>', $xml );
		$this->assertStringContainsString( '<lastmod>2026-02-01</lastmod>', $xml );
		$this->assertStringNotContainsString( 'hidden', $xml );
	}
}
