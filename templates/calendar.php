<?php
$args = array(
	'bp_displayed_user_id' => bp_displayed_user_id(),
);
echo eo_get_event_fullcalendar( $args );
