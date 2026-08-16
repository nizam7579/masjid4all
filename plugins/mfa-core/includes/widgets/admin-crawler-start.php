<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * [mfa_admin_crawler_start] - /admin/crawler/start/, a child page of
 * /admin/crawler/. Claims and crawls exactly ONE non-Done cell for the
 * selected country (or, in city mode, one cell within a radius of a
 * geocoded city - see below) per page load (see
 * mfa_geohash_crawl_claim_and_run_one() in geohash-crawl.php), then
 * redirects itself back to the same URL after a short pause. No AJAX/JS
 * loop - each cycle is a full page load, so several tabs (or several
 * computers) can each hold one open per country and just keep indexing,
 * matching how this crawl was driven manually before this page existed. A
 * page reload is also immune to the browser silently discarding a
 * backgrounded tab's JS state, which an in-page loop is not.
 *
 * City mode (2026-08-14): optional ?lat=&lng=&radius=&label= query args,
 * set by the "Crawl by city" section on the overview page after it
 * geocodes a city name. Same cells/tables/claim mechanism as a normal
 * country crawl, just filtered to a radius instead of the whole country -
 * for large, mostly-rural countries (e.g. Indonesia: 33,395 cells, most
 * low-yield) this lets a tab spend its budget on a dense city instead of
 * wasting credits on sparse rural cells. Country-level stats stay correct
 * automatically since it's the same underlying rows, just fewer of them
 * touched per session.
 */

add_shortcode( 'mfa_admin_crawler_start', 'mfa_admin_crawler_start_shortcode' );
function mfa_admin_crawler_start_shortcode() {
	if ( function_exists( 'mfa_admin_require_section_access' ) ) {
		$no_access = mfa_admin_require_section_access( 'crawler' );
		if ( $no_access ) {
			return $no_access;
		}
	} elseif ( ! current_user_can( 'manage_options' ) ) {
		return '<p>You do not have permission to view this page.</p>';
	}

	$countries    = mfa_geohash_country_summary();
	$cc           = isset( $_GET['country'] ) ? strtoupper( sanitize_text_field( wp_unslash( $_GET['country'] ) ) ) : '';
	$lat          = isset( $_GET['lat'] ) && is_numeric( $_GET['lat'] ) ? (float) $_GET['lat'] : null;
	$lng          = isset( $_GET['lng'] ) && is_numeric( $_GET['lng'] ) ? (float) $_GET['lng'] : null;
	$radius       = isset( $_GET['radius'] ) ? max( 1, min( 200, (int) $_GET['radius'] ) ) : null;
	$city_label   = isset( $_GET['label'] ) ? sanitize_text_field( wp_unslash( $_GET['label'] ) ) : '';
	$is_city      = ( null !== $lat && null !== $lng && $radius );
	$overview_url = home_url( '/admin/crawler/' );

	$heading = '';
	if ( $cc && isset( $countries[ $cc ] ) ) {
		$heading = $is_city && $city_label
			? ' &mdash; ' . esc_html( $city_label ) . ', ' . esc_html( $countries[ $cc ]['name'] )
			: ' &mdash; ' . esc_html( $countries[ $cc ]['name'] );
	}

	ob_start();
	?>
	<div class="mfa-crawler">
		<h1 class="mfa-h2">Start Crawl<?php echo $heading; ?></h1>

		<?php if ( ! $cc || ! isset( $countries[ $cc ] ) ) : ?>
			<div class="mfa-crawler-section">
				<form method="get" class="mfa-crawler-row">
					<select name="country">
						<?php foreach ( $countries as $code => $d ) : ?>
							<option value="<?php echo esc_attr( $code ); ?>"><?php echo esc_html( $d['name'] . ' (' . $code . ')' ); ?></option>
						<?php endforeach; ?>
					</select>
					<button class="mfa-crawler-btn mfa-crawler-btn-primary" type="submit">Start crawl now &rarr;</button>
				</form>
				<p class="mfa-crawler-hint">Open this in as many tabs as you like, one per country (or the same country in several tabs) &mdash; each tab claims a different location so they never overlap.</p>
			</div>
		<?php else :
			echo mfa_admin_crawler_start_render( $cc, $countries[ $cc ]['name'], $lat, $lng, $radius, $city_label );
		endif;
		?>

		<p class="mfa-crawler-hint"><a href="<?php echo esc_url( $overview_url ); ?>">&larr; Back to overview</a></p>
	</div>
	<?php
	return ob_get_clean();
}

/**
 * Builds the self-redirect URL for this page, carrying the city params
 * forward alongside country whenever they're present - every state below
 * needs this, so it's centralised here rather than repeated per-branch.
 */
function mfa_admin_crawler_start_next_url( $cc, $lat, $lng, $radius, $city_label ) {
	$args = array( 'country' => $cc );
	if ( null !== $lat && null !== $lng && $radius ) {
		$args['lat']    = $lat;
		$args['lng']    = $lng;
		$args['radius'] = $radius;
		if ( $city_label ) {
			$args['label'] = $city_label;
		}
	}
	return add_query_arg( $args, home_url( '/admin/crawler/start/' ) );
}

