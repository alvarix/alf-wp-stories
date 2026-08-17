<?php
/**
 * Plugin Name:       ALF WP Stories
 * Plugin URI:        https://example.com/alf-wp-stories
 * Description:       A configurable, multi-frame visual story content type with an Instagram-style viewer, launcher block, shortcode, REST API, Open Graph, and a dedicated configurable RSS feed. No dependency on Google Web Stories.
 * Version:           1.0.0
 * Requires at least: 6.0
 * Requires PHP:      7.4
 * Author:            Alvar Sirlin
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       alf-wp-stories
 *
 * @package ALF_WP_Stories
 */

defined( 'ABSPATH' ) || exit;

define( 'ALF_WP_STORIES_VERSION', '1.0.0' );
define( 'ALF_WP_STORIES_FILE', __FILE__ );
define( 'ALF_WP_STORIES_DIR', plugin_dir_path( __FILE__ ) );
define( 'ALF_WP_STORIES_URL', plugin_dir_url( __FILE__ ) );

require_once ALF_WP_STORIES_DIR . 'includes/class-plugin.php';

/**
 * Boot the plugin.
 *
 * Runs on `plugins_loaded` so themes and other plugins can hook into the
 * service wiring before the plugin registers its content types.
 */
function alf_wp_stories_init() {
	ALF_WP_Stories\Plugin::instance()->boot();
}
add_action( 'plugins_loaded', 'alf_wp_stories_init' );
