<?php
/**
 * FujiTV Goo Video Feed
 *
 * @package FujiTV
 */

namespace FujiTV\RssDelivery\Service;

use const FujiTV\Video\CPT_SLUG;
use FujiTV\Rss_Delivery;
use Tarosky\FeedGenerator\DeliveryManager;
use Tarosky\FeedGenerator\Service\GooVideo;
use WP_Query;

/**
 * RSS for Fuji TV Goo
 */
class FujiTVGooVideo extends GooVideo {

	/**
	 * Display confirmation identification ID for each article.
	 *
	 * @var string $id Identification ID.
	 */
	protected $id = 'goovideo';

	/**
	 * Display name for each service.
	 *
	 * @var string $label Label.
	 */
	protected $label = 'Goo Video';

	/**
	 * Display order priority.
	 *
	 * @var int $order_priority Display priority, the higher the priority.
	 */
	protected $order_priolity = 50;

	/**
	 * Generate and return each item of Feed
	 *
	 * @param \WP_Post $post Post Object.
	 */
	protected function render_item( $post ) {
		parent::render_item( $post );
	}

	/**
	 * Specify the conditions to create a feed
	 *
	 * @return array $query_arg query_args.
	 */
	protected function get_query_arg() {
		$dm = DeliveryManager::instance();
		$id = $this->get_id();

		$query_arg = [
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

		return $query_arg;
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
		// lastBuildDateの時間をローカルタイムゾーンに合わせる.
		add_filter( 'get_feed_build_date', function( $max_modified_time, $format ) {
			return $this->to_local_time( $max_modified_time, $format, 'Asia/Tokyo', true );
		}, 10, 2 );

		add_action( 'rss_add_channel', function() {
			Rss_Delivery\print_feed_copyright();
		} );

		remove_action( 'rss2_head', 'rss2_site_icon' );

		$args = $this->get_query_arg();
		if ( ! empty( $args ) ) {
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
		parent::do_feed();
	}
}
