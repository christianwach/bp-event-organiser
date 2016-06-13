<?php
/*
Plugin Name: BP Event Organiser - Group Widget
Description: Embed events from one of your groups that you are a member of with this widget.
Author: CUNY Academic Commons Team
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html
*/

/** HELPERS **************************************************************/

/**
 * Get a user's public groups.
 *
 * @param  int   $user_id User ID
 * @return array Array with group ID as key and group name as value.
 */
function bpeo_get_user_public_groups( $user_id = 0 ) {
	$groups = groups_get_groups( array(
		'user_id'           => ! empty( $user_id ) ? (int) $user_id : bp_loggedin_user_id(),
		'per_page'          => null,
		'page'              => null,
		'update_meta_cache' => false,
	) );

	$retval = array();

	foreach ( $groups['groups'] as $i => $group ) {
		if ( 'public' !== $group->status ) {
			continue;
		}

		$retval[ $group->id ] = apply_filters( 'bp_get_group_name', $group->name, $group );
	}

	unset( $groups );

	return $retval;
}

/** SHORTCODE ************************************************************/

// Add our shortcode support.
add_action( 'init', 'bpeo_group_shortcode_init' );

/**
 * Shortcode initializer.
 */
function bpeo_group_shortcode_init() {
	add_shortcode( 'bpeo-events', 'bpeo_group_events_shortcode' );
}

/**
 * Add shortcode for group events.
 *
 * @param  array $r Shortcode attributes.
 * @return string
 */
function bpeo_group_events_shortcode( $r = array() ) {
	global $content_width;

	$r = shortcode_atts( array(
		'id' => 0,

		// Type.
		'type' => 'list',

		// Dimensions.
		'width'  => ! empty( $content_width ) ? $content_width : '100%',
		'height' => 300,   // default height is set to 300
	), $r );

	// BP Groupblog fallback support.
	$group_id = empty( $r['group_id'] ) && function_exists( 'get_groupblog_group_id' ) ? get_groupblog_group_id( get_current_blog_id() ) : $group_id;

	if ( empty( $group_id ) ) {
		return;
	}

	$group = groups_get_group( array(
		'group_id'        => $group_id,
		'populate_extras' => false,
	) );

	// Sanity check!
	if ( empty( $group ) ) {
		return;
	}

	// Group calendar
	if ( 'calendar' === $r['type'] ) {
		$link = bp_get_group_permalink( $group ) . 'events/?embedded=true';

	// Upcoming events
	} else {
		$link = bp_get_group_permalink( $group ) . 'events/upcoming/?embedded=true';
	}

	$height = ! empty( $r['height'] ) ? 'height="' . (int) $r['height'] . '"' : '';

	return sprintf(
		'<iframe src="%1$s" frameborder="0" width="%2$s"%3$s></iframe>',
		esc_url( $link ),
		esc_attr( $r['width'] ),
		$height
	);
}

/** WIDGET ***************************************************************/

/**
 * Registers our widget with WordPress.
 */
function bpeo_group_widget_init() {
	// Do not proceed if BuddyPress is not available
	if ( false === function_exists( 'buddypress' ) ) {
		return;
	}

	// Make sure groups component is active
	if ( false === bp_is_active( 'groups' ) ) {
		return;
	}

	// Check if BPEO is registered on the root blog
	$active_plugins = get_blog_option( bp_get_root_blog_id(), 'active_plugins' );
	if( false === in_array( 'bp-event-organiser/bp-event-organiser.php', $active_plugins, true ) ) {
		return;
	}

	// Finally, register our widget!
	register_widget( 'BPEO_Group_Widget' );
}
add_action( 'widgets_init', 'bpeo_group_widget_init' );

/**
 * Group events widget class for BP Event Organiser.
 */
class BPEO_Group_Widget extends WP_Widget {

	/**
	 * Constructor.
	 */
	public function __construct() {
		parent::__construct(
			'bpeo-group-widget',
			__( '(BuddyPress) Group Events', 'bpeo-group-widget' ),
			array(
				'classname'   => 'widget_bpeo_group',
				'description' => __( 'Embed events from one of your groups.', 'bpeo-group-widget' )
			)
		);
	}

	/**
	 * @param array $args
	 * @param array $instance
	 */
	public function widget( $args, $instance ) {

		/**
		 * Filter the widget title.
		 *
		 * @param string $title    The widget title. Default 'My Events'.
		 * @param array  $instance An array of the widget's settings.
		 * @param mixed  $id_base  The widget ID.
		 */
		$title = apply_filters( 'widget_title', empty( $instance['title'] ) ? __( 'My Events', 'bpeo-group-widget' ) : $instance['title'], $instance, $this->id_base );

		echo $args['before_widget'];
		if ( $title ) {
			echo $args['before_title'] . $title . $args['after_title'];
		}

		echo bpeo_group_events_shortcode( array(
			'id'     => $group_id,
			'type'   => $instance['type'],
			'width'  => '100%',
			'height' => $instance['height']
		) );

		echo $args['after_widget'];
	}

