/**
 * Open/close behaviour for the Sofia-assist popups on the directory pages
 * (directory-pages.php's mfa_dir_assist_cta() / dir-assist-v1.css). Delegated
 * click handling so it works regardless of how many popups are on the page.
 */
( function () {
	function openModal( overlay ) {
		overlay.classList.add( 'is-open' );
		overlay.setAttribute( 'aria-hidden', 'false' );
		document.body.style.overflow = 'hidden';
	}

	function closeModal( overlay ) {
		overlay.classList.remove( 'is-open' );
		overlay.setAttribute( 'aria-hidden', 'true' );
		document.body.style.overflow = '';
	}

	document.addEventListener( 'click', function ( e ) {
		var opener = e.target.closest( '.mfa-assist-open' );
		if ( opener ) {
			e.preventDefault();
			var target = document.getElementById( opener.getAttribute( 'data-target' ) );
			if ( target ) {
				openModal( target );
			}
			return;
		}

		if ( e.target.closest( '.mfa-assist-close' ) ) {
			var viaClose = e.target.closest( '.mfa-assist-overlay' );
			if ( viaClose ) {
				closeModal( viaClose );
			}
			return;
		}

		// Click on the backdrop itself (not the modal card) closes it.
		if ( e.target.classList && e.target.classList.contains( 'mfa-assist-overlay' ) ) {
			closeModal( e.target );
		}
	} );

	document.addEventListener( 'keydown', function ( e ) {
		if ( 'Escape' === e.key ) {
			var open = document.querySelectorAll( '.mfa-assist-overlay.is-open' );
			for ( var i = 0; i < open.length; i++ ) {
				closeModal( open[ i ] );
			}
		}
	} );
}() );
