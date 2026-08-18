<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * [mfa_admin_website_generate_start] - /admin/website/generate/, a child
 * page of /admin/website/. Same self-redirecting, one-record-per-page-load
 * pattern as /admin/crawler/start/ (see admin-crawler-start.php): each
 * load claims and generates content for exactly ONE website whose
 * listing_status is New or Pending (oldest _ID first), shows the result,
 * then redirects itself back to this same URL after a short pause. Opened
 * in a new tab from the "Generate Content" button on /admin/website/
 * (target="_blank", matching the crawler overview page's "Start crawl
 * now" link) so the admin can watch progress live and stop at any point
 * just by closing the tab - no separate stop button needed.
 *
 * Calls website_update_content() (enaizi-mfa/shortcodes/website.php) -
 * the proven, live generator, NOT web_update_content() (web.php) - see
 * admin-website-list.php's docblock and [[project_website_extract]] for
 * the full history of why.
 *
 * On is_wp_error() from the generator (missing CCT, Perplexity network/
 * JSON failure, etc.) this marks the record's listing_status as 'Error'
 * itself before moving on - website_update_content() only does that for
 * its own "AI returned empty content" case, and every other WP_Error path
 * leaves the record untouched at New/Pending. Without this, the next
 * iteration's query would keep re-selecting the SAME broken record
 * forever instead of making it through the rest of the queue.
 */

add_shortcode( 'mfa_admin_website_generate_start', 'mfa_admin_website_generate_start_shortcode' );
function mfa_admin_website_generate_start_shortcode() {
	// Administrator-only, tighter than the rest of the website section: this
	// rewrites listing content and status in a loop, so it is not something to
	// leave one URL away for every Editor and Helpline user with page access.
	// Checked here as well as behind the button, because hiding a button is not
	// a control - the page can be opened directly.
	if ( function_exists( 'mfa_admin_website_tools_allowed' ) ) {
		if ( ! mfa_admin_website_tools_allowed() ) {
			return '<p class="mfa-body-muted">Only an administrator can generate website content.</p>';
		}
	} elseif ( ! current_user_can( 'administrator' ) ) {
		return '<p class="mfa-body-muted">Only an administrator can generate website content.</p>';
	}

	$list_url = home_url( '/admin/website/' );

	ob_start();
	?>
	<div class="mfa-crawler">
		<h1 class="mfa-h2">Generate Website Content</h1>
		<?php echo mfa_admin_website_generate_start_render(); ?>
		<p class="mfa-crawler-hint">Close this tab any time to stop. <a href="<?php echo esc_url( $list_url ); ?>">&larr; Back to website list</a></p>
	</div>
	<?php
	return ob_get_clean();
}

/**
 * Catches genuine PHP-level failures the same way
 * mfa_admin_crawler_start_render() does - without this, an unhandled
 * Throwable here would fatal the whole page out with no output at all,
 * including no redirect script, silently killing the tab for good.
 */
function mfa_admin_website_generate_start_render() {
	try {
		return mfa_admin_website_generate_start_attempt();
	} catch ( Throwable $e ) {
		$next_url = home_url( '/admin/website/generate/' );
		ob_start();
		?>
		<div class="mfa-crawler-banner is-paused">&#9208; Unexpected error &mdash; retrying automatically.</div>
		<p class="mfa-crawler-hint"><?php echo esc_html( $e->getMessage() ); ?></p>
		<script>setTimeout( function () { location.href = <?php echo wp_json_encode( $next_url ); ?>; }, <?php echo (int) wp_rand( 15000, 30000 ); ?> );</script>
		<?php
		return ob_get_clean();
	}
}


/**
 * Does this site answer at all?
 *
 * Reuses mfa_web_linkcheck_classify() so there is one definition of what a
 * response means, rather than a second opinion drifting away from the first.
 * Retries once on a soft failure: a single timeout is not evidence a site is
 * dead, and parking a working listing at Error is worse than spending another
 * second checking. A hard 404 or DNS failure is not retried - it will not
 * change.
 *
 * @return array ok (bool), status (string) - the code or ERR:n to record.
 */
/**
 * Claim columns, so two tabs cannot generate the same record.
 *
 * Verified on every load rather than trusted to a version option - the same
 * reasoning as the state and http_* columns, and the same failure they were
 * bitten by.
 */
add_action( 'plugins_loaded', 'mfa_web_generate_maybe_add_claim_columns' );
function mfa_web_generate_maybe_add_claim_columns() {
	global $wpdb;
	$table = $wpdb->prefix . 'jet_cct_web';

	$wanted = array(
		'claimed_at'  => 'ADD COLUMN claimed_at DATETIME NULL',
		'claim_token' => 'ADD COLUMN claim_token VARCHAR(32) NULL',
	);

	$missing = array();
	foreach ( $wanted as $col => $clause ) {
		if ( ! $wpdb->get_var( $wpdb->prepare( 'SHOW COLUMNS FROM ' . $table . ' LIKE %s', $col ) ) ) {
			$missing[] = $clause;
		}
	}
	if ( $missing ) {
		$wpdb->query( "ALTER TABLE {$table} " . implode( ', ', $missing ) );
		$wpdb->query( "ALTER TABLE {$table} ADD INDEX idx_claim_token (claim_token)" );
	}
}

/**
 * Takes exactly one record for this tab, or null when there is nothing to take.
 *
 * The claim is a single UPDATE ... ORDER BY ... LIMIT 1 stamped with a token
 * unique to this page load, so two tabs racing cannot both win the same row -
 * whichever UPDATE lands second simply matches a different record.
 *
 * It deliberately does NOT mark the row with a "Generating" listing_status,
 * which was the obvious approach. website_update_content() reads the previous
 * status and awards the contributor 10 Barakah points when a New or Pending
 * record becomes Approved; a status of "Generating" would fail that test and
 * silently stop paying people for their submissions.
 *
 * A claim older than ten minutes is up for grabs again, so closing a tab
 * mid-record releases it rather than stranding it. Ten minutes is generous
 * against a 12-26 second generation plus a retry.
 */
function mfa_admin_website_generate_claim() {
	global $wpdb;
	$table = $wpdb->prefix . 'jet_cct_web';
	$token = wp_generate_password( 32, false, false );

	$claimed = $wpdb->query(
		$wpdb->prepare(
			"UPDATE {$table}
			 SET claimed_at = %s, claim_token = %s
			 WHERE listing_status IN ('New','Pending')
			   AND cct_single_post_id IS NOT NULL
			   AND ( claimed_at IS NULL OR claimed_at < %s )
			 ORDER BY _ID ASC
			 LIMIT 1",
			current_time( 'mysql' ),
			$token,
			gmdate( 'Y-m-d H:i:s', time() - 10 * MINUTE_IN_SECONDS )
		)
	);

	if ( ! $claimed ) {
		return null;
	}

	return $wpdb->get_row(
		$wpdb->prepare( "SELECT _ID, name, url, cct_single_post_id FROM {$table} WHERE claim_token = %s LIMIT 1", $token ),
		ARRAY_A
	);
}

/** Releases a claim so a record that stayed New/Pending is not locked out. */
function mfa_admin_website_generate_release( $id ) {
	global $wpdb;
	$wpdb->update(
		$wpdb->prefix . 'jet_cct_web',
		array( 'claimed_at' => null, 'claim_token' => null ),
		array( '_ID' => (int) $id )
	);
}

function mfa_admin_website_generate_precheck( $url ) {
	$url = trim( (string) $url );
	if ( '' === $url ) {
		return array( 'ok' => false, 'status' => 'no-url' );
	}
	if ( ! preg_match( '#^https?://#i', $url ) ) {
		$url = 'https://' . $url;
	}

	// Without the checker loaded we cannot classify, so let the record through
	// rather than marking it Error on the strength of a missing dependency.
	if ( ! function_exists( 'mfa_web_linkcheck_classify' ) ) {
		return array( 'ok' => true, 'status' => 'unchecked' );
	}

	for ( $attempt = 1; $attempt <= 2; $attempt++ ) {
		$ch = curl_init( $url );
		curl_setopt_array( $ch, array(
			CURLOPT_NOBODY         => true,
			CURLOPT_FOLLOWLOCATION => true,
			CURLOPT_MAXREDIRS      => 4,
			CURLOPT_TIMEOUT        => 12,
			CURLOPT_CONNECTTIMEOUT => 6,
			CURLOPT_SSL_VERIFYPEER => false,
			CURLOPT_SSL_VERIFYHOST => 0,
			CURLOPT_RETURNTRANSFER => true,
			CURLOPT_USERAGENT      => 'Mozilla/5.0 (compatible; Masjid4AllLinkCheck/1.0; +' . home_url() . ')',
		) );
		curl_exec( $ch );
		$code  = (int) curl_getinfo( $ch, CURLINFO_RESPONSE_CODE );
		$errno = (int) curl_errno( $ch );
		curl_close( $ch );

		if ( ! $errno && ! $code ) {
			$errno = 7;
		}

		$verdict = mfa_web_linkcheck_classify( $code, $errno );
		$status  = $errno ? 'ERR:' . $errno : (string) $code;

		// Blocked counts as reachable - see the note at the call site.
		if ( 'alive' === $verdict || 'blocked' === $verdict ) {
			return array( 'ok' => true, 'status' => $status );
		}

		// A 404 or 410 is settled; only soft failures earn a second look.
		if ( 'dead' === $verdict ) {
			return array( 'ok' => false, 'status' => $status );
		}
	}

	return array( 'ok' => false, 'status' => isset( $status ) ? $status : 'unreachable' );
}

function mfa_admin_website_generate_start_attempt() {
	global $wpdb;
	$table    = $wpdb->prefix . 'jet_cct_web';
	$next_url = home_url( '/admin/website/generate/' );

	$row = mfa_admin_website_generate_claim();

	if ( ! $row ) {
		$stuck = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table} WHERE listing_status IN ('New','Pending')" );
		if ( $stuck > 0 ) {
			return '<p class="mfa-crawler-hint">&#127881; No more generatable records - ' . number_format_i18n( $stuck ) . ' New/Pending row(s) remain but have no linked post to update.</p>';
		}
		return '<p class="mfa-crawler-hint">&#127881; All done - no websites left with New or Pending status.</p>';
	}

	$view_url = get_permalink( (int) $row['cct_single_post_id'] );

	if ( ! function_exists( 'website_update_content' ) ) {
		return '<div class="mfa-crawler-banner is-paused">&#9208; Content generation is unavailable (website_update_content() missing).</div>';
	}

	// Check the site answers before paying to have it read. A generation call
	// costs about $0.0083, two thirds of which is a flat per-request fee that
	// applies whether the site responds or not, and takes 12-26 seconds. A HEAD
	// request costs nothing and takes under a second, so the roughly one site in
	// ten that is dead is now skipped rather than billed for.
	//
	// A 403 deliberately does NOT skip: around one site in eight refuses our
	// HEAD while being perfectly alive, and Perplexity fetches with its own
	// infrastructure, so it may well read a page we are refused.
	$precheck = mfa_admin_website_generate_precheck( $row['url'] );

	if ( ! $precheck['ok'] ) {
		$wpdb->update(
			$table,
			array(
				'listing_status' => 'Error',
				'status_detail'  => 'Unreachable before generation: ' . $precheck['status'],
				'http_status'    => $precheck['status'],
				'http_checked'   => current_time( 'mysql' ),
			),
			array( '_ID' => (int) $row['_ID'] )
		);

		mfa_admin_website_generate_release( $row['_ID'] );

		ob_start();
		?>
		<div class="mfa-crawler-banner is-paused">
			&#9208; "<?php echo esc_html( $row['name'] ); ?>" is unreachable (<?php echo esc_html( $precheck['status'] ); ?>) &mdash; marked Error without calling the generator.
		</div>
		<script>setTimeout( function () { location.href = <?php echo wp_json_encode( $next_url ); ?>; }, <?php echo (int) wp_rand( 800, 1600 ); ?> );</script>
		<?php
		return ob_get_clean();
	}

	$result = website_update_content( (int) $row['cct_single_post_id'] );

	// Released either way: the record has left New/Pending so it will not be
	// re-selected, but leaving a stale claim behind would confuse anyone
	// reading the table later.
	mfa_admin_website_generate_release( $row['_ID'] );

	if ( is_wp_error( $result ) ) {
		$wpdb->update(
			$table,
			array(
				'listing_status' => 'Error',
				'status_detail'  => $result->get_error_message(),
			),
			array( '_ID' => (int) $row['_ID'] )
		);

		ob_start();
		?>
		<div class="mfa-crawler-banner is-paused">
			&#9208; "<?php echo esc_html( $row['name'] ); ?>" failed: <?php echo esc_html( $result->get_error_message() ); ?> &mdash; marked Error, moving on.
			<?php if ( $view_url ) : ?>
				<a href="<?php echo esc_url( $view_url ); ?>" target="_blank" rel="noopener">View &rarr;</a>
			<?php endif; ?>
		</div>
		<script>setTimeout( function () { location.href = <?php echo wp_json_encode( $next_url ); ?>; }, <?php echo (int) wp_rand( 1500, 3000 ); ?> );</script>
		<?php
		return ob_get_clean();
	}

	$remaining = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table} WHERE listing_status IN ('New','Pending')" );

	ob_start();
	?>
	<p class="mfa-crawler-hint">
		&#9989; "<?php echo esc_html( $row['name'] ); ?>" &mdash; new status: <strong><?php echo esc_html( $result['status'] ); ?></strong>.
		<?php if ( $view_url ) : ?>
			<a href="<?php echo esc_url( $view_url ); ?>" target="_blank" rel="noopener">View &rarr;</a>
		<?php endif; ?>
		<?php echo number_format_i18n( $remaining ); ?> more to go. Loading the next record&hellip;
	</p>
	<script>setTimeout( function () { location.href = <?php echo wp_json_encode( $next_url ); ?>; }, <?php echo (int) wp_rand( 800, 1600 ); ?> );</script>
	<?php
	return ob_get_clean();
}
