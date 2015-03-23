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