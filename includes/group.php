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

	$set = wp_set_object_terms( $event_id, array( 'group_' . $group_id ), 'bpeo_event_group', true );

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

	$event_groups = bpeo_get_group_events( $group_id );
	if ( ! in_array( $event_id, $event_groups ) ) {
		return new WP_Error( 'event_not_found_for_group', __( 'No event found by that ID connected to this group.', 'bp-event-organiser' ) );
	}

	$removed = wp_remove_object_terms( $event_id, 'group_' . $group_id , 'bpeo_event_group' );

	return $removed;
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
	$r['post_status'] = 'any';

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

/**
 * Get group IDs associated with an event.
 *
 * @param int $event_id ID of the event.
 * @return array Array of group IDs.
 */
function bpeo_get_event_groups( $event_id ) {
	$group_terms = wp_get_object_terms( $event_id, 'bpeo_event_group' );
	$group_term_names = wp_list_pluck( $group_terms, 'name' );

	$group_ids = array();
	foreach ( $group_term_names as $group_term_name ) {
		// Trim leading 'group_'.
		$group_ids[] = intval( substr( $group_term_name, 6 ) );
	}

	return $group_ids;
}

/**
 * Modify `WP_Query` requests for the 'bp_group' param.
 *
 * @param WP_Query Query object, passed by reference.
 */
function bpeo_filter_query_for_bp_group( $query ) {
	// Only modify 'event' queries.
	$post_types = $query->get( 'post_type' );
	if ( ! in_array( 'event', (array) $post_types ) ) {
		return;
	}

	$bp_group = $query->get( 'bp_group', null );
	if ( null === $bp_group ) {
		return;
	}

	if ( ! is_array( $bp_group ) ) {
		$group_ids = array( $bp_group );
	} else {
		$group_ids = $bp_group;
	}

	// Empty array will always return no results.
	if ( empty( $group_ids ) ) {
		$query->set( 'post__in', array( 0 ) );
		return;
	}

	// Convert group IDs to a tax query.
	$tq = $query->get( 'tax_query' );
	$group_terms = array();
	foreach ( $group_ids as $group_id ) {
		$group_terms[] = 'group_' . $group_id;
	}

	$tq[] = array(
		'taxonomy' => 'bpeo_event_group',
		'terms' => $group_terms,
		'field' => 'name',
		'operator' => 'IN',
	);

	$query->set( 'tax_query', $tq );
}
add_action( 'pre_get_posts', 'bpeo_filter_query_for_bp_group' );

/**
 * Modify the calendar query to include the current group ID.
 *
 * @param  array $query Query vars as set up by EO.
 * @return array
 */
function bpeo_filter_calendar_query_for_bp_group( $query ) {
	if ( ! bp_is_group() ) {
		return $query;
	}

	$query['bp_group'] = bp_get_current_group_id();

	return $query;
}
add_filter( 'eventorganiser_fullcalendar_query', 'bpeo_filter_calendar_query_for_bp_group' );

/**
 * Filter event links on a group events page to use the group event permalink.
 *
 * @param string $retval Current event permalink
 * @return string
 */
function bpeo_calendar_filter_event_link_for_bp_group( $retval ) {
	if ( ! bp_is_group() ) {
		return $retval;
	}

	// this is to avoid requerying the event just for the post slug
	$event_url = explode( '/', untrailingslashit( $retval ) );
	$post_slug = array_pop( $event_url );

	// regenerate the post URL to account for group permalink
	return trailingslashit( bpeo_get_group_permalink() . $post_slug );
}
add_filter( 'eventorganiser_calendar_event_link', 'bpeo_calendar_filter_event_link_for_bp_group' );

/**
 * Add group information to calendar event markup.
 *
 * @param array $event         Array of data about the event.
 * @param int   $event_id      ID of the event.
 * @param int   $occurrence_id ID of the occurrence.
 * @return array
 */
function bpeo_add_group_info_to_calendar_event( $event, $event_id, $occurrence_id ) {
	foreach ( bpeo_get_event_groups( $event_id ) as $group_id ) {
		$event['className'][] = 'eo-event-bp-group-' . intval( $group_id );

		if ( ! isset( $event['groups'] ) ) {
			$event['groups'] = array();
		}

		if ( ! isset( $event['groups'][ $group_id ] ) ) {
			$group = groups_get_group( array( 'group_id' => $group_id ) );
			$event['groups'][ $group_id ] = array(
				'name' => $group->name,
				'url' => bp_get_group_permalink( $group ),
				'id' => $group_id,
				'color' => bpeo_get_item_calendar_color( $group_id, 'group' ),
			);
		}
	}

	return $event;
}
add_filter( 'eventorganiser_fullcalendar_event', 'bpeo_add_group_info_to_calendar_event', 10, 3 );

