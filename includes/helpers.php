<?php
/**
 * Shared helper functions.
 *
 * @package ALF_WP_Stories
 */

namespace ALF_WP_Stories;

defined( 'ABSPATH' ) || exit;

/**
 * Normalize a frame array with defaults and sanitized values.
 *
 * @param array $frame Raw frame array.
 * @return array Normalized frame: {image_id:int, alt:string, duration:int}.
 */
function normalize_frame( $frame ) {
	$frame = is_array( $frame ) ? $frame : array();

	return array(
		'image_id' => isset( $frame['image_id'] ) ? absint( $frame['image_id'] ) : 0,
		'alt'      => isset( $frame['alt'] ) ? sanitize_text_field( $frame['alt'] ) : '',
		'duration' => isset( $frame['duration'] ) ? absint( $frame['duration'] ) : 0,
	);
}

/**
 * Sanitize an entire frames collection for saving.
 *
 * @param array $frames Raw frames from the form/REST.
 * @return array
 */
function sanitize_frames( $frames ) {
	if ( ! is_array( $frames ) ) {
		return array();
	}
	$clean = array();
	foreach ( $frames as $frame ) {
		if ( ! is_array( $frame ) || empty( $frame['image_id'] ) ) {
			continue;
		}
		$clean[] = normalize_frame( $frame );
	}
	return $clean;
}

/**
 * Resolve the absolute HTTPS URL for a given attachment size.
 *
 * @param int    $attachment_id Attachment ID.
 * @param string $size          Registered or built-in image size.
 * @return string Absolute URL, or '' when unavailable.
 */
function attachment_url( $attachment_id, $size = 'large' ) {
	$url = wp_get_attachment_image_url( (int) $attachment_id, $size );
	if ( ! $url ) {
		return '';
	}
	return set_url_scheme( $url);
}

/**
 * Resolve the dimensions for an attachment size, with a graceful fallback.
 *
 * @param int    $attachment_id Attachment ID.
 * @param string $size          Registered or built-in image size.
 * @return array{0:int,1:int} Width and height.
 */
function attachment_dimensions( $attachment_id, $size = 'large' ) {
	$meta = wp_get_attachment_metadata( (int) $attachment_id );

	// Built-in sizes live under `sizes`, full lives under `file`.
	$data = array();
	if ( 'full' === $size ) {
		$data = array(
			isset( $meta['width'] ) ? (int) $meta['width'] : 0,
			isset( $meta['height'] ) ? (int) $meta['height'] : 0,
		);
	} elseif ( is_array( $meta ) && isset( $meta['sizes'][ $size ] ) ) {
		$data = array(
			(int) $meta['sizes'][ $size ]['width'],
			(int) $meta['sizes'][ $size ]['height'],
		);
	}

	if ( empty( $data[0] ) || empty( $data[1] ) ) {
		$src = attachment_url( $attachment_id, $size );
		if ( $src ) {
			$data = array( 0, 0 );
		}
	}

	return array( (int) $data[0], (int) $data[1] );
}

/**
 * Escape a value for use inside an HTML attribute.
 *
 * Thin wrapper so callers are explicit about context.
 *
 * @param string $value Raw value.
 * @return string
 */
function esc_attr_safe( $value ) {
	return esc_attr( (string) $value );
}

/**
 * Locate a plugin template, allowing theme overrides.
 *
 * Theme override paths:
 *   {theme}/alf-wp-stories/{slug}.php
 *
 * @param string $slug Template slug without extension.
 * @return string Absolute path to the template file (plugin fallback).
 */
function locate_template_file( $slug ) {
	$theme_file = locate_template( array( 'alf-wp-stories/' . $slug . '.php' ) );
	if ( $theme_file ) {
		return $theme_file;
	}
	$plugin_file = ALF_WP_STORIES_DIR . 'templates/' . $slug . '.php';
	return file_exists( $plugin_file ) ? $plugin_file : '';
}
