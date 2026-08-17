/**
 * Story frame editor: add/remove/reorder frames, media picker for cover and frames.
 */
( function ( $ ) {
	'use strict';

	var cfg = window.alfWpStoriesAdmin || {
		frameTitle: 'Choose frame image',
		frameButton: 'Use this image',
		coverTitle: 'Choose cover image',
		coverButton: 'Use as cover image',
		defaultDuration: 5000
	};

	/**
	 * Reindex all frame rows so the submitted field names stay sequential.
	 */
	function reindex() {
		$( '#alf-wp-stories-frames' ).children( '.alf-wp-stories-frame' ).each( function ( i ) {
			$( this )
				.attr( 'data-index', i )
				.find( 'input, textarea' ).each( function () {
					var name = $( this ).attr( 'name' );
					if ( name ) {
						$( this ).attr( 'name', name.replace( /alf_wp_stories_frames\[\d+\]/, 'alf_wp_stories_frames[' + i + ']' ) );
					}
				} );
		} );
	}

	/**
	 * Build a fresh frame row.
	 *
	 * @return {jQuery}
	 */
	function newFrame() {
		var html =
			'<div class="alf-wp-stories-frame">' +
				'<span class="alf-wp-stories-drag-handle dashicons dashicons-move"></span>' +
				'<div class="alf-wp-stories-frame-preview"><span class="alf-wp-stories-placeholder">No image</span></div>' +
				'<div class="alf-wp-stories-frame-fields">' +
					'<input type="hidden" class="alf-wp-stories-frame-id" name="" value="0" />' +
					'<p><button type="button" class="button alf-wp-stories-select-frame">Select image</button></p>' +
					'<p><input type="text" class="alf-wp-stories-frame-alt regular-text" name="" value="" placeholder="Alt text" /></p>' +
					'<p><label>Duration (ms)</label> <input type="number" class="alf-wp-stories-frame-duration small-text" name="" value="' + cfg.defaultDuration + '" min="0" step="100" /></p>' +
				'</div>' +
				'<button type="button" class="button-link-delete alf-wp-stories-remove-frame">Remove</button>' +
			'</div>';
		return $( html );
	}

	/**
	 * Open the WP media frame for an image selection.
	 *
	 * @param {string}   title    Modal title.
	 * @param {string}   button   Button text.
	 * @param {Function} callback Receives the selected attachment object.
	 */
	function openMedia( title, button, callback ) {
		var frame = wp.media( {
			title: title,
			button: { text: button },
			multiple: false
		} );
		frame.on( 'select', function () {
			var attachment = frame.state().get( 'selection' ).first().toJSON();
			callback( attachment );
		} );
		frame.open();
	}

	/**
	 * Render a frame preview image.
	 *
	 * @param {jQuery} $frame Frame row.
	 * @param {object} attachment Attachment object.
	 */
	function setFrameImage( $frame, attachment ) {
		$frame.find( '.alf-wp-stories-frame-id' ).val( attachment.id );
		var url = attachment.sizes && attachment.sizes.medium ? attachment.sizes.medium.url : attachment.url;
		$frame.find( '.alf-wp-stories-frame-preview' ).html( $( '<img />' ).attr( 'src', url ).attr( 'alt', '' ) );
	}

	$( function () {
		var $frames = $( '#alf-wp-stories-frames' );

		// Enable drag-and-drop ordering.
		$frames.sortable( {
			handle: '.alf-wp-stories-drag-handle',
			placeholder: 'alf-wp-stories-frame ui-sortable-placeholder',
			update: reindex
		} );

		// Add frame.
		$( '.alf-wp-stories-add-frame' ).on( 'click', function () {
			var $f = newFrame();
			$frames.append( $f );
			reindex();
		} );

		// Remove frame (delegated).
		$frames.on( 'click', '.alf-wp-stories-remove-frame', function () {
			$( this ).closest( '.alf-wp-stories-frame' ).remove();
			reindex();
		} );

		// Select frame image (delegated).
		$frames.on( 'click', '.alf-wp-stories-select-frame', function () {
			var $frame = $( this ).closest( '.alf-wp-stories-frame' );
			openMedia( cfg.frameTitle, cfg.frameButton, function ( attachment ) {
				setFrameImage( $frame, attachment );
			} );
		} );

		// Cover image.
		$( '.alf-wp-stories-select-cover' ).on( 'click', function () {
			openMedia( cfg.coverTitle, cfg.coverButton, function ( attachment ) {
				$( '#alf-wp-stories-cover-id' ).val( attachment.id );
				var url = attachment.sizes && attachment.sizes.medium ? attachment.sizes.medium.url : attachment.url;
				$( '#alf-wp-stories-cover' ).html( $( '<img />' ).attr( 'src', url ).attr( 'alt', '' ) );
				$( '.alf-wp-stories-remove-cover' ).show();
			} );
		} );

		// Remove cover.
		$( '.alf-wp-stories-remove-cover' ).on( 'click', function () {
			$( '#alf-wp-stories-cover-id' ).val( '' );
			$( '#alf-wp-stories-cover' ).html( '<p class="alf-wp-stories-placeholder">No cover image selected.</p>' );
			$( this ).hide();
		} );

		// Reindex once on load in case of server-side reordering.
		reindex();
	} );
}( jQuery ) );
