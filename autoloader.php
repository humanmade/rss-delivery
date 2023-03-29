<?php
/**
 * オートローダー.
 *
 * @package FujiTV
 */

/**
 * Classが定義されていない場合に、ファイルを探すクラス
 */
class ClassLoader {
	/**
	 * ファイルがあるディレクトリのリスト.
	 *
	 * @var type class
	 */
	private static $dirs;

	/**
	 * クラスが見つからなかった場合呼び出されるメソッド,spl_autoload_register でこのメソッドを登録してください.
	 *
	 * @param string $class 名前空間など含んだクラス名.
	 * @return bool 成功すればtrue.
	 */
	public static function load_class( $class ) {
		foreach ( self::directories() as $directory ) {
			$file_name = "{$directory}/{$class}.php";
			$file_name = str_replace( '\\', '/', $file_name );

			if ( is_file( $file_name ) ) {
				require $file_name;

				return true;
			}
		}
	}

	/**
	 * ディレクトリリスト
	 *
	 * @return array フルパスのリスト
	 */
	private static function directories() {
		if ( empty( self::$dirs ) ) {
			$dir = __DIR__;
			$target = preg_replace( '@/mu-plugins/rss-delivery.*@', '/mu-plugins/rss-delivery/src', $dir );

			self::$dirs = [
				$target,
			];
		}

		return self::$dirs;
	}
}

// これを実行しないとオートローダーとして動かない.
spl_autoload_register( [ 'ClassLoader', 'load_class' ] );
