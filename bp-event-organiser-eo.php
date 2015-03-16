<?php /*
--------------------------------------------------------------------------------
BuddyPress_Event_Organiser_EO Class
--------------------------------------------------------------------------------
*/

class BuddyPress_Event_Organiser_EO {

	/**
	 * properties
	 */

	// parent object
	public $plugin;

	// group IDs
	public $group_ids;



	/**
	 * @description: initialises this object
	 * @return object
	 */
	function __construct() {

		// register hooks
		$this->register_hooks();

		// --<
		return $this;

	}



	/**
	 * @description: set references to other objects
	 * @return nothing
	 */
	public function set_references( $parent ) {

		// store
		$this->plugin = $parent;

	}



	/**
	 * @description: register hooks on BuddyPress loaded
	 * @return nothing
	 */
	public function register_hooks() {

		// check for Event Organiser
		if ( !$this->is_active() ) return;

		// add our event meta box
		add_action( 'add_meta_boxes', array( $this, 'event_meta_box' ) );

		// intercept save event
		add_action( 'eventorganiser_save_event', array( $this, 'intercept_save_event' ), 10, 1 );

		// intercept before break occurrence
		add_action( 'eventorganiser_pre_break_occurrence', array( $this, 'pre_break_occurrence' ), 10, 2 );

		// intercept after break occurrence, because a new post is created
		//add_action( 'eventorganiser_occurrence_broken', array( $this, 'occurrence_broken' ), 10, 3 );

		// intercept calendar display
		add_filter( 'eventorganiser_fullcalendar_event', array( $this, 'intercept_calendar' ), 10, 3 );

		// intercept post content - try to catch calendar shortcodes
		add_filter( 'the_content', array( $this, 'intercept_content' ), 10, 1 );

	}



	/**
	 * @description: utility to check if Event Organiser is present and active
	 * @return bool
	 */
	public function is_active() {
		// only check once
		static $eo_active = false;
		if ( $eo_active ) { return true; }

		// access Event Organiser option
		$installed_version = get_option( 'eventorganiser_version' );

		// this plugin will not work without EO
		if ( $installed_version === false ) {
			wp_die( '<p>Event Organiser plugin is required</p>' );
		}

		// we need version 2 at least
		if ( $installed_version < '2' ) {
			wp_die( '<p>Event Organiser version 2 or higher is required</p>' );
		}

		// set flag
		$eo_active = true;

		// --<
		return $eo_active;

	}



	/**
	 * @description: utility to check if BP Group Hierarchy is present and active
	 * @return bool
	 */
	public function is_group_hierarchy_active() {

		// only check once
		static $bpgh_active = false;
		if ( $bpgh_active ) { return true; }

		// do we have the BP Group Hierarchy plugin constant and tree method?
		if (
			defined( 'BP_GROUP_HIERARCHY_IS_INSTALLED' ) AND
			method_exists( 'BP_Groups_Hierarchy', 'get_tree' )
		) {

			// set flag
			$bpgh_active = true;

		}

		// --<
		return $bpgh_active;

	}



 	//##########################################################################



	/**
	 * @description: intercept save event
	 * @param int $post_id the numeric ID of the WP post
	 * @return nothing
	 */
	public function intercept_save_event( $post_id ) {

		// check that we trust the source of the data (EO does this for us)
		//check_admin_referer( 'bp_event_organiser_meta_save', 'bp_event_organiser_nonce_field' );

		// get post data
		$post = get_post( $post_id );

		// save BP groups for this EO event
		$this->update_event_groups( $post_id );

	}



	/**
	 * @description: intercept before break occurrence
	 * @param int $post_id the numeric ID of the WP post
	 * @param int $occurrence_id the numeric ID of the occurrence
	 * @return nothing
	 */
	public function pre_break_occurrence( $post_id, $occurrence_id ) {

		/*
		// eg ( [post_id] => 31 [occurrence_id] => 2 )
		print_r( array(
			'method' => 'bpeo->pre_break_occurrence',
			'post_id' => $post_id,
			'occurrence_id' => $occurrence_id,
		) ); die();
		*/

		// init or die
		if ( ! $this->is_active() ) return;

		// unhook eventorganiser_save_event, because EO copies across post-meta
		remove_action( 'eventorganiser_save_event', array( $this, 'intercept_save_event' ), 10 );

	}



