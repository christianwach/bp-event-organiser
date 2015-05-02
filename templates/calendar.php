<?php

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

if ( bp_is_user() ) {
	$args['bp_displayed_user_id'] = bp_displayed_user_id();
} elseif ( function_exists( 'bp_is_group' ) && bp_is_group() ) {
	$args['bp_group'] = bp_get_current_group_id();
}

echo eo_get_event_fullcalendar( $args );
