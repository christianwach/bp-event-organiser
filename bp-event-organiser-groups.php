<?php /*
================================================================================
BP Group Organiser Group Extension
================================================================================
AUTHOR: Christian Wach <needle@haystack.co.uk>
--------------------------------------------------------------------------------
NOTES
=====

This class extends BP_Group_Extension to create the screens our plugin requires.
See: http://codex.buddypress.org/developer/plugin-development/group-extension-api/

--------------------------------------------------------------------------------
*/



// prevent problems during upgrade or when Groups are disabled
if ( !class_exists( 'BP_Group_Extension' ) ) { return; }



/*
================================================================================
Class Name
================================================================================
*/

class BP_Event_Organiser_Group_Extension extends BP_Group_Extension {



	/*
	============================================================================
	Properties
	============================================================================
	*/

	/*
	// 'public' will show our extension to non-group members
	// 'private' means only members of the group can view our extension
	public $visibility = 'public';

	// if our extension does not need a navigation item, set this to false
	public $enable_nav_item = true;

	// if our extension does not need an edit screen, set this to false
	public $enable_edit_item = true;

	// if our extension does not need an admin metabox, set this to false
	public $enable_admin_item = true;

	// the context of our admin metabox. See add_meta_box()
	public $admin_metabox_context = 'core';

	// the priority of our admin metabox. See add_meta_box()
	public $admin_metabox_priority = 'normal';
	*/

	// no need for a creation step
	public $enable_create_step = false;

	// if our extension does not need an edit screen, set this to false
	public $enable_edit_item = false;

	// if our extension does not need an admin metabox, set this to false
	public $enable_admin_item = false;



	/**
	 * @description: initialises this object
	 * @return nothing
	 */
	public function __construct() {

		// init vars with filters applied
		$name = apply_filters( 'bpeo_extension_title', __( 'Events', 'bp-event-organizer' ) );
		$slug = apply_filters( 'bpeo_extension_slug', bpeo_get_events_slug() );
		$pos = apply_filters( 'bpeo_extension_pos', 31 );

		// test for BP 1.8+
		// could also use 'bp_esc_sql_order' (the other core addition)
		if ( function_exists( 'bp_core_get_upload_dir' ) ) {

			// init array
			$args = array(
				'name' => $name,
				'slug' => $slug,
				'nav_item_position' => $pos,
				'enable_create_step' => false,
			);

			// init
			parent::init( $args );

	 	} else {

			// name our tab
			$this->name = $name;
			$this->slug = $slug;

			// set position in navigation
			$this->nav_item_position = $pos;

			// disable create step
			$this->enable_create_step = false;

		}

		$this->register_additional_nav_items();
		$this->register_buttons();
	}

	/**
	 * Registers additional subnav items.  Mainly the "New Event" subnav item.
	 */
	protected function register_additional_nav_items() {
		if ( ! bp_is_group() ) {
			return;
		}

		bp_core_new_subnav_item( array(
			'name'            => __( 'New Event', 'bp-event-organizer' ),
			'slug'            => bpeo_get_events_new_slug(),
			'parent_url'      => bp_get_group_permalink( groups_get_current_group() ),
			'parent_slug'     => bp_get_current_group_slug(),
			'screen_function' => array( $this, '_display_hook' ),
			'position'        => 9999,
			'item_css_id'     => 'nav-' . bpeo_get_events_new_slug(),

			// check if user has access
			// @todo currently all group members have access to edit events... restrict to mods?
			// also, this tab can only be seen on the 'new-event' page
			'user_has_access' => buddypress()->groups->current_group->is_user_member && bp_is_current_action( bpeo_get_events_new_slug() )
		) );
	}

	/**
	 * Registers buttons to be shown in the group header.
	 */
	protected function register_buttons() {
		add_action( 'bp_group_header_actions', array( $this, 'new_event_button' ) );
	}

