// mfa-core mosque-ai-updater-v1.js — AJAX wiring for the always-visible
// "Update Content" action-row button ([mfa_mosque_ai_updater],
// Administrator/Editor only). querySelectorAll-based, same shape as
// enaizi-mfa's business/website "Update Content" buttons, so any number
// of instances on one page work independently - deliberately separate
// from mosque-single.php's own inline "Click to Update" prompt (its own
// getElementById-scoped script), even though both post to the same
// mfa_mosque_ai_update AJAX action.
( function () {
	var updateBtns = document.querySelectorAll( '.mfa-mosque-ai-update-btn' );

	updateBtns.forEach( function ( btn ) {
		btn.addEventListener( 'click', function () {
			var wrapper = btn.closest( '.mfa-mosque-ai-update-wrapper' );
			var msg = wrapper ? wrapper.querySelector( '.mfa-mosque-ai-update-msg' ) : null;
			var originalHtml = btn.innerHTML;

			btn.disabled = true;
			btn.textContent = 'Updating…';
			if ( msg ) {
				msg.textContent = '';
			}

			var data = new FormData();
			data.append( 'action', 'mfa_mosque_ai_update' );
			data.append( 'post_id', btn.dataset.postId );
			data.append( 'nonce', btn.dataset.nonce );
			data.append( 'mosque_name', btn.dataset.name );

			fetch( btn.dataset.ajaxurl, { method: 'POST', body: data } )
				.then( function ( r ) { return r.json(); } )
				.then( function ( result ) {
					if ( result && result.success ) {
						if ( msg ) {
							msg.textContent = 'Updated. Refreshing…';
						}
						setTimeout( function () { location.reload(); }, 1200 );
						return;
					}
					btn.disabled = false;
					btn.innerHTML = originalHtml;
					if ( msg ) {
						msg.textContent = ( result && result.data ) || 'Update failed. Please try again.';
					}
				} )
				.catch( function () {
					btn.disabled = false;
					btn.innerHTML = originalHtml;
					if ( msg ) {
						msg.textContent = 'Network error. Please try again.';
					}
				} );
		} );
	} );
} )();
