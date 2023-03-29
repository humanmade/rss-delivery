<?php
/**
 * FujiTV TV Dogatch Feed
 *
 * @package FujiTV
 */

namespace FujiTV\RssDelivery\Service;

use FujiTV\Rss_Delivery;
use FujiTV\Theme\Assets;
use Tarosky\FeedGenerator\DeliveryManager;
use Tarosky\FeedGenerator\Service\Dogatch;
use WP_Query;

/**
 * FujiTV TV Dogatch用RSS
 */
class FujiTVDogatch extends Dogatch {

	/**
	 * 記事ごとの表示確認識別ID.
	 *
	 * @var string $id 識別ID.
	 */
	protected $id = 'dogatch';

	/**
	 * サービスごとの表示名.
	 *
	 * @var string $label 表示ラベル.
	 */
	protected $label = 'TV Dogatch';

	/**
	 * 表示順の優先度.
	 *
	 * @var int $order_priolity 表示優先度 大きいほうが優先度が高い.
	 */
	protected $order_priolity = 95;

	/**
	 * Feedを作り出す条件を指定する
	 *
	 * @return array $query_arg query_args.
	 */
	protected function get_query_arg() {
		$dm = DeliveryManager::instance();
		$id = $this->get_id();

		$query_arg = [
			'feed'          => 'dogatch',
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

		return $query_arg;
	}

	/**
	 * クエリの上書き
	 *
	 * @param WP_Query $wp_query クエリ.
	 */
	public function pre_get_posts( WP_Query &$wp_query ) {

		/**
		 * CHANNEL
		 */
		// lastBuildDateの時間をローカルタイムゾーンに合わせる.
		add_filter( 'get_feed_build_date', function( $max_modified_time, $format ) {
			return $this->to_local_time( $max_modified_time, $format, 'Asia/Tokyo', true );
		}, 10, 2 );

		remove_action( 'rss2_head', 'rss2_site_icon' );

		$args = $this->get_query_arg();
		if ( ! empty( $args ) ) {
			foreach ( $args as $key => $val ) {
				$wp_query->set( $key, $val );
			}
		}
		add_action( 'do_feed_dogatch', [ $this, 'do_feed' ] );
	}

	/**
	 * フィルター・フックで対応しきれない場合はfeed全体を作り直す
	 * rss2.0をベースに作成
	 */
	public function do_feed() {
		parent::do_feed();
	}

	/**
	 * Feedの一つ一つのアイテムを生成して返す
	 *
	 * @param \WP_Post $post 投稿記事.
	 */
	protected function render_item( $post ) {
		parent::render_item( $post );
	}
}
