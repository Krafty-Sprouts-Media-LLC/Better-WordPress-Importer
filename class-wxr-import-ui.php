<?php

class WXR_Import_UI {
	/**
	 * Should we fetch attachments?
	 *
	 * Set in {@see display_import_step}.
	 *
	 * @var bool
	 */
	protected $fetch_attachments = false;

	/**
	 * Constructor.
	 */
	public function __construct() {
		add_action( 'wxr_importer.ui.header', array( $this, 'show_updates_in_header' ) );
		add_filter( 'upload_mimes', array( $this, 'add_mime_type_xml' ) );
		add_action( 'wp_ajax_wxr-import-upload-chunk', array( $this, 'handle_chunk_upload' ) );
		add_action( 'wp_ajax_wxr-import-start', array( $this, 'handle_start_import' ) );
	}

	/**
	 * Add .xml files as supported format in the uploader.
	 *
	 * @param array $mimes Already supported mime types.
	 */
	public function add_mime_type_xml( $mimes ) {
		$mimes = array_merge( $mimes, array( 'xml' => 'application/xml' ) );

		return $mimes;
	}

	/**
	 * Directory chunked uploads are assembled in, under wp-content/uploads.
	 *
	 * @since 2.1.0
	 *
	 * @return string|WP_Error Absolute directory path, or error if it could not be created.
	 */
	protected function get_chunks_dir() {
		$upload_dir = wp_upload_dir();
		if ( ! empty( $upload_dir['error'] ) ) {
			return new WP_Error( 'wxr_importer.upload.dir_error', $upload_dir['error'] );
		}

		$dir = trailingslashit( $upload_dir['basedir'] ) . 'wxr-import-chunks';
		if ( ! wp_mkdir_p( $dir ) ) {
			return new WP_Error( 'wxr_importer.upload.dir_error', __( 'Could not create the upload directory.', 'wordpress-importer' ) );
		}

		$index = trailingslashit( $dir ) . 'index.php';
		if ( ! file_exists( $index ) ) {
			// Block directory listing; partially-assembled uploads live here.
			file_put_contents( $index, "<?php\n// Silence is golden.\n" );
		}

		return $dir;
	}

