<?php
/**
 * File-based logger for the WXR importer.
 *
 * @package WordPress_Importer
 * @since   2.1.0
 */

/**
 * Logs importer events to a plain-text file, independent of WP_DEBUG.
 *
 * Unlike WP_Importer_Logger_HTML, WP_Importer_Logger_CLI, and
 * WP_Importer_Logger_ServerSentEvents (which only echo live to the current
 * request/response), this logger persists every entry so an import run can
 * be reviewed after the fact.
 *
 * @since 2.1.0
 */
class WP_Importer_Logger_File extends WP_Importer_Logger {
	/**
	 * Absolute path to the log file.
	 *
	 * @since 2.1.0
	 * @var string
	 */
	protected $path;

	/**
	 * Constructor.
	 *
	 * @since 2.1.0
	 *
	 * @param string $path Optional. Absolute path to the log file. Defaults to
	 *                     `wp-content/wxr-importer-debug.log` — deliberately named
	 *                     apart from the standard WP_DEBUG_LOG output so the two
	 *                     are never confused in a directory listing.
	 */
	public function __construct( $path = '' ) {
		$this->path = $path ? $path : WP_CONTENT_DIR . '/wxr-importer-debug.log';
	}

	/**
	 * Logs with an arbitrary level.
	 *
	 * Appends a single line per call; the file is not held open between
	 * calls, since an import can span multiple requests (e.g. the AJAX/SSE
	 * driven dashboard flow).
	 *
	 * @since 2.1.0
	 *
	 * @param mixed  $level   Log level (e.g. 'info', 'warning', 'error').
	 * @param string $message Log message.
	 * @param array  $context Optional. Additional context for the message. Default empty array.
	 * @return null
	 */
	public function log( $level, $message, array $context = array() ) {
		$line = sprintf( '[%s] [%s] %s', date( 'Y-m-d H:i:s' ), strtoupper( $level ), $message );
		if ( ! empty( $context ) ) {
			$line .= ' ' . wp_json_encode( $context );
		}
		$line .= PHP_EOL;

		error_log( $line, 3, $this->path );
	}
}
