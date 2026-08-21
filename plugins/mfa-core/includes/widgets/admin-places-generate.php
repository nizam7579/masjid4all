<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * [mfa_admin_places_generate] - /admin/crawler/places/, the runner that writes
 * the editorial intro on each /places/ country and state hub.
 *
 * Same self-redirecting, one-record-per-page-load pattern as
 * /admin/crawler/start/ and /admin/website/generate/: each load claims exactly
 * one hub, generates its intro, shows the result, then redirects back to itself.
 * Opened in a tab and watched; closing the tab is the stop button.
 *
 * It lives under /admin/crawler/ rather than a new /admin/places/ section
 * because it is a bulk generation tool, which is what that section already
 * holds, and adding a top-level section would mean a new entry in the admin
 * nav and in admin-access-control.php for a job that runs 82 times and is then
 * finished.
 *
 * The claim is post meta rather than the claim_token COLUMNS the website
 * generator adds to jet_cct_web - these are WordPress posts, so there is a meta
 * table already and no ALTER to run. A claim older than ten minutes is up for
 * grabs again, so closing the tab mid-hub releases it instead of stranding it.
 *
 * Administrator-only, checked here rather than only behind a link: hiding a
 * link is not a control, and this one spends money and rewrites published
 * content.
 */

const MFA_PLACES_GENERATE_CLAIM_TTL = 10 * MINUTE_IN_SECONDS;

add_shortcode( 'mfa_admin_places_generate', 'mfa_admin_places_generate_shortcode' );
function mfa_admin_places_generate_shortcode() {
	if ( ! current_user_can( 'administrator' ) ) {
		return '<p class="mfa-body-muted">Only an administrator can generate place content.</p>';
	}

	ob_start();
	?>
	<div class="mfa-crawler">
		<h1 class="mfa-h2">Generate Place Intros</h1>
		<?php echo mfa_admin_places_generate_render(); ?>
		<p class="mfa-crawler-hint">Countries and states only &mdash; city hubs are deliberately skipped. Close this tab any time to stop. <a href="<?php echo esc_url( home_url( '/places/' ) ); ?>" target="_blank" rel="noopener">View /places/ &rarr;</a></p>
	</div>
	<?php
	return ob_get_clean();
}

/**
 * Catches genuine PHP-level failures, the same way the crawler and website
 * runners do - an unhandled Throwable would otherwise fatal the page out with
 * no output at all, including no redirect, silently killing the tab.
 */
function mfa_admin_places_generate_render() {
	try {
		return mfa_admin_places_generate_attempt();
	} catch ( Throwable $e ) {
		$next_url = home_url( '/admin/crawler/places/' );
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
 * Takes one hub for this tab, or 0 when there is nothing left.
 *
 * Claims are checked and stamped one at a time rather than in a single UPDATE:
 * unlike the website generator's CCT table there is no single column to race
 * on, and at 82 records with one operator watching a tab, the simple version is
 * the honest one. The stamp still means a second tab skips what the first took.
 */
function mfa_admin_places_generate_claim() {
	$cutoff = time() - MFA_PLACES_GENERATE_CLAIM_TTL;

	foreach ( mfa_place_content_target_ids() as $id ) {
		if ( '' !== trim( (string) get_post_field( 'post_content', $id ) ) ) {
			continue;
		}

		$claimed_at = (int) get_post_meta( $id, '_mfa_place_content_claimed', true );
		if ( $claimed_at && $claimed_at > $cutoff ) {
			continue;
		}

		update_post_meta( $id, '_mfa_place_content_claimed', time() );

		return (int) $id;
	}

	return 0;
}

/** Releases a claim so a hub that did not get written is not locked out. */
function mfa_admin_places_generate_release( $id ) {
	delete_post_meta( (int) $id, '_mfa_place_content_claimed' );
}

function mfa_admin_places_generate_attempt() {
	$next_url = home_url( '/admin/crawler/places/' );

	if ( ! function_exists( 'mfa_place_content_generate' ) ) {
		return '<div class="mfa-crawler-banner is-paused">&#9208; Generation is unavailable (place-content.php not loaded).</div>';
	}

	if ( '' === mfa_place_content_deepseek_key() ) {
		return '<div class="mfa-crawler-banner is-paused">&#9208; No DeepSeek API key &mdash; set DEEPSEEK_API_KEY in wp-config.php.</div>';
	}

	$id = mfa_admin_places_generate_claim();

	if ( ! $id ) {
		return '<p class="mfa-crawler-hint">&#127881; All done &mdash; every country and state hub has an intro.</p>';
	}

	$name     = get_the_title( $id );
	$view_url = get_permalink( $id );
	$result   = mfa_place_content_generate( $id );

	if ( is_wp_error( $result ) ) {
		// Released, not left claimed: the hub still has no content, so it must
		// come round again rather than being quietly dropped from the run. The
		// ten-minute TTL means a hub that fails repeatedly does not spin - it
		// is retried once the operator has had time to notice the banner.
		mfa_admin_places_generate_release( $id );

		ob_start();
		?>
		<div class="mfa-crawler-banner is-paused">
			&#9208; "<?php echo esc_html( $name ); ?>" failed: <?php echo esc_html( $result->get_error_message() ); ?>
		</div>
		<p class="mfa-crawler-hint">Retrying in a moment. Close the tab to stop.</p>
		<script>setTimeout( function () { location.href = <?php echo wp_json_encode( $next_url ); ?>; }, <?php echo (int) wp_rand( 20000, 30000 ); ?> );</script>
		<?php
		return ob_get_clean();
	}

	mfa_admin_places_generate_release( $id );

	$remaining = mfa_place_content_remaining();

	ob_start();
	?>
	<p class="mfa-crawler-hint">
		&#9989; "<?php echo esc_html( $name ); ?>" &mdash; <?php echo (int) $result['words']; ?> words written.
		<?php if ( $view_url ) : ?>
			<a href="<?php echo esc_url( $view_url ); ?>" target="_blank" rel="noopener">Read it &rarr;</a>
		<?php endif; ?>
		<?php echo number_format_i18n( $remaining ); ?> more to go. Loading the next hub&hellip;
	</p>
	<blockquote class="mfa-crawler-hint"><?php echo esc_html( $result['excerpt'] ); ?></blockquote>
	<script>setTimeout( function () { location.href = <?php echo wp_json_encode( $next_url ); ?>; }, <?php echo (int) wp_rand( 1200, 2200 ); ?> );</script>
	<?php
	return ob_get_clean();
}
