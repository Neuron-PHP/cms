<?php

namespace Neuron\Cms\Services\Breadcrumb;

use Neuron\Data\Settings\SettingManager;

/**
 * Public breadcrumb trails for pages, posts, and other CMS routes.
 *
 * @package Neuron\Cms\Services\Breadcrumb
 */
class BreadcrumbService
{
	private SettingManager $_settings;

	public function __construct( SettingManager $settings )
	{
		$this->_settings = $settings;
	}

	public function isEnabled(): bool
	{
		$value = $this->_settings->get( 'breadcrumbs', 'enabled' );

		return $value === null ? true : (bool) $value;
	}

	public function jsonLdEnabled(): bool
	{
		$value = $this->_settings->get( 'breadcrumbs', 'json_ld' );

		return $value === null ? true : (bool) $value;
	}

	public function homeLabel(): string
	{
		$label = trim( (string) ( $this->_settings->get( 'breadcrumbs', 'home_label' ) ?? '' ) );

		return $label !== '' ? $label : 'Home';
	}

	public function homeUrl(): string
	{
		if( function_exists( 'route_path' ) )
		{
			$path = route_path( 'home' );

			if( $path !== '' )
			{
				return $path;
			}
		}

		return '/';
	}

	/**
	 * Prepend Home to a trail. Empty input or a disabled setting yields [].
	 *
	 * @param array<int, array{label: string, url?: ?string}> $items
	 * @return array<int, array{label: string, url: ?string}>
	 */
	public function trail( array $items ): array
	{
		if( !$this->isEnabled() || $items === [] )
		{
			return [];
		}

		$homeUrl = $this->homeUrl();
		$firstUrl = $items[0]['url'] ?? null;

		if( $firstUrl !== $homeUrl && ( $items[0]['label'] ?? '' ) !== $this->homeLabel() )
		{
			array_unshift( $items, [
				'label' => $this->homeLabel(),
				'url' => $homeUrl
			] );
		}

		$normalized = [];

		foreach( $items as $item )
		{
			$label = trim( (string) ( $item['label'] ?? '' ) );

			if( $label === '' )
			{
				continue;
			}

			$url = $item['url'] ?? null;
			$normalized[] = [
				'label' => $label,
				'url' => $url !== '' ? $url : null
			];
		}

		if( $normalized === [] )
		{
			return [];
		}

		$normalized[ array_key_last( $normalized ) ]['url'] = null;

		return $normalized;
	}

	/**
	 * @param array<int, array{label: string, url: ?string}> $items
	 */
	public function render( array $items ): string
	{
		if( $items === [] )
		{
			return '';
		}

		$html = '<nav aria-label="breadcrumb"><ol class="breadcrumb">';

		foreach( $items as $item )
		{
			$label = htmlspecialchars( $item['label'], ENT_QUOTES, 'UTF-8' );

			if( $item['url'] )
			{
				$html .= '<li class="breadcrumb-item"><a href="'
					. htmlspecialchars( $item['url'], ENT_QUOTES, 'UTF-8' )
					. '">' . $label . '</a></li>';
			}
			else
			{
				$html .= '<li class="breadcrumb-item active" aria-current="page">' . $label . '</li>';
			}
		}

		$html .= '</ol></nav>';

		return $html;
	}

	/**
	 * @param array<int, array{label: string, url: ?string}> $items
	 */
	public function jsonLd( array $items, string $canonical = '' ): string
	{
		if( !$this->jsonLdEnabled() || count( $items ) < 2 )
		{
			return '';
		}

		$list = [];

		foreach( $items as $position => $item )
		{
			$entry = [
				'@type' => 'ListItem',
				'position' => $position + 1,
				'name' => $item['label']
			];

			$url = $item['url'] ?? ( $position === array_key_last( $items ) ? $canonical : null );

			if( $url )
			{
				$entry['item'] = $this->absoluteUrl( $url );
			}

			$list[] = $entry;
		}

		$payload = [
			'@context' => 'https://schema.org',
			'@type' => 'BreadcrumbList',
			'itemListElement' => $list
		];

		return '<script type="application/ld+json">'
			. json_encode( $payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE )
			. '</script>';
	}

	private function absoluteUrl( string $path ): string
	{
		if( preg_match( '#^https?://#i', $path ) )
		{
			return $path;
		}

		$base = rtrim( (string) ( $this->_settings->get( 'site', 'url' ) ?? '' ), '/' );

		if( $base === '' )
		{
			return $path;
		}

		if( $path === '' || $path === '/' )
		{
			return $base . '/';
		}

		return $base . ( str_starts_with( $path, '/' ) ? $path : '/' . $path );
	}
}
