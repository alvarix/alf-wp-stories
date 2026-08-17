<?php
/**
 * Registers the optional grouping taxonomy.
 *
 * @package ALF_WP_Stories
 */

namespace ALF_WP_Stories;

defined( 'ABSPATH' ) || exit;

/**
 * Taxonomy registration.
 */
class Taxonomy {

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
		add_action( 'init', array( $this, 'register_taxonomy' ) );
	}

	/**
	 * Register the taxonomy when enabled.
	 *
	 * @return void
	 */
	public function register_taxonomy() {
		if ( ! $this->options->taxonomy_enabled() ) {
			return;
		}

		$name      = $this->options->get( 'taxonomy.name', 'Clients' );
		$slug      = $this->options->get( 'taxonomy.slug', 'clients' );
		$slug      = sanitize_title( $slug ) ?: 'clients';
		$post_type = $this->options->post_type_key();

		$args = array(
			'labels'            => array(
				'name'          => $name,
				'singular_name' => $name,
				'search_items'  => sprintf( __( 'Search %s', 'alf-wp-stories' ), $name ),
				'all_items'     => sprintf( __( 'All %s', 'alf-wp-stories' ), $name ),
				'edit_item'     => sprintf( __( 'Edit %s', 'alf-wp-stories' ), $name ),
				'update_item'   => sprintf( __( 'Update %s', 'alf-wp-stories' ), $name ),
				'add_new_item'  => sprintf( __( 'Add New %s', 'alf-wp-stories' ), $name ),
				'not_found'     => sprintf( __( 'No %s found.', 'alf-wp-stories' ), $name ),
			),
			'public'            => true,
			'show_ui'           => true,
			'show_in_rest'      => true,
			'show_admin_column' => true,
			'hierarchical'      => (bool) $this->options->get( 'taxonomy.hierarchical', false ),
			'rewrite'           => array(
				'slug'       => $slug,
				'with_front' => false,
			),
		);

		/**
		 * Filter the story taxonomy registration arguments.
		 *
		 * @param array  $args      Taxonomy args.
		 * @param string $taxonomy  Taxonomy key.
		 * @param string $post_type Post type key.
		 */
		$args = apply_filters( 'alf_wp_stories_taxonomy_args', $args, $this->options->taxonomy_key(), $post_type );

		register_taxonomy( $this->options->taxonomy_key(), $post_type, $args );
	}
}
