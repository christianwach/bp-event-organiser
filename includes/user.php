<?php

/**
 * Get IDs for events that should appear on a user's "My Calendar".
 *
 * By default, grabs a user's events as well as their friends and groups.
 *
 * @param int   $user_id ID of the user.
 * @param array $args {
 *     Array of arguments.
 *     @type bool $friends          Should we grab events created by friends? Default: true.
 *     @type bool $show_unpublished Should we grab unpublished events?  Default: false.
 * }
 */
function bpeo_get_my_calendar_event_ids( $user_id, $args = array() ) {
	$r = wp_parse_args( $args, array(
		'friends' => true,
		'show_unpublished' => false
	) );

	// Common event args
	$event_args = array(
		'post_type' => 'event',
		'fields' => 'ids',
		'showpastevents' => true,
		'nopaging' => true,
		'orderby' => 'none'
	);

	// Post status - Only applicable for user and groups, not friends
	if ( true === $r['show_unpublished'] ) {
		$post_status = array( 'pending', 'draft', 'future', 'trash' );
	} else {
		$post_status = array( 'private', 'publish' );
	}

	$event_ids = array();

	// Events created by me
	$eids_by_me = get_posts( array_merge(
		$event_args,
		array(
			'author__in'  => array( $user_id ),
			'post_status' => $post_status
		)
	) );
	$event_ids = array_merge( $event_ids, $eids_by_me );

	// Events created by friends
	if ( bp_is_active( 'friends' ) && true === (bool) $r['friends'] ) {
		$friends = friends_get_friend_user_ids( $user_id );
		if ( ! empty( $friends ) ) {
			$eids_by_friends = get_posts( array_merge(
				$event_args,
				array(
					'author__in'  => $friends,

					// can only see public events for friends
					'post_status' => 'publish'
				)
			) );

			$event_ids = array_merge( $event_ids, $eids_by_friends );
		}
	}

	// Events connected to my groups.
	if ( bp_is_active( 'groups' ) ) {
		$user_groups = groups_get_user_groups( $user_id );
		$group_ids = $user_groups['groups'];
		if ( ! empty( $group_ids ) ) {
			$eids_by_group = get_posts( array_merge(
				$event_args,
				array(
					'bp_group'    => $group_ids,
					'post_status' => $post_status,
				)
			) );

			$event_ids = array_merge( $event_ids, $eids_by_group );
		}


	}

	return array_unique( $event_ids );
}

/**
 * Add EO capabilities for subscribers and contributors.
 *
 * By default, subscribers and contributors do not have caps to post, edit or
 * delete events. This function injects these caps for users with these roles.
 *
 * @param  array  $caps    The mapped caps
 * @param  string $cap     The cap being mapped
 * @param  int    $user_id The user id in question
 * @return array
 */
function bpeo_map_basic_meta_caps( $caps, $cap, $user_id ) {
	switch ( $cap ) {
		case 'publish_events' :
		case 'edit_events' :
		case 'delete_events' :
			break;

		default :
			return $caps;
			break;
	}

	// make sure user is valid
	$user = new WP_User( $user_id );
	if ( ! is_a( $user, 'WP_User' ) || empty( $user->ID ) ) {
		return $caps;
	}

	/**
	 * Filters BPEO basic meta caps.
	 *
	 * @param array   Pass 'exist' cap so users are able to manage events.
	 * @param array   $caps The mapped caps
	 * @param string  $cap The cap being mapped
	 * @param WP_User The user being tested for the cap.
	 */
	return apply_filters( 'bpeo_map_basic_meta_caps', array( 'exist' ), $caps, $cap, $user );
}
add_filter( 'map_meta_cap', 'bpeo_map_basic_meta_caps', 10, 3 );

/**
 * Give users the 'upload_files' cap, when appropriate.
 *
 * @param  array  $caps    The mapped caps
 * @param  string $cap     The cap being mapped
 * @param  int    $user_id The user id in question
 * @return array
 */
