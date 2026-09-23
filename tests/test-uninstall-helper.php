<?php

namespace Bojaghi\CustomTAbles\Tests;

use WP_UnitTestCase;
use Bojaghi\CustomTables\Uninstall_Helper;

class Uninstall_Helper_Test extends WP_UnitTestCase {
	public function test_uninstall(): void {
		$instance = function () {
			static $flag = 0;

			return $flag++;
		};

		Uninstall_Helper::add_instance( $instance );
		Uninstall_Helper::uninstall();

		// $instance is called twice, returns 1 here.
		$this->assertEquals( 1, $instance() );
	}
}
