<?php
/**
 * Open Graph metadata for story pages.
 *
 * @package ALF_WP_Stories
 */

namespace ALF_WP_Stories;

defined( 'ABSPATH' ) || exit;

/**
 * Open Graph head injection.
 */
class OpenGraph {

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
		add_action( 'wp_head', array( $this, 'print_tags' ), 5 );
	}

	/**
	 * Print Open Graph tags on single story pages only.
	 *
	 * @return void
	 */
	public function print_tags() {
		if ( ! is_singular( $this->options->post_type_key() ) ) {
			return;
		}

		$post_id = get_queried_object_id();
		if ( ! $post_id ) {
			return;
		}

		$story_model = Plugin::instance()->get( 'story' );
		$story       = $story_model->get_story( $post_id );

		$tags = array(
			'og:title'       => $story['title'],
			'og:description' => $story['caption'] ? $story['caption'] : $story['title'],
			'og:url'         => $story['url'],
			'og:type'        => 'article',
		);

		if ( ! empty( $story['social_cover_id'] ) ) {
			$tags['og:image'] = $story['social_cover'];
			list( $width, $height ) = attachment_dimensions( (int) $story['social_cover_id'], 'large' );
			if ( $width && $height ) {
				$tags['og:image:width']  = $width;
				$tags['og:image:height'] = $height;
			}
		} elseif ( $story['cover'] ) {
			$tags['og:image'] = $story['cover'];
			list( $width, $height ) = attachment_dimensions( $story['cover_id'], 'large' );
			if ( $width && $height ) {
				$tags['og:image:width']  = $width;
				$tags['og:image:height'] = $height;
			}
		}

		/**
		 * Filter the Open Graph tags before output.
		 *
		 * @param array $tags    Associative array of property => content.
		 * @param array $story   Story payload.
		 */
		$tags = apply_filters( 'alf_wp_stories_opengraph_tags', $tags, $story );

		foreach ( $tags as $property => $content ) {
			if ( '' === $content ) {
				continue;
			}
			printf(
				'<meta property="%s" content="%s" />' . "\n",
				esc_attr( $property ),
				esc_attr( $content )
			);
		}
	}
}
