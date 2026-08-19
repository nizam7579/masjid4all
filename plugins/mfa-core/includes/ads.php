<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Ported from the standalone Enaizi ADS plugin (2026-08-13) as part of the
 * enaizi-* consolidation into mfa-core. Reuses the plugin's original
 * wp_enaizi_ads / wp_enaizi_wallet tables and the [enaizi_ads] shortcode
 * name unchanged, so directory-single.php's sidebar config
 * ('[enaizi_ads count="4" layout="vertical"]') needed zero changes.
 *
 * Two security issues present in the original plugin were fixed during the
 * port, not preserved: the admin "Create Ad" form inserted raw $_POST with
 * zero sanitization and no nonce, and the Approvals page's approve link was
 * a bare, unauthenticated GET with no nonce (CSRF). Both now go through
 * sanitize_text_field()/esc_url_raw() and a nonce check.
 */

function mfa_ads_table() {
	global $wpdb;
	return $wpdb->prefix . 'enaizi_ads';
}

function mfa_wallet_table() {
	global $wpdb;
	return $wpdb->prefix . 'enaizi_wallet';
}

// ---------------------------------------------------------------------
// Table creation - reuses the exact schema/table names the original
// enaizi-ads plugin created, so existing rows/wallets carry over as-is.
// dbDelta() is idempotent, so this is a safe no-op once the tables match.
// ---------------------------------------------------------------------
define( 'MFA_ADS_TABLE_VERSION', '1.1' );

add_action( 'plugins_loaded', 'mfa_ads_maybe_create_tables' );
function mfa_ads_maybe_create_tables() {
	if ( get_option( 'mfa_ads_table_version' ) === MFA_ADS_TABLE_VERSION ) {
		return;
	}

	global $wpdb;
	require_once ABSPATH . 'wp-admin/includes/upgrade.php';

	$cc = $wpdb->get_charset_collate();

	dbDelta( 'CREATE TABLE ' . mfa_ads_table() . " (
		id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
		user_id BIGINT(20),
		image_url TEXT,
		target_url TEXT,
		country VARCHAR(10),
		category VARCHAR(50),
		lat DECIMAL(10,6),
		lng DECIMAL(10,6),
		radius INT DEFAULT 10,
		impressions BIGINT DEFAULT 0,
		viewable BIGINT DEFAULT 0,
		clicks BIGINT DEFAULT 0,
		base_price DECIMAL(10,2) DEFAULT 0.20,
		dynamic_price DECIMAL(10,2) DEFAULT 0.20,
		status TINYINT DEFAULT 2,
		created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
		PRIMARY KEY (id)
	) {$cc};" );

	dbDelta( 'CREATE TABLE ' . mfa_wallet_table() . " (
		id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
		user_id BIGINT(20),
		balance DECIMAL(10,2) DEFAULT 0,
		PRIMARY KEY (id),
		UNIQUE KEY user_id (user_id)
	) {$cc};" );

	update_option( 'mfa_ads_table_version', MFA_ADS_TABLE_VERSION );
}

// ---------------------------------------------------------------------
// Scoring / pricing / selection - unchanged logic from the original.
// ---------------------------------------------------------------------
function mfa_ads_score( $ad ) {
	$ctr = $ad['impressions'] ? $ad['clicks'] / $ad['impressions'] : 0;

	$score = 1 + ( $ctr * 100 );

	if ( $ctr > 0.05 ) {
		$score += 5;
	}
	if ( $ctr < 0.01 ) {
		$score -= 2;
	}

	return max( 1, $score );
}

function mfa_ads_price( $ad ) {
	$price = $ad['base_price'];
	$ctr   = $ad['impressions'] ? $ad['clicks'] / $ad['impressions'] : 0;

	if ( $ctr > 0.05 ) {
		$price *= 1.3;
	}
	if ( $ctr < 0.01 ) {
		$price *= 0.8;
	}

	return round( $price, 2 );
}

