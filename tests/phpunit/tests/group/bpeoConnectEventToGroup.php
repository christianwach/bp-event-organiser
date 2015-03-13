<?php

/**
 * @group group
 */
class BPEO_Tests_Group_BpeoConnectEventToGroup extends BPEO_UnitTestCase {
	public function test_should_return_false_for_non_existent_group() {
		$e = $this->event_factory->event->create();
		$this->assertFalse( bpeo_connect_event_to_group( $e, 12345 ) );
	}

	public function test_should_return_false_for_non_existent_event() {
		$g = $this->factory->group->create();
		$this->assertFalse( bpeo_connect_event_to_group( 12345, $g ) );
	}
}
