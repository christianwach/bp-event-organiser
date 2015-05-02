<?php
$args = array(
	'bp_displayed_user_id' => bp_displayed_user_id(),
);

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

echo eo_get_event_fullcalendar( $args );
