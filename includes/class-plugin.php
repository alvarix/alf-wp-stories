<?php
/**
 * Plugin bootstrap and service container.
 *
 * Wires every service together and exposes the singleton so that other code
 * (and tests) can reach registered components.
 *
 * @package ALF_WP_Stories
 */

namespace ALF_WP_Stories;

defined( 'ABSPATH' ) || exit;

/**
 * Main plugin orchestrator.
 */
class Plugin {

	/**
	 * Singleton instance.
	 *
	 * @var Plugin|null
	 */
	private static $instance = null;

	/**
	 * Loaded service instances, keyed by short class name.
	 *
	 * @var array<string, object>
	 */
	private $services = array();

	/**
	 * Retrieve (or create) the singleton.
	 *
	 * @return Plugin
	 */
	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Boot all services.
	 *
	 * @return void
	 */
	public function boot() {
		require_once ALF_WP_STORIES_DIR . 'includes/helpers.php';
		require_once ALF_WP_STORIES_DIR . 'includes/class-options.php';
		require_once ALF_WP_STORIES_DIR . 'includes/class-post-type.php';
		require_once ALF_WP_STORIES_DIR . 'includes/class-taxonomy.php';
		require_once ALF_WP_STORIES_DIR . 'includes/class-story-meta.php';
		require_once ALF_WP_STORIES_DIR . 'includes/class-story.php';
		require_once ALF_WP_STORIES_DIR . 'includes/class-templates.php';
		require_once ALF_WP_STORIES_DIR . 'includes/class-viewer.php';
		require_once ALF_WP_STORIES_DIR . 'includes/class-launcher.php';
		require_once ALF_WP_STORIES_DIR . 'includes/class-rss.php';
		require_once ALF_WP_STORIES_DIR . 'includes/class-opengraph.php';
		require_once ALF_WP_STORIES_DIR . 'includes/class-rest.php';
		require_once ALF_WP_STORIES_DIR . 'includes/class-filename-parser.php';
		require_once ALF_WP_STORIES_DIR . 'includes/class-bulk-ingest.php';
		require_once ALF_WP_STORIES_DIR . 'includes/class-integration.php';

		$options = new Options();
		$parser  = new Filename_Parser( $options );

		$services = array(
			'options'        => $options,
			'post_type'      => new Post_Type( $options ),
			'taxonomy'       => new Taxonomy( $options ),
			'story_meta'     => new Story_Meta( $options ),
			'story'          => new Story( $options ),
			'templates'      => new Templates( $options ),
			'viewer'         => new Viewer( $options ),
			'launcher'       => new Launcher( $options ),
			'rss'            => new Rss( $options ),
			'opengraph'      => new OpenGraph( $options ),
			'rest'           => new Rest( $options ),
			'filename_parser' => $parser,
			'bulk_ingest'    => new Bulk_Ingest( $options, $parser ),
			'integration'    => new Integration( $options ),
		);

		foreach ( $services as $key => $service ) {
			$this->services[ $key ] = $service;
			if ( method_exists( $service, 'register' ) ) {
				$service->register();
			}
		}
	}

	/**
	 * Fetch a registered service by key.
	 *
	 * @param string $key Service key (e.g. 'options', 'story').
	 * @return object|null
	 */
	public function get( $key ) {
		return isset( $this->services[ $key ] ) ? $this->services[ $key ] : null;
	}
}
