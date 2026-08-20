<?php
/**
 * Integration with the WP ALF Image Ratio Check plugin.
 *
 * Registers a compliance context for story covers, exposes a helper to query
 * an attachment's compliance status, and listens for the `wp_alf_img_ratio_check_passed`
 * action to store a compliant derivative as the story's social cover.
 *
 * Option A (doc 003): stories keep their 9:16 cover for the viewer; a separate
 * 3:4 `_alf_wp_stories_social_cover` derivative feeds RSS/OG/Metricool.
 *
 * @package ALF_WP_Stories
 */

namespace ALF_WP_Stories;

defined( 'ABSPATH' ) || exit;

/**
 * Ratio-check integration.
 */
class Integration {

	const META_SOCIAL_COVER = '_alf_wp_stories_social_cover';

	/**
	 * Options service.
	 *
	 * @var Options
	 */
	private $options;

	/**
	 * Cached ratio-check plugin instance, if active.
	 *
	 * @var object|null
	 */
	private $ratio_plugin = null;

	/**
	 * Constructor.
	 *
	 * @param Options $options Options.
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
		add_filter( 'wp_alf_img_ratio_check_contexts', array( $this, 'register_context' ) );
		add_action( 'wp_alf_img_ratio_check_passed', array( $this, 'on_ratio_passed' ), 10, 3 );
	}

	/**
	 * Register the story-cover compliance context.
	 *
	 * @param array $contexts Existing contexts.
	 * @return array
	 */
	public function register_context( $contexts ) {
		$contexts[] = array(
			'id'       => 'alf_wp_stories_cover',
			'plugin'   => 'alf_wp_stories',
			'cpt'      => $this->options->post_type_key(),
			'field'    => 'social_cover',
			'label'    => __( 'Story cover (social)', 'alf-wp-stories' ),
			'min'      => 0.75, // Metricool 3:4.
			'max'      => 1.91, // Metricool 1.91:1.
			'resolver'  => function ( $post_id ) {
				// Prefer the social_cover derivative; fall back to the cover.
				$social = (int) get_post_meta( $post_id, self::META_SOCIAL_COVER, true );
				if ( $social ) {
					return $social;
				}
				$cover = (int) get_post_meta( $post_id, Story::META_COVER, true );
				return $cover ? $cover : (int) get_post_thumbnail_id( $post_id );
			},
		);
		return $contexts;
	}

	/**
	 * Get the ratio-check plugin instance, if active.
	 *
	 * @return object|null
	 */
	private function ratio_plugin() {
		if ( null !== $this->ratio_plugin ) {
			return $this->ratio_plugin;
		}
		if ( ! class_exists( 'WP_Alf_Img_Ratio_Check\\Plugin' ) ) {
			$this->ratio_plugin = false;
			return null;
		}
		$this->ratio_plugin = \WP_Alf_Img_Ratio_Check\Plugin::instance();
		return $this->ratio_plugin;
	}

	/**
	 * Whether the ratio-check plugin is active.
	 *
	 * @return bool
	 */
	public function is_active() {
		return null !== $this->ratio_plugin();
	}

	/**
	 * Query an attachment's global compliance status.
	 *
	 * @param int $attachment_id Attachment ID.
	 * @return bool|null True=compliant, false=non-compliant, null=unknown/inactive.
	 */
	public function attachment_is_compliant( $attachment_id ) {
		$plugin = $this->ratio_plugin();
		if ( ! $plugin ) {
			return null;
		}
		$checker = $plugin->get( 'checker' );
		if ( ! $checker ) {
			return null;
		}
		$status = $checker->get_status( $attachment_id );
		return isset( $status['compliant'] ) ? $status['compliant'] : null;
	}

	/**
	 * When a compliant derivative is produced, store it as the story's social cover.
	 *
	 * @param int $new_id      New compliant attachment ID.
	 * @param int $original_id Source attachment ID.
	 * @param float $ratio     Target ratio.
	 * @return void
	 */
	public function on_ratio_passed( $new_id, $original_id, $ratio ) {
		$new_id      = (int) $new_id;
		$original_id = (int) $original_id;
		if ( ! $new_id || ! $original_id ) {
			return;
		}

		// Find stories whose cover (or existing social cover) is the original.
		$stories = new \WP_Query( array(
			'post_type'      => $this->options->post_type_key(),
			'post_status'    => 'any',
			'posts_per_page' => -1,
			'fields'         => 'ids',
			'no_found_rows'  => true,
			'meta_query'     => array(
				'relation' => 'OR',
				array( 'key' => Story::META_COVER, 'value' => $original_id ),
				array( 'key' => self::META_SOCIAL_COVER, 'value' => $original_id ),
			),
		) );

		if ( is_array( $stories->posts ) ) {
			foreach ( $stories->posts as $story_id ) {
				update_post_meta( (int) $story_id, self::META_SOCIAL_COVER, $new_id );
			}
		}
	}

	/**
	 * Get the social cover ID for a story (the Metricool-facing image).
	 *
	 * @param int|\WP_Post $post Post or ID.
	 * @return int Attachment ID (falls back to the cover) or 0.
	 */
	public function get_social_cover_id( $post ) {
		$post_id = ( is_numeric( $post ) ) ? (int) $post : ( isset( $post->ID ) ? (int) $post->ID : 0 );
		if ( ! $post_id ) {
			return 0;
		}
		$social = (int) get_post_meta( $post_id, self::META_SOCIAL_COVER, true );
		if ( $social ) {
			return $social;
		}
		$story_model = Plugin::instance()->get( 'story' );
		if ( $story_model instanceof Story ) {
			return $story_model->get_cover_id( $post_id );
		}
		return 0;
	}

	/**
	 * Get the social cover URL for a story.
	 *
	 * @param int|\WP_Post $post Post or ID.
	 * @param string       $size Image size.
	 * @return string
	 */
	public function get_social_cover_url( $post, $size = 'large' ) {
		$id = $this->get_social_cover_id( $post );
		if ( ! $id ) {
			return '';
		}
		return attachment_url( $id, $size );
	}
}
