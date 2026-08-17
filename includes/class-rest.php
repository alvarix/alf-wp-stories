<?php
/**
 * REST API integration for story content.
 *
 * @package ALF_WP_Stories
 */

namespace ALF_WP_Stories;

defined( 'ABSPATH' ) || exit;

/**
 * REST API support.
 */
class Rest {

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
		add_action( 'rest_api_init', array( $this, 'register_routes' ) );
		add_filter( 'rest_prepare_' . $this->options->post_type_key(), array( $this, 'add_story_fields' ), 10, 3 );
	}

	/**
	 * Register a dedicated REST route returning full story payloads.
	 *
	 * Route: /wp-json/alf-wp-stories/v1/stories
	 *
	 * @return void
	 */
	public function register_routes() {
		register_rest_route(
			'alf-wp-stories/v1',
			'/stories',
			array(
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_stories' ),
				'permission_callback' => '__return_true',
			)
		);
	}

	/**
	 * Return published stories with full payloads.
	 *
	 * @return \WP_REST_Response
	 */
	public function get_stories() {
		$story_model = Plugin::instance()->get( 'story' );
		$ids         = $story_model->query_story_ids(
			array(
				'posts_per_page' => 100,
			)
		);

		$stories = array();
		foreach ( $ids as $id ) {
			$stories[] = $story_model->get_story( $id );
		}

		/**
		 * Filter the REST stories payload.
		 *
		 * @param array $stories Story payloads.
		 */
		$stories = apply_filters( 'alf_wp_stories_rest_stories', $stories );

		return rest_ensure_response( $stories );
	}

	/**
	 * Add the full story payload to the default post-type REST response.
	 *
	 * @param \WP_REST_Response $response Response object.
	 * @param \WP_Post          $post     Post object.
	 * @param \WP_REST_Request  $request  Request object.
	 * @return \WP_REST_Response
	 */
	public function add_story_fields( $response, $post, $request ) {
		if ( ! $post instanceof \WP_Post ) {
			return $response;
		}
		$story_model = Plugin::instance()->get( 'story' );
		$story       = $story_model->get_story( $post->ID );

		if ( null !== $story ) {
			$response->data['story'] = $story;
		}

		return $response;
	}
}
