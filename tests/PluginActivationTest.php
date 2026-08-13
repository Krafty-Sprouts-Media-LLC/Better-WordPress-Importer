<?php

class PluginActivationTest extends WP_UnitTestCase {
	/**
	 * The plugin should be installed and activated.
	 */
	public function test_plugin_activated() {
		$this->assertTrue( class_exists( 'WXR_Importer' ) );
	}
}
