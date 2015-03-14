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
		$name = apply_filters( 'bpeo_extension_title', __( 'Group Events', 'bp-event-organizer' ) );
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

	}

	/**
	 * Override parent _display_hook() method to add logic for single events.
	 */
	public function _display_hook() {
		// single event
		if ( ! empty( buddypress()->action_variables ) ) {
			$this->single_event_screen();
			add_action( 'bp_template_content', array( $this, 'display_single_event' ) );

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

		// delete the calendar transient cache depending on user cap
		// @todo EO's calendar transient cache needs overhauling
		if( current_user_can( 'read_private_events' ) ){
			delete_transient( 'eo_full_calendar_public_priv' );
		} else {
			delete_transient( 'eo_full_calendar_public' );
		}

		// show events calendar, filtered by meta value in eo->intercept_calendar()
		echo eo_get_event_fullcalendar( array(
			'headerright' => 'prev,next today,month,agendaWeek',
		) );

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

		// save event
		$this->queried_event = $event[0];
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

		global $post;

		// save $post global temporarily
		$_post = false;
		if ( ! empty( $post ) ) {
			$_post = $post;
		}

		// override the $post global so EO can use its functions
		$post = $this->queried_event;

		/**
		 * Move this logic into a template part
		 */
		echo '<h2> ' . get_the_title() . '</h2>';

		echo '<h4>' . __( 'Event Description', 'bp-event-organizer' ) . '</h4>';

		// Make this better... have to juggle the_content filters...
		echo wpautop( $post->post_content );
		eo_get_template_part( 'event-meta-event-single' );

		// Action links
		// @todo Add 'Edit' link
		echo '<a href="' . bpeo_get_group_permalink() . '">' . __( '&larr; Back', 'bp-events-organizer' ). '</a>';

		// revert $post global
		if ( ! empty( $_post ) ) {
			$post = $_post;
		}
	}


} // class ends



// register our class
bp_register_group_extension( 'BP_Event_Organiser_Group_Extension' );



