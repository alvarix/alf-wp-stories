<?php
/**
 * Registers the configurable story Custom Post Type.
 *
 * @package ALF_WP_Stories
 */

namespace ALF_WP_Stories;

defined( 'ABSPATH' ) || exit;

/**
 * Story post type registration.
 */
class Post_Type {

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
		add_action( 'init', array( $this, 'register_post_type' ) );
	}

	/**
	 * Build labels from the configured singular/plural names.
	 *
	 * @return array
	 */
	private function labels() {
		$plural   = $this->options->get( 'post_type.plural', 'Client Stories' );
		$singular = $this->options->get( 'post_type.singular', 'Client Story' );
		$lc_plural = mb_strtolower( $plural );

		return array(
			'name'                  => $plural,
			'singular_name'         => $singular,
			'menu_name'             => $plural,
			'name_admin_bar'        => $singular,
			'add_new'               => __( 'Add New', 'alf-wp-stories' ),
			/* translators: %s: singular label */
			'add_new_item'          => sprintf( __( 'Add New %s', 'alf-wp-stories' ), $singular ),
			/* translators: %s: singular label */
			'new_item'              => sprintf( __( 'New %s', 'alf-wp-stories' ), $singular ),
			/* translators: %s: singular label */
			'edit_item'             => sprintf( __( 'Edit %s', 'alf-wp-stories' ), $singular ),
			/* translators: %s: singular label */
			'view_item'             => sprintf( __( 'View %s', 'alf-wp-stories' ), $singular ),
			/* translators: %s: plural label */
			'all_items'             => sprintf( __( 'All %s', 'alf-wp-stories' ), $plural ),
			/* translators: %s: plural label */
			'search_items'          => sprintf( __( 'Search %s', 'alf-wp-stories' ), $plural ),
			/* translators: %s: plural label (lowercase) */
			'not_found'             => sprintf( __( 'No %s found.', 'alf-wp-stories' ), $lc_plural ),
			/* translators: %s: plural label (lowercase) */
			'not_found_in_trash'    => sprintf( __( 'No %s found in Trash.', 'alf-wp-stories' ), $lc_plural ),
			/* translators: %s: singular label */
			'featured_image'        => sprintf( __( '%s Cover Image', 'alf-wp-stories' ), $singular ),
			'set_featured_image'    => __( 'Set cover image', 'alf-wp-stories' ),
			'remove_featured_image' => __( 'Remove cover image', 'alf-wp-stories' ),
			'use_featured_image'    => __( 'Use as cover image', 'alf-wp-stories' ),
		);
	}

	/**
	 * Register the post type.
	 *
	 * @return void
	 */
	public function register_post_type() {
		$slug  = $this->options->get( 'post_type.slug', 'client-stories' );
		$slug  = sanitize_title( $slug ) ?: 'client-stories';
		$key   = $this->options->post_type_key();

		$args = array(
			'labels'             => $this->labels(),
			'public'             => true,
			'publicly_queryable' => true,
			'show_ui'            => true,
			'show_in_menu'       => true,
			'show_in_rest'       => (bool) $this->options->get( 'post_type.show_in_rest', true ),
			'menu_icon'          => 'dashicons-images-alt2',
			'rewrite'            => array(
				'slug'       => $slug,
				'with_front' => false,
			),
			'has_archive'        => (bool) $this->options->get( 'post_type.has_archive', true ) ? $slug : false,
			'hierarchical'       => false,
			'supports'           => array( 'title', 'thumbnail', 'revisions' ),
		);

		/**
		 * Filter the story post type registration arguments.
		 *
		 * @param array  $args    Post type args.
		 * @param string $key     Internal post type key.
		 * @param Options $options Options service.
		 */
		$args = apply_filters( 'alf_wp_stories_post_type_args', $args, $key, $this->options );

		register_post_type( $key, $args );
	}
}
