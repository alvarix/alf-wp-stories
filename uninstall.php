<?php
/**
 * Uninstall handler: removes plugin options only.
 *
 * Post data (stories, frames, cover images, taxonomy terms) is intentionally
 * preserved so an accidental reactivation does not lose content.
 *
 * @package ALF_WP_Stories
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

delete_option( 'alf_wp_stories_options' );

// Flush rewrite rules on next request.
if ( function_exists( 'flush_rewrite_rules' ) ) {
	flush_rewrite_rules();
}