	/**
	 * Override parent _display_hook() method to add logic for single events.
	 */
	public function _display_hook() {
		// single event
		if ( ! empty( buddypress()->action_variables ) ) {
			$this->single_event_screen();
			add_action( 'bp_template_content', array( $this, 'display_single_event' ) );

		// create event
		} elseif ( bp_is_current_action( bpeo_get_events_new_slug() ) ) {
			// check if user has access
			// @todo currently all group members have access to edit events... restrict to mods?
			if ( false === is_user_logged_in() || false === buddypress()->groups->current_group->is_user_member ) {
				bp_core_add_message( __( 'You do not have access to edit this event.', 'bp-event-organiser' ), 'error' );
				bp_core_redirect( bpeo_get_group_permalink() );
				die();
			}

			// magic admin screen code!
			require BPEO_PATH . '/includes/class.bpeo_frontend_admin_screen.php';

			$this->create_event = new BPEO_Frontend_Admin_Screen( array(
				'type'           => 'new',
				'redirect_root'  => bpeo_get_group_permalink()
			) );

			add_action( 'bp_template_content', array( $this->create_event, 'display' ) );

		// default behavior
		} else{
			add_action( 'bp_template_content', array( $this, 'call_display' ) );
		}

		bp_core_load_template( apply_filters( 'bp_core_template_plugin', $this->template_file ) );
	}

	/**
	 * @description display our content when the nav item is selected
	 */
	public function display( $group_id = null ) {
		// show header
		echo '<h3>'.apply_filters( 'bpeo_extension_title', __( 'Group Events', 'bp-event-organizer' ) ).'</h3>';

		// show secondary title if filter is in use
		$filter_title = bpeo_get_the_filter_title();
		if ( ! empty( $filter_title ) ) {
			echo "<h4>{$filter_title}</h4>";
		}

		// delete the calendar transient cache depending on user cap
		// @todo EO's calendar transient cache needs overhauling
		if( current_user_can( 'read_private_events' ) ){
			delete_transient( 'eo_full_calendar_public_priv' );
		} else {
			delete_transient( 'eo_full_calendar_public' );
		}

		$cat = ! empty( $_GET['cat'] ) ? esc_attr( $_GET['cat'] ) : '';
		$tag = ! empty( $_GET['tag'] ) ? esc_attr( $_GET['tag'] ) : '';

		$args = array(
			'headerright' => 'prev,next today,month,agendaWeek',
		);

		if ( ! empty( $cat ) ) {
			$args['event-category'] = $cat;
		}

		if ( ! empty( $tag ) ) {
			$args['event-tag'] = $tag;
		}

		// show events calendar, filtered by meta value in eo->intercept_calendar()
		echo eo_get_event_fullcalendar( $args );

	}

	/**
	 * Single event screen handler.
	 */
	protected function single_event_screen() {
		if ( false === bp_is_current_action( $this->slug ) ) {
			return;
		}

		if ( empty( buddypress()->action_variables ) ) {
			return;
		}

		// query for the event
		$event = eo_get_events( array(
			'name' => bp_action_variable()
		) );

		// check if event exists
		if ( empty( $event ) ) {
			bp_core_add_message( __( 'Event does not exist.', 'bp-event-organiser' ), 'error' );
			bp_core_redirect( bpeo_get_group_permalink() );
			die();
		}

		// check if event belongs to group
		// this needs to be edited once boone finishes new schema
		if ( false == in_array( bp_get_current_group_id(), $GLOBALS['buddypress_event_organiser']->eo->get_calendar_groups( $event[0]->ID ) ) ) {
			bp_core_add_message( __( 'Event does not belong to this group.', 'bp-event-organiser' ), 'error' );
			bp_core_redirect( bpeo_get_group_permalink() );
			die();
		}

		// save queried event as property
		$this->queried_event = $event[0];

		// edit single event logic
		if ( bp_is_action_variable( 'edit', 1 ) ) {
			// check if user has access
			if ( false === current_user_can( 'edit_event', $this->queried_event->ID ) ) {
				bp_core_add_message( __( 'You do not have access to edit this event.', 'bp-event-organiser' ), 'error' );
				bp_core_redirect( bpeo_get_group_permalink() . "{$this->queried_event->post_name}/" );
				die();
			}


			// magic admin screen code!
			require BPEO_PATH . '/includes/class.bpeo_frontend_admin_screen.php';

			$this->edit_event = new BPEO_Frontend_Admin_Screen( array(
				'queried_post'   => $this->queried_event,
				'redirect_root'  => bpeo_get_group_permalink()
			) );

		// delete single event logic
		} elseif ( bp_is_action_variable( 'delete', 1 ) ) {
			// check if user has access
			if ( false === current_user_can( 'delete_event', $this->queried_event->ID ) ) {
				bp_core_add_message( __( 'You do not have permission to delete this event.', 'bp-event-organiser' ), 'error' );
				bp_core_redirect( bpeo_get_group_permalink() . "{$this->queried_event->post_name}/" );
				die();
			}

			// verify nonce
			if ( false === bp_action_variable( 2 ) || ! wp_verify_nonce( bp_action_variable( 2 ), "bpeo_delete_event_{$this->queried_event->ID}" ) ) {
				bp_core_add_message( __( 'You do not have permission to delete this event.', 'bp-event-organiser' ), 'error' );
				bp_core_redirect( bpeo_get_group_permalink() . "{$this->queried_event->post_name}/" );
				die();
			}

			// delete event
			$delete = wp_delete_post( $this->queried_event->ID, true );
			if ( false === $delete ) {
				bp_core_add_message( __( 'There was a problem deleting the event.', 'bp-event-organiser' ), 'error' );
			} else {
				bp_core_add_message( __( 'Event deleted.', 'bp-event-organiser' ) );
			}

			bp_core_redirect( bpeo_get_group_permalink() );
			die();
		}
	}

