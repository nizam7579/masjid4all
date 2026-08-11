<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * [mfa_directory_single] - the shared single-listing component for the
 * directory CPTs (masjid / business / web), replacing the Kadence Theme
 * Builder "Mosque" (875) / "Business" (9151) / "Web" (220902) templates.
 *
 * One component, configured per post type: a header shortcode, a tabbed body
 * (each pane = one existing tab shortcode), an optional sidebar, and an
 * optional gated "generate AI content" action. Auto-detects the current post
 * type, so all three single-{type}.php theme templates just call this.
 *
 * The AI-generate action reuses the existing per-type updater shortcodes
 * (niz_business_ai_updater / niz_web_ai_updater) but only renders them when
 * the listing's status is New/Pending AND the viewer owns the listing or is
 * an admin - replacing the old always-visible, nopriv buttons.
 */

function mfa_directory_single_config() {
	return array(
		'masjid'   => array(
			'header'    => 'niz_mfa_masjid_header',
			'tabs'      => array(
				array( 'Home', 'mfa_mosque_home_tab' ),
				array( 'Community', 'mosque_community' ),
				array( 'Local Business', 'mfa_mosque_local_business_tab' ),
				array( 'Review', 'niz_review' ),
			),
			'sidebar'   => '',
			'owner_col' => 'cct_author_id',
			'action'    => null,
		),
		'business' => array(
			'header'    => 'niz_business_header',
			'tabs'      => array(
				array( 'Home', 'mfa_business_home_tab' ),
				array( 'Nearby Mosques', 'mfa_business_nearby_mosques_tab' ),
				array( 'Review', 'niz_review' ),
				array( 'Claim', 'mfa_claim_business_listing' ),
			),
			'sidebar'   => 'mfa_business_sidebar_directory',
			'owner_col' => 'owner_id',
			'action'    => array( 'sc' => 'niz_business_ai_updater', 'when_status' => array( 'New', 'Pending' ) ),
		),
		'web'      => array(
			'header'    => 'mfa_website_header',
			'tabs'      => array(
				array( 'Home', 'mfa_website_home_tab' ),
				array( 'Review', 'niz_review' ),
				array( 'Claim Website', 'mfa_claim_web_listing' ),
			),
			'sidebar'   => 'niz_mfa_web_directory',
			'owner_col' => 'cct_author_id',
			'action'    => array( 'sc' => 'niz_web_ai_updater', 'when_status' => array( 'New', 'Pending' ) ),
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
 */
function mfa_directory_single_action( $post_id, $type, $cfg ) {
	if ( empty( $cfg['action'] ) ) {
		return '';
	}
	$rec = mfa_directory_single_record( $post_id, $type, $cfg['owner_col'] );

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

	ob_start();
	?>
	<div class="mfa-dir-single mfa-dir-<?php echo esc_attr( $type ); ?>">
		<?php echo do_shortcode( '[' . $c['header'] . ']' ); ?>
		<?php echo mfa_directory_single_action( $post_id, $type, $c ); ?>

		<div class="mfa-dir-body">
			<div class="mfa-dir-main">
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
			</div>

			<?php if ( ! empty( $c['sidebar'] ) ) : ?>
				<aside class="mfa-dir-sidebar"><?php echo do_shortcode( '[' . $c['sidebar'] . ']' ); ?></aside>
			<?php endif; ?>
		</div>
	</div>
	<?php
	return ob_get_clean();
}
