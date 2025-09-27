<?php
/**
 * Enables automated plugin updates from a GitHub repository.
 * This class was inspired by the article "How To Deploy WordPress Plugins With GitHub Using Transients"
 * by Matthew Ray.
 *
 * @see https://www.smashingmagazine.com/2015/08/deploy-wordpress-plugins-with-github-using-transients/
 * @see http://www.matthewray.com/
 * @author Per Egil Roksvaag
 */

declare( strict_types = 1 );
namespace Peroks\WP\Plugin\Tools;

use WP_Upgrader;

/**
 * Enables automated plugin updates from a GitHub repository.
 */
class Github_Updater {

	/**
	 * Wrapper object for accessing plugin data.
	 *
	 * @var Plugin_Data
	 */
	protected Plugin_Data $plugin;

	/**
	 * The url to the plugin GitHup repository.
	 *
	 * @var string
	 */
	protected string $repository_url;

	/**
	 * The GitHub token to access the repository.
	 *
	 * @var string
	 */
	protected string $repository_token;

	/**
	 * The latest release from the GitHub repository.
	 *
	 * @var object|false|null
	 */
	protected object|false|null $release = null;

	/**
	 * Creates a GitHub Updater instance.
	 *
	 * @param string $plugin_file The plugin file.
	 * @param string $repository_token A GitHub token to access a private repository.
	 */
	public static function create( string $plugin_file, string $repository_token = '' ): static|null {
		if ( is_admin() && $plugin_file ) {
			return new static( $plugin_file, $repository_token );
		}
		return null;
	}

	/**
	 * Constructor.
	 *
	 * @param string $plugin_file The plugin file.
	 * @param string $repository_token A GitHub token to access a private repository.
	 */
	public function __construct( string $plugin_file, string $repository_token = '' ) {
		if ( is_admin() ) {
			$this->plugin           = new Plugin_Data( $plugin_file );
			$this->repository_url   = $this->plugin->UpdateURI;
			$this->repository_token = $repository_token;

			if ( $this->repository_url ) {
				add_filter( 'pre_set_site_transient_update_plugins', [ $this, 'update_plugins' ] );
				add_filter( 'plugins_api', [ $this, 'plugins_api' ], 10, 3 );
				add_filter( 'upgrader_pre_download', [ $this, 'upgrader_pre_download' ], 10, 4 );
				add_filter( 'upgrader_source_selection', [ $this, 'upgrader_source_selection' ], 10, 4 );
			}
		}
	}

	/**
	 * Checks if a newer version of this plugin is available on GitHub.
	 *
	 * @param object $transient Contains plugin update states.
	 *
	 * @return object the modified object.
	 */
	public function update_plugins( object $transient ): object {

		// Did WordPress check for updates?
		if ( property_exists( $transient, 'checked' ) && $transient->checked ) {
			$release = $this->get_latest_release();

			// Do we have a valid release object?
			if ( is_object( $release ) && empty( is_wp_error( $release ) ) ) {
				$release_version = ltrim( $release->tag_name, 'v' );

				// Is a newer version available on GitHub?
				if ( version_compare( $this->plugin->Version, $release_version, '<' ) ) {
					$transient->response[ $this->plugin->Base ] = (object) [
						'url'          => $this->repository_url,
						'slug'         => current( explode( '/', $this->plugin->Base ) ),
						'plugin'       => $this->plugin->Base,
						'package'      => $release->zipball_url,
						'new_version'  => $release_version,
						'requires_php' => $this->plugin->RequiresPHP,
					];
				}
			}
		}
		return $transient;
	}

