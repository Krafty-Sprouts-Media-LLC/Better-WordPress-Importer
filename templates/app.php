<?php
/**
 * Single-page import app: upload, review authors, and run the import
 * without a full page reload between steps.
 *
 * @package WordPress_Importer
 * @since   2.1.0
 */

$max_upload_size = apply_filters( 'import_upload_size_limit', wp_max_upload_size() );

$script_data = array(
	'ajaxUrl' => admin_url( 'admin-ajax.php' ),
	'chunkUploadNonce' => wp_create_nonce( 'wxr-import-upload-chunk' ),
	'startImportNonce' => wp_create_nonce( 'wxr-import-start' ),
	'chunkSize' => 8 * MB_IN_BYTES,
	'maxUploadSize' => (int) $max_upload_size,
	'allowCreateUsers' => $this->allow_create_users(),
	'defaultRole' => get_option( 'default_role' ),
	'strings' => array(
		'uploading'        => __( 'Uploading…', 'wordpress-importer' ),
		'uploadComplete'   => __( 'Upload complete', 'wordpress-importer' ),
		'uploadFailed'     => __( 'Upload failed:', 'wordpress-importer' ),
		'fileTooLarge'     => sprintf(
			/* translators: %s: maximum upload size, e.g. "512 MB" */
			__( 'That file is larger than the %s limit for a single upload.', 'wordpress-importer' ),
			size_format( $max_upload_size )
		),
		'notXml'           => __( 'Please choose a WordPress export (.xml) file.', 'wordpress-importer' ),
		'createNewUser'    => __( 'Create new user', 'wordpress-importer' ),
		/* translators: %s: existing username */
		'mapToExisting'    => __( 'Map to existing: %s', 'wordpress-importer' ),
		'complete'         => __( 'Import complete!', 'wordpress-importer' ),
		/* translators: 1: number created, 2: number skipped as duplicates, 3: number failed. */
		'summary'          => __( '%1$d created, %2$d skipped as duplicates, %3$d failed.', 'wordpress-importer' ),
		'errorPrefix'      => __( 'The import could not finish:', 'wordpress-importer' ),
		'connectionLost'   => __( 'Having trouble staying connected to the import. It should keep running in the background — this page will update again once the connection recovers. If this continues, check wp-content/wxr-importer-debug.log for the latest status.', 'wordpress-importer' ),
		'reconnected'      => __( 'Reconnected — resuming live updates.', 'wordpress-importer' ),
		'importing'        => __( 'Importing your content…', 'wordpress-importer' ),
		'startFailed'      => __( 'Could not start the import:', 'wordpress-importer' ),
		'noAuthors'        => __( 'This export doesn’t reference any authors that need mapping.', 'wordpress-importer' ),
		/* translators: %d: number of authors referenced in the export file (exactly one). */
		'authorsLedeOne'   => __( 'This export references %d author. Match them to an existing account, or create a new one.', 'wordpress-importer' ),
		/* translators: %d: number of authors referenced in the export file (more than one). */
		'authorsLedeMany'  => __( 'This export references %d authors. Match each to an existing account, or create a new one.', 'wordpress-importer' ),
	),
	'types' => array(
		'posts'    => __( 'Posts & pages', 'wordpress-importer' ),
		'media'    => __( 'Media', 'wordpress-importer' ),
		'users'    => __( 'Users', 'wordpress-importer' ),
		'comments' => __( 'Comments', 'wordpress-importer' ),
		'terms'    => __( 'Categories & tags', 'wordpress-importer' ),
	),
);

wp_enqueue_style( 'wxr-importer-app', plugins_url( 'assets/app.css', dirname( __FILE__ ) ), array(), '2.1.3' );
wp_enqueue_script( 'wxr-importer-app', plugins_url( 'assets/app.js', dirname( __FILE__ ) ), array(), '2.1.3', true );
wp_localize_script( 'wxr-importer-app', 'wxrImporterApp', $script_data );

$this->render_header();
?>

<h1><?php esc_html_e( 'Import content', 'wordpress-importer' ) ?></h1>

<div class="wxrimp-stepper" role="tablist" aria-label="<?php esc_attr_e( 'Import steps', 'wordpress-importer' ) ?>">
	<div class="wxrimp-step is-active" id="wxrimp-step-indicator-1"><span class="wxrimp-step-num"><span>1</span></span><span class="wxrimp-step-label"><?php esc_html_e( 'Choose file', 'wordpress-importer' ) ?></span></div>
	<div class="wxrimp-step-divider"></div>
	<div class="wxrimp-step" id="wxrimp-step-indicator-2"><span class="wxrimp-step-num"><span>2</span></span><span class="wxrimp-step-label"><?php esc_html_e( 'Review authors', 'wordpress-importer' ) ?></span></div>
	<div class="wxrimp-step-divider"></div>
	<div class="wxrimp-step" id="wxrimp-step-indicator-3"><span class="wxrimp-step-num"><span>3</span></span><span class="wxrimp-step-label"><?php esc_html_e( 'Import', 'wordpress-importer' ) ?></span></div>
</div>

