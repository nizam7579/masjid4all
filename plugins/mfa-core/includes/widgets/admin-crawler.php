<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * [mfa_admin_crawler] - the /admin/crawler/ control panel for the Serper
 * directory-build pipeline (see includes/geohash.php + geohash-crawl.php).
 * Drives every stage as a button so the whole grid can be (re)built and
 * crawled on live with no DB push: seed from cities -> fold mosque/business
 * counts -> seed US -> queue a country -> run batches / enable the cron.
 * Shows a live status board (cells by status + country, credit budget) and a
 * paused banner. Admin-only; all actions go through one nonce'd AJAX endpoint.
 */

add_shortcode( 'mfa_admin_crawler', 'mfa_admin_crawler_shortcode' );
function mfa_admin_crawler_shortcode() {
	if ( ! current_user_can( 'manage_options' ) ) {
		return '<p>You do not have permission to view this page.</p>';
	}

	$countries = array(
		'ID' => 'Indonesia',
		'GB' => 'United Kingdom',
		'AU' => 'Australia',
		'CA' => 'Canada',
		'MY' => 'Malaysia',
		'SG' => 'Singapore',
		'BN' => 'Brunei',
		'US' => 'United States',
	);
	$nonce    = wp_create_nonce( 'mfa_crawler' );
	$cron_cmd = 'cd ' . ABSPATH . ' && wp mfa geohash-cron';

	// Auto-mint the external-cron token on first view so live setup needs no
	// server access - just copy the URL below into cron-job.org. Per-site, so
	// staging and live get their own.
	$cron_token = (string) mfa_crawl_opt( 'cron_token', '' );
	if ( '' === $cron_token ) {
		$cron_token = wp_generate_password( 32, false );
		mfa_crawl_set( 'cron_token', $cron_token );
	}
	$trigger_url = home_url( '/wp-json/mfa/v1/crawl-run?token=' . $cron_token . '&limit=3' );

	ob_start();
	?>
	<div class="mfa-crawler" data-nonce="<?php echo esc_attr( $nonce ); ?>" data-ajax="<?php echo esc_url( admin_url( 'admin-ajax.php' ) ); ?>">
		<h1 class="mfa-h2">Directory Crawler</h1>

		<div class="mfa-crawler-banner" id="mfa-crawler-banner" hidden></div>

		<div class="mfa-crawler-cards" id="mfa-crawler-cards"></div>
		<div class="mfa-crawler-credits" id="mfa-crawler-credits"></div>

		<div class="mfa-crawler-section">
			<h3 class="mfa-h3">Coverage by country</h3>
			<div id="mfa-crawler-countries"></div>
		</div>

		<div class="mfa-crawler-section">
			<h3 class="mfa-h3">1 &middot; Build / refresh the grid</h3>
			<p class="mfa-crawler-hint">Idempotent &mdash; safe to re-run. Reads this site's own cities / mosque / business tables, so this is how you build the grid on live too.</p>
			<div class="mfa-crawler-btns">
				<button class="mfa-crawler-btn" data-op="seed_cities">Import cities &rarr; grid</button>
				<button class="mfa-crawler-btn" data-op="seed_counts">Fold mosque + business counts</button>
				<button class="mfa-crawler-btn" data-op="seed_us">Seed US grid</button>
			</div>
		</div>

		<div class="mfa-crawler-section">
			<h3 class="mfa-h3">2 &middot; Queue a country for crawling</h3>
			<div class="mfa-crawler-row">
				<select id="mfa-crawler-queue-country">
					<?php foreach ( $countries as $cc => $name ) : ?>
						<option value="<?php echo esc_attr( $cc ); ?>"><?php echo esc_html( $name . ' (' . $cc . ')' ); ?></option>
					<?php endforeach; ?>
				</select>
				<input type="number" id="mfa-crawler-queue-limit" value="1000" min="0" placeholder="cells (0 = all)">
				<button class="mfa-crawler-btn" data-op="queue">Queue &rarr; Pending</button>
			</div>
			<p class="mfa-crawler-hint">Flips New cells to Pending. 0 = the whole country.</p>
		</div>

		<div class="mfa-crawler-section">
			<h3 class="mfa-h3">3 &middot; Run a crawl batch now</h3>
			<div class="mfa-crawler-row">
				<input type="number" id="mfa-crawler-run-limit" value="5" min="1" max="50">
				<button class="mfa-crawler-btn mfa-crawler-btn-primary" data-op="run">Run batch (mosque + halal)</button>
				<span class="mfa-crawler-hint">~17s per cell &mdash; keep this small in the browser; use the cron for bulk.</span>
			</div>
		</div>

		<div class="mfa-crawler-section">
			<h3 class="mfa-h3">4 &middot; Automated cron</h3>
			<div class="mfa-crawler-row">
				<label><input type="checkbox" id="mfa-crawler-cron-enabled"> Cron enabled</label>
				<label>Batch size <input type="number" id="mfa-crawler-cron-size" value="5" min="1" max="50"></label>
				<label>Country
					<select id="mfa-crawler-cron-country">
						<option value="">All queued</option>
						<?php foreach ( $countries as $cc => $name ) : ?>
							<option value="<?php echo esc_attr( $cc ); ?>"><?php echo esc_html( $name ); ?></option>
						<?php endforeach; ?>
					</select>
				</label>
				<button class="mfa-crawler-btn" data-op="save_cron">Save cron settings</button>
			</div>
			<p class="mfa-crawler-hint"><strong>Recommended &mdash; external cron (reliable, host-independent).</strong> Point cron-job.org (or any external cron) at this URL every 1 minute. Keep the token private; raise <code>limit</code> to go faster:</p>
			<code class="mfa-crawler-cmd"><?php echo esc_html( $trigger_url ); ?></code>
			<p class="mfa-crawler-hint">Alternatively, a server cron (WP-CLI) &mdash; only if your host's scheduler is reliable:</p>
			<code class="mfa-crawler-cmd"><?php echo esc_html( $cron_cmd ); ?></code>
		</div>

		<div class="mfa-crawler-section">
			<h3 class="mfa-h3">Credit budget</h3>
			<div class="mfa-crawler-row">
				<label>Budget <input type="number" id="mfa-crawler-budget" min="0"></label>
				<label>Credits / cell <input type="number" id="mfa-crawler-percell" min="1"></label>
				<button class="mfa-crawler-btn" data-op="save_budget">Save</button>
				<button class="mfa-crawler-btn" data-op="reset_used">Reset used counter</button>
				<button class="mfa-crawler-btn mfa-crawler-btn-resume" data-op="resume" id="mfa-crawler-resume" hidden>Resume (clear pause)</button>
			</div>
			<p class="mfa-crawler-hint">After topping up Serper, raise the budget (and/or reset the used counter), then Resume.</p>
		</div>

		<div class="mfa-crawler-log" id="mfa-crawler-log" hidden></div>
	</div>
	<?php
	return ob_get_clean();
}

