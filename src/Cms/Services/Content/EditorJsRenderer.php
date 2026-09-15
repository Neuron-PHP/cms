<?php

namespace Neuron\Cms\Services\Content;

/**
 * Renders Editor.js JSON content to HTML.
 *
 * Supports standard Editor.js block types and custom shortcodes.
 *
 * @package Neuron\Cms\Services\Content
 */
class EditorJsRenderer
{
	private ?ShortcodeParser $_shortcodeParser = null;
	private bool $_needsInstagramEmbedScript = false;

	public function __construct( ?ShortcodeParser $shortcodeParser = null )
	{
		$this->_shortcodeParser = $shortcodeParser;
	}

	/**
	 * Render Editor.js JSON data to HTML
	 *
	 * @param array $editorData Editor.js JSON data (decoded)
	 * @return string Rendered HTML
	 */
	public function render( array $editorData ): string
	{
		$this->_needsInstagramEmbedScript = false;
		$html = '';

		foreach( $editorData['blocks'] ?? [] as $block )
		{
			$html .= $this->renderBlock( $block );
		}

		if( $this->_needsInstagramEmbedScript )
		{
			$html .= "<script async src='https://www.instagram.com/embed.js'></script>\n";
		}

		return $html;
	}

	/**
	 * Render a single block
	 *
	 * @param array $block Block data
	 * @return string Rendered HTML
	 */
	private function renderBlock( array $block ): string
	{
		$type = $block['type'] ?? 'paragraph';
		$data = $block['data'] ?? [];

		return match( $type )
		{
			'header' => $this->renderHeader( $data ),
			'paragraph' => $this->renderParagraph( $data ),
			'list' => $this->renderList( $data ),
			'image' => $this->renderImage( $data ),
			'quote' => $this->renderQuote( $data ),
			'code' => $this->renderCode( $data ),
			'delimiter' => $this->renderDelimiter( $data ),
			'raw' => $this->renderRaw( $data ),
			'embed' => $this->renderEmbed( $data ),
			default => $this->renderUnknown( $type )
		};
	}

	/**
	 * Render header block
	 */
	private function renderHeader( array $data ): string
	{
		// Sanitize header level: coerce to int and clamp to valid range (1-6)
		$rawLevel = $data['level'] ?? 2;
		$level = max( 1, min( 6, intval( $rawLevel ) ) );

		$text = $this->parseInlineContent( $data['text'] ?? '' );

		return "<h{$level} class='my-4'>{$text}</h{$level}>\n";
	}

	/**
	 * Render paragraph block
	 */
	private function renderParagraph( array $data ): string
	{
		$text = $this->parseInlineContent( $data['text'] ?? '' );

		return "<p class='mb-3'>{$text}</p>\n";
	}

	/**
	 * Render list block
	 */
	private function renderList( array $data ): string
	{
		$style = $data['style'] ?? 'unordered';
		$items = $data['items'] ?? [];

		$tag = $style === 'ordered' ? 'ol' : 'ul';

		$html = "<{$tag} class='mb-3'>\n";
		foreach( $items as $item )
		{
			$html .= $this->renderListItem( $item, $style );
		}
		$html .= "</{$tag}>\n";

		return $html;
	}

	/**
	 * Render a single list item (handles both strings and nested structures)
	 *
	 * Editor.js List v1.9+ supports nested lists where items can be:
	 * - Simple strings: "Item text"
	 * - Objects with nested items: { "content": "Item text", "items": [nested items] }
	 *
	 * @param mixed $item The list item (string or array)
	 * @param string $style List style (ordered/unordered)
	 * @return string Rendered HTML
	 */
	private function renderListItem( mixed $item, string $style ): string
	{
		// Handle simple string items (legacy format and leaf items)
		if( is_string( $item ) )
		{
			return "  <li>" . $this->parseInlineContent( $item ) . "</li>\n";
		}

		// Handle nested list items (objects with content and items)
		if( is_array( $item ) && isset( $item['content'] ) )
		{
			$html = "  <li>\n";
			$html .= "    " . $this->parseInlineContent( $item['content'] ) . "\n";

			// Recursively render nested items
			if( isset( $item['items'] ) && is_array( $item['items'] ) && !empty( $item['items'] ) )
			{
				$tag = $style === 'ordered' ? 'ol' : 'ul';
				$html .= "    <{$tag}>\n";
				foreach( $item['items'] as $nestedItem )
				{
					// Indent nested items
					$nestedHtml = $this->renderListItem( $nestedItem, $style );
					$html .= "  " . $nestedHtml;
				}
				$html .= "    </{$tag}>\n";
			}

			$html .= "  </li>\n";
			return $html;
		}

		// Fallback for unknown item types (shouldn't happen in valid Editor.js data)
		return "  <li><!-- Invalid list item --></li>\n";
	}