	/**
	 * Handle one chunk of a chunked WXR upload.
	 *
	 * Appends the chunk to a per-upload temporary file. On the final chunk,
	 * the assembled file is sideloaded into the media library and parsed
	 * for preliminary import info (authors, content counts), returned in
	 * the same response so the client can move straight to author mapping.
	 *
	 * @since 2.1.0
	 */
	public function handle_chunk_upload() {
		check_ajax_referer( 'wxr-import-upload-chunk' );

		if ( ! current_user_can( 'upload_files' ) ) {
			wp_send_json_error( array( 'message' => __( 'You do not have permission to upload files.', 'wordpress-importer' ) ), 403 );
		}

		$token = isset( $_POST['token'] ) ? sanitize_key( wp_unslash( $_POST['token'] ) ) : '';
		if ( ! preg_match( '/^[a-f0-9]{32,64}$/', $token ) ) {
			wp_send_json_error( array( 'message' => __( 'Invalid upload token.', 'wordpress-importer' ) ), 400 );
		}

		$chunk_index  = isset( $_POST['chunk_index'] ) ? (int) $_POST['chunk_index'] : -1;
		$total_chunks = isset( $_POST['total_chunks'] ) ? (int) $_POST['total_chunks'] : 0;
		$filename     = isset( $_POST['filename'] ) ? sanitize_file_name( wp_unslash( $_POST['filename'] ) ) : 'import.xml';

		$filetype = wp_check_filetype( $filename, array( 'xml' => 'application/xml' ) );
		if ( empty( $filetype['ext'] ) || 'xml' !== $filetype['ext'] ) {
			wp_send_json_error( array( 'message' => __( 'Please upload a WordPress export (WXR) file with a .xml extension.', 'wordpress-importer' ) ), 400 );
		}

		if ( $total_chunks < 1 || $chunk_index < 0 || $chunk_index >= $total_chunks ) {
			wp_send_json_error( array( 'message' => __( 'Invalid chunk index.', 'wordpress-importer' ) ), 400 );
		}

		if ( empty( $_FILES['chunk']['tmp_name'] ) || ! is_uploaded_file( $_FILES['chunk']['tmp_name'] ) ) {
			wp_send_json_error( array( 'message' => __( 'No chunk data was received.', 'wordpress-importer' ) ), 400 );
		}

		$dir = $this->get_chunks_dir();
		if ( is_wp_error( $dir ) ) {
			wp_send_json_error( array( 'message' => $dir->get_error_message() ) );
		}

		$partial_path = trailingslashit( $dir ) . $token . '.part';

		if ( 0 === $chunk_index && file_exists( $partial_path ) ) {
			// Fresh start (e.g. a retried upload) — discard any earlier attempt.
			unlink( $partial_path );
		}

		$chunk_contents = file_get_contents( $_FILES['chunk']['tmp_name'] );
		if ( false === $chunk_contents || false === file_put_contents( $partial_path, $chunk_contents, FILE_APPEND | LOCK_EX ) ) {
			wp_send_json_error( array( 'message' => __( 'Could not save the uploaded chunk.', 'wordpress-importer' ) ) );
		}

		if ( $chunk_index < $total_chunks - 1 ) {
			wp_send_json_success( array( 'received' => $chunk_index ) );
		}

		// Final chunk — finalize into a real attachment.
		if ( ! function_exists( 'wp_handle_sideload' ) ) {
			require_once ABSPATH . 'wp-admin/includes/file.php';
		}

		$file_array = array(
			'name'     => $filename,
			'tmp_name' => $partial_path,
		);
		$overrides = array(
			'test_form' => false,
			'test_type' => false,
			'action'    => 'wxr-import-upload-chunk',
		);

		// wp_handle_sideload() moves (not copies) the source file, so the
		// partial file is gone after this regardless of outcome.
		//
		// test_type is skipped on purpose, matching core's
		// wp_import_handle_upload(): WXR files contain HTML in post
		// content, so finfo often reports text/html (or text/xml) which
		// fails wp_check_filetype_and_ext() even when .xml is allowed via
		// upload_mimes. That is the "Sorry, you are not allowed to upload
		// this file type." error on the dashboard upload step.
		$sideloaded = wp_handle_sideload( $file_array, $overrides );
		if ( isset( $sideloaded['error'] ) ) {
			if ( file_exists( $partial_path ) ) {
				unlink( $partial_path );
			}
			wp_send_json_error( array( 'message' => $sideloaded['error'] ) );
		}

		$mime = ! empty( $sideloaded['type'] ) ? $sideloaded['type'] : 'application/xml';

		$attachment_id = wp_insert_attachment( array(
			'post_mime_type' => $mime,
			'post_title'     => $filename,
			'post_content'   => '',
			'post_status'    => 'private',
		), $sideloaded['file'] );

		if ( ! $attachment_id || is_wp_error( $attachment_id ) ) {
			wp_send_json_error( array( 'message' => __( 'Could not register the uploaded file.', 'wordpress-importer' ) ) );
		}

		$this->id = $attachment_id;
		$info = $this->get_data_for_attachment( $attachment_id );
		if ( is_wp_error( $info ) ) {
			wp_send_json_error( array( 'message' => $info->get_error_message() ) );
		}

		$users = array();
		foreach ( $info->users as $index => $user ) {
			$matched = $this->find_existing_user( $user );
			$users[] = array(
				'index'        => $index,
				'login'        => $user['data']['user_login'],
				'display_name' => $user['data']['display_name'],
				'email'        => isset( $user['data']['user_email'] ) ? $user['data']['user_email'] : '',
				'old_id'       => isset( $user['data']['ID'] ) ? (int) $user['data']['ID'] : 0,
				'matched_id'   => $matched ? (int) $matched->ID : 0,
			);
		}

		wp_send_json_success( array(
			'attachment_id'        => $attachment_id,
			'users'                => $users,
			'site_users'           => $this->get_site_users_for_mapping(),
			'allow_create_users'   => $this->allow_create_users(),
			'allow_fetch_attachments' => $this->allow_fetch_attachments(),
			'counts' => array(
				'posts'    => (int) $info->post_count,
				'media'    => (int) $info->media_count,
				'users'    => count( $info->users ),
				'comments' => (int) $info->comment_count,
				'terms'    => (int) $info->term_count,
			),
			'site' => array(
				'title'     => $info->title,
				'home'      => $info->home,
				'generator' => $info->generator,
				'version'   => $info->version,
			),
		) );
	}

