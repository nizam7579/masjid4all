// mfa-theme footer-nav.js — highlight the bottom-nav item matching the
// current page (longest matching path wins; "/" only matches the homepage).
( function () {
	var path = window.location.pathname.replace( /\/+$/, '' ) || '/';
	var items = document.querySelectorAll( '.mfa-footer-nav-item' );
	var best = null;
	var bestLen = -1;

	items.forEach( function ( a ) {
		var p = ( a.getAttribute( 'data-path' ) || '' ).replace( /\/+$/, '' ) || '/';
		if ( p === '/' ) {
			if ( path === '/' && bestLen < 0 ) {
				best = a;
				bestLen = 0;
			}
		} else if ( path === p || path.indexOf( p + '/' ) === 0 ) {
			if ( p.length > bestLen ) {
				best = a;
				bestLen = p.length;
			}
		}
	} );

	if ( best ) {
		best.classList.add( 'is-active' );
	}
} )();
