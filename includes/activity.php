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
function bpeo_create_activity_for_event( $event_id, $event, $update ) {
	if ( 'event' !== $event->post_type ) {
		return;
	}

	// Hack: distinguish 'create' from 'edit' by comparing post_date and post_modified.
	if ( $event->post_date === $event->post_modified ) {
		$type = 'bpeo_create_event';
	} else {
		$type = 'bpeo_edit_event';
	}

	// Existing activity items for this event.
	$activities = bpeo_get_activity_by_event_id( $event_id );

	// There should never be more than one top-level create item.
	if ( 'bpeo_create_event' === $type ) {
		$create_items = array();
		foreach ( $activities as $activity ) {
			if ( 'bpeo_create_event' === $activity->type && 'events' === $activity->component ) {
				return;
			}
		}
	}

	// Prevent edit floods.
	if ( 'bpeo_edit_event' === $type ) {

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

	$recorded_time = 'bpeo_create_event' === $type ? $event->post_date : $event->post_modified;

	$activity_args = array(
		'component' => 'events',
		'type' => $type,
		'user_id' => $event->post_author, // @todo Event edited by non-author?
		'primary_link' => get_permalink( $event ),
		'secondary_item_id' => $event_id, // Leave 'item_id' blank for groups.
		'recorded_time' => $recorded_time,
	);

	bp_activity_add( $activity_args );

	do_action( 'bpeo_create_event_activity', $activity_args, $event );
}
add_action( 'save_post', 'bpeo_create_activity_for_event', 20, 3 );

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

	$original_action = $action;

	$action = sprintf(
		$base,
		sprintf( '<a href="%s">%s</a>', esc_url( bp_core_get_user_domain( $activity->user_id ) ), esc_html( bp_core_get_user_displayname( $activity->user_id ) ) ),
		sprintf( '<a href="%s">%s</a>', esc_url( get_permalink( $event ) ), esc_html( $event->post_title ) )
	);

	/**
	 * Filters the activity action for an event.
	 *
	 * The groups component uses this hook to add group-specific information to the action.
	 *
	 * @param string $action          Action string.
	 * @param object $activity        Activity object.
	 * @param string $original_action Action string as originally passed to the object.
	 */
	return apply_filters( 'bpeo_activity_action', $action, $activity, $original_action );
}

/**
 * Remove event-related duplicates from activity streams.
 *
 */
function bpeo_remove_duplicates_from_activity_stream( $activity, $r, $do_backfill = true ) {
	// Get a list of queried activity IDs before we start removing.
	$queried_activity_ids = wp_list_pluck( $activity['activities'], 'id' );

	// Make a list of all 'bpeo_' results, sorted by type and event ID.
	$eas = array();
	foreach ( $activity['activities'] as $a_index => $a ) {
		if ( 0 === strpos( $a->type, 'bpeo_' ) ) {
			if ( ! isset( $eas[ $a->type ] ) ) {
				$eas[ $a->type ] = array();
			}

			if ( ! isset( $eas[ $a->type ][ $a->secondary_item_id ] ) ) {
				$eas[ $a->type ][ $a->secondary_item_id ] = array();
			}

			$eas[ $a->type ][ $a->secondary_item_id ][] = $a_index;
		}
	}

	// Find cases of duplicates.
	$removed = 0;
	foreach ( $eas as $type => $events ) {
		foreach ( $events as $event_id => $a_indexes ) {
			// No dupes for this event.
			if ( count( $a_indexes ) <= 1 ) {
				continue;
			}

			/*
			 * Identify the "primary" activity:
			 * - Prefer the "canonical" activity if available (component=events)
			 * - Otherwise just pick the first one
			 */
			$primary_a_index = reset( $a_indexes );
			foreach ( $a_indexes as $a_index ) {
				if ( 'events' === $activity['activities'][ $a_index ]->component ) {
					$primary_a_index = $a_index;
					break;
				}
			}

			// Remove all items but the primary.
			foreach ( $a_indexes as $a_index ) {
				if ( $a_index !== $primary_a_index ) {
					unset( $activity['activities'][ $a_index ] );
					$removed++;
				}
			}
		}
	}

	if ( $removed && $do_backfill ) {
		// Backfill to correct per_page.
		$deduped_activity_count  = count( $activity['activities'] );
		$original_activity_count = count( $queried_activity_ids );
		while ( $deduped_activity_count < $original_activity_count ) {
			$backfill_args = $r;

			// Offset for the originally queried activities.
			$exclude = (array) $r['exclude'];
			$backfill_args['exclude'] = array_merge( $exclude, $queried_activity_ids );

			// In case of more reduction due to further duplication, fetch a generous number.
			$backfill_args['per_page'] += $removed;

			$backfill = bp_activity_get( $backfill_args );

			/*
			 * If the number of backfill items returned is less than the number requested, it means there
			 * are no more activity items to query after this. Set a flag so that we override the count
			 * logic and break out of the loop.
			 */
			$break_early = false;
			if ( count( $backfill['activities'] ) < $backfill_args['per_page'] ) {
				$break_early = true;
			}

			$activity['activities'] = array_merge( $activity['activities'], $backfill['activities'] );
			$activity['total'] += $backfill['total'];

			// Backfill may duplicate existing items, so we run the whole works through this function again.
			$activity = bpeo_remove_duplicates_from_activity_stream( $activity, $r, ! $break_early );

			// If we're left with more activity than we need, trim it down.
			if ( count( $activity['activities'] > $original_activity_count ) ) {
				$activity['activities'] = array_slice( $activity['activities'], 0, $original_activity_count );
			}

			// Break early if we're out of activity to backfill.
			if ( $break_early ) {
				break;
			}

			$deduped_activity_count = count( $activity['activities'] );
		}
	}

	return $activity;
}

/**
 * Hook the duplicate-removing logic.
 */
function bpeo_hook_duplicate_removing_for_activity_template( $args ) {
	add_filter( 'bp_activity_get', 'bpeo_remove_duplicates_from_activity_stream', 10, 2 );
	return $args;
}
add_filter( 'bp_before_has_activities_parse_args', 'bpeo_hook_duplicate_removing_for_activity_template' );

/**
 * Unhook the duplicate-removing logic.
 */
function bpeo_unhook_duplicate_removing_for_activity_template( $retval ) {
	remove_filter( 'bp_activity_get', 'bpeo_remove_duplicates_from_activity_stream', 10, 2 );
	return $retval;
}
add_filter( 'bp_has_activities', 'bpeo_unhook_duplicate_removing_for_activity_template' );
