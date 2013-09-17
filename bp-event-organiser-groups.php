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
	function __construct() {
		
		// init vars with filters applied
		$name = apply_filters( 'bpeo_extension_title', __( 'Group Events', 'bp-event-organizer' ) );
		$slug = apply_filters( 'bpeo_extension_slug', 'events' );
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
		
		}
		
	}
	
	
	
	/**
	 * @description display our content when the nav item is selected
	 */
	function display() {
		
		// show header
		echo '<h3>'.apply_filters( 'bpeo_extension_title', __( 'Group Events', 'bp-event-organizer' ) ).'</h3>';
	
		// show events calendar, filtered by meta value
		echo eo_get_event_fullcalendar( array() );
	
	}
	
	
	
} // class ends



// register our class
bp_register_group_extension( 'BP_Event_Organiser_Group_Extension' );



