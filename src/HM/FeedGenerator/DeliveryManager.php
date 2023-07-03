<?php
/**
 * Feed配信関係の処理群
 *
 * @package HM\RSS_Delivery
 */

namespace HM\FeedGenerator;

use HM\FeedGenerator\Model\Singleton;
use HM\FeedGenerator\RouterManager;
use WP_Query;

/**
 * Feed配信関係の処理群クラス
 */
class DeliveryManager extends Singleton {

	/**
	 * ルーターマネージャーオブジェクト.
	 *
	 * @var Object $router ルーターマネージャークラス.
	 */
	protected $router = null;

	/**
	 * Feed用保存メタ名.
	 *
	 * @var string $meta_name 保存メタ名.
	 */
	protected $meta_name = 'trs_feed_selected';

	/**
	 * 配信先一覧.
	 *
	 * @var array $services 配信先一覧.
	 */
	protected $services = [];

	/**
	 * コンストラクタ
	 *
	 * @param array $settings 設定配列.
	 */
	public function __construct( array $settings ) {
		$this->router = new RouterManager();

		add_action( 'admin_menu', [ $this, 'set_admin_menu' ] );
		add_action( 'save_post', [ $this, 'save_custom_postdata' ] );
		add_action( 'pre_get_posts', [ $this, 'pre_get_posts' ] );
		add_action( 'admin_enqueue_scripts', [ $this, 'admin_enqueue' ] );
	}

	/**
	 * 投稿画面用の必要ファイルを読み込む.
	 *
	 * @param string $hook_suffix 現在の管理ページ.
	 */
	public function admin_enqueue( $hook_suffix ) {
		if ( 'post.php' !== $hook_suffix && 'post-new.php' !== $hook_suffix ) {
			return;
		}

		wp_enqueue_style( 'trs-feed-generator', plugins_url( '/FeedGenerator/Assets/css/main.css', __DIR__ ) );
		wp_enqueue_script( 'trs-feed-generator', plugins_url( '/FeedGenerator/Assets/js/main.js', __DIR__ ), [ 'jquery' ] );
	}

	/**
	 * Feed表示情報を保存してあるmeta_nameを返す。
	 *
	 * @return string
	 */
	public function get_meta_name() {
		return $this->meta_name;
	}

	/**
	 * Admin_menu
	 */
	public function set_admin_menu() {
		add_meta_box(
			'trs_feed_checkbox',
			__( 'Feed Check', 'hm-rssdelivery' ),
			[ $this, 'callback_checkbox_template' ],
			'post',
			'side',
			'low'
		);
		add_meta_box(
			'trs_feed_checkbox',
			__( 'Feed Check', 'hm-rssdelivery' ),
			[ $this, 'callback_checkbox_template' ],
			'video',
			'side',
			'low'
		);
	}

	/**
	 * チェックボックテンプレート
	 */
	public function callback_checkbox_template() {
		global $pagenow;

		$options = [];
		foreach ( $this->get_services() as $service ) {
			$id = $service->get_id();
			$label = $service->get_label();

			$options[ $id ] = $label;
		}

		$meta_name = $this->meta_name;

		$id = get_the_ID();

		// カスタムフィールドの値を取得.
		// 値がなければarray()を設定する.
		$checked_list = get_post_meta( $id, $meta_name, true );
		$checked_list = $checked_list ? $checked_list : [];

		if ( $options ) {
			foreach ( $options as $id => $label ) {
				$is_checked = '';
				if ( 'post-new.php' === $pagenow || false !== array_search( $id, $checked_list ) ) {
					$is_checked = 'checked';
				}

				ob_start();
				?>
				<div>
					<label>
						<input
							type="checkbox"
							name="<?php echo esc_attr( $meta_name ) ?>[]"
							value="<?php echo esc_attr( $id ); ?>"
							<?php echo esc_attr( $is_checked ); ?> >
						<?php echo esc_html( $label ); ?>
					</label>
				</div>
				<?php
				echo wp_kses(
					ob_get_clean(),
					[
						'div'   => [],
						'label' => [],
						'input' => [
							'type'    => [],
							'name'    => [],
							'value'   => [],
							'checked' => [],
						],
					]
				);
			}
		}
	}

