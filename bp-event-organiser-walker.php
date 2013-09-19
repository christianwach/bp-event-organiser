<?php /*
--------------------------------------------------------------------------------
Walker Classes
Kudos to the fantastic Group Organiser plugin for the code framework
--------------------------------------------------------------------------------
*/

/**
 * Amend Walker to include parent field
 */
class Walker_BPEO extends Walker {
	
	// update db fields
	var $db_fields = array( 
		'parent' => 'parent_id', 
		'id' => 'id' 
	);

}



/**
 * Create HTML list of items with checkboxes.
 */
class Walker_BPEO_Group extends Walker_BPEO  {
	
	
	
	/**
	 * @see Walker_Nav_Menu::start_lvl()
	 * @since 3.0.0
	 *
	 * @param string $output Passed by reference.
	 */
	function start_lvl( &$output ) {}
	
	
	
	/**
	 * @see Walker_Nav_Menu::end_lvl()
	 * @since 3.0.0
	 *
	 * @param string $output Passed by reference.
	 */
	function end_lvl( &$output ) {
	}
	
	
	
	/**
	 * @see Walker::start_el()
	 * @since 3.0.0
	 *
	 * @param string $output Passed by reference. Used to append additional content.
	 * @param object $item Menu item data object.
	 * @param int $depth Depth of menu item. Used for padding.
	 * @param object $args
	 */
	function start_el( &$output, $item, $depth, $args ) {
	
		// if the user is not an admin
		if ( !is_super_admin OR !current_user_can( 'manage_options' ) ) {
			
			// if not a public group
			if ( isset( $item->status ) AND 'public' != $item->status ) {

				// kick out if not member
				if ( !bp_group_is_member( $item ) ) return;
				
			}
		
		}
		
		// start buffer
		ob_start();
		
		// sanitise ID
		$item_id = esc_attr( $item->id );
		
		// define classes
		$classes = array(
			'menu-item menu-item-depth-' . $depth
		);
		
		// get title
		$title = $item->name;
		
		// update title based on ststus
		if ( isset( $item->status ) && 'private' == $item->status ) {
			$classes[] = 'status-private';
			/* translators: %s: title of private group */
			$title = sprintf( __( '%s (Private)', 'bp-event-organizer' ), $title );
		} elseif ( isset( $item->status ) && 'hidden' == $item->status ) {
			$classes[] = 'status-hidden';
			/* translators: %s: title of hidden group */
			$title = sprintf( __('%s (Hidden)', 'bp-event-organizer' ), $title );
		}
		
		// init checked
		$checked = '';
		
		// access array of group IDs for this event
		$groups_for_this_event = bp_event_organiser_get_group_ids();
		
		//print_r( $groups_for_this_event ); die();
		
		// is this item checked?
		if ( in_array( $item->id, $groups_for_this_event ) ) {
		
			// override checked
			$checked = ' checked="checked"';
		
		}
		
		// create markup
		?>
		<li id="menu-item-<?php echo $item_id; ?>" class="<?php echo implode(' ', $classes ); ?>">
			<span class="item-title"><input type="checkbox" value="<?php echo $item_id ?>" id="bp-group-organizer-group-<?php echo $item_id ?>" name="bp_group_organizer_groups[]"<?php echo $checked; ?> /> <label for="bp-group-organizer-group-<?php echo $item_id ?>"><?php echo esc_html( stripslashes( $title ) ); ?></label></span>
		<?php
		
		// collapse buffer into output
		$output .= ob_get_clean();
		
	}
	
	
	
} // class ends



/**
 * @description: a modified clone of the walk_group_tree function from Group Organiser
 */
function bp_event_organiser_walk_group_tree( $items, $depth, $r ) {
	$walker = ( empty($r->walker) ) ? new Walker_BPEO : $r->walker;
	$args = array( $items, $depth, $r );
	return call_user_func_array( array(&$walker, 'walk'), $args );
}