	/**
	 * @description: intercept after break occurrence
	 * @param int $post_id the numeric ID of the WP post
	 * @param int $occurrence_id the numeric ID of the occurrence
	 * @param int $new_event_id the numeric ID of the new WP post
	 * @return nothing
	 */
	public function occurrence_broken( $post_id, $occurrence_id, $new_event_id ) {

		/*
		print_r( array(
			'method' => 'bpeo->occurrence_broken',
			'post_id' => $post_id,
			'occurrence_id' => $occurrence_id,
			'new_event_id' => $new_event_id,
		) ); die();
		*/

		// --<
		return;

		// get existing event groups
		$existing = $this->get_event_groups( $post_id );

		// transfer to new event
		$this->set_event_groups( $new_event_id, $existing );

	}



	/**
	 * @description: register event meta box
	 * @return nothing
	 */
	public function event_meta_box() {

		// create it
		add_meta_box(
			'bp_event_organiser_metabox',
			'BuddyPress Groups',
			array( $this, 'event_meta_box_render' ),
			'event',
			'side', //'normal',
			'core' //'high'
		);

	}



	/**
	 * @description: define venue meta box
	 * @return nothing
	 */
	public function event_meta_box_render( $event ) {

		// add nonce
		wp_nonce_field( 'bp_event_organiser_meta_save', 'bp_event_organiser_nonce_field' );

		//print_r( $event ); die();

		// is group hierarchy present?
		if( $this->is_group_hierarchy_active() ) {

			// get tree
			$groups_list = array(
				'groups' => BP_Groups_Hierarchy::get_tree()
			);

			// add total
			$groups_list['total'] = count( $groups_list['groups'] );

		} else {

			// init params
			$params = array(
				'type' => 'alphabetical',
				'per_page' => 1000, // avoid pagination cutoff
				//'show_hidden' => true, // removed this since events always show up
			);

			// if not super admin (or admin in single site install)
			if ( ! is_super_admin() ) {

				// restrict groups to those the user is a member of
				$params['user_id'] = bp_loggedin_user_id();

			}

			// get flat list
			$groups_list = BP_Groups_Group::get( $params );

		}

		// kick out if we don't have any
		if ( $groups_list['total'] === 0 ) {

			// show message
			echo '<p class="bp_event_organiser_desc">' . __( 'No groups were found.', 'bp-group-organizer' ) . '</p>';

			// kick
			return;

		}

		//print_r( $groups_list ); die();

		// get array of checked IDs for this event
		$this->group_ids = $this->get_event_groups( $event->ID );

		// define walker
		$walker = new Walker_BPEO_Group;

		// open html
		$result = '';

		// open scroller
		$result .= '<div class="bp_event_organiser_scroller" style="height: 200px; overflow-y: scroll; border: 1px solid #ccc; background: #fff; padding: 0 6px;">'."\n";

		// open list
		$result .= '<ul class="bp_event_organiser_groups_list">'."\n";

		// call walker
		$result .= bp_event_organiser_walk_group_tree(
			$groups_list['groups'],
			0,
			(object) array( 'walker' => $walker )
		);

		// close list
		$result .= '</ul>'."\n\n\n";

		// close scroller
		$result .= '</div>'."\n\n\n";

		// show meta box
		echo '

		<p class="bp_event_organiser_desc">Choose the groups this event should be assigned to.</p>

		'.$result;

	}



	//##########################################################################



	/**
	 * @description: update event groups array
	 * @param int $event_id the numeric ID of the event
	 * @return nothing
	 */
	public function update_event_groups( $event_id ) {

		// init as off
		$value = array();

		// kick out if not set
		if ( isset( $_POST['bp_group_organizer_groups'] ) ) {

			// retrieve meta value
			$value = is_array( $_POST['bp_group_organizer_groups'] ) ? $_POST['bp_group_organizer_groups'] : array();

		}

		// save
		$this->set_event_groups( $event_id, $value );

	}



	/**
	 * @description: update event groups array
	 * @param int $event_id the numeric ID of the event
	 * @return nothing
	 */
	public function set_event_groups( $event_id, $value = array() ) {

		// convert to string to be safe
		$string = implode( ',', $value );

		// trace
		//print_r( $string ); die();
		//print_r( $value ); die();

		// update event meta
		update_post_meta( $event_id,  '_bpeo_event_groups', $string );

	}



