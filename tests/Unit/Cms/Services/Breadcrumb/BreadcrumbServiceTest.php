<?php

namespace Tests\Unit\Cms\Services\Breadcrumb;

use Neuron\Cms\Services\Breadcrumb\BreadcrumbService;
use Neuron\Data\Settings\SettingManager;
use PHPUnit\Framework\TestCase;

class BreadcrumbServiceTest extends TestCase
{
	public function testTrailPrependsHomeAndClearsLastUrl(): void
	{
		$service = new BreadcrumbService( $this->settings() );

		$trail = $service->trail( [
			[ 'label' => 'Blog', 'url' => '/blog' ],
			[ 'label' => 'Hello', 'url' => '/blog/post/hello' ]
		] );

		$this->assertSame( 'Home', $trail[0]['label'] );
		$this->assertSame( '/', $trail[0]['url'] );
		$this->assertSame( 'Blog', $trail[1]['label'] );
		$this->assertSame( '/blog', $trail[1]['url'] );
		$this->assertSame( 'Hello', $trail[2]['label'] );
		$this->assertNull( $trail[2]['url'] );
	}

	public function testTrailReturnsEmptyWhenDisabledOrEmpty(): void
	{
		$disabled = new BreadcrumbService( $this->settings( [ 'enabled' => false ] ) );
		$enabled = new BreadcrumbService( $this->settings() );

		$this->assertSame( [], $disabled->trail( [ [ 'label' => 'About' ] ] ) );
		$this->assertSame( [], $enabled->trail( [] ) );
	}

	public function testRenderMarksCurrentPage(): void
	{
		$service = new BreadcrumbService( $this->settings() );
		$html = $service->render( $service->trail( [ [ 'label' => 'About' ] ] ) );

		$this->assertStringContainsString( 'aria-label="breadcrumb"', $html );
		$this->assertStringContainsString( 'href="/"', $html );
		$this->assertStringContainsString( 'aria-current="page">About</li>', $html );
	}

	public function testJsonLdIncludesAbsoluteUrls(): void
	{
		$service = new BreadcrumbService( $this->settings() );
		$trail = $service->trail( [
			[ 'label' => 'Blog', 'url' => '/blog' ],
			[ 'label' => 'Hello' ]
		] );

		$json = $service->jsonLd( $trail, 'https://example.test/blog/post/hello' );

		$this->assertStringContainsString( 'application/ld+json', $json );
		$this->assertStringContainsString( 'BreadcrumbList', $json );
		$this->assertStringContainsString( 'https://example.test/blog', $json );
		$this->assertStringContainsString( 'https://example.test/blog/post/hello', $json );
	}

	public function testJsonLdCanBeDisabled(): void
	{
		$service = new BreadcrumbService( $this->settings( [ 'json_ld' => false ] ) );
		$trail = $service->trail( [ [ 'label' => 'About' ] ] );

		$this->assertSame( '', $service->jsonLd( $trail, 'https://example.test/about' ) );
	}

	public function testHomeLabelIsConfigurable(): void
	{
		$service = new BreadcrumbService( $this->settings( [ 'home_label' => 'Start' ] ) );
		$trail = $service->trail( [ [ 'label' => 'About' ] ] );

		$this->assertSame( 'Start', $trail[0]['label'] );
	}

	/**
	 * @param array<string, mixed> $crumbs
	 */
	private function settings( array $crumbs = [] ): SettingManager
	{
		$settings = $this->createMock( SettingManager::class );
		$settings->method( 'get' )
			->willReturnCallback( function( string $section, string $name ) use ( $crumbs ) {
				if( $section === 'site' && $name === 'url' )
				{
					return 'https://example.test';
				}

				if( $section === 'breadcrumbs' )
				{
					return $crumbs[ $name ] ?? null;
				}

				return null;
			} );

		return $settings;
	}
}
