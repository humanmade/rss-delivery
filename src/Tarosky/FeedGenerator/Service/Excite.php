<?php
/**
 * LINE Feed
 *
 * @package FujiTV
 */

namespace Tarosky\FeedGenerator\Service;

use Tarosky\FeedGenerator\AbstractFeed;
use Tarosky\FeedGenerator\DeliveryManager;
use WP_Query;

/**
 * RSS for Excite
 *
 * @package Tarosky\FeedGenerator\Service
 */
class Excite extends AbstractFeed {

	/**
	 * Specify the conditions to create a feed
	 *
	 * @return array
	 */
	protected function get_query_arg() {
		$dm = DeliveryManager::instance();
		$id = $this->get_id();

		return [
			'feed'          => 'excite',
			'posts_per_rss' => $this->per_page,
			'post_type'     => 'post',
			'post_status'   => [ 'publish', 'trash' ],
			'orderby'       => [
				'date' => 'DESC',
			],
			'meta_query'    => [ // phpcs:disable WordPress.DB.SlowDBQuery.slow_db_query_meta_query
				[
					'key'     => $dm->get_meta_name(),
					'value'   => sprintf( '"%s"', $id ),
					'compare' => 'REGEXP',
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
		// Edit guid tag content.
		add_filter( 'the_guid', function( $guid, $id ) {
			return get_permalink( $id );
		}, 10, 2 );

		$args = $this->get_query_arg();
		if ( $args ) {
			foreach ( $args as $key => $val ) {
				$wp_query->set( $key, $val );
			}
		}
		add_action( 'do_feed_excite', [ $this, 'do_feed' ] );
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
			<?php do_action( 'rss2_ns' ); ?>
		>

			<channel>
				<title><?php wp_title_rss(); ?></title>
				<link><?php bloginfo_rss( 'url' ); ?></link>
				<language><?php bloginfo_rss( 'language' ); ?></language>
				<description><?php bloginfo_rss( 'description' ); ?></description>
				<lastBuildDate><?php echo esc_html( get_feed_build_date( 'r' ) ); ?></lastBuildDate>
				<?php
				do_action( 'rss_add_channel', [ $this, 'rss_add_channel' ] );

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
	 * Feedの一つ一つのアイテムを生成して返す
	 *
	 * @param \WP_Post $post 投稿記事.
	 */
	protected function render_item( $post ) {
		$content = get_the_content_feed( 'rss2' );
		/**
		 * Excite Specific requirement - `Set <br /> for line breaks and <br /> <br /> for paragraph breaks.`
		 *
		 * @link https://publishers-support.excite.co.jp/rss-spec-exnews-laurier/
		 */
		$content = str_replace( '</p>', '</p><br/><br/>', $content );
		?>
		<item>
			<title><?php the_title_rss(); ?></title>
			<link><?php the_permalink_rss(); ?></link>
			<guid isPermaLink="false"><?php the_guid(); ?></guid>
			<description><![CDATA[<?php the_excerpt(); ?>]]></description>
			<content:encoded><![CDATA[<?php echo wp_kses_post( $content ); ?>]]></content:encoded>
			<pubDate><?php echo esc_html( $this->to_local_time( get_the_time( 'Y-m-d H:i:s' ), 'r', 'Asia/Tokyo' ) ); ?></pubDate>
			<lastPubDate><?php echo esc_html( $this->to_local_time( get_the_modified_time( 'Y-m-d H:i:s' ), 'r', 'Asia/Tokyo' ) ); ?></lastPubDate>
			<status><?php echo esc_html( get_the_time() === get_the_modified_time() ? 0 : 1 ); ?></status>
			<?php
			/**
			 * Hard-coding the category, since we do not have dynamic categories as required by Excite
			 *
			 * @link https://publishers-support.excite.co.jp/rss-spec-exnews-laurier/
			 */
			?>
			<category><![CDATA[<?php esc_html_e( 'entertainment', 'fujitv' ); ?>]]></category>
			<?php $this->the_tags(); ?>
			<?php
			do_action( 'rss2_item', [ $this, 'rss_add_item' ] );
			?>
		</item>
		<?php
	}

	/**
	 * Render tags for the post.
	 *
	 * @return void
	 */
	protected function the_tags() : void {
		$tags = get_the_tags();
		if ( empty( $tags ) || is_wp_error( $tags ) ) {
			return;
		}

		$i = 0;

		foreach ( $tags as $tag ) {
			if ( $i >= 5 ) {
				continue;
			}
			printf( '<tags><![CDATA[%s]]></tags>', esc_html( $tag->name ) );
			$i++;
		}
	}
}
