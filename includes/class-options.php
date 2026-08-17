<?php
/**
 * Plugin options: defaults, storage, sanitization, and the settings page.
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
	}

	/**
	 * Build the canonical defaults.
	 *
	 * @return array
	 */
	private function build_defaults() {
		return array(
			'post_type' => array(
				'plural'        => 'Client Stories',
				'singular'      => 'Client Story',
				'slug'          => 'client-stories',
				'has_archive'   => true,
				'show_in_rest'  => true,
			),
			'taxonomy'  => array(
				'enabled'      => true,
				'name'         => 'Clients',
				'slug'         => 'clients',
				'hierarchical' => false,
			),
			'viewer'    => array(
				'frame_duration'   => 5000,
				'autoplay'         => true,
				'loop'             => true,
				'show_progress'    => true,
				'show_close'       => true,
				'keyboard'         => true,
				'swipe'            => true,
			),
			'rss'       => array(
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
	 * Register the settings submenu.
	 *
	 * @return void
	 */
	public function add_menu() {
		add_options_page(
			__( 'Visual Stories', 'alf-wp-stories' ),
			__( 'Visual Stories', 'alf-wp-stories' ),
			'manage_options',
			'alf-wp-stories',
			array( $this, 'render_page' )
		);
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

		$this->add_section( 'post_type', __( 'Content Type', 'alf-wp-stories' ) );
		$this->add_field( 'post_type', 'plural', __( 'Plural name', 'alf-wp-stories' ), 'text' );
		$this->add_field( 'post_type', 'singular', __( 'Singular name', 'alf-wp-stories' ), 'text' );
		$this->add_field( 'post_type', 'slug', __( 'URL slug', 'alf-wp-stories' ), 'text', __( 'Single posts at /{slug}/{story-slug}/ and archive at /{slug}/', 'alf-wp-stories' ) );
		$this->add_field( 'post_type', 'has_archive', __( 'Enable archive', 'alf-wp-stories' ), 'checkbox' );
		$this->add_field( 'post_type', 'show_in_rest', __( 'Enable REST API', 'alf-wp-stories' ), 'checkbox' );

		$this->add_section( 'taxonomy', __( 'Taxonomy', 'alf-wp-stories' ) );
		$this->add_field( 'taxonomy', 'enabled', __( 'Enable taxonomy', 'alf-wp-stories' ), 'checkbox' );
		$this->add_field( 'taxonomy', 'name', __( 'Taxonomy name', 'alf-wp-stories' ), 'text' );
		$this->add_field( 'taxonomy', 'slug', __( 'Taxonomy slug', 'alf-wp-stories' ), 'text' );
		$this->add_field( 'taxonomy', 'hierarchical', __( 'Hierarchical (category-style)', 'alf-wp-stories' ), 'checkbox' );

		$this->add_section( 'viewer', __( 'Viewer', 'alf-wp-stories' ) );
		$this->add_field( 'viewer', 'frame_duration', __( 'Default frame duration (ms)', 'alf-wp-stories' ), 'number' );
		$this->add_field( 'viewer', 'autoplay', __( 'Autoplay', 'alf-wp-stories' ), 'checkbox' );
		$this->add_field( 'viewer', 'loop', __( 'Loop', 'alf-wp-stories' ), 'checkbox' );
		$this->add_field( 'viewer', 'show_progress', __( 'Show progress indicators', 'alf-wp-stories' ), 'checkbox' );
		$this->add_field( 'viewer', 'show_close', __( 'Show close button', 'alf-wp-stories' ), 'checkbox' );
		$this->add_field( 'viewer', 'keyboard', __( 'Enable keyboard navigation', 'alf-wp-stories' ), 'checkbox' );
		$this->add_field( 'viewer', 'swipe', __( 'Enable swipe navigation', 'alf-wp-stories' ), 'checkbox' );

		$this->add_section( 'rss', __( 'RSS Feed', 'alf-wp-stories' ) );
		$this->add_field( 'rss', 'enabled', __( 'Enable dedicated feed', 'alf-wp-stories' ), 'checkbox' );
		$this->add_field( 'rss', 'title', __( 'Feed title', 'alf-wp-stories' ), 'text', __( 'Leave empty to use the site name.', 'alf-wp-stories' ) );
		$this->add_field( 'rss', 'description', __( 'Feed description', 'alf-wp-stories' ), 'textarea' );
		$this->add_field( 'rss', 'count', __( 'Number of items', 'alf-wp-stories' ), 'number' );
		$this->add_field( 'rss', 'orderby', __( 'Ordering', 'alf-wp-stories' ), 'select', '', array( 'date' => 'Date', 'title' => 'Title', 'menu_order' => 'Menu order' ) );
		$this->add_field( 'rss', 'order', __( 'Order direction', 'alf-wp-stories' ), 'select', '', array( 'DESC' => 'Descending', 'ASC' => 'Ascending' ) );
		$this->add_field( 'rss', 'taxonomy_filter', __( 'Taxonomy filtering', 'alf-wp-stories' ), 'select', '', array( 'all' => 'All terms', 'include' => 'Only selected terms', 'exclude' => 'Exclude selected terms' ) );
		$this->add_field( 'rss', 'taxonomy_terms', __( 'Taxonomy terms (comma-separated slugs)', 'alf-wp-stories' ), 'text' );
		$this->add_field( 'rss', 'item_title_source', __( 'Item title source', 'alf-wp-stories' ), 'select', '', array( 'post_title' => 'Post title', 'caption' => 'Caption', 'custom' => 'Custom template' ) );
		$this->add_field( 'rss', 'item_description_source', __( 'Item description source', 'alf-wp-stories' ), 'select', '', array( 'caption' => 'Caption', 'post_excerpt' => 'Excerpt', 'none' => 'None' ) );
		$this->add_field( 'rss', 'image_source', __( 'Image source', 'alf-wp-stories' ), 'select', '', array( 'cover' => 'Cover image', 'first_frame' => 'First frame', 'custom' => 'Custom field' ) );
		$this->add_field( 'rss', 'image_size', __( 'Image size', 'alf-wp-stories' ), 'text', __( 'e.g. large, full, or a registered size.', 'alf-wp-stories' ) );
		$this->add_field( 'rss', 'image_url_format', __( 'Image URL format', 'alf-wp-stories' ), 'select', '', array( 'absolute' => 'Absolute HTTPS', 'relative' => 'Relative' ) );
		$this->add_field( 'rss', 'custom_fields', __( 'Custom fields (comma-separated meta keys)', 'alf-wp-stories' ), 'text' );
		$this->add_field( 'rss', 'custom_template', __( 'Custom item template (placeholders: {title} {description} {link} {guid} {image})', 'alf-wp-stories' ), 'textarea' );
	}

	/**
	 * Add a settings section.
	 *
	 * @param string $id    Section id.
	 * @param string $title Section title.
	 * @return void
	 */
	private function add_section( $id, $title ) {
		add_settings_section(
			'alf_wp_stories_' . $id,
			$title,
			'__return_false',
			'alf-wp-stories'
		);
	}

	/**
	 * Add a settings field.
	 *
	 * @param string $section Section id.
	 * @param string $key     Field key within the section.
	 * @param string $label   Field label.
	 * @param string $type    Field type (text, number, checkbox, textarea, select).
	 * @param string $help    Optional help text.
	 * @param array  $choices Choices for select fields.
	 * @return void
	 */
	private function add_field( $section, $key, $label, $type, $help = '', $choices = array() ) {
		add_settings_field(
			'alf_wp_stories_' . $section . '_' . $key,
			$label,
			array( $this, 'render_field' ),
			'alf-wp-stories',
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
	 * Enqueue admin assets on the settings screen only.
	 *
	 * @param string $hook Current admin screen hook suffix.
	 * @return void
	 */
	public function enqueue_admin( $hook ) {
		if ( 'settings_page_alf-wp-stories' !== $hook ) {
			return;
		}
		wp_enqueue_style(
			'alf-wp-stories-admin',
			ALF_WP_STORIES_URL . 'assets/css/admin.css',
			array(),
			ALF_WP_STORIES_VERSION
		);
	}

	/**
	 * Render the settings page, including a feed preview/test tool.
	 *
	 * @return void
	 */
	public function render_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$feed_url = home_url( user_trailingslashit( $this->get( 'post_type.slug', 'client-stories' ) ) . 'feed/' );
		?>
		<div class="wrap alf-wp-stories-settings">
			<h1><?php esc_html_e( 'Visual Stories', 'alf-wp-stories' ); ?></h1>
			<form method="post" action="options.php">
				<?php
				settings_fields( 'alf_wp_stories_group' );
				do_settings_sections( 'alf-wp-stories' );
				submit_button();
				?>
			</form>

			<hr />
			<h2><?php esc_html_e( 'Feed Preview &amp; Test', 'alf-wp-stories' ); ?></h2>
			<p>
				<?php esc_html_e( 'Dedicated feed URL:', 'alf-wp-stories' ); ?>
				<code><?php echo esc_html( $feed_url ); ?></code>
			</p>
			<?php if ( $this->get( 'rss.enabled', true ) ) : ?>
				<p>
					<a class="button" href="<?php echo esc_url( $feed_url ); ?>" target="_blank" rel="noopener">
						<?php esc_html_e( 'Open feed', 'alf-wp-stories' ); ?>
					</a>
				</p>
			<?php else : ?>
				<p class="description"><?php esc_html_e( 'The feed is currently disabled.', 'alf-wp-stories' ); ?></p>
			<?php endif; ?>
		</div>
		<?php
	}
}
