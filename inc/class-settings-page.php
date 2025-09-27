<?php
/**
 * A helper class for registering settings and creating a corresponding settings page.
 *
 * @author Per Egil Roksvaag
 */

// phpcs:disable Squiz.Commenting.FunctionComment.MissingParamTag
// phpcs:disable Squiz.Commenting.FunctionComment.MissingParamName
// phpcs:disable Squiz.Commenting.VariableComment.MissingVar

declare( strict_types = 1 );
namespace Peroks\WP\Plugin\Tools;

/**
 * A helper class for registering settings and creating a corresponding settings page.
 */
class Settings_Page {
	/**
	 * An array of registered setting page instances.
	 *
	 * @var static[]
	 */
	protected static array $inst = [];

	/**
	 * The slug name of the settings page.
	 */
	protected string $slug;

	/**
	 * Whether to enqueue assets on the settings page (true) or not (false, default).
	 */
	protected bool $enqueue_assets = false;

	/**
	 * The settings page properties.
	 *
	 * @var array{
	 *   'menu_title': string,
	 *   'page_title': string,
	 *   'parent_slug': string,
	 *   'capability': string,
	 *   'load_page_callback': callable|null,
	 *   'show_page_callback': callable,
	 *   'position': int|null,
	 *   'plugin_basename': string,
	 * } $props
	 */
	protected array $props;

	/**
	 * Registers a submenu settings page and returns its instance.
	 *
	 * This function and the `add_*` methods of its returned instance should be
	 * called during the `init` action at the latest in order to register the
	 * settings (options) with their default values BEFORE they are used with
	 * `get_option()` elsewhere in your code.
	 *
	 * The registered settings are not only used in the admin area but also
	 * on the frontend and in REST API responses as well. Therefore, don't
	 * use `is_admin()` to limit execution to the admin.
	 *
	 * The actual settings submenu/page and the settings fields are only created
	 * during the `admin_menu` action. This is handled internally, so you can
	 * call this function and the `add_*` methods earlier without problems.
	 *
	 * @param string $slug The settings page slug.
	 * @param array{
	 *   'menu_title': string,
	 *   'page_title': string,
	 *   'parent_slug'?: string,
	 *   'capability'?: string,
	 *   'load_page_callback'?: callable,
	 *   'show_page_callback'?: callable,
	 *   'position'?: int,
	 *   'plugin_basename'?: string,
	 * } $props The settings page properties.
	 *
	 * @return static|null A new or existing settings page instance matching the slug,
	 * or null if the slug is empty.
	 */
	public static function register_page( string $slug, array $props = [] ): static|null {
		if ( empty( $slug ) ) {
			return null;
		}

		if ( empty( array_key_exists( $slug, self::$inst ) ) ) {
			self::$inst[ $slug ] = new static( $slug, $props );
		}

		return self::$inst[ $slug ];
	}

	/**
	 * Constructor.
	 *
	 * @param string $slug The settings page slug.
	 * @param array{
	 *   'menu_title': string,
	 *   'page_title': string,
	 *   'parent_slug'?: string,
	 *   'capability'?: string,
	 *   'load_page_callback'?: callable,
	 *   'show_page_callback'?: callable,
	 *   'position'?: int,
	 *   'plugin_basename'?: string,
	 * } $props The settings page properties
	 */
	protected function __construct( string $slug, array $props ) {
		$this->slug  = $slug;
		$this->props = wp_parse_args( $props, [
			'menu_title'         => $slug,
			'page_title'         => $slug,
			'parent_slug'        => 'options-general.php',
			'capability'         => 'manage_options',
			'load_page_callback' => null,
			'show_page_callback' => [ $this, 'show_page' ],
			'position'           => null,
			'plugin_basename'    => '',
		] );

		if ( is_admin() ) {
			// Adds the submenu page to the admin menu.
			Utils::add_elastic_action( 'admin_menu', [ $this, 'add_sub_menu' ] );

			// Displays a "Settings" link on the Plugins page.
			if ( $this->get_page_property( 'plugin_basename' ) ) {
				$name = $this->get_page_property( 'plugin_basename' );
				add_filter( "plugin_action_links_{$name}", [ $this, 'plugin_action_links' ] );
			}
		}
	}

