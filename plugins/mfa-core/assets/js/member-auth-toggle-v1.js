/**
 * Logged-out /member auth. Registration moved to Sofia (WhatsApp), so the old
 * login/register tab toggle is gone. The reused [niz_login] form still carries
 * a built-in "Register" cross-link to /register/ (closed) — intercept it and
 * open the "Register with Sofia" popup instead. The popups' own open/close is
 * handled by dir-assist-v1.js (the shared .mfa-assist-* component).
 */
document.addEventListener( 'DOMContentLoaded', function () {
	var root = document.getElementById( 'mfa-member-auth' );
	if ( ! root ) {
		return;
	}

	root.addEventListener( 'click', function ( e ) {
		var link = e.target.closest( 'a' );
		if ( ! link ) {
			return;
		}
		var href = link.getAttribute( 'href' ) || '';
		// Only the "/register/" cross-link — leave "Forgot password?" (which
		// points at /forgot-password/) to navigate normally.
		if ( href.indexOf( '/register/' ) !== -1 ) {
			e.preventDefault();
			var pop = document.getElementById( 'mfa-auth-register' );
			if ( pop ) {
				pop.classList.add( 'is-open' );
				pop.setAttribute( 'aria-hidden', 'false' );
				document.body.style.overflow = 'hidden';
			}
		}
	} );
} );
