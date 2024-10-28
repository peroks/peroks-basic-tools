<?php
/**
 * Plugin Name:       Basic Plugin Tools
 * Description:       Basic tools and classes for use in other WordPress plugins.
 *
 * Text Domain:       peroks-basic-tools
 * Domain Path:       /languages
 *
 * Author:            Per Egil Roksvaag
 * Author URI:        https://github.com/peroks
 *
 * Plugin URI:        https://github.com/peroks/peroks-basic-tools
 * Update URI:        https://github.com/peroks/peroks-basic-tools
 *
 * Version:           0.2.3
 * Stable tag:        0.2.3
 * Requires at least: 6.6
 * Tested up to:      6.6
 * Requires PHP:      8.2
 */

declare( strict_types = 1 );
namespace Peroks\WP\Plugin\Tools;

require_once __DIR__ . '/inc/trait-singleton.php';

/**
 * The plugin main class.
 */
class Plugin {
	use Singleton;

	/**
	 * The full path to this file.
	 *
	 * @var string The plugin file.
	 */
	const FILE = __FILE__;

	/**
	 * The plugin prefix, Use lowercase and underscores as word separator.
	 *
	 * @var string The plugin prefix (underscore).
	 */
	const PREFIX = 'peroks_basic_tools';

	/**
	 * The plugin global filter hooks.
	 */
	const FILTER_CLASS_NAME     = self::PREFIX . '/class_name';
	const FILTER_CLASS_INSTANCE = self::PREFIX . '/class_instance';
	const FILTER_CLASS_PATHS    = self::PREFIX . '/class_paths';
	const FILTER_PLUGIN_VERSION = self::PREFIX . '/plugin_version';
	const FILTER_PLUGIN_PATH    = self::PREFIX . '/plugin_path';
	const FILTER_PLUGIN_URL     = self::PREFIX . '/plugin_url';

	/**
	 * The plugin global action hooks.
	 */
	const ACTION_CLASS_LOADED = self::PREFIX . '/class_loaded';

	/**
	 * Constructor.
	 */
	protected function __construct() {
		$this->autoload();
		$this->run();
	}

	/**
	 * Registers autoloading.
	 */
	protected function autoload(): void {
		$classes = apply_filters( self::FILTER_CLASS_PATHS, [
			// Plugin setup.
			__NAMESPACE__ . '\\Admin'          => static::path( 'inc/class-admin.php' ),
			__NAMESPACE__ . '\\Setup'          => static::path( 'inc/class-setup.php' ),

			// Basic tools.
			__NAMESPACE__ . '\\Github_Updater' => static::path( 'inc/class-github-updater.php' ),
			__NAMESPACE__ . '\\Plugin_Data'    => static::path( 'inc/class-plugin-data.php' ),
			__NAMESPACE__ . '\\Settings_Page'  => static::path( 'inc/class-settings-page.php' ),
			__NAMESPACE__ . '\\Utils'          => static::path( 'inc/class-utils.php' ),
		] );

		spl_autoload_register( function( $name ) use ( $classes ) {
			if ( array_key_exists( $name, $classes ) ) {
				require $classes[ $name ];
			}
		} );
	}

	/**
	 * Loads and runs the plugin classes.
	 * You must register your classes for autoloading (above) before you can run them here.
	 */
	protected function run(): void {
		Setup::instance();

		if ( is_admin() ) {
			Admin::instance();
		}
	}

	/**
	 * Gets the current plugin version.
	 */
	public static function version(): string {
		$version = Plugin_Data::create( self::FILE )->Version;
		return apply_filters( self::FILTER_PLUGIN_VERSION, $version, static::class );
	}

	/**
	 * Gets a full filesystem path from a local path.
	 *
	 * @param string $path The local path relative to this plugin's root directory.
	 *
	 * @return string The full filesystem path.
	 */
	public static function path( string $path = '' ): string {
		$path = ltrim( trim( $path ), '/' );
		$full = plugin_dir_path( self::FILE ) . $path;
		return apply_filters( self::FILTER_PLUGIN_PATH, $full, $path );
	}

	/**
	 * Gets the URL to the given local path.
	 *
	 * @param string $path The local path relative to this plugin's root directory.
	 *
	 * @return string The URL.
	 */
	public static function url( string $path = '' ): string {
		$path = ltrim( trim( $path ), '/' );
		$url  = plugins_url( $path, self::FILE );
		return apply_filters( self::FILTER_PLUGIN_URL, $url, $path );
	}
}

// Registers and runs the main plugin class.
if ( defined( 'ABSPATH' ) && ABSPATH ) {
	add_action( 'plugins_loaded', [ Plugin::class, 'instance' ] );
}
