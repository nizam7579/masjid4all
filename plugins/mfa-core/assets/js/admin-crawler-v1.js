/* Directory Crawler admin panel (see includes/widgets/admin-crawler.php).
   One AJAX endpoint (mfa_admin_crawler_action) drives every action; the status
   board re-renders from the snapshot each call returns. */
( function () {
	var root = document.querySelector( '.mfa-crawler' );
	if ( ! root ) { return; }

	var ajax  = root.getAttribute( 'data-ajax' );
	var nonce = root.getAttribute( 'data-nonce' );
	var COUNTRIES = { ID: 'Indonesia', GB: 'UK', AU: 'Australia', CA: 'Canada', MY: 'Malaysia', SG: 'Singapore', BN: 'Brunei', US: 'US' };

	function el( id ) { return document.getElementById( id ); }
	function n( x ) { return ( x == null ? 0 : Number( x ) ).toLocaleString( 'en-US' ); }

	function post( op, extra ) {
		var data = new FormData();
		data.append( 'action', 'mfa_admin_crawler_action' );
		data.append( 'nonce', nonce );
		data.append( 'op', op );
		if ( extra ) { for ( var k in extra ) { data.append( k, extra[ k ] ); } }
		return fetch( ajax, { method: 'POST', credentials: 'same-origin', body: data } ).then( function ( r ) { return r.json(); } );
	}

	function card( v, l ) {
		return '<div class="mfa-crawler-card"><span class="mfa-crawler-card-num">' + v + '</span><span class="mfa-crawler-card-lbl">' + l + '</span></div>';
	}

	function render( s ) {
		if ( ! s || ! s.table_exists ) {
			el( 'mfa-crawler-cards' ).innerHTML = '<p class="mfa-crawler-hint">Grid table is empty &mdash; run step 1 (Import cities) to build it.</p>';
			return;
		}

		var banner = el( 'mfa-crawler-banner' );
		if ( s.paused ) {
			banner.hidden = false;
			banner.className = 'mfa-crawler-banner is-paused';
			banner.textContent = '⏸ Paused — ' + ( s.pause_reason || '' );
			el( 'mfa-crawler-resume' ).hidden = false;
		} else {
			banner.hidden = true;
			el( 'mfa-crawler-resume' ).hidden = true;
		}

		var st = s.by_status || {};
		el( 'mfa-crawler-cards' ).innerHTML = [
			card( n( s.total ), 'Total cells' ),
			card( n( st.New ), 'New' ),
			card( n( st.Pending ), 'Pending' ),
			card( n( st.Done ), 'Done' ),
			card( n( s.mosque_total ), 'Mosques' ),
			card( n( s.business_total ), 'Businesses' )
		].join( '' );

		var used = Number( s.credits_used ), bud = Number( s.credits_budget );
		var pct = bud ? Math.min( 100, Math.round( used / bud * 100 ) ) : 0;
		el( 'mfa-crawler-credits' ).innerHTML =
			'<div class="mfa-crawler-credit-head"><span><strong>' + n( used ) + '</strong> credits used &middot; <strong>' + n( s.credits_remaining ) + '</strong> remaining</span>' +
			'<span>budget ' + n( bud ) + ' &middot; ~' + s.credits_per_cell + '/cell</span></div>' +
			'<div class="mfa-crawler-bar"><span style="width:' + pct + '%"></span></div>';

		var bc = s.by_country || {}, rows = '';
		for ( var cc in COUNTRIES ) {
			var d = bc[ cc ] || { New: 0, Pending: 0, Done: 0 };
			rows += '<tr><td>' + COUNTRIES[ cc ] + ' (' + cc + ')</td><td>' + n( d.New ) + '</td><td>' + n( d.Pending ) + '</td><td>' + n( d.Done ) + '</td></tr>';
		}
		el( 'mfa-crawler-countries' ).innerHTML =
			'<table class="mfa-crawler-table"><thead><tr><th>Country</th><th>New</th><th>Pending</th><th>Done</th></tr></thead><tbody>' + rows + '</tbody></table>';

		el( 'mfa-crawler-cron-enabled' ).checked = !! s.cron_enabled;
		el( 'mfa-crawler-cron-size' ).value = s.batch_size;
		el( 'mfa-crawler-cron-country' ).value = s.cron_country || '';
		el( 'mfa-crawler-budget' ).value = s.credits_budget;
		el( 'mfa-crawler-percell' ).value = s.credits_per_cell;
	}

	function log( msg, busy ) {
		var box = el( 'mfa-crawler-log' );
		box.hidden = false;
		box.className = 'mfa-crawler-log' + ( busy ? ' is-busy' : '' );
		box.textContent = msg;
	}

	var LABELS = {
		seed_cities: 'Importing cities…', seed_counts: 'Folding counts…', seed_us: 'Seeding US grid…',
		queue: 'Queuing…', run: 'Crawling (this can take a while)…', resume: 'Resuming…',
		save_cron: 'Saving…', save_budget: 'Saving…', reset_used: 'Resetting…'
	};

	root.addEventListener( 'click', function ( e ) {
		var btn = e.target.closest( '.mfa-crawler-btn' );
		if ( ! btn ) { return; }
		var op = btn.getAttribute( 'data-op' );
		var extra = {};

		if ( 'queue' === op ) {
			extra.country = el( 'mfa-crawler-queue-country' ).value;
			extra.limit = el( 'mfa-crawler-queue-limit' ).value || 0;
		}
		if ( 'run' === op ) {
			extra.country = '';
			extra.limit = el( 'mfa-crawler-run-limit' ).value || 5;
		}
		if ( 'save_cron' === op ) {
			extra.enabled = el( 'mfa-crawler-cron-enabled' ).checked ? '1' : '0';
			extra.batch_size = el( 'mfa-crawler-cron-size' ).value;
			extra.cron_country = el( 'mfa-crawler-cron-country' ).value;
		}
		if ( 'save_budget' === op ) {
			extra.budget = el( 'mfa-crawler-budget' ).value;
			extra.per_cell = el( 'mfa-crawler-percell' ).value;
		}
		if ( ( 'seed_cities' === op || 'seed_counts' === op || 'seed_us' === op ) && ! window.confirm( 'Run this pipeline step now?' ) ) {
			return;
		}

		btn.disabled = true;
		log( LABELS[ op ] || 'Working…', true );
		post( op, extra ).then( function ( j ) {
			btn.disabled = false;
			if ( j && j.success ) {
				log( j.data.message || 'Done.' );
				render( j.data.status );
			} else {
				log( 'Error: ' + ( j && j.data ? j.data : 'request failed' ) );
			}
		} ).catch( function () {
			btn.disabled = false;
			log( 'Request failed.' );
		} );
	} );

	post( 'status' ).then( function ( j ) { if ( j && j.success ) { render( j.data.status ); } } );
} )();