function mfa_ads_get_ads( $count = 2 ) {
	global $wpdb;

	$rows = $wpdb->get_results( 'SELECT * FROM ' . mfa_ads_table() . ' WHERE status = 1', ARRAY_A );
	if ( empty( $rows ) ) {
		return array();
	}

	// Weighted selection WITHOUT replacement.
	//
	// The previous version pushed `score` copies of every ad into one pool,
	// shuffled it and sliced $count off the front, so the same ad could fill
	// two or three of the four slots in a single column - visible to any
	// advertiser looking at their own placement. Each pass here picks one ad
	// and removes it from the pool, so a column never repeats an advertiser.
	$count  = min( (int) $count, count( $rows ) );
	$picked = array();

	while ( count( $picked ) < $count && ! empty( $rows ) ) {
		$weights = array();
		$total   = 0.0;
		foreach ( $rows as $k => $r ) {
			$w             = max( 0.0001, (float) mfa_ads_score( $r ) );
			$weights[ $k ] = $w;
			$total        += $w;
		}

		// Scaled to integers so mt_rand() can do the pick without float edge
		// cases at the boundaries.
		$target = mt_rand( 1, max( 1, (int) round( $total * 1000 ) ) );
		$acc    = 0;
		$chosen = array_key_first( $rows );
		foreach ( $weights as $k => $w ) {
			$acc += (int) round( $w * 1000 );
			if ( $acc >= $target ) {
				$chosen = $k;
				break;
			}
		}

		$picked[] = $rows[ $chosen ];
		unset( $rows[ $chosen ] );
	}

	return $picked;
}

function mfa_ads_update_balance( $user, $amount ) {
	global $wpdb;

	$wpdb->query( $wpdb->prepare(
		'INSERT INTO ' . mfa_wallet_table() . ' (user_id, balance)
		 VALUES (%d, %f)
		 ON DUPLICATE KEY UPDATE balance = balance + %f',
		$user, $amount, $amount
	) );
}

