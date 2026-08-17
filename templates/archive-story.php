<?php
/**
 * Archive template for the story post type.
 *
 * Themes can override at {theme}/alf-wp-stories/archive-story.php.
 *
 * @package ALF_WP_Stories
 */

defined( 'ABSPATH' ) || exit;

get_header();

$story_model = ALF_WP_Stories\Plugin::instance()->get( 'story' );
$options     = ALF_WP_Stories\Plugin::instance()->get( 'options' );
$plural      = $options->get( 'post_type.plural', 'Client Stories' );
?>

<main class="alf-wp-stories-archive">
	<header class="alf-wp-stories-archive-header">
		<h1><?php echo esc_html( $plural ); ?></h1>
	</header>

	<?php if ( have_posts() ) : ?>
		<div class="alf-wp-stories-archive-grid">
			<?php
			while ( have_posts() ) :
				the_post();
				$story = $story_model->get_story( get_the_ID() );
				?>
				<a class="alf-wp-stories-archive-card" href="<?php echo esc_url( $story['url'] ); ?>">
					<?php if ( $story['cover'] ) : ?>
						<img src="<?php echo esc_url( $story['cover'] ); ?>" alt="<?php echo esc_attr( $story['title'] ); ?>" loading="lazy" />
					<?php endif; ?>
					<span class="alf-wp-stories-archive-title"><?php echo esc_html( $story['title'] ); ?></span>
				</a>
			<?php endwhile; ?>
		</div>
		<?php the_posts_pagination(); ?>
	<?php else : ?>
		<p><?php esc_html_e( 'No stories found.', 'alf-wp-stories' ); ?></p>
	<?php endif; ?>
</main>

<?php
get_footer();
