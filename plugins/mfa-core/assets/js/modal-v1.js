// mfa-core modal-v1.js — behaviour for the reusable [mfa_modal] dialog.
// Event delegation so any number of modals (added statically or later) work
// without per-modal wiring. Supports stacked modals, ESC, overlay click, and
// body scroll-lock.
( function () {
	var open = [];

	function openModal( id ) {
		var m = document.getElementById( id );
		if ( ! m || m.classList.contains( 'is-open' ) ) {
			return;
		}
		m.classList.add( 'is-open' );
		m.setAttribute( 'aria-hidden', 'false' );
		document.body.classList.add( 'mfa-modal-lock' );
		open.push( m );
		var closeBtn = m.querySelector( '.mfa-modal-close' );
		if ( closeBtn ) {
			closeBtn.focus();
		}
	}

	function closeModal( m ) {
		if ( ! m ) {
			return;
		}
		m.classList.remove( 'is-open' );
		m.setAttribute( 'aria-hidden', 'true' );
		open = open.filter( function ( x ) {
			return x !== m;
		} );
		if ( ! open.length ) {
			document.body.classList.remove( 'mfa-modal-lock' );
		}
	}

	document.addEventListener( 'click', function ( e ) {
		var opener = e.target.closest( '[data-mfa-modal-open]' );
		if ( opener ) {
			e.preventDefault();
			openModal( opener.getAttribute( 'data-mfa-modal-open' ) );
			return;
		}
		var closer = e.target.closest( '[data-mfa-modal-close]' );
		if ( closer ) {
			e.preventDefault();
			closeModal( closer.closest( '.mfa-modal-overlay' ) );
			return;
		}
		// Click on the overlay backdrop itself (not the dialog) closes.
		if ( e.target.classList && e.target.classList.contains( 'mfa-modal-overlay' ) ) {
			closeModal( e.target );
		}
	} );

	document.addEventListener( 'keydown', function ( e ) {
		if ( 'Escape' === e.key && open.length ) {
			closeModal( open[ open.length - 1 ] );
		}
	} );
} )();
