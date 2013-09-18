// init var
var bp_event_organiser_group_id = 0;

// test for our localisation object
if ( 'undefined' !== typeof BpEventOrganiserSettings ) {

	// get our var
	bp_event_organiser_group_id = BpEventOrganiserSettings.group_id;
	
}



/** 
 * @description: define what happens when the page is ready
 *
 */
jQuery(document).ready( function($) {

	// add filter for AJAX request so that the group ID is passed
	wp.hooks.addFilter(
	
		'eventorganiser.fullcalendar_request', 
		function( request, a, b, c, d ) {
			
			// add our variable to the request
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
	
	//var blah = wp.hooks.applyFilters( 'eventorganiser.fullcalendar_request', 'r', 'a', 'b', 'c', 'd' ); 
	
	//alert('here:' + blah );

});