// ---------------------------------------------------------------------
// [enaizi_ads count="N" layout="horizontal|vertical"] - shortcode name,
// attributes, and rendered markup unchanged from the original so
// directory-single.php's sidebar config needs no changes.
// ---------------------------------------------------------------------
add_shortcode( 'enaizi_ads', 'mfa_ads_shortcode' );
function mfa_ads_shortcode( $atts ) {
	global $wpdb;

	$atts = shortcode_atts( array(
		'count'  => 2,
		'layout' => 'horizontal',
		'promo'  => 1,
	), $atts );

	$count = intval( $atts['count'] );
	if ( $count < 1 ) {
		$count = 1;
	}
	if ( $count > 6 ) {
		$count = 6;
	}

	$layout = sanitize_key( $atts['layout'] );
	if ( ! in_array( $layout, array( 'horizontal', 'vertical' ), true ) ) {
		$layout = 'horizontal';
	}

	$ads = mfa_ads_get_ads( $count );

	// The promo card is part of the ads block, not a separate section under
	// it: "you could be here" only makes sense sitting among the ads it is
	// talking about. It renders FIRST, and it renders even when there are no
	// ads to show - an empty slot is the best possible moment to offer it,
	// and returning '' there (as this shortcode used to) would hide it
	// exactly where there is most room.
	$promo = ( ! empty( $atts['promo'] ) && shortcode_exists( 'mfa_lead_cta' ) )
		? do_shortcode( '[mfa_lead_cta type="advertise" style="ad"]' )
		: '';

	if ( empty( $ads ) && '' === $promo ) {
		return '';
	}

	// Registered in mfa_ads_register_assets(); enqueued here so the tracker
	// ships exactly where an ad renders.
	wp_enqueue_script( 'mfa-core-ads' );

	ob_start();
	?>
	<div class="enaizi-ads-container enaizi-layout-<?php echo esc_attr( $layout ); ?> enaizi-cols-<?php echo esc_attr( $count ); ?>">
		<?php echo $promo; // phpcs:ignore WordPress.Security.EscapeOutput ?>
		<?php foreach ( $ads as $a ) : ?>
			<?php
			$wpdb->query( $wpdb->prepare(
				'UPDATE ' . mfa_ads_table() . ' SET impressions = impressions + 1 WHERE id = %d',
				$a['id']
			) );
			?>
			<a href="<?php echo esc_url( $a['target_url'] ); ?>"
			   target="_blank"
			   rel="noopener noreferrer"
			   class="enaizi-click"
			   data-id="<?php echo esc_attr( $a['id'] ); ?>">
				<img src="<?php echo esc_url( $a['image_url'] ); ?>" loading="lazy" alt="Advertisement">
			</a>
		<?php endforeach; ?>
	</div>

	<?php
	// Dialog goes outside the container on purpose - see mfa_lead_modal_html().
	if ( '' !== $promo && function_exists( 'mfa_lead_modal_html' ) ) {
		echo mfa_lead_modal_html( 'advertise' ); // phpcs:ignore WordPress.Security.EscapeOutput
	}
	?>
	<style>
	.enaizi-ads-container {
		display: flex;
		flex-direction: column;
		gap: 16px;
		width: 100%;
		margin: 15px auto;
	}
	.enaizi-ads-container .enaizi-click {
		display: block;
		width: 100%;
	}
	.enaizi-ads-container .enaizi-click img {
		width: 100%;
		height: auto;
		object-fit: cover;
		display: block;
		border-radius: 8px;
	}
	@media (min-width: 768px) {
		.enaizi-ads-container.enaizi-layout-horizontal {
			display: grid;
			gap: 20px;
		}
		.enaizi-ads-container.enaizi-layout-horizontal.enaizi-cols-1 { grid-template-columns: repeat(1, 1fr); }
		.enaizi-ads-container.enaizi-layout-horizontal.enaizi-cols-2 { grid-template-columns: repeat(2, 1fr); }
		.enaizi-ads-container.enaizi-layout-horizontal.enaizi-cols-3 { grid-template-columns: repeat(3, 1fr); }
		.enaizi-ads-container.enaizi-layout-horizontal.enaizi-cols-4 { grid-template-columns: repeat(4, 1fr); }
		.enaizi-ads-container.enaizi-layout-horizontal.enaizi-cols-5 { grid-template-columns: repeat(5, 1fr); }
		.enaizi-ads-container.enaizi-layout-horizontal.enaizi-cols-6 { grid-template-columns: repeat(6, 1fr); }
		.enaizi-ads-container.enaizi-layout-vertical {
			flex-direction: column;
			max-width: 350px;
		}
	}
	</style>
	<?php
	return ob_get_clean();
}

// ---------------------------------------------------------------------
// Click-tracking AJAX. Fixed vs the original: both queries now go through
// $wpdb->prepare() instead of raw interpolation of $_POST['id'].
// ---------------------------------------------------------------------
add_action( 'wp_ajax_enaizi_click', 'mfa_ads_click' );
add_action( 'wp_ajax_nopriv_enaizi_click', 'mfa_ads_click' );
function mfa_ads_click() {
	global $wpdb;

	$id = isset( $_POST['id'] ) ? intval( $_POST['id'] ) : 0;
	if ( ! $id ) {
		wp_send_json_error();
	}

	$ad = $wpdb->get_row( $wpdb->prepare(
		'SELECT * FROM ' . mfa_ads_table() . ' WHERE id = %d', $id
	), ARRAY_A );

	if ( ! $ad ) {
		wp_send_json_error();
	}

	$wpdb->query( $wpdb->prepare(
		'UPDATE ' . mfa_ads_table() . ' SET clicks = clicks + 1 WHERE id = %d', $id
	) );

	if ( $ad['user_id'] ) {
		$price = mfa_ads_price( $ad );
		mfa_ads_update_balance( $ad['user_id'], -$price );
	}

	wp_send_json_success();
}