	/**
	 * Display a single event within a group.
	 *
	 * @todo Move part of this functionality into a template part so theme devs can customize.
	 */
	public function display_single_event() {
		if ( empty( $this->queried_event ) ) {
			return;
		}

		// save $post global temporarily
		global $post;
		$_post = false;
		if ( ! empty( $post ) ) {
			$_post = $post;
		}

		// override the $post global so EO can use its functions
		$post = $this->queried_event;

		// edit screen has its own display method
		if ( bp_is_action_variable( 'edit', 1 ) ) {
			$this->edit_event->display();

			// revert $post global
			if ( ! empty( $_post ) ) {
				$post = $_post;
			}
			return;
		}

		/**
		 * Move this logic into a template part
		 */
		echo '<h2> ' . get_the_title() . '</h2>';

		echo '<h4>' . __( 'Event Description', 'bp-event-organizer' ) . '</h4>';

		// Make this better... have to juggle the_content filters...
		echo wpautop( $post->post_content );

		// post thumbnail - hardcoded to medium size at the moment.
		the_post_thumbnail( 'medium' );

		add_action( 'loop_end', array( $this, 'catch_reset_postdata' ) );
		eo_get_template_part( 'event-meta-event-single' );
		remove_action( 'loop_end', array( $this, 'catch_reset_postdata' ) );

		// Action links
		// @todo Make this a template function
		echo '<a href="' . bpeo_get_group_permalink() . '">' . __( '&larr; Back', 'bp-events-organizer' ). '</a>';

		// @todo make 'edit' slug changeable
		if ( current_user_can( 'edit_event', $this->queried_event->ID ) ) {
			echo ' | <a href="' . bpeo_get_group_permalink() . $this->queried_event->post_name . '/edit/">' . __( 'Edit', 'bp-events-organizer' ). '</a>';
		}

		// @todo make 'delete' slug changeable
		if ( current_user_can( 'delete_event', $this->queried_event->ID ) ) {
			echo ' | <a class="confirm" href="' . bpeo_get_group_permalink() . $this->queried_event->post_name . '/delete/' . wp_create_nonce( "bpeo_delete_event_{$this->queried_event->ID}" ). '/">' . __( 'Delete', 'bp-events-organizer' ). '</a>';
		}

		// revert $post global
		if ( ! empty( $_post ) ) {
			$post = $_post;
		}
	}

	/**
	 * Renders the 'New Event' button in the group header.
	 */
	public function new_event_button() {
		// do not show button if on 'new-event' page or if not on a group event page
		if ( false === $this->user_can_see_nav_item() || bp_is_current_action( bpeo_get_events_new_slug() ) || ! bp_is_current_action( bpeo_get_events_slug() ) ) {
			return;
		}

		// don't show if user is not a member of the group
		if ( false === buddypress()->groups->current_group->is_user_member ) {
			return;
		}

		bp_button( array(
			'component'         => 'groups',
			'id'                => 'new_event',
			'must_be_logged_in' => true,
			'link_text'         => __( 'New Event', 'bp-event-organizer' ),
			'link_href'         => trailingslashit( bp_get_group_permalink() . bpeo_get_events_new_slug() )
		) );
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
	public function catch_reset_postdata( $q ) {
		// check if a reoccurence loop occurred; if not, bail
		if ( empty( $q->query['post_type'] ) ) {
			return;
		}

		// wipe out the post property in $wp_query to prevent our page from resetting
		// when wp_reset_postdata() is used
		$GLOBALS['wp_query']->post = null;
	}
} // class ends



// register our class
bp_register_group_extension( 'BP_Event_Organiser_Group_Extension' );