<div class="wxrimp-card">
	<div class="wxrimp-card-body">

		<!-- Screen 1: upload -->
		<section class="wxrimp-screen is-visible" id="wxrimp-screen-1">
			<h2><?php esc_html_e( 'Choose an export file', 'wordpress-importer' ) ?></h2>
			<p class="wxrimp-lede"><?php esc_html_e( 'Drop a WordPress export (WXR) file, or browse for one. Large files upload in chunks, so a dropped connection won’t force you to start over.', 'wordpress-importer' ) ?></p>

			<div class="wxrimp-dropzone" id="wxrimp-dropzone" tabindex="0" role="button" aria-label="<?php esc_attr_e( 'Choose a WXR file to upload', 'wordpress-importer' ) ?>">
				<div class="wxrimp-dropzone-icon" aria-hidden="true">
					<svg width="20" height="20" viewBox="0 0 24 24" fill="none"><path d="M12 3v12m0-12 4.5 4.5M12 3 7.5 7.5M4 17v2a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2v-2" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg>
				</div>
				<strong><?php esc_html_e( 'Drop your .xml export here', 'wordpress-importer' ) ?></strong>
				<span><?php echo esc_html( sprintf(
					/* translators: %s: maximum upload size, e.g. "512 MB" */
					__( 'or click to browse — up to %s, resumable', 'wordpress-importer' ),
					size_format( $max_upload_size )
				) ) ?></span>
				<input type="file" id="wxrimp-file-input" accept=".xml" class="screen-reader-text" tabindex="-1" />
			</div>

			<div class="wxrimp-file-card" id="wxrimp-file-card" hidden>
				<div class="wxrimp-ficon" aria-hidden="true"><svg width="18" height="18" viewBox="0 0 24 24" fill="none"><path d="M6 2h9l5 5v13a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2Z" stroke="currentColor" stroke-width="1.8" stroke-linejoin="round"/><path d="M15 2v5h5" stroke="currentColor" stroke-width="1.8" stroke-linejoin="round"/></svg></div>
				<div class="wxrimp-finfo">
					<div class="wxrimp-fname" id="wxrimp-file-name"></div>
					<div class="wxrimp-fmeta" id="wxrimp-file-meta"></div>
					<div class="wxrimp-progress-track"><div class="wxrimp-progress-fill" id="wxrimp-upload-fill"></div></div>
					<div class="wxrimp-chunk-note" id="wxrimp-chunk-note" role="status" aria-live="polite"></div>
				</div>
			</div>

			<div class="wxrimp-actions">
				<button class="button button-primary" id="wxrimp-to-step2" disabled><?php esc_html_e( 'Continue', 'wordpress-importer' ) ?></button>
			</div>
		</section>

		<!-- Screen 2: author mapping -->
		<section class="wxrimp-screen" id="wxrimp-screen-2">
			<h2><?php esc_html_e( 'Review authors', 'wordpress-importer' ) ?></h2>
			<p class="wxrimp-lede" id="wxrimp-authors-lede"></p>

			<div id="wxrimp-authors"></div>

			<label class="wxrimp-checkbox-row" id="wxrimp-fetch-attachments-row" hidden>
				<input type="checkbox" id="wxrimp-fetch-attachments" checked>
				<span><strong><?php esc_html_e( 'Download and import file attachments', 'wordpress-importer' ) ?></strong><p><?php esc_html_e( 'Media referenced in the export will be fetched from the original site.', 'wordpress-importer' ) ?></p></span>
			</label>

			<div class="wxrimp-actions wxrimp-actions-split">
				<button class="button" id="wxrimp-to-step1-back"><?php esc_html_e( 'Back', 'wordpress-importer' ) ?></button>
				<button class="button button-primary" id="wxrimp-to-step3"><?php esc_html_e( 'Start import', 'wordpress-importer' ) ?></button>
			</div>
		</section>

		<!-- Screen 3: import -->
		<section class="wxrimp-screen" id="wxrimp-screen-3">
			<h2><?php esc_html_e( 'Importing', 'wordpress-importer' ) ?></h2>
			<p class="wxrimp-lede"><?php esc_html_e( 'Safe to close this tab or switch apps — the import keeps running.', 'wordpress-importer' ) ?></p>

			<div class="wxrimp-status-banner" id="wxrimp-status-banner" role="status" aria-live="polite">
				<span class="wxrimp-spinner" aria-hidden="true"></span>
				<span id="wxrimp-status-text"></span>
			</div>
			<p id="wxrimp-summary" class="wxrimp-summary-inline" role="status" aria-live="polite" hidden></p>

			<div class="wxrimp-stat-grid" id="wxrimp-stat-grid"></div>

			<div class="wxrimp-log-panel" id="wxrimp-log-panel">
				<button class="wxrimp-log-toggle" id="wxrimp-log-toggle" aria-expanded="false" aria-controls="wxrimp-log-body">
					<span><?php esc_html_e( 'Activity log', 'wordpress-importer' ) ?></span>
					<svg class="wxrimp-log-chevron" width="14" height="14" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M6 9l6 6 6-6" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
				</button>
				<table class="wxrimp-log-table" id="wxrimp-log-body">
					<caption class="screen-reader-text"><?php esc_html_e( 'Import event log', 'wordpress-importer' ) ?></caption>
					<tbody></tbody>
				</table>
			</div>
		</section>

	</div>
</div>

<?php
$this->render_footer();
