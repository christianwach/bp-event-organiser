<?php

/**
 * Group functionality.
 */

/**
 * Register group connection taxonomy.
 *
 * Fires at init:15 to ensure EO has a chance to register its post type first.
 */
function bpeo_register_group_connection_taxonomy() {
	register_taxonomy( 'bpeo_event_group', 'event', array(
		'public' => false,
	) );
}
add_action( 'init', 'bpeo_register_group_connection_taxonomy', 15 );

/**
 * Connect an event to a group.
 *
 * @param int $event_id ID of the event.
 * @param int $group_id ID of the group.
 * @return bool|WP_Error True on success, WP_Error on failure.
 */
function bpeo_connect_event_to_group( $event_id, $group_id ) {
	$group = groups_get_group( array( 'group_id' => $group_id ) );
	if ( ! $group->id ) {
		return new WP_Error( 'group_not_found', __( 'No group found by that ID.', 'bp-event-organiser' ) );
	}

	$event = get_post( $event_id );
	if ( ! ( $event instanceof WP_Post ) || 'event' !== $event->post_type ) {
		return new WP_Error( 'event_not_found', __( 'No event found by that ID.', 'bp-event-organiser' ) );
	}

	$set = wp_set_object_terms( $event_id, array( 'group_' . $group_id ), 'bpeo_event_group' );

	if ( is_wp_error( $set ) || empty( $set ) ) {
		return $set;
	} else {
		return true;
	}
}

/**
 * Disconnect an event from a group.
 *
 * @param int $event_id ID of the event.
 * @param int $group_id ID of the group.
 * @return bool|WP_Error True on success, WP_Error on failure.
 */
 function bpeo_disconnect_event_from_group( $event_id, $group_id ) {
	$group = groups_get_group( array( 'group_id' => $group_id ) );
	if ( ! $group->id ) {
		return new WP_Error( 'group_not_found', __( 'No group found by that ID.', 'bp-event-organiser' ) );
	}

	$event = get_post( $event_id );
	if ( ! ( $event instanceof WP_Post ) || 'event' !== $event->post_type ) {
		return new WP_Error( 'event_not_found', __( 'No event found by that ID.', 'bp-event-organiser' ) );
	}
 }

/**
 * Get event IDs associated with a group.
 *
 * @param int   $group_id ID of the group.
 * @param array $args {
 *     Optional query args. All WP_Query args are accepted, along with the following.
 *     @type bool $showpastevents True to show past events, false otherwise. Default: false.
 * }
 * @return array Array of event IDs.
 */
function bpeo_get_group_events( $group_id, $args = array() ) {
	$r = array_merge( array(
		'posts_per_page' => -1,
		'showpastevents' => true,
	), $args );

	$r['fields'] = 'ids';
	$r['post_type'] = 'event';

	$r['tax_query'] = array(
		array(
			'taxonomy' => 'bpeo_event_group',
			'terms' => 'group_' . $group_id,
			'field' => 'name',
		),
	);

	$q = new WP_Query( $r );

	return $q->posts;
}
