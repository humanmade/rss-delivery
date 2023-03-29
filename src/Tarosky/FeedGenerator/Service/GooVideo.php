<?php
/**
 * Goo Video Feed
 *
 * @package FujiTV
 */

namespace Tarosky\FeedGenerator\Service;

use Cloudinary\Api;
use Cloudinary\Api\Admin\AdminApi;
use const FujiTV\Post_Meta\PREFIX as POST_META;
use const FujiTV\Video\CPT_SLUG;
use Tarosky\FeedGenerator\AbstractFeed;
use Tarosky\FeedGenerator\DeliveryManager;
use WP_Query;

/**
 * RSS for Goo Video
 */
class GooVideo extends AbstractFeed {

	/**
	 * Current Post ID.
	 *
	 * @var int
	 */
	protected static $post_id;

	/**
	 * Specify the conditions to create a feed
	 *
	 * @return array
	 */
	protected function get_query_arg() {
		$dm = DeliveryManager::instance();
		$id = $this->get_id();

		return [
			'feed'          => 'goovideo',
			'posts_per_rss' => $this->per_page,
			'post_type'     => CPT_SLUG,
			'post_status'   => [ 'publish', 'trash' ],
			'orderby'       => [
				'modified' => 'DESC',
			],
			'meta_query'    => [ // phpcs:disable WordPress.DB.SlowDBQuery.slow_db_query_meta_query
				'relation' => 'AND',
				[
					'key'     => $dm->get_meta_name(),
					'value'   => sprintf( '"%s"', $id ),
					'compare' => 'REGEXP',
				],
				[
					'key'     => 'fujitv_post_meta_video_url',
					'compare' => 'EXISTS',
				],
			],
		];
	}

	/**
	 * Query overwrite
	 *
	 * @param WP_Query $wp_query Query.
	 */
	public function pre_get_posts( WP_Query &$wp_query ) {
		/**
		 * CHANNEL
		 */
		// Set the time of lastBuildDate to the local timezone.
		add_filter( 'get_feed_build_date', function( $max_modified_time, $format ) {
			return $this->to_local_time( $max_modified_time, $format, 'Asia/Tokyo', true );
		}, 10, 2 );

		/**
		 * ITEM
		 */
		// Edit the guid tag content.
		add_filter( 'the_guid', function( $guid, $id ) {
			return get_permalink( $id );
		}, 10, 2 );

		$args = $this->get_query_arg();
		if ( $args ) {
			foreach ( $args as $key => $val ) {
				$wp_query->set( $key, $val );
			}
		}
		add_action( 'do_feed_goovideo', [ $this, 'do_feed' ] );
	}

	/**
	 * If the filter hook cannot handle it, recreate the entire feed. Created based on rss2.0
	 */
	public function do_feed() {
		$this->xml_header();

		do_action( 'rss_tag_pre', 'rss2' );
		?>
		<rss version="2.0"
			xmlns:content="http://purl.org/rss/1.0/modules/content/"
			xmlns:dc="http://purl.org/dc/elements/1.1/"
			xmlns:goonews="http://news.goo.ne.jp/rss/2.0/news/goonews/"
			xmlns:smp="http://news.goo.ne.jp/rss/2.0/news/smp/"
			<?php
			do_action( 'rss2_ns' );
			?>
		>

		<channel>
			<title><?php echo esc_html( get_wp_title_rss() ); ?></title>
			<link><?php bloginfo_rss( 'url' ); ?></link>
			<description><?php bloginfo_rss( 'description' ); ?></description>
			<language><?php bloginfo_rss( 'language' ); ?></language>
			<pubDate><?php echo esc_html( get_feed_build_date( 'r' ) ); ?></pubDate>
			<?php
			do_action( 'rss_add_channel', [ $this, 'rss_add_channel' ] );
			do_action( 'rss2_head' );

			while ( have_posts() ) :
				the_post();
				$this->render_item( get_post() );
				?>
			<?php endwhile; ?>
		</channel>
		</rss>
		<?php
	}

	/**
	 * Generate and return each item of Feed
	 *
	 * @param \WP_Post $post Post Object.
	 */
	protected function render_item( $post ) {
		$status  = $post->post_status === 'publish' ? 'active' : 'deleted';
		$content = $this->parse_content( $post->post_content, 'rss2' );

		self::$post_id = (int) $post->ID;

		?>
			<item>
				<guid isPermaLink="false"><?php echo esc_html( get_the_id() ); ?></guid>
				<?php if ( 'deleted' === $status ) : ?>
					<goonews:delete>1</goonews:delete>
				<?php endif; ?>
				<title><?php echo esc_html( get_the_title_rss() ); ?></title>
				<link><?php the_permalink_rss(); ?></link>
				<smp:link><?php the_permalink_rss(); ?></smp:link>
				<category>エンタメ</category>
				<pubDate><?php echo esc_html( $this->to_local_time( get_the_time( 'Y-m-d H:i:s' ), 'r', 'Asia/Tokyo' ) ); ?></pubDate>
				<goonews:modified><?php echo esc_html( $this->to_local_time( get_post_modified_time( 'Y-m-d H:i:s' ), 'r', 'Asia/Tokyo' ) ); ?></goonews:modified>
				<dc:creator>フジテレビュー!!編集部</dc:creator>
				<description><![CDATA[<?php echo wp_kses_post( $content ); ?>]]></description>
				<?php $this->the_video_enclosure(); ?>
				<?php $this->the_ref_link(); ?>

				<?php
					do_action( 'rss2_item', [ $this, 'rss_add_item' ] );
				?>

			</item>

		<?php
	}