/**
 * Unhook BP's rel=canonical and replace with our custom version.
 */
function bpeo_rel_canonical_for_group() {
	if ( ! bp_is_group() ) {
		return;
	}

	if ( ! bp_is_current_action( bpeo_get_events_slug() ) ) {
		return;
	}

	if ( ! $event_slug = bp_action_variable( 0 ) ) {
		return;
	}

	if ( ! $e = get_page_by_path( $event_slug, OBJECT, 'event' ) ) {
		return;
	}

	// Don't let BP output its own canonical tag.
	remove_action( 'bp_head', 'bp_rel_canonical' );

	$canonical_url = get_permalink( $e );
	echo "<link rel='canonical' href='" . esc_url( $canonical_url ) . "' />\n";
}
add_action( 'wp_head', 'bpeo_rel_canonical_for_group', 9 );

/**
 * Modify EO capabilities for group membership.
 *
 * @param array  $caps    Capability array.
 * @param string $cap     Capability to check.
 * @param int    $user_id ID of the user being checked.
 * @param array  $args    Miscellaneous args.
 * @return array Caps whitelist.
 */
function bpeo_group_event_meta_cap( $caps, $cap, $user_id, $args ) {
	// @todo Need real caching in BP for group memberships.
	if ( ! in_array( $cap, array( 'read_event' ) ) ) {
		return $caps;
	}

	$event = get_post( $args[0] );
	if ( 'event' !== $event->post_type ) {
		return $caps;
	}

	$event_groups = bpeo_get_event_groups( $event->ID );
	$user_groups = groups_get_user_groups( $user_id );

	switch ( $cap ) {
		case 'read_event' :
			if ( 'private' !== $event->post_status ) {
				// EO uses 'read', which doesn't include non-logged-in users.
				$caps = array( 'exist' );
			} elseif ( array_intersect( $user_groups['groups'], $event_groups ) ) {
				$caps = array( 'read' );
			}

			break;
	}

	return $caps;
}
add_filter( 'map_meta_cap', 'bpeo_group_event_meta_cap', 20, 4 );

/**
 * Register activity actions and format callbacks for 'groups' component.
 */
function bpeo_register_activity_actions_for_groups() {
	bp_activity_set_action(
		buddypress()->groups->id,
		'bpeo_create_event',
		__( 'Events created', 'bp-event-organiser' ),
		'bpeo_activity_action_format',
		__( 'Events created', 'buddypress' ),
		array( 'activity', 'member', 'group', 'member_groups' )
	);

	bp_activity_set_action(
		buddypress()->groups->id,
		'bpeo_edit_event',
		__( 'Events edited', 'bp-event-organiser' ),
		'bpeo_activity_action_format',
		__( 'Events edited', 'buddypress' ),
		array( 'activity', 'member', 'group', 'member_groups' )
	);
}
add_action( 'bp_register_activity_actions', 'bpeo_register_activity_actions_for_groups' );

/**
 * Create activity items for connected groups.
 *
 * @param array   $activity_args Arguments used to create the 'events' activity item.
 * @param WP_Post $event         Event post object.
 */
function bpeo_create_group_activity_items( $activity_args, $event ) {
	$group_ids = bpeo_get_event_groups( $event->ID );

	foreach ( $group_ids as $group_id ) {
		$_activity_args = $activity_args;
		$_activity_args['component'] = buddypress()->groups->id;
		$_activity_args['item_id'] = $group_id;
		$_activity_args['hide_sitewide'] = true;
		bp_activity_add( $_activity_args );
	}
}
add_action( 'bpeo_create_event_activity', 'bpeo_create_group_activity_items', 10, 2 );

