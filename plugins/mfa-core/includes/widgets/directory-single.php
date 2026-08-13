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
				array( 'Claim', 'mfa_claim_business_listing' ),
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
				array( 'Claim Website', 'mfa_claim_web_listing' ),
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
	</div>
	<?php
	return ob_get_clean();
}
