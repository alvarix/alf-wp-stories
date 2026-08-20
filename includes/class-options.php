<?php
/**
 * Plugin options: defaults, storage, sanitization, and the settings page.
 *
 * The settings page lives under the story CPT admin menu and is tabbed:
 *   Settings | Auto-Post | Help
 *
 * @package ALF_WP_Stories
 */

namespace ALF_WP_Stories;

defined( 'ABSPATH' ) || exit;

/**
 * Options model and settings screen.
 */
class Options {

	const OPTION_KEY = 'alf_wp_stories_options';

	/** Page slug for the main (Settings tab) sections. */
	const PAGE_SETTINGS  = 'alf-wp-stories';
	/** Page slug for the Auto-Post tab sections. */
	const PAGE_AUTOP_POST = 'alf-wp-stories-autopost';

	/**
	 * Default option values, keyed by section.
	 *
	 * @var array
	 */
	private $defaults;

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->defaults = $this->build_defaults();
	}

	/**
	 * Register admin hooks.
	 *
	 * @return void
	 */
	public function register() {
		add_action( 'admin_menu', array( $this, 'add_menu' ) );
		add_action( 'admin_init', array( $this, 'register_settings' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin' ) );
		add_filter( 'plugin_action_links_' . plugin_basename( ALF_WP_STORIES_FILE ), array( $this, 'plugin_action_links' ) );
	}

	/**
	 * Build the canonical defaults.
	 *
	 * @return array
	 */
	private function build_defaults() {
		return array(
			'post_type'        => array(
				'plural'        => 'Client Stories',
				'singular'      => 'Client Story',
				'slug'          => 'client-stories',
				'has_archive'   => true,
				'show_in_rest'  => true,
			),
			'taxonomy'         => array(
				'enabled'      => true,
				'name'         => 'Clients',
				'slug'         => 'clients',
				'hierarchical' => false,
			),
			'viewer'           => array(
				'frame_duration' => 5000,
				'autoplay'       => true,
				'loop'           => true,
				'show_progress'  => true,
				'show_close'     => true,
				'keyboard'       => true,
				'swipe'          => true,
			),
			'story'            => array(
				'default_cover_source'  => 'first_frame',
				'default_post_status'   => 'publish',
				'auto_open_on_single'   => true,
			),
			'theming'          => array(
				'accent'             => '#c8ccd2',
				'ring_width'         => 4,
				'ring_style'         => 'solid',
				'overlay'            => 'rgba(20,22,26,0.96)',
				'launcher_background' => '#eceef1',
			),
			'filename_grammar' => array(
				'delimiter' => '__',
				'group_key' => 'title',
				'segments'  => array(
					array( 'target' => 'title',       'transform' => 'title_case' ),
					array( 'target' => 'frame_order', 'transform' => 'integer' ),
				),
			),
			'rss'              => array(
				'enabled'                 => true,
				'title'                   => '',
				'description'             => '',
				'count'                   => 10,
				'orderby'                 => 'date',
				'order'                   => 'DESC',
				'taxonomy_filter'         => 'all',
				'taxonomy_terms'          => array(),
				'item_title_source'       => 'post_title',
				'item_description_source' => 'caption',
				'item_link_source'        => 'permalink',
				'guid_source'             => 'permalink',
				'image_source'            => 'cover',
				'image_size'              => 'large',
				'image_url_format'        => 'absolute',
				'custom_fields'           => array(),
				'custom_template'         => '',
			),
		);
	}

	/**
	 * Get the full merged options array.
	 *
	 * @return array
	 */
	public function get_all() {
		$saved = get_option( self::OPTION_KEY, array() );
		if ( ! is_array( $saved ) ) {
			$saved = array();
		}
		return $this->merge_recursive( $this->defaults, $saved );
	}

	/**
	 * Recursively merge defaults with saved values (saved wins).
	 *
	 * @param array $defaults Default array.
	 * @param array $saved    Saved array.
	 * @return array
	 */
	private function merge_recursive( $defaults, $saved ) {
		$merged = $defaults;
		foreach ( $saved as $key => $value ) {
			if ( is_array( $value ) && isset( $merged[ $key ] ) && is_array( $merged[ $key ] ) ) {
				$merged[ $key ] = $this->merge_recursive( $merged[ $key ], $value );
			} else {
				$merged[ $key ] = $value;
			}
		}
		return $merged;
	}

	/**
	 * Get a single option value with a dot-path key (e.g. 'viewer.loop').
	 *
	 * @param string $path     Dot-separated path.
	 * @param mixed  $fallback Fallback value.
	 * @return mixed
	 */
	public function get( $path, $fallback = null ) {
		$value = $this->get_all();
		foreach ( explode( '.', $path ) as $segment ) {
			if ( ! is_array( $value ) || ! array_key_exists( $segment, $value ) ) {
				return $fallback;
			}
			$value = $value[ $segment ];
		}
		return $value;
	}

	/**
	 * Derive the internal post type key from the configured slug.
	 *
	 * @return string
	 */
	public function post_type_key() {
		$slug = $this->get( 'post_type.slug', 'client-stories' );
		$key  = sanitize_key( $slug );
		return $key ? $key : 'client_stories';
	}

	/**
	 * Derive the taxonomy key from its slug.
	 *
	 * @return string
	 */
	public function taxonomy_key() {
		$slug = $this->get( 'taxonomy.slug', 'clients' );
		$key  = sanitize_key( $slug );
		return $key ? $key : 'clients';
	}

	/**
	 * Whether the taxonomy is enabled.
	 *
	 * @return bool
	 */
	public function taxonomy_enabled() {
		return (bool) $this->get( 'taxonomy.enabled', false );
	}

	/**
	 * Add the settings submenu under the story CPT admin menu.
	 *
	 * @return void
	 */
	public function add_menu() {
		$key = $this->post_type_key();
		add_submenu_page(
			'edit.php?post_type=' . $key,
			__( 'Visual Stories', 'alf-wp-stories' ),
			__( 'Settings', 'alf-wp-stories' ),
			'manage_options',
			'alf-wp-stories',
			array( $this, 'render_page' )
		);
	}

	/**
	 * Add a Settings link to the plugin row on the Plugins list.
	 *
	 * @param string[] $links Existing links.
	 * @return string[]
	 */
	public function plugin_action_links( $links ) {
		$url = admin_url( 'edit.php?post_type=' . $this->post_type_key() . '&page=alf-wp-stories' );
		array_unshift( $links, '<a href="' . esc_url( $url ) . '">' . esc_html__( 'Settings', 'alf-wp-stories' ) . '</a>' );
		return $links;
	}

	/**
	 * Register settings fields and sections.
	 *
	 * @return void
	 */
	public function register_settings() {
		register_setting(
			'alf_wp_stories_group',
			self::OPTION_KEY,
			array(
				'sanitize_callback' => array( $this, 'sanitize' ),
				'default'           => $this->defaults,
			)
		);

		// --- Settings tab sections ---
		$this->add_section( 'post_type', __( 'Content Type', 'alf-wp-stories' ), self::PAGE_SETTINGS );
		$this->add_field( 'post_type', 'plural', __( 'Plural name', 'alf-wp-stories' ), 'text', self::PAGE_SETTINGS );
		$this->add_field( 'post_type', 'singular', __( 'Singular name', 'alf-wp-stories' ), 'text', self::PAGE_SETTINGS );
		$this->add_field( 'post_type', 'slug', __( 'URL slug', 'alf-wp-stories' ), 'text', self::PAGE_SETTINGS, __( 'Single posts at /{slug}/{story-slug}/ and archive at /{slug}/', 'alf-wp-stories' ) );
		$this->add_field( 'post_type', 'has_archive', __( 'Enable archive', 'alf-wp-stories' ), 'checkbox', self::PAGE_SETTINGS );
		$this->add_field( 'post_type', 'show_in_rest', __( 'Enable REST API', 'alf-wp-stories' ), 'checkbox', self::PAGE_SETTINGS );

		$this->add_section( 'taxonomy', __( 'Taxonomy', 'alf-wp-stories' ), self::PAGE_SETTINGS );
		$this->add_field( 'taxonomy', 'enabled', __( 'Enable taxonomy', 'alf-wp-stories' ), 'checkbox', self::PAGE_SETTINGS );
		$this->add_field( 'taxonomy', 'name', __( 'Taxonomy name', 'alf-wp-stories' ), 'text', self::PAGE_SETTINGS );
		$this->add_field( 'taxonomy', 'slug', __( 'Taxonomy slug', 'alf-wp-stories' ), 'text', self::PAGE_SETTINGS );
		$this->add_field( 'taxonomy', 'hierarchical', __( 'Hierarchical (category-style)', 'alf-wp-stories' ), 'checkbox', self::PAGE_SETTINGS );

		$this->add_section( 'viewer', __( 'Viewer', 'alf-wp-stories' ), self::PAGE_SETTINGS );
		$this->add_field( 'viewer', 'frame_duration', __( 'Default frame duration (ms)', 'alf-wp-stories' ), 'number', self::PAGE_SETTINGS );
		$this->add_field( 'viewer', 'autoplay', __( 'Autoplay', 'alf-wp-stories' ), 'checkbox', self::PAGE_SETTINGS );
		$this->add_field( 'viewer', 'loop', __( 'Loop', 'alf-wp-stories' ), 'checkbox', self::PAGE_SETTINGS );
		$this->add_field( 'viewer', 'show_progress', __( 'Show progress indicators', 'alf-wp-stories' ), 'checkbox', self::PAGE_SETTINGS );
		$this->add_field( 'viewer', 'show_close', __( 'Show close button', 'alf-wp-stories' ), 'checkbox', self::PAGE_SETTINGS );
		$this->add_field( 'viewer', 'keyboard', __( 'Enable keyboard navigation', 'alf-wp-stories' ), 'checkbox', self::PAGE_SETTINGS );
		$this->add_field( 'viewer', 'swipe', __( 'Enable swipe navigation', 'alf-wp-stories' ), 'checkbox', self::PAGE_SETTINGS );

		$this->add_section( 'story', __( 'Story Defaults', 'alf-wp-stories' ), self::PAGE_SETTINGS );
		$this->add_field( 'story', 'default_cover_source', __( 'Default cover source', 'alf-wp-stories' ), 'select', self::PAGE_SETTINGS, '', array( 'first_frame' => 'First frame', 'last_frame' => 'Last frame', 'largest' => 'Largest frame' ) );
		$this->add_field( 'story', 'default_post_status', __( 'Default post status (batch create)', 'alf-wp-stories' ), 'select', self::PAGE_SETTINGS, '', array( 'publish' => 'Publish', 'draft' => 'Draft' ) );
		$this->add_field( 'story', 'auto_open_on_single', __( 'Auto-open viewer on single story page', 'alf-wp-stories' ), 'checkbox', self::PAGE_SETTINGS );

		$this->add_section( 'theming', __( 'Theming', 'alf-wp-stories' ), self::PAGE_SETTINGS );
		$this->add_field( 'theming', 'accent', __( 'Accent color (ring + progress)', 'alf-wp-stories' ), 'color', self::PAGE_SETTINGS );
		$this->add_field( 'theming', 'ring_width', __( 'Ring width (px)', 'alf-wp-stories' ), 'number', self::PAGE_SETTINGS );
		$this->add_field( 'theming', 'ring_style', __( 'Ring style', 'alf-wp-stories' ), 'select', self::PAGE_SETTINGS, '', array( 'solid' => 'Solid', 'gradient' => 'Gradient' ) );
		$this->add_field( 'theming', 'overlay', __( 'Viewer overlay background', 'alf-wp-stories' ), 'text', self::PAGE_SETTINGS, __( 'Any CSS color, e.g. rgba(20,22,26,0.96)', 'alf-wp-stories' ) );
		$this->add_field( 'theming', 'launcher_background', __( 'Launcher placeholder background', 'alf-wp-stories' ), 'color', self::PAGE_SETTINGS );

		$this->add_section( 'rss', __( 'RSS Feed', 'alf-wp-stories' ), self::PAGE_SETTINGS );
		$this->add_field( 'rss', 'enabled', __( 'Enable dedicated feed', 'alf-wp-stories' ), 'checkbox', self::PAGE_SETTINGS );
		$this->add_field( 'rss', 'title', __( 'Feed title', 'alf-wp-stories' ), 'text', self::PAGE_SETTINGS, __( 'Leave empty to use the site name.', 'alf-wp-stories' ) );
		$this->add_field( 'rss', 'description', __( 'Feed description', 'alf-wp-stories' ), 'textarea', self::PAGE_SETTINGS );
		$this->add_field( 'rss', 'count', __( 'Number of items', 'alf-wp-stories' ), 'number', self::PAGE_SETTINGS );
		$this->add_field( 'rss', 'orderby', __( 'Ordering', 'alf-wp-stories' ), 'select', self::PAGE_SETTINGS, '', array( 'date' => 'Date', 'title' => 'Title', 'menu_order' => 'Menu order' ) );
		$this->add_field( 'rss', 'order', __( 'Order direction', 'alf-wp-stories' ), 'select', self::PAGE_SETTINGS, '', array( 'DESC' => 'Descending', 'ASC' => 'Ascending' ) );
		$this->add_field( 'rss', 'taxonomy_filter', __( 'Taxonomy filtering', 'alf-wp-stories' ), 'select', self::PAGE_SETTINGS, '', array( 'all' => 'All terms', 'include' => 'Only selected terms', 'exclude' => 'Exclude selected terms' ) );
		$this->add_field( 'rss', 'taxonomy_terms', __( 'Taxonomy terms (comma-separated slugs)', 'alf-wp-stories' ), 'text', self::PAGE_SETTINGS );
		$this->add_field( 'rss', 'item_title_source', __( 'Item title source', 'alf-wp-stories' ), 'select', self::PAGE_SETTINGS, '', array( 'post_title' => 'Post title', 'caption' => 'Caption', 'custom' => 'Custom template' ) );
		$this->add_field( 'rss', 'item_description_source', __( 'Item description source', 'alf-wp-stories' ), 'select', self::PAGE_SETTINGS, '', array( 'caption' => 'Caption', 'post_excerpt' => 'Excerpt', 'none' => 'None' ) );
		$this->add_field( 'rss', 'image_source', __( 'Image source', 'alf-wp-stories' ), 'select', self::PAGE_SETTINGS, '', array( 'cover' => 'Cover image', 'social_cover' => 'Social cover (compliant)', 'first_frame' => 'First frame', 'custom' => 'Custom field' ) );
		$this->add_field( 'rss', 'image_size', __( 'Image size', 'alf-wp-stories' ), 'text', self::PAGE_SETTINGS, __( 'e.g. large, full, or a registered size.', 'alf-wp-stories' ) );
		$this->add_field( 'rss', 'image_url_format', __( 'Image URL format', 'alf-wp-stories' ), 'select', self::PAGE_SETTINGS, '', array( 'absolute' => 'Absolute HTTPS', 'relative' => 'Relative' ) );
		$this->add_field( 'rss', 'custom_fields', __( 'Custom fields (comma-separated meta keys)', 'alf-wp-stories' ), 'text', self::PAGE_SETTINGS );
		$this->add_field( 'rss', 'custom_template', __( 'Custom item template (placeholders: {title} {description} {link} {guid} {image})', 'alf-wp-stories' ), 'textarea', self::PAGE_SETTINGS );

		// --- Auto-Post tab section ---
		$this->add_section( 'filename_grammar', __( 'Filename Variable Extraction', 'alf-wp-stories' ), self::PAGE_AUTOP_POST );
		add_settings_field(
			'alf_wp_stories_filename_grammar_segments',
			__( 'Segment mapping', 'alf-wp-stories' ),
			array( $this, 'render_grammar_field' ),
			self::PAGE_AUTOP_POST,
			'alf_wp_stories_filename_grammar'
		);
	}

	/**
	 * Add a settings section.
	 *
	 * @param string $id    Section id.
	 * @param string $title Section title.
	 * @param string $page Page slug.
	 * @return void
	 */
	private function add_section( $id, $title, $page ) {
		add_settings_section(
			'alf_wp_stories_' . $id,
			$title,
			'__return_false',
			$page
		);
	}

	/**
	 * Add a settings field.
	 *
	 * @param string $section Section id.
	 * @param string $key     Field key within the section.
	 * @param string $label   Field label.
	 * @param string $type    Field type (text, number, checkbox, textarea, select, color).
	 * @param string $page    Page slug.
	 * @param string $help    Optional help text.
	 * @param array  $choices Choices for select fields.
	 * @return void
	 */
	private function add_field( $section, $key, $label, $type, $page, $help = '', $choices = array() ) {
		add_settings_field(
			'alf_wp_stories_' . $section . '_' . $key,
			$label,
			array( $this, 'render_field' ),
			$page,
			'alf_wp_stories_' . $section,
			array(
				'section' => $section,
				'key'     => $key,
				'type'    => $type,
				'help'    => $help,
				'choices' => $choices,
			)
		);
	}

	/**
	 * Render a single settings field.
	 *
	 * @param array $args Field arguments from add_settings_field.
	 * @return void
	 */
	public function render_field( $args ) {
		$section = $args['section'];
		$key     = $args['key'];
		$type    = $args['type'];
		$value   = $this->get( $section . '.' . $key );
		$name    = self::OPTION_KEY . '[' . $section . '][' . $key . ']';
		$id      = 'alf_wp_stories_' . $section . '_' . $key;

		switch ( $type ) {
			case 'checkbox':
				printf(
					'<input type="checkbox" id="%1$s" name="%2$s" value="1" %3$s />',
					esc_attr( $id ),
					esc_attr( $name ),
					checked( 1, (bool) $value, false )
				);
				break;

			case 'number':
				printf(
					'<input type="number" id="%1$s" name="%2$s" value="%3$s" class="small-text" />',
					esc_attr( $id ),
					esc_attr( $name ),
					esc_attr( (string) $value )
				);
				break;

			case 'color':
				printf(
					'<input type="text" id="%1$s" name="%2$s" value="%3$s" class="alf-wp-stories-color-field" />',
					esc_attr( $id ),
					esc_attr( $name ),
					esc_attr( (string) $value )
				);
				break;

			case 'textarea':
				printf(
					'<textarea id="%1$s" name="%2$s" rows="4" class="large-text">%3$s</textarea>',
					esc_attr( $id ),
					esc_attr( $name ),
					esc_textarea( (string) $value )
				);
				break;

			case 'select':
				printf( '<select id="%1$s" name="%2$s">', esc_attr( $id ), esc_attr( $name ) );
				foreach ( $args['choices'] as $choice_value => $choice_label ) {
					printf(
						'<option value="%1$s" %2$s>%3$s</option>',
						esc_attr( $choice_value ),
						selected( (string) $value, (string) $choice_value, false ),
						esc_html( $choice_label )
					);
				}
				echo '</select>';
				break;

			case 'text':
			default:
				printf(
					'<input type="text" id="%1$s" name="%2$s" value="%3$s" class="regular-text" />',
					esc_attr( $id ),
					esc_attr( $name ),
					esc_attr( (string) $value )
				);
				break;
		}

		if ( ! empty( $args['help'] ) ) {
			printf( '<p class="description">%s</p>', esc_html( $args['help'] ) );
		}
	}

	/**
	 * Render the filename-grammar segment repeater.
	 *
	 * @return void
	 */
	public function render_grammar_field() {
		$grammar  = $this->get( 'filename_grammar', array() );
		$segments = isset( $grammar['segments'] ) && is_array( $grammar['segments'] ) ? $grammar['segments'] : array();

		$targets = self::grammar_targets();
		$transforms = self::grammar_transforms();

		echo '<p class="description">' . esc_html__( 'Delimiter is fixed to "__" (double underscore). Map each filename segment to a story field.', 'alf-wp-stories' ) . '</p>';

		// Group key selector.
		$group_key = isset( $grammar['group_key'] ) ? $grammar['group_key'] : 'title';
		printf(
			'<p><label><strong>%s</strong> ',
			esc_html__( 'Group images into one story by:', 'alf-wp-stories' )
		);
		printf( '<select name="%s">', esc_attr( self::OPTION_KEY . '[filename_grammar][group_key]' ) );
		foreach ( $targets as $tval => $tlabel ) {
			printf( '<option value="%s" %s>%s</option>', esc_attr( $tval ), selected( $group_key, $tval, false ), esc_html( $tlabel ) );
		}
		echo '</select></label></p>';

		echo '<table class="widefat striped alf-wp-stories-grammar-table" style="max-width:640px;">';
		echo '<thead><tr><th>#</th><th>Target</th><th>Transform</th><th></th></tr></thead><tbody>';
		foreach ( $segments as $i => $seg ) {
			$this->grammar_row( $i, $seg, $targets, $transforms );
		}
		echo '</tbody></table>';
		printf( '<p><button type="button" class="button alf-wp-stories-grammar-add">%s</button></p>', esc_html__( 'Add segment', 'alf-wp-stories' ) );

		// Live preview tool.
		echo '<hr /><h3>' . esc_html__( 'Live preview', 'alf-wp-stories' ) . '</h3>';
		printf( '<p><input type="text" id="alf-wp-stories-grammar-preview-input" class="regular-text" placeholder="e.g. mabel__01.jpg" /> <button type="button" class="button" id="alf-wp-stories-grammar-preview-btn">%s</button></p>', esc_html__( 'Preview', 'alf-wp-stories' ) );
		echo '<div id="alf-wp-stories-grammar-preview-out" class="alf-wp-stories-grammar-preview"></div>';
	}

	/**
	 * Output one grammar segment row.
	 *
	 * @param int   $i         Index.
	 * @param array $seg       Segment {target, transform}.
	 * @param array $targets   Target options.
	 * @param array $transforms Transform options.
	 * @return void
	 */
	private function grammar_row( $i, $seg, $targets, $transforms ) {
		$target    = isset( $seg['target'] ) ? $seg['target'] : 'ignore';
		$transform = isset( $seg['transform'] ) ? $seg['transform'] : 'raw';
		$base      = self::OPTION_KEY . '[filename_grammar][segments][' . (int) $i . ']';
		echo '<tr>';
		echo '<td>' . (int) ( $i + 1 ) . '</td>';
		echo '<td><select name="' . esc_attr( $base ) . '[target]">';
		foreach ( $targets as $tval => $tlabel ) {
			echo '<option value="' . esc_attr( $tval ) . '" ' . selected( $target, $tval, false ) . '>' . esc_html( $tlabel ) . '</option>';
		}
		echo '</select></td>';
		echo '<td><select name="' . esc_attr( $base ) . '[transform]">';
		foreach ( $transforms as $tval => $tlabel ) {
			echo '<option value="' . esc_attr( $tval ) . '" ' . selected( $transform, $tval, false ) . '>' . esc_html( $tlabel ) . '</option>';
		}
		echo '</select></td>';
		echo '<td><button type="button" class="button-link-delete alf-wp-stories-grammar-remove">' . esc_html__( 'Remove', 'alf-wp-stories' ) . '</button></td>';
		echo '</tr>';
	}

	/**
	 * Allowed segment targets.
	 *
	 * @return array
	 */
	public static function grammar_targets() {
		return array(
			'title'        => __( 'Story title', 'alf-wp-stories' ),
			'caption'       => __( 'Caption', 'alf-wp-stories' ),
			'tag'           => __( 'Tag (repeatable)', 'alf-wp-stories' ),
			'frame_order'  => __( 'Frame order (integer)', 'alf-wp-stories' ),
			'ignore'        => __( 'Ignore', 'alf-wp-stories' ),
		);
	}

	/**
	 * Allowed segment transforms.
	 *
	 * @return array
	 */
	public static function grammar_transforms() {
		return array(
			'raw'        => __( 'Raw', 'alf-wp-stories' ),
			'title_case' => __( 'Title case', 'alf-wp-stories' ),
			'slugify'    => __( 'Slugify', 'alf-wp-stories' ),
			'integer'    => __( 'Integer', 'alf-wp-stories' ),
		);
	}

	/**
	 * Sanitize the full options array on save.
	 *
	 * @param mixed $input Raw submitted options.
	 * @return array Sanitized options (merged with defaults).
	 */
	public function sanitize( $input ) {
		if ( ! is_array( $input ) ) {
			$input = array();
		}
		$d = $this->defaults;

		$clean = array(
			'post_type' => array(
				'plural'       => isset( $input['post_type']['plural'] ) ? sanitize_text_field( $input['post_type']['plural'] ) : $d['post_type']['plural'],
				'singular'     => isset( $input['post_type']['singular'] ) ? sanitize_text_field( $input['post_type']['singular'] ) : $d['post_type']['singular'],
				'slug'         => isset( $input['post_type']['slug'] ) ? sanitize_title( $input['post_type']['slug'] ) : $d['post_type']['slug'],
				'has_archive'  => ! empty( $input['post_type']['has_archive'] ),
				'show_in_rest' => ! empty( $input['post_type']['show_in_rest'] ),
			),
			'taxonomy'  => array(
				'enabled'      => ! empty( $input['taxonomy']['enabled'] ),
				'name'         => isset( $input['taxonomy']['name'] ) ? sanitize_text_field( $input['taxonomy']['name'] ) : $d['taxonomy']['name'],
				'slug'         => isset( $input['taxonomy']['slug'] ) ? sanitize_title( $input['taxonomy']['slug'] ) : $d['taxonomy']['slug'],
				'hierarchical' => ! empty( $input['taxonomy']['hierarchical'] ),
			),
			'viewer'    => array(
				'frame_duration' => isset( $input['viewer']['frame_duration'] ) ? absint( $input['viewer']['frame_duration'] ) : $d['viewer']['frame_duration'],
				'autoplay'       => ! empty( $input['viewer']['autoplay'] ),
				'loop'           => ! empty( $input['viewer']['loop'] ),
				'show_progress'  => ! empty( $input['viewer']['show_progress'] ),
				'show_close'     => ! empty( $input['viewer']['show_close'] ),
				'keyboard'       => ! empty( $input['viewer']['keyboard'] ),
				'swipe'          => ! empty( $input['viewer']['swipe'] ),
			),
			'story'     => array(
				'default_cover_source' => isset( $input['story']['default_cover_source'] ) && in_array( $input['story']['default_cover_source'], array( 'first_frame', 'last_frame', 'largest' ), true ) ? $input['story']['default_cover_source'] : 'first_frame',
				'default_post_status'  => isset( $input['story']['default_post_status'] ) && 'draft' === $input['story']['default_post_status'] ? 'draft' : 'publish',
				'auto_open_on_single'  => ! empty( $input['story']['auto_open_on_single'] ),
			),
			'theming'   => array(
				'accent'              => isset( $input['theming']['accent'] ) ? sanitize_hex_color( $input['theming']['accent'] ) : $d['theming']['accent'],
				'ring_width'         => isset( $input['theming']['ring_width'] ) ? absint( $input['theming']['ring_width'] ) : $d['theming']['ring_width'],
				'ring_style'         => isset( $input['theming']['ring_style'] ) && 'gradient' === $input['theming']['ring_style'] ? 'gradient' : 'solid',
				'overlay'            => isset( $input['theming']['overlay'] ) ? sanitize_text_field( $input['theming']['overlay'] ) : $d['theming']['overlay'],
				'launcher_background' => isset( $input['theming']['launcher_background'] ) ? sanitize_hex_color( $input['theming']['launcher_background'] ) : $d['theming']['launcher_background'],
			),
			'filename_grammar' => $this->sanitize_grammar( isset( $input['filename_grammar'] ) ? $input['filename_grammar'] : array() ),
			'rss'       => array(
				'enabled'                 => ! empty( $input['rss']['enabled'] ),
				'title'                   => isset( $input['rss']['title'] ) ? sanitize_text_field( $input['rss']['title'] ) : $d['rss']['title'],
				'description'             => isset( $input['rss']['description'] ) ? sanitize_textarea_field( $input['rss']['description'] ) : $d['rss']['description'],
				'count'                   => isset( $input['rss']['count'] ) ? absint( $input['rss']['count'] ) : $d['rss']['count'],
				'orderby'                 => isset( $input['rss']['orderby'] ) ? sanitize_key( $input['rss']['orderby'] ) : $d['rss']['orderby'],
				'order'                   => isset( $input['rss']['order'] ) && 'ASC' === strtoupper( $input['rss']['order'] ) ? 'ASC' : 'DESC',
				'taxonomy_filter'         => isset( $input['rss']['taxonomy_filter'] ) ? sanitize_key( $input['rss']['taxonomy_filter'] ) : $d['rss']['taxonomy_filter'],
				'taxonomy_terms'          => isset( $input['rss']['taxonomy_terms'] ) ? array_filter( array_map( 'sanitize_title', explode( ',', (string) $input['rss']['taxonomy_terms'] ) ) ) : $d['rss']['taxonomy_terms'],
				'item_title_source'       => isset( $input['rss']['item_title_source'] ) ? sanitize_key( $input['rss']['item_title_source'] ) : $d['rss']['item_title_source'],
				'item_description_source' => isset( $input['rss']['item_description_source'] ) ? sanitize_key( $input['rss']['item_description_source'] ) : $d['rss']['item_description_source'],
				'item_link_source'        => isset( $input['rss']['item_link_source'] ) ? sanitize_key( $input['rss']['item_link_source'] ) : $d['rss']['item_link_source'],
				'guid_source'             => isset( $input['rss']['guid_source'] ) ? sanitize_key( $input['rss']['guid_source'] ) : $d['rss']['guid_source'],
				'image_source'            => isset( $input['rss']['image_source'] ) ? sanitize_key( $input['rss']['image_source'] ) : $d['rss']['image_source'],
				'image_size'              => isset( $input['rss']['image_size'] ) ? sanitize_key( $input['rss']['image_size'] ) : $d['rss']['image_size'],
				'image_url_format'        => isset( $input['rss']['image_url_format'] ) ? sanitize_key( $input['rss']['image_url_format'] ) : $d['rss']['image_url_format'],
				'custom_fields'           => isset( $input['rss']['custom_fields'] ) ? array_filter( array_map( 'sanitize_key', explode( ',', (string) $input['rss']['custom_fields'] ) ) ) : $d['rss']['custom_fields'],
				'custom_template'         => isset( $input['rss']['custom_template'] ) ? wp_kses_post( $input['rss']['custom_template'] ) : $d['rss']['custom_template'],
			),
		);

		return $clean;
	}

	/**
	 * Sanitize the filename grammar.
	 *
	 * @param array $input Raw grammar.
	 * @return array
	 */
	private function sanitize_grammar( $input ) {
		$d = $this->defaults['filename_grammar'];
		$targets = array_keys( self::grammar_targets() );
		$transforms = array_keys( self::grammar_transforms() );

		$segments = array();
		if ( isset( $input['segments'] ) && is_array( $input['segments'] ) ) {
			foreach ( $input['segments'] as $seg ) {
				if ( ! is_array( $seg ) ) {
					continue;
			}
				$target    = isset( $seg['target'] ) && in_array( $seg['target'], $targets, true ) ? $seg['target'] : 'ignore';
				$transform = isset( $seg['transform'] ) && in_array( $seg['transform'], $transforms, true ) ? $seg['transform'] : 'raw';
				$segments[] = array(
					'target'    => $target,
					'transform' => $transform,
				);
			}
		}
		if ( empty( $segments ) ) {
			$segments = $d['segments'];
		}

		$group_key = isset( $input['group_key'] ) && in_array( $input['group_key'], $targets, true ) ? $input['group_key'] : 'title';

		return array(
			'delimiter' => '__', // fixed.
			'group_key' => $group_key,
			'segments'  => $segments,
		);
	}

	/**
	 * Enqueue admin assets on the settings screen only.
	 *
	 * @param string $hook Current admin screen hook suffix.
	 * @return void
	 */
	public function enqueue_admin( $hook ) {
		$is_settings = ( false !== strpos( (string) $hook, 'page_alf-wp-stories' ) );
		if ( $is_settings ) {
			wp_enqueue_style( 'wp-color-picker' );
			wp_enqueue_style( 'alf-wp-stories-admin', ALF_WP_STORIES_URL . 'assets/css/admin.css', array(), ALF_WP_STORIES_VERSION );
			wp_enqueue_script( 'alf-wp-stories-admin-grammar', ALF_WP_STORIES_URL . 'assets/js/admin-grammar.js', array( 'jquery', 'wp-color-picker' ), ALF_WP_STORIES_VERSION, true );
			wp_localize_script( 'alf-wp-stories-admin-grammar', 'alfWpStoriesGrammar', array(
				'nonce'     => wp_create_nonce( 'alf_wp_stories_preview_filename' ),
				'ajaxUrl'    => admin_url( 'admin-ajax.php' ),
				'targets'    => self::grammar_targets(),
				'transforms' => self::grammar_transforms(),
			) );
		}
	}

	/**
	 * Render the tabbed settings page.
	 *
	 * @return void
	 */
	public function render_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$feed_url = home_url( user_trailingslashit( $this->get( 'post_type.slug', 'client-stories' ) ) . 'feed/' );
		$singular = $this->get( 'post_type.singular', 'Client Story' );
		$plural   = $this->get( 'post_type.plural', 'Client Stories' );
		$shortcode = '[visual_story_launcher limit="1"]';
		?>
		<div class="wrap alf-wp-stories-settings">
			<h1><?php esc_html_e( 'Visual Stories', 'alf-wp-stories' ); ?></h1>
			<nav class="nav-tab-wrapper" style="margin-bottom:1.5em;">
				<a href="#alf-wp-stories-tab-settings" class="nav-tab nav-tab-active" data-tab="alf-wp-stories-tab-settings"><?php esc_html_e( 'Settings', 'alf-wp-stories' ); ?></a>
				<a href="#alf-wp-stories-tab-autopost" class="nav-tab" data-tab="alf-wp-stories-tab-autopost"><?php esc_html_e( 'Auto-Post', 'alf-wp-stories' ); ?></a>
				<a href="#alf-wp-stories-tab-help" class="nav-tab" data-tab="alf-wp-stories-tab-help"><?php esc_html_e( 'Help', 'alf-wp-stories' ); ?></a>
			</nav>

			<form method="post" action="options.php">
				<?php settings_fields( 'alf_wp_stories_group' ); ?>

				<div id="alf-wp-stories-tab-settings">
					<?php do_settings_sections( self::PAGE_SETTINGS ); ?>
				</div>

				<div id="alf-wp-stories-tab-autopost" style="display:none;">
					<?php do_settings_sections( self::PAGE_AUTOP_POST ); ?>
				</div>

				<div id="alf-wp-stories-tab-help" style="display:none;">
					<h2><?php esc_html_e( 'How to use', 'alf-wp-stories' ); ?></h2>
					<ol>
						<li><?php esc_html_e( 'Name your image files using the filename convention (see Auto-Post tab).', 'alf-wp-stories' ); ?></li>
						<li><?php esc_html_e( 'Upload images to the Media Library.', 'alf-wp-stories' ); ?></li>
						<li><?php esc_html_e( 'Select images, choose "Create Stories" from Bulk Actions, confirm in the modal.', 'alf-wp-stories' ); ?></li>
						<li><?php esc_html_e( 'Add the Story Launcher block or the shortcode below to any page.', 'alf-wp-stories' ); ?></li>
					</ol>
					<h3><?php esc_html_e( 'Shortcode', 'alf-wp-stories' ); ?></h3>
					<p><code><?php echo esc_html( $shortcode ); ?></code></p>
					<p class="description"><?php esc_html_e( 'Optional parameters: story, limit, taxonomy, term, size, show_title, ring.', 'alf-wp-stories' ); ?></p>

					<h3><?php esc_html_e( 'RSS Feed', 'alf-wp-stories' ); ?></h3>
					<p><code><?php echo esc_html( $feed_url ); ?></code></p>
					<?php if ( $this->get( 'rss.enabled', true ) ) : ?>
						<p><a class="button" href="<?php echo esc_url( $feed_url ); ?>" target="_blank" rel="noopener"><?php esc_html_e( 'Open feed', 'alf-wp-stories' ); ?></a></p>
					<?php else : ?>
						<p class="description"><?php esc_html_e( 'The feed is currently disabled.', 'alf-wp-stories' ); ?></p>
					<?php endif; ?>
				</div>

				<?php submit_button(); ?>
			</form>
		</div>
		<script>
		( function () {
			var tabs = document.querySelectorAll( '.alf-wp-stories-settings .nav-tab' );
			tabs.forEach( function ( tab ) {
				tab.addEventListener( 'click', function ( e ) {
					e.preventDefault();
				tabs.forEach( function ( t ) { t.classList.remove( 'nav-tab-active' ); } );
				['settings','autopost','help'].forEach( function ( k ) {
					document.getElementById( 'alf-wp-stories-tab-' + k ).style.display = 'none';
				} );
				tab.classList.add( 'nav-tab-active' );
				document.getElementById( tab.dataset.tab ).style.display = '';
			} );
			} );
		} )();
		</script>
		<?php
	}

	/**
	 * Build scoped CSS variables for theming, applied to the viewer/launcher.
	 *
	 * Only emits properties that differ from defaults, to avoid global noise.
	 *
	 * @return string CSS rules (may be empty).
	 */
	public function theming_css() {
		$t = $this->get( 'theming', array() );
		$d = $this->defaults['theming'];
		$vars = array();
		if ( isset( $t['accent'] ) && $t['accent'] !== $d['accent'] ) {
			$vars['--alf-wp-stories-accent'] = $t['accent'];
		}
		if ( isset( $t['ring_width'] ) && (int) $t['ring_width'] !== (int) $d['ring_width'] ) {
			$vars['--alf-wp-stories-ring-width'] = (int) $t['ring_width'] . 'px';
		}
		if ( isset( $t['ring_style'] ) && $t['ring_style'] !== $d['ring_style'] ) {
			$vars['--alf-wp-stories-ring-style'] = $t['ring_style'];
		}
		if ( isset( $t['overlay'] ) && $t['overlay'] !== $d['overlay'] ) {
			$vars['--alf-wp-stories-overlay'] = $t['overlay'];
		}
		if ( isset( $t['launcher_background'] ) && $t['launcher_background'] !== $d['launcher_background'] ) {
			$vars['--alf-wp-stories-launcher-bg'] = $t['launcher_background'];
		}
		if ( empty( $vars ) ) {
			return '';
		}
		$css = '';
		foreach ( $vars as $prop => $val ) {
			$css .= $prop . ':' . $val . ';';
		}
		return '.alf-wp-stories-viewer,.alf-wp-stories-launcher{' . $css . '}';
	}
}

