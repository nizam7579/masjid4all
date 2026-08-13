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
define( 'MFA_ADS_TABLE_VERSION', '1.0' );

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

	$pool = array();
	foreach ( $rows as $r ) {
		$score = mfa_ads_score( $r );
		for ( $i = 0; $i < $score; $i++ ) {
			$pool[] = $r;
		}
	}

	shuffle( $pool );
	return array_slice( $pool, 0, $count );
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
	if ( empty( $ads ) ) {
		return '';
	}

	ob_start();
	?>
	<div class="enaizi-ads-container enaizi-layout-<?php echo esc_attr( $layout ); ?> enaizi-cols-<?php echo esc_attr( $count ); ?>">
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
// Click-tracking JS - only enqueued on the post types [enaizi_ads]
// actually renders on (mosque/business/web singles, via
// directory-single.php's sidebar config). has_shortcode() can't see it
// there since it's injected from PHP config, not literal post_content -
// same reasoning as the directory-single/modal block in
// widgets-enqueue.php.
// ---------------------------------------------------------------------
add_action( 'wp_enqueue_scripts', 'mfa_ads_enqueue_assets' );
function mfa_ads_enqueue_assets() {
	$post = get_post();
	if ( ! $post || ! in_array( $post->post_type, array( 'masjid', 'business', 'web' ), true ) ) {
		return;
	}

	$js = MFA_CORE_PATH . 'assets/js/ads-v1.js';
	wp_enqueue_script( 'mfa-core-ads', MFA_CORE_URL . 'assets/js/ads-v1.js', array(), file_exists( $js ) ? filemtime( $js ) : MFA_CORE_VERSION, true );
	wp_localize_script( 'mfa-core-ads', 'mfaAdsAjax', array(
		'url' => admin_url( 'admin-ajax.php' ),
	) );
}
