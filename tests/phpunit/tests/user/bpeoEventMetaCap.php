<?php

/**
 * @group user
 * @group cap
 */
class BPEO_Tests_User_BpeoEventMetaCap extends BPEO_UnitTestCase {
	protected $current_user;
	protected $user;

	public function setUp() {
		parent::setUp();
		$this->current_user = bp_loggedin_user_id();
	}

	public function tearDown() {
		$this->set_current_user( $this->current_user );
	}

	public function test_non_loggedin_user_can_publish_events() {
		$this->set_current_user( 0 );
		$this->assertFalse( current_user_can( 'publish_events' ) );
	}

	public function test_loggedin_user_can_publish_events() {
		$this->user = $this->factory->user->create();
		$this->set_current_user( $this->user );

		$this->assertTrue( current_user_can( 'publish_events' ) );
	}

	public function test_loggedin_user_can_edit_events() {
		$this->user = $this->factory->user->create();
		$this->set_current_user( $this->user );

		$this->assertTrue( current_user_can( 'edit_events' ) );
	}

	public function test_loggedin_user_can_delete_events() {
		$this->user = $this->factory->user->create();
		$this->set_current_user( $this->user );

		$this->assertTrue( current_user_can( 'delete_events' ) );
	}
}
