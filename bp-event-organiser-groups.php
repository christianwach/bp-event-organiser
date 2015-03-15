<?php /*
================================================================================
BP Group Organiser Group Extension
================================================================================
AUTHOR: Christian Wach <needle@haystack.co.uk>
--------------------------------------------------------------------------------
NOTES
=====

This class extends BP_Group_Extension to create the screens our plugin requires.
See: http://codex.buddypress.org/developer/plugin-development/group-extension-api/

--------------------------------------------------------------------------------
*/



// prevent problems during upgrade or when Groups are disabled
if ( !class_exists( 'BP_Group_Extension' ) ) { return; }



/*
================================================================================
Class Name
================================================================================
*/

class BP_Event_Organiser_Group_Extension extends BP_Group_Extension {



	/*
	============================================================================
	Properties
	============================================================================
	*/

	/*
	// 'public' will show our extension to non-group members
	// 'private' means only members of the group can view our extension
	public $visibility = 'public';

	// if our extension does not need a navigation item, set this to false
	public $enable_nav_item = true;

	// if our extension does not need an edit screen, set this to false
	public $enable_edit_item = true;

	// if our extension does not need an admin metabox, set this to false
	public $enable_admin_item = true;

	// the context of our admin metabox. See add_meta_box()
	public $admin_metabox_context = 'core';

	// the priority of our admin metabox. See add_meta_box()
	public $admin_metabox_priority = 'normal';
	*/

	// no need for a creation step
	public $enable_create_step = false;

	// if our extension does not need an edit screen, set this to false
	public $enable_edit_item = false;

	// if our extension does not need an admin metabox, set this to false
	public $enable_admin_item = false;



	/**
	 * @description: initialises this object
	 * @return nothing
	 */
	public function __construct() {

		// init vars with filters applied
		$name = apply_filters( 'bpeo_extension_title', __( 'Group Events', 'bp-event-organizer' ) );
		$slug = apply_filters( 'bpeo_extension_slug', bpeo_get_events_slug() );
		$pos = apply_filters( 'bpeo_extension_pos', 31 );

		// test for BP 1.8+
		// could also use 'bp_esc_sql_order' (the other core addition)
		if ( function_exists( 'bp_core_get_upload_dir' ) ) {

			// init array
			$args = array(
				'name' => $name,
				'slug' => $slug,
				'nav_item_position' => $pos,
				'enable_create_step' => false,
			);

			// init
			parent::init( $args );

	 	} else {

			// name our tab
			$this->name = $name;
			$this->slug = $slug;

			// set position in navigation
			$this->nav_item_position = $pos;

			// disable create step
			$this->enable_create_step = false;

		}

	}

	/**
	 * Override parent _display_hook() method to add logic for single events.
	 */
	public function _display_hook() {
		// single event
		if ( ! empty( buddypress()->action_variables ) ) {
			$this->single_event_screen();
			add_action( 'bp_template_content', array( $this, 'display_single_event' ) );

		// default behavior
		} else{
			add_action( 'bp_template_content', array( $this, 'call_display' ) );
		}

		bp_core_load_template( apply_filters( 'bp_core_template_plugin', $this->template_file ) );
	}

	/**
	 * @description display our content when the nav item is selected
	 */
	public function display( $group_id = null ) {
		// show header
		echo '<h3>'.apply_filters( 'bpeo_extension_title', __( 'Group Events', 'bp-event-organizer' ) ).'</h3>';

		// delete the calendar transient cache depending on user cap
		// @todo EO's calendar transient cache needs overhauling
		if( current_user_can( 'read_private_events' ) ){
			delete_transient( 'eo_full_calendar_public_priv' );
		} else {
			delete_transient( 'eo_full_calendar_public' );
		}

		// show events calendar, filtered by meta value in eo->intercept_calendar()
		echo eo_get_event_fullcalendar( array(
			'headerright' => 'prev,next today,month,agendaWeek',
		) );

	}

