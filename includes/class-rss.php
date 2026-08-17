<?php
/**
 * Dedicated, configurable RSS feed for the story post type.
 *
 * @package ALF_WP_Stories
 */

namespace ALF_WP_Stories;

defined( 'ABSPATH' ) || exit;

/**
 * RSS feed generator.
 */
class Rss {

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
	 * The dedicated feed is served by intercepting `template_redirect`, which
	 * fires before core's own `do_feed()`, so we fully control the output for
	 * this post type's archive feed without affecting any other site feed.
	 *
	 * @return void
	 */
	public function register() {
		add_action( 'template_redirect', array( $this, 'serve_feed' ), 1 );
	}

	/**
	 * Serve the dedicated feed when the request is the story archive feed.
	 *
	 * Detects via query flags (works with both pretty and query-string permalinks).
	 *
	 * @return void
	 */
	public function serve_feed() {
		if ( is_admin() ) {
			return;
		}
		$query = isset( $GLOBALS['wp_query'] ) ? $GLOBALS['wp_query'] : null;
		if ( ! $query instanceof \WP_Query ) {
			return;
		}
		if ( ! $query->is_feed() || ! $query->is_post_type_archive( $this->options->post_type_key() ) ) {
			return;
		}

		$this->render();
	}

	/**
	 * Render the dedicated RSS feed.
	 *
	 * Sends an XML response and exits. If the feed is disabled, sends 404.
	 *
	 * @return void
	 */
	public function render() {
		if ( ! $this->options->get( 'rss.enabled', true ) ) {
			status_header( 404 );
			nocache_headers();
			exit;
		}

		$stories = $this->query_stories();

		header( 'Content-Type: ' . feed_content_type( 'rss2' ) . '; charset=' . get_option( 'blog_charset' ), true );
		status_header( 200 );

		echo '<?xml version="1.0" encoding="' . esc_attr( get_option( 'blog_charset' ) ) . '"?' . '>' . "\n";
		?>
		<rss version="2.0" xmlns:content="http://purl.org/rss/1.0/modules/content/" xmlns:dc="http://purl.org/dc/elements/1.1/" xmlns:atom="http://www.w3.org/2005/Atom">
			<channel>
				<title><?php echo esc_xml( $this->feed_title() ); ?></title>
				<atom:link href="<?php echo esc_url( self::feed_url() ); ?>" rel="self" type="application/rss+xml" />
				<link><?php echo esc_url( home_url( '/' ) ); ?></link>
				<description><?php echo esc_xml( $this->options->get( 'rss.description', '' ) ); ?></description>
				<language><?php echo esc_xml( get_bloginfo_rss( 'language' ) ); ?></language>
				<?php foreach ( $stories as $story ) : ?>
					<?php echo $this->render_item( $story ); // phpcs:ignore WordPress.Security.EscapeOutput -- built from esc_xml parts. ?>
				<?php endforeach; ?>
			</channel>
		</rss>
		<?php
		exit;
	}

	/**
	 * Query the stories for the feed.
	 *
	 * @return int[] Story post IDs.
	 */
	private function query_stories() {
		$args = array(
			'post_type'      => $this->options->post_type_key(),
			'post_status'    => 'publish',
			'posts_per_page' => max( 1, (int) $this->options->get( 'rss.count', 10 ) ),
			'orderby'        => $this->options->get( 'rss.orderby', 'date' ),
			'order'          => $this->options->get( 'rss.order', 'DESC' ),
			'fields'         => 'ids',
			'no_found_rows'  => true,
		);

		$filter = $this->options->get( 'rss.taxonomy_filter', 'all' );
		$terms  = (array) $this->options->get( 'rss.taxonomy_terms', array() );

		if ( $this->options->taxonomy_enabled() && 'all' !== $filter && ! empty( $terms ) ) {
			$args['tax_query'] = array(
				array(
					'taxonomy' => $this->options->taxonomy_key(),
					'field'    => 'slug',
					'terms'    => $terms,
					'operator' => 'include' === $filter ? 'IN' : 'NOT IN',
				),
			);
		}

		/**
		 * Filter the RSS query arguments.
		 *
		 * @param array $args WP_Query args.
		 */
		$args = apply_filters( 'alf_wp_stories_rss_query_args', $args );

		$query = new \WP_Query( $args );
		return is_array( $query->posts ) ? array_map( 'absint', $query->posts ) : array();
	}

	/**
	 * Render a single RSS item.
	 *
	 * @param int $story_id Story post ID.
	 * @return string XML item markup.
	 */
	private function render_item( $story_id ) {
		$story_model = Plugin::instance()->get( 'story' );
		$story       = $story_model->get_story( $story_id );

		$title       = $this->item_title( $story );
		$description = $this->item_description( $story );
		$link        = $this->item_link( $story );
		$guid        = $this->item_guid( $story );
		$image       = $this->item_image( $story );

		$out  = "\t\t<item>\n";
		$out .= "\t\t\t<title>" . esc_xml( $title ) . "</title>\n";
		$out .= "\t\t\t<link>" . esc_url( $link ) . "</link>\n";
		$out .= "\t\t\t<guid isPermaLink=\"false\">" . esc_xml( $guid ) . "</guid>\n";
		$out .= "\t\t\t<description><![CDATA[" . $this->cdata( $description ) . "]]></description>\n";
		if ( $image ) {
			$out .= "\t\t\t<enclosure url=\"" . esc_url( $image ) . "\" type=\"image/jpeg\" />\n";
			$out .= "\t\t\t<media:content url=\"" . esc_url( $image ) . "\" medium=\"image\" xmlns:media=\"http://search.yahoo.com/mrss/\" />\n";
		}
		$out .= $this->custom_fields_xml( $story );
		$out .= "\t\t</item>\n";

		/**
		 * Filter a single RSS item's XML.
		 *
		 * @param string $out     Item XML.
		 * @param array  $story   Story payload.
		 * @param int    $story_id Story ID.
		 */
		return apply_filters( 'alf_wp_stories_rss_item', $out, $story, $story_id );
	}