	/**
	 * Gets the settings page instance matching the given slug.
	 *
	 * @param string $slug The settings page slug.
	 *
	 * @return static|null The settings page instance matching the given slug,
	 * or null if not found.
	 */
	public static function get_page( string $slug ): static|null {
		return self::$inst[ $slug ] ?? null;
	}

	/**
	 * Checks if a settings page with the given slug has been registered.
	 *
	 * @param string $slug The settings page slug.
	 */
	public static function has_page( string $slug ): bool {
		return (bool) static::get_page( $slug );
	}

	/**
	 * Gets the value of a settings page property.
	 *
	 * @param string $property The property name.
	 *
	 * @return mixed The property value.
	 */
	public function get_page_property( string $property ): mixed {
		if ( 'page_slug' === $property || 'menu_slug' === $property ) {
			return $this->slug;
		}
		return $this->props[ $property ] ?? null;
	}

	/**
	 * Sets the value of a settings page property.
	 *
	 * @param string $property The property name.
	 * @param mixed  $value The property value.
	 *
	 * @return mixed The given property value.
	 */
	public function set_page_property( string $property, mixed $value ): mixed {
		$this->props[ $property ] = $value;
		return $value;
	}

	/**
	 * Adds a submenu page to the admin menu.
	 */
	public function add_sub_menu(): void {
		$page = add_submenu_page(
			$this->get_page_property( 'parent_slug' ),
			$this->get_page_property( 'page_title' ),
			$this->get_page_property( 'menu_title' ),
			$this->get_page_property( 'capability' ),
			$this->slug,
			$this->get_page_property( 'show_page_callback' ),
			$this->get_page_property( 'position' ),
		);

		$hook_name = "load-{$page}";
		$callback  = $this->get_page_property( 'load_page_callback' );

		// Enqueue assets for image selection.
		add_action( $hook_name, [ $this, 'enqueue_assets' ] );

		// Enqueue custom assets when the page is loaded.
		if ( $callback ) {
			add_action( $hook_name, $callback );
		}
	}

	/**
	 * Enqueue assets for image selection.
	 */
	public function enqueue_assets(): void {
		// Only enqueue if required.
		if ( $this->enqueue_assets ) {
			// Enqueue WordPress media scripts.
			wp_enqueue_media();

			// Enqueue script for handling image selection.
			wp_enqueue_script(
				'peroks-tools-settings-page',
				Plugin::url( 'assets/js/settings-page.js' ),
				[ 'jquery-core', 'media-models' ],
				Plugin::version(),
				[ 'strategy' => 'defer' ]
			);
		}
	}

	/**
	 * Displays a "Settings" link for this plugin on the Plugins page.
	 *
	 * @param array $actions An array of plugin action links.
	 *
	 * @return array Tme modified action links.
	 */
	public function plugin_action_links( array $actions ): array {
		array_unshift( $actions, vsprintf( '<a href="%s">%s</a>', [
			esc_url( menu_page_url( $this->slug, false ) ),
			esc_html__( 'Settings' ), // phpcs:ignore WordPress.WP.I18n.MissingArgDomain
		] ) );

		return $actions;
	}

	/**
	 * Displays the settings page with content.
	 */
	public function show_page(): void {
		if ( current_user_can( $this->get_page_property( 'capability' ) ) ) {
			printf( '<div class="wrap">' );
			printf( '<h1>%s</h1>', esc_html( get_admin_page_title() ) );
			printf( '<form method="post" action="options.php">' );

			settings_fields( $this->slug );
			do_settings_sections( $this->slug );
			submit_button();

			printf( '</form>' );
			printf( '</div>' );
		}
	}

	/**
	 * Adds a new section to an admin page.
	 *
	 * @param array{
	 *   'section': string,
	 *   'label': string,
	 *   'description'?: string|array,
	 * } $args An array of arguments.
	 */
	public function add_section( array $args ): void {
		$param = (object) wp_parse_args( $args, [
			'section'     => 'default',
			'label'       => '',
			'description' => '',
		] );

		is_admin() && Utils::add_elastic_action( 'admin_menu', function () use ( $param ) {
			add_settings_section( $param->section, $param->label, function () use ( $param ) {
				printf( '<p>%s</p>', wp_kses_post( Utils::to_string( $param->description ) ) );
			}, $this->slug );
		} );
	}

