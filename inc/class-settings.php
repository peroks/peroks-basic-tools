<?php
/**
 * Plugin admin setup.
 *
 * @author Per Egil Roksvaag
 */

// phpcs:disable WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase

declare( strict_types = 1 );
namespace Peroks\WP\Plugin\Tools;

/**
 * Plugin admin setup.
 */
class Settings {
	use Singleton;

	// The plugin settings page slug.
	const SETTINGS_PAGE_SLUG = 'peroks-basic-tools';

	// Section and option ids.
	const SECTION_GITHUB_UPDATER = Plugin::PREFIX . '/github-updater';
	const OPTION_GITHUB_TOKEN    = Plugin::PREFIX . '/github-token';

	/**
	 * Constructor.
	 */
	protected function __construct() {
		add_action( 'init', [ $this, 'enable_github_updater' ] );
		add_action( 'init', [ $this, 'register_settings' ], 5 );
	}

	/**
	 * Enable plugin update from GitHub.
	 */
	public function enable_github_updater(): void {
		$token = (string) get_option( self::OPTION_GITHUB_TOKEN );
		Github_Updater::create( Plugin::FILE, $token );
	}

	/**
	 * Registers settings and creates a corresponding settings page.
	 */
	public function register_settings(): void {
		$plugin = Plugin_Data::create( Plugin::FILE );
		$page   = Settings_Page::register_page( self::SETTINGS_PAGE_SLUG, [
			'page_title'      => sprintf( '%s %s', $plugin->Name, __( 'Settings' ) ), // phpcs:ignore
			'menu_title'      => $plugin->Name,
			'plugin_basename' => $plugin->Base,
		] );

		$page->add_section( [
			'section' => self::SECTION_GITHUB_UPDATER,
			'label'   => __( 'Automated plugin update from a GitHub repository', 'peroks-basic-tools' ),
		] );

		$page->add_text( [
			'option'      => self::OPTION_GITHUB_TOKEN,
			'section'     => self::SECTION_GITHUB_UPDATER,
			'label'       => __( 'GitHub access token', 'peroks-basic-tools' ),
			'description' => __( 'Enter a GitHub access token for private repositories.', 'peroks-basic-tools' ),
		] );
	}
}