	/**
	 * Render image block
	 */
	private function renderImage( array $data ): string
	{
		$url = htmlspecialchars( $data['file']['url'] ?? '' );
		$caption = htmlspecialchars( $data['caption'] ?? '' );
		$stretched = $data['stretched'] ?? false;
		$withBorder = $data['withBorder'] ?? false;
		$withBackground = $data['withBackground'] ?? false;

		$imgClass = 'img-fluid';
		if( $stretched )
		{
			$imgClass .= ' w-100';
		}
		if( $withBorder )
		{
			$imgClass .= ' border';
		}

		$figureClass = 'my-4';
		if( $withBackground )
		{
			$figureClass .= ' bg-light p-3';
		}

		$html = "<figure class='{$figureClass}'>\n";
		$html .= "  <img src='{$url}' class='{$imgClass}' alt='{$caption}'>\n";
		if( $caption )
		{
			$html .= "  <figcaption class='text-center text-muted mt-2'>{$caption}</figcaption>\n";
		}
		$html .= "</figure>\n";

		return $html;
	}

	/**
	 * Render quote block
	 */
	private function renderQuote( array $data ): string
	{
		$text = htmlspecialchars( $data['text'] ?? '' );
		$caption = htmlspecialchars( $data['caption'] ?? '' );
		$alignment = $data['alignment'] ?? 'left';

		$alignmentClass = match( $alignment )
		{
			'center' => 'text-center',
			'right' => 'text-end',
			default => ''
		};

		$html = "<blockquote class='blockquote my-4 {$alignmentClass}'>\n";
		$html .= "  <p class='mb-0'>{$text}</p>\n";
		if( $caption )
		{
			$html .= "  <footer class='blockquote-footer mt-2'>{$caption}</footer>\n";
		}
		$html .= "</blockquote>\n";

		return $html;
	}

	/**
	 * Render code block
	 */
	private function renderCode( array $data ): string
	{
		$code = htmlspecialchars( $data['code'] ?? '' );

		return "<pre class='my-4'><code>{$code}</code></pre>\n";
	}

	/**
	 * Render delimiter block
	 */
	private function renderDelimiter( array $data ): string
	{
		return "<hr class='my-4'>\n";
	}

	/**
	 * Render raw HTML block
	 */
	private function renderRaw( array $data ): string
	{
		$html = $data['html'] ?? '';

		// Sanitize HTML to prevent XSS
		return $this->sanitizeHtml( $html ) . "\n";
	}

	/**
	 * Render embed block
	 */
	private function renderEmbed( array $data ): string
	{
		$service = $data['service'] ?? '';
		$source = $data['source'] ?? '';
		$embed = $data['embed'] ?? '';
		$width = max( 1, intval( $data['width'] ?? 580 ) );
		$height = max( 1, intval( $data['height'] ?? 320 ) );
		$caption = htmlspecialchars( $data['caption'] ?? '' );

		// If no embed URL, return comment
		if( empty( $embed ) )
		{
			return "<!-- Embed block missing URL -->\n";
		}

		// Sanitize embed URL - only allow trusted domains
		$allowedDomains = [
			'youtube.com',
			'youtube-nocookie.com',
			'youtu.be',
			'vimeo.com',
			'player.vimeo.com',
			'twitter.com',
			'platform.twitter.com',
			'instagram.com',
			'facebook.com',
			'codepen.io',
			'github.com',
			'gist.github.com',
			'coub.com',
			'imgur.com',
			'gfycat.com',
			'giphy.com',
			'reddit.com',
			'open.spotify.com',
			'soundcloud.com',
			'twitch.tv',
			'player.twitch.tv',
			'miro.com',
			'figma.com'
		];

		$embedUrl = htmlspecialchars( $embed );
		$parsedUrl = parse_url( $embed );
		$host = $parsedUrl['host'] ?? '';

		// Check if domain is allowed
		$isAllowed = false;
		foreach( $allowedDomains as $domain )
		{
			if( $host === $domain || str_ends_with( $host, '.' . $domain ) )
			{
				$isAllowed = true;
				break;
			}
		}

		if( !$isAllowed )
		{
			return "<!-- Embed from untrusted domain: {$host} -->\n";
		}

		if( $service === 'instagram' )
		{
			return $this->renderInstagramEmbed( $source, $embed, $caption );
		}

		// Landscape stays 16:9. Tall embeds use a portrait frame so
		// vertical video is not cropped.
		[ $figureClass, $figureAttr, $ratioClass, $ratioAttr ] = $this->embedFrameAttributes( $width, $height );

		$html = "<figure class='{$figureClass}'{$figureAttr}>\n";
		$html .= "  <div class='{$ratioClass}'{$ratioAttr}>\n";
		$html .= "    <iframe src='{$embedUrl}' frameborder='0' allowfullscreen sandbox='allow-scripts allow-same-origin allow-presentation allow-popups'></iframe>\n";
		$html .= "  </div>\n";

		if( $caption )
		{
			$html .= "  <figcaption class='text-center text-muted mt-2'>{$caption}</figcaption>\n";
		}

		$html .= "</figure>\n";

		return $html;
	}