	/**
	 * Adds a checkbox to a section on an admin page.
	 *
	 * @param array{
	 *   'option': string,
	 *   'section': string,
	 *   'label': string,
	 *   'description'?: string|array,
	 *   'default'?: int,
	 *   'show_in_rest'?: bool|array,
	 *   'sanitize'?: callable(mixed): int,
	 *   'class'?: string,
	 *   'attributes'?: array,
	 * } $args An array of arguments.
	 */
	public function add_checkbox( array $args ): void {
		$param = (object) wp_parse_args( $args, [
			'option'       => '',
			'section'      => 'default',
			'label'        => '',
			'description'  => '',
			'default'      => 0,
			'show_in_rest' => false,
			'sanitize'     => null,
			'class'        => '',
			'attributes'   => [],
		] );

		$sanitize = $param->sanitize ?? function ( $value ): int {
			return $value ? 1 : 0;
		};

		register_setting( $this->slug, $param->option, [
			'type'              => 'integer',
			'label'             => $param->label,
			'description'       => Utils::to_string( $param->description ),
			'sanitize_callback' => $sanitize,
			'show_in_rest'      => $param->show_in_rest,
			'default'           => $sanitize( $param->default ),
		] );

		is_admin() && Utils::add_elastic_action( 'admin_menu', function () use ( $param ) {
			add_settings_field( $param->option, $param->label, function () use ( $param ) {
				vprintf( '<input type="checkbox" id="%s" class="%s" name="%s" value="1"%s%s>', [
					esc_attr( $param->option ),
					esc_attr( $param->class ),
					esc_attr( $param->option ),
					get_option( $param->option ) ? ' checked' : '',
					Utils::array_to_attrs( $param->attributes ), // phpcs:ignore
				] );

				printf( '<span>%s</span>', wp_kses_post( Utils::to_string( $param->description ) ) );
			}, $this->slug, $param->section, [ 'label_for' => esc_attr( $param->option ) ] );
		} );
	}

	/**
	 * Adds multiple checkboxes to a section on an admin page.
	 *
	 * @param array{
	 *   'option': string,
	 *   'section': string,
	 *   'label': string,
	 *   'description'?: string|array,
	 *   'default'?: string[]|int[],
	 *   'show_in_rest'?: bool|array,
	 *   'sanitize'?: callable(mixed): array,
	 *   'class'?: string,
	 *   'terms': string[]|int[],
	 *   'attributes'?: array,
	 * } $args An array of arguments.
	 */
	public function add_multibox( array $args ): void {
		$param = (object) wp_parse_args( $args, [
			'option'       => '',
			'section'      => 'default',
			'label'        => '',
			'description'  => '',
			'default'      => [],
			'show_in_rest' => false,
			'sanitize'     => null,
			'class'        => '',
			'terms'        => [],
			'attributes'   => [],
		] );

		$sanitize = $param->sanitize ?? function ( $value ): array {
			return Utils::string_to_array( $value );
		};

		register_setting( $this->slug, $param->option, [
			'type'              => 'array',
			'label'             => $param->label,
			'description'       => Utils::to_string( $param->description ),
			'sanitize_callback' => $sanitize,
			'show_in_rest'      => $param->show_in_rest,
			'default'           => $sanitize( $param->default ),
		] );

		is_admin() && Utils::add_elastic_action( 'admin_menu', function () use ( $param ) {
			add_settings_field( $param->option, $param->label, function () use ( $param ) {
				printf( '<p class="description">%s</p>', wp_kses_post( Utils::to_string( $param->description ) ) );

				$value   = get_option( $param->option );
				$is_list = array_is_list( $param->terms );

				foreach ( $param->terms as $key => $label ) {
					$key   = $is_list ? $label : $key;
					$input = vsprintf( '<input type="checkbox" class="%s" name="%s[]" value="%s"%s%s>', [
						esc_attr( $param->class ),
						esc_attr( $param->option ),
						esc_attr( $key ),
						in_array( $key, $value, true ) ? ' checked' : '',
						Utils::array_to_attrs( $param->attributes ),
					] );
					printf( '<p><label>%s %s</label></p> ', $input, esc_html( $label ) ); // phpcs:ignore
				}
			}, $this->slug, $param->section );
		} );
	}

