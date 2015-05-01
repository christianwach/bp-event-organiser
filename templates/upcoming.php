<?php
/**
 * Upcoming event list template.
 */

$eo_get_events_args = array(
	'showpastevents' => false,
);

if ( bp_is_user() ) {
	$eo_get_events_args['bp_displayed_user_id'] = bp_displayed_user_id();
} elseif ( function_exists( 'bp_is_group' ) && bp_is_group() ) {
	$eo_get_events_args['bp_group'] = bp_get_current_group_id();
}

$events = eo_get_events( $eo_get_events_args ); ?>

<?php if ( ! empty( $events ) ) : ?>
	<ul class="bpeo-upcoming-events">
	<?php foreach ( $events as $event ) : ?>
		<li class="bpeo-upcoming-event-<?php echo esc_attr( $event->ID ) ?>">
			<div class="bpeo-upcoming-event-datetime">
				<span class="bpeo-upcoming-event-date"><?php echo date( 'M j, Y', strtotime( $event->StartDate ) ) ?></span> &middot; <span class="bpeo-upcoming-event-time"><?php echo date( 'g:ia', strtotime( $event->StartTime ) ) ?></span>
			</div>

			<a class="bpeo-upcoming-event-title" href="<?php echo esc_url( apply_filters( 'eventorganiser_calendar_event_link', get_permalink( $event->ID ), $event->ID ) ) ?>"><?php echo esc_html( $event->post_title ) ?></a>

		</li>
	<?php endforeach; ?>
	</ul>
<?php else : // ! empty( $events ) ?>
	<p><?php _e( 'No upcoming events found.', 'bp-event-organiser' ) ?></p>
<?php endif; // ! empty( $events ) ?>
