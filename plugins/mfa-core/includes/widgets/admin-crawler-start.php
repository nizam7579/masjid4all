<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * [mfa_admin_crawler_start] - /admin/crawler/start/, a child page of
 * /admin/crawler/. Claims and crawls exactly ONE non-Done cell for the
 * selected country per page load (see mfa_geohash_crawl_claim_and_run_one()
 * in geohash-crawl.php), then redirects itself back to the same URL after a
 * short pause. No AJAX/JS loop - each cycle is a full page load, so several
 * tabs (or several computers) can each hold one open per country and just
 * keep indexing, matching how this crawl was driven manually before this
 * page existed. A page reload is also immune to the browser silently
 * discarding a backgrounded tab's JS state, which an in-page loop is not.
 */

add_shortcode( 'mfa_admin_crawler_start', 'mfa_admin_crawler_start_shortcode' );
function mfa_admin_crawler_start_shortcode() {
	if ( ! current_user_can( 'manage_options' ) ) {
		return '<p>You do not have permission to view this page.</p>';
	}

	$countries = mfa_geohash_country_summary();
	$cc        = isset( $_GET['country'] ) ? strtoupper( sanitize_text_field( wp_unslash( $_GET['country'] ) ) ) : '';
	$overview_url = home_url( '/admin/crawler/' );

	ob_start();
	?>
	<div class="mfa-crawler">
		<h1 class="mfa-h2">Start Crawl<?php echo ( $cc && isset( $countries[ $cc ] ) ) ? ' &mdash; ' . esc_html( $countries[ $cc ]['name'] ) : ''; ?></h1>

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
			echo mfa_admin_crawler_start_render( $cc, $countries[ $cc ]['name'] );
		endif;
		?>

		<p class="mfa-crawler-hint"><a href="<?php echo esc_url( $overview_url ); ?>">&larr; Back to overview</a></p>
	</div>
	<?php
	return ob_get_clean();
}

function mfa_admin_crawler_start_render( $cc, $label ) {
	$r = mfa_geohash_crawl_claim_and_run_one( $cc );

	if ( 'paused' === $r['state'] ) {
		return '<div class="mfa-crawler-banner is-paused">&#9208; Paused &mdash; ' . esc_html( $r['reason'] ) . '</div>'
			. '<p class="mfa-crawler-hint">Resolve this on the <a href="' . esc_url( home_url( '/admin/crawler/' ) ) . '">overview page</a>, then reload this tab to resume.</p>';
	}

	if ( 'busy' === $r['state'] ) {
		// Every slot is taken by other tabs right now - retry shortly rather
		// than doing any DB/Serper work. Jittered so tabs that lost the race
		// together don't all retry in lockstep and re-collide on the retry.
		$next_url = add_query_arg( 'country', $cc, home_url( '/admin/crawler/start/' ) );
		ob_start();
		?>
		<p class="mfa-crawler-hint">All crawl slots are busy right now &mdash; waiting for one to free up&hellip;</p>
		<script>setTimeout( function () { location.href = <?php echo wp_json_encode( $next_url ); ?>; }, <?php echo (int) wp_rand( 800, 2000 ); ?> );</script>
		<?php
		return ob_get_clean();
	}

	if ( 'done_all' === $r['state'] ) {
		return '<p class="mfa-crawler-hint">&#127881; ' . esc_html( $label ) . ' is fully crawled &mdash; '
			. number_format_i18n( $r['totals']['done'] ) . ' / ' . number_format_i18n( $r['totals']['total'] ) . ' locations done.</p>';
	}

	if ( 'error' === $r['state'] ) {
		return '<div class="mfa-crawler-banner is-paused">&#9208; Stopped &mdash; ' . esc_html( $r['message'] ) . '</div>'
			. '<p class="mfa-crawler-hint">Location ' . esc_html( $r['geohash'] ) . ' stays queued and will be retried once this is resolved.</p>';
	}

	// 'ok' - show what just happened and self-redirect to claim the next cell.
	$cell     = $r['cell'];
	$res      = $r['result'];
	$totals   = $r['totals'];
	$next_url = add_query_arg( 'country', $cc, home_url( '/admin/crawler/start/' ) );

	ob_start();
	?>
	<p class="mfa-crawler-hint">
		<?php echo esc_html( $cell['geohash'] ); ?>: +<?php echo (int) $res['mosque_new']; ?> mosques, +<?php echo (int) $res['business_new']; ?> businesses.
		<?php echo esc_html( $label ); ?>: <?php echo number_format_i18n( $totals['done'] ); ?> / <?php echo number_format_i18n( $totals['total'] ); ?> done.
		Loading the next location&hellip;
	</p>
	<script>setTimeout( function () { location.href = <?php echo wp_json_encode( $next_url ); ?>; }, 600 );</script>
	<?php
	return ob_get_clean();
}
