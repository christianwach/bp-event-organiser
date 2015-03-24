<?php
/**
 * Common functions.
 */

/**
 * Return sanitized version of the events slug.
 */
function bpeo_get_events_slug() {
	return sanitize_title( constant( 'BPEO_EVENTS_SLUG' ) );
}

/**
 * Return sanitized version of the new events slug.
 */
function bpeo_get_events_new_slug() {
	return sanitize_title( constant( 'BPEO_EVENTS_NEW_SLUG' ) );
}

/**
 * Output the filter title depending on URL querystring.
 *
 * @see bpeo_get_the_filter_title()
 */
function bpeo_the_filter_title() {
	echo bpeo_get_the_filter_title();
}
	/**
	 * Return the filter title depending on URL querystring.
	 *
	 * If the 'cat' or 'tag' URL parameter is in use, this function will output
	 * a title based on these parameters.
	 *
	 * @return string
	 */
	function bpeo_get_the_filter_title() {
		$cat = $tag = '';

		if ( ! empty( $_GET['cat'] ) ) {
			$cat = str_replace( ',', ', ', esc_attr( $_GET['cat'] ) );
		}

		if ( ! empty( $_GET['tag'] ) ) {
			$tag = str_replace( ',', ', ', esc_attr( $_GET['tag'] ) );
		}

		if ( ! empty( $cat ) && ! empty( $tag ) ) {
			return sprintf( __( "Filtered by category '%1$s' and tag '%2$s'", 'bp-event-organizer' ), $cat, $tag );
		} elseif ( ! empty( $cat ) ) {
			return sprintf( __( "Filtered by category '%s'", 'bp-event-organizer' ), $cat );
		} elseif ( ! empty( $tag ) ) {
			return sprintf( __( "Filtered by tag '%s'", 'bp-event-organizer' ), $tag );
		} else {
			return '';
		}
	}

/** HOOKS ***************************************************************/

/**
 * Filter event taxonomy term links to match the current BP page.
 *
 * BP event content should be displayed within BP instead of event links
 * linking to Event Organiser's pages.
 *
 * @param  string $retval Current term links
 * @return string
 */
function bpeo_filter_term_list( $retval = '' ) {
	if ( ! is_buddypress() ) {
		return $retval;
	}

	global $wp_rewrite;

	$taxonomy = str_replace( 'term_links-', '', current_filter() );
	$base = str_replace( "%{$taxonomy}%", '', $wp_rewrite->get_extra_permastruct( $taxonomy ) );
	$base = home_url( $base );

	// group
	if ( bp_is_group() ) {
		$bp_base = bpeo_get_group_permalink();

	// assume user
	} else {
		$bp_base = trailingslashit( bp_displayed_user_domain() . bpeo_get_events_slug() );
	}

	// set query arg
	if ( 'event-tag' === $taxonomy ) {
		$query_arg = 'tag';
	} else {
		$query_arg = 'cat';
	}

	// string manipulation
	$retval = str_replace( $base, $bp_base . "?{$query_arg}=", $retval );
	$retval = str_replace( '/"', '"', $retval );
	return $retval;
}
add_filter( 'term_links-event-tag',      'bpeo_filter_term_list' );
add_filter( 'term_links-event-category', 'bpeo_filter_term_list' );
