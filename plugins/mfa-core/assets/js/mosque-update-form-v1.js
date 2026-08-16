// mfa-core mosque-update-form-v1.js — AJAX submit for
// [mfa_mosque_update_form] (the "Edit Mosque" modal on the single mosque
// post's Home tab, Administrator/Editor only). Same shape as
// business-update-form-v1.js. Event delegation so it works no matter how
// many times the modal's been opened/closed.
( function () {
	document.addEventListener( 'submit', function ( e ) {
		var form = e.target.closest( '.mfa-mosque-edit-form' );
		if ( ! form ) {
			return;
		}
		e.preventDefault();

		var msg = form.querySelector( '[data-mfa-form-message]' );
		var btn = form.querySelector( '.mfa-modal-submit' );
		var ajaxUrl = form.dataset.ajaxurl;
		var postId = form.dataset.postId;

		var data = new FormData( form );
		data.append( 'action', 'mfa_mosque_update_info' );
		data.append( 'post_id', postId );

		if ( msg ) {
			msg.textContent = '';
			msg.classList.remove( 'is-success' );
		}
		if ( btn ) {
			btn.disabled = true;
		}

		fetch( ajaxUrl, { method: 'POST', body: data } )
			.then( function ( r ) { return r.json(); } )
			.then( function ( result ) {
				if ( ! msg ) {
					return;
				}
				if ( result.success ) {
					msg.textContent = result.data.message;
					msg.classList.add( 'is-success' );
					setTimeout( function () { location.reload(); }, 1200 );
				} else {
					msg.textContent = ( result.data && result.data.message ) || 'Something went wrong. Please try again.';
					if ( btn ) {
						btn.disabled = false;
					}
				}
			} )
			.catch( function () {
				if ( msg ) {
					msg.textContent = 'Network error. Please try again.';
				}
				if ( btn ) {
					btn.disabled = false;
				}
			} );
	} );
} )();
