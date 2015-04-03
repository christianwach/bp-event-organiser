<?php

/**
 * @group activity
 */
class BPEO_Tests_Activity_EventEdit extends BPEO_UnitTestCase {
	public function test_edit_event_with_no_prior_edits_should_create_new_activity_item() {
		$u = $this->factory->user->create();

		$now = time();
		$e = $this->event_factory->event->create( array(
			'post_date' => date( 'Y-m-d H:i:s', $now - 60*60*24 ),
			'post_author' => $u,
			'start' => new DateTime( date( 'Y-m-d H:i:s', $now - 60*60 ) ),
			'end' => new DateTime( date( 'Y-m-d H:i:s' ) ),
		) );

		$before = bpeo_get_activity_by_event_id( $e );

		eo_update_event( $e, array(), array( 'post_content' => 'foo' ) );

		$after = bpeo_get_activity_by_event_id( $e );

		// `array_diff()` for our modern times.
		$a = array();
		foreach ( $after as $_after ) {
			foreach ( $before as $_before ) {
				if ( $_after == $_before ) {
					continue 2;
				}
			}

			$a[] = $_after;
		}

		$this->assertNotEmpty( $a );
		$this->assertEquals( $u, $a[0]->user_id );
		$this->assertEquals( 'events', $a[0]->component );
		$this->assertEquals( 'bpeo_edit_event', $a[0]->type );
		$this->assertEquals( $e, $a[0]->secondary_item_id );
	}

	public function test_edit_event_with_no_prior_edits_should_create_new_activity_item_in_connected_groups() {
		$u = $this->factory->user->create();
		$this->groups = $this->factory->group->create_many( 3 );

		// Group connections happen on 'eventorganiser_save_event'. Whee!
		add_action( 'eventorganiser_save_event', array( $this, 'connect_events' ) );

		$now = time();
		$e = $this->event_factory->event->create( array(
			'post_date' => date( 'Y-m-d H:i:s', $now - 60*60*24 ),
			'post_author' => $u,
			'start' => new DateTime( date( 'Y-m-d H:i:s', $now - 60*60 ) ),
			'end' => new DateTime( date( 'Y-m-d H:i:s' ) ),
		) );

		remove_action( 'eventorganiser_save_event', array( $this, 'connect_events' ) );

		$before = bpeo_get_activity_by_event_id( $e );

		eo_update_event( $e, array(), array( 'post_content' => 'foo' ) );

		$after = bpeo_get_activity_by_event_id( $e );

		// `array_diff()` for our modern times.
		$a = array();
		foreach ( $after as $_after ) {
			foreach ( $before as $_before ) {
				if ( $_after == $_before ) {
					continue 2;
				}
			}

			$a[] = $_after;
		}

		// Get only the group updates.
		$a = wp_list_filter( $a, array( 'component' => 'groups' ) );
		$a = array_values( $a );

		$this->assertNotEmpty( $a );
		$this->assertEquals( $u, $a[0]->user_id );
		$this->assertEquals( 'groups', $a[0]->component );
		$this->assertEquals( 'bpeo_edit_event', $a[0]->type );
		$this->assertEquals( $this->groups[0], $a[0]->item_id );
		$this->assertEquals( $e, $a[0]->secondary_item_id );

		$this->assertNotEmpty( $a );
		$this->assertEquals( $u, $a[1]->user_id );
		$this->assertEquals( 'groups', $a[1]->component );
		$this->assertEquals( 'bpeo_edit_event', $a[1]->type );
		$this->assertEquals( $this->groups[2], $a[1]->item_id );
		$this->assertEquals( $e, $a[1]->secondary_item_id );
	}

	public function test_edit_event_with_prior_edit_less_than_six_hours_old_should_not_create_now_activity_item() {
		$u = $this->factory->user->create();

		$now = time();
		$e = $this->event_factory->event->create( array(
			'post_date' => date( 'Y-m-d H:i:s', $now - 60*60*24 ),
			'post_author' => $u,
			'start' => new DateTime( date( 'Y-m-d H:i:s', $now - 60*60 ) ),
			'end' => new DateTime( date( 'Y-m-d H:i:s' ) ),
		) );

		// Manually create edit item, 5:59 ago.
		$ago = $now - ( 60*60*6 - 60 );
		bp_activity_add( array(
			'component' => 'events',
			'type' => 'bpeo_edit_event',
			'secondary_item_id' => $e,
			'recorded_time' => date( 'Y-m-d H:i:s', $ago ),
		) );

		$before = bpeo_get_activity_by_event_id( $e );

		eo_update_event( $e, array(), array( 'post_content' => 'foo' ) );

		$after = bpeo_get_activity_by_event_id( $e );

		$this->assertEquals( $before, $after );
	}

	public function test_edit_event_with_prior_edit_more_than_six_hours_old_should_not_create_now_activity_item() {
		$u = $this->factory->user->create();

		$now = time();
		$e = $this->event_factory->event->create( array(
			'post_date' => date( 'Y-m-d H:i:s', $now - 60*60*24 ),
			'post_author' => $u,
			'start' => new DateTime( date( 'Y-m-d H:i:s', $now - 60*60 ) ),
			'end' => new DateTime( date( 'Y-m-d H:i:s' ) ),
		) );

		// Manually create edit item, 6:01 ago.
		$ago = $now - ( 60*60*6 + 60 );
		bp_activity_add( array(
			'component' => 'events',
			'type' => 'bpeo_edit_event',
			'secondary_item_id' => $e,
			'recorded_time' => date( 'Y-m-d H:i:s', $ago ),
		) );

		$before = bpeo_get_activity_by_event_id( $e );

		eo_update_event( $e, array(), array( 'post_content' => 'foo' ) );

		$after = bpeo_get_activity_by_event_id( $e );

		// `array_diff()` for our modern times.
		$a = array();
		foreach ( $after as $_after ) {
			foreach ( $before as $_before ) {
				if ( $_after == $_before ) {
					continue 2;
				}
			}

			$a[] = $_after;
		}

		$this->assertNotEmpty( $a );
		$this->assertEquals( $u, $a[0]->user_id );
		$this->assertEquals( 'events', $a[0]->component );
		$this->assertEquals( 'bpeo_edit_event', $a[0]->type );
		$this->assertEquals( $e, $a[0]->secondary_item_id );

		$modified_event = get_post( $e );
		$this->assertEquals( $modified_event->post_modified, $a[0]->date_recorded );
	}

	public function connect_events( $e ) {
		bpeo_connect_event_to_group( $e, $this->groups[0] );
		bpeo_connect_event_to_group( $e, $this->groups[2] );
	}
}
