<?php
/**
 * Story launcher: Gutenberg block + shortcode with dynamic rendering.
 *
 * @package ALF_WP_Stories
 */

namespace ALF_WP_Stories;

defined( 'ABSPATH' ) || exit;

/**
 * Launcher block/shortcode.
 */
class Launcher {

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
		add_action( 'init', array( $this, 'register_launcher_script' ), 5 );
		add_action( 'init', array( $this, 'register_block' ) );
		add_shortcode( 'visual_story_launcher', array( $this, 'shortcode' ) );
		add_action( 'wp_footer', array( $this, 'print_story_registry' ) );
	}

	/**
	 * Register the Gutenberg block (server-side render).
	 *
	 * @return void
	 */
	public function register_block() {
		$singular = $this->options->get( 'post_type.singular', 'Client Story' );

		register_block_type(
			'alf-wp-stories/launcher',
			array(
				/* translators: %s: singular label */
				'title'           => sprintf( __( '%s Launcher', 'alf-wp-stories' ), $singular ),
				'description'     => __( 'Display circular story entry points that open the story viewer.', 'alf-wp-stories' ),
				'category'        => 'widgets',
				'icon'            => 'format-image',
				'keywords'        => array( 'story', 'stories', 'launcher' ),
				'attributes'      => array(
					'mode'       => array( 'type' => 'string', 'default' => 'latest' ),
					'storyId'    => array( 'type' => 'number', 'default' => 0 ),
					'term'       => array( 'type' => 'string', 'default' => '' ),
					'limit'      => array( 'type' => 'number', 'default' => 1 ),
					'size'       => array( 'type' => 'number', 'default' => 56 ),
					'showTitle'  => array( 'type' => 'boolean', 'default' => true ),
					'ring'       => array( 'type' => 'boolean', 'default' => true ),
				),
				'render_callback' => array( $this, 'render_block' ),
				'editor_script'   => 'alf-wp-stories-block',
				'editor_style'    => 'alf-wp-stories-launcher',
				'style'           => 'alf-wp-stories-launcher',
			)
		);
	}

	/**
	 * Register the editor script (block controls).
	 *
	 * @return void
	 */
	public function register_launcher_script() {
		$asset_file = ALF_WP_STORIES_DIR . 'assets/js/block.asset.php';
		$deps       = array( 'wp-blocks', 'wp-element', 'wp-components', 'wp-block-editor', 'wp-i18n', 'wp-server-side-render' );
		$version    = ALF_WP_STORIES_VERSION;

		if ( file_exists( $asset_file ) ) {
			$asset   = include $asset_file;
			$deps    = isset( $asset['dependencies'] ) ? $asset['dependencies'] : $deps;
			$version = isset( $asset['version'] ) ? $asset['version'] : $version;
		}

		wp_register_script(
			'alf-wp-stories-block',
			ALF_WP_STORIES_URL . 'assets/js/block.js',
			$deps,
			$version,
			true
		);
	}

	/**
	 * Render the block from its stored attributes.
	 *
	 * @param array $attributes Block attributes.
	 * @return string Markup.
	 */
	public function render_block( $attributes ) {
		return $this->render( $attributes );
	}

	/**
	 * Shortcode callback.
	 *
	 * @param array $atts Shortcode attributes.
	 * @return string Markup.
	 */
	public function shortcode( $atts ) {
		$atts = shortcode_atts(
			array(
				'story'      => 0,
				'mode'       => '',
				'limit'      => 1,
				'term'       => '',
				'size'       => 56,
				'show_title' => 1,
				'ring'       => 1,
			),
			$atts,
			'visual_story_launcher'
		);

		$attributes = array(
			'mode'      => $atts['mode'],
			'storyId'   => absint( $atts['story'] ),
			'term'      => sanitize_title( $atts['term'] ),
			'limit'     => absint( $atts['limit'] ),
			'size'      => absint( $atts['size'] ),
			'showTitle' => (bool) $atts['show_title'],
			'ring'      => (bool) $atts['ring'],
		);

		return $this->render( $attributes );
	}

	/**
	 * Core render: resolve stories and build the launcher markup.
	 *
	 * @param array $attributes Normalized attributes.
	 * @return string Markup.
	 */
	public function render( $attributes ) {
		$attributes = wp_parse_args(
			$attributes,
			array(
				'mode'      => 'latest',
				'storyId'   => 0,
				'term'      => '',
				'limit'     => 1,
				'size'      => 56,
				'showTitle' => true,
				'ring'      => true,
			)
		);

		$story_ids = $this->resolve_stories( $attributes );

		// Enqueue assets only when the launcher is actually rendered.
		$this->enqueue();

		if ( empty( $story_ids ) ) {
			return '';
		}

		// Register story data for the viewer JS.
		$this->register_stories( $story_ids );

		$items = '';
		foreach ( $story_ids as $story_id ) {
			$story     = Plugin::instance()->get( 'story' )->get_story( $story_id );
			$title     = $story['title'];
			$cover_url = $story['cover'];

			$items .= $this->render_item( $story_id, $title, $cover_url, $attributes );
		}

		$class = 'alf-wp-stories-launcher' . ( $attributes['ring'] ? ' has-ring' : '' );

		/**
		 * Filter the launcher wrapper markup.
		 *
		 * @param string $markup     Full launcher markup.
		 * @param array  $attributes Attributes.
		 * @param int[]  $story_ids  Resolved story IDs.
		 */
		$markup = sprintf( '<div class="%s">%s</div>', esc_attr( $class ), $items );

		return apply_filters( 'alf_wp_stories_launcher_markup', $markup, $attributes, $story_ids );
	}

	/**
	 * Render a single launcher item (circle + optional title).
	 *
	 * @param int    $story_id Story post ID.
	 * @param string $title    Story title.
	 * @param string $cover_url Cover image URL.
	 * @param array  $attributes Launcher attributes.
	 * @return string
	 */
	private function render_item( $story_id, $title, $cover_url, $attributes ) {
		$size  = max( 24, (int) $attributes['size'] );
		$alt   = ! empty( $title ) ? sprintf( __( 'Open story: %s', 'alf-wp-stories' ), $title ) : __( 'Open story', 'alf-wp-stories' );

		$image = '';
		if ( $cover_url ) {
			$image = sprintf(
				'<img src="%s" alt="%s" width="%d" height="%d" loading="lazy" />',
				esc_url( $cover_url ),
				esc_attr( $alt ),
				$size,
				$size
			);
		} else {
			$image = '<span class="alf-wp-stories-launcher-placeholder" aria-hidden="true"></span>';
		}

		$style = sprintf(
			'width:%dpx;height:%dpx;',
			$size,
			$size
		);

		$circle = sprintf(
			'<span class="alf-wp-stories-launcher-circle" style="%s">%s</span>',
			esc_attr( $style ),
			$image
		);

		$title_markup = '';
		if ( $attributes['showTitle'] ) {
			$title_markup = sprintf(
				'<span class="alf-wp-stories-launcher-title">%s</span>',
				esc_html( $title )
			);
		}

		$item = sprintf(
			'<button type="button" class="alf-wp-stories-launcher-item" data-story-id="%d" aria-label="%s">%s%s</button>',
			(int) $story_id,
			esc_attr( $alt ),
			$circle,
			$title_markup
		);

		/**
		 * Filter a single launcher item.
		 *
		 * @param string $item     Item markup.
		 * @param int    $story_id Story ID.
		 * @param array  $attributes Attributes.
		 */
		return apply_filters( 'alf_wp_stories_launcher_item', $item, $story_id, $attributes );
	}

	/**
	 * Resolve which story IDs to display based on attributes.
	 *
	 * @param array $attributes Attributes.
	 * @return int[]
	 */
	private function resolve_stories( $attributes ) {
		$mode  = $attributes['mode'];
		$limit = max( 1, (int) $attributes['limit'] );

		$args = array(
			'post_type'      => $this->options->post_type_key(),
			'post_status'    => 'publish',
			'posts_per_page' => $limit,
			'orderby'        => 'date',
			'order'          => 'DESC',
			'fields'         => 'ids',
			'no_found_rows'  => true,
		);

		if ( 'specific' === $mode && ! empty( $attributes['storyId'] ) ) {
			$args['post__in'] = array( absint( $attributes['storyId'] ) );
			$args['orderby']  = 'post__in';
		} elseif ( 'term' === $mode && ! empty( $attributes['term'] ) && $this->options->taxonomy_enabled() ) {
			$args['tax_query'] = array(
				array(
					'taxonomy' => $this->options->taxonomy_key(),
					'field'    => 'slug',
					'terms'    => sanitize_title( $attributes['term'] ),
				),
			);
		}

		/**
		 * Filter the launcher query arguments.
		 *
		 * @param array $args       WP_Query args.
		 * @param array $attributes Launcher attributes.
		 */
		$args = apply_filters( 'alf_wp_stories_launcher_query_args', $args, $attributes );

		$query = new \WP_Query( $args );
		$ids   = is_array( $query->posts ) ? array_map( 'absint', $query->posts ) : array();

		/**
		 * Filter the resolved launcher story IDs.
		 *
		 * @param int[] $ids        Story IDs.
		 * @param array $attributes Attributes.
		 */
		return apply_filters( 'alf_wp_stories_launcher_story_ids', $ids, $attributes );
	}

	/**
	 * Enqueue launcher and viewer assets.
	 *
	 * @return void
	 */
	private function enqueue() {
		$viewer = Plugin::instance()->get( 'viewer' );
		if ( $viewer instanceof Viewer ) {
			$viewer->enqueue_viewer();
			$viewer->enqueue_launcher_styles();
		}
		// Ensure theming vars also apply to the launcher handle.
		$css = $this->options->theming_css();
		if ( '' !== $css && ! wp_styles()->add_data( 'alf-wp-stories-launcher', 'alf-theming', true ) ) {
			wp_add_inline_style( 'alf-wp-stories-launcher', $css );
		}
	}

	/**
	 * Collect story payloads to expose to the viewer JS.
	 *
	 * @param int[] $story_ids Story IDs.
	 * @return void
	 */
	private function register_stories( $story_ids ) {
		static $registry = array();
		$story_model = Plugin::instance()->get( 'story' );

		foreach ( $story_ids as $story_id ) {
			if ( isset( $registry[ $story_id ] ) ) {
				continue;
			}
			$registry[ $story_id ] = $story_model->get_story( $story_id );
		}

		// Store on the launcher instance via a static property on the class.
		self::$story_registry = $registry;
	}

	/**
	 * Story registry shared with the footer printer.
	 *
	 * @var array
	 */
	private static $story_registry = array();

	/**
	 * Print the story registry JSON in the footer for the viewer.
	 *
	 * @return void
	 */
	public function print_story_registry() {
		if ( empty( self::$story_registry ) ) {
			return;
		}
		$data = array_values( self::$story_registry );

		/**
		 * Filter the story registry before it is printed.
		 *
		 * @param array $data Story payloads.
		 */
		$data = apply_filters( 'alf_wp_stories_story_registry', $data );

		printf(
			'<script type="application/json" id="alf-wp-stories-registry">%s</script>',
			wp_json_encode( $data )
		);
	}
}