	/**
	 * Adds a dropdown to a section on an admin page.
	 *
	 * @param array{
	 *   'option': string,
	 *   'section': string,
	 *   'label': string,
	 *   'description'?: string|array,
	 *   'default'?: string|int,
	 *   'show_in_rest'?: bool|array,
	 *   'sanitize'?: callable(mixed): string|int,
	 *   'class'?: string,
	 *   'terms': string[]|int[],
	 *   'attributes'?: array,
	 * } $args An array of arguments.
	 */
	public function add_dropdown( array $args ): void {
		$param = (object) wp_parse_args( $args, [
			'option'       => '',
			'section'      => 'default',
			'label'        => '',
			'description'  => '',
			'default'      => '',
			'show_in_rest' => false,
			'sanitize'     => null,
			'class'        => 'regular-text',
			'terms'        => [],
			'attributes'   => [],
		] );

		$sanitize = $param->sanitize ?? function ( $value ) use ( $param ): int|string {
			return is_int( $param->default ) ? intval( $value ) : sanitize_text_field( $value );
		};

		register_setting( $this->slug, $param->option, [
			'type'              => is_int( $param->default ) ? 'integer' : 'string',
			'label'             => $param->label,
			'description'       => Utils::to_string( $param->description ),
			'sanitize_callback' => $sanitize,
			'show_in_rest'      => $param->show_in_rest,
			'default'           => $sanitize( $param->default ),
		] );

		is_admin() && Utils::add_elastic_action( 'admin_menu', function () use ( $param ) {
			add_settings_field( $param->option, $param->label, function () use ( $param ) {
				vprintf( '<select id="%s" class="%s" name="%s"%s>', [
					esc_attr( $param->option ),
					esc_attr( $param->class ),
					esc_attr( $param->option ),
					Utils::array_to_attrs( $param->attributes ), //phpcs:ignore
				] );

				$value   = get_option( $param->option );
				$is_list = array_is_list( $param->terms );

				foreach ( $param->terms as $key => $label ) {
					$key = $is_list ? $label : $key;

					vprintf( '<option value="%s"%s>%s</option>', [
						esc_attr( $key ),
						$key === $value ? ' selected' : '',
						esc_attr( $label ),
					] );
				}

				printf( '</select>' );
				printf( '<p class="description">%s</p>', wp_kses_post( Utils::to_string( $param->description ) ) );
			}, $this->slug, $param->section, [ 'label_for' => esc_attr( $param->option ) ] );
		} );
	}

	/**
	 * Adds multiple radio buttons to a section on an admin page.
	 *
	 * @param array{
	 *   'option': string,
	 *   'section': string,
	 *   'label': string,
	 *   'description'?: string|array,
	 *   'default'?: string|int,
	 *   'show_in_rest'?: bool|array,
	 *   'sanitize'?: callable(mixed): string|int,
	 *   'class'?: string,
	 *   'terms': string[]|int[],
	 *   'attributes'?: array,
	 * } $args An array of arguments.
	 */
	public function add_radio( array $args ): void {
		$param = (object) wp_parse_args( $args, [
			'option'       => '',
			'section'      => 'default',
			'label'        => '',
			'description'  => '',
			'default'      => '',
			'show_in_rest' => false,
			'sanitize'     => null,
			'class'        => '',
			'terms'        => [],
			'attributes'   => [],
		] );

		$sanitize = $param->sanitize ?? function ( $value ) use ( $param ): int|string {
			return is_int( $param->default ) ? intval( $value ) : sanitize_text_field( $value );
		};

		register_setting( $this->slug, $param->option, [
			'type'              => is_int( $param->default ) ? 'integer' : 'string',
			'label'             => $param->label,
			'description'       => Utils::to_string( $param->description ),
			'sanitize_callback' => $sanitize,
			'show_in_rest'      => $param->show_in_rest,
			'default'           => $sanitize( $param->default ),
		] );

		is_admin() && Utils::add_elastic_action( 'admin_menu', function () use ( $param ) {
			add_settings_field( $param->option, $param->label, function () use ( $param ) {
				printf( '<p class="description">%s</p>', wp_kses_post( Utils::to_string( $param->description ) ) );

				$value   = get_option( $param->option );
				$is_list = array_is_list( $param->terms );

				foreach ( $param->terms as $key => $label ) {
					$key   = $is_list ? $label : $key;
					$input = vsprintf( '<input type="radio" class="%s" name="%s" value="%s"%s%s>', [
						esc_attr( $param->class ),
						esc_attr( $param->option ),
						esc_attr( $key ),
						$key === $value ? ' checked' : '',
						Utils::array_to_attrs( $param->attributes ),
					] );
					printf( '<p><label>%s%s</label></p> ', $input, esc_html( $label ) ); // phpcs:ignore
				}
			}, $this->slug, $param->section );
		} );
	}

