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

function mfa_admin_website_generate_start_attempt() {
	global $wpdb;
	$table    = $wpdb->prefix . 'jet_cct_web';
	$next_url = home_url( '/admin/website/generate/' );

	$row = $wpdb->get_row( "SELECT _ID, name, cct_single_post_id FROM {$table} WHERE listing_status IN ('New','Pending') AND cct_single_post_id IS NOT NULL ORDER BY _ID ASC LIMIT 1", ARRAY_A );

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

	$result = website_update_content( (int) $row['cct_single_post_id'] );

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
