<?php
/**
 * Single story template: embeds the viewer for the current story.
 *
 * Themes can override at {theme}/alf-wp-stories/single-story.php.
 *
 * @package ALF_WP_Stories
 */

defined( 'ABSPATH' ) || exit;

get_header();

$story_model = ALF_WP_Stories\Plugin::instance()->get( 'story' );
$viewer      = ALF_WP_Stories\Plugin::instance()->get( 'viewer' );

while ( have_posts() ) :
	the_post();
	$story = $story_model->get_story( get_the_ID() );

	// Enqueue viewer assets and register the story for the viewer JS.
	$viewer->enqueue_viewer();
	?>
	<main class="alf-wp-stories-single">
		<article class="alf-wp-stories-story">
			<header class="alf-wp-stories-story-header">
				<h1><?php echo esc_html( $story['title'] ); ?></h1>
				<?php if ( $story['caption'] ) : ?>
					<p class="alf-wp-stories-story-caption"><?php echo esc_html( $story['caption'] ); ?></p>
				<?php endif; ?>
			</header>

			<div class="alf-wp-stories-story-viewer-mount"
				data-auto-open="1"
				data-story-json="<?php echo esc_attr( wp_json_encode( $story ) ); ?>"></div>
		</article>
	</main>
	<?php
endwhile;

get_footer();
