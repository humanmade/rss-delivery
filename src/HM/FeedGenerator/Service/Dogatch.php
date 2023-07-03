<?php
/**
 * TV Dogatch Feed
 *
 * @package HM\RSS_Delivery
 */

namespace HM\FeedGenerator\Service;

use HM\FeedGenerator\AbstractFeed;
use HM\FeedGenerator\DeliveryManager;
use WP_Query;

/**
 * TV Dogatch用RSS
 */
class Dogatch extends AbstractFeed {

	/**
	 * Feedを作り出す条件を指定する
	 *
	 * @return array args query_args.
	 */
	protected function get_query_arg() {
		$dm = DeliveryManager::instance();
		$id = $this->get_id();

		$args = [
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
		add_action( 'do_feed_dogatch', [ $this, 'do_feed' ] );
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
			xmlns:dgf="http://dogatch.jp/media/dgf"
			xmlns:content="http://purl.org/rss/1.0/modules/content/"
			xmlns:dcterms="http://purl.org/dc/terms/"
			<?php
			do_action( 'rss2_ns' );
			?>
		>

		<channel>
			<language><?php bloginfo_rss( 'language' ); ?></language>
			<title><?php wp_title_rss(); ?></title>
			<dgf:shortTitle><?php wp_title_rss(); ?></dgf:shortTitle>
			<link><?php bloginfo_rss( 'url' ); ?></link>
			<description><?php bloginfo_rss( 'description' ); ?></description>
			<lastBuildDate><?php echo esc_html( $date ); ?></lastBuildDate>
			<gaTrackingId>UA-148738433-1</gaTrackingId>
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
		$status  = $post->post_status === 'publish' ? 'active' : 'deleted';
		$content = get_the_content_feed( 'rss2' );

		// Changed the format of img and caption - <div class="innerpic"><img><span class="caption">caption</span></div>.
		$content = preg_replace( '/<a class="image-anchor[^>]*>.*?<\/a>/ims', '', $content );
		$content = preg_replace( '/<span class="c-thumb__icon[^>]*>.*?<\/span>/ims', '', $content );
		$content = preg_replace( '/<span class="image-wrapper[^>]*>(.*?)<\/span>/ims', '$1', $content );
		$content = preg_replace( '/<figcaption>(.*?)<\/figcaption>/ims', '<span class="caption">$1</span>', $content );
		$content = preg_replace( '/<figure class="wp-block-image[^>]*>(.*?)<\/figure>/ims', '<div class="innerpic">$1</div>', $content );

		// Changed the format of embed Youtube - <div class="player"><iframe></iframe></div>.
		$content = preg_replace( '/<figure class="wp-block-embed is-type-video is-provider-youtube[^>]*><div [^>]*>(.*?)<\/div><\/figure>/ims', '<div class="player">$1</div>', $content );

		$get_post_terms_args = [
			'number' => 4,
			'fields' => 'names',
		];
		$cat_name_list       = wp_get_post_terms( $post->ID, 'category', $get_post_terms_args );

		$thumbnail_url  = '';
		$thumbnail_type = '';
		$thumbnail_id   = get_post_thumbnail_id( $post->ID );
		if ( $thumbnail_id ) {
			$thumbnail_url  = get_the_post_thumbnail_url( null, 'landscape_wide' );
			$thumbnail_type = get_post_mime_type( $thumbnail_id );
		}
		?>
			<item>
				<guid><?php echo esc_html( $post->ID ); ?></guid>
				<title><![CDATA[<?php the_title_rss(); ?>]]></title>
				<link><?php the_permalink_rss(); ?></link>
				<pubDate><?php echo esc_html( $this->to_local_time( get_the_time( 'Y-m-d H:i:s' ), 'r', 'Asia/Tokyo' ) ); ?></pubDate>
				<lastBuildDate><?php echo esc_html( $this->to_local_time( get_the_modified_date( 'Y-m-d H:i:s' ), 'r', 'Asia/Tokyo' ) ); ?></lastBuildDate>
				<?php if ( 'deleted' === $status ) : ?>
					<dgf:deleteFlag>true</dgf:deleteFlag>
				<?php endif; ?>
				<?php
				if ( $cat_name_list ) :
					foreach ( $cat_name_list as $cat_name ) :
						?>
					<category><?php echo esc_html( $cat_name ); ?></category>
						<?php
					endforeach;
				endif;
				?>
				<dgf:page no="1">
					<description><![CDATA[<?php the_excerpt_rss(); ?>]]></description>
					<content:encoded><![CDATA[<?php echo wp_kses_post( $content ); ?>]]></content:encoded>
					<?php if ( $thumbnail_id ) : ?>
						<enclosure url="<?php echo esc_url( convert_chars( $thumbnail_url ) ); ?>" type="<?php echo esc_attr( $thumbnail_type ); ?>" />
					<?php endif; ?>
				</dgf:page>
				<?php
					do_action( 'rss2_item', [ $this, 'rss_add_item' ] );
				?>

			</item>

		<?php
	}
}