	/**
	 * Save import settings (author mapping, attachment fetching) and hand
	 * back the URL the client should open an EventSource against to run
	 * the actual import — the AJAX counterpart to the old full-page-POST
	 * `display_import_step()`.
	 *
	 * @since 2.1.0
	 */
	public function handle_start_import() {
		check_ajax_referer( 'wxr-import-start' );

		$import_id = isset( $_POST['import_id'] ) ? (int) $_POST['import_id'] : 0;
		$attachment = $import_id ? get_post( $import_id ) : null;
		if ( ! $attachment || $attachment->post_type !== 'attachment' ) {
			wp_send_json_error( array( 'message' => __( 'Invalid import file ID.', 'wordpress-importer' ) ), 400 );
		}

		if ( ! current_user_can( 'read_post', $attachment->ID ) || ! current_user_can( 'upload_files' ) ) {
			wp_send_json_error( array( 'message' => __( 'You cannot access the selected media item.', 'wordpress-importer' ) ), 403 );
		}

		$this->id = $import_id;

		$args = wp_unslash( $_POST );
		$mapping = $this->get_author_mapping( $args );
		$fetch_attachments = ( ! empty( $args['fetch_attachments'] ) && $this->allow_fetch_attachments() );

		$settings = compact( 'mapping', 'fetch_attachments' );
		update_post_meta( $this->id, '_wxr_import_settings', $settings );

		$stream_args = array(
			'action' => 'wxr-import',
			'id'     => $this->id,
		);
		$url = add_query_arg( urlencode_deep( $stream_args ), admin_url( 'admin-ajax.php' ) );

		wp_send_json_success( array( 'stream_url' => $url ) );
	}

	/**
	 * Show an update notice in the importer header.
	 */
	public function show_updates_in_header() {
		// Check for updates too.
		$updates = get_plugin_updates();
		$basename = plugin_basename( __FILE__ );
		if ( empty( $updates[ $basename ] ) ) {
			return;
		}

		$message = sprintf(
			esc_html__( 'A new version of this importer is available. Please update to version %s to ensure compatibility with newer export files.', 'wordpress-importer' ),
			$updates[ $basename ]->update->new_version
		);

		$args = array(
			'action' => 'upgrade-plugin',
			'plugin' => $basename,
		);
		$url = add_query_arg( $args, self_admin_url( 'update.php' ) );
		$url = wp_nonce_url( $url, 'upgrade-plugin_' . $basename );
		$link = sprintf( '<a href="%s" class="button">%s</a>', $url, esc_html__( 'Update Now', 'wordpress-importer' ) );

		printf( '<div class="error"><p>%s</p><p>%s</p></div>', $message, $link );
	}

	/**
	 * Handle load event for the importer.
	 */
	public function on_load() {
		// Skip outputting the header on our import page, so we can handle it.
		$_GET['noheader'] = true;
	}

	/**
	 * Render the import page.
	 */
	public function dispatch() {
		require __DIR__ . '/templates/app.php';
	}

	/**
	 * Render the importer header.
	 */
	protected function render_header() {
		require __DIR__ . '/templates/header.php';
	}

	/**
	 * Render the importer footer.
	 */
	protected function render_footer() {
		require __DIR__ . '/templates/footer.php';
	}

