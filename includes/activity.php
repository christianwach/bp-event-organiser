<?php

/**
 * Activity component integration.
 */

/**
 * Create activity on event save.
 *
 * The 'eventorganiser_save_event' hook fires both on insert and update, so we use this function as a router.
 *
 * Run late to ensure that group connections have been set.
 *
 * @param int $event_id ID of the event.
 */
function bpeo_create_activity_on_event_save( $event_id ) {
	if ( 'eventorganiser_created_event' === current_action() ) {
		$type = 'bpeo_create_event';
	} else {
		$type = 'bpeo_edit_event';
	}

	$event = get_post( $event_id );

	// Prevent edit floods.
	if ( 'bpeo_edit_event' === $type ) {
		$activities = bpeo_get_activity_by_event_id( $event_id );

		if ( $activities ) {

			// Just in case.
			$activities = bp_sort_by_key( $activities, 'date_recorded' );
			$last_activity = end( $activities );

			/**
			 * Filters the number of seconds in the event edit throttle.
			 *
			 * This prevents activity stream flooding by multiple edits of the same event.
			 *
			 * @param int $throttle_period Defaults to 6 hours.
			 */
			$throttle_period = apply_filters( 'bpeo_event_edit_throttle_period', 6 * HOUR_IN_SECONDS );
			if ( ( time() - strtotime( $last_activity->date_recorded ) ) < $throttle_period ) {
				return;
			}
		}
	}

	$activity_args = array(
		'component' => 'events',
		'type' => $type,
		'user_id' => $event->post_author, // @todo Event edited by non-author?
		'primary_link' => get_permalink( $event ),
		'secondary_item_id' => $event_id, // Leave 'item_id' blank for groups.
		'recorded_time' => $event->post_modified,
	);

	bp_activity_add( $activity_args );

	do_action( 'bpeo_create_event_activity', $activity_args, $event );
}
add_action( 'eventorganiser_created_event', 'bpeo_create_activity_on_event_save' );
add_action( 'eventorganiser_updated_event', 'bpeo_create_activity_on_event_save' );

/**
 * Get activity items associated with an event ID.
 *
 * @param int $event_id ID of the event.
 * @return array Array of activity items.
 */
function bpeo_get_activity_by_event_id( $event_id ) {
	$a = bp_activity_get( array(
		'filter_query' => array(
			'relation' => 'AND',
			array(
				'column' => 'component',
				'value' => array( 'groups', 'events' ),
				'compare' => 'IN',
			),
			array(
				'column' => 'type',
				'value' => array( 'bpeo_create_event', 'bpeo_edit_event' ),
				'compare' => 'IN',
			),
			array(
				'column' => 'secondary_item_id',
				'value' => $event_id,
				'compare' => '=',
			),
		),
		'show_hidden' => true,
	) );

	return $a['activities'];
}

/**
 * Register activity actions and format callbacks.
 */
function bpeo_register_activity_actions() {
	bp_activity_set_action(
		'events',
		'bpeo_create_event',
		__( 'Events created', 'bp-event-organiser' ),
		'bpeo_activity_action_format',
		__( 'Events created', 'buddypress' ),
		array( 'activity', 'member', 'group', 'member_groups' )
	);

	bp_activity_set_action(
		'events',
		'bpeo_edit_event',
		__( 'Events edited', 'bp-event-organiser' ),
		'bpeo_activity_action_format',
		__( 'Events edited', 'buddypress' ),
		array( 'activity', 'member', 'group', 'member_groups' )
	);
}
add_action( 'bp_register_activity_actions', 'bpeo_register_activity_actions' );

/**
 * Format activity action strings.
 */
function bpeo_activity_action_format( $action, $activity ) {
	$event = get_post( $activity->secondary_item_id );

	// Sanity check - mainly for unit tests.
	if ( ! ( $event instanceof WP_Post ) || 'event' !== $event->post_type ) {
		return $action;
	}

	switch ( $activity->type ) {
		case 'bpeo_create_event' :
			/* translators: 1: link to user, 2: link to event */
			$base = __( '%1$s created the event %2$s', 'bp-event-organiser' );
			break;
		case 'bpeo_edit_event' :
			/* translators: 1: link to user, 2: link to event */
			$base = __( '%1$s edited the event %2$s', 'bp-event-organiser' );
			break;
	}

	$action = sprintf(
		$base,
		sprintf( '<a href="%s">%s</a>', esc_url( bp_core_get_user_domain( $activity->user_id ) ), esc_html( bp_core_get_user_displayname( $activity->user_id ) ) ),
		sprintf( '<a href="%s">%s</a>', esc_url( get_permalink( $event ) ), esc_html( $event->post_title ) )
	);

	return $action;
}
