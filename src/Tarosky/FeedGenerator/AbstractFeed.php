<?php
/**
 * RSS基本動作抽象クラス
 *
 * @package FujiTV
 */

namespace Tarosky\FeedGenerator;

use DateTime;
use DateTimeZone;
use Tarosky\FeedGenerator\Model\Singleton;
use WP_Query;

/**
 * RSS用基盤抽象クラス
 */
abstract class AbstractFeed extends Singleton {

	/**
	 * 記事ごとの表示確認識別ID.
	 *
	 * @var string $id 識別ID.
	 */
	protected $id = '';

	/**
	 * サービスごとの表示名.
	 *
	 * @var string $label 表示ラベル.
	 */
	protected $label = '';

	/**
	 * 表示件数.
	 *
	 * @var int $per_page 表示件数.
	 */
	protected $per_page = 20;

	/**
	 * 表示順の優先度.
	 *
	 * @var int $order_priolity 表示優先度 大きいほうが優先度が高い.
	 */
	protected $order_priolity = 1;

	/**
	 * Constructor.
	 *
	 * @param array $settings Setting array.
	 */
	protected function __construct( $settings = [] ) {
		add_filter( 'the_content_feed', [ $this, 'remove_nextpage' ] );
	}

	/**
	 * Feedを作り出す条件を指定する
	 *
	 * @return array WP_Query に渡す配列
	 */
	abstract protected function get_query_arg();

	/**
	 * Feedの一つ一つのアイテムを生成して返す
	 *
	 * @param \WP_Post $post 投稿記事.
	 */
	abstract protected function render_item( $post );

	/**
	 * 配信する本文から <!--nextpage--> を取り除く
	 *
	 * @param string $content 本文.
	 *
	 * @return string
	 */
	public function remove_nextpage( $content ) {
		return str_replace( '<p><!--nextpage--></p>', '', $content );
	}

	/**
	 * GMTの日付を特定のタイムゾーンに合わす
	 *
	 * @param string  $date 日付.
	 * @param string  $format フォーマット.
	 * @param string  $local_timezone 指定タイムゾーン デフォルトは設定タイムゾーン.
	 * @param boolean $is_gmt 引き渡す日付がgmtか.
	 *
	 * @return string
	 */
	protected function to_local_time( $date, $format, $local_timezone = '', $is_gmt = false ) {
		if ( ! $local_timezone ) {
			$local_timezone = get_option( 'timezone_string' );
		}
		if ( $local_timezone ) {
			if ( $is_gmt ) {
				$date = new DateTime( $date );
				$date->setTimeZone( new DateTimeZone( $local_timezone ) );
				return $date->format( $format );
			} else {
				$date = new DateTime( $date, new DateTimeZone( $local_timezone ) );
				return $date->format( $format );
			}
		} else {
			return $date;
		}
	}

	/**
	 * XMLヘッダーを吐く
	 *
	 * @param int $hours 期限, デフォルトは1時間.
	 */
	protected function xml_header( $hours = 1 ) {
		if ( $hours ) {
			$this->expires_header( $hours );
		}
		header( 'Content-Type: text/xml; charset=' . get_option( 'blog_charset' ), true );
		echo '<?xml version="1.0" encoding="' . esc_attr( get_option( 'blog_charset' ) ) . '"?>' . "\n";

	}

	/**
	 * Expiresヘッダーを吐く
	 *
	 * @param int $hours 期限, デフォルトは1時間.
	 */
	protected function expires_header( $hours = 1 ) {
		$time = current_time( 'timestamp', true ) + 60 * 60 * $hours;
		header( 'Expires: ' . gmdate( 'D, d M Y H:i:s', $time ) . ' GMT' );
	}

	/**
	 * IDを取得する。
	 *
	 * @return string
	 */
	public function get_id() {
		$class_name = explode( '\\', get_called_class() );
		return $this->id ? $this->id : strtolower( end( $class_name ) );
	}

