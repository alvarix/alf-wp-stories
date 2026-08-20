<?php
/**
 * Filename parser: maps a filename to story fields using a configurable grammar.
 *
 * Pure PHP — no WordPress dependencies. Unit-testable in isolation.
 *
 * Grammar shape (from Options::get('filename_grammar')):
 *   delimiter: string (fixed to '__' by Options, but parser accepts any)
 *   group_key: string  (a target name; segments matching this target form the group key)
 *   segments:  array of { target: string, transform: string }
 *
 * @package ALF_WP_Stories
 */

namespace ALF_WP_Stories;

defined( 'ABSPATH' ) || exit;

/**
 * Filename grammar parser.
 */
class Filename_Parser {

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
	 * Register hooks (none — pure accessor; kept for interface parity).
	 *
	 * @return void
	 */
	public function register() {
	}

	/**
	 * Parse a filename (without extension) into structured fields.
	 *
	 * @param string $filename Filename without extension.
	 * @param array  $grammar  Optional grammar override; defaults to configured.
	 * @return array {
	 *   title: string,
	 *   caption: string,
	 *   tags: string[],
	 *   frame_order: int|null,
	 *   group_key: string,
	 *   segments: array (raw parsed segment values keyed by target)
	 * }
	 */
	public function parse( $filename, $grammar = null ) {
		if ( null === $grammar ) {
			$grammar = $this->options->get( 'filename_grammar', array() );
		}

		$delimiter = isset( $grammar['delimiter'] ) ? $grammar['delimiter'] : '__';
		$segments  = isset( $grammar['segments'] ) && is_array( $grammar['segments'] ) ? $grammar['segments'] : array();
		$group_target = isset( $grammar['group_key'] ) ? $grammar['group_key'] : 'title';

		// Strip WordPress-appended suffixes (-scaled, -rotated, -1024x768).
		$filename = preg_replace( '/-(scaled|rotated|\d+x\d+)$/', '', $filename );

		$parts = explode( $delimiter, $filename );

		$result = array(
			'title'        => '',
			'caption'      => '',
			'tags'         => array(),
			'frame_order'  => null,
			'group_key'    => '',
			'segments'     => array(),
		);

		foreach ( $segments as $i => $seg ) {
			$target    = isset( $seg['target'] ) ? $seg['target'] : 'ignore';
			$transform = isset( $seg['transform'] ) ? $seg['transform'] : 'raw';
			$raw       = isset( $parts[ $i ] ) ? $parts[ $i ] : '';

			$value = $this->transform( $raw, $transform, $target );
			$result['segments'][ $target ] = $value;

			switch ( $target ) {
				case 'title':
					if ( '' === $result['title'] ) {
						$result['title'] = $value;
					}
					break;
				case 'caption':
					$result['caption'] = $value;
					break;
				case 'tag':
					if ( '' !== $value ) {
						$result['tags'][] = $value;
						// Consume remaining parts as tags (repeatable).
						for ( $j = $i + 1; $j < count( $parts ); $j++ ) {
							$extra = $this->transform( $parts[ $j ], $transform, 'tag' );
							if ( '' !== $extra ) {
								$result['tags'][] = $extra;
							}
						}
					}
					break;
				case 'frame_order':
					$result['frame_order'] = '' === $value ? null : (int) $value;
					break;
				case 'ignore':
				default:
					break;
			}

			if ( $target === $group_target && '' !== $value ) {
				$result['group_key'] = $value;
			}
		}

		// If the group target never set a key, fall back to the title.
		if ( '' === $result['group_key'] ) {
			$result['group_key'] = $result['title'];
		}

		// Ensure a non-empty group key for single-image stories.
		if ( '' === $result['group_key'] ) {
			$result['group_key'] = $filename;
			$result['title']     = $this->transform( $filename, 'title_case', 'title' );
		}

		return $result;
	}

	/**
	 * Apply a transform to a raw segment value.
	 *
	 * @param string $raw       Raw segment.
	 * @param string $transform Transform name.
	 * @param string $target    Target (affects tag splitting).
	 * @return string
	 */
	private function transform( $raw, $transform, $target ) {
		$raw = (string) $raw;
		if ( '' === $raw ) {
			return '';
		}

		switch ( $transform ) {
			case 'title_case':
				return ucwords( str_replace( array( '-', '_', '.' ), ' ', $raw ) );
			case 'slugify':
				return sanitize_title( $raw );
			case 'integer':
				// Extract leading integer.
				if ( preg_match( '/\d+/', $raw, $m ) ) {
					return $m[0];
				}
				return '';
			case 'raw':
			default:
				// For tags, split on '.' and return first sub-segment.
				if ( 'tag' === $target ) {
					$parts = explode( '.', $raw );
					return trim( str_replace( array( '-', '_' ), ' ', $parts[0] ) );
				}
				return $raw;
		}
	}
}
