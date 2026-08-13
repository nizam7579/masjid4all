<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * [mfa_admin_crawler] - the /admin/crawler/ overview for the Serper
 * directory-build pipeline (see includes/geohash.php + geohash-crawl.php).
 * The grid itself (seed from cities, fold mosque/business counts, seed US)
 * is a one-time setup step already done on this site - see
 * mfa_geohash_seed_cities()/_seed_counts()/_seed_us() if it's ever needed
 * again (e.g. rebuilding after a fresh live deploy), but there's no button
 * for it here now that it's routine.
 *
 * Actual crawling happens on the child page /admin/crawler/start/ (see
 * admin-crawler-start.php) - this page just shows where things stand and
 * links out to start a crawl. No AJAX/JS: every action here is a plain
 * GET/POST form, since a full page reload is all any of these need.
 */

add_shortcode( 'mfa_admin_crawler', 'mfa_admin_crawler_shortcode' );
function mfa_admin_crawler_shortcode() {
	if ( ! current_user_can( 'manage_options' ) ) {
		return '<p>You do not have permission to view this page.</p>';
	}

	$notice    = mfa_admin_crawler_handle_form();
	$status    = mfa_geohash_crawl_status();
	$countries = $status['countries'];
	$start_url = home_url( '/admin/crawler/start/' );

	if ( ! $status['table_exists'] ) {
		return '<div class="mfa-crawler"><h1 class="mfa-h2">Directory Crawler</h1><p class="mfa-crawler-hint">Grid table not found.</p></div>';
	}

	ob_start();
	?>
	<div class="mfa-crawler">
		<h1 class="mfa-h2">Directory Crawler</h1>

		<?php if ( $notice ) : ?>
			<p class="mfa-crawler-hint"><?php echo esc_html( $notice ); ?></p>
		<?php endif; ?>

		<?php if ( $status['paused'] ) : ?>
			<div class="mfa-crawler-banner is-paused">
				&#9208; Paused &mdash; <?php echo esc_html( $status['pause_reason'] ); ?>
				<form method="post" class="mfa-crawler-inline-form">
					<?php wp_nonce_field( 'mfa_crawler_action', 'mfa_crawler_nonce' ); ?>
					<input type="hidden" name="mfa_crawler_op" value="resume">
					<button class="mfa-crawler-btn mfa-crawler-btn-resume" type="submit">Resume</button>
				</form>
			</div>
		<?php endif; ?>

		<?php
		$st = $status['by_status'];
		$cards = array(
			array( $status['total'], 'Total locations' ),
			array( $st['Done'], 'Done' ),
			array( $status['total'] - $st['Done'], 'Balance' ),
			array( $status['mosque_total'], 'Mosques' ),
			array( $status['business_total'], 'Businesses' ),
		);
		?>
		<div class="mfa-crawler-cards">
			<?php foreach ( $cards as $c ) : ?>
				<div class="mfa-crawler-card"><span class="mfa-crawler-card-num"><?php echo number_format_i18n( $c[0] ); ?></span><span class="mfa-crawler-card-lbl"><?php echo esc_html( $c[1] ); ?></span></div>
			<?php endforeach; ?>
		</div>

		<?php
		$used = (int) $status['credits_used'];
		$bud  = (int) $status['credits_budget'];
		$pct  = $bud ? min( 100, round( $used / $bud * 100 ) ) : 0;
		?>
		<div class="mfa-crawler-credits">
			<div class="mfa-crawler-credit-head">
				<span><strong><?php echo number_format_i18n( $used ); ?></strong> credits used &middot; <strong><?php echo number_format_i18n( $status['credits_remaining'] ); ?></strong> remaining</span>
				<span>budget <?php echo number_format_i18n( $bud ); ?> &middot; ~<?php echo (int) $status['credits_per_cell']; ?>/cell</span>
			</div>
			<div class="mfa-crawler-bar"><span style="width:<?php echo (int) $pct; ?>%"></span></div>
		</div>

		<div class="mfa-crawler-section">
			<h3 class="mfa-h3">Start crawl now</h3>
			<form method="get" action="<?php echo esc_url( $start_url ); ?>" target="_blank" class="mfa-crawler-row">
				<select name="country">
					<?php foreach ( $countries as $cc => $d ) : ?>
						<option value="<?php echo esc_attr( $cc ); ?>"><?php echo esc_html( $d['name'] . ' (' . $cc . ')' ); ?></option>
					<?php endforeach; ?>
				</select>
				<button class="mfa-crawler-btn mfa-crawler-btn-primary" type="submit">Start crawl now &rarr;</button>
			</form>
			<p class="mfa-crawler-hint">Opens in a new tab and indexes one location at a time for that country, moving to the next automatically. Open several tabs (same or different countries) to run in parallel &mdash; each tab claims a different location, so they never collide.</p>
		</div>

		<div class="mfa-crawler-section">
			<h3 class="mfa-h3">Coverage by country</h3>
			<table class="mfa-crawler-table">
				<thead><tr><th>Country</th><th>Locations</th><th>Done</th><th>Balance</th><th>Mosques</th><th>Businesses</th></tr></thead>
				<tbody>
					<?php foreach ( $countries as $cc => $d ) :
						$balance = $d['locations'] - $d['done'];
						?>
						<tr>
							<td><?php echo esc_html( $d['name'] . ' (' . $cc . ')' ); ?></td>
							<td><?php echo number_format_i18n( $d['locations'] ); ?></td>
							<td><?php echo number_format_i18n( $d['done'] ); ?></td>
							<td><?php echo number_format_i18n( $balance ); ?></td>
							<td><?php echo number_format_i18n( $d['mosque'] ); ?></td>
							<td><?php echo number_format_i18n( $d['business'] ); ?></td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		</div>

		<div class="mfa-crawler-section">
			<h3 class="mfa-h3">Credit budget</h3>
			<form method="post" class="mfa-crawler-row">
				<?php wp_nonce_field( 'mfa_crawler_action', 'mfa_crawler_nonce' ); ?>
				<input type="hidden" name="mfa_crawler_op" value="save_budget">
				<label>Budget <input type="number" name="budget" value="<?php echo esc_attr( $bud ); ?>" min="0"></label>
				<label>Credits / cell <input type="number" name="per_cell" value="<?php echo esc_attr( $status['credits_per_cell'] ); ?>" min="1"></label>
				<label>Max concurrent tabs <input type="number" name="max_concurrent" value="<?php echo esc_attr( mfa_crawl_max_concurrent() ); ?>" min="1" max="20"></label>
				<button class="mfa-crawler-btn" type="submit">Save</button>
			</form>
			<form method="post" class="mfa-crawler-row">
				<?php wp_nonce_field( 'mfa_crawler_action', 'mfa_crawler_nonce' ); ?>
				<input type="hidden" name="mfa_crawler_op" value="reset_used">
				<button class="mfa-crawler-btn" type="submit">Reset used counter</button>
			</form>
			<p class="mfa-crawler-hint">After topping up Serper, raise the budget (and/or reset the used counter), then Resume above. "Max concurrent tabs" caps how many crawl tabs actually hit the database/Serper at once - extra tabs just wait their turn - so running many tabs in parallel doesn't overload the host's MySQL connection limit (the cause of "Error establishing a database connection" if it happens site-wide while crawling).</p>
		</div>
	</div>
	<?php
	return ob_get_clean();
}

