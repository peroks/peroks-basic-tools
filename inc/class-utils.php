<?php
/**
 * Static helpers and utilities.
 *
 * @author Per Egil Roksvaag
 */

declare( strict_types = 1 );
namespace Peroks\WP\Plugin\Tools;

/**
 * Static helpers and utilities.
 */
class Utils {
	/**
	 * Converts arrays and objects to strings with a separator.
	 *
	 * String are returned without changes, other types are cast to string.
	 *
	 * @param mixed  $value The value to convert.
	 * @param string $separator The separator to use between array elements.
	 *
	 * @return string The converted string.
	 */
	public static function to_string( mixed $value, string $separator = ' ' ): string {
		if ( is_array( $value ) ) {
			return join( $separator, $value );
		}
		if ( is_object( $value ) ) {
			return join( $separator, get_object_vars( $value ) );
		}
		return (string) $value;
	}

	/**
	 * Converts a string to an array.
	 *
	 * @param string|array $value A string to be converted or an array to be returned as is.
	 * @param string $separator The string separator, default to comma.
	 *
	 * @return array The converted or original array.
	 */
	public static function string_to_array( string|array $value, string $separator = ',' ): array {
		if ( is_string( $value ) ) {
			return array_map( 'trim', explode( $separator, $value ) );
		}
		return $value;
	}

	/**
	 * Converts an associative array to a string of HTML attributes.
	 *
	 * Boolean attributes are rendered as key only when true, and omitted when false.
	 * String attributes with empty values are omitted.
	 * Other attributes are rendered as key="value".
	 *
	 * @param array $attributes An associative array of attributes.
	 * @param bool  $prepend_space Whether to prepend a space if the result is not empty (true, default) or not (false).
	 *
	 * @return string The string of HTML attributes, prefixed with a space if not empty.
	 */
	public static function array_to_attrs( array $attributes, bool $prepend_space = true ): string {
		$result = array_map( function ( string $key, mixed $value ): string {
			if ( is_bool( $value ) ) {
				return $value ? sanitize_key( $key ) : '';
			}
			if ( is_string( $value ) ) {
				$value = esc_attr( trim( $value ) );
				return $value ? sprintf( '%s="%s"', sanitize_key( $key ), $value ) : '';
			}
			return sprintf( '%s="%s"', sanitize_key( $key ), esc_attr( strval( $value ) ) );
		}, array_keys( $attributes ), $attributes );

		$attrs   = join( ' ', array_filter( $result ) );
		$prepend = $prepend_space ? ' ' : '';

		return $attrs ? $prepend . $attrs : '';
	}

	/**
	 * Build a style attribute string from an associative array.
	 *
	 * @param array $styles An associative array of css style properties (keys) and their values.
	 * @param bool  $esc Whether to escape the css string (true, default) or not (false).
	 */
	public static function array_to_style( array $styles, bool $esc = true ): string {
		$result = array_map( function ( string $key, mixed $value ): string {
			return sprintf( '%s:%s;', sanitize_key( $key ), $value );
		}, array_keys( $styles ), $styles );

		$style = join( ' ', $result );
		return $esc ? esc_attr( $style ) : $style;
	}

	/**
	 * Adds an action with a possibly increased priority to ensure it will be executed during the current action.
	 *
	 * @param string   $tag The name of the action to add or execute.
	 * @param callable $callback The callback function to be executed.
	 * @param int      $priority Optional. The priority at which the function should be fired. Default is 10.
	 * @param int      $accepted_args Optional. The number of arguments the function accepts. Default is 1.
	 */
	public static function add_elastic_action( string $tag, callable $callback, int $priority = 10, int $accepted_args = 1 ): void {
		global $wp_filter;

		if ( doing_action( $tag ) ) {
			// Increase priority if necessary to ensure the callback is executed.
			$priority = max( $priority, 1 + $wp_filter[ $tag ]->current_priority() );
		}

		add_action( $tag, $callback, $priority, $accepted_args );
	}

	/**
	 * Adds a filter with a possibly increased priority to ensure it will be executed during the current filter.
	 *
	 * @param string   $tag The name of the filter to add or execute.
	 * @param callable $callback The callback function to be executed.
	 * @param int      $priority Optional. The priority at which the function should be fired. Default is 10.
	 * @param int      $accepted_args Optional. The number of arguments the function accepts. Default is 1.
	 */
	public static function add_elastic_filter( string $tag, callable $callback, int $priority = 10, int $accepted_args = 1 ): void {
		global $wp_filter;

		if ( doing_filter( $tag ) ) {
			// Increase priority if necessary to ensure the callback is executed.
			$priority = max( $priority, 1 + $wp_filter[ $tag ]->current_priority() );
		}

		add_filter( $tag, $callback, $priority, $accepted_args );
	}
}
