<?php
/**
 * Template loading.
 *
 * A template can be overridden by dropping a file of the same name into
 * yourtheme/french-path/, so the site can restyle the learner facing output
 * without touching the plugin.
 *
 * @package French_Path
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Finds and renders templates.
 */
class French_Path_Templates {

	/**
	 * Directory a theme puts its overrides in.
	 */
	const THEME_DIR = 'french-path';

	/**
	 * Renders a template with the given variables in scope.
	 *
	 * @param string $name Template name without the extension.
	 * @param array  $vars Variables extracted into the template.
	 * @return void
	 */
	public static function render( $name, $vars = array() ) {
		$file = self::locate( $name );

		if ( ! $file ) {
			return;
		}

		// phpcs:ignore WordPress.PHP.DontExtract.extract_extract
		extract( (array) $vars, EXTR_SKIP );

		include $file;
	}

	/**
	 * Finds a template, child theme first, then parent, then the plugin.
	 *
	 * @param string $name Template name without the extension.
	 * @return string Absolute path, empty when nothing was found.
	 */
	public static function locate( $name ) {
		$name = sanitize_file_name( (string) $name );

		if ( '' === $name ) {
			return '';
		}

		$relative = self::THEME_DIR . '/' . $name . '.php';

		$found = locate_template( array( $relative ) );

		if ( $found ) {
			return $found;
		}

		/**
		 * Filters the directories searched for a template.
		 *
		 * An add-on can add its own directory here rather than shipping
		 * files into the theme.
		 *
		 * @param string[] $directories Absolute paths, searched in order.
		 * @param string   $name        Template name.
		 */
		$directories = (array) apply_filters(
			'french_path_template_directories',
			array( FRENCH_PATH_PATH . 'templates' ),
			$name
		);

		foreach ( $directories as $directory ) {
			$path = trailingslashit( $directory ) . $name . '.php';

			if ( file_exists( $path ) ) {
				return $path;
			}
		}

		return '';
	}
}
