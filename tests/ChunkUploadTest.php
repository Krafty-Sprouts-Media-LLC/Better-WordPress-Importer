<?php
/**
 * Tests for assembling a WXR upload into the media library.
 *
 * @package WordPress_Importer
 */

class ChunkUploadTest extends WP_UnitTestCase {
	/**
	 * Path to the small fixture WXR file.
	 *
	 * @var string
	 */
	protected $fixture_file;

	public function set_up() {
		parent::set_up();

		$this->fixture_file = dirname( __FILE__ ) . '/data/small-export.xml';

		if ( ! function_exists( 'wp_handle_sideload' ) ) {
			require_once ABSPATH . 'wp-admin/includes/file.php';
		}
	}

	/**
	 * Copy the fixture to a unique temp path so wp_handle_sideload() can
	 * move it without destroying the original.
	 *
	 * @return string Absolute path to the temp copy.
	 */
	protected function copy_fixture_to_temp() {
		$tmp = wp_tempnam( 'wxr-upload-test.xml' );
		$this->assertNotFalse( $tmp );
		$this->assertTrue( copy( $this->fixture_file, $tmp ) );

		return $tmp;
	}

	/**
	 * Sideload overrides used by WXR_Import_UI::handle_chunk_upload().
	 *
	 * @return array
	 */
	protected function sideload_overrides() {
		return array(
			'test_form' => false,
			'test_type' => false,
			'action'    => 'wxr-import-upload-chunk',
		);
	}

	public function test_xml_extension_is_required() {
		$rejected = wp_check_filetype( 'payload.php', array( 'xml' => 'application/xml' ) );
		$this->assertEmpty( $rejected['ext'] );

		$accepted = wp_check_filetype( 'inquiral.WordPress.2026-08-17.xml', array( 'xml' => 'application/xml' ) );
		$this->assertSame( 'xml', $accepted['ext'] );
		$this->assertSame( 'application/xml', $accepted['type'] );
	}

	public function test_wxr_sideload_succeeds_when_mime_check_is_skipped() {
		$tmp = $this->copy_fixture_to_temp();

		$file = array(
			'name'     => 'inquiral.WordPress.2026-08-17.xml',
			'tmp_name' => $tmp,
		);
		$result = wp_handle_sideload( $file, $this->sideload_overrides() );

		$this->assertArrayNotHasKey( 'error', $result, isset( $result['error'] ) ? $result['error'] : '' );
		$this->assertFileExists( $result['file'] );
		$this->assertStringEndsWith( '.xml', $result['file'] );

		$attachment_id = wp_insert_attachment(
			array(
				'post_mime_type' => ! empty( $result['type'] ) ? $result['type'] : 'application/xml',
				'post_title'     => 'inquiral.WordPress.2026-08-17.xml',
				'post_content'   => '',
				'post_status'    => 'private',
			),
			$result['file']
		);

		$this->assertNotWPError( $attachment_id );
		$this->assertGreaterThan( 0, (int) $attachment_id );
	}
}
