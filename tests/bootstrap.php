<?php

if ( ! defined( 'BP_TESTS_DIR' ) ) {
	define( 'BP_TESTS_DIR', dirname( __FILE__ ) . '/../../buddypress/tests/phpunit' );
}

if ( file_exists( BP_TESTS_DIR . '/bootstrap.php' ) ) :

	require_once getenv( 'WP_DEVELOP_DIR' ) . '/tests/phpunit/includes/functions.php';

	function _bootstrap_bp() {
		// Make sure BP is installed and loaded first.
		require BP_TESTS_DIR . '/includes/loader.php';
	}
	tests_add_filter( 'muplugins_loaded', '_bootstrap_bp' );

	// Hack: setup_theme is late enough to ensure WP_Rewrite, but earlier than 'init'.
	function _bootstrap_bpeo() {
		// Bootstrap EO.
		require dirname( __FILE__ ) . '/../../event-organiser/event-organiser.php';
		eventorganiser_install();

		// Then load BPEO.
		require dirname( __FILE__ ) . '/../bp-event-organiser.php';
	}
	tests_add_filter( 'setup_theme', '_bootstrap_bpeo' );

	require getenv( 'WP_DEVELOP_DIR' ) . '/tests/phpunit/includes/bootstrap.php';

	// Load the BP test files
	require BP_TESTS_DIR . '/includes/testcase.php';

endif;
