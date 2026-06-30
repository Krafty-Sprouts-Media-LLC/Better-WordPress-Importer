<?php
/**
 * Import queue persistence — bulk seed and row access.
 *
 * @package Better_WordPress_Importer
 * @since 1.0.0
 */

defined( 'ABSPATH' ) || exit;

/**
 * Queue repository.
 *
 * @since 1.0.0
 */
class Better_Import_Queue_Repository {

	/**
	 * Rows inserted per SQL statement during manifest seeding.
	 *
	 * @since 1.0.0
	 */
	const INSERT_BATCH_SIZE = 250;

	/**
	 * Queue table name including prefix.
	 *
	 * @since 1.0.0
	 * @var string
	 */
	protected $table;

	/**
	 * Constructor.
	 *
	 * @since 1.0.0
	 */
	public function __construct() {
		global $wpdb;
		$this->table = $wpdb->prefix . 'better_import_queue';
	}

	/**
	 * Insert queue rows for every manifest entry.
	 *
	 * @since 1.0.0
	 *
	 * @param int   $job_id   Import job ID.
	 * @param array $manifest Compact manifest entries.
	 *
	 * @return true|WP_Error
	 */
	public function seed_from_manifest( $job_id, array $manifest ) {
		global $wpdb;

		$job_id = absint( $job_id );
		if ( $job_id <= 0 ) {
			return new WP_Error( 'better_importer.queue.invalid_job', __( 'Invalid import job ID.', 'better-wordpress-importer' ) );
		}

		if ( empty( $manifest ) ) {
			return new WP_Error( 'better_importer.queue.empty_manifest', __( 'The import manifest is empty.', 'better-wordpress-importer' ) );
		}

		$now   = current_time( 'mysql', true );
		$batch = array();

		foreach ( $manifest as $entry ) {
			$batch[] = array(
				'job_id'        => $job_id,
				'entity_index'  => isset( $entry['i'] ) ? (int) $entry['i'] : 0,
				'entity_type'   => isset( $entry['t'] ) ? sanitize_key( $entry['t'] ) : '',
				'old_entity_id' => isset( $entry['id'] ) ? sanitize_text_field( (string) $entry['id'] ) : '',
				'title'         => isset( $entry['title'] ) ? sanitize_text_field( (string) $entry['title'] ) : '',
				'status'        => 'pending',
				'step'          => 'create',
				'created_at'    => $now,
				'updated_at'    => $now,
			);

			if ( count( $batch ) >= self::INSERT_BATCH_SIZE ) {
				$result = $this->insert_batch( $batch );
				if ( is_wp_error( $result ) ) {
					return $result;
				}
				$batch = array();
			}
		}

		if ( ! empty( $batch ) ) {
			$result = $this->insert_batch( $batch );
			if ( is_wp_error( $result ) ) {
				return $result;
			}
		}

		return true;
	}

	/**
	 * Count queue rows for a job.
	 *
	 * @since 1.0.0
	 *
	 * @param int $job_id Import job ID.
	 *
	 * @return int
	 */
	public function count_for_job( $job_id ) {
		global $wpdb;

		return (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$this->table} WHERE job_id = %d",
				absint( $job_id )
			)
		);
	}

	/**
	 * Insert a batch of queue rows with one query.
	 *
	 * @since 1.0.0
	 *
	 * @param array<int, array<string, mixed>> $rows Rows to insert.
	 *
	 * @return true|WP_Error
	 */
	protected function insert_batch( array $rows ) {
		global $wpdb;

		if ( empty( $rows ) ) {
			return true;
		}

		$placeholders = array();
		$values       = array();

		foreach ( $rows as $row ) {
			$placeholders[] = '(%d, %d, %s, %s, %s, %s, %s, %s, %s)';
			$values[]       = $row['job_id'];
			$values[]       = $row['entity_index'];
			$values[]       = $row['entity_type'];
			$values[]       = $row['old_entity_id'];
			$values[]       = $row['title'];
			$values[]       = $row['status'];
			$values[]       = $row['step'];
			$values[]       = $row['created_at'];
			$values[]       = $row['updated_at'];
		}

		$sql = "INSERT INTO {$this->table}
			(job_id, entity_index, entity_type, old_entity_id, title, status, step, created_at, updated_at)
			VALUES " . implode( ', ', $placeholders );

		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- placeholders built above.
		$prepared = $wpdb->prepare( $sql, $values );
		$result   = $wpdb->query( $prepared );

		if ( false === $result ) {
			return new WP_Error(
				'better_importer.queue.insert_failed',
				__( 'Could not create import queue rows.', 'better-wordpress-importer' )
			);
		}

		return true;
	}
}
