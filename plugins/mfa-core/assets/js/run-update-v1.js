// mfa-core run-update-v1.js — drives [run-update]'s Start button. Calls
// each pending entry's AJAX batch endpoint repeatedly (one round trip per
// call) until it reports done, then moves to the next entry; when every
// entry is done, hides the list/button and shows "All updated." Each
// run_batch() call waits for the previous one's response before firing the
// next, so a slow/rate-limited job is naturally paced by however long its
// own batch takes - never overlapping requests.
( function () {
	document.addEventListener( 'click', function ( e ) {
		var btn = e.target.closest( '.mfa-run-update-start' );
		if ( ! btn ) {
			return;
		}
		var root = btn.closest( '.mfa-run-update' );
		if ( ! root ) {
			return;
		}
		e.preventDefault();
		runAll( root, btn );
	} );

	function runAll( root, btn ) {
		var items = Array.prototype.slice.call( root.querySelectorAll( '[data-update-key]' ) );
		var status = root.querySelector( '.mfa-run-update-status' );
		var list = root.querySelector( '.mfa-run-update-list' );

		btn.disabled = true;
		btn.textContent = 'Running…';

		function next( i ) {
			if ( i >= items.length ) {
				if ( list ) {
					list.hidden = true;
				}
				btn.hidden = true;
				if ( status ) {
					status.textContent = 'All updated.';
					status.hidden = false;
				}
				return;
			}
			runOne( root, items[ i ], function () {
				next( i + 1 );
			}, function ( message ) {
				btn.disabled = false;
				btn.textContent = 'Start';
				if ( status ) {
					status.textContent = message || 'Something went wrong. Please try again.';
					status.hidden = false;
				}
			} );
		}

		next( 0 );
	}

	function runOne( root, li, onDone, onError ) {
		var key = li.getAttribute( 'data-update-key' );
		var progress = li.querySelector( '.mfa-run-update-progress' );

		var data = new FormData();
		data.append( 'action', 'mfa_run_update_batch' );
		data.append( 'key', key );
		data.append( 'nonce', root.dataset.nonce );

		fetch( root.dataset.ajaxurl, { method: 'POST', body: data } )
			.then( function ( r ) { return r.json(); } )
			.then( function ( result ) {
				if ( ! result.success ) {
					onError( result.data && result.data.message );
					return;
				}
				if ( progress ) {
					progress.textContent = result.data.progress || '';
				}
				if ( result.data.done ) {
					onDone();
				} else {
					runOne( root, li, onDone, onError );
				}
			} )
			.catch( function () {
				onError( 'Network error. Please try again.' );
			} );
	}
} )();
