<?php
/**
 * Human Made RSS Delivery - Site Feed
 *
 * @package HM\RSS_Delivery
 */

namespace HM\RSS_Delivery\Service;

use HM\FeedGenerator\DeliveryManager;
use HM\FeedGenerator\Service\Main;
use WP_Query;

/**
 * HM Main 用RSS
 */
class HMMain extends Main {

	/**
	 * 記事ごとの表示確認識別ID.
	 *
	 * @var string $id 識別ID.
	 */
	protected $id = 'main';

	/**
	 * サービスごとの表示名.
	 *
	 * @var string $label 表示ラベル.
	 */
	protected $label = 'Main';

	/**
	 * 表示順の優先度.
	 *
	 * @var int $order_priolity 表示優先度 大きいほうが優先度が高い.
	 */
	protected $order_priolity = 100;

	/**
	 * Feedを作り出す条件を指定する
	 *
	 * @return array $query_arg query_args.
	 */
	protected function get_query_arg() {
		$dm = DeliveryManager::instance();
		$id = $this->get_id();

		$query_arg = [
			'feed'          => 'main',
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
		parent::pre_get_posts( $wp_query );
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
