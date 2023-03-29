<?php
/**
 * パーマリンク関係を扱うファイル
 *
 * @package FujiTV
 */

namespace Tarosky\FeedGenerator;

use Tarosky\FeedGenerator\DeliveryManager;
use WP_Query;

/**
 * ルーターマネージャー
 */
class RouterManager {
	const PARAM_NAME_ID = 'delivery_id';

	/**
	 * コンストラクタ
	 */
	public function __construct() {
		add_filter( 'rewrite_rules_array', [ $this, 'rewrite_rules' ] );
		add_filter( 'query_vars', [ $this, 'query_vars' ] );
	}

	/**
	 * クエリバーを追加
	 *
	 * @param array $vars クエリバー.
	 * @return array
	 */
	public function query_vars( $vars ) {
		$vars[] = self::PARAM_NAME_ID;
		return $vars;
	}

	/**
	 * リライトルールを追加
	 *
	 * @param array $rules リライトルール.
	 * @return array
	 */
	public function rewrite_rules( $rules ) {
		$dm = DeliveryManager::instance();
		$service_identifiers = [];

		foreach ( $dm->get_services() as $service ) :
			$service_identifiers[] = $service->get_id();
		endforeach;

		$delivery_targets = implode( '|', $service_identifiers );
		$new_rules = [
			'^feed/(' . $delivery_targets . ')$' => sprintf( 'index.php?feed=rss&%s=$matches[1]', self::PARAM_NAME_ID ),
		];
		return array_merge( $new_rules, $rules );
	}

	/**
	 * 現在のページがプラグインで生成したFeedかどうか
	 *
	 * @param WP_Query $wp_query クエリ.
	 * @return boolean
	 */
	public function is_feed( WP_Query $wp_query ) {
		$is_feed = false;
		$service_identifiers = [];

		$dm = DeliveryManager::instance();

		foreach ( $dm->get_services() as $service ) :
			$service_identifiers[] = $service->get_id();
		endforeach;

		if ( $wp_query->is_feed && $wp_query->get( self::PARAM_NAME_ID ) && in_array( $wp_query->get( self::PARAM_NAME_ID ), $service_identifiers, true ) ) {
			$is_feed = true;
		}

		return $is_feed;
	}

	/**
	 * 現在対象とされているFeed_IDを返す
	 *
	 * @param \WP_Query $wp_query クエリ.
	 * @return string
	 */
	public function get_feed_id( WP_Query $wp_query ) {
		$ret_id = '';

		if ( $this->is_feed( $wp_query ) ) {
			$ret_id = $wp_query->get( self::PARAM_NAME_ID );
		}

		return $ret_id;
	}
}