	/**
	 * Get Video Enclosure.
	 *
	 * Get Video Meta Data from Cloudinary API.
	 *
	 * @throws Api\GeneralError If API throws error.
	 * @throws Api::CLOUDINARY_API_ERROR_CLASSES If API throws specific errors.
	 *
	 * @return void
	 */
	protected function the_video_enclosure() : void {
		$thumb_size = $this->vars['thumb_size'] ?? 'landscape_wide';
		$video_url  = get_post_meta( self::$post_id, 'fujitv_post_meta_video_url', true );

		if ( empty( $video_url ) ) {
			return;
		}

		$public_id = $this->get_public_id_from_url( $video_url );

		if ( empty( $public_id ) ) {
			return;
		}

		// Initiate cloudinary API request.
		$api = new AdminApi(
			[
				'cloud_name'    => 'fujitv-view',
				'api_key'       => '718624484713196',
				'api_secret'    => 'rw282cGlq5XKkQpAdKhaDlybllQ',
			]
		);
		try {
			$res = $api->asset(
				$public_id,
				[
					'resource_type' => 'video',
				]
			);
			// Catch not found error specifically: FTV-454.
		} catch ( Api\NotFound $exception ) {
			return;
		}
		$data = $res->getArrayCopy();
		printf(
			'<enclosure type="%s" length="%s" alt="%s" url="%s" rev="1" thumb="%s" />',
			esc_attr( 'video/mp4' ),
			esc_attr( $data['bytes'] ),
			esc_attr( get_the_title_rss() ),
			esc_url( $video_url ),
			esc_url( get_the_post_thumbnail_url( null, $thumb_size ) )
		);

	}

	/**
	 * Parse Block Depends on Block Attribute.
	 *
	 * @param string $content   Post Content.
	 * @param string $feed_type Feed Type.
	 *
	 * @return string
	 */
	protected function parse_content( string $content, string $feed_type ) : string {
		$parsed_blocks = parse_blocks( $content );

		$parsed_content = '';

		foreach ( $parsed_blocks as $parsed_block ) {
			if ( empty( $parsed_block['blockName'] ) ) {
				continue;
			}

			if ( isset( $parsed_block['attrs']['enabled'] ) && ! $parsed_block['attrs']['enabled'] ) {
				continue;
			}

			$parsed_content .= $parsed_block['innerHTML'];
		}

		/** This filter is documented in wp-includes/post-template.php */
		$parsed_content = apply_filters( 'the_content', $parsed_content );
		$parsed_content = str_replace( ']]>', ']]&gt;', $parsed_content );

		$parsed_content = str_replace( '<p><!--nextpage--></p>', '<!-- pagebreak -->', $parsed_content );
		$parsed_content = preg_replace( '/<a(?: .+?)?>(.*?)<\/a>/ims', '$1', $parsed_content );

		/** This filter is documented in wp-includes/feed.php */
		return apply_filters( 'the_content_feed', $parsed_content, $feed_type );
	}

	/**
	 * Get related link for Goo.
	 *
	 * @return void
	 */
	protected function the_ref_link() : void {
		$number_ref_link_goonews = 5;
		$number_ref_link_smp     = 3;
		$number_related_links    = 0;
		$ref_links               = [];

		$related_links = get_post_meta( self::$post_id, POST_META . '_related_links', true );
		if ( $related_links ) {
			foreach ( $related_links as $count => $link ) {
				if ( $count >= $number_ref_link_goonews ) {
					break;
				}

				$ref_links[] = [
					'text' => $link['text'],
					'url'  => $link['url'],
				];

				$number_related_links++;
			}
		}

		if ( $number_related_links >= $number_ref_link_goonews ) {
			return;
		}

		$related_topics = get_post_meta( self::$post_id, POST_META . '_related_topics', true );

		$posts = $this->get_related_topics_posts( self::$post_id, $related_topics, $number_ref_link_goonews - $number_related_links );

		if ( empty( $posts ) ) {
			return;
		}

		$connected_galleries = new WP_Query(
			[
				'connected_type'  => 'gallery_to_post',
				'connected_items' => $posts,
			]
		);

		if ( ! $connected_galleries->have_posts() ) {
			return;
		}

		$p2p_to_key_galleries = array_column( $connected_galleries->posts, null, 'p2p_to' );

		foreach ( $posts as $post_id ) {
			if ( ! isset( $p2p_to_key_galleries[ $post_id ] ) ) {
				continue;
			}

			$ref_links[] = [
				'text' => '【写真】' . $p2p_to_key_galleries[ $post_id ]->post_title,
				'url'  => get_permalink( $p2p_to_key_galleries[ $post_id ]->ID ),
			];
		}

		foreach ( $ref_links as $ref_link ) {
			printf(
				'<goonews:relation><goonews:caption>%1$s</goonews:caption><goonews:url>%2$s</goonews:url></goonews:relation>',
				esc_html( $ref_link['text'] ),
				esc_url( $ref_link['url'] )
			);
		}
		foreach ( $ref_links as $number => $ref_link ) {
			if ( $number >= $number_ref_link_smp ) {
				break;
			}

			printf(
				'<smp:relation><smp:caption>%1$s</smp:caption><smp:url>%2$s</smp:url></smp:relation>',
				esc_html( $ref_link['text'] ),
				esc_url( $ref_link['url'] )
			);
		}
	}

	/**
	 * Filters stored video URL before sending to cloudinary.
	 *
	 * @param string $url Stored cloudinary URI.
	 *
	 * @return string
	 */
	protected function get_public_id_from_url( string $url ) : string {
		$basename = basename( $url );

		// Remove URL decoding from string, cloudinary API does not support it.
		$public_id = urldecode( $basename );

		// Goo Video only allows mp4 files.
		if ( strpos( $public_id, '.mp4' ) === false ) {
			return '';
		}

		return str_replace( '.mp4', '', $public_id );
	}
}