	/**
	 * Determine the item title from the configured source.
	 *
	 * @param array $story Story payload.
	 * @return string
	 */
	private function item_title( $story ) {
		$source = $this->options->get( 'rss.item_title_source', 'post_title' );

		if ( 'caption' === $source && ! empty( $story['caption'] ) ) {
			$title = $story['caption'];
		} elseif ( 'custom' === $source ) {
			$title = $this->apply_template( $story, 'title' );
		} else {
			$title = $story['title'];
		}

		return (string) apply_filters( 'alf_wp_stories_rss_item_title', $title, $story );
	}

	/**
	 * Determine the item description from the configured source.
	 *
	 * @param array $story Story payload.
	 * @return string
	 */
	private function item_description( $story ) {
		$source = $this->options->get( 'rss.item_description_source', 'caption' );

		if ( 'post_excerpt' === $source ) {
			$description = get_the_excerpt( $story['id'] );
		} elseif ( 'none' === $source ) {
			$description = '';
		} else {
			$description = $story['caption'];
		}

		return (string) apply_filters( 'alf_wp_stories_rss_item_description', $description, $story );
	}

	/**
	 * Determine the item link.
	 *
	 * @param array $story Story payload.
	 * @return string
	 */
	private function item_link( $story ) {
		return (string) apply_filters( 'alf_wp_stories_rss_item_link', $story['url'], $story );
	}

	/**
	 * Determine the item GUID.
	 *
	 * @param array $story Story payload.
	 * @return string
	 */
	private function item_guid( $story ) {
		$source = $this->options->get( 'rss.guid_source', 'permalink' );
		$guid   = 'permalink' === $source ? $story['url'] : 'story-' . $story['id'];
		return (string) apply_filters( 'alf_wp_stories_rss_item_guid', $guid, $story );
	}

	/**
	 * Determine the item image URL.
	 *
	 * @param array $story Story payload.
	 * @return string
	 */
	private function item_image( $story ) {
		$source = $this->options->get( 'rss.image_source', 'cover' );
		$size   = $this->options->get( 'rss.image_size', 'large' );

		$image = '';
		if ( 'first_frame' === $source && ! empty( $story['frames'][0]['image_id'] ) ) {
			$image = attachment_url( $story['frames'][0]['image_id'], $size );
		} elseif ( 'cover' === $source ) {
			$image = $this->image_for_size( $story, $size );
		}

		if ( $image && 'relative' === $this->options->get( 'rss.image_url_format', 'absolute' ) ) {
			$image = wp_make_link_relative( $image );
		}

		return (string) apply_filters( 'alf_wp_stories_rss_item_image', $image, $story );
	}

	/**
	 * Resolve a cover image at a specific size.
	 *
	 * @param array  $story Story payload.
	 * @param string $size  Image size.
	 * @return string
	 */
	private function image_for_size( $story, $size ) {
		$story_model = Plugin::instance()->get( 'story' );
		return $story_model->get_cover_url( $story['id'], $size );
	}

	/**
	 * Build custom field XML from configured meta keys.
	 *
	 * @param array $story Story payload.
	 * @return string
	 */
	private function custom_fields_xml( $story ) {
		$keys = (array) $this->options->get( 'rss.custom_fields', array() );
		if ( empty( $keys ) ) {
			return '';
		}
		$out = '';
		foreach ( $keys as $key ) {
			$value = get_post_meta( $story['id'], $key, true );
			if ( is_scalar( $value ) && '' !== $value ) {
				$out .= "\t\t\t<" . esc_xml( $key ) . ">" . esc_xml( (string) $value ) . "</" . esc_xml( $key ) . ">\n";
			}
		}
		return $out;
	}

	/**
	 * Apply the custom template for a field.
	 *
	 * @param array  $story Story payload.
	 * @param string $field Field being rendered ('title' or 'description').
	 * @return string
	 */
	private function apply_template( $story, $field ) {
		$template = $this->options->get( 'rss.custom_template', '' );
		$replace  = array(
			'{title}'       => $story['title'],
			'{description}' => $story['caption'],
			'{link}'        => $story['url'],
			'{guid}'        => 'story-' . $story['id'],
			'{image}'       => $story['cover'],
		);
		return strtr( $template, $replace );
	}

	/**
	 * Wrap a string in a CDATA-safe block.
	 *
	 * @param string $value Raw string.
	 * @return string
	 */
	private function cdata( $value ) {
		// Split on ]]> which would prematurely close a CDATA section.
		return str_replace( ']]>', ']]]]><![CDATA[>', (string) $value );
	}

	/**
	 * Determine the feed title.
	 *
	 * @return string
	 */
	private function feed_title() {
		$title = $this->options->get( 'rss.title', '' );
		if ( '' === $title ) {
			$title = get_bloginfo( 'name' ) . ' — ' . $this->options->get( 'post_type.plural', 'Client Stories' );
		}
		return (string) apply_filters( 'alf_wp_stories_rss_feed_title', $title );
	}

	/**
	 * Build the feed URL.
	 *
	 * @return string
	 */
	public static function feed_url() {
		$options = Plugin::instance()->get( 'options' );
		$slug    = $options ? $options->get( 'post_type.slug', 'client-stories' ) : 'client-stories';
		return home_url( user_trailingslashit( sanitize_title( $slug ) ) . 'feed/' );
	}
}