	/**
	 * Single event screen handler.
	 */
	protected function single_event_screen() {
		if ( false === bp_is_current_action( $this->slug ) ) {
			return;
		}

		if ( empty( buddypress()->action_variables ) ) {
			return;
		}

		// query for the event
		$event = eo_get_events( array(
			'name' => bp_action_variable()
		) );

		// check if event exists
		if ( empty( $event ) ) {
			bp_core_add_message( __( 'Event does not exist.', 'bp-event-organiser' ), 'error' );
			bp_core_redirect( bpeo_get_group_permalink() );
			die();
		}

		// check if event belongs to group
		// this needs to be edited once boone finishes new schema
		if ( false == in_array( bp_get_current_group_id(), $GLOBALS['buddypress_event_organiser']->eo->get_calendar_groups( $event[0]->ID ) ) ) {
			bp_core_add_message( __( 'Event does not belong to this group.', 'bp-event-organiser' ), 'error' );
			bp_core_redirect( bpeo_get_group_permalink() );
			die();
		}

		// save queried event as property
		$this->queried_event = $event[0];

		// edit single event logic
		if ( bp_is_action_variable( 'edit', 1 ) ) {
			// check if user has access
			// @todo currently all group members have access to edit events... restrict to mods?
			if ( false === is_user_logged_in() || false === buddypress()->groups->current_group->user_has_access ) {
				bp_core_add_message( __( 'You do not have access to edit this event.', 'bp-event-organiser' ), 'error' );
				bp_core_redirect( bpeo_get_group_permalink() . "{$this->queried_event->post_name}/" );
				die();
			}

			// load up EO edit routine
			require EVENT_ORGANISER_DIR . 'event-organiser-edit.php';

			// update event
			if ( $_POST ) {
				// require admin post functions to use edit_post()
				require ABSPATH . '/wp-admin/includes/post.php';

				// add EO save hook
				add_action( 'save_post', 'eventorganiser_details_save' );

				// verify!
				check_admin_referer( 'update-post_' . $_POST['post_ID'] );

				// rejig content for saving function
				// this is due to us changing the editor ID to avoid conflicts with themes
				$_POST['content'] = $_POST['bpeo-content'];
				edit_post();

				// redirect
				bp_core_add_message( __( 'Event updated.', 'bp-event-organiser' ) );
				bp_core_redirect( bpeo_get_group_permalink() . "{$this->queried_event->post_name}/" );
				die();

			// display edit necessities
			} else {
				// magic metabox abstraction code!
				require BPEO_PATH . '/includes/metabox-abstraction.php';

				// enqueue editor scripts
				add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_editor_scripts' ) );
				add_action( 'wp_footer',          array( $this, 'inline_js' ), 20 );
			}
		}
	}

	/**
	 * Display a single event within a group.
	 *
	 * @todo Move part of this functionality into a template part so theme devs can customize.
	 */
	public function display_single_event() {
		if ( empty( $this->queried_event ) ) {
			return;
		}

		// save $post global temporarily
		global $post;
		$_post = false;
		if ( ! empty( $post ) ) {
			$_post = $post;
		}

		// override the $post global so EO can use its functions
		$post = $this->queried_event;

		// edit screen has its own display method
		if ( bp_is_action_variable( 'edit', 1 ) ) {
			$this->display_single_event_edit();

			// revert $post global
			if ( ! empty( $_post ) ) {
				$post = $_post;
			}
			return;
		}

		/**
		 * Move this logic into a template part
		 */
		echo '<h2> ' . get_the_title() . '</h2>';

		echo '<h4>' . __( 'Event Description', 'bp-event-organizer' ) . '</h4>';

		// Make this better... have to juggle the_content filters...
		echo wpautop( $post->post_content );
		eo_get_template_part( 'event-meta-event-single' );

		// Action links
		// @todo Add 'Edit' link
		echo '<a href="' . bpeo_get_group_permalink() . '">' . __( '&larr; Back', 'bp-events-organizer' ). '</a>';

		// revert $post global
		if ( ! empty( $_post ) ) {
			$post = $_post;
		}
	}