	/**
	 * 記事保存時にチェックボックス内容を保存する
	 *
	 * @param int $post_id 記事ID.
	 */
	public function save_custom_postdata( $post_id ) {
		if ( ! empty( $_POST ) ) {
			$meta_name = $this->meta_name;

			// 入力した値(postされた値).
			$set_data = isset( $_POST[ $meta_name ] ) ? $_POST[ $meta_name ] : null;

			// DBに登録してあるデータ.
			$seted_data = get_post_meta( $post_id, $meta_name, true );

			if ( $set_data ) {
				update_post_meta( $post_id, $meta_name, $set_data );
			} else {
				delete_post_meta( $post_id, $meta_name, $seted_data );
			}
		}
	}

	/**
	 * サービスの取得
	 *
	 * @param string $id 識別ID.
	 * @return object
	 */
	public function get_service_by_id( $id ) {
		$ret_service = null;
		foreach ( $this->get_services() as $service ) {
			if ( $id === $service->get_id() ) {
				$ret_service = $service;
			}
		}
		return $ret_service;
	}

	/**
	 * サービス一覧の取得
	 *
	 * @return array
	 */
	public function get_services() {
		$ret_services = $this->services;
		if ( ! $ret_services ) {
			$all_dir = sprintf( '%s../../', plugin_dir_path( __FILE__ ) );
			if ( is_dir( $all_dir ) ) {
				$set_services = [];
				$this->services = [];
				foreach ( scandir( $all_dir ) as $base_project ) {
					if ( preg_match( '/^(\.|\.\.)$/', $base_project, $match ) ) {
						continue;
					}
					$base_dir = sprintf( '%s%s', $all_dir, $base_project );
					foreach ( scandir( $base_dir ) as $base_name ) {
						if ( preg_match( '/^(\.|\.\.)$/', $base_name, $match ) ) {
							continue;
						}
						$dir = sprintf( '%s%s/%s/%s', $all_dir, $base_project, $base_name, 'Service' );
						if ( is_dir( $dir ) ) {
							foreach ( scandir( $dir ) as $service_file ) {
								if ( ! preg_match( '/^([^\\.].*)\.php$/', $service_file, $match ) ) {
									continue;
								}
								$service = $match[1];
								$class_name = $base_project . '\\' . $base_name . '\\Service\\' . $service;
								if ( class_exists( $class_name ) ) {
									$instance = $class_name::instance();

									$is_override = false;
									$parent = get_parent_class( $instance );
									$parent = explode( '\\', $parent );
									if ( 'AbstractFeed' === end( $parent ) ) {
										$set_service_name = $instance->get_id();
									} else {
										$is_override = true;
										$set_service_name = $instance->get_id();
									}

									if ( $is_override ) {
										$set_services[ $set_service_name ] = $instance;
									} else {
										if ( ! isset( $set_services[ $set_service_name ] ) ) {
											$set_services[ $set_service_name ] = $instance;
										}
									}
								}
							}
						}
					}
				}

				if ( $set_services ) {
					$group_list = [];
					foreach ( $set_services as $service ) {
						$priolity_no = $service->get_priolity();
						$group_list[ $priolity_no ][] = $service;
					}
					krsort( $group_list );
					foreach ( $group_list as $group ) {
						foreach ( $group as $service ) {
							$id = $service->get_id();
							$this->services[ $id ] = $service;
						}
					}
				}
				$ret_services = $this->services;
			}
		}

		return $ret_services;
	}

	/**
	 * クエリの上書き
	 *
	 * @param WP_Query $wp_query クエリ.
	 */
	public function pre_get_posts( WP_Query &$wp_query ) {
		if ( $wp_query->is_main_query() ) {
			if ( $this->router->is_feed( $wp_query ) ) {
				$feed_id = $this->router->get_feed_id( $wp_query );
				if ( $feed_id ) {
					$service = $this->get_service_by_id( $feed_id );
					if ( $service ) {
						$service->pre_get_posts( $wp_query );
					}
				}
			}
		}
	}
}
