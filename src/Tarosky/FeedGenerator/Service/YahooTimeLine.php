<?php
/**
 * Yahoo Time Line Feed
 *
 * @package HM\RSS_Delivery
 */

namespace Tarosky\FeedGenerator\Service;

use Tarosky\FeedGenerator\AbstractFeed;
use Tarosky\FeedGenerator\DeliveryManager;
use WP_Query;

/**
 * Yahoo Time Line用RSS
 */
class YahooTimeLine extends AbstractFeed {

	/**
	 * 記事ごとの表示確認識別ID.
	 *
	 * @var string $id 識別ID.
	 */
	protected $id = 'yahoo-tl';

	/**
	 * 表示件数.
	 *
	 * @var int $per_page 表示件数.
	 */
	protected $per_page = 30;

	/**
	 * Feedを作り出す条件を指定する
	 *
	 * @return array args query_args.
	 */
	protected function get_query_arg() {
		$dm = DeliveryManager::instance();
		$id = $this->get_id();

		$args = [
			'feed'          => 'yahoo-tl',
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
	 * @param \WP_Query $wp_query クエリ.
	 */
	public function pre_get_posts( WP_Query &$wp_query ) {

		/**
		 * ITEM
		 */
		$args = $this->get_query_arg();
		if ( ! empty( $args ) ) {
			foreach ( $args as $key => $val ) {
				$wp_query->set( $key, $val );
			}
		}
		add_action( 'do_feed_yahoo-tl', [ $this, 'do_feed' ] );
	}

	/**
	 * フィルター・フックで対応しきれない場合はfeed全体を作り直す
	 * rss2.0をベースに作成
	 */
	public function do_feed() {
		$this->xml_header();

		$date = $this->to_local_time( '', 'r', 'Asia/Tokyo' );
		do_action( 'rss_tag_pre', 'rss2' );
		?>
		<rss version="2.0"
			xmlns:yj="http://cmspf.yahoo.co.jp/rss"
			yj:version="1.0"
			<?php
			do_action( 'rss2_ns' );
			?>
		>

		<channel>
			<title><?php wp_title_rss(); ?></title>
			<link><?php bloginfo_rss( 'url' ); ?></link>
			<description><?php bloginfo_rss( 'description' ); ?></description>
			<lastBuildDate><?php echo esc_html( $date ); ?></lastBuildDate>
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
		$content = preg_replace( '/<a class="image-anchor[^>]*>.*?<\/a>/ims', '', $content );
		$content = preg_replace( '/<span class="c-thumb__icon[^>]*>.*?<\/span>/ims', '', $content );
		$content = preg_replace( '/<span class="image-wrapper[^>]*>(.*?)<\/span>/ims', '$1', $content );
		$content = preg_replace( '/<figcaption>(.*?)<\/figcaption>/ims', '<cite>$1</cite>', $content );
		$content = preg_replace( '/<figure class="wp-block-image[^>]*>(.*?)<\/figure>/ims', '$1', $content );
		$content = preg_replace( '/<figure class="wp-block-embed[^>]*>(.*?)<\/figure>/ims', '$1', $content );
		$content = preg_replace( '/<div class="wp-block-embed__wrapper[^>]*>(.*?)<\/div>/ims', '$1', $content );

		$pubdate = '';
		if ( $post->post_status === 'publish' ) {
			$pubdate_time = $this->to_local_time( get_the_time( 'Y-m-d H:i:s' ), 'r', 'Asia/Tokyo' );
			$pubdate_mod = $this->to_local_time( get_the_modified_date( 'Y-m-d H:i:s' ), 'r', 'Asia/Tokyo' );
			if ( strtotime( $pubdate_time ) > strtotime( $pubdate_mod ) ) {
				$pubdate = $pubdate_time;
			} else {
				$pubdate = $pubdate_mod;
			}
		}
		$thumbnail_id = get_post_thumbnail_id( $post->ID );
		?>
			<item>
				<title><?php the_title_rss(); ?></title>
				<link><?php the_permalink_rss(); ?></link>
				<category>trend</category>
				<guid><?php echo esc_html( get_the_ID() ); ?></guid>
				<pubDate><?php echo esc_html( $pubdate ); ?></pubDate>
				<description><![CDATA[<?php echo wp_kses_post( $content ); ?>]]></description>
				<?php
				if ( $thumbnail_id ) {
					printf(
						'<enclosure url="%1$s" length="%2$d" type="%3$s" />',
						esc_url( get_the_post_thumbnail_url( null, 'landscape_wide' ) ),
						esc_attr( filesize( get_attached_file( $thumbnail_id ) ) ),
						esc_attr( get_post_mime_type( $thumbnail_id ) )
					);
				}
				?>
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

			$parsed_content .= $parsed_block['innerHTML'];
		}

		/** This filter is documented in wp-includes/post-template.php */
		$parsed_content = apply_filters( 'the_content', $parsed_content );
		$parsed_content = str_replace( ']]>', ']]&gt;', $parsed_content );

		/** This filter is documented in wp-includes/feed.php */
		return apply_filters( 'the_content_feed', $parsed_content, $feed_type );
	}

}