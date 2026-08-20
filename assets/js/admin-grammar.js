/**
 * Settings page: color picker init, grammar repeater (add/remove rows),
 * and live filename preview via AJAX.
 */
( function ( $ ) {
	'use strict';

	var cfg = window.alfWpStoriesGrammar || { nonce: '', ajaxUrl: '' };

	$( function () {
		// Color pickers.
		$( '.alf-wp-stories-color-field' ).wpColorPicker();

		// Grammar repeater.
		var $table = $( '.alf-wp-stories-grammar-table' );
		if ( $table.length ) {
			// Remove handler (delegated).
			$table.on( 'click', '.alf-wp-stories-grammar-remove', function () {
				$( this ).closest( 'tr' ).remove();
			} );

			// Add handler.
			$( '.alf-wp-stories-grammar-add' ).on( 'click', function () {
				var $tbody = $table.find( 'tbody' );
				var index = $tbody.children().length;
				var $row = $( '<tr>' );
				$row.append( '<td>' + ( index + 1 ) + '</td>' );
				$row.append( buildSelectCell( 'target', index, cfg.targets || {} ) );
				$row.append( buildSelectCell( 'transform', index, cfg.transforms || {} ) );
				$row.append( '<td><button type="button" class="button-link-delete alf-wp-stories-grammar-remove">Remove</button></td>' );
				$tbody.append( $row );
			} );
		}

		// Live preview.
		$( '#alf-wp-stories-grammar-preview-btn' ).on( 'click', function () {
			var filename = $( '#alf-wp-stories-grammar-preview-input' ).val();
			var $out = $( '#alf-wp-stories-grammar-preview-out' );
			$out.text( 'Parsing…' );
			$.post( cfg.ajaxUrl, {
				action: 'alf_wp_stories_preview_filename',
				nonce: cfg.nonce,
				filename: filename
			} ).done( function ( res ) {
				if ( ! res || ! res.success ) {
					$out.text( 'Error: ' + ( ( res && res.data ) || 'failed' ) );
					return;
				}
				var p = res.data.parsed || {};
				var html = '';
				html += '<span class="kv"><span>title:</span> ' + esc( p.title || '' ) + '</span>';
				html += '<span class="kv"><span>caption:</span> ' + esc( p.caption || '' ) + '</span>';
				html += '<span class="kv"><span>tags:</span> ' + esc( ( p.tags || [] ).join( ', ' ) ) + '</span>';
				html += '<span class="kv"><span>frame_order:</span> ' + esc( p.frame_order === null ? '—' : p.frame_order ) + '</span>';
				html += '<span class="kv"><span>group_key:</span> ' + esc( p.group_key || '' ) + '</span>';
				$out.html( html );
			} ).fail( function () {
				$out.text( 'Request failed.' );
			} );
		} );
	} );

	function buildSelectCell( name, index, options ) {
		var base = 'alf_wp_stories_options[filename_grammar][segments][' + index + '][' + name + ']';
		var html = '<td><select name="' + base + '">';
		Object.keys( options ).forEach( function ( k ) {
			html += '<option value="' + k + '">' + options[ k ] + '</option>';
		} );
		html += '</select></td>';
		return html;
	}

	function esc( s ) {
		return $( '<span>' ).text( String( s ) ).html();
	}
}( jQuery ) );
