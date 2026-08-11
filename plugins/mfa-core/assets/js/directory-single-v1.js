// mfa-core directory-single-v1.js — tab switching for [mfa_directory_single].
// Event delegation, scoped to each .mfa-dir-single, so it works regardless of
// how many listings/tabs render on the page.
( function () {
	document.addEventListener( 'click', function ( e ) {
		var tab = e.target.closest( '.mfa-dir-tab' );
		if ( ! tab ) {
			return;
		}
		var wrap = tab.closest( '.mfa-dir-single' );
		if ( ! wrap ) {
			return;
		}
		var idx = tab.getAttribute( 'data-mfa-tab' );

		wrap.querySelectorAll( '.mfa-dir-tab' ).forEach( function ( t ) {
			t.classList.toggle( 'is-active', t === tab );
		} );
		wrap.querySelectorAll( '.mfa-dir-pane' ).forEach( function ( p ) {
			p.classList.toggle( 'is-active', p.getAttribute( 'data-mfa-pane' ) === idx );
		} );
	} );
} )();
