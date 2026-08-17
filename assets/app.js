/**
 * Single-page WXR import app: chunked upload, author mapping, live import.
 *
 * @package WordPress_Importer
 * @since   2.1.0
 */
( function () {
	'use strict';

	var cfg = window.wxrImporterApp;
	var $ = function ( id ) { return document.getElementById( id ); };

	var state = {
		file: null,
		token: null,
		attachmentId: null,
		users: [],
		siteUsers: [],
		counts: {},
		allowFetchAttachments: false,
	};

	var complete = {};

	function setStep( n ) {
		[ 1, 2, 3 ].forEach( function ( i ) {
			var indicator = $( 'wxrimp-step-indicator-' + i );
			indicator.classList.toggle( 'is-active', i === n );
			indicator.classList.toggle( 'is-done', i < n );
			$( 'wxrimp-screen-' + i ).classList.toggle( 'is-visible', i === n );
		} );
	}

	function genToken() {
		var bytes = new Uint8Array( 16 );
		window.crypto.getRandomValues( bytes );
		return Array.prototype.map.call( bytes, function ( b ) {
			return b.toString( 16 ).padStart( 2, '0' );
		} ).join( '' );
	}

	function formatBytes( bytes ) {
		if ( bytes < 1024 ) {
			return bytes + ' B';
		}
		var units = [ 'KB', 'MB', 'GB' ];
		var i = -1;
		do {
			bytes /= 1024;
			i++;
		} while ( bytes >= 1024 && i < units.length - 1 );
		return bytes.toFixed( 1 ) + ' ' + units[ i ];
	}

	function setSpinnerVisible( visible ) {
		var spinner = $( 'wxrimp-status-banner' ).querySelector( '.wxrimp-spinner' );
		spinner.style.display = visible ? '' : 'none';
	}

	/* ---------- Screen 1: chunked upload ---------- */

	function chooseFile( file ) {
		if ( ! /\.xml$/i.test( file.name ) ) {
			window.alert( cfg.strings.notXml );
			return;
		}
		if ( cfg.maxUploadSize && file.size > cfg.maxUploadSize ) {
			window.alert( cfg.strings.fileTooLarge );
			return;
		}

		state.file = file;
		state.token = genToken();

		$( 'wxrimp-file-card' ).hidden = false;
		$( 'wxrimp-file-name' ).textContent = file.name;
		$( 'wxrimp-to-step2' ).disabled = true;
		$( 'wxrimp-upload-fill' ).style.width = '0%';
		$( 'wxrimp-chunk-note' ).textContent = '';

		uploadFile( file );
	}

	function uploadFile( file ) {
		var chunkSize = cfg.chunkSize || ( 8 * 1024 * 1024 );
		var totalChunks = Math.max( 1, Math.ceil( file.size / chunkSize ) );
		var index = 0;

		function uploadNext() {
			var start = index * chunkSize;
			var end = Math.min( file.size, start + chunkSize );
			var blob = file.slice( start, end );

			var body = new FormData();
			body.append( 'action', 'wxr-import-upload-chunk' );
			body.append( '_ajax_nonce', cfg.chunkUploadNonce );
			body.append( 'token', state.token );
			body.append( 'chunk_index', index );
			body.append( 'total_chunks', totalChunks );
			body.append( 'filename', file.name );
			body.append( 'chunk', blob, file.name );

			$( 'wxrimp-file-meta' ).textContent =
				formatBytes( file.size ) + ' · ' + cfg.strings.uploading + ' (' + ( index + 1 ) + '/' + totalChunks + ')';

			fetch( cfg.ajaxUrl, { method: 'POST', credentials: 'same-origin', body: body } )
				.then( function ( r ) { return r.json(); } )
				.then( function ( res ) {
					if ( ! res.success ) {
						throw new Error( ( res.data && res.data.message ) || 'Upload failed' );
					}

					var pct = Math.round( ( ( index + 1 ) / totalChunks ) * 100 );
					$( 'wxrimp-upload-fill' ).style.width = pct + '%';

					index++;
					if ( index < totalChunks ) {
						uploadNext();
						return;
					}

					onUploadComplete( res.data );
				} )
				.catch( function ( err ) {
					$( 'wxrimp-chunk-note' ).textContent = cfg.strings.uploadFailed + ' ' + err.message;
				} );
		}

		uploadNext();
	}

	function onUploadComplete( data ) {
		state.attachmentId = data.attachment_id;
		state.users = data.users || [];
		state.siteUsers = data.site_users || [];
		state.counts = data.counts || {};
		state.allowFetchAttachments = !! data.allow_fetch_attachments;

		$( 'wxrimp-file-meta' ).textContent = formatBytes( state.file.size ) + ' · ' + cfg.strings.uploadComplete;
		$( 'wxrimp-chunk-note' ).textContent = '';
		$( 'wxrimp-to-step2' ).disabled = false;
	}

	var dropzone = $( 'wxrimp-dropzone' );
	var fileInput = $( 'wxrimp-file-input' );

	dropzone.addEventListener( 'click', function () { fileInput.click(); } );
	dropzone.addEventListener( 'keydown', function ( e ) {
		if ( e.key === 'Enter' || e.key === ' ' ) {
			e.preventDefault();
			fileInput.click();
		}
	} );
	dropzone.addEventListener( 'dragover', function ( e ) {
		e.preventDefault();
		dropzone.classList.add( 'is-drag' );
	} );
	dropzone.addEventListener( 'dragleave', function () {
		dropzone.classList.remove( 'is-drag' );
	} );
	dropzone.addEventListener( 'drop', function ( e ) {
		e.preventDefault();
		dropzone.classList.remove( 'is-drag' );
		if ( e.dataTransfer.files && e.dataTransfer.files[ 0 ] ) {
			chooseFile( e.dataTransfer.files[ 0 ] );
		}
	} );
	fileInput.addEventListener( 'change', function () {
		if ( fileInput.files[ 0 ] ) {
			chooseFile( fileInput.files[ 0 ] );
		}
	} );

	$( 'wxrimp-to-step2' ).addEventListener( 'click', function () {
		renderAuthors();
		setStep( 2 );
	} );

	/* ---------- Screen 2: author mapping ---------- */

	function renderAuthors() {
		var container = $( 'wxrimp-authors' );
		container.innerHTML = '';

		var lede = $( 'wxrimp-authors-lede' );
		if ( ! state.users.length ) {
			lede.textContent = cfg.strings.noAuthors;
		} else {
			var template = state.users.length === 1 ? cfg.strings.authorsLedeOne : cfg.strings.authorsLedeMany;
			lede.textContent = template.replace( '%d', state.users.length );
		}

		state.users.forEach( function ( user, i ) {
			var row = document.createElement( 'div' );
			row.className = 'wxrimp-author-row';
			row.dataset.oldLogin = user.login;
			row.dataset.oldId = user.old_id || '';

			var initials = ( user.display_name || user.login || '?' ).slice( 0, 2 ).toUpperCase();

			var idBlock = document.createElement( 'div' );
			idBlock.className = 'wxrimp-author-id';

			var avatar = document.createElement( 'span' );
			avatar.className = 'wxrimp-avatar';
			avatar.setAttribute( 'aria-hidden', 'true' );
			avatar.textContent = initials;

			var idText = document.createElement( 'div' );
			var loginEl = document.createElement( 'div' );
			loginEl.className = 'login';
			loginEl.textContent = user.login;
			var emailEl = document.createElement( 'div' );
			emailEl.className = 'email';
			emailEl.textContent = user.email || '';
			idText.appendChild( loginEl );
			idText.appendChild( emailEl );

			idBlock.appendChild( avatar );
			idBlock.appendChild( idText );

			var arrow = document.createElement( 'span' );
			arrow.className = 'wxrimp-arrow';
			arrow.setAttribute( 'aria-hidden', 'true' );
			arrow.textContent = '→';

			var selectWrap = document.createElement( 'div' );
			var select = document.createElement( 'select' );
			select.className = 'wxrimp-user-select';

			( state.siteUsers || [] ).forEach( function ( siteUser ) {
				var opt = document.createElement( 'option' );
				opt.value = String( siteUser.id );
				opt.textContent = siteUser.display_name + ' (' + siteUser.login + ')';
				select.appendChild( opt );
			} );

			if ( cfg.allowCreateUsers ) {
				var createOpt = document.createElement( 'option' );
				createOpt.value = '';
				createOpt.textContent = cfg.strings.createNewUser + ' “' + user.login + '”';
				select.appendChild( createOpt );
			}

			if ( user.matched_id ) {
				select.value = String( user.matched_id );
			} else if ( cfg.allowCreateUsers ) {
				select.value = '';
			}

			selectWrap.appendChild( select );

			row.appendChild( idBlock );
			row.appendChild( arrow );
			row.appendChild( selectWrap );

			container.appendChild( row );
		} );

		$( 'wxrimp-fetch-attachments-row' ).hidden = ! state.allowFetchAttachments;
	}

	$( 'wxrimp-to-step1-back' ).addEventListener( 'click', function () { setStep( 1 ); } );
	$( 'wxrimp-to-step3' ).addEventListener( 'click', startImport );

	/* ---------- Screen 3: run the import ---------- */

	function startImport() {
		var body = new FormData();
		body.append( 'action', 'wxr-import-start' );
		body.append( '_ajax_nonce', cfg.startImportNonce );
		body.append( 'import_id', state.attachmentId );

		var rows = document.querySelectorAll( '#wxrimp-authors .wxrimp-author-row' );
		rows.forEach( function ( row, i ) {
			var select = row.querySelector( '.wxrimp-user-select' );
			body.append( 'imported_authors[' + i + ']', row.dataset.oldLogin );
			if ( row.dataset.oldId ) {
				body.append( 'imported_author_ids[' + i + ']', row.dataset.oldId );
			}
			if ( select && select.value ) {
				body.append( 'user_map[' + i + ']', select.value );
			} else {
				body.append( 'user_new[' + i + ']', row.dataset.oldLogin );
			}
		} );
		if ( state.users.length ) {
			body.append( 'imported_authors_present', '1' );
		}

		if ( state.allowFetchAttachments && $( 'wxrimp-fetch-attachments' ).checked ) {
			body.append( 'fetch_attachments', '1' );
		}

		setStep( 3 );
		buildStatGrid();
		complete = {};
		$( 'wxrimp-status-banner' ).className = 'wxrimp-status-banner';
		setSpinnerVisible( true );
		$( 'wxrimp-status-text' ).textContent = cfg.strings.importing;
		$( 'wxrimp-summary' ).hidden = true;
		$( 'wxrimp-log-body' ).querySelector( 'tbody' ).textContent = '';

		fetch( cfg.ajaxUrl, { method: 'POST', credentials: 'same-origin', body: body } )
			.then( function ( r ) { return r.json(); } )
			.then( function ( res ) {
				if ( ! res.success ) {
					throw new Error( ( res.data && res.data.message ) || 'Could not start import' );
				}
				openStream( res.data.stream_url );
			} )
			.catch( function ( err ) {
				$( 'wxrimp-status-banner' ).className = 'wxrimp-status-banner error';
				setSpinnerVisible( false );
				$( 'wxrimp-status-text' ).textContent = cfg.strings.startFailed + ' ' + err.message;
			} );
	}

	function buildStatGrid() {
		var grid = $( 'wxrimp-stat-grid' );
		grid.textContent = '';
		Object.keys( cfg.types ).forEach( function ( type ) {
			var tile = document.createElement( 'div' );
			tile.className = 'wxrimp-stat-tile';

			var head = document.createElement( 'div' );
			head.className = 'wxrimp-stat-head';
			head.textContent = cfg.types[ type ];

			var countRow = document.createElement( 'div' );
			countRow.className = 'wxrimp-stat-count';
			var n = document.createElement( 'span' );
			n.className = 'n';
			n.id = 'wxrimp-n-' + type;
			n.textContent = '0';
			countRow.appendChild( n );
			countRow.appendChild( document.createTextNode( ' / ' + ( state.counts[ type ] || 0 ) ) );

			var track = document.createElement( 'div' );
			track.className = 'wxrimp-progress-track';
			var fill = document.createElement( 'div' );
			fill.className = 'wxrimp-progress-fill';
			fill.id = 'wxrimp-bar-' + type;
			track.appendChild( fill );

			tile.appendChild( head );
			tile.appendChild( countRow );
			tile.appendChild( track );
			grid.appendChild( tile );
		} );
	}

	function updateDelta( type, delta ) {
		complete[ type ] = ( complete[ type ] || 0 ) + delta;
		var total = state.counts[ type ] || 1;
		var pct = Math.min( 100, Math.round( ( complete[ type ] / total ) * 100 ) );
		var nEl = $( 'wxrimp-n-' + type );
		var barEl = $( 'wxrimp-bar-' + type );
		if ( nEl ) {
			nEl.textContent = complete[ type ];
		}
		if ( barEl ) {
			barEl.style.width = pct + '%';
		}
	}

	function logRow( level, message ) {
		var row = document.createElement( 'tr' );
		var levelCell = document.createElement( 'td' );
		levelCell.className = 'level ' + level;
		levelCell.textContent = level;
		var msgCell = document.createElement( 'td' );
		msgCell.className = 'message';
		msgCell.textContent = message;
		row.appendChild( levelCell );
		row.appendChild( msgCell );

		var tbody = $( 'wxrimp-log-body' ).querySelector( 'tbody' );
		tbody.appendChild( row );
		$( 'wxrimp-log-body' ).scrollTop = $( 'wxrimp-log-body' ).scrollHeight;
	}

	function openStream( url ) {
		var evtSource = new EventSource( url );
		var consecutiveErrors = 0;

		evtSource.onmessage = function ( message ) {
			if ( consecutiveErrors >= 3 ) {
				$( 'wxrimp-status-banner' ).className = 'wxrimp-status-banner';
				setSpinnerVisible( true );
				$( 'wxrimp-status-text' ).textContent = cfg.strings.reconnected;
			}
			consecutiveErrors = 0;

			var data = JSON.parse( message.data );
			if ( data.action === 'updateDelta' ) {
				updateDelta( data.type, data.delta );
			} else if ( data.action === 'complete' ) {
				evtSource.close();
				finish( data );
			}
		};

		evtSource.addEventListener( 'log', function ( message ) {
			var data = JSON.parse( message.data );
			logRow( data.level, data.message );
		} );

		evtSource.onerror = function () {
			consecutiveErrors++;
			if ( consecutiveErrors < 3 ) {
				return;
			}

			var banner = $( 'wxrimp-status-banner' );
			banner.className = 'wxrimp-status-banner warning';
			setSpinnerVisible( false );
			$( 'wxrimp-status-text' ).textContent = cfg.strings.connectionLost;
		};
	}

	function finish( data ) {
		var summary = data.summary || { created: 0, skipped: 0, failed: 0 };
		var banner = $( 'wxrimp-status-banner' );
		setSpinnerVisible( false );

		if ( data.error ) {
			banner.className = 'wxrimp-status-banner error';
			$( 'wxrimp-status-text' ).textContent = cfg.strings.errorPrefix + ' ' + data.error;
			return;
		}

		banner.className = 'wxrimp-status-banner ' + ( summary.failed > 0 ? 'warning' : 'success' );
		$( 'wxrimp-status-text' ).textContent = cfg.strings.complete;

		var summaryText = cfg.strings.summary
			.replace( '%1$d', summary.created )
			.replace( '%2$d', summary.skipped )
			.replace( '%3$d', summary.failed );
		var summaryEl = $( 'wxrimp-summary' );
		summaryEl.textContent = summaryText;
		summaryEl.hidden = false;
	}

	$( 'wxrimp-log-toggle' ).addEventListener( 'click', function () {
		var panel = $( 'wxrimp-log-panel' );
		var open = panel.classList.toggle( 'is-open' );
		this.setAttribute( 'aria-expanded', open ? 'true' : 'false' );
	} );
} )();
