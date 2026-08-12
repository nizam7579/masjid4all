<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * [mfa_homepage_stats] - live directory + membership counts for the homepage
 * stats strip, pulled from the JetEngine CCT tables and wp_users (read via
 * $wpdb per the project's standing rule). Cached 6h so the homepage doesn't
 * run COUNT(*) against multi-hundred-thousand-row tables on every pageview.
 *
 * Directory counts only include "listed" statuses
 * (New/Pending/Approved/Verified/Premium) - Rejected/Error/Deleted are
 * excluded. The fourth stat is "Our Members": users registered since
 * 1 Jan 2026. Each value animates from 0 up to its target (running counter,
 * see the inline script), degrading to the real number when JS is off.
 */
function mfa_homepage_live_counts() {
	$counts = get_transient( 'mfa_homepage_stats_counts' );

	if ( false === $counts ) {
		global $wpdb;
		$listed = "listing_status IN ('New','Pending','Approved','Verified','Premium')";
		$counts = array(
			'mosque'   => (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->prefix}jet_cct_mosque WHERE {$listed}" ),
			'business' => (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->prefix}jet_cct_business WHERE {$listed}" ),
			'web'      => (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->prefix}jet_cct_web WHERE {$listed}" ),
			'members'  => (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->users} WHERE user_registered >= '2026-01-01 00:00:00'" ),
		);
		set_transient( 'mfa_homepage_stats_counts', $counts, 6 * HOUR_IN_SECONDS );
	}

	return $counts;
}

/**
 * Flush the cached homepage stat counts so the next homepage view recomputes
 * instead of waiting out the 6h cache. Wired to:
 *   - user_register        -> a new member is added ("Our Members" count).
 *   - save_post_{masjid,business,web} -> a listing is added or approved. The
 *     add flows (wp_insert_post) and the AI updaters / approval path
 *     (wp_update_post on the linked post) both fire save_post for these types.
 */
function mfa_flush_homepage_stats_cache() {
	delete_transient( 'mfa_homepage_stats_counts' );
}
add_action( 'user_register', 'mfa_flush_homepage_stats_cache' );

function mfa_flush_homepage_stats_cache_on_save( $post_id ) {
	if ( wp_is_post_autosave( $post_id ) || wp_is_post_revision( $post_id ) ) {
		return;
	}
	mfa_flush_homepage_stats_cache();
}
add_action( 'save_post_masjid', 'mfa_flush_homepage_stats_cache_on_save' );
add_action( 'save_post_business', 'mfa_flush_homepage_stats_cache_on_save' );
add_action( 'save_post_web', 'mfa_flush_homepage_stats_cache_on_save' );

add_shortcode( 'mfa_homepage_stats', 'mfa_homepage_stats_shortcode' );
function mfa_homepage_stats_shortcode() {
	$counts = mfa_homepage_live_counts();

	$items = array(
		array( 'value' => (int) $counts['mosque'],   'label' => 'Mosques Listed' ),
		array( 'value' => (int) $counts['business'], 'label' => 'Halal Businesses' ),
		array( 'value' => (int) $counts['web'],      'label' => 'Trusted Websites' ),
		array( 'value' => (int) $counts['members'],  'label' => 'Our Members' ),
	);

	ob_start();
	?>
	<div class="mfa-stats-strip">
		<?php foreach ( $items as $item ) : ?>
			<div class="mfa-stat-item">
				<span class="mfa-stat-value" data-target="<?php echo esc_attr( $item['value'] ); ?>"><?php echo esc_html( number_format_i18n( $item['value'] ) ); ?></span>
				<span class="mfa-stat-label"><?php echo esc_html( $item['label'] ); ?></span>
			</div>
		<?php endforeach; ?>
	</div>
	<script>
	(function () {
		var strip = document.currentScript.previousElementSibling;
		if ( ! strip || ! strip.classList.contains( 'mfa-stats-strip' ) ) {
			strip = document.querySelector( '.mfa-stats-strip' );
		}
		if ( ! strip ) { return; }
		var vals = strip.querySelectorAll( '.mfa-stat-value[data-target]' );
		var ran  = false;

		function animate( el ) {
			var target = parseInt( el.getAttribute( 'data-target' ), 10 ) || 0;
			var duration = 1800, start = null;
			function step( ts ) {
				if ( ! start ) { start = ts; }
				var p = Math.min( ( ts - start ) / duration, 1 );
				var eased = 1 - Math.pow( 1 - p, 3 );
				el.textContent = Math.floor( eased * target ).toLocaleString( 'en-US' );
				if ( p < 1 ) { requestAnimationFrame( step ); }
				else { el.textContent = target.toLocaleString( 'en-US' ); }
			}
			requestAnimationFrame( step );
		}

		function run() {
			if ( ran ) { return; }
			ran = true;
			vals.forEach( animate );
		}

		if ( 'IntersectionObserver' in window ) {
			var io = new IntersectionObserver( function ( entries ) {
				entries.forEach( function ( e ) {
					if ( e.isIntersecting ) { run(); io.disconnect(); }
				} );
			}, { threshold: 0.3 } );
			io.observe( strip );
		} else {
			run();
		}
	})();
	</script>
	<?php
	return ob_get_clean();
}
