<?php
/**
 * Bulk ingestion: Media Library bulk action, modal, AJAX creator, filename preview.
 *
 * Groups selected attachments into stories by the configured grammar's group
 * key, creates/updates stories idempotently, and runs the ratio-compliance
 * gate before publishing.
 *
 * @package ALF_WP_Stories
 */

namespace ALF_WP_Stories;

defined( 'ABSPATH' ) || exit;

/**
 * Batch auto-post creator.
 */
class Bulk_Ingest {

	const META_SOURCE_IDS = '_alf_wp_stories_source_ids';
	const META_GROUP_KEY  = '_alf_wp_stories_group_key';

	/**
	 * Options service.
	 *
	 * @var Options
	 */
	private $options;

	/**
	 * Filename parser.
	 *
	 * @var Filename_Parser
	 */
	private $parser;

	/**
	 * Constructor.
	 *
	 * @param Options         $options Options.
	 * @param Filename_Parser $parser  Parser.
	 */
	public function __construct( Options $options, Filename_Parser $parser ) {
		$this->options = $options;
		$this->parser  = $parser;
	}

	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	public function register() {
		add_filter( 'bulk_actions-upload', array( $this, 'register_bulk_action' ) );
		add_filter( 'handle_bulk_actions-upload', array( $this, 'handle_bulk_action' ), 10, 3 );
		add_action( 'admin_notices', array( $this, 'bulk_action_notice' ) );
		add_action( 'admin_footer-upload.php', array( $this, 'render_modal' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue' ) );

		add_action( 'wp_ajax_alf_wp_stories_bulk_create', array( $this, 'ajax_bulk_create' ) );
		add_action( 'wp_ajax_alf_wp_stories_preview_filename', array( $this, 'ajax_preview_filename' ) );
	}

	/**
	 * Register the bulk action in the Media Library.
	 *
	 * @param array $actions Existing bulk actions.
	 * @return array
	 */
	public function register_bulk_action( $actions ) {
		$plural = $this->options->get( 'post_type.plural', 'Client Stories' );
		$actions['alf_wp_stories_create'] = sprintf( /* translators: plural label */ __( 'Create %s', 'alf-wp-stories' ), $plural );
		return $actions;
	}

	/**
	 * No-op fallback for the bulk action redirect (JS modal intercepts).
	 *
	 * @param string $redirect_url Redirect URL.
	 * @param string $action       Action name.
	 * @param array  $post_ids     Selected post IDs.
	 * @return string
	 */
	public function handle_bulk_action( $redirect_url, $action, $post_ids ) {
		return $redirect_url;
	}

	/**
	 * Display admin notice after bulk story creation.
	 *
	 * @return void
	 */
	public function bulk_action_notice() {
		if ( ! empty( $_REQUEST['alf_wp_stories_created'] ) ) {
			printf(
				'<div class="notice notice-success is-dismissible"><p>%s</p></div>',
				esc_html( sprintf( /* translators: count */ __( '%d story/stories created.', 'alf-wp-stories' ), intval( $_REQUEST['alf_wp_stories_created'] ) ) )
			);
		}
	}

	/**
	 * Enqueue admin assets on the Media Library page.
	 *
	 * @param string $hook Current admin page hook.
	 * @return void
	 */
	public function enqueue( $hook ) {
		if ( 'upload.php' !== $hook ) {
			return;
		}
		wp_enqueue_style( 'alf-wp-stories-admin', ALF_WP_STORIES_URL . 'assets/css/admin.css', array(), ALF_WP_STORIES_VERSION );
		wp_enqueue_style( 'alf-wp-stories-bulk', ALF_WP_STORIES_URL . 'assets/css/bulk.css', array(), ALF_WP_STORIES_VERSION );
		wp_enqueue_script( 'alf-wp-stories-bulk', ALF_WP_STORIES_URL . 'assets/js/admin-bulk.js', array( 'jquery' ), ALF_WP_STORIES_VERSION, true );

		// Localize taxonomy terms for the modal, if taxonomy enabled.
		$term_data = array();
		if ( $this->options->taxonomy_enabled() ) {
			$terms = get_terms( array( 'taxonomy' => $this->options->taxonomy_key(), 'hide_empty' => false ) );
			if ( ! is_wp_error( $terms ) && is_array( $terms ) ) {
				foreach ( $terms as $term ) {
					$term_data[] = array( 'term_id' => $term->term_id, 'name' => $term->name );
				}
			}
		}

		wp_localize_script( 'alf-wp-stories-bulk', 'alfWpStoriesBulk', array(
			'nonce'        => wp_create_nonce( 'alf_wp_stories_bulk_create' ),
			'ajaxUrl'      => admin_url( 'admin-ajax.php' ),
			'cptLabel'     => $this->options->get( 'post_type.plural', 'Client Stories' ),
			'defaultStatus' => $this->options->get( 'story.default_post_status', 'publish' ),
			'terms'        => $term_data,
			'hasRatioCheck' => class_exists( 'WP_Alf_Img_Ratio_Check\Plugin' ),
		) );
	}

	/**
	 * Render the bulk-create confirmation modal in the Media Library footer.
	 *
	 * @return void
	 */
	public function render_modal() {
		?>
		<div id="alf-wp-stories-modal" class="alf-wp-stories-modal" style="display:none;">
			<div class="alf-wp-stories-modal-overlay"></div>
			<div class="alf-wp-stories-modal-content">
				<h2><?php esc_html_e( 'Create Stories', 'alf-wp-stories' ); ?></h2>
				<p class="alf-wp-stories-modal-count"></p>

				<div class="alf-wp-stories-modal-status">
					<p><label>
						<?php esc_html_e( 'Post status', 'alf-wp-stories' ); ?>
						<select id="alf-wp-stories-modal-status">
							<option value="publish"><?php esc_html_e( 'Publish', 'alf-wp-stories' ); ?></option>
							<option value="draft"><?php esc_html_e( 'Draft', 'alf-wp-stories' ); ?></option>
						</select>
					</label></p>
				</div>

				<div class="alf-wp-stories-modal-terms" style="display:none;">
					<label><strong><?php esc_html_e( 'Assign taxonomy terms:', 'alf-wp-stories' ); ?></strong></label>
					<div id="alf-wp-stories-term-list" class="alf-wp-stories-term-checkboxes"></div>
				</div>

				<div class="alf-wp-stories-modal-ratio" style="display:none;">
					<p class="alf-wp-stories-ratio-warning"></p>
				</div>

				<div class="alf-wp-stories-modal-actions">
					<button type="button" class="button button-primary" id="alf-wp-stories-modal-confirm"><?php esc_html_e( 'Create Stories', 'alf-wp-stories' ); ?></button>
					<button type="button" class="button" id="alf-wp-stories-modal-cancel"><?php esc_html_e( 'Cancel', 'alf-wp-stories' ); ?></button>
				</div>
				<div id="alf-wp-stories-modal-progress" style="display:none;">
					<span class="spinner is-active"></span> <?php esc_html_e( 'Creating stories…', 'alf-wp-stories' ); ?>
				</div>
			</div>
		</div>
		<?php
	}

	/**
	 * AJAX: preview a filename parse (used by the settings live preview tool).
	 *
	 * @return void
	 */
	public function ajax_preview_filename() {
		check_ajax_referer( 'alf_wp_stories_preview_filename', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( 'Permission denied.', 403 );
		}

		$filename = isset( $_POST['filename'] ) ? sanitize_text_field( wp_unslash( $_POST['filename'] ) ) : '';
		if ( '' === $filename ) {
			wp_send_json_error( 'No filename provided.', 400 );
		}

		// Strip extension if provided.
		$filename = pathinfo( $filename, PATHINFO_FILENAME );
		$grammar  = $this->options->get( 'filename_grammar', array() );

		$result = $this->parser->parse( $filename, $grammar );

		wp_send_json_success( array(
			'input'  => $filename,
			'parsed'  => $result,
			'grammar' => $grammar,
		) );
	}

	/**
	 * AJAX: bulk-create stories from selected media attachments.
	 *
	 * @return void
	 */
	public function ajax_bulk_create() {
		check_ajax_referer( 'alf_wp_stories_bulk_create', 'nonce' );

		if ( ! current_user_can( 'edit_posts' ) ) {
			wp_send_json_error( 'Permission denied.', 403 );
		}

		$attachment_ids = isset( $_POST['attachment_ids'] ) ? array_map( 'absint', (array) $_POST['attachment_ids'] ) : array();
		$term_ids        = isset( $_POST['term_ids'] ) ? array_map( 'absint', (array) $_POST['term_ids'] ) : array();
		$post_status     = isset( $_POST['post_status'] ) && 'draft' === $_POST['post_status'] ? 'draft' : 'publish';

		if ( empty( $attachment_ids ) ) {
			wp_send_json_error( 'No images selected.', 400 );
		}

		$groups = $this->group_attachments( $attachment_ids );

		$created         = 0;
		$updated          = 0;
		$frames_total     = 0;
		$needs_attention = array();
		$errors          = array();

		foreach ( $groups as $group_key => $attachments ) {
			$result = $this->ingest_group( $group_key, $attachments, $term_ids, $post_status );
			if ( is_wp_error( $result ) ) {
				$errors[] = $result->get_error_message();
				continue;
			}
			if ( $result['created'] ) {
				$created++;
			} elseif ( $result['updated'] ) {
				$updated++;
			}
			$frames_total += $result['frames'];
			if ( ! empty( $result['needs_attention'] ) ) {
				$needs_attention = array_merge( $needs_attention, $result['needs_attention'] );
			}
		}

		wp_send_json_success( array(
			'created'         => $created,
			'updated'         => $updated,
			'frames'          => $frames_total,
			'needs_attention' => $needs_attention,
			'errors'          => $errors,
			'message'         => sprintf(
				/* translators: 1: created, 2: updated, 3: frames */
				__( '%1$d created, %2$d updated, %3$d frames.', 'alf-wp-stories' ),
				$created,
				$updated,
				$frames_total
			),
		) );
	}

	/**
	 * Group selected attachments by the configured group key.
	 *
	 * @param int[] $attachment_ids Attachment IDs.
	 * @return array Map of group_key => array of attachment arrays {id, parsed}.
	 */
	private function group_attachments( $attachment_ids ) {
		$groups = array();
		// Dedupe attachment IDs.
		$ids = array_unique( array_filter( array_map( 'absint', $attachment_ids ) ) );

		foreach ( $ids as $attachment_id ) {
			$attachment = get_post( $attachment_id );
			if ( ! $attachment || 'attachment' !== $attachment->post_type ) {
				continue;
			}
			$filename = pathinfo( (string) get_attached_file( $attachment_id ), PATHINFO_FILENAME );
			$parsed   = $this->parser->parse( $filename );

			$key = $parsed['group_key'];
			if ( '' === $key ) {
				$key = 'attachment-' . $attachment_id;
			}
			if ( ! isset( $groups[ $key ] ) ) {
				$groups[ $key ] = array();
			}
			$groups[ $key ][] = array(
				'id'     => $attachment_id,
				'parsed' => $parsed,
			);
		}

		// Sort each group's frames by frame_order if present, else by attachment ID.
		foreach ( $groups as $key => $attachments ) {
			usort( $groups[ $key ], function ( $a, $b ) {
				$oa = isset( $a['parsed']['frame_order'] ) ? $a['parsed']['frame_order'] : null;
				$ob = isset( $b['parsed']['frame_order'] ) ? $b['parsed']['frame_order'] : null;
				if ( null !== $oa && null !== $ob ) {
					return $oa <=> $ob;
				}
				return $a['id'] <=> $b['id'];
			} );
		}

		return $groups;
	}

	/**
	 * Create or update a story from a group of attachments.
	 *
	 * @param string $group_key   Group key.
	 * @param array  $attachments Array of {id, parsed}.
	 * @param int[]  $term_ids    Taxonomy term IDs to assign.
	 * @param string $post_status Requested post status.
	 * @return array|\WP_Error { created: bool, updated: bool, frames: int, needs_attention: array }
	 */
	private function ingest_group( $group_key, $attachments, $term_ids, $post_status ) {
		if ( empty( $attachments ) ) {
			return new \WP_Error( 'empty_group', 'Empty group.' );
		}

		// Use the first parsed title as the post title.
		$title   = $attachments[0]['parsed']['title'];
		$caption = $attachments[0]['parsed']['caption'];

		// Collect attachment IDs in this group.
		$source_ids = array();
		foreach ( $attachments as $a ) {
			$source_ids[] = (int) $a['id'];
		}

		// Idempotency: find an existing story with this group key.
		$existing_id = $this->find_existing_story( $group_key );

		// Resolve cover from the configured default cover source.
		$cover_id = $this->resolve_cover( $attachments );

		// Build frames array.
		$frames = array();
		foreach ( $attachments as $a ) {
			$frames[] = array(
				'image_id' => (int) $a['id'],
				'alt'      => $title,
				'duration' => 0,
			);
		}

		// Compliance gate: if the ratio-check plugin is active, check the cover.
		$needs_attention = array();
		$final_status = $post_status;
		if ( $this->ratio_check_active() ) {
			$cover_compliant = $this->attachment_ratio_ok( $cover_id );
			if ( false === $cover_compliant && 'publish' === $post_status ) {
				// Downgrade to draft so Metricool cannot ingest a non-compliant item.
				$final_status = 'draft';
				$needs_attention[] = array(
					'group_key'     => $group_key,
					'attachment_id' => $cover_id,
					'reason'        => 'non-compliant cover ratio',
				);
			}
		}

		$postarr = array(
			'post_type'   => $this->options->post_type_key(),
			'post_title'  => $title,
			'post_status' => $final_status,
		);

		if ( $existing_id ) {
			$postarr['ID'] = $existing_id;
			$post_id = wp_update_post( $postarr, true );
		} else {
			$post_id = wp_insert_post( $postarr, true );
		}

		if ( is_wp_error( $post_id ) || ! $post_id ) {
			return new \WP_Error( 'insert_failed', 'Could not create story: ' . $title );
		}

		// Save story meta through the canonical model's meta keys.
		if ( $cover_id ) {
			update_post_meta( $post_id, Story::META_COVER, $cover_id );
			set_post_thumbnail( $post_id, $cover_id );
		}
		update_post_meta( $post_id, Story::META_CAPTION, $caption );
		update_post_meta( $post_id, Story::META_FRAMES, $frames );
		update_post_meta( $post_id, self::META_SOURCE_IDS, $source_ids );
		update_post_meta( $post_id, self::META_GROUP_KEY, $group_key );

		// Taxonomy terms: from form + from parsed tags.
		$assign_terms = array_merge( $term_ids, $this->parsed_term_ids( $attachments[0]['parsed'] ) );
		if ( ! empty( $assign_terms ) && $this->options->taxonomy_enabled() ) {
			wp_set_object_terms( $post_id, array_map( 'absint', $assign_terms ), $this->options->taxonomy_key() );
		}

		return array(
			'created'         => $existing_id ? false : true,
			'updated'         => $existing_id ? true : false,
			'frames'          => count( $frames ),
			'needs_attention' => $needs_attention,
		);
	}

	/**
	 * Resolve the cover attachment ID per the configured default cover source.
	 *
	 * @param array $attachments Group attachments.
	 * @return int Attachment ID.
	 */
	private function resolve_cover( $attachments ) {
		$source = $this->options->get( 'story.default_cover_source', 'first_frame' );

		if ( 'last_frame' === $source ) {
			$last = end( $attachments );
			return (int) $last['id'];
		}

		if ( 'largest' === $source ) {
			$largest_id = 0;
			$largest_size = 0;
			foreach ( $attachments as $a ) {
				$meta = wp_get_attachment_metadata( $a['id'] );
				$area = ( is_array( $meta ) && isset( $meta['width'], $meta['height'] ) ) ? (int) $meta['width'] * (int) $meta['height'] : 0;
				if ( $area > $largest_size ) {
					$largest_size = $area;
					$largest_id = (int) $a['id'];
				}
			}
			return $largest_id ? $largest_id : (int) $attachments[0]['id'];
		}

		// first_frame (default).
		return (int) $attachments[0]['id'];
	}

	/**
	 * Convert parsed tags into taxonomy term IDs (creating terms as needed).
	 *
	 * @param array $parsed Parsed filename result.
	 * @return int[] Term IDs.
	 */
	private function parsed_term_ids( $parsed ) {
		if ( ! $this->options->taxonomy_enabled() || empty( $parsed['tags'] ) ) {
			return array();
		}
		$tax = $this->options->taxonomy_key();
		$ids = array();
		foreach ( $parsed['tags'] as $tag ) {
			if ( '' === $tag ) {
				continue;
			}
			$res = wp_insert_term( $tag, $tax );
			if ( is_wp_error( $res ) && $res->get_error_code() === 'term_exists' ) {
				// Already exists; fetch its term_id.
				$existing = term_exists( $tag, $tax );
				if ( isset( $existing['term_id'] ) ) {
					$ids[] = (int) $existing['term_id'];
				}
			} elseif ( ! is_wp_error( $res ) && isset( $res['term_id'] ) ) {
				$ids[] = (int) $res['term_id'];
			}
		}
		return $ids;
	}

	/**
	 * Find an existing story post by its group key meta.
	 *
	 * @param string $group_key Group key.
	 * @return int Post ID or 0.
	 */
	private function find_existing_story( $group_key ) {
		$query = new \WP_Query( array(
			'post_type'      => $this->options->post_type_key(),
			'post_status'    => array( 'publish', 'draft', 'private' ),
			'posts_per_page' => 1,
			'fields'         => 'ids',
			'no_found_rows'  => true,
			'meta_query'     => array(
				array(
					'key'   => self::META_GROUP_KEY,
					'value' => $group_key,
				),
			),
		) );
		return ( is_array( $query->posts ) && ! empty( $query->posts[0] ) ) ? (int) $query->posts[0] : 0;
	}

	/**
	 * Whether the ratio-check plugin is active.
	 *
	 * @return bool
	 */
	private function ratio_check_active() {
		return class_exists( 'WP_Alf_Img_Ratio_Check\Plugin' );
	}

	/**
	 * Check an attachment's ratio compliance via the ratio-check plugin.
	 *
	 * @param int $attachment_id Attachment ID.
	 * @return bool|null True=compliant, false=non-compliant, null=unknown/inactive.
	 */
	private function attachment_ratio_ok( $attachment_id ) {
		if ( ! $this->ratio_check_active() || ! $attachment_id ) {
			return null;
		}
		$plugin = Plugin::instance()->get( 'integration' );
		if ( ! $plugin instanceof Integration ) {
			return null;
		}
		return $plugin->attachment_is_compliant( $attachment_id );
	}
}