function bpeo_map_upload_files_meta_cap( $caps, $cap, $user_id ) {
	// bail if not checking for 'upload_files' cap
	if ( 'upload_files' !== $cap ) {
		return $caps;
	}

	// make sure user is valid
	$maybe_user = new WP_User( $user_id );
	if ( ! is_a( $maybe_user, 'WP_User' ) || empty( $maybe_user->ID ) ) {
		return $caps;
	}

	// allow 'upload_files' cap on BPEO new and edit pages
	if ( bpeo_is_action( 'new' ) || bpeo_is_action( 'edit' ) ) {
		return array( 'exist' );
	}

	return $caps;
}
add_filter( 'map_meta_cap', 'bpeo_map_upload_files_meta_cap', 10, 3 );

/**
 * Modify `WP_Query` requests for the 'bp_displayed_user_id' param.
 *
 * @param WP_Query Query object, passed by reference.
 */
function bpeo_filter_query_for_bp_displayed_user_id( $query ) {
	// Only modify 'event' queries.
	$post_types = $query->get( 'post_type' );
	if ( ! in_array( 'event', (array) $post_types ) ) {
		return;
	}

	$user_id = $query->get( 'bp_displayed_user_id', null );
	if ( null === $user_id ) {
		return;
	}

	// Empty user_id will always return no results.
	if ( empty( $user_id ) ) {
		$query->set( 'post__in', array( 0 ) );
		return;
	}

	// Get a list of IDs to pass to post__in.
	$event_ids = bpeo_get_my_calendar_event_ids( $user_id );

	if ( empty( $event_ids ) ) {
		$event_ids = array( 0 );
	}
	$query->set( 'post__in', $event_ids );
}
add_action( 'pre_get_posts', 'bpeo_filter_query_for_bp_displayed_user_id', 1000 );

/**
 * Filter event links on a group events page to use the group event permalink.
 *
 * @param string $retval Current event permalink
 * @return string
 */
function bpeo_calendar_filter_event_link_for_bp_user( $retval ) {
	if ( ! bp_is_user() ) {
		return $retval;
	}

	// this is to avoid requerying the event just for the post slug
	$event_url = explode( '/', untrailingslashit( $retval ) );
	$post_slug = array_pop( $event_url );

	// regenerate the post URL to account for group permalink
	return trailingslashit( bp_displayed_user_domain() . bpeo_get_events_slug() . '/' . $post_slug );
}
add_filter( 'eventorganiser_calendar_event_link', 'bpeo_calendar_filter_event_link_for_bp_user' );

/**
 * Unhook BP's rel=canonical and replace with our custom version.
 */
function bpeo_rel_canonical_for_member() {
	if ( ! bp_is_user() ) {
		return;
	}

	if ( ! bp_is_current_component( bpeo_get_events_slug() ) ) {
		return;
	}

	if ( ! $event_slug = bp_current_action() ) {
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
add_action( 'wp_head', 'bpeo_rel_canonical_for_member', 9 );

/**
 * Modify the calendar query to include the displayed user ID.
 *
 * @param  array $query Query vars as set up by EO.
 * @return array
 */
function bpeo_filter_calendar_query_for_bp_user( $query ) {
	if ( ! bp_is_user() ) {
		return $query;
	}

	$query['bp_displayed_user_id'] = bp_displayed_user_id();

	return $query;
}
add_filter( 'eventorganiser_fullcalendar_query', 'bpeo_filter_calendar_query_for_bp_user' );

/**
 * Add author information to calendar event markup.
 *
 * @param array $event         Array of data about the event.
 * @param int   $event_id      ID of the event.
 * @param int   $occurrence_id ID of the occurrence.
 * @return array
 */
function bpeo_add_author_info_to_calendar_event( $event, $event_id, $occurrence_id ) {
	// Only show author info when on a user's My Events page.
	if ( ! bp_is_user() ) {
		return $event;
	}

	$event_obj = get_post( $event_id );
	$event['className'][] = 'eo-event-author-' . intval( $event_obj->post_author );

	$event['author'] = array(
		'id' => $event_obj->post_author,
		'url' => bp_core_get_user_domain( $event_obj->post_author ),
		'name' => bp_core_get_user_displayname( $event_obj->post_author ),
		'color' => bpeo_get_item_calendar_color( $event_obj->post_author, 'author' ),
	);

	return $event;
}
add_filter( 'eventorganiser_fullcalendar_event', 'bpeo_add_author_info_to_calendar_event', 10, 3 );
