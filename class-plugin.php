<?php
/**
 * Plugin Name:       Basic Plugin Tools
 * Description:       Basic tools and classes for use in other WordPress plugins.
 * Plugin URI:        https://github.com/peroks/peroks-basic-tools
 * Update URI:        https://github.com/peroks/peroks-basic-tools
 * Text Domain:       peroks-basic-tools
 * Domain Path:       /languages
 * Author:            Per Egil Roksvaag
 * Author URI:        https://github.com/peroks
 * License:           MIT
 * Version:           0.3
 * Stable tag:        0.3
 * Requires at least: 6.6
 * Tested up to:      6.8
 * Requires PHP:      8.1
 */

declare( strict_types = 1 );
namespace Peroks\WP\Plugin\Tools;

// Require the singleton trait.
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
		$classes = [
			// Plugin setup.
			__NAMESPACE__ . '\\Settings'       => static::path( 'inc/class-settings.php' ),
			__NAMESPACE__ . '\\Setup'          => static::path( 'inc/class-setup.php' ),

			// Basic tools.
			__NAMESPACE__ . '\\Github_Updater' => static::path( 'inc/class-github-updater.php' ),
			__NAMESPACE__ . '\\Plugin_Data'    => static::path( 'inc/class-plugin-data.php' ),
			__NAMESPACE__ . '\\Settings_Page'  => static::path( 'inc/class-settings-page.php' ),
			__NAMESPACE__ . '\\Utils'          => static::path( 'inc/class-utils.php' ),
		];

		spl_autoload_register( function ( $name ) use ( $classes ) {
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
		Settings::instance();
		Setup::instance();
	}

	/**
	 * Gets the current plugin version.
	 */
	public static function version(): string {
		return Plugin_Data::create( self::FILE )->Version;
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
		return plugin_dir_path( self::FILE ) . $path;
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
		return plugins_url( $path, self::FILE );
	}
}

// Initialize the plugin and notify that it's fully loaded and ready.
if ( defined( 'ABSPATH' ) && ABSPATH ) {
	add_action( 'plugins_loaded', function () {
		do_action( Plugin::PREFIX . '_ready', Plugin::instance() );
	}, 20 );
}