	/**
	 * Displays the single event's edit content.
	 *
	 * Heavily piggybacks off /wp-admin/edit-form-advanced.php. This will probably
	 * be moved out of the group extension class to support user events later on.
	 *
	 * Currently, only supports taxonomy metaboxes.  Other metaboxes such as
	 * excerpt and thumbnail might be added later on.
	 */
	public function display_single_event_edit() {
		global $post;

		$_wp_editor_expand = $_content_editor_dfw = false;
	?>

		<h2><?php printf( __( 'Edit Event', 'bp-events-organizer' ), get_the_title() ); ?></h2>

		<form id="post" method="post" action="" name="post">
			<?php wp_nonce_field( 'update-post_' . $post->ID ); ?>
			<input type="hidden" id="post_ID" name="post_ID" value="<?php echo esc_attr( $post->ID ); ?>" />
			<input type="hidden" id="post_author" name="post_author" value="<?php echo esc_attr( bp_loggedin_user_id() ); ?>" />
			<input type="hidden" id="post_type" name="post_type" value="<?php echo esc_attr( $post->post_type ) ?>" />

			<div id="titlediv">
			<div id="titlewrap">
				<?php
				/**
				 * Filter the title field placeholder text.
				 *
				 * @param string  $text Placeholder text. Default 'Enter title here'.
				 * @param WP_Post $post Post object.
				 */
				$title_placeholder = apply_filters( 'enter_title_here', __( 'Enter title here', 'bp-event-organizer' ), $post );
				?>
				<label class="screen-reader-text" id="title-prompt-text" for="title"><?php echo $title_placeholder; ?></label>
				<input type="text" name="post_title" size="30" value="<?php echo esc_attr( htmlspecialchars( $post->post_title ) ); ?>" id="title" spellcheck="true" autocomplete="off" />
			</div>
			</div>

			<div id="postdivrich" class="postarea<?php if ( $_wp_editor_expand ) { echo ' wp-editor-expand'; } ?>">
				<?php // we have to change the ID element to something other than 'content' to prevent theme conflicts ?>
				<?php wp_editor( $post->post_content, 'bpeo-content', array(
					'_content_editor_dfw' => $_content_editor_dfw,
					'drag_drop_upload' => true,
					'tabfocus_elements' => 'content-html,save-post',
					'editor_height' => 300,
					'tinymce' => array(
						'resize' => false,
						'wp_autoresize_on' => $_wp_editor_expand,
						'add_unload_trigger' => false,
					),
				) ); ?>

				<table id="post-status-info"><tbody><tr>
					<td id="wp-word-count"><?php printf( __( 'Word count: %s', 'bp-event-organizer' ), '<span class="word-count">0</span>' ); ?></td>
					<td class="autosave-info">
					<span class="autosave-message">&nbsp;</span>
				<?php
					if ( 'auto-draft' != $post->post_status ) {
						echo '<span id="last-edit">';
						if ( $last_user = get_userdata( get_post_meta( $post->ID, '_edit_last', true ) ) ) {
							printf( __( 'Last edited by %1$s on %2$s at %3$s', 'bp-event-organizer' ), esc_html( $last_user->display_name ), mysql2date( get_option( 'date_format' ), $post->post_modified ), mysql2date( get_option( 'time_format' ), $post->post_modified ) );
						} else {
							printf(__('Last edited on %1$s at %2$s', 'bp-event-organizer' ), mysql2date( get_option( 'date_format' ), $post->post_modified ), mysql2date( get_option( 'time_format' ), $post->post_modified ) );
						}
						echo '</span>';
					} ?>
					</td>
					<td id="content-resize-handle" class="hide-if-no-js"><br /></td>
				</tr></tbody></table>

				<?php if ( ! wp_is_mobile() && '' === $post->post_title ) : ?>
				<script type="text/javascript">
					try{document.post.title.focus();}catch(e){}
				</script>
				<?php endif; ?>

			</div>

	<?php
		// metabox time!

		// load up EO's metabox
		eventorganiser_edit_init();

		// duplicates taxonomy metabox registration from edit-form-advanced.php
		foreach ( get_object_taxonomies( $post ) as $tax_name ) {
			$taxonomy = get_taxonomy( $tax_name );
			if ( ! $taxonomy->show_ui || false === $taxonomy->meta_box_cb ) {
				continue;
			}

			$label = $taxonomy->labels->name;

			if ( ! is_taxonomy_hierarchical( $tax_name ) ) {
				$tax_meta_box_id = 'tagsdiv-' . $tax_name;
			} else {
				$tax_meta_box_id = $tax_name . 'div';
			}

			add_meta_box( $tax_meta_box_id, $label, $taxonomy->meta_box_cb, null, 'side', 'core', array( 'taxonomy' => $tax_name ) );
		}

		// plugin metabox registration
		do_action( 'add_meta_boxes', 'event', $post );
		do_action( 'add_meta_boxes_event', $post );

		// render metaboxes
		do_meta_boxes( 'event', 'normal', $post );
		do_meta_boxes( 'event', 'side', $post );

		// output save button
		// copied from publish metabox
		// we're not supporting directly changing the post date at the moment
	?>

			<div id="publishing-action">
			<span class="spinner"></span>
			<?php
			if ( !in_array( $post->post_status, array('publish', 'future', 'private') ) || 0 == $post->ID ) {
				if ( $can_publish ) :
					if ( !empty($post->post_date_gmt) && time() < strtotime( $post->post_date_gmt . ' +0000' ) ) : ?>
					<input name="original_publish" type="hidden" id="original_publish" value="<?php esc_attr_e('Schedule') ?>" />
					<?php submit_button( __( 'Schedule' ), 'primary button-large', 'publish', false ); ?>
			<?php	else : ?>
					<input name="original_publish" type="hidden" id="original_publish" value="<?php esc_attr_e('Publish') ?>" />
					<?php submit_button( __( 'Publish' ), 'primary button-large', 'publish', false ); ?>
			<?php	endif;
				else : ?>
					<input name="original_publish" type="hidden" id="original_publish" value="<?php esc_attr_e('Submit for Review') ?>" />
					<?php submit_button( __( 'Submit for Review' ), 'primary button-large', 'publish', false ); ?>
			<?php
				endif;
			} else { ?>
					<input name="original_publish" type="hidden" id="original_publish" value="<?php esc_attr_e('Update') ?>" />
					<input name="save" type="submit" class="button button-primary button-large" id="publish" value="<?php esc_attr_e( 'Update' ) ?>" />
			<?php
			} ?>
			</div>
		</form>

	<?php
	}