	/**
	 * Adds a numeric input field to a section on an admin page.
	 *
	 * @param array{
	 *   'option': string,
	 *   'section': string,
	 *   'label': string,
	 *   'description'?: string|array,
	 *   'default'?: int|float,
	 *   'show_in_rest'?: bool|array,
	 *   'sanitize'?: callable(mixed): int|float,
	 *   'class'?: string,
	 *   'attributes'?: array,
	 * } $args An array of arguments.
	 */
	public function add_number( array $args ): void {
		$param = (object) wp_parse_args( $args, [
			'option'       => '',
			'section'      => 'default',
			'label'        => '',
			'description'  => '',
			'default'      => 0,
			'show_in_rest' => false,
			'sanitize'     => null,
			'class'        => 'small-text',
			'attributes'   => [],
		] );

		$sanitize = $param->sanitize ?? function ( $value ) use ( $param ): int|float {
			return is_int( $param->default ) ? intval( $value ) : floatval( $value );
		};

		register_setting( $this->slug, $param->option, [
			'type'              => 'number',
			'label'             => $param->label,
			'description'       => Utils::to_string( $param->description ),
			'sanitize_callback' => $sanitize,
			'show_in_rest'      => $param->show_in_rest,
			'default'           => $sanitize( $param->default ),
		] );

		is_admin() && Utils::add_elastic_action( 'admin_menu', function () use ( $param ) {
			add_settings_field( $param->option, $param->label, function () use ( $param ) {
				vprintf( '<input type="number" id="%s" class="%s" name="%s" value="%d"%s>', [
					esc_attr( $param->option ),
					esc_attr( $param->class ),
					esc_attr( $param->option ),
					esc_attr( get_option( $param->option ) ),
					Utils::array_to_attrs( $param->attributes ), // phpcs:ignore
				] );

				printf( ' <span>%s</span>', wp_kses_post( Utils::to_string( $param->description ) ) );
			}, $this->slug, $param->section, [ 'label_for' => esc_attr( $param->option ) ] );
		} );
	}

	/**
	 * Adds a text input field to a section on an admin page.
	 *
	 * @param array{
	 *   'option': string,
	 *   'section': string,
	 *   'label': string,
	 *   'description'?: string|array,
	 *   'default'?: string,
	 *   'placeholder'?: string,
	 *   'show_in_rest'?: bool|array,
	 *   'sanitize'?: callable(mixed): string,
	 *   'class'?: string,
	 *   'type'?: string,
	 *   'attributes'?: array,
	 * } $args An array of arguments.
	 */
	public function add_text( array $args ): void {
		$param = (object) wp_parse_args( $args, [
			'option'       => '',
			'section'      => 'default',
			'label'        => '',
			'description'  => '',
			'default'      => '',
			'placeholder'  => '',
			'show_in_rest' => false,
			'sanitize'     => null,
			'class'        => 'regular-text',
			'type'         => 'text',
			'attributes'   => [],
		] );

		$sanitize = $param->sanitize ?? function ( $value ): string {
			return sanitize_text_field( (string) $value );
		};

		register_setting( $this->slug, $param->option, [
			'type'              => 'string',
			'label'             => $param->label,
			'description'       => Utils::to_string( $param->description ),
			'sanitize_callback' => $sanitize,
			'show_in_rest'      => $param->show_in_rest,
			'default'           => $sanitize( $param->default ),
		] );

		is_admin() && Utils::add_elastic_action( 'admin_menu', function () use ( $param ) {
			add_settings_field( $param->option, $param->label, function () use ( $param ) {
				vprintf( '<input type="%s" id="%s" class="%s" name="%s" value="%s" placeholder="%s"%s>', [
					esc_attr( $param->type ),
					esc_attr( $param->option ),
					esc_attr( $param->class ),
					esc_attr( $param->option ),
					esc_attr( get_option( $param->option ) ),
					esc_attr( $param->placeholder ),
					Utils::array_to_attrs( $param->attributes ), // phpcs:ignore
				] );

				printf( '<p class="description">%s</p>', wp_kses_post( Utils::to_string( $param->description ) ) );
			}, $this->slug, $param->section, [ 'label_for' => esc_attr( $param->option ) ] );
		} );
	}

