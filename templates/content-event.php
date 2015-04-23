
	<h4><?php _e( 'Event Description', 'bp-event-organizer' ); ?></h4>

	<?php
	the_content();

	// post thumbnail - hardcoded to medium size at the moment.
	the_post_thumbnail( 'medium' );

	eo_get_template_part( 'event-meta', 'event-single' );

	bpeo_the_single_event_action_links();
	?>