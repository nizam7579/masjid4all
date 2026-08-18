<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * [mfa_directory_single] - the shared single-listing component for the
 * directory CPTs (masjid / business / web / city), replacing the Kadence
 * Theme Builder "Mosque" (875) / "Business" (9151) / "Web" (220902) / "City"
 * (225556) templates.
 *
 * One component, configured per post type: a header shortcode, a tabbed body
 * (each pane = one existing tab shortcode), an optional right-column sidebar
 * (ads), and an optional gated "generate AI content" action. Auto-detects the
 * current post type, so all the single-{type}.php theme templates just call
 * this.
 *
 * Layout: two columns (like the knowledge single) - the header/featured image,
 * the action and the tabbed content sit in the left column (.mfa-dir-main),
 * the ads sit in the right column (.mfa-dir-sidebar). See directory-single-v1.css.
 *
 * The AI-generate action reuses the existing per-type updater shortcodes
 * (niz_business_ai_updater / niz_web_ai_updater) but only renders them when
 * the listing's status is New/Pending AND the viewer owns the listing or is
 * an admin - replacing the old always-visible, nopriv buttons.
 */

function mfa_directory_single_config() {
	return array(
		'masjid'   => array(
			'header'        => 'niz_mfa_masjid_header',
			'tabs'          => array(
				array( 'Home', 'mfa_mosque_home_tab' ),
				array( 'Community', 'mosque_community' ),
				array( 'Local Business', 'mfa_mosque_local_business_tab' ),
				array( 'Review', 'niz_review' ),
			),
			'sidebar'       => '[enaizi_ads count="4" layout="vertical"]',
			// Full-width band under the listing body. Renders nothing unless this
			// mosque's country has a /places/ hub. Only masjid has one so far.
			'below'         => '[mfa_place_links]',
			'owner_col'     => 'cct_author_id',
			'action'        => null,
			// Matches mfa_mosque_info_display()'s own "actual content" gate.
			'live_statuses' => array( 'Approved', 'Active' ),
		),
		'business' => array(
			'header'        => 'niz_business_header',
			'tabs'          => array(
				array( 'Home', 'mfa_business_home_tab' ),
				array( 'Nearby Mosques', 'mfa_business_nearby_mosques_tab' ),
				array( 'Review', 'niz_review' ),
			),
			'sidebar'       => '[enaizi_ads count="4" layout="vertical"]',
			'owner_col'     => 'owner_id',
			'action'        => array( 'sc' => 'niz_business_ai_updater', 'when_status' => array( 'New', 'Pending' ) ),
			// Matches mfa_business_info_display()'s own "actual content" gate.
			'live_statuses' => array( 'Approved', 'Verified', 'Premium' ),
		),
		'web'      => array(
			'header'        => 'mfa_website_header',
			'tabs'          => array(
				array( 'Home', 'mfa_website_home_tab' ),
				array( 'Review', 'niz_review' ),
			),
			'sidebar'       => '[enaizi_ads count="4" layout="vertical"]',
			'owner_col'     => 'cct_author_id',
			'action'        => array( 'sc' => 'niz_web_ai_updater', 'when_status' => array( 'New', 'Pending' ) ),
			// Matches mfa_web_info_display()'s own "actual content" gate.
			'live_statuses' => array( 'Approved', 'Verified', 'Premium' ),
		),
		'city'     => array(
			'header'        => '',
			'tabs'          => array(
				array( 'Home', 'niz_mfa_business_info' ),
				array( 'Community', 'niz_review' ),
				array( 'Mosques', 'niz_mfa_local_mosques' ),
				array( 'Businesses', 'niz_mfa_local_business' ),
			),
			'sidebar'       => '',
			'owner_col'     => 'cct_author_id',
			'action'        => null,
			// No claim/approval workflow for city pages - always show the
			// full tab UI regardless of any status field.
			'live_statuses' => null,
		),
	);
}

/**
 * Read the listing's status + owner from its JetEngine CCT table, joined on
 * cct_single_post_id (the WP post this CPT single renders).
 */
function mfa_directory_single_record( $post_id, $type, $owner_col ) {
	global $wpdb;
	$cct   = ( 'masjid' === $type ) ? 'mosque' : $type; // masjid post type -> jet_cct_mosque
	$table = $wpdb->prefix . 'jet_cct_' . $cct;
	// $owner_col is from our own trusted config, not user input.
	$row = $wpdb->get_row(
		$wpdb->prepare(
			"SELECT listing_status AS status, `{$owner_col}` AS owner FROM `{$table}` WHERE cct_single_post_id = %d LIMIT 1",
			$post_id
		),
		ARRAY_A
	);
	return $row ?: array( 'status' => null, 'owner' => null );
}

/**
 * Render the gated AI-generate action, or '' if it shouldn't show.
 * Takes the already-fetched $rec (see mfa_directory_single_shortcode()) so
 * this doesn't re-query the CCT table a second time per page load.
 */
function mfa_directory_single_action( $rec, $cfg ) {
	if ( empty( $cfg['action'] ) ) {
		return '';
	}
	if ( ! in_array( $rec['status'], $cfg['action']['when_status'], true ) ) {
		return '';
	}
	$uid      = get_current_user_id();
	$is_owner = $uid && (int) $rec['owner'] === $uid;
	if ( ! $is_owner && ! current_user_can( 'manage_options' ) ) {
		return '';
	}
	return '<div class="mfa-dir-action">' . do_shortcode( '[' . $cfg['action']['sc'] . ']' ) . '</div>';
}

/**
 * Claim / Manage call-to-action for the single business & website Home tabs.
 * Claiming now happens entirely in WhatsApp (Sofia), so this replaces the old
 * on-page "log in to claim" form and the Claim tab:
 *   - Unclaimed listing         -> "Claim this <thing>" button that opens a
 *                                  Sofia popup deep-linking to WhatsApp
 *                                  (wa.me ...?text=claim business|website).
 *                                  No login required - Sofia verifies in chat.
 *   - Claimed by the current
 *     (logged-in) user           -> "Manage this <thing>" button ->
 *                                  /member/business/?id=<post_id>.
 *   - Claimed by someone else    -> nothing (button hidden).
 * Admins/editors already manage via the Update Info button, so the claim
 * button is suppressed for them on unclaimed listings too.
 *
 * $type is 'business' or 'web'; it drives the copy and the WhatsApp keyword.
 * The manage URL is /member/business/ for both (mirrors the old claim
 * shortcodes, which used that path for websites too).
 */