	/**
	 * Get preliminary data for an import file.
	 *
	 * This is a quick pre-parse to verify the file and grab authors from it.
	 *
	 * @param int $id Media item ID.
	 * @return WXR_Import_Info|WP_Error Import info instance on success, error otherwise.
	 */
	protected function get_data_for_attachment( $id ) {
		$existing = get_post_meta( $id, '_wxr_import_info' );
		if ( ! empty( $existing ) ) {
			$data = $existing[0];
			$this->authors = $data->users;
			$this->version = $data->version;
			return $data;
		}

		$file = get_attached_file( $id );

		$importer = $this->get_importer();
		$data = $importer->get_preliminary_information( $file );
		if ( is_wp_error( $data ) ) {
			return $data;
		}

		// Cache the information on the upload
		if ( ! update_post_meta( $id, '_wxr_import_info', $data ) ) {
			return new WP_Error(
				'wxr_importer.upload.failed_save_meta',
				__( 'Could not cache information on the import.', 'wordpress-importer' ),
				compact( 'id' )
			);
		}

		$this->authors = $data->users;
		$this->version = $data->version;

		return $data;
	}

	/**
	 * Run an import, and send an event-stream response.
	 *
	 * Streams logs and success messages to the browser to allow live status
	 * and updates.
	 */
	public function stream_import() {
		// Turn off PHP output compression
		$previous = error_reporting( error_reporting() ^ E_WARNING );
		ini_set( 'output_buffering', 'off' );
		ini_set( 'zlib.output_compression', false );
		error_reporting( $previous );

		if ( $GLOBALS['is_nginx'] ) {
			// Setting this header instructs Nginx to disable fastcgi_buffering
			// and disable gzip for this request.
			header( 'X-Accel-Buffering: no' );
			header( 'Content-Encoding: none' );
		}

		// Start the event stream.
		header( 'Content-Type: text/event-stream' );

		$this->id = wp_unslash( (int) $_REQUEST['id'] );
		$settings = get_post_meta( $this->id, '_wxr_import_settings', true );
		if ( empty( $settings ) ) {
			// Tell the browser to stop reconnecting.
			status_header( 204 );
			exit;
		}

		// 2KB padding for IE
		echo ':' . str_repeat( ' ', 2048 ) . "\n\n";

		// Since ignore_user_abort() below keeps a run alive server-side
		// even after the browser disconnects, the browser's EventSource
		// auto-reconnecting would otherwise start a second, fully
		// concurrent pass over the same file — racing post_exists()
		// checks against the still-running first pass. Guard against that
		// with a simple heartbeat lock instead.
		if ( ! $this->acquire_import_lock() ) {
			echo "event: log\n";
			echo 'data: ' . wp_json_encode( array(
				'level'   => 'notice',
				'message' => __( 'This import is already running from another connection; not starting a duplicate pass.', 'wordpress-importer' ),
			) ) . "\n\n";
			flush();
			exit;
		}

		// Time to run the import! Keep running server-side even if the
		// browser disconnects (tab closed, network drop) instead of dying
		// mid-post — there's no way to resume a partial WXR read, so an
		// import that's already running should finish uninterrupted.
		ignore_user_abort( true );
		set_time_limit( 0 );

		// Ensure we're not buffered.
		wp_ob_end_flush_all();
		flush();

		$mapping = $settings['mapping'];
		$this->fetch_attachments = (bool) $settings['fetch_attachments'];

		$importer = $this->get_importer();
		if ( ! empty( $mapping['mapping'] ) ) {
			$importer->set_user_mapping( $mapping['mapping'] );
		}
		if ( ! empty( $mapping['slug_overrides'] ) ) {
			$importer->set_user_slug_overrides( $mapping['slug_overrides'] );
		}

		// Are we allowed to create users?
		if ( ! $this->allow_create_users() ) {
			add_filter( 'wxr_importer.pre_process.user', '__return_null' );
		}

		// Keep track of our progress. Each outcome (created / skipped as a
		// duplicate / failed) is wired to its own status so the end-of-run
		// summary can report them separately instead of lumping failures
		// in with successes.
		add_action( 'wxr_importer.processed.post', array( $this, 'imported_post' ), 10, 2 );
		add_action( 'wxr_importer.process_failed.post', array( $this, 'failed_post' ), 10, 2 );
		add_action( 'wxr_importer.process_already_imported.post', array( $this, 'already_imported_post' ), 10, 2 );
		add_action( 'wxr_importer.process_skipped.post', array( $this, 'already_imported_post' ), 10, 2 );
		add_action( 'wxr_importer.processed.comment', array( $this, 'imported_comment' ) );
		add_action( 'wxr_importer.process_already_imported.comment', array( $this, 'already_imported_comment' ) );
		add_action( 'wxr_importer.processed.term', array( $this, 'imported_term' ) );
		add_action( 'wxr_importer.process_failed.term', array( $this, 'failed_term' ) );
		add_action( 'wxr_importer.process_already_imported.term', array( $this, 'already_imported_term' ) );
		add_action( 'wxr_importer.processed.user', array( $this, 'imported_user' ) );
		add_action( 'wxr_importer.process_failed.user', array( $this, 'failed_user' ) );

		// Clean up some memory
		unset( $settings );

		// Flush once more.
		flush();

		$file = get_attached_file( $this->id );
		$err = $importer->import( $file );

		// Remove the settings to stop future reconnects, and release the
		// run lock now that this pass has finished.
		delete_post_meta( $this->id, '_wxr_import_settings' );
		$this->release_import_lock();

		// Let the browser know we're done.
		$complete = array(
			'action'  => 'complete',
			'error'   => false,
			'summary' => $this->summary,
		);
		if ( is_wp_error( $err ) ) {
			$complete['error'] = $err->get_error_message();
		}

		$this->emit_sse_message( $complete );
		exit;
	}