/**
 * Single nonce'd, admin-only AJAX endpoint for every panel action. Each op
 * runs its pipeline function and returns a fresh status snapshot so the board
 * re-renders.
 */
add_action( 'wp_ajax_mfa_admin_crawler_action', 'mfa_admin_crawler_ajax' );
function mfa_admin_crawler_ajax() {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_send_json_error( 'forbidden' );
	}
	check_ajax_referer( 'mfa_crawler', 'nonce' );

	$op  = isset( $_POST['op'] ) ? sanitize_key( $_POST['op'] ) : '';
	$msg = '';

	switch ( $op ) {
		case 'status':
			break;

		case 'seed_cities':
			$n   = mfa_geohash_seed_cities();
			$msg = 'Cities imported. Total cells: ' . number_format_i18n( $n ) . '.';
			break;

		case 'seed_counts':
			$r   = mfa_geohash_seed_counts();
			$msg = 'Counts folded: ' . number_format_i18n( $r['cells_with_mosque'] ) . ' cells with a mosque, ' . number_format_i18n( $r['cells_with_business'] ) . ' with a business.';
			break;

		case 'seed_us':
			$r   = mfa_geohash_seed_us();
			$msg = 'US seeded: ' . number_format_i18n( $r['us_cells_total'] ) . ' US cells (' . number_format_i18n( $r['grid_inserted'] ) . ' grid cells added).';
			break;

		case 'queue':
			$cc    = isset( $_POST['country'] ) ? strtoupper( sanitize_text_field( wp_unslash( $_POST['country'] ) ) ) : '';
			$limit = isset( $_POST['limit'] ) ? (int) $_POST['limit'] : 0;
			$n     = mfa_geohash_queue_country( $cc, $limit );
			$msg   = 'Queued ' . number_format_i18n( $n ) . ' cells in ' . $cc . '.';
			break;

		case 'run':
			$cc    = isset( $_POST['country'] ) ? strtoupper( sanitize_text_field( wp_unslash( $_POST['country'] ) ) ) : '';
			$limit = isset( $_POST['limit'] ) ? (int) $_POST['limit'] : 5;
			$r     = mfa_geohash_crawl_run_batch( $cc, $limit );
			$msg   = 'Ran ' . $r['processed'] . '/' . $r['queued_found'] . ' cells. +' . $r['mosque_new'] . ' mosques, +' . $r['business_new'] . ' businesses.';
			if ( ! empty( $r['stopped'] ) ) {
				$msg .= ' STOPPED: ' . $r['stopped'];
			}
			break;

		case 'resume':
			mfa_crawl_resume();
			$msg = 'Crawler resumed.';
			break;

		case 'save_cron':
			mfa_crawl_set( 'enabled', ( isset( $_POST['enabled'] ) && '1' === $_POST['enabled'] ) ? 1 : 0 );
			if ( isset( $_POST['batch_size'] ) ) {
				mfa_crawl_set( 'batch_size', max( 1, min( 50, (int) $_POST['batch_size'] ) ) );
			}
			if ( isset( $_POST['cron_country'] ) ) {
				mfa_crawl_set( 'country', strtoupper( sanitize_text_field( wp_unslash( $_POST['cron_country'] ) ) ) );
			}
			$msg = 'Cron settings saved.';
			break;

		case 'save_budget':
			if ( isset( $_POST['budget'] ) ) {
				mfa_crawl_set( 'credits_budget', max( 0, (int) $_POST['budget'] ) );
			}
			if ( isset( $_POST['per_cell'] ) ) {
				mfa_crawl_set( 'credits_per_cell', max( 1, (int) $_POST['per_cell'] ) );
			}
			$msg = 'Budget saved.';
			break;

		case 'reset_used':
			mfa_crawl_set( 'credits_used', 0 );
			$msg = 'Used counter reset to 0.';
			break;

		default:
			wp_send_json_error( 'unknown op' );
	}

	wp_send_json_success( array(
		'message' => $msg,
		'status'  => mfa_geohash_crawl_status(),
	) );
}
