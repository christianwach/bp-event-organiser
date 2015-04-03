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
	/*
	 * Hack: distinguish between create and edit by comparing created and modified date.
	 * See https://github.com/stephenharris/Event-Organiser/pull/253.
	 */
	$event = get_post( $event_id );
	if ( $event->post_date === $event->post_modified ) {
		$type = 'bpeo_create_event';
	} else {
		$type = 'bpeo_edit_event';
	}

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
add_action( 'eventorganiser_save_event', 'bpeo_create_activity_on_event_save', 20 );

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
