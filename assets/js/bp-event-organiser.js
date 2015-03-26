// init var
var bp_event_organiser_group_id = 0,
    bp_event_organiser_displayed_user_id = 0;

// test for our localisation object
if ( 'undefined' !== typeof BpEventOrganiserSettings ) {

	// get our var
	bp_event_organiser_group_id = BpEventOrganiserSettings.group_id;
	bp_event_organiser_displayed_user_id = BpEventOrganiserSettings.displayed_user_id;
	
}



/** 
 * @description: define what happens when the page is ready
 */
jQuery(document).ready( function($) {

	// test if we have wp.hooks
	if ( 'undefined' !== typeof wp && 'undefined' !== typeof wp.hooks ) {

		// add filter for AJAX request so that the group ID is passed
		wp.hooks.addFilter(
	
			'eventorganiser.fullcalendar_request', 
			function( request, a, b, c, d ) {
				// add our variable to the request
				request['bp_displayed_user_id'] = bp_event_organiser_displayed_user_id;
				request['bp_group_id'] = bp_event_organiser_group_id;
			
				/*
				// trace
				console.log( 'HERE' );
				console.log( request );
				console.log( a );
				console.log( b );
				console.log( c );
				console.log( d );
				*/
			
				// --<
				return request;
		
			}
		
		);
	
	}
	
});