/**
 * Handles the overview page's plain POST forms (resume / save budget / reset
 * counter) - no admin-ajax.php, the page just re-renders after the redirect.
 * Returns a short notice string for the next render, or ''.
 */
function mfa_admin_crawler_handle_form() {
	if ( empty( $_POST['mfa_crawler_op'] ) || ! current_user_can( 'manage_options' ) ) {
		return '';
	}
	if ( ! isset( $_POST['mfa_crawler_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['mfa_crawler_nonce'] ) ), 'mfa_crawler_action' ) ) {
		return 'Security check failed - please try again.';
	}

	$op = sanitize_key( $_POST['mfa_crawler_op'] );
	switch ( $op ) {
		case 'resume':
			mfa_crawl_resume();
			return 'Crawler resumed.';

		case 'save_budget':
			if ( isset( $_POST['budget'] ) ) {
				mfa_crawl_set( 'credits_budget', max( 0, (int) $_POST['budget'] ) );
			}
			if ( isset( $_POST['per_cell'] ) ) {
				mfa_crawl_set( 'credits_per_cell', max( 1, (int) $_POST['per_cell'] ) );
			}
			if ( isset( $_POST['max_concurrent'] ) ) {
				mfa_crawl_set( 'max_concurrent', max( 1, min( 20, (int) $_POST['max_concurrent'] ) ) );
			}
			return 'Budget saved.';

		case 'reset_used':
			mfa_crawl_set( 'credits_used', 0 );
			return 'Used counter reset to 0.';
	}
	return '';
}