	/**
	 * Enqueues editor scripts and styles for the frontend.
	 *
	 * This will probably be moved out of the group extension class in the future
	 * to support user events.
	 */
	public function enqueue_editor_scripts() {
		// save $post global temporarily
		global $post;
		$_post = false;
		if ( ! empty( $post ) ) {
			$_post = $post;
		}

		// override the $post global so EO can use its functions
		$post = $this->queried_event;

		/** EO-specific scripts ***********************************************/

		// deregister frontend Google Maps script and use admin one
		wp_deregister_script( 'eo_GoogleMap' );
		wp_register_script( 'eo_GoogleMap', '//maps.googleapis.com/maps/api/js?sensor=false&language='.substr(get_locale(),0,2));

		// manually queue up EO's scripts
		eventorganiser_register_scripts();
		eventorganiser_add_admin_scripts( 'post.php' );

		/** editor-specific scripts *******************************************/

		// frontend requires manually enqueuing some scripts due to is_admin() check
		// @see wp_default_scripts()
		$suffix = SCRIPT_DEBUG ? '' : '.min';

		wp_enqueue_script( 'word-count', admin_url( "js/word-count$suffix.js" ), array( 'jquery' ), false, 1 );
		wp_enqueue_script( 'tags-box', admin_url( "js/tags-box$suffix.js" ), array( 'jquery', 'suggest' ), false, 1 );
		wp_enqueue_script( 'postbox', admin_url( "js/postbox$suffix.js" ), array('jquery-ui-sortable'), false, 1 );
		wp_enqueue_script( 'post', admin_url( "js/post$suffix.js" ), array( 'suggest', 'wp-lists', 'postbox', 'tags-box' ), false, 1 );
		wp_enqueue_script( 'link', admin_url( "js/link$suffix.js" ), array( 'wp-lists', 'postbox' ), false, 1 );
		//wp_enqueue_script( 'autosave' );
		if ( wp_is_mobile() ) {
			wp_enqueue_script( 'jquery-touch-punch' );
		}

		// localization
		wp_localize_script( 'tags-box', 'tagsBoxL10n', array(
			'tagDelimiter' => _x( ',', 'tag delimiter', 'bp-event-organizer' ),
		) );
		wp_localize_script( 'word-count', 'wordCountL10n', array(
			/* translators: If your word count is based on single characters (East Asian characters),
			   enter 'characters'. Otherwise, enter 'words'. Do not translate into your own language. */
			'type' => 'characters' == _x( 'words', 'word count: words or characters?', 'bp-event-organizer' ) ? 'c' : 'w',
		) );
		// might not be needed... keeping for now
		wp_localize_script( 'post', 'postL10n', array(
			'ok' => __('OK'),
			'cancel' => __('Cancel'),
			'publishOn' => __('Publish on:'),
			'publishOnFuture' =>  __('Schedule for:'),
			'publishOnPast' => __('Published on:'),
			/* translators: 1: month, 2: day, 3: year, 4: hour, 5: minute */
			'dateFormat' => __('%1$s %2$s, %3$s @ %4$s : %5$s'),
			'showcomm' => __('Show more comments'),
			'endcomm' => __('No more comments found.'),
			'publish' => __('Publish'),
			'schedule' => __('Schedule'),
			'update' => __('Update'),
			'savePending' => __('Save as Pending'),
			'saveDraft' => __('Save Draft'),
			'private' => __('Private'),
			'public' => __('Public'),
			'publicSticky' => __('Public, Sticky'),
			'password' => __('Password Protected'),
			'privatelyPublished' => __('Privately Published'),
			'published' => __('Published'),
			'saveAlert' => __('The changes you made will be lost if you navigate away from this page.'),
			'savingText' => __('Saving Draft&#8230;'),
		) );

		// editor-specific styles
		wp_enqueue_style( 'bpeo-editor-edit', admin_url( 'css/edit.css' ) );
		wp_enqueue_style( 'bpeo-editor', BUDDYPRESS_EVENT_ORGANISER_URL . 'assets/css/editor.css' );

		// revert $post global
		if ( ! empty( $_post ) ) {
			$post = $_post;
		}

		// set the 'pagenow' JS variable to emulate wp-admin area
		// @see /wp-admin/admin-header.php
	?>

<script type="text/javascript">
	var pagenow = '<?php echo get_current_screen()->id; ?>',
		post_type = '<?php echo get_current_screen()->id; ?>';
</script>

	<?php
	}

	/**
	 * Fix various wp-admin JS looking for the wrong element.
	 *
	 * Currently supports word count on load.
	 */
	public function inline_js() {
	?>

	<script type="text/javascript">
	( function( $, window ) {
		if ( typeof(wpWordCount) != 'undefined' ) {
			$(document).trigger('wpcountwords', [ $('.wp-editor-area').val() ]);
		}
	}( jQuery, window ));
	</script>

	<?php
	}

} // class ends



// register our class
bp_register_group_extension( 'BP_Event_Organiser_Group_Extension' );