	/**
	 * @param array $new_instance
	 * @param array $old_instance
	 * @return array
	 */
	public function update( $new_instance, $old_instance ) {
		$instance = $old_instance;

		$instance['title'] = strip_tags( $new_instance['title'] );

		// Sanity check! Check if user is part of the group before saving
		$group_id = (int) $new_instance['group_id'];
		if ( groups_is_user_member( bp_loggedin_user_id(), $group_id ) ) {
			$instance['group_id'] = $group_id;
		}

		if ( in_array( $new_instance['type'], array( 'list', 'calendar' ) ) ) {
			$instance['type'] = $new_instance['type'];
		} else {
			$instance['type'] = 'list';
		}

		if ( ! empty( $new_instance['height'] ) ) {
			$instance['height'] = (int) $new_instance['height'];
		} else {
			$instance['height'] = 300;
		}

		return $instance;
	}

	/**
	 * @param array $instance
	 */
	public function form( $instance ) {
		// Defaults
		$instance = wp_parse_args( (array) $instance, array(
			'title' => '',
			'group_id' => '',
			'height' => 300
		) );

		$title = esc_attr( $instance['title'] );

		$group_id = $instance['group_id'];

		// BP Groupblog fallback support
		$group_id = empty( $group_id ) && function_exists( 'get_groupblog_group_id' ) ? get_groupblog_group_id( get_current_blog_id() ) : $group_id;

		$group_id = ! empty( $group_id ) ? $group_id : '';

		$groups = bpeo_get_user_public_groups();

		if ( ! empty( $groups ) ) {
	?>

			<p><label for="<?php echo $this->get_field_id('title'); ?>"><?php _e( 'Title:', 'bpeo-group-widget' ); ?></label> <input class="widefat" id="<?php echo $this->get_field_id('title'); ?>" name="<?php echo $this->get_field_name('title'); ?>" type="text" value="<?php echo $title; ?>" /></p>

			<p><label for="<?php echo $this->get_field_id('group_id'); ?>" title="<?php esc_attr_e( 'Select the group you want to display events for.', 'bpeo-group-widget' ); ?>"><?php _e( 'Group:', 'bpeo-group-widget' ); ?></label>
			<select class="widefat" name="<?php echo esc_attr( $this->get_field_name( 'group_id' ) ); ?>" id="<?php echo esc_attr( $this->get_field_id( 'group_id' ) ); ?>">
				<option value="" <?php selected( $group_id, '' ); ?>><?php esc_html_e( '--- Select a group ---', 'bpeo-group-widget' ); ?></option>

				<?php foreach ( $groups as $i => $group_name ) : ?>
					<option value="<?php echo esc_attr( $i ); ?>" <?php selected( $group_id, $i ); ?>><?php echo esc_html( $group->name ); ?></option>
				<?php endforeach; ?>
			</select></p>

			<p><label for="<?php echo $this->get_field_id('type'); ?>" title="<?php esc_attr_e( 'Embed type. Note: Calendar type only works well for large widget areas', 'bpeo-group-widget' ); ?>"><?php _e( 'Embed Type:', 'bpeo-group-widget' ); ?></label>

			<select class="widefat" name="<?php echo esc_attr( $this->get_field_name( 'type' ) ); ?>" id="<?php echo esc_attr( $this->get_field_id( 'type' ) ); ?>">
				<option value="list" <?php selected( $instance['type'], 'list' ); ?>><?php esc_html_e( 'List of upcoming events', 'bpeo-group-widget' ); ?></option>

				<?php // Only show this option if we're using EO 3.0+ ?>
				<?php if ( class_exists( 'EO_Theme_Compat' ) ) : ?>
					<option value="calendar" <?php selected( $instance['type'], 'calendar' ); ?>><?php esc_html_e( 'Calendar from group', 'bpeo-group-widget' ); ?></option>
				<?php endif; ?>

			</select></p>

			<p><label for="<?php echo $this->get_field_id( 'height' ); ?>" title="<?php esc_attr_e( 'Height of the group widget. Set this to a larger number if desired.', 'bpeo-group-widget' ); ?>"><?php _e( 'Height:' ); ?></label>
			<input id="<?php echo $this->get_field_id( 'height' ); ?>" name="<?php echo $this->get_field_name( 'height' ); ?>" type="text" value="<?php echo $instance['height']; ?>" size="3" /></p>

<?php
		} else {
?>

			<p><?php _e( "You are not a member of any groups.  Please join a group before attempting to embed a group's event list.", 'bpeo-group-widget' ); ?></p>

<?php
		}
	}
}