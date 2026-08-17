<?php
/**
 * Template overrides, rewrite flushing, and feed URL wiring.
 *
 * @package ALF_WP_Stories
 */

namespace ALF_WP_Stories;

defined( 'ABSPATH' ) || exit;

/**
 * Template loading and rewrite management.
 */
class Templates {

	/**
	 * Options service.
	 *
	 * @var Options
	 */
	private $options;

	/**
	 * Constructor.
	 *
	 * @param Options $options Options service.
	 */
	public function __construct( Options $options ) {
		$this->options = $options;
	}

	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	public function register() {
		add_filter( 'archive_template', array( $this, 'archive_template' ) );
		add_filter( 'single_template', array( $this, 'single_template' ) );

		// Flush rewrite rules only when the slug changes.
		add_action( 'update_option_' . Options::OPTION_KEY, array( $this, 'maybe_flush_rewrites' ), 10, 2 );
	}

	/**
	 * Filter the archive template for the story post type.
	 *
	 * @param string $template Resolved template path.
	 * @return string
	 */
	public function archive_template( $template ) {
		if ( is_post_type_archive( $this->options->post_type_key() ) ) {
			$t = locate_template_file( 'archive-story' );
			if ( $t ) {
				return $t;
			}
		}
		return $template;
	}

	/**
	 * Filter the single template for the story post type.
	 *
	 * @param string $template Resolved template path.
	 * @return string
	 */
	public function single_template( $template ) {
		if ( is_singular( $this->options->post_type_key() ) ) {
			$t = locate_template_file( 'single-story' );
			if ( $t ) {
				return $t;
			}
		}
		return $template;
	}

	/**
	 * Flush rewrite rules if the slug changed between saves.
	 *
	 * @param mixed $old_value Previous option value.
	 * @param mixed $new_value New option value.
	 * @return void
	 */
	public function maybe_flush_rewrites( $old_value, $new_value ) {
		$old_slug = is_array( $old_value ) && isset( $old_value['post_type']['slug'] ) ? $old_value['post_type']['slug'] : '';
		$new_slug = is_array( $new_value ) && isset( $new_value['post_type']['slug'] ) ? $new_value['post_type']['slug'] : '';

		if ( $old_slug !== $new_slug ) {
			flush_rewrite_rules();
		}
	}
}