	/**
	 * Render an Instagram post with Instagram's embed script so the
	 * iframe matches the media height instead of a guessed ratio.
	 */
	private function renderInstagramEmbed( string $source, string $embed, string $caption ): string
	{
		$permalink = $this->instagramPermalink( $source, $embed );

		if( $permalink === null )
		{
			return "<!-- Embed from untrusted domain: instagram -->\n";
		}

		$permalinkAttr = htmlspecialchars( $permalink );

		$html = "<figure class='embed-responsive embed-instagram my-4 mx-auto' style='max-width: 540px;'>\n";
		$html .= "  <blockquote class='instagram-media' data-instgrm-permalink='{$permalinkAttr}' data-instgrm-version='14' style='background: #FFF; border: 0; margin: 0 auto; max-width: 540px; min-width: 326px; width: 100%;'></blockquote>\n";

		if( $caption )
		{
			$html .= "  <figcaption class='text-center text-muted mt-2'>{$caption}</figcaption>\n";
		}

		$html .= "</figure>\n";

		$this->_needsInstagramEmbedScript = true;

		return $html;
	}

	/**
	 * Build a canonical Instagram permalink from the Editor.js source or embed URL.
	 */
	private function instagramPermalink( string $source, string $embed ): ?string
	{
		foreach( [ $source, $embed ] as $url )
		{
			if( $url === '' )
			{
				continue;
			}

			$parsed = parse_url( $url );
			$scheme = strtolower( $parsed['scheme'] ?? '' );
			$host = strtolower( $parsed['host'] ?? '' );

			if( $scheme !== 'https' )
			{
				continue;
			}

			if( $host !== 'instagram.com' && !str_ends_with( $host, '.instagram.com' ) )
			{
				continue;
			}

			$path = $parsed['path'] ?? '';
			if( preg_match( '#^/(p|reel|tv)/([A-Za-z0-9_-]+)#', $path, $matches ) )
			{
				return 'https://www.instagram.com/' . $matches[1] . '/' . $matches[2] . '/';
			}
		}

		return null;
	}

	/**
	 * Choose a responsive frame for the embed.
	 *
	 * @return array{0: string, 1: string, 2: string, 3: string} figure class, figure attrs, ratio class, ratio attrs
	 */
	private function embedFrameAttributes( int $width, int $height ): array
	{
		$aspectPercent = ( $height / $width ) * 100;

		if( $aspectPercent < 100.0 )
		{
			return [ 'embed-responsive my-4', '', 'ratio ratio-16x9', '' ];
		}

		$maxWidth = min( $width, 540 );
		$formatted = rtrim( rtrim( number_format( $aspectPercent, 2, '.', '' ), '0' ), '.' );

		return [
			'embed-responsive embed-portrait my-4 mx-auto',
			" style='max-width: {$maxWidth}px;'",
			'ratio',
			" style='--bs-aspect-ratio: {$formatted}%;'"
		];
	}

	/**
	 * Render unknown block type
	 */
	private function renderUnknown( string $type ): string
	{
		return "<!-- Unknown block type: {$type} -->\n";
	}

	/**
	 * Parse inline content (may contain shortcodes or simple HTML)
	 *
	 * @param string $content
	 * @return string
	 */
	private function parseInlineContent( string $content ): string
	{
		// Check for shortcodes
		if( $this->_shortcodeParser && str_contains( $content, '[' ) )
		{
			return $this->_shortcodeParser->parse( $content );
		}

		// Otherwise, sanitize and return
		return $this->sanitizeHtml( $content );
	}

	/**
	 * Sanitize HTML to prevent XSS while allowing safe tags
	 *
	 * @param string $html
	 * @return string
	 */
	private function sanitizeHtml( string $html ): string
	{
		// Allow common inline HTML tags
		$allowedTags = '<b><strong><i><em><u><a><code><mark>';

		return strip_tags( $html, $allowedTags );
	}
}