function mfa_admin_crawler_start_render( $cc, $label, $lat, $lng, $radius, $city_label ) {
	// Catches genuine PHP-level failures (a dropped DB connection, an
	// unexpected null, etc.), not just the expected WP_Error path from a
	// Serper failure - without this, an unhandled Throwable here would fatal
	// the whole page out with no output at all, including no redirect script,
	// silently killing the tab for good with nothing to notice or retry.
	try {
		return mfa_admin_crawler_start_attempt( $cc, $label, $lat, $lng, $radius, $city_label );
	} catch ( Throwable $e ) {
		mfa_crawl_notify( 'Crawler error', "The directory crawler hit an unexpected PHP error and stopped:\n\n" . $e->getMessage() );
		$next_url = mfa_admin_crawler_start_next_url( $cc, $lat, $lng, $radius, $city_label );
		ob_start();
		?>
		<div class="mfa-crawler-banner is-paused">&#9208; Unexpected error &mdash; retrying automatically.</div>
		<script>setTimeout( function () { location.href = <?php echo wp_json_encode( $next_url ); ?>; }, <?php echo (int) wp_rand( 15000, 30000 ); ?> );</script>
		<?php
		return ob_get_clean();
	}
}

function mfa_admin_crawler_start_attempt( $cc, $label, $lat, $lng, $radius, $city_label ) {
	$r = mfa_geohash_crawl_claim_and_run_one( $cc, $lat, $lng, $radius );

	if ( 'paused' === $r['state'] ) {
		// A deliberate circuit breaker (out of credits) - don't hammer Serper by
		// retrying fast, but keep checking slowly so a tab left open overnight
		// auto-resumes on its own once someone tops up credits and hits Resume
		// on the overview page, instead of needing every open tab reloaded by
		// hand. mfa_crawl_pause() already emails on the way in here.
		$next_url = mfa_admin_crawler_start_next_url( $cc, $lat, $lng, $radius, $city_label );
		ob_start();
		?>
		<div class="mfa-crawler-banner is-paused">&#9208; Paused &mdash; <?php echo esc_html( $r['reason'] ); ?></div>
		<p class="mfa-crawler-hint">Resolve this on the <a href="<?php echo esc_url( home_url( '/admin/crawler/' ) ); ?>">overview page</a> - this tab will pick back up on its own once resumed.</p>
		<script>setTimeout( function () { location.href = <?php echo wp_json_encode( $next_url ); ?>; }, 60000 );</script>
		<?php
		return ob_get_clean();
	}

	if ( 'busy' === $r['state'] ) {
		// Every slot is taken by other tabs right now - retry shortly rather
		// than doing any DB/Serper work. Jittered so tabs that lost the race
		// together don't all retry in lockstep and re-collide on the retry.
		$next_url = mfa_admin_crawler_start_next_url( $cc, $lat, $lng, $radius, $city_label );
		ob_start();
		?>
		<p class="mfa-crawler-hint">All crawl slots are busy right now &mdash; waiting for one to free up&hellip;</p>
		<script>setTimeout( function () { location.href = <?php echo wp_json_encode( $next_url ); ?>; }, <?php echo (int) wp_rand( 800, 2000 ); ?> );</script>
		<?php
		return ob_get_clean();
	}

	if ( 'done_all' === $r['state'] ) {
		$done_label = ( $city_label && $radius ) ? $city_label . ', ' . $label : $label;
		return '<p class="mfa-crawler-hint">&#127881; ' . esc_html( $done_label ) . ' is fully crawled &mdash; '
			. number_format_i18n( $r['totals']['done'] ) . ' / ' . number_format_i18n( $r['totals']['total'] ) . ' locations done.</p>';
	}

	if ( 'error' === $r['state'] ) {
		// Non-credit errors (network blip, a transient Serper hiccup) shouldn't
		// need a human to notice and reload the tab - the cell is already left
		// Pending (safe to retry, nothing lost) and a failed request costs no
		// credits, so auto-retry with a backoff is safe. mfa_crawl_notify()
		// already emailed on the way in here (de-duped hourly) if this turns
		// out to be a real, persistent problem rather than a one-off.
		$next_url = mfa_admin_crawler_start_next_url( $cc, $lat, $lng, $radius, $city_label );
		ob_start();
		?>
		<div class="mfa-crawler-banner is-paused">&#9208; Error &mdash; <?php echo esc_html( $r['message'] ); ?></div>
		<p class="mfa-crawler-hint">Location <?php echo esc_html( $r['geohash'] ); ?> stays queued and will be retried automatically.</p>
		<script>setTimeout( function () { location.href = <?php echo wp_json_encode( $next_url ); ?>; }, <?php echo (int) wp_rand( 15000, 30000 ); ?> );</script>
		<?php
		return ob_get_clean();
	}

	// 'ok' - show what just happened and self-redirect to claim the next cell.
	$cell     = $r['cell'];
	$res      = $r['result'];
	$totals   = $r['totals'];
	$next_url = mfa_admin_crawler_start_next_url( $cc, $lat, $lng, $radius, $city_label );

	ob_start();
	?>
	<p class="mfa-crawler-hint">
		<?php echo esc_html( $cell['geohash'] ); ?>: +<?php echo (int) $res['mosque_new']; ?> mosques, +<?php echo (int) $res['business_new']; ?> businesses.
		<?php echo esc_html( $label ); ?>: <?php echo number_format_i18n( $totals['done'] ); ?> / <?php echo number_format_i18n( $totals['total'] ); ?> done.
		Loading the next location&hellip;
	</p>
	<script>setTimeout( function () { location.href = <?php echo wp_json_encode( $next_url ); ?>; }, <?php echo (int) wp_rand( 400, 1400 ); ?> );</script>
	<?php
	return ob_get_clean();
}