	/**
	 * Displays plugin version details.
	 *
	 * @param array|false|object $result The result object or array.
	 * @param string             $action The type of information being requested from the Plugin Installation API.
	 * @param object             $args Plugin API arguments.
	 *
	 * @return false|object Plugin information
	 */
	public function plugins_api( mixed $result, string $action, object $args ): mixed {
		$slug = current( explode( '/', $this->plugin->Base ) );

		if ( property_exists( $args, 'slug' ) && $args->slug === $slug ) {
			$release = $this->get_latest_release();

			return (object) [
				'name'              => $this->plugin->Name,
				'slug'              => $slug,
				'plugin'            => $this->plugin->Base,
				'version'           => ltrim( $release->tag_name, 'v' ),
				'author'            => $this->plugin->Author,
				'author_profile'    => $this->plugin->AuthorURI,
				'last_updated'      => $release->published_at,
				'homepage'          => $this->plugin->PluginURI,
				'short_description' => $this->plugin->Description,
				'sections'          => [
					'Description' => $this->plugin->Description,
					'Updates'     => $release->body,
				],
				'download_link'     => $release->zipball_url,
			];
		}
		return $result;
	}

	/**
	 * Adds an authorisation header for private GitHub repositories.
	 * You can create a "Personal access token" in GitHub.
	 *
	 * @param bool        $reply Whether to bail without returning the package. Default false.
	 * @param string      $package The package file name.
	 * @param WP_Upgrader $upgrader The WP_Upgrader instance.
	 * @param array       $hook_extra Extra arguments passed to hooked filters.
	 *
	 * @return bool The modified reply.
	 */
	public function upgrader_pre_download( bool $reply, string $package, WP_Upgrader $upgrader, array $hook_extra ): bool {
		$plugin_base = $hook_extra['plugin'] ?? null;

		if ( $this->plugin->Base === $plugin_base && $this->repository_token ) {
			add_filter( 'http_request_args', function ( $args, $url ) use ( $package ) {
				if ( isset( $args['filename'] ) && $url === $package ) {
					$args['headers']['Authorization'] = "token {$this->repository_token}";
				}
				return $args;
			}, 10, 2 );
		}

		return $reply;
	}

	/**
	 * Moves the source file location for the upgrade package.
	 *
	 * @param string      $source File source location.
	 * @param string      $remote_source Remote file source location.
	 * @param WP_Upgrader $upgrader WP_Upgrader instance.
	 * @param array       $hook_extra Extra arguments passed to hooked filters.
	 *
	 * @return string The modified source file location.
	 */
	public function upgrader_source_selection( string $source, string $remote_source, WP_Upgrader $upgrader, array $hook_extra ): string {
		global $wp_filesystem;
		$plugin_base = $hook_extra['plugin'] ?? '';

		// Set plugin slug and move source accordingly.
		if ( $this->plugin->Base === $plugin_base ) {
			$slug   = current( explode( '/', $this->plugin->Base ) );
			$target = trailingslashit( dirname( $source ) . '/' . $slug );

			if ( $wp_filesystem->move( $source, $target ) ) {
				return $target;
			}
		}

		return $source;
	}

	/**
	 * Gets the latest release of this plugin on GitHub.
	 *
	 * @return object|false The latest release of this plugin on GitHub.
	 */
	public function get_latest_release(): object|false {
		if ( is_null( $this->release ) ) {
			$this->release = false;

			$repo = wp_parse_url( $this->repository_url );
			$host = trim( $repo['host'] ?? '' );
			$path = trim( $repo['path'] ?? '', '/' );
			$args = [];

			if ( 'github.com' === $host && strpos( $path, '/' ) ) {
				if ( $this->repository_token ) {
					$args['headers']['Authorization'] = "token {$this->repository_token}";
				}

				$request  = "https://api.github.com/repos/{$path}/releases";
				$response = wp_remote_get( $request, $args );
				$status   = wp_remote_retrieve_response_code( $response );

				if ( is_wp_error( $response ) ) {
					return $response;
				}

				if ( 200 === $status ) {
					$releases = json_decode( wp_remote_retrieve_body( $response ) );
					$releases = array_filter( (array) $releases, function ( $release ) {
						return isset( $release->draft ) && false === $release->draft;
					} );

					$this->release = current( $releases );
				}
			}
		}
		return $this->release;
	}
}