function mfa_claim_or_manage_cta( $type, $post_id ) {
	if ( 'business' !== $type && 'web' !== $type ) {
		return '';
	}

	$post_id   = (int) $post_id;
	$noun      = ( 'web' === $type ) ? 'website' : 'business';
	$btn_class = ( 'web' === $type ) ? 'mfa-web-action-btn' : 'mfa-biz-action-btn';

	global $wpdb;
	$owner_table = $wpdb->prefix . 'jet_cct_listing_owner';
	$claim_uid   = (int) $wpdb->get_var( $wpdb->prepare(
		"SELECT user_id FROM {$owner_table} WHERE post_id = %d LIMIT 1",
		$post_id
	) );

	// Already claimed.
	if ( $claim_uid > 0 ) {
		$uid = get_current_user_id();
		if ( $uid && $claim_uid === $uid ) {
			$manage_icon = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M3 3h7v7H3z"></path><path d="M14 3h7v7h-7z"></path><path d="M14 14h7v7h-7z"></path><path d="M3 14h7v7H3z"></path></svg>';
			return '<a class="' . esc_attr( $btn_class ) . '" href="' . esc_url( '/member/business/?id=' . $post_id ) . '">' . $manage_icon . ' Manage this ' . esc_html( $noun ) . '</a>';
		}
		// Claimed by another user - hide.
		return '';
	}

	// Unclaimed: admins/editors manage via Update Info, so don't offer claim.
	if ( current_user_can( 'editor' ) || current_user_can( 'administrator' ) ) {
		return '';
	}

	// Unclaimed, ordinary visitor (logged in or out) -> Claim button + Sofia popup.
	$modal_id = 'mfa-claim-' . $type . '-' . $post_id;
	$wa_link  = 'https://wa.me/60189897579?text=' . rawurlencode( 'claim ' . $noun );
	$emoji    = ( 'web' === $type ) ? '🌐' : '🏪';
	$claim_icon = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"></path><path d="M9 12l2 2 4-4"></path></svg>';
	$wa_icon    = '<svg viewBox="0 0 24 24" width="18" height="18" fill="currentColor" aria-hidden="true"><path d="M12.04 2C6.58 2 2.13 6.45 2.13 11.91c0 1.75.46 3.45 1.32 4.95L2 22l5.25-1.38a9.9 9.9 0 0 0 4.79 1.22h.01c5.46 0 9.91-4.45 9.91-9.91 0-2.65-1.03-5.14-2.9-7.01A9.82 9.82 0 0 0 12.04 2zm0 18.15h-.01a8.2 8.2 0 0 1-4.19-1.15l-.3-.18-3.11.82.83-3.03-.2-.31a8.2 8.2 0 0 1-1.26-4.38c0-4.54 3.7-8.24 8.25-8.24 2.2 0 4.27.86 5.83 2.42a8.2 8.2 0 0 1 2.41 5.83c0 4.54-3.7 8.24-8.25 8.24zm4.52-6.16c-.25-.12-1.47-.72-1.69-.81-.23-.08-.39-.12-.56.13-.16.24-.64.8-.79.97-.14.16-.29.18-.54.06-.25-.12-1.05-.39-1.99-1.23-.74-.66-1.23-1.47-1.38-1.72-.14-.25-.02-.38.11-.5.11-.11.25-.29.37-.43.12-.15.16-.25.25-.42.08-.16.04-.31-.02-.43-.06-.12-.56-1.34-.76-1.84-.2-.48-.4-.42-.56-.42h-.48c-.16 0-.43.06-.66.31-.23.24-.86.84-.86 2.06 0 1.22.89 2.4 1.01 2.56.12.16 1.75 2.67 4.23 3.74.59.26 1.05.41 1.41.52.59.19 1.13.16 1.56.1.48-.07 1.47-.6 1.68-1.18.21-.58.21-1.07.14-1.18-.06-.11-.22-.17-.47-.29z"/></svg>';

	ob_start();
	?>
	<button type="button" class="<?php echo esc_attr( $btn_class ); ?> mfa-assist-open" data-target="<?php echo esc_attr( $modal_id ); ?>" aria-haspopup="dialog" aria-controls="<?php echo esc_attr( $modal_id ); ?>"><?php echo $claim_icon; // phpcs:ignore WordPress.Security.EscapeOutput ?> Claim this <?php echo esc_html( $noun ); ?></button>

	<div class="mfa-assist-overlay" id="<?php echo esc_attr( $modal_id ); ?>" role="dialog" aria-modal="true" aria-hidden="true" aria-label="<?php echo esc_attr( 'Claim this ' . $noun ); ?>">
		<div class="mfa-assist-modal">
			<button type="button" class="mfa-assist-close" aria-label="Close">
				<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"></line><line x1="6" y1="6" x2="18" y2="18"></line></svg>
			</button>
			<div class="mfa-assist-emoji"><?php echo $emoji; // phpcs:ignore WordPress.Security.EscapeOutput ?></div>
			<h3 class="mfa-assist-title">Claim this <?php echo esc_html( $noun ); ?></h3>
			<p class="mfa-assist-text">Are you the owner? Our AI assistant <strong>Sofia</strong> will verify you and hand over management of this <?php echo esc_html( $noun ); ?> on WhatsApp &mdash; it&rsquo;s quick and free, no password needed.</p>
			<a class="mfa-assist-cta" href="<?php echo esc_url( $wa_link ); ?>" target="_blank" rel="noopener noreferrer"><?php echo $wa_icon; // phpcs:ignore WordPress.Security.EscapeOutput ?> Claim on WhatsApp</a>
		</div>
	</div>
	<?php
	return ob_get_clean();
}