// ---------------------------------------------------------------------
// Viewable-impression AJAX.
//
// The `impressions` column counts server-side RENDERS - every time the
// shortcode outputs an ad, including requests from bots and ads that are
// never scrolled into view. That is a "served" count, not a seen one, and
// measuring click-through against it understates real performance by a
// wide margin.
//
// `viewable` is the honest denominator: the browser reports an ad only
// once it has been at least 50% visible for a continuous second (the
// usual display-advertising definition). Because it needs a real browser
// running IntersectionObserver, it also excludes most crawler traffic for
// free. Both columns are kept so the served-vs-viewable ratio stays
// visible - that ratio is itself the useful signal.
//
// No nonce, deliberately: these pages are served from LiteSpeed's page
// cache, so an embedded nonce would be stale for most visitors. That
// matches the existing click endpoint. Both counters are therefore
// inflatable by anyone who can POST - worth solving before ad spend is
// ever tied to them, but out of scope here and no ad is billed today.
// The batch is capped so one request cannot amplify far.
// ---------------------------------------------------------------------
add_action( 'wp_ajax_enaizi_impression', 'mfa_ads_impression' );
add_action( 'wp_ajax_nopriv_enaizi_impression', 'mfa_ads_impression' );
function mfa_ads_impression() {
	global $wpdb;

	$raw = isset( $_POST['ids'] ) ? sanitize_text_field( wp_unslash( $_POST['ids'] ) ) : '';
	if ( '' === $raw ) {
		wp_send_json_error();
	}

	$ids = array_map( 'intval', explode( ',', $raw ) );
	$ids = array_values( array_unique( array_filter( $ids ) ) );
	$ids = array_slice( $ids, 0, 12 );

	if ( empty( $ids ) ) {
		wp_send_json_error();
	}

	// Every value is an int by construction, so this interpolation is safe;
	// $wpdb->prepare() has no placeholder for a variable-length IN list.
	$in = implode( ',', $ids );

	$wpdb->query(
		'UPDATE ' . mfa_ads_table() . " SET viewable = viewable + 1 WHERE id IN ({$in}) AND status = 1"
	);

	wp_send_json_success( count( $ids ) );
}
// ---------------------------------------------------------------------
// wp-admin: "Ads" top-level menu -> Create Ad + Approvals. Same layout as
// the original enaizi-ads plugin, with sanitization + nonces added (the
// original had neither on the create form or the approve link).
// ---------------------------------------------------------------------
add_action( 'admin_menu', 'mfa_ads_admin_menu' );
function mfa_ads_admin_menu() {
	add_menu_page( 'Ads', 'Ads', 'manage_options', 'mfa-ads', 'mfa_ads_admin_page', 'dashicons-megaphone' );
	add_submenu_page( 'mfa-ads', 'Ads Approvals', 'Approvals', 'manage_options', 'mfa-ads-approvals', 'mfa_ads_approvals_page' );
}