	/**
	 * @description: get all event groups
	 * @param int $post_id the numeric ID of the WP post
	 * @return bool $event_groups_array the event groups event
	 */
	public function get_event_groups( $post_id ) {

		// get the meta value
		$event_groups = get_post_meta( $post_id, '_bpeo_event_groups', true );

		// if it's not yet set it will be an empty string, so cast as array
		if ( $event_groups === '' ) return array();

		// convert to array
		$event_groups_array = explode( ',', $event_groups );

		// --<
		return $event_groups_array;

	}



	/**
	 * @description: delete event groups
	 * @param int $post_id the numeric ID of the WP post
	 * @return nothing
	 */
	public function clear_event_groups( $post_id ) {

		// delete the meta value
		delete_post_meta( $post_id, '_bpeo_event_groups' );

	}



	//##########################################################################



	/**
	 * @description: getter method for accessing group IDs for metabox list walker
	 * @return array $group_ids array of group IDs
	 */
	public function get_group_ids() {

		// do we have the property?
		if ( isset( $this->group_ids ) AND is_array( $this->group_ids ) ) {

			// yup, send it back
			return $this->group_ids;

		}

		// return an empty array by default
		return array();

	}



	//##########################################################################



	/**
	 * @description: intercept display of group calendar
	 * @param object $post the WP post object
	 * @param int $post_id the numeric ID of the WP post
	 * @param int $occurrence_id the numeric ID of the EO occurrence
	 * @return nothing
	 */
	public function intercept_calendar( $post, $post_id, $occurrence_id ) {

		/*
		bp_get_current_group_id() reports incorrect IDs for BP Group Hierarchy
		sub groups - it only reports the top level group's ID presumably because
		BuddyPress is parsing the URL to get the group ID. As a result, we need to
		parse $_GET, which has our injected value.
		*/

		// init
		$group_id = 0;

		// Get group ID from $_GET for AJAX requests.
		if ( isset( $_GET['bp_group_id'] ) && is_numeric( $_GET['bp_group_id'] ) ) {
			$group_id = absint( $_GET['bp_group_id'] );
		}

		// pass if not in group
		if ( 0 == $group_id ) return $post;

		// get groups for this post
		$groups = $this->get_calendar_groups( $post_id );

		// do we show this post?
		if ( in_array( $group_id, $groups ) ) {
			// this is to avoid requerying the event just for the post slug
			$event_url = explode( '/', untrailingslashit( $post['url'] ) );
			$post_slug = array_pop( $event_url );

			// regenerate the post URL to account for group permalink
			$post['url'] = trailingslashit( bpeo_get_group_permalink( $group_id ) . $post_slug );

			// --<
			return $post;
		}

		// --<
		return null;

	}



	/**
	 * @description: get all event groups
	 * @param int $event_id the numeric ID of the WP post
	 * @return bool $event_groups_array the event groups event
	 */
	public function get_calendar_groups( $event_id ) {
		return bpeo_get_event_groups( $event_id );
	}



	//##########################################################################



	/**
	 * @description: intercept content and clear calendar cache if shortcode is present
	 * @return string $content the post content
	 */
	public function intercept_content( $content ) {

		// do we have the shortcode?
		if ( has_shortcode( $content, 'eo_fullcalendar' ) ) {

			// yup, bust cache
			delete_transient( 'eo_full_calendar_public' );

		}

		// pass content on
		return $content;

	}



	//##########################################################################



	/**
	 * @description: debugging
	 * @param array $msg
	 * @return string
	 */
	private function _debug( $msg ) {

		// add to internal array
		$this->messages[] = $msg;

		// do we want output?
		if ( BUDDYPRESS_EVENT_ORGANISER_DEBUG ) print_r( $msg );

	}



} // class ends



/**
 * @description: get list of groups for an event
 * @return array $groups comma-delimited
 */
function bp_event_organiser_get_groups( $post_id ) {

	// access plugin global
	global $buddypress_event_organiser;

	// --<
	return $buddypress_event_organiser->eo->get_event_groups( $post_id );

}


/**
 * @description: get list of group IDs for an event's metabox
 */
function bp_event_organiser_get_group_ids() {

	// access plugin global
	global $buddypress_event_organiser;

	// --<
	return $buddypress_event_organiser->eo->get_group_ids();

}


