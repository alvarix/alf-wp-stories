<?php
/**
 * Story model: canonical content accessor.
 *
 * The story model is the single source of truth for story data. Every output
 * (viewer, launcher, RSS, REST, Open Graph) reads through this class.
 *
 * @package ALF_WP_Stories
 */

namespace ALF_WP_Stories;

defined( 'ABSPATH' ) || exit;

/**
 * Story data model.
 */
class Story {

	const META_COVER   = '_alf_wp_stories_cover';
	const META_CAPTION = '_alf_wp_stories_caption';
	const META_FRAMES  = '_alf_wp_stories_frames';

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
	 * Register hooks (none needed at runtime; kept for interface parity).
	 *
	 * @return void
	 */
	public function register() {
		// Intentionally empty: the model is a passive accessor.
	}

	/**
	 * Get the cover image ID for a story.
	 *
	 * Falls back to the featured image, then to the first frame.
	 *
	 * @param int|\WP_Post $post Post or post ID.
	 * @return int Attachment ID or 0.
	 */
	public function get_cover_id( $post ) {
		$post_id = $this->id( $post );
		if ( ! $post_id ) {
			return 0;
		}

		$cover = (int) get_post_meta( $post_id, self::META_COVER, true );

		if ( ! $cover ) {
			$thumb = get_post_thumbnail_id( $post_id );
			if ( $thumb ) {
				$cover = (int) $thumb;
			}
		}

		if ( ! $cover ) {
			$frames = $this->get_frames( $post_id );
			if ( ! empty( $frames[0]['image_id'] ) ) {
				$cover = (int) $frames[0]['image_id'];
			}
		}

		/**
		 * Filter the resolved cover image ID.
		 *
		 * @param int $cover   Attachment ID.
		 * @param int $post_id Story post ID.
		 */
		return (int) apply_filters( 'alf_wp_stories_cover_image_id', $cover, $post_id );
	}

	/**
	 * Get the cover image URL for a story.
	 *
	 * @param int|\WP_Post $post Post or post ID.
	 * @param string       $size Image size.
	 * @return string Absolute URL or ''.
	 */
	public function get_cover_url( $post, $size = 'large' ) {
		$cover = $this->get_cover_id( $post );
		if ( ! $cover ) {
			return '';
		}
		$url = attachment_url( $cover, $size );

		/**
		 * Filter the cover image URL.
		 *
		 * @param string $url  URL.
		 * @param int    $post_id Story post ID.
		 * @param string $size Image size.
		 */
		return (string) apply_filters( 'alf_wp_stories_cover_image_url', $url, $this->id( $post ), $size );
	}

	/**
	 * Get the caption for a story.
	 *
	 * @param int|\WP_Post $post Post or post ID.
	 * @return string
	 */
	public function get_caption( $post ) {
		$post_id = $this->id( $post );
		if ( ! $post_id ) {
			return '';
		}
		return (string) get_post_meta( $post_id, self::META_CAPTION, true );
	}

	/**
	 * Get the ordered frames for a story.
	 *
	 * @param int|\WP_Post $post Post or post ID.
	 * @return array Array of normalized frames.
	 */
	public function get_frames( $post ) {
		$post_id = $this->id( $post );
		if ( ! $post_id ) {
			return array();
		}
		$frames = get_post_meta( $post_id, self::META_FRAMES, true );
		$frames = is_array( $frames ) ? $frames : array();

		$normalized = array_map( 'ALF_WP_Stories\normalize_frame', $frames );

		/**
		 * Filter the story frames.
		 *
		 * @param array $frames  Normalized frames.
		 * @param int   $post_id Story post ID.
		 */
		return apply_filters( 'alf_wp_stories_frames', $normalized, $post_id );
	}

	/**
	 * Get the complete normalized story data for a post.
	 *
	 * @param int|\WP_Post $post Post or post ID.
	 * @return array|null Story payload, or null if post does not exist.
	 */
	public function get_story( $post ) {
		$post = get_post( $post );
		if ( ! $post ) {
			return null;
		}

		$frames  = $this->get_frames( $post->ID );

		// Social cover (compliant derivative) from the ratio-check integration.
		$social_id  = 0;
		$social_url = '';
		$integration = Plugin::instance()->get( 'integration' );
		if ( $integration instanceof Integration ) {
			$social_id  = $integration->get_social_cover_id( $post->ID );
			$social_url = $social_id ? attachment_url( $social_id, 'large' ) : '';
		}

		$payload = array(
			'id'            => (int) $post->ID,
			'title'         => get_the_title( $post ),
			'url'           => get_permalink( $post ),
			'cover'         => $this->get_cover_url( $post->ID, 'large' ),
			'cover_id'      => $this->get_cover_id( $post->ID ),
			'social_cover'  => $social_url,
			'social_cover_id' => $social_id,
			'caption'       => $this->get_caption( $post->ID ),
			'frames'        => $this->frames_payload( $frames ),
			'terms'         => $this->terms( $post->ID ),
		);

		/**
		 * Filter the full story payload.
		 *
		 * @param array $payload Story payload.
		 * @param int   $post_id Story post ID.
		 */
		return apply_filters( 'alf_wp_stories_story', $payload, $post->ID );
	}

	/**
	 * Enrich each frame with resolved image URLs and dimensions.
	 *
	 * @param array $frames Normalized frames.
	 * @return array
	 */
	private function frames_payload( $frames ) {
		$out = array();
		foreach ( $frames as $frame ) {
			$frame['image_url'] = attachment_url( $frame['image_id'], 'large' );
			$frame['image_srcset'] = wp_get_attachment_image_srcset( $frame['image_id'], 'large' ) ?: '';
			$out[] = $frame;
		}
		return $out;
	}

	/**
	 * Get taxonomy terms for a story.
	 *
	 * @param int $post_id Story post ID.
	 * @return array Array of term slugs.
	 */
	private function terms( $post_id ) {
		if ( ! $this->options->taxonomy_enabled() ) {
			return array();
		}
		$terms = get_the_terms( $post_id, $this->options->taxonomy_key() );
		if ( is_wp_error( $terms ) || ! is_array( $terms ) ) {
			return array();
		}
		return wp_list_pluck( $terms, 'slug' );
	}

	/**
	 * Query stories with sensible defaults.
	 *
	 * @param array $args Optional WP_Query args overrides.
	 * @return int[] Array of post IDs.
	 */
	public function query_story_ids( $args = array() ) {
		$defaults = array(
			'post_type'      => $this->options->post_type_key(),
			'post_status'    => 'publish',
			'posts_per_page' => -1,
			'orderby'        => 'date',
			'order'          => 'DESC',
			'fields'         => 'ids',
			'no_found_rows'  => true,
		);

		$query_args = wp_parse_args( $args, $defaults );

		/**
		 * Filter the story query arguments.
		 *
		 * @param array $query_args WP_Query args.
		 */
		$query_args = apply_filters( 'alf_wp_stories_query_args', $query_args );

		$query = new \WP_Query( $query_args );
		return is_array( $query->posts ) ? array_map( 'absint', $query->posts ) : array();
	}

	/**
	 * Extract a post ID from a post object or ID.
	 *
	 * @param int|\WP_Post $post Post or post ID.
	 * @return int
	 */
	private function id( $post ) {
		if ( is_numeric( $post ) ) {
			return (int) $post;
		}
		if ( $post instanceof \WP_Post ) {
			return (int) $post->ID;
		}
		return 0;
	}
}
