<?php

/**
 * `BP_Component` implementation.
 */
class BPEO_Component extends BP_Component {
	/**
	 * Constructor.
	 */
	public function __construct() {
		parent::start(
			'bpeo',
			__( 'Event Organiser', 'bp-event-organiser' ),
			BPEO_PATH
		);
	}

	/**
	 * Set up globals.
	 */
	public function setup_globals( $args = array() ) {
		parent::setup_globals( array(
			'slug' => bpeo_get_events_slug(),
			'has_directory' => false,
		) );
	}

	/**
	 * Set up navigation.
	 */
	public function setup_nav( $main_nav = array(), $sub_nav = array() ) {
		$name = bp_is_my_profile() ? __( 'My Events', 'bp-event-organiser' ) : __( 'Events', 'bp-event-organiser' );

		$main_nav = array(
			'name' => $name,
			'slug' => $this->slug,
			'position' => 62,
			'show_for_displayed_user' => false,
			'screen_function' => array( $this, 'template_loader' ),
			'default_subnav_slug' => 'calendar',
		);

		$sub_nav[] = array(
			'name' => __( 'Calendar', 'bp-event-organiser' ),
			'slug' => 'calendar', // @todo better l10n
			'parent_url' => bp_displayed_user_domain() . trailingslashit( $this->slug ),
			'parent_slug' => $this->slug,
			'screen_function' => array( $this, 'template_loader' ),
		);

		$sub_nav[] = array(
			'name' => __( 'New Event', 'bp-event-organizer' ),
			'slug' => bpeo_get_events_new_slug(),
			'parent_url' => bp_displayed_user_domain() . trailingslashit( $this->slug ),
			'parent_slug' => $this->slug,
			'user_has_access' => bp_core_can_edit_settings(),
			'screen_function' => array( $this, 'template_loader' ),
		);

		parent::setup_nav( $main_nav, $sub_nav );
	}

	public function template_loader() {
		// new event
		if ( bp_is_current_action( bpeo_get_events_new_slug() ) ) {
			// magic admin screen code!
			require BPEO_PATH . '/includes/class.bpeo_frontend_admin_screen.php';

			$this->create_event = new BPEO_Frontend_Admin_Screen( array(
				'type' => 'new',
			) );

			add_action( 'bp_template_content', array( $this->create_event, 'display' ) );

		// user calendar
		} else {
			add_action( 'bp_template_content', array( $this, 'select_template' ) );
		}

		bp_core_load_template( 'members/single/plugins' );
	}

	/**
	 * Utility function for selecting the correct Docs template to be loaded in the component
	 */
	public function select_template() {

		$args = array(
			'bp_displayed_user_id' => bp_displayed_user_id(),
		);

		echo eo_get_event_fullcalendar( $args );
	}
}

buddypress()->bpeo = new BPEO_Component();
