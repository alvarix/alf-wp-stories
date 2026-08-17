<?php
/**
 * Admin editor: cover image, caption, and the frame repeater with drag-and-drop.
 *
 * @package ALF_WP_Stories
 */

namespace ALF_WP_Stories;

defined( 'ABSPATH' ) || exit;

/**
 * Story meta box and frame editor.
 */
class Story_Meta {

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
		add_action( 'add_meta_boxes', array( $this, 'add_meta_box' ) );
		add_action( 'save_post', array( $this, 'save' ), 10, 2 );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue' ) );
	}

	/**
	 * Add the story meta box.
	 *
	 * @return void
	 */
	public function add_meta_box() {
		add_meta_box(
			'alf-wp-stories-meta',
			__( 'Story', 'alf-wp-stories' ),
			array( $this, 'render' ),
			$this->options->post_type_key(),
			'normal',
			'high'
		);
	}

	/**
	 * Enqueue admin editor assets.
	 *
	 * @param string $hook Current admin screen hook.
	 * @return void
	 */
	public function enqueue( $hook ) {
		if ( ! in_array( $hook, array( 'post.php', 'post-new.php' ), true ) ) {
			return;
		}
		$screen = get_current_screen();
		if ( ! $screen || $this->options->post_type_key() !== $screen->post_type ) {
			return;
		}

		wp_enqueue_media();
		wp_enqueue_style(
			'alf-wp-stories-admin',
			ALF_WP_STORIES_URL . 'assets/css/admin.css',
			array(),
			ALF_WP_STORIES_VERSION
		);
		wp_enqueue_script(
			'alf-wp-stories-admin',
			ALF_WP_STORIES_URL . 'assets/js/admin-frames.js',
			array( 'jquery', 'jquery-ui-sortable', 'wp-mediaelement' ),
			ALF_WP_STORIES_VERSION,
			true
		);
		wp_localize_script(
			'alf-wp-stories-admin',
			'alfWpStoriesAdmin',
			array(
				'frameTitle'   => __( 'Choose frame image', 'alf-wp-stories' ),
				'frameButton'  => __( 'Use this image', 'alf-wp-stories' ),
				'coverTitle'   => __( 'Choose cover image', 'alf-wp-stories' ),
				'coverButton'  => __( 'Use as cover image', 'alf-wp-stories' ),
				'defaultDuration' => (int) $this->options->get( 'viewer.frame_duration', 5000 ),
			)
		);
	}

	/**
	 * Render the meta box.
	 *
	 * @param \WP_Post $post Current post.
	 * @return void
	 */
	public function render( $post ) {
		wp_nonce_field( 'alf_wp_stories_save', 'alf_wp_stories_nonce' );

		$cover   = (int) get_post_meta( $post->ID, Story::META_COVER, true );
		$caption = (string) get_post_meta( $post->ID, Story::META_CAPTION, true );
		$frames  = get_post_meta( $post->ID, Story::META_FRAMES, true );
		$frames  = is_array( $frames ) ? $frames : array();

		$cover_url = $cover ? attachment_url( $cover, 'medium' ) : '';
		?>
		<div class="alf-wp-stories-editor">

			<div class="alf-wp-stories-field">
				<label for="alf-wp-stories-cover"><?php esc_html_e( 'Cover Image', 'alf-wp-stories' ); ?></label>
				<div class="alf-wp-stories-cover" id="alf-wp-stories-cover">
					<?php if ( $cover_url ) : ?>
						<img src="<?php echo esc_url( $cover_url ); ?>" alt="" />
					<?php else : ?>
						<p class="alf-wp-stories-placeholder"><?php esc_html_e( 'No cover image selected.', 'alf-wp-stories' ); ?></p>
					<?php endif; ?>
				</div>
				<input type="hidden" id="alf-wp-stories-cover-id" name="alf_wp_stories_cover" value="<?php echo esc_attr( $cover ); ?>" />
				<p>
					<button type="button" class="button alf-wp-stories-select-cover">
						<?php esc_html_e( 'Select Cover Image', 'alf-wp-stories' ); ?>
					</button>
					<button type="button" class="button alf-wp-stories-remove-cover" <?php echo $cover ? '' : 'style="display:none"'; ?>>
						<?php esc_html_e( 'Remove', 'alf-wp-stories' ); ?>
					</button>
				</p>
				<p class="description"><?php esc_html_e( 'Recommended 1080 × 1920 (9:16).', 'alf-wp-stories' ); ?></p>
			</div>

			<div class="alf-wp-stories-field">
				<label for="alf-wp-stories-caption"><?php esc_html_e( 'Caption', 'alf-wp-stories' ); ?></label>
				<textarea id="alf-wp-stories-caption" name="alf_wp_stories_caption" rows="3" class="large-text"><?php echo esc_textarea( $caption ); ?></textarea>
			</div>

			<div class="alf-wp-stories-field">
				<label><?php esc_html_e( 'Frames', 'alf-wp-stories' ); ?></label>
				<p class="description"><?php esc_html_e( 'Drag to reorder. Each frame is an image with optional alt text and duration.', 'alf-wp-stories' ); ?></p>

				<div class="alf-wp-stories-frames" id="alf-wp-stories-frames">
					<?php foreach ( $frames as $index => $frame ) : ?>
						<?php
						$frame    = normalize_frame( $frame );
						$frame_url = $frame['image_id'] ? attachment_url( $frame['image_id'], 'medium' ) : '';
						?>
						<div class="alf-wp-stories-frame" data-index="<?php echo esc_attr( $index ); ?>">
							<span class="alf-wp-stories-drag-handle dashicons dashicons-move"></span>
							<div class="alf-wp-stories-frame-preview">
								<?php if ( $frame_url ) : ?>
									<img src="<?php echo esc_url( $frame_url ); ?>" alt="" />
								<?php else : ?>
									<span class="alf-wp-stories-placeholder"><?php esc_html_e( 'No image', 'alf-wp-stories' ); ?></span>
								<?php endif; ?>
							</div>
							<div class="alf-wp-stories-frame-fields">
								<input type="hidden" class="alf-wp-stories-frame-id" name="alf_wp_stories_frames[<?php echo esc_attr( $index ); ?>][image_id]" value="<?php echo esc_attr( $frame['image_id'] ); ?>" />
								<p>
									<button type="button" class="button alf-wp-stories-select-frame"><?php esc_html_e( 'Select image', 'alf-wp-stories' ); ?></button>
								</p>
								<p>
									<label class="screen-reader-text"><?php esc_html_e( 'Alt text', 'alf-wp-stories' ); ?></label>
									<input type="text" class="alf-wp-stories-frame-alt regular-text" name="alf_wp_stories_frames[<?php echo esc_attr( $index ); ?>][alt]" value="<?php echo esc_attr( $frame['alt'] ); ?>" placeholder="<?php esc_attr_e( 'Alt text', 'alf-wp-stories' ); ?>" />
								</p>
								<p>
									<label><?php esc_html_e( 'Duration (ms)', 'alf-wp-stories' ); ?></label>
									<input type="number" class="alf-wp-stories-frame-duration small-text" name="alf_wp_stories_frames[<?php echo esc_attr( $index ); ?>][duration]" value="<?php echo esc_attr( $frame['duration'] ); ?>" min="0" step="100" />
								</p>
							</div>
							<button type="button" class="button-link-delete alf-wp-stories-remove-frame"><?php esc_html_e( 'Remove', 'alf-wp-stories' ); ?></button>
						</div>
					<?php endforeach; ?>
				</div>

				<p>
					<button type="button" class="button button-primary alf-wp-stories-add-frame"><?php esc_html_e( 'Add Frame', 'alf-wp-stories' ); ?></button>
				</p>
			</div>

		</div>
		<?php
	}

	/**
	 * Save the story meta.
	 *
	 * @param int      $post_id Post ID.
	 * @param \WP_Post $post    Post object.
	 * @return void
	 */
	public function save( $post_id, $post ) {
		if ( $this->options->post_type_key() !== $post->post_type ) {
			return;
		}

		if ( ! isset( $_POST['alf_wp_stories_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['alf_wp_stories_nonce'] ) ), 'alf_wp_stories_save' ) ) {
			return;
		}

		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}

		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		// Cover image.
		$cover = isset( $_POST['alf_wp_stories_cover'] ) ? absint( $_POST['alf_wp_stories_cover'] ) : 0;
		if ( $cover ) {
			update_post_meta( $post_id, Story::META_COVER, $cover );
		} else {
			delete_post_meta( $post_id, Story::META_COVER );
		}

		// Caption.
		$caption = isset( $_POST['alf_wp_stories_caption'] ) ? sanitize_textarea_field( wp_unslash( $_POST['alf_wp_stories_caption'] ) ) : '';
		if ( '' !== $caption ) {
			update_post_meta( $post_id, Story::META_CAPTION, $caption );
		} else {
			delete_post_meta( $post_id, Story::META_CAPTION );
		}

		// Frames.
		$frames = isset( $_POST['alf_wp_stories_frames'] ) ? wp_unslash( $_POST['alf_wp_stories_frames'] ) : array();
		$frames = sanitize_frames( $frames );
		if ( ! empty( $frames ) ) {
			update_post_meta( $post_id, Story::META_FRAMES, $frames );
		} else {
			delete_post_meta( $post_id, Story::META_FRAMES );
		}
	}
}