function mfa_ads_admin_page() {
	global $wpdb;

	if ( isset( $_POST['save'] ) && check_admin_referer( 'mfa_ads_create' ) ) {
		$wpdb->insert( mfa_ads_table(), array(
			'user_id'    => get_current_user_id(),
			'image_url'  => esc_url_raw( wp_unslash( $_POST['image'] ?? '' ) ),
			'target_url' => esc_url_raw( wp_unslash( $_POST['url'] ?? '' ) ),
			'country'    => sanitize_text_field( wp_unslash( $_POST['country'] ?? '' ) ),
			'category'   => sanitize_text_field( wp_unslash( $_POST['category'] ?? '' ) ),
			'status'     => 2,
		) );
		echo '<div class="notice notice-success"><p>Ad submitted for approval.</p></div>';
	}
	?>
	<div class="wrap">
		<h1>Create Ad</h1>
		<form method="post">
			<?php wp_nonce_field( 'mfa_ads_create' ); ?>
			<table class="form-table">
				<tr>
					<th><label for="mfa-ads-image">Image URL</label></th>
					<td><input type="url" id="mfa-ads-image" name="image" class="regular-text" required></td>
				</tr>
				<tr>
					<th><label for="mfa-ads-url">Target URL</label></th>
					<td><input type="url" id="mfa-ads-url" name="url" class="regular-text" required></td>
				</tr>
				<tr>
					<th><label for="mfa-ads-country">Country</label></th>
					<td>
						<select id="mfa-ads-country" name="country">
							<option value="ALL">All</option>
							<option value="MY">MY</option>
						</select>
					</td>
				</tr>
				<tr>
					<th><label for="mfa-ads-category">Category</label></th>
					<td>
						<select id="mfa-ads-category" name="category">
							<option value="halal_food">Halal Food</option>
							<option value="services">Services</option>
						</select>
					</td>
				</tr>
			</table>
			<?php submit_button( 'Submit', 'primary', 'save' ); ?>
		</form>
	</div>
	<?php
}

function mfa_ads_approvals_page() {
	global $wpdb;

	if ( isset( $_GET['approve'] ) ) {
		check_admin_referer( 'mfa_ads_approve_' . intval( $_GET['approve'] ) );
		$wpdb->update( mfa_ads_table(), array( 'status' => 1 ), array( 'id' => intval( $_GET['approve'] ) ) );
		echo '<div class="notice notice-success"><p>Ad approved.</p></div>';
	}

	$ads = $wpdb->get_results( 'SELECT * FROM ' . mfa_ads_table() . ' WHERE status = 2' );
	?>
	<div class="wrap">
		<h1>Pending Ads</h1>
		<?php if ( empty( $ads ) ) : ?>
			<p>No ads awaiting approval.</p>
		<?php else : ?>
			<?php foreach ( $ads as $a ) : ?>
				<div style="margin-bottom:16px;">
					<img src="<?php echo esc_url( $a->image_url ); ?>" width="150" alt="">
					<p><a href="<?php echo esc_url( $a->target_url ); ?>" target="_blank" rel="noopener noreferrer"><?php echo esc_html( $a->target_url ); ?></a></p>
					<p><a class="button button-primary" href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin.php?page=mfa-ads-approvals&approve=' . $a->id ), 'mfa_ads_approve_' . $a->id ) ); ?>">Approve</a></p>
				</div>
			<?php endforeach; ?>
		<?php endif; ?>
	</div>
	<?php
}
// ---------------------------------------------------------------------
// Script registration.
//
// Registered globally, enqueued from mfa_ads_shortcode() itself, so the
// tracker loads exactly where an ad actually renders and nowhere else.
//
// This replaces a post-type check ( masjid / business / web ) which meant
// the click tracker loaded on the three single-listing templates ONLY.
// Ads render in eleven places - the four directory pages, single mosque/
// business/web, /quran/ and single surah, /prayer-times/ and
// /qibla-finder/ - so impressions were being counted everywhere while
// clicks were counted in three of them. Any click-through figure taken
// before 2026-08-19 is therefore an undercount, not a real CTR, and the
// two are not comparable across that date.
// ---------------------------------------------------------------------
add_action( 'wp_enqueue_scripts', 'mfa_ads_register_assets' );
function mfa_ads_register_assets() {
	$js = MFA_CORE_PATH . 'assets/js/ads-v2.js';
	wp_register_script(
		'mfa-core-ads',
		MFA_CORE_URL . 'assets/js/ads-v2.js',
		array(),
		file_exists( $js ) ? filemtime( $js ) : MFA_CORE_VERSION,
		true
	);
	wp_localize_script( 'mfa-core-ads', 'mfaAdsAjax', array(
		'url' => admin_url( 'admin-ajax.php' ),
	) );
}
