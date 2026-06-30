<?php
/**
 * Plugin activation — creates custom tables for the 1.0 import engine.
 *
 * @package Better_WordPress_Importer
 * @since 1.0.0
 */

defined( 'ABSPATH' ) || exit;

/**
 * Database installation and migration helpers.
 *
 * @since 1.0.0
 */
class Better_Install {

	/**
	 * Current schema version.
	 *
	 * @since 1.0.0
	 */
	const DB_VERSION = '1.0.0';

	/**
	 * Create or upgrade import database tables.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public static function install_tables() {
		global $wpdb;

		$charset_collate = $wpdb->get_charset_collate();
		$jobs_table      = $wpdb->prefix . 'better_import_jobs';
		$queue_table     = $wpdb->prefix . 'better_import_queue';
		$log_table       = $wpdb->prefix . 'better_import_log';

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$sql_jobs = "CREATE TABLE {$jobs_table} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			status varchar(20) NOT NULL DEFAULT 'created',
			phase varchar(30) NOT NULL DEFAULT '',
			phase_cursor int(10) unsigned NOT NULL DEFAULT 0,
			file_path varchar(500) NOT NULL,
			attachment_id bigint(20) unsigned NOT NULL DEFAULT 0,
			total_posts int(10) unsigned NOT NULL DEFAULT 0,
			total_comments int(10) unsigned NOT NULL DEFAULT 0,
			total_terms int(10) unsigned NOT NULL DEFAULT 0,
			total_users int(10) unsigned NOT NULL DEFAULT 0,
			total_media int(10) unsigned NOT NULL DEFAULT 0,
			scanned_posts int(10) unsigned NOT NULL DEFAULT 0,
			scanned_comments int(10) unsigned NOT NULL DEFAULT 0,
			scanned_terms int(10) unsigned NOT NULL DEFAULT 0,
			scanned_users int(10) unsigned NOT NULL DEFAULT 0,
			scanned_media int(10) unsigned NOT NULL DEFAULT 0,
			imported_posts int(10) unsigned NOT NULL DEFAULT 0,
			imported_comments int(10) unsigned NOT NULL DEFAULT 0,
			imported_terms int(10) unsigned NOT NULL DEFAULT 0,
			imported_users int(10) unsigned NOT NULL DEFAULT 0,
			imported_media int(10) unsigned NOT NULL DEFAULT 0,
			skipped_posts int(10) unsigned NOT NULL DEFAULT 0,
			skipped_comments int(10) unsigned NOT NULL DEFAULT 0,
			skipped_terms int(10) unsigned NOT NULL DEFAULT 0,
			skipped_users int(10) unsigned NOT NULL DEFAULT 0,
			skipped_media int(10) unsigned NOT NULL DEFAULT 0,
			failed_items int(10) unsigned NOT NULL DEFAULT 0,
			options longtext DEFAULT NULL,
			preflight_data longtext DEFAULT NULL,
			item_manifest longtext DEFAULT NULL,
			mapping_state longtext DEFAULT NULL,
			user_id bigint(20) unsigned NOT NULL DEFAULT 0,
			started_at datetime DEFAULT NULL,
			completed_at datetime DEFAULT NULL,
			created_at datetime NOT NULL,
			updated_at datetime NOT NULL,
			PRIMARY KEY  (id),
			KEY status (status),
			KEY user_id (user_id)
		) {$charset_collate};";

		$sql_queue = "CREATE TABLE {$queue_table} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			job_id bigint(20) unsigned NOT NULL,
			entity_index int(10) unsigned NOT NULL,
			entity_type varchar(20) NOT NULL,
			old_entity_id varchar(100) NOT NULL DEFAULT '',
			new_entity_id bigint(20) unsigned DEFAULT NULL,
			status varchar(20) NOT NULL DEFAULT 'pending',
			step varchar(30) NOT NULL DEFAULT 'create',
			step_cursor int(10) unsigned NOT NULL DEFAULT 0,
			step_total int(10) unsigned NOT NULL DEFAULT 0,
			parsed_payload longblob DEFAULT NULL,
			payload_hash varchar(64) NOT NULL DEFAULT '',
			title varchar(500) DEFAULT NULL,
			attempts tinyint(3) unsigned NOT NULL DEFAULT 0,
			error_message text DEFAULT NULL,
			error_code varchar(50) DEFAULT NULL,
			last_error_at datetime DEFAULT NULL,
			created_at datetime NOT NULL,
			updated_at datetime NOT NULL,
			PRIMARY KEY  (id),
			KEY job_entity (job_id, entity_index),
			KEY job_status (job_id, status),
			KEY job_status_step (job_id, status, step)
		) {$charset_collate};";

		$sql_log = "CREATE TABLE {$log_table} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			job_id bigint(20) unsigned NOT NULL,
			level varchar(10) NOT NULL DEFAULT 'info',
			message text NOT NULL,
			entity_index int(10) unsigned DEFAULT NULL,
			context longtext DEFAULT NULL,
			created_at datetime NOT NULL,
			PRIMARY KEY  (id),
			KEY job_id (job_id),
			KEY job_level (job_id, level)
		) {$charset_collate};";

		dbDelta( $sql_jobs );
		dbDelta( $sql_queue );
		dbDelta( $sql_log );

		update_option( 'better_importer_db_version', self::DB_VERSION );

		self::maybe_flag_legacy_data();
	}

	/**
	 * Flag legacy experimental tables without dropping them.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public static function maybe_flag_legacy_data() {
		if ( get_option( 'wxr_importer_db_version' ) && ! get_option( 'better_importer_legacy_flagged' ) ) {
			update_option( 'better_importer_legacy_flagged', true );
			update_option( 'better_importer_legacy_detected_at', current_time( 'mysql', true ) );
		}
	}

	/**
	 * Run install on plugin activation.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public static function activate() {
		self::install_tables();
	}
}