	/**
	 * Get the importer instance.
	 *
	 * @return WXR_Importer
	 */
	protected function get_importer() {
		$importer = new WXR_Importer( $this->get_import_options() );
		$logger = new WP_Importer_Logger_Multi( array(
			new WP_Importer_Logger_ServerSentEvents(),
			new WP_Importer_Logger_File(),
		) );
		$importer->set_logger( $logger );

		return $importer;
	}

	/**
	 * Get options for the importer.
	 *
	 * @return array Options to pass to WXR_Importer::__construct
	 */
	protected function get_import_options() {
		$options = array(
			'fetch_attachments' => $this->fetch_attachments,
			'default_author'    => get_current_user_id(),
		);

		/**
		 * Filter the importer options used in the admin UI.
		 *
		 * @param array $options Options to pass to WXR_Importer::__construct
		 */
		return apply_filters( 'wxr_importer.admin.import_options', $options );
	}

	/**
	 * Decide whether or not the importer should attempt to download attachment files.
	 * Default is true, can be filtered via import_allow_fetch_attachments. The choice
	 * made at the import options screen must also be true, false here hides that checkbox.
	 *
	 * @return bool True if downloading attachments is allowed
	 */
	protected function allow_fetch_attachments() {
		return apply_filters( 'import_allow_fetch_attachments', true );
	}

	/**
	 * Decide whether or not the importer is allowed to create users.
	 * Default is true, can be filtered via import_allow_create_users
	 *
	 * @return bool True if creating users is allowed
	 */
	protected function allow_create_users() {
		return apply_filters( 'import_allow_create_users', true );
	}

	/**
	 * Find a destination-site user that matches a WXR author.
	 *
	 * Email is checked first (unique, survives a login rename), then
	 * user_login, then nicename.
	 *
	 * @since 2.1.2
	 *
	 * @param array $wxr_user A WXR user item with a `data` key.
	 * @return WP_User|null
	 */
	protected function find_existing_user( array $wxr_user ) {
		$data  = isset( $wxr_user['data'] ) && is_array( $wxr_user['data'] ) ? $wxr_user['data'] : array();
		$email = isset( $data['user_email'] ) ? $data['user_email'] : '';
		$login = isset( $data['user_login'] ) ? $data['user_login'] : '';

		if ( is_email( $email ) ) {
			$user = get_user_by( 'email', $email );
			if ( $user ) {
				return $user;
			}
		}

		if ( $login ) {
			$user = get_user_by( 'login', $login );
			if ( $user ) {
				return $user;
			}

			$user = get_user_by( 'slug', $login );
			if ( $user ) {
				return $user;
			}
		}

		return null;
	}

