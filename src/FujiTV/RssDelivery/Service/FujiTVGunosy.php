<?php
/**
 * FujiTV Gunosy Feed
 *
 * @package FujiTV
 */

namespace FujiTV\RssDelivery\Service;

use FujiTV\Rss_Delivery;
use FujiTV\Theme\Assets;
use Tarosky\FeedGenerator\DeliveryManager;
use Tarosky\FeedGenerator\Service\Gunosy;
use WP_Query;

/**
 * FujiTVGUnosy用RSS
 */
class FujiTVGunosy extends Gunosy {

	/**
	 * 記事ごとの表示確認識別ID.
	 *
	 * @var string $id 識別ID.
	 */
	protected $id = 'gunosy';

	/**
	 * サービスごとの表示名.
	 *
	 * @var string $label 表示ラベル.
	 */
	protected $label = 'Gunosy';

	/**
	 * 表示順の優先度.
	 *
	 * @var int $order_priolity 表示優先度 大きいほうが優先度が高い.
	 */
	protected $order_priolity = 65;

	/**
	 * カテゴリー対応表.
	 *
	 * @var array $tag_category_list サイト内表記とサービス側の表記を配列.
	 */
	protected $tag_category_list = [
		[
			'service_category' => 'entertainment',
			'site_category'    => 'ドラマ・映画',
		],
		[
			'service_category' => 'entertainment',
			'site_category'    => 'バラエティ',
		],
		[
			'service_category' => 'entertainment',
			'site_category'    => '情報',
		],
		[
			'service_category' => 'entertainment',
			'site_category'    => 'アニメ',
		],
		[
			'service_category' => 'entertainment',
			'site_category'    => 'ピープル',
		],
		[
			'service_category' => 'column',
			'site_category'    => 'おでかけ・イベント',
		],
		[
			'service_category' => 'column',
			'site_category'    => 'ライフ',
		],
		[
			'service_category' => 'column',
			'site_category'    => 'ビューティー',
		],
		[
			'service_category' => 'sports',
			'site_category'    => 'スポーツ',
		],
		[
			'service_category' => 'omoshiro',
			'site_category'    => '井戸端会議',
		],
	];

	/**
	 * Feedを作り出す条件を指定する
	 *
	 * @return array $query_arg query_args.
	 */
	protected function get_query_arg() {
		$dm = DeliveryManager::instance();
		$id = $this->get_id();

		$query_arg = [
			'feed'          => 'gunosy',
			'posts_per_rss' => $this->per_page,
			'post_type'     => 'post',
			'post_status'   => 'publish',
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

		add_action( 'rss_add_channel', function() {
			Rss_Delivery\print_feed_copyright();
		} );

		/**
		 * ITEM
		 */
		add_action( 'rss2_item', function() {
			$set_category         = '';
			$search_category_list = [];

			// 本サイトの記事カテゴリーに対応するメディアカテゴリーを探し出す.
			foreach ( $this->tag_category_list as $tag_category ) {
				$search_category_list[] = $tag_category['site_category'];
			}
			$category_list = get_the_category();
			foreach ( $category_list as $cat ) {
				$index = array_search( $cat->name, $search_category_list );
				if ( false === $index ) {
					continue;
				}
				$set_category = $this->tag_category_list[ $index ]['service_category'];

				break;
			}
			if ( $set_category ) {
				printf( '<gnf:category>%s</gnf:category>', esc_html( $set_category ) );
			}
		} );

		$args = $this->get_query_arg();
		if ( ! empty( $args ) ) {
			foreach ( $args as $key => $val ) {
				$wp_query->set( $key, $val );
			}
		}
		add_action( 'do_feed_gunosy', [ $this, 'do_feed' ] );
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
