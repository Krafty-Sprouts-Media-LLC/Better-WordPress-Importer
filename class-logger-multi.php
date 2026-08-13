<?php
/**
 * Composite logger for the WXR importer.
 *
 * @package WordPress_Importer
 * @since   2.1.0
 */

/**
 * Forwards every log call to a set of other loggers.
 *
 * Lets the importer be given a single WP_Importer_Logger instance (per
 * WXR_Importer::set_logger()) while actually logging to more than one
 * backend at once — e.g. the existing live HTML/CLI/SSE output plus the
 * new durable WP_Importer_Logger_File record.
 *
 * @since 2.1.0
 */
class WP_Importer_Logger_Multi extends WP_Importer_Logger {
	/**
	 * Loggers to forward log calls to.
	 *
	 * @since 2.1.0
	 * @var WP_Importer_Logger[]
	 */
	protected $loggers;

	/**
	 * Constructor.
	 *
	 * @since 2.1.0
	 *
	 * @param WP_Importer_Logger[] $loggers Loggers to forward every log call to.
	 */
	public function __construct( array $loggers ) {
		$this->loggers = $loggers;
	}

	/**
	 * Logs with an arbitrary level.
	 *
	 * @since 2.1.0
	 *
	 * @param mixed  $level   Log level (e.g. 'info', 'warning', 'error').
	 * @param string $message Log message.
	 * @param array  $context Optional. Additional context for the message. Default empty array.
	 * @return null
	 */
	public function log( $level, $message, array $context = array() ) {
		foreach ( $this->loggers as $logger ) {
			$logger->log( $level, $message, $context );
		}
	}
}