	/**
	 * サービスの表示名を取得する。各サービスでlabelに指定がされている場合はそれを使用。無ければクラス名が使われる。
	 *
	 * @return string
	 */
	public function get_label() {
		$class_name = explode( '\\', get_called_class() );
		return $this->label ? $this->label : end( $class_name );
	}

	/**
	 * 表示優先度の値を返す。
	 *
	 * @return int
	 */
	public function get_priolity() {
		return $this->order_priolity;
	}

	/**
	 * クエリの上書き
	 *
	 * @param WP_Query $wp_query クエリ.
	 */
	public function pre_get_posts( WP_Query &$wp_query ) {
		$args = $this->get_query_arg();
		if ( $args ) {
			foreach ( $args as $key => $val ) {
				$wp_query->set( $key, $val );
			}
		}
	}

	/**
	 * 設定したカスタムロゴURLを返す
	 *
	 * @param bool $is_rectangle Whether it is rectangular.
	 * @return string $logo_url ロゴURL.
	 */
	public function get_logo_url( $is_rectangle = false ) {
		$logo_url = '';
		if ( has_custom_logo() ) {
			$logo     = get_theme_mod( 'custom_logo' );
			$logo_url = wp_get_attachment_url( $logo );
		}
		return $logo_url;
	}

	/**
	 * Get Feature Image url and pass as enclosure.
	 *
	 * @param int $post_id Current Post ID.
	 *
	 * @return void
	 */
	public function the_rss_enclosure( int $post_id ) : void {
		$image_id = get_post_thumbnail_id( $post_id );

		// No Featured Image is Set.
		if ( empty( $image_id ) ) {
			return;
		}

		$image = wp_get_attachment_image_url( $image_id, 'landscape_wide' );

		// Did not get Image from the Image ID.
		if ( empty( $image ) ) {
			return;
		}

		printf(
			'<enclosure url="%s" type="%s"/>',
			esc_url( $image ),
			esc_attr( get_post_mime_type( $image_id ) )
		);
	}

	/**
	 * Get Posts by related topics.
	 *
	 * @param int          $post_id        Current Post ID.
	 * @param array|string $related_topics Related_topics for the post.
	 * @param int          $posts_per_page Number of related posts per post.
	 *
	 * @return array
	 */
	public function get_related_topics_posts( int $post_id, $related_topics, int $posts_per_page ) : array {
		// Exclude Current Post ID.
		$exclude_posts = [ $post_id ];

		if ( empty( $related_topics ) ) {
			return $this->get_latest_posts( $posts_per_page, $exclude_posts );
		}

		$query = new WP_Query(
			[
				'fields'         => 'ids',
				'tag__in'        => $related_topics,
				'posts_per_page' => $posts_per_page,
				'post__not_in'   => $exclude_posts,
			]
		);

		if ( ! $query->have_posts() ) {
			return $this->get_latest_posts( $posts_per_page, $exclude_posts );
		}

		if ( $query->post_count === $posts_per_page ) {
			return $query->posts;
		}

		$posts = $query->posts;
		$latest_posts = $this->get_latest_posts(
			(int) ( $posts_per_page - $query->post_count ),
			array_merge( $posts, $exclude_posts )
		);

		return array_merge( $posts, $latest_posts );
	}

	/**
	 * Get Latest post when related posts does not have enough posts.
	 *
	 * @param int   $count         Number of latest posts to get.
	 * @param array $exclude_posts Post Ids which are already present.
	 *
	 * @return array
	 */
	public function get_latest_posts( int $count = 0, array $exclude_posts = [] ) : array {
		if ( empty( $count ) ) {
			return [];
		}

		$query = new WP_Query( [
			'fields'         => 'ids',
			'posts_per_page' => $count,
			'post__not_in'   => $exclude_posts,
		] );

		if ( ! $query->have_posts() ) {
			return [];
		}

		return $query->posts;
	}
}
