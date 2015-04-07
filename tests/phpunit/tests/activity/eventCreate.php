<?php

/**
 * @group activity
 */
class BPEO_Tests_Activity_EventCreate extends BPEO_UnitTestCase {
	public function test_new_event_not_connected_to_group() {
		$u = $this->factory->user->create();

		$now = time();
		$e = eo_insert_event( array(
			'post_author' => $u,
			'start' => new DateTime( date( 'Y-m-d H:i:s', $now - 60*60 ) ),
			'end' => new DateTime( date( 'Y-m-d H:i:s' ) ),
		) );

		$a = bpeo_get_activity_by_event_id( $e );

		$this->assertNotEmpty( $a );
		$this->assertEquals( $u, $a[0]->user_id );
		$this->assertEquals( 'events', $a[0]->component );
		$this->assertEquals( 'bpeo_create_event', $a[0]->type );
		$this->assertEquals( $e, $a[0]->secondary_item_id );
	}

	public function test_new_event_connected_to_groups() {
		$u = $this->factory->user->create();
		$this->groups = $this->factory->group->create_many( 3 );

		// Group connections happen on 'eventorganiser_save_event'. Whee!
		add_action( 'eventorganiser_save_event', array( $this, 'connect_events' ) );

		$now = time();
		$e = eo_insert_event( array(
			'post_author' => $u,
			'start' => new DateTime( date( 'Y-m-d H:i:s', $now - 60*60 ) ),
			'end' => new DateTime( date( 'Y-m-d H:i:s' ) ),
		) );

		remove_action( 'eventorganiser_save_event', array( $this, 'connect_events' ) );

		$a = bpeo_get_activity_by_event_id( $e );
		$this->assertNotEmpty( $a );

		// User item.
		$this->assertEquals( $u, $a[0]->user_id );
		$this->assertEquals( 'events', $a[0]->component );
		$this->assertEquals( 'bpeo_create_event', $a[0]->type );
		$this->assertEquals( $e, $a[0]->secondary_item_id );

		// Group item.
		$this->assertEquals( $u, $a[1]->user_id );
		$this->assertEquals( 'groups', $a[1]->component );
		$this->assertEquals( 'bpeo_create_event', $a[1]->type );
		$this->assertEquals( $this->groups[0], $a[1]->item_id );
		$this->assertEquals( $e, $a[1]->secondary_item_id );
		$this->assertEquals( 1, $a[1]->hide_sitewide );

		// Group item.
		$this->assertEquals( $u, $a[2]->user_id );
		$this->assertEquals( 'groups', $a[2]->component );
		$this->assertEquals( 'bpeo_create_event', $a[2]->type );
		$this->assertEquals( $this->groups[2], $a[2]->item_id );
		$this->assertEquals( $e, $a[2]->secondary_item_id );
		$this->assertEquals( 1, $a[2]->hide_sitewide );
	}

	public function test_action_string_for_new_event_not_connected_to_groups() {
		$u = $this->factory->user->create();

		$now = time();
		$e = eo_insert_event( array(
			'post_author' => $u,
			'start' => new DateTime( date( 'Y-m-d H:i:s', $now - 60*60 ) ),
			'end' => new DateTime( date( 'Y-m-d H:i:s' ) ),
		) );

		$a = bpeo_get_activity_by_event_id( $e );

		$event = get_post( $e );

		$expected = sprintf(
			'%s created the event %s',
			sprintf( '<a href="%s">%s</a>', esc_url( bp_core_get_user_domain( $u ) ), esc_html( bp_core_get_user_displayname( $u ) ) ),
			sprintf( '<a href="%s">%s</a>', esc_url( get_permalink( $event ) ), esc_html( $event->post_title ) )
		);

		$this->assertSame( $expected, $a[0]->action );
	}

	public function connect_events( $e ) {
		bpeo_connect_event_to_group( $e, $this->groups[0] );
		bpeo_connect_event_to_group( $e, $this->groups[2] );
	}
}
