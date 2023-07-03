<?php
/**
 * Gunosy Feed
 *
 * @package HM\RSS_Delivery
 */

namespace HM\FeedGenerator\Service;

use HM\FeedGenerator\AbstractFeed;
use HM\FeedGenerator\DeliveryManager;
use WP_Query;

/**
 * Gunosy用RSS
 */
class Gunosy extends AbstractFeed {

	/**
	 * Feedを作り出す条件を指定する
	 *
	 * @return array $args query_args.
	 */
	protected function get_query_arg() {
		$dm = DeliveryManager::instance();
		$id = $this->get_id();

		$args = [
			'feed'          => 'gunosy',
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

		$this->xml_header();
		do_action( 'rss_tag_pre', 'rss2' );

		$logo_url      = $this->get_logo_url();
		$wide_logo_url = $this->get_logo_url( true );
		?>
		<rss version="2.0"
			xmlns:gnf="http://assets.gunosy.com/media/gnf"
			xmlns:content="http://purl.org/rss/1.0/modules/content/"
			xmlns:dc="http://purl.org/dc/elements/1.1/"
			xmlns:media="http://search.yahoo.com/mrss/"
			<?php
			do_action( 'rss2_ns' );
			?>
		>

			<channel>
				<title><?php wp_title_rss(); ?></title>
				<link><?php bloginfo_rss( 'url' ); ?></link>
				<description><?php bloginfo_rss( 'description' ); ?></description>
				<?php if ( $logo_url ) : ?>
					<image>
						<url><?php echo esc_url( $logo_url ); ?></url>
						<title><?php wp_title_rss(); ?></title>
						<link><?php bloginfo_rss( 'url' ); ?></link>
					</image>
				<?php endif; ?>
				<?php if ( $wide_logo_url ) : ?>
					<gnf:wide_image_link>
						<?php echo esc_url( $wide_logo_url ); ?>
					</gnf:wide_image_link>
				<?php endif; ?>
				<language><?php bloginfo_rss( 'language' ); ?></language>
				<lastBuildDate><?php echo esc_html( get_feed_build_date( 'r' ) ); ?></lastBuildDate>
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
		$content  = get_the_content_feed( 'rss2' );
		$status   = $post->post_status === 'publish' ? 'active' : 'deleted';
		$keywords = '';
		$tags     = get_the_tags();
		if ( $tags ) {
			foreach ( $tags as $tag ) {
				$keywords .= $keywords ? ',' : '';
				$keywords .= $tag->name;
			}
		}

		?>
		<item>
			<title><?php the_title_rss(); ?></title>
			<link><?php the_permalink_rss(); ?></link>
			<guid isPermaLink="false"><?php the_guid(); ?></guid>

			<?php if ( $keywords ) : ?>
				<gnf:keyword><?php echo esc_html( $keywords ); ?></gnf:keyword>
			<?php endif; ?>
			<description><![CDATA[<?php echo wp_kses_post( $content ); ?>]]></description>
			<content:encoded><![CDATA[<?php echo wp_kses_post( $content ); ?>]]></content:encoded>
			<media:status><?php echo esc_html( $status ); ?></media:status>
			<pubDate><?php echo esc_html( $this->to_local_time( get_the_time( 'Y-m-d H:i:s' ), 'r', 'Asia/Tokyo' ) ); ?></pubDate>
			<dc:creator><?php the_author(); ?></dc:creator>
			<gnf:modified><?php echo esc_html( $this->to_local_time( get_post_modified_time( 'Y-m-d H:i:s' ), 'r', 'Asia/Tokyo' ) ); ?></gnf:modified>
			<?php rss_enclosure(); ?>
			<?php
			do_action( 'rss2_item', [ $this, 'rss_add_item' ] );
			?>

		</item>

		<?php
	}

}
