/**
 * Bulk story creator: intercept the Media Library bulk action, open the modal,
 * query the ratio-checker (if active) for a grouping preview, then POST to
 * the AJAX creator.
 */
( function ( $ ) {
	'use strict';

	var cfg = window.alfWpStoriesBulk || {
		nonce: '',
		ajaxUrl: '',
		cptLabel: 'Stories',
		defaultStatus: 'publish',
		terms: [],
		hasRatioCheck: false
	};

	var $modal = null;
	var selectedIds = [];

	$( function () {
		$modal = $( '#alf-wp-stories-modal' );

		$( 'select[name="action"], select[name="action2"]' ).on( 'change', function () {
			// Just enabling; the actual intercept is on form submit.
		} );

		// Intercept the bulk action form submit (top + bottom).
		$( 'form#posts-filter' ).on( 'submit', function ( e ) {
			var action = $( 'select[name="action"]' ).val() || $( 'select[name="action2"]' ).val();
			if ( action !== 'alf_wp_stories_create' ) {
				return true;
			}
			e.preventDefault();
			collectAndOpen();
		} );

		$( '#alf-wp-stories-modal-cancel, .alf-wp-stories-modal-overlay' ).on( 'click', closeModal );
		$( '#alf-wp-stories-modal-confirm' ).on( 'click', confirmCreate );
	} );

	function collectAndOpen() {
		selectedIds = [];
		$( 'input[name="media[]"]:checked' ).each( function () {
			selectedIds.push( $( this ).val() );
		} );

		if ( selectedIds.length === 0 ) {
			alert( 'Please select at least one image first.' );
			return;
		}

		// Default the status selector to the configured default.
		$( '#alf-wp-stories-modal-status' ).val( cfg.defaultStatus );

		// Render taxonomy term checkboxes.
		var $terms = $( '#alf-wp-stories-term-list' );
		$terms.empty();
		if ( cfg.terms && cfg.terms.length ) {
			$( '.alf-wp-stories-modal-terms' ).show();
			cfg.terms.forEach( function ( t ) {
				$terms.append(
					'<label><input type="checkbox" name="alf_wp_stories_term_ids[]" value="' + t.term_id + '" /> ' +
					$( '<div>' ).text( t.name ).html() +
					'</label>'
				);
			} );
		} else {
			$( '.alf-wp-stories-modal-terms' ).hide();
		}

		$modal.find( '.alf-wp-stories-modal-count' ).text( selectedIds.length + ' image(s) selected.' );

		// Ratio grouping preview.
		var $ratio = $( '.alf-wp-stories-modal-ratio' );
		$ratio.hide();
		$( '#alf-wp-stories-modal-progress' ).hide();
		$( '#alf-wp-stories-modal-confirm' ).prop( 'disabled', false );

		if ( cfg.hasRatioCheck && window.wpAlfImgRatioCheck && window.wpAlfImgRatioCheck.check ) {
			window.wpAlfImgRatioCheck.check( selectedIds ).then( function ( items ) {
				var bad = [];
				Object.keys( items ).forEach( function ( id ) {
					if ( items[ id ].compliant === false ) {
						bad.push( id );
					}
				} );
				if ( bad.length ) {
					$ratio.find( '.alf-wp-stories-ratio-warning' )
						.removeClass( 'is-ok' )
						.text( bad.length + ' image(s) are outside the compliant ratio range (3:4–1.91:1). ' +
							'Stories with non-compliant covers will be created as Draft so Metricool cannot ingest them. ' +
							'Use the "Fix" link on the Ratio column to crop to a compliant preset.' );
					$ratio.show();
				} else {
					$ratio.find( '.alf-wp-stories-ratio-warning' )
						.addClass( 'is-ok' )
						.text( 'All selected images are within the compliant ratio range.' );
					$ratio.show();
				}
			} );
		}

		$modal.show();
	}

	function closeModal() {
		$modal.hide();
	}

	function confirmCreate() {
		var termIds = [];
		$( 'input[name="alf_wp_stories_term_ids[]"]:checked' ).each( function () {
			termIds.push( $( this ).val() );
		} );

		var status = $( '#alf-wp-stories-modal-status' ).val() || cfg.defaultStatus;

		$( '#alf-wp-stories-modal-confirm' ).prop( 'disabled', true );
		$( '#alf-wp-stories-modal-progress' ).show();

		$.ajax( {
			url: cfg.ajaxUrl,
			method: 'POST',
			data: {
				action: 'alf_wp_stories_bulk_create',
				nonce: cfg.nonce,
				attachment_ids: selectedIds,
				term_ids: termIds,
				post_status: status
			},
			success: function ( response ) {
				if ( response && response.success ) {
					var d = response.data || {};
					var msg = d.message || 'Done.';
					if ( d.needs_attention && d.needs_attention.length ) {
						msg += '\n\n' + d.needs_attention.length + ' story/stories created as Draft because their cover ratio is non-compliant.';
					}
					alert( msg );
					closeModal();
					window.location.reload();
				} else {
					alert( 'Error: ' + ( ( response && response.data ) || 'failed' ) );
					$( '#alf-wp-stories-modal-confirm' ).prop( 'disabled', false );
					$( '#alf-wp-stories-modal-progress' ).hide();
				}
			},
			error: function () {
				alert( 'Request failed. Please try again.' );
				$( '#alf-wp-stories-modal-confirm' ).prop( 'disabled', false );
				$( '#alf-wp-stories-modal-progress' ).hide();
			}
		} );
	}
}( jQuery ) );
