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
 * Normalize EO action conditional checks across BP components.
 *
 * The BP groups component shifts the current path over by 1, which can make
 * conditional checks a little uneven.  This function normalizes these
 * conditional checks.
 *
 * @param string $action The action to check. eg. 'new', 'edit', or 'delete'.
 *
 * @return bool
 */
function bpeo_is_action( $action = '' ) {
	$retval = false;

	if ( bp_is_user() ) {
		$is_component = 'bp_is_current_component';
		$is_new = 'bp_is_current_action';
		$pos = 0;
	} elseif( bp_is_group() ) {
		$is_component = 'bp_is_current_action';
		$is_new = 'bp_is_action_variable';
		$pos = 1;
	} else {
		return $retval;
	}

	// not on an events page, so stop!
	if ( false === $is_component( bpeo_get_events_slug() ) ) {
		return $retval;
	}

	// alias of 'new'
	$action = 'new' === $action ? bpeo_get_events_new_slug() : $action;

	// check if we're on a 'new event' page
	if ( bpeo_get_events_new_slug() === $action ) {
		return $is_new( $action );
	}

	// check if we're on a 'manage events' page
	if ( 'manage' === $action ) {
		return $is_new( $action );
	}

	// all other actions - 'edit', 'delete'
	if ( false === bp_is_action_variable( $action, $pos ) ) {
		return $retval;
	}

	return true;
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

/**
 * Output the iCal link for an event.
 *
 * @param int $post_id The post ID.
 */
function bpeo_the_ical_link( $post_id ) {
	echo bpeo_get_the_ical_link( $post_id );
}
	/**
	 * Returns the iCal link for an event.
	 *
	 * Only works for the 'event' post type.
	 *
	 * @param  int $post_id The post ID.
	 * @return string
	 */
	function bpeo_get_the_ical_link( $post_id ) {
		if ( 'event' !== get_post( $post_id )->post_type ) {
			return '';
		}

		return trailingslashit( get_permalink( $post_id ) . 'feed/eo-events' );
	}

/**
 * Output the single event action links.
 *
 * @param WP_Post|int $post The WP Post object or the post ID.
 */
function bpeo_the_single_event_action_links( $post = 0 ) {
	echo bpeo_get_the_single_event_action_links( $post );
}
	/**
	 * Return the single event action links.
	 *
	 * @param  WP_Post|int $post The WP Post object or the post ID.
	 * @return string
	 */
	function bpeo_get_the_single_event_action_links( $post = 0 ) {
		if ( false === $post instanceof WP_Post ) {
			$post = get_post( $post );
		}

		if ( bp_is_user() ) {
			$back = $root = trailingslashit( bp_displayed_user_domain() . bpeo_get_events_slug() );
		} elseif ( bp_is_group() ) {
			$back = $root = bpeo_get_group_permalink();

		// WP single event page
		} else {
			// see if we have an events page
			$back = get_page_by_path( bpeo_get_events_slug() );
			if ( ! empty( $back ) ) {
				$back = trailingslashit( home_url( bpeo_get_events_slug() ) );

			// no events page, so use EO's main events archive page
			} else {
				$back = trailingslashit( home_url( trim( eventorganiser_get_option( 'url_events', 'events/event' ) ) ) );
			}

			$root = trailingslashit( bp_loggedin_user_domain() . bpeo_get_events_slug() );
		}

		$links = array();

		$links['back'] = '<a href="' . esc_url( $back ) . '">' . __( '&larr; Back', 'bp-events-organizer' ). '</a>';

		// @todo make 'edit' slug changeable
		if ( current_user_can( 'edit_event', $post->ID ) ) {
			$links['edit'] = '<a href="' . esc_url( $root ) . $post->post_name . '/edit/">' . __( 'Edit', 'bp-events-organizer' ). '</a>';
		}

		// @todo make 'delete' slug changeable
		if ( current_user_can( 'delete_event', $post->ID ) ) {
			$links['delete'] = '<a class="confirm" href="' . esc_url( $root ) . $post->post_name . '/delete/' . wp_create_nonce( "bpeo_delete_event_{$post->ID}" ). '/">' . __( 'Delete', 'bp-events-organizer' ). '</a>';
		}

		return implode( ' | ', (array) apply_filters( 'bpeo_get_the_single_event_action_links', $links ) );
	}

/** HOOKS ***************************************************************/

/**
 * Replace EO's default content with our own one when on a canonical event page.
 *
 * If you want to use EO's original default content, use this snippet:
 *     add_filter( 'bpeo_enable_replace_canonical_event_content', '__return_false' );
 *
 * @param  string $retval Existing canonical event content.
 * @return string
 */
function bpeo_remove_default_canonical_event_content( $retval ) {
	// bail if we shouldn't replace the existing content
	if ( false === (bool) apply_filters( 'bpeo_enable_replace_canonical_event_content', true ) ) {
		return $retval;
	}

	if( is_singular('event') && false === eventorganiser_is_event_template( '', 'event' ) ) {
		remove_filter( 'the_content', '_eventorganiser_single_event_content' );
		add_filter( 'the_content', 'bpeo_canonical_event_content', 999 );
	}

	return $retval;
}
add_filter( 'template_include', 'bpeo_remove_default_canonical_event_content', 20 );

/**
 * Callback filter to use BPEO's content for the canonical event page.
 *
 * @see bpeo_remove_default_canonical_event_content()
 *
 * @param  string $content Current content.
 * @return string
 */
function bpeo_canonical_event_content( $content ) {
	global $pages;

	// reset get_the_content() to use already-rendered content so we can use it in
	// our content-eo-event.php template part
	//
	// get_the_content() is weird and checks the $pages global for the content
	// so let's use the rendered content here and set it in the $pages global
	$pages[0] = $content;

	// remove all filters for 'the_content' to prevent recursion when using
	// 'the_content' again
	bp_remove_all_filters( 'the_content' );

	// buffer the template part
	ob_start();
	eo_get_template_part( 'content-eo', 'event' );
	$tpart = ob_get_contents();
	ob_end_clean();

	// restore filters for 'the_content'
	bp_restore_all_filters( 'the_content' );

	remove_filter( 'eventorganiser_template_stack', 'bpeo_register_template_stack' );

	return $tpart;
}

/**
 * Registers BPEO's template directory with EO's template stack.
 *
 * To register the stack, use:
 *     add_filter( 'eventorganiser_template_stack', 'bpeo_register_template_stack' );
 *
 * @param  array $retval Current template stack.
 * @return array
 */
function bpeo_register_template_stack( $retval ) {
	// inject our stack between the current theme and EO's template directory
	array_splice( $retval, 2, 0, constant( 'BPEO_PATH' ) . 'templates/' );
	return $retval;
}

/**
 * Use our template stack only when calling the content-eo-event.php template.
 *
 * The content-eo-event.php template is a custom template bundled with BPEO.  We
 * want EO to use our template directory ahead of their own.
 *
 * @param string $slug The template part slug
 * @param string $name The template part name.
 */
function bpeo_add_template_stack_to_content_event_template( $slug, $name ) {
	// not matching our template name? stop now!
	if ( 'event' !== $name ) {
		return;
	}

	// use our template stack
	add_filter( 'eventorganiser_template_stack', 'bpeo_register_template_stack' );

	// this is for cleaning up the post global when using the
	// event-meta-single-event.php template with recurring events
	add_action( 'loop_end', 'bpeo_catch_reset_postdata' );
}
add_action( 'get_template_part_content-eo', 'bpeo_add_template_stack_to_content_event_template', 10, 2 );

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

/**
 * Add iCal link to single event pages.
 */
function bpeo_add_ical_link_to_eventmeta() {
?>
	<li><a class="bpeo-ical-link" href="<?php bpeo_the_ical_link( get_the_ID() ); ?>"><span class="icon"></span><?php esc_html_e( 'Download iCal file', 'bp-events-organizer' ); ?></a></li>

<?php
}
add_action( 'eventorganiser_additional_event_meta', 'bpeo_add_ical_link_to_eventmeta', 50 );

/**
 * Whitelist BPEO shortcode attributes.
 *
 * @param array $out Output array of shortcode attributes.
 * @param array $pairs Default attributes as defined by EO.
 * @param array $atts Attributes passed to the shortcode.
 * @return array
 */
function bpeo_filter_eo_fullcalendar_shortcode_attributes( $out, $pairs, $atts ) {
	$whitelisted_atts = array(
		'bp_group',
		'bp_displayed_user_id',
	);

	foreach ( $atts as $att_name => $att_value ) {
		if ( isset( $out[ $att_name ] ) ) {
			continue;
		}

		if ( ! in_array( $att_name, $whitelisted_atts ) ) {
			continue;
		}

		$out[ $att_name ] = $att_value;
	}

	return $out;
}
add_filter( 'shortcode_atts_eo_fullcalendar', 'bpeo_filter_eo_fullcalendar_shortcode_attributes', 10, 3 );

/**
 * Disable EO's transient cache for calendar queries.
 */
add_filter( 'pre_transient_eo_full_calendar_public', '__return_empty_array' );
add_filter( 'pre_transient_eo_full_calendar_public_priv', '__return_empty_array' );

/**
 * Get an item's calendar color.
 *
 * Will select one randomly from a whitelist if not found.
 *
 * @param int    $item_id   ID of the item.
 * @param string $item_type Type of the item. 'author' or 'group'.
 * @return string Hex code for the item color.
 */
function bpeo_get_item_calendar_color( $item_id, $item_type ) {
	$color = '';
	switch ( $item_type ) {
		case 'group' :
			$color = groups_get_groupmeta( $item_id, 'bpeo_calendar_color' );
			break;

		case 'author' :
		default :
			$color = bp_get_user_meta( $item_id, 'bpeo_calendar_color', true );
			break;
	}

	if ( ! $color ) {
		// http://stackoverflow.com/a/4382138
		$colors = array(
			'FFB300', // Vivid Yellow
			'803E75', // Strong Purple
			'FF6800', // Vivid Orange
			'A6BDD7', // Very Light Blue
			'C10020', // Vivid Red
			'CEA262', // Grayish Yellow
			'817066', // Medium Gray

			// The following don't work well for people with defective color vision
			'007D34', // Vivid Green
			'F6768E', // Strong Purplish Pink
			'00538A', // Strong Blue
			'FF7A5C', // Strong Yellowish Pink
			'53377A', // Strong Violet
			'FF8E00', // Vivid Orange Yellow
			'B32851', // Strong Purplish Red
			'F4C800', // Vivid Greenish Yellow
			'7F180D', // Strong Reddish Brown
			'93AA00', // Vivid Yellowish Green
			'593315', // Deep Yellowish Brown
			'F13A13', // Vivid Reddish Orange
			'232C16', // Dark Olive Green
		);

		$index = array_rand( $colors );
		$color = $colors[ $index ];

		switch ( $item_type ) {
			case 'group' :
				groups_update_groupmeta( $item_id, 'bpeo_calendar_color', $color );
				break;

			case 'author' :
			default :
				bp_update_user_meta( $item_id, 'bpeo_calendar_color', $color );
				break;
		}
	}

	return $color;
}

/**
 * Ensure that wp_reset_postdata() doesn't reset the post back to page ID 0.
 *
 * The event meta template provided by EO uses {@link wp_reset_postdata()} when
 * an event is recurring.  This interferes with BuddyPress when using EO's
 * 'eventorganiser_additional_event_meta' hook and wanting to fetch EO's WP
 * post for further data output.
 *
 * This method catches the end of the reoccurence event loop and wipes out the
 * post so wp_reset_postdata() doesn't reset the post back to page ID 0.
 */
function bpeo_catch_reset_postdata( $q ) {
	// check if a reoccurence loop occurred; if not, bail
	if ( empty( $q->query['post_type'] ) ) {
		return;
	}

	// wipe out the post property in $wp_query to prevent our page from resetting
	// when wp_reset_postdata() is used
	$GLOBALS['wp_query']->post = null;
}
