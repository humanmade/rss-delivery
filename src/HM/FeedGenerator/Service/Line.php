<?php
/**
 * LINE Feed
 *
 * @package HM\RSS_Delivery
 */

namespace HM\FeedGenerator\Service;

use HM\FeedGenerator\AbstractFeed;
use HM\FeedGenerator\DeliveryManager;
use WP_Query;

/**
 * LINE用RSS
 */
class Line extends AbstractFeed {

	/**
	 * Current Post ID.
	 *
	 * @var int
	 */
	protected static $post_id;

	/**
	 * Feedを作り出す条件を指定する
	 *
	 * @return array
	 */
	protected function get_query_arg() {
		$dm = DeliveryManager::instance();
		$id = $this->get_id();

		$args = [
			'feed'          => 'line',
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

		return $args;
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

		/**
		 * ITEM
		 */
		// guidタグ内容を編集.
		add_filter( 'the_guid', function( $guid, $id ) {
			return get_permalink( $id );
		}, 10, 2 );

		$args = $this->get_query_arg();
		if ( $args ) {
			foreach ( $args as $key => $val ) {
				$wp_query->set( $key, $val );
			}
		}
		add_action( 'do_feed_line', [ $this, 'do_feed' ] );
	}

	/**
	 * フィルター・フックで対応しきれない場合はfeed全体を作り直す
	 * rss2.0をベースに作成
	 */
	public function do_feed() {
		$this->xml_header();

		do_action( 'rss_tag_pre', 'rss2' );
		?>
		<rss version="2.0"
			xmlns:oa="http://news.line.me/rss/1.0/oa"
			<?php
			do_action( 'rss2_ns' );
			?>
		>

		<channel>
			<title><![CDATA[<?php wp_title_rss(); ?>]]></title>
			<link><?php bloginfo_rss( 'url' ); ?></link>
			<lastBuildDate><?php echo esc_html( get_feed_build_date( 'r' ) ); ?></lastBuildDate>
			<description><![CDATA[<?php bloginfo_rss( 'description' ); ?>]]></description>
			<language><?php bloginfo_rss( 'language' ); ?></language>
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
	 * Feedの一つ一つのアイテムを生成して返す
	 *
	 * @param \WP_Post $post 投稿記事.
	 */
	protected function render_item( $post ) {
		$content = $this->parse_content( $post->post_content, 'rss2' );
		$status  = $post->post_status === 'publish' ? '2' : '0';

		self::$post_id = (int) $post->ID;

		?>
			<item>
				<guid><?php the_guid(); ?></guid>
				<title><![CDATA[<?php the_title_rss(); ?>]]></title>
				<link><?php the_permalink_rss(); ?></link>
				<?php $this->the_rss_enclosure( self::$post_id ); ?>
				<description><![CDATA[<?php echo wp_kses_post( $content ); ?>]]></description>
				<pubDate><?php echo esc_html( $this->to_local_time( get_the_time( 'Y-m-d H:i:s' ), 'r', 'Asia/Tokyo' ) ); ?></pubDate>
				<oa:lastPubDate><?php echo esc_html( $this->to_local_time( get_post_modified_time( 'Y-m-d H:i:s' ), 'r', 'Asia/Tokyo' ) ); ?></oa:lastPubDate>
				<oa:pubStatus><?php echo esc_html( $status ); ?></oa:pubStatus>

				<?php
					do_action( 'rss2_item', [ $this, 'rss_add_item' ] );
				?>

			</item>

		<?php
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

			$core_embed_blocks = [
				'core-embed/twitter',
				'core-embed/youtube',
				'core-embed/instagram',
			];

			if ( in_array( $parsed_block['blockName'], $core_embed_blocks, true ) ) {
				$parsed_content .= $parsed_block['innerHTML'];
				continue;
			}

			// Remove Anchor Tag from other blocks except core-embed ones.
			$parsed_content .= preg_replace( '#<a.*?>([^>]*)</a>#i', '$1', $parsed_block['innerHTML'] );
		}

		/** This filter is documented in wp-includes/post-template.php */
		$parsed_content = apply_filters( 'the_content', $parsed_content );
		$parsed_content = str_replace( ']]>', ']]&gt;', $parsed_content );

		/** This filter is documented in wp-includes/feed.php */
		return apply_filters( 'the_content_feed', $parsed_content, $feed_type );
	}
}
