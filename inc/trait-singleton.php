<?php
/**
 * Implements the singleton pattern.
 *
 * @author Per Egil Roksvaag
 */

declare( strict_types = 1 );
namespace Peroks\WP\Plugin\Tools;

/**
 * Implements the singleton pattern.
 */
trait Singleton {
	/**
	 * An array of class singletons.
	 *
	 * @var static[]
	 */
	private static array $instances = [];

	/**
	 * Gets a new or existing class instance.
	 *
	 * @return static The class singleton.
	 */
	public static function instance(): static {
		if ( empty( static::$instances[ static::class ] ) ) {
			static::$instances[ static::class ] = new static();
		}
		return static::$instances[ static::class ];
	}

	/**
	 * Protect constructor.
	 */
	protected function __construct() {}
}