	/**
	 * Adds a password input field to a section on an admin page.
	 *
	 * @param array{
	 *   'option': string,
	 *   'section': string,
	 *   'label': string,
	 *   'description'?: string|array,
	 *   'default'?: string,
	 *   'placeholder'?: string,
	 *   'show_in_rest'?: bool|array,
	 *   'sanitize'?: callable(mixed): string,
	 *   'class'?: string,
	 *   'attributes'?: array,
	 * } $args An array of arguments.
	 */
	public function add_password( array $args ): void {
		$args['type'] = 'password';
		$this->add_text( $args );
	}

	/**
	 * Adds a textarea to a section on an admin page.
	 *
	 * @param array{
	 *   'option': string,
	 *   'section': string,
	 *   'label': string,
	 *   'description'?: string|array,
	 *   'default'?: string,
	 *   'placeholder'?: string,
	 *   'show_in_rest'?: bool|array,
	 *   'sanitize'?: callable(mixed): string,
	 *   'class'?: string,
	 *   'rows'?: int,
	 *   'attributes'?: array,
	 * } $args An array of arguments.
	 */
	public function add_textarea( array $args ): void {
		$param = (object) wp_parse_args( $args, [
			'option'       => '',
			'section'      => 'default',
			'label'        => '',
			'description'  => '',
			'default'      => '',
			'placeholder'  => '',
			'show_in_rest' => false,
			'sanitize'     => null,
			'class'        => 'large-text',
			'rows'         => 10,
			'attributes'   => [],
		] );

		$sanitize = $param->sanitize ?? function ( $value ): string {
			return sanitize_text_field( (string) $value );
		};

		register_setting( $this->slug, $param->option, [
			'type'              => 'string',
			'label'             => $param->label,
			'description'       => Utils::to_string( $param->description ),
			'sanitize_callback' => $sanitize,
			'show_in_rest'      => $param->show_in_rest,
			'default'           => $sanitize( $param->default ),
		] );

		is_admin() && Utils::add_elastic_action( 'admin_menu', function () use ( $param ) {
			add_settings_field( $param->option, $param->label, function () use ( $param ) {
				vprintf( '<textarea id="%s" class="%s" rows="%d" name="%s" placeholder="%s" %s>%s</textarea>', [
					esc_attr( $param->option ),
					esc_attr( $param->class ),
					intval( $param->rows ),
					esc_attr( $param->option ),
					esc_attr( $param->placeholder ),
					Utils::array_to_attrs( $param->attributes ), // phpcs:ignore
					wp_kses_post( get_option( $param->option ) ),
				] );

				printf( '<p class="description">%s</p>', wp_kses_post( Utils::to_string( $param->description ) ) );
			}, $this->slug, $param->section, [ 'label_for' => esc_attr( $param->option ) ] );
		} );
	}

	/**
	 * Adds a list input field to a section on an admin page.
	 *
	 * @param array{
	 *   'option': string,
	 *   'section': string,
	 *   'label': string,
	 *   'description'?: string|array,
	 *   'default'?: string|string[],
	 *   'placeholder'?: string,
	 *   'show_in_rest'?: bool|array,
	 *   'sanitize'?: callable(mixed): array,
	 *   'class'?: string,
	 *   'rows'?: int,
	 *   'attributes'?: array,
	 * } $args An array of arguments.
	 */
	public function add_list( array $args ): void {
		$param = (object) wp_parse_args( $args, [
			'option'       => '',
			'section'      => 'default',
			'label'        => '',
			'description'  => '',
			'default'      => [],
			'placeholder'  => '',
			'show_in_rest' => false,
			'sanitize'     => null,
			'class'        => 'large-text',
			'rows'         => 10,
			'attributes'   => [],
		] );

		$sanitize = $param->sanitize ?? function ( $value ): array {
			$value = array_map( 'sanitize_text_field', Utils::string_to_array( $value, "\n" ) );
			return array_values( array_unique( array_filter( $value ) ) );
		};

		register_setting( $this->slug, $param->option, [
			'type'              => 'array',
			'label'             => $param->label,
			'description'       => Utils::to_string( $param->description ),
			'sanitize_callback' => $sanitize,
			'show_in_rest'      => $param->show_in_rest,
			'default'           => $sanitize( $param->default ),
		] );

		is_admin() && Utils::add_elastic_action( 'admin_menu', function () use ( $param ) {
			add_settings_field( $param->option, $param->label, function () use ( $param ) {
				vprintf( '<textarea id="%s" class="%s" rows="%d" name="%s" placeholder="%s" %s>%s</textarea>', [
					esc_attr( $param->option ),
					esc_attr( $param->class ),
					intval( $param->rows ),
					esc_attr( $param->option ),
					esc_attr( $param->placeholder ),
					Utils::array_to_attrs( $param->attributes ), //phpcs:ignore
					wp_kses_post( join( "\n", Utils::string_to_array( get_option( $param->option ), "\n" ) ) ),
				] );

				printf( '<p class="description">%s</p>', wp_kses_post( Utils::to_string( $param->description ) ) );
			}, $this->slug, $param->section, [ 'label_for' => esc_attr( $param->option ) ] );
		} );
	}