	/**
	 * Destination-site users for the author-mapping dropdown.
	 *
	 * @since 2.1.2
	 *
	 * @return array[] {
	 *     @type int    $id
	 *     @type string $login
	 *     @type string $display_name
	 * }
	 */
	protected function get_site_users_for_mapping() {
		$users = get_users(
			array(
				'orderby' => 'display_name',
				'order'   => 'ASC',
				'fields'  => array( 'ID', 'user_login', 'display_name' ),
			)
		);

		$out = array();
		foreach ( $users as $user ) {
			$out[] = array(
				'id'           => (int) $user->ID,
				'login'        => $user->user_login,
				'display_name' => $user->display_name,
			);
		}

		return $out;
	}

	/**
	 * Get mapping data from request data.
	 *
	 * Parses form request data into an internally usable mapping format.
	 *
	 * @param array $args Raw (UNSLASHED) POST data to parse.
	 * @return array Map containing `mapping` and `slug_overrides` keys.
	 */
	protected function get_author_mapping( $args ) {
		if ( ! isset( $args['imported_authors'] ) ) {
			return array(
				'mapping'        => array(),
				'slug_overrides' => array(),
			);
		}

		$map        = isset( $args['user_map'] ) ? (array) $args['user_map'] : array();
		$new_users  = isset( $args['user_new'] ) ? $args['user_new'] : array();
		$old_ids    = isset( $args['imported_author_ids'] ) ? (array) $args['imported_author_ids'] : array();

		// Store the actual map.
		$mapping = array();
		$slug_overrides = array();

		foreach ( (array) $args['imported_authors'] as $i => $old_login ) {
			$old_id = isset( $old_ids[ $i ] ) ? (int) $old_ids[ $i ] : false;

			if ( ! empty( $map[ $i ] ) ) {
				$user = get_user_by( 'id', (int) $map[ $i ] );

				if ( isset( $user->ID ) ) {
					$mapping[] = array(
						'old_slug' => $old_login,
						'old_id'   => $old_id,
						'new_id'   => $user->ID,
					);
				}
			} elseif ( ! empty( $new_users[ $i ] ) ) {
				if ( $new_users[ $i ] !== $old_login ) {
					$slug_overrides[ $old_login ] = $new_users[ $i ];
				}
			}
		}

		return compact( 'mapping', 'slug_overrides' );
	}

	/**
	 * Emit a Server-Sent Events message.
	 *
	 * @param mixed $data Data to be JSON-encoded and sent in the message.
	 */
	protected function emit_sse_message( $data ) {
		$this->maybe_refresh_import_lock();

		echo "event: message\n";
		echo 'data: ' . wp_json_encode( $data ) . "\n\n";

		// Extra padding.
		echo ':' . str_repeat( ' ', 2048 ) . "\n\n";

		flush();
	}

	/**
	 * How long an import lock heartbeat is considered valid without a refresh.
	 *
	 * @since 2.1.0
	 * @var int
	 */
	const IMPORT_LOCK_TIMEOUT = 5 * MINUTE_IN_SECONDS;

	/**
	 * Timestamp the import lock was last refreshed, to throttle DB writes.
	 *
	 * @since 2.1.0
	 * @var int
	 */
	protected $import_lock_refreshed_at = 0;

