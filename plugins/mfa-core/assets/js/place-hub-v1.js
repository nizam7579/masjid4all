// mfa-core place-hub-v1.js — tab switching for the Mosques/Businesses
// sections on [mfa_place_hub] (/places/...). Pure progressive enhancement:
// both panels are already in the markup (see place-hub.php), this only
// adds the class that lets CSS hide the inactive one and wires up clicks.
// Loaded in the footer, so the markup already exists by the time this runs.
( function () {
	document.querySelectorAll( '.mfa-place-tabs' ).forEach( function ( tabs ) {
		tabs.classList.add( 'mfa-place-tabs-js' );

		var buttons = tabs.querySelectorAll( '.mfa-place-tab' );
		var panels = tabs.querySelectorAll( '.mfa-place-tabpanel' );

		buttons.forEach( function ( button ) {
			button.addEventListener( 'click', function () {
				var target = button.getAttribute( 'data-tab' );

				buttons.forEach( function ( b ) {
					var active = b === button;
					b.classList.toggle( 'is-active', active );
					b.setAttribute( 'aria-selected', active ? 'true' : 'false' );
				} );

				panels.forEach( function ( panel ) {
					panel.classList.toggle( 'is-active', panel.getAttribute( 'data-tabpanel' ) === target );
				} );
			} );
		} );
	} );
} )();