add_shortcode( 'mfa_directory_single', 'mfa_directory_single_shortcode' );
function mfa_directory_single_shortcode() {
	$post_id = get_the_ID();
	$type    = get_post_type( $post_id );
	$config  = mfa_directory_single_config();

	if ( ! isset( $config[ $type ] ) ) {
		return '';
	}
	$c = $config[ $type ];

	$rec = mfa_directory_single_record( $post_id, $type, $c['owner_col'] );

	// Only masjid/business/web (city has 'live_statuses' => null) go through
	// a claimable approval workflow - each type's own "live" statuses match
	// what its info-display function already treats as "actual content"
	// (mosque: Approved/Active; business/web: Approved/Verified/Premium).
	// Below that, hide the featured image and every tab except Home (which
	// already shows its own status-appropriate content - a "Click to
	// Update" prompt for New/Pending, a "we're verifying" card for anything
	// else) - a Rejected or not-yet-reviewed listing shouldn't let visitors
	// into its Community/Nearby Mosques/Review/Claim tabs (2026-08-13,
	// explicit user direction: don't let people "join community" etc. on
	// unverified listings).
	$is_live = empty( $c['live_statuses'] ) || in_array( $rec['status'], $c['live_statuses'], true );

	ob_start();
	?>
	<div class="mfa-dir-single mfa-dir-<?php echo esc_attr( $type ); ?>">
		<div class="mfa-dir-body">
			<div class="mfa-dir-main">
				<?php if ( $is_live && ! empty( $c['header'] ) ) { echo do_shortcode( '[' . $c['header'] . ']' ); } ?>
				<?php echo mfa_directory_single_action( $rec, $c ); ?>
				<?php if ( $is_live ) : ?>
					<div class="mfa-dir-tabs" role="tablist">
						<?php foreach ( $c['tabs'] as $i => $t ) : ?>
							<button type="button" class="mfa-dir-tab<?php echo 0 === $i ? ' is-active' : ''; ?>" data-mfa-tab="<?php echo (int) $i; ?>" role="tab"><?php echo esc_html( $t[0] ); ?></button>
						<?php endforeach; ?>
					</div>
					<?php foreach ( $c['tabs'] as $i => $t ) : ?>
						<div class="mfa-dir-pane<?php echo 0 === $i ? ' is-active' : ''; ?>" data-mfa-pane="<?php echo (int) $i; ?>">
							<?php echo do_shortcode( '[' . $t[1] . ']' ); ?>
						</div>
					<?php endforeach; ?>
				<?php elseif ( ! empty( $c['tabs'] ) ) : ?>
					<div class="mfa-dir-pane is-active">
						<?php echo do_shortcode( '[' . $c['tabs'][0][1] . ']' ); ?>
					</div>
				<?php endif; ?>
			</div>

			<?php if ( ! empty( $c['sidebar'] ) ) : ?>
				<aside class="mfa-dir-sidebar"><?php echo do_shortcode( $c['sidebar'] ); ?></aside>
			<?php endif; ?>
		</div>

		<?php
		// Full width, outside the main/sidebar row. empty() rather than isset()
		// so a type config without a 'below' key is simply skipped.
		if ( ! empty( $c['below'] ) ) {
			$below_html = do_shortcode( $c['below'] );
			// Only wrap when there is something to wrap - the block renders ''
			// for a country with no hub, and an empty div still carries margin.
			if ( '' !== trim( $below_html ) ) {
				echo '<div class="mfa-dir-below">' . $below_html . '</div>';
			}
		}
		?>
	</div>
	<?php
	return ob_get_clean();
}