	/**
	 * Try to acquire the run lock for the current import.
	 *
	 * Guards against a second `stream_import()` request (e.g. a browser's
	 * EventSource auto-reconnecting) running a fully concurrent pass over
	 * the same file while an earlier pass is still alive server-side. A
	 * lock with no heartbeat for IMPORT_LOCK_TIMEOUT is treated as
	 * abandoned (crashed process) and can be taken over.
	 *
	 * @since 2.1.0
	 *
	 * @return bool True if the lock was acquired, false if another run holds it.
	 */
	protected function acquire_import_lock() {
		$now = time();

		if ( add_post_meta( $this->id, '_wxr_import_running', $now, true ) ) {
			$this->import_lock_refreshed_at = $now;
			return true;
		}

		$existing = (int) get_post_meta( $this->id, '_wxr_import_running', true );
		if ( $existing && ( $now - $existing ) < self::IMPORT_LOCK_TIMEOUT ) {
			return false;
		}

		// Stale lock (no heartbeat within the timeout) — take it over.
		update_post_meta( $this->id, '_wxr_import_running', $now );
		$this->import_lock_refreshed_at = $now;
		return true;
	}

	/**
	 * Refresh the import lock's heartbeat, throttled to avoid a DB write
	 * on every single progress event.
	 *
	 * @since 2.1.0
	 */
	protected function maybe_refresh_import_lock() {
		$now = time();
		if ( ( $now - $this->import_lock_refreshed_at ) < MINUTE_IN_SECONDS ) {
			return;
		}

		update_post_meta( $this->id, '_wxr_import_running', $now );
		$this->import_lock_refreshed_at = $now;
	}

	/**
	 * Release the import lock.
	 *
	 * @since 2.1.0
	 */
	protected function release_import_lock() {
		delete_post_meta( $this->id, '_wxr_import_running' );
	}

	/**
	 * Running tally of import outcomes, reported in the end-of-run summary.
	 *
	 * @since 2.1.0
	 * @var array
	 */
	protected $summary = array(
		'created' => 0,
		'skipped' => 0,
		'failed'  => 0,
	);

	/**
	 * Send a progress delta and record it in the run summary.
	 *
	 * @since 2.1.0
	 *
	 * @param string $type   Progress category (posts, media, comments, terms, users).
	 * @param string $status One of 'created', 'skipped', 'failed'.
	 */
	protected function track_progress( $type, $status ) {
		$this->summary[ $status ]++;

		$this->emit_sse_message( array(
			'action' => 'updateDelta',
			'type'   => $type,
			'status' => $status,
			'delta'  => 1,
		));
	}

	/**
	 * Send message when a post has been imported.
	 *
	 * @param int $id Post ID.
	 * @param array $data Post data saved to the DB.
	 */
	public function imported_post( $id, $data ) {
		$this->track_progress( ( $data['post_type'] === 'attachment' ) ? 'media' : 'posts', 'created' );
	}

	/**
	 * Send message when a post failed to import.
	 *
	 * @param WP_Error $error Error encountered while importing.
	 * @param array    $data  Raw post data that failed to import.
	 */
	public function failed_post( $error, $data ) {
		$this->track_progress( ( $data['post_type'] === 'attachment' ) ? 'media' : 'posts', 'failed' );
	}

	/**
	 * Send message when a post is marked as already imported.
	 *
	 * @param array $data Post data saved to the DB.
	 */
	public function already_imported_post( $data ) {
		$this->track_progress( ( $data['post_type'] === 'attachment' ) ? 'media' : 'posts', 'skipped' );
	}

	/**
	 * Send message when a comment has been imported.
	 */
	public function imported_comment() {
		$this->track_progress( 'comments', 'created' );
	}

	/**
	 * Send message when a comment is marked as already imported.
	 */
	public function already_imported_comment() {
		$this->track_progress( 'comments', 'skipped' );
	}

	/**
	 * Send message when a term has been imported.
	 */
	public function imported_term() {
		$this->track_progress( 'terms', 'created' );
	}

	/**
	 * Send message when a term failed to import.
	 */
	public function failed_term() {
		$this->track_progress( 'terms', 'failed' );
	}

	/**
	 * Send message when a term is marked as already imported.
	 */
	public function already_imported_term() {
		$this->track_progress( 'terms', 'skipped' );
	}

	/**
	 * Send message when a user has been imported.
	 */
	public function imported_user() {
		$this->track_progress( 'users', 'created' );
	}

	/**
	 * Send message when a user failed to import.
	 */
	public function failed_user() {
		$this->track_progress( 'users', 'failed' );
	}
}
