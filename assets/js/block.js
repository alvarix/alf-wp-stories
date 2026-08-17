/**
 * Story launcher Gutenberg block.
 *
 * Server-side rendered: the editor preview fetches the rendered markup from
 * the server so the latest-story resolution always reflects current content.
 */
( function ( wp ) {
	var el = wp.element.createElement;
	var Fragment = wp.element.Fragment;
	var InspectorControls = wp.blockEditor.InspectorControls;
	var ServerSideRender = wp.serverSideRender;
	var PanelBody = wp.components.PanelBody;
	var SelectControl = wp.components.SelectControl;
	var TextControl = wp.components.TextControl;
	var ToggleControl = wp.components.ToggleControl;
	var RangeControl = wp.components.RangeControl;
	var __ = wp.i18n.__;

	wp.blocks.registerBlockType( 'alf-wp-stories/launcher', {
		title: __( 'Story Launcher', 'alf-wp-stories' ),
		icon: 'format-image',
		category: 'widgets',
		attributes: {
			mode: { type: 'string', default: 'latest' },
			storyId: { type: 'number', default: 0 },
			term: { type: 'string', default: '' },
			limit: { type: 'number', default: 1 },
			size: { type: 'number', default: 56 },
			showTitle: { type: 'boolean', default: true },
			ring: { type: 'boolean', default: true }
		},

		edit: function ( props ) {
			var attrs = props.attributes;
			var setAttrs = props.setAttributes;

			var inspector = el(
				InspectorControls,
				null,
				el(
					PanelBody,
					{ title: __( 'Story selection', 'alf-wp-stories' ), initialOpen: true },
					el( SelectControl, {
						label: __( 'Mode', 'alf-wp-stories' ),
						value: attrs.mode,
						options: [
							{ label: __( 'Latest story', 'alf-wp-stories' ), value: 'latest' },
							{ label: __( 'Specific story', 'alf-wp-stories' ), value: 'specific' },
							{ label: __( 'Latest from taxonomy term', 'alf-wp-stories' ), value: 'term' }
						],
						onChange: function ( v ) {
							setAttrs( { mode: v } );
						}
					} ),
					attrs.mode === 'specific' && el( TextControl, {
						label: __( 'Story ID', 'alf-wp-stories' ),
						value: attrs.storyId,
						onChange: function ( v ) {
							setAttrs( { storyId: parseInt( v, 10 ) || 0 } );
						}
					} ),
					attrs.mode === 'term' && el( TextControl, {
						label: __( 'Term slug', 'alf-wp-stories' ),
						value: attrs.term,
						onChange: function ( v ) {
							setAttrs( { term: v } );
						}
					} )
				),
				el(
					PanelBody,
					{ title: __( 'Display', 'alf-wp-stories' ), initialOpen: false },
					el( RangeControl, {
						label: __( 'Number of items', 'alf-wp-stories' ),
						value: attrs.limit,
						min: 1,
						max: 12,
						onChange: function ( v ) {
							setAttrs( { limit: v } );
						}
					} ),
					el( RangeControl, {
						label: __( 'Circle diameter (px)', 'alf-wp-stories' ),
						value: attrs.size,
						min: 24,
						max: 160,
						onChange: function ( v ) {
							setAttrs( { size: v } );
						}
					} ),
					el( ToggleControl, {
						label: __( 'Show title', 'alf-wp-stories' ),
						checked: attrs.showTitle,
						onChange: function ( v ) {
							setAttrs( { showTitle: v } );
						}
					} ),
					el( ToggleControl, {
						label: __( 'Story ring', 'alf-wp-stories' ),
						checked: attrs.ring,
						onChange: function ( v ) {
							setAttrs( { ring: v } );
						}
					} )
				)
			);

			var preview = el( ServerSideRender, {
				block: 'alf-wp-stories/launcher',
				attributes: attrs
			} );

			return el( Fragment, null, inspector, preview );
		},

		save: function () {
			// Server-side rendered; return null.
			return null;
		}
	} );
} )( window.wp );
