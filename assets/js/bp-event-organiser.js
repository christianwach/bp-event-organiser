// Ensure the global `wp` object exists.
window.wp = window.wp || {};

( function( $ ) {
	/**
	 * Bindings etc that happen on $(document).ready.
	 */
	init = function() {
		var group_id, user_id;

		// test for our localisation object
		if ( 'undefined' !== typeof BpEventOrganiserSettings ) {
			// get our var
			group_id = BpEventOrganiserSettings.group_id;
			user_id = BpEventOrganiserSettings.displayed_user_id;
		}

		// test if we have wp.hooks
		if ( 'undefined' !== typeof wp && 'undefined' !== typeof wp.hooks ) {

			// add filter for AJAX request so that the group ID is passed
			wp.hooks.addFilter(
				'eventorganiser.fullcalendar_request',
				function( request, a, b, c, d ) {
					// add our variable to the request
					if ( user_id > 0 ) {
						request['bp_displayed_user_id'] = user_id;
					}

					if ( group_id > 0 ) {
						request['bp_group_id'] = group_id;
					}

					// --<
					return request;
				}
			);
		}
	}

	$( document ).ready( function() {
		init();
	});
}( jQuery ));
