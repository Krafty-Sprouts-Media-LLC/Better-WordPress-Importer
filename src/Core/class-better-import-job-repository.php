<?php
/**
 * Import job persistence.
 *
 * @package Better_WordPress_Importer
 * @since 1.0.0
 */

defined( 'ABSPATH' ) || exit;

/**
 * Job repository.
 *
 * @since 1.0.0
 */
class Better_Import_Job_Repository {

	/**
	 * Jobs table name including prefix.
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
		$this->table = $wpdb->prefix . 'better_import_jobs';
	}

	/**
	 * Load a job by ID.
	 *
	 * @since 1.0.0
	 *
	 * @param int $job_id Job ID.
	 *
	 * @return Better_Import_Job|null
	 */
	public function get( $job_id ) {
		global $wpdb;

		$row = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$this->table} WHERE id = %d",
				absint( $job_id )
			)
		);

		if ( ! $row ) {
			return null;
		}

		return Better_Import_Job::from_row( $row );
	}

	/**
	 * Persist a job row.
	 *
	 * @since 1.0.0
	 *
	 * @param Better_Import_Job $job Job instance.
	 *
	 * @return true|WP_Error
	 */
	public function save( Better_Import_Job $job ) {
		global $wpdb;

		$now = current_time( 'mysql', true );
		$data = array(
			'status'            => $job->status,
			'phase'             => $job->phase,
			'phase_cursor'      => $job->phase_cursor,
			'file_path'         => $job->file_path,
			'attachment_id'     => $job->attachment_id,
			'total_posts'       => $job->total_posts,
			'total_comments'    => $job->total_comments,
			'total_terms'       => $job->total_terms,
			'total_users'       => $job->total_users,
			'total_media'       => $job->total_media,
			'scanned_posts'     => $job->scanned_posts,
			'scanned_comments'  => $job->scanned_comments,
			'scanned_terms'     => $job->scanned_terms,
			'scanned_users'     => $job->scanned_users,
			'scanned_media'     => $job->scanned_media,
			'imported_posts'    => $job->imported_posts,
			'imported_comments' => $job->imported_comments,
			'imported_terms'    => $job->imported_terms,
			'imported_users'    => $job->imported_users,
			'imported_media'    => $job->imported_media,
			'skipped_posts'     => $job->skipped_posts,
			'skipped_comments'  => $job->skipped_comments,
			'skipped_terms'     => $job->skipped_terms,
			'skipped_users'     => $job->skipped_users,
			'skipped_media'     => $job->skipped_media,
			'failed_items'      => $job->failed_items,
			'options'           => wp_json_encode( $job->options ),
			'preflight_data'    => wp_json_encode( $job->preflight_data ),
			'item_manifest'     => wp_json_encode( $job->item_manifest ),
			'mapping_state'     => wp_json_encode( $job->mapping_state ),
			'user_id'           => $job->user_id,
			'started_at'        => $job->started_at,
			'completed_at'      => $job->completed_at,
			'updated_at'        => $now,
		);

		$format = array(
			'%s', '%s', '%d', '%s', '%d',
			'%d', '%d', '%d', '%d', '%d',
			'%d', '%d', '%d', '%d', '%d',
			'%d', '%d', '%d', '%d', '%d',
			'%d', '%d', '%d', '%d', '%d',
			'%d', '%s', '%s', '%s', '%s',
			'%d', '%s', '%s', '%s',
		);

		if ( $job->id > 0 ) {
			$result = $wpdb->update(
				$this->table,
				$data,
				array( 'id' => $job->id ),
				$format,
				array( '%d' )
			);
		} else {
			$data['created_at'] = $now;
			$result             = $wpdb->insert(
				$this->table,
				$data,
				array_merge( $format, array( '%s' ) )
			);
			if ( false !== $result ) {
				$job->id         = (int) $wpdb->insert_id;
				$job->created_at = $now;
			}
		}

		if ( false === $result ) {
			return new WP_Error(
				'better_importer.job.save_failed',
				__( 'Could not save the import job.', 'better-wordpress-importer' )
			);
		}

		$job->updated_at = $now;

		return true;
	}
}
