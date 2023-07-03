<?php
/**
 * シングルトンモデル
 *
 * @package HM\RSS_Delivery
 */

namespace HM\FeedGenerator\Model;

/**
 * Singleton Class
 */
abstract class Singleton {

	/**
	 * Instance holder.
	 *
	 * @var array
	 */
	private static $instances = [];

	/**
	 * Singleton constructor.
	 *
	 * @param array $settings Setting array.
	 */
	protected function __construct( $settings = [] ) {
		// Do something.
	}

	/**
	 * Get instance
	 *
	 * @param array $settings Setting array.
	 *
	 * @return static
	 */
	public static function instance( $settings = [] ) {
		$class_name = get_called_class();
		if ( ! isset( self::$instances[ $class_name ] ) ) {
			self::$instances[ $class_name ] = new $class_name( $settings );
		}

		return self::$instances[ $class_name ];
	}
}