function bpeo_activity_action_format_for_groups( $action, $activity ) {
	$groups = bpeo_get_event_groups( $activity->secondary_item_id );

	if ( empty( $groups ) ) {
		return $action;
	}

	$_groups = groups_get_groups( array(
		'include' => $groups,
		'populate_extras' => false,
		'per_page' => false,
		'type' => 'alphabetical',
		'show_hidden' => true,
	) );
	$groups = $_groups['groups'];

	// Remove groups the current user doesn't have access to.
	foreach ( $groups as $group_index => $group ) {
		if ( 'public' === $group->status ) {
			continue;
		}

		if ( ! is_user_logged_in() || ! groups_is_user_member( bp_loggedin_user_id(), $group->id ) ) {
			unset( $groups[ $group_index ] );
			continue;
		}
	}

	$groups = array_values( array_filter( $groups ) );
	if ( empty( $groups ) ) {
		return $action;
	}

	$group_count = count( $groups );
	switch ( $activity->type ) {
		case 'bpeo_create_event' :
			/* translators: 1: link to user, 2: link to event, 3: comma-separated list of group links */
			$base = _n( '%1$s created the event %2$s in the group %3$s.', '%1$s created the event %2$s in the groups %3$s.', $group_count, 'bp-event-organiser' );
			break;
		case 'bpeo_edit_event' :
			/* translators: 1: link to user, 2: link to event, 3: comma-separated list of group links */
			$base = _n( '%1$s edited the event %2$s in the group %3$s.', '%1$s edited the event %2$s in the groups %3$s.', $group_count, 'bp-event-organiser' );
			break;
	}

	// If this is a user activity item, keeps groups in alphabetical order. Otherwise put primary group first.
	if ( buddypress()->groups->id === $activity->component ) {
		foreach ( $groups as $group_index => $group ) {
			if ( $activity->item_id == $group->id ) {
				$this_group = $group;
				unset( $groups[ $group_index ] );
				array_unshift( $groups, $this_group );
			}
		}
	}

	$groups = array_values( array_filter( $groups ) );

	$group_links = array();
	foreach ( $groups as $group ) {
		$group_links[] = sprintf(
			'<a href="%s">%s</a>',
			esc_url( trailingslashit( bp_get_group_permalink( $group ) . bpeo_get_events_slug() ) ),
			esc_html( $group->name )
		);
	}

	$event = get_post( $activity->secondary_item_id );

	$action = sprintf(
		$base,
		sprintf( '<a href="%s">%s</a>', esc_url( bp_core_get_user_domain( $activity->user_id ) ), esc_html( bp_core_get_user_displayname( $activity->user_id ) ) ),
		sprintf( '<a href="%s">%s</a>', esc_url( get_permalink( $event ) ), esc_html( $event->post_title ) ),
		implode( ', ', $group_links )
	);

	return $action;
}
add_filter( 'bpeo_activity_action', 'bpeo_activity_action_format_for_groups', 10, 2 );

/** TEMPLATE ************************************************************/

/**
 * Get the permalink to a group's events page.
 *
 * @param  BP_Groups_Group|int $group The group object or the group ID to fetch the group for.
 * @return string
 */
function bpeo_get_group_permalink( $group = 0 ) {
	if ( empty( $group ) ) {
		$group = groups_get_current_group();
	}

	if ( ! empty( $group ) && ! $group instanceof BP_Groups_Group && is_int( $group ) ) {
		$group = groups_get_group( array(
			'group_id'        => $group,
			'populate_extras' => false
		) );
	}

	return trailingslashit( bp_get_group_permalink( $group ) . bpeo_get_events_slug() );
}

/**
 * Display a list of connected groups on single event pages.
 */
function bpeo_list_connected_groups() {
	$event_group_ids = bpeo_get_event_groups( get_the_ID() );

	if ( empty( $event_group_ids ) ) {
		return;
	}

	$event_groups = groups_get_groups( array(
		'include' => $event_group_ids,
		'show_hidden' => true, // We roll our own.
	) );

	$markup = array();
	foreach ( $event_groups['groups'] as $eg ) {
		// Remove groups that the current user should not have access to.
		if ( 'public' !== $eg->status && ! current_user_can( 'bp_moderate' ) && ! groups_is_user_member( bp_current_user_id(), $eg->id ) ) {
			continue;
		}

		$markup[] = sprintf(
			'<a href="%s">%s</a>',
			esc_url( bpeo_get_group_permalink( $eg ) ),
			esc_html( stripslashes( $eg->name ) )
		);
	}

	if ( empty( $markup ) ) {
		return;
	}

	$count = count( $markup );
	$base = _n( '<strong>Connected group:</strong> %s', '<strong>Connected groups:</strong> %s', $count, 'bp-event-organiser' );

	echo sprintf( '<li>' . wp_filter_kses( $base ) . '</li>', implode( ', ', $markup ) );
}
add_action( 'eventorganiser_additional_event_meta', 'bpeo_list_connected_groups' );
