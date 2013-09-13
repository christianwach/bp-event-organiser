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
		
			// get flat list
			$groups_list = BP_Groups_Group::get( 'alphabetical' );
			
		}
		
		// kick out if we don't have any
		if ( $groups_list['total'] === 0 ) {
			
			// show message
			echo '<p class="bp_event_organiser_desc">' . __( 'No groups were found.', 'bp-group-organizer' ) . '</p>';
			
			// kick
			return;
			
		}
		
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
	
		//print_r( $groups_list ); die();

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
		
		// trace
		//print_r( $_POST['bp_group_organizer_groups'] ); die();
		
		// convert to string to be safe
		$string = implode( ',', $value );
		
		// update event meta
		update_post_meta( $event_id,  '_bpeo_event_groups', $value );
		
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



