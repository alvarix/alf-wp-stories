<?php
/**
 * Frontend story viewer: asset registration and settings localization.
 *
 * Viewer assets are registered but only enqueued when a story viewer or
 * launcher is actually rendered (see Launcher::enqueue and the single/archive
 * templates), satisfying the "no global viewer JS" requirement.
 *
 * @package ALF_WP_Stories
 */

namespace ALF_WP_Stories;

defined( 'ABSPATH' ) || exit;

/**
 * Viewer asset management.
 */
class Viewer {

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
		// Register on `init` so both frontend and the block editor can resolve handles.
		add_action( 'init', array( $this, 'register_assets' ), 5 );
	}

	/**
	 * Register viewer and launcher assets (but do not enqueue globally).
	 *
	 * @return void
	 */
	public function register_assets() {
		wp_register_style(
			'alf-wp-stories-viewer',
			ALF_WP_STORIES_URL . 'assets/css/viewer.css',
			array(),
			ALF_WP_STORIES_VERSION
		);
		wp_register_style(
			'alf-wp-stories-launcher',
			ALF_WP_STORIES_URL . 'assets/css/launcher.css',
			array(),
			ALF_WP_STORIES_VERSION
		);
		wp_register_script(
			'alf-wp-stories-viewer',
			ALF_WP_STORIES_URL . 'assets/js/viewer.js',
			array(),
			ALF_WP_STORIES_VERSION,
			true
		);
	}

	/**
	 * Build the viewer settings payload for localization.
	 *
	 * @return array
	 */
	public function settings() {
		$settings = array(
			'frameDuration' => (int) $this->options->get( 'viewer.frame_duration', 5000 ),
			'autoplay'      => (bool) $this->options->get( 'viewer.autoplay', true ),
			'loop'          => (bool) $this->options->get( 'viewer.loop', true ),
			'showProgress'  => (bool) $this->options->get( 'viewer.show_progress', true ),
			'showClose'     => (bool) $this->options->get( 'viewer.show_close', true ),
			'keyboard'      => (bool) $this->options->get( 'viewer.keyboard', true ),
			'swipe'         => (bool) $this->options->get( 'viewer.swipe', true ),
		);

		/**
		 * Filter viewer settings before they reach JavaScript.
		 *
		 * @param array $settings Viewer settings.
		 */
		return apply_filters( 'alf_wp_stories_viewer_settings', $settings );
	}

	/**
	 * Enqueue viewer assets (called by launcher/single template).
	 *
	 * @return void
	 */
	public function enqueue_viewer() {
		wp_enqueue_style( 'alf-wp-stories-viewer' );
		wp_enqueue_script( 'alf-wp-stories-viewer' );
		$this->inline_theming();
		wp_localize_script(
			'alf-wp-stories-viewer',
			'alfWpStoriesViewer',
			array(
				'settings' => $this->settings(),
				'i18n'     => array(
					'close' => __( 'Close story', 'alf-wp-stories' ),
				),
			)
		);
	}

	/**
	 * Emit theming CSS variables scoped to the viewer/launcher.
	 *
	 * @return void
	 */
	private function inline_theming() {
		$css = $this->options->theming_css();
		if ( '' !== $css && ! wp_styles()->add_data( 'alf-wp-stories-viewer', 'alf-theming', true ) ) {
			wp_add_inline_style( 'alf-wp-stories-viewer', $css );
		}
	}

	/**
	 * Enqueue launcher styles only.
	 *
	 * @return void
	 */
	public function enqueue_launcher_styles() {
		wp_enqueue_style( 'alf-wp-stories-launcher' );
	}
}
