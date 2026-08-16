// mfa-core admin-tabs-v1.js — generic tab switching for any [mfa_admin_*]
// page using the .mfa-admin-tabs markup (see admin-member-info.php for the
// first use, place-hub-v1.js for the identical pattern this was copied
// from). Pure progressive enhancement: both panels are already in the
// markup, this only adds the class that lets CSS hide the inactive one and
// wires up clicks. Loaded in the footer, so the markup already exists by
// the time this runs.
( function () {
	document.querySelectorAll( '.mfa-admin-tabs' ).forEach( function ( tabs ) {
		tabs.classList.add( 'mfa-admin-tabs-js' );

		var buttons = tabs.querySelectorAll( '.mfa-admin-tab' );
		var panels = tabs.querySelectorAll( '.mfa-admin-tabpanel' );

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
