<?php
/**
 * Human Made RSS Delivery
 *
 * @package HM\RSS_Delivery
 */

namespace HM\RSS_Delivery;

use DOMAttr;
use DOMDocument;
use DOMElement;
use Tarosky\FeedGenerator\DeliveryManager;

const PREFIX = 'hm_featured_media';

/**
 * Plugin bootstrapper vsc
 */
function bootstrap() {
	DeliveryManager::instance();
	add_filter( 'the_content_feed', __NAMESPACE__ . '\\smartnews_add_media_at_content_top' );
	add_filter( 'the_content_feed', __NAMESPACE__ . '\\line_add_caption_attribute' );
	add_filter( 'wp_kses_allowed_html', __NAMESPACE__ . '\\handle_tags_for_feeds' );
	add_filter( 'content_pagination', __NAMESPACE__ . '\\no_paging_in_feeds', 10, 2 );
}

/**
 * Add youtube iFrame or featured image to smartphone feeds.
 *
 * @param string $content The current post content.
 *
 * @return string
 */
function smartnews_add_media_at_content_top( string $content ) : string {
	if ( strpos( $_SERVER['REQUEST_URI'], '/feed/smartnews/' ) === false ) {
		return $content;
	}

	$media = wp_oembed_get( get_post_meta( get_the_ID(), PREFIX . '_media_video', true ) );

	// Replication wp block classes here.
	$classes = 'wp-block-embed-youtube wp-block-embed is-type-video is-provider-youtube wp-embed-aspect-16-9 wp-has-aspect-ratio';

	if ( $media ) {
		$image   = wp_get_attachment_image_src( get_post_thumbnail_id( null ), 'landscape_wide' );
		$classes = 'wp-block-image';
		$media   = sprintf(
			'<img src="%s" alt="" class="c-thumb__media wp-image-%d">',
			esc_url( $image[0] ),
			esc_attr( get_the_ID() )
		);
	}

	// Replicating how media looks like in a post.
	$media = sprintf( '<figure class="%s">%s</figure>', esc_attr( $classes ), $media );

	$allowed = [
		'iframe' => [
			'title'           => [],
			'width'           => [],
			'height'          => [],
			'src'             => [],
			'frameborder'     => [],
			'class'           => [],
			'allowfullscreen' => [],
		],
		'figure' => [
			'class' => [],
		],
		'img'    => [
			'width'  => [],
			'height' => [],
			'src'    => [],
			'class'  => [],
			'alt'    => [],
		],
	];

	return sprintf( '%s%s', wp_kses( $media, $allowed ), $content );
}

/**
 * Print Copyright for feed
 *
 * @return void
 */
function print_feed_copyright() : void {
	echo wp_kses(
		sprintf(
			'<copyright>%s</copyright>',
			esc_html__( '© All rights reserved.', 'hm-rssdelivery' )
		),
		[ 'copyright' => [] ]
	);
}

/**
 * Remove anchor tag from allowed tags in Line Feed.
 * Add iframe tag so that embeds works in the feed.
 *
 * @param array $tags Allowed tags.
 *
 * @return array
 */
function handle_tags_for_feeds( array $tags ) : array {
	if ( strpos( $_SERVER['REQUEST_URI'], '/feed/' ) === false ) {
		return $tags;
	}

	if ( ! array_key_exists( 'iframe', $tags ) ) {
		$tags['iframe'] = [
			'title'           => [],
			'width'           => [],
			'height'          => [],
			'src'             => [],
			'frameborder'     => [],
			'allow'           => [],
			'allowfullscreen' => [],
			'class'           => [],
		];
	}

	if ( ! array_key_exists( 'script', $tags ) ) {
		$tags['script'] = [
			'async'   => [],
			'src'     => [],
			'charset' => [],
		];
	}

	return $tags;
}

/**
 * Add caption to `img` tag
 *
 * The figcaption is not a valid xml so line allows a `data-caption` attribute.
 *
 * phpcs:disable WordPress.NamingConventions.ValidVariableName.NotSnakeCaseMemberVar
 *
 * @param string $content The current post content.
 *
 * @return string
 */
function line_add_caption_attribute( string $content ) : string {
	// Feed should be either line or linevideo.
	if ( strpos( $_SERVER['REQUEST_URI'], '/feed/line' ) === false || empty( $content ) ) {
		return $content;
	}

	$dom = new DOMDocument();

	/**
	 * HTML5 tags are not recognized by old DOMDocument parser of PHP
	 * and throws unnecessary warnings around the those tags.
	 *
	 * TODO: find a better way to handle HTML5 tags.
	 */
	libxml_use_internal_errors( true );

	/**
	 * "HTML" is alis for HTML-ENTITIES
	 * "auto" is expanded to "ASCII,JIS,UTF-8,EUC-JP,SJIS"
	 */
	$dom->loadHTML( mb_convert_encoding( $content, 'HTML', 'auto' ) );

	// clear errors.
	libxml_clear_errors();

	$images = $dom->getElementsByTagName( 'img' );

	if ( empty( $images->length ) ) {
		return  $content;
	}

	$error = false;

	for ( $i = $images->length; --$i >= 0; ) {
		$image = $images->item( $i );

		if ( ! $image instanceof DOMElement ) {
			continue;
		}

		$next_sibling = $image->nextSibling;

		if (
			! $next_sibling instanceof DOMElement
			|| $next_sibling->tagName !== 'figcaption'
			|| empty( $next_sibling->textContent )
		) {
			continue;
		}

		$text_content = $next_sibling->textContent;
		$dom_attr = $image->setAttribute( 'data-caption', esc_attr( $text_content ) );

		if ( $dom_attr instanceof DOMAttr && $dom_attr->textContent === $text_content ) {
			$next_sibling->parentNode->removeChild( $next_sibling );
			continue;
		}

		$error = trigger_error( 'Data caption attribute could not be set for line/linevideo feed' );
	}

	if ( $error ) {
		return $content;
	}

	return $dom->saveHTML( $dom->documentElement );
}

/**
 * Don't do paging in the feed even if there is a nextpage tag.
 *
 * @param string[] $pages Array of "pages" from the post content split by `<!-- nextpage -->` tags.
 * @param WP_Post  $post  Current post object.
 */
function no_paging_in_feeds( $pages, $post ) {
	if ( is_feed() ) {
		$pages = [ implode( "\n\n", $pages ) ];
	}
	return $pages;
}