	/**
	 * Adds an image selector to a section on an admin page.
	 *
	 * @param array{
	 *   'option': string,
	 *   'section': string,
	 *   'label': string,
	 *   'description'?: string|array,
	 *   'default'?: int,
	 *   'sanitize'?: callable(mixed): int,
	 *   'class'?: string,
	 *   'size'?: string|int[],
	 *   'icon'?: bool,
	 *   'attributes'?: array,
	 * } $args An array of arguments.
	 */
	public function add_image( array $args ): void {
		$param = (object) wp_parse_args( $args, [
			'option'       => '',
			'section'      => 'default',
			'label'        => '',
			'description'  => '',
			'default'      => 0,
			'show_in_rest' => false,
			'sanitize'     => null,
			'class'        => '',
			'size'         => 'medium',
			'icon'         => false,
			'attributes'   => [],
		] );

		// We need frontend scripts for handling image selection.
		$this->enqueue_assets = true;

		$sanitize = $param->sanitize ?? function ( $value ): int {
			return intval( $value );
		};

		register_setting( $this->slug, $param->option, [
			'type'              => 'integer',
			'label'             => $param->label,
			'description'       => Utils::to_string( $param->description ),
			'sanitize_callback' => $sanitize,
			'show_in_rest'      => $param->show_in_rest,
			'default'           => $sanitize( $param->default ),
		] );

		is_admin() && Utils::add_elastic_action( 'admin_menu', function () use ( $param ) {
			add_settings_field( $param->option, $param->label, function () use ( $param ) {
				$value = get_option( $param->option );
				$size  = is_array( $param->size ) ? join( 'x', $param->size ) : $param->size;
				$image = wp_get_attachment_image( $value, $param->size, $param->icon, $param->attributes );

				// Output the description and the hidden input field.
				printf( '<p class="description">%s</p>', wp_kses_post( Utils::to_string( $param->description ) ) );
				vprintf( '<input type="hidden" id="%s" class="%s" name="%s" value="%d">', [
					esc_attr( $param->option ),
					esc_attr( $param->class ),
					esc_attr( $param->option ),
					esc_attr( get_option( $param->option ) ),
				] );

				// Render the select button.
				$select = vsprintf( '<button type="button" class="%s">%s</button>', [
					esc_attr( 'button select-image' ),
					esc_html__( 'Select image' ), // phpcs:ignore WordPress.WP.I18n.MissingArgDomain
				] );

				// Render the remove button, hidden if no image is selected.
				$remove = vsprintf( '<button type="button" class="%s" style="%s">%s</button>', [
					esc_attr( 'button remove-image' ),
					esc_attr( $image ? '' : 'display:none;' ),
					esc_html__( 'Remove image' ), // phpcs:ignore WordPress.WP.I18n.MissingArgDomain
				] );

				// Output figure element containing the image preview, select and remove buttons.
				printf( '
					<figure class="peroks-tools-settings-page-image" data-size="%s" style="margin: 0.5rem 0 0">
						%s
						<figcaption>
							%s
							%s
						</figcaption>
					</figure>',
					esc_attr( $size ),
					wp_kses_post( $image ) ?: '<img src="" alt="">',
					$select, // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
					$remove // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
				);
			}, $this->slug, $param->section );
		} );
	}
}
