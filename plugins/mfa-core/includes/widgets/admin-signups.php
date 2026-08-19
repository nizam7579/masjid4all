<?php
/**
 * [mfa_admin_signups] - "real signups since launch" panel on /admin/.
 *
 * Why this exists, and why it counts by user ID rather than by date:
 *
 * `user_registered` cannot be trusted for the bulk of wp_users. The
 * imported cohorts were written with synthetic dates - every month from
 * May to October 2026 spans the same full ID range (14,281 -> 89,3xx),
 * user ID 2 (the admin) carries an October date, and two of those months
 * are in the future. So any "growth" measured from that column is noise.
 *
 * User IDs, by contrast, are monotonic and cannot be backdated. The last
 * imported account is 123910; 123911 (a real Google signup) is the first
 * genuine one. Everything above the watermark is therefore real, and this
 * panel is a clean forward-looking baseline: month one is month one.
 *
 * The watermark lives in an option so it can be re-baselined without a
 * deploy, and is filterable for tests.
 *
 * @package mfa-core
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Last imported user ID. Anything above it is a genuine signup. */
function mfa_signup_watermark() {
	$id = (int) get_option( 'mfa_signup_watermark_id', 0 );

	if ( $id <= 0 ) {
		// 123910 is the highest id in the 2026-12-01 import cohort on
		// production. Stored on first read so the baseline is explicit
		// rather than a constant buried in code.
		$id = 123910;
		update_option( 'mfa_signup_watermark_id', $id, false );
	}

	return (int) apply_filters( 'mfa_signup_watermark', $id );
}

/**
 * Counts for everything above the watermark.
 *
 * A "self signup" is anyone whose email is not the <phone>@mfa.com
 * placeholder WhatsApp-created accounts get - that placeholder is the
 * only reliable marker separating someone who chose to register from an
 * account created for them by a WhatsApp or directory interaction.
 */
function mfa_signup_stats() {
	global $wpdb;

	$mark = mfa_signup_watermark();

	$stats = array( 'watermark' => $mark );

	$stats['total'] = (int) $wpdb->get_var( $wpdb->prepare(
		"SELECT COUNT(*) FROM {$wpdb->users} WHERE ID > %d", $mark
	) );

	$stats['self'] = (int) $wpdb->get_var( $wpdb->prepare(
		"SELECT COUNT(*) FROM {$wpdb->users} WHERE ID > %d AND user_email NOT LIKE %s",
		$mark, '%@mfa.com'
	) );

	$stats['whatsapp'] = $stats['total'] - $stats['self'];

	$stats['members'] = (int) $wpdb->get_var( $wpdb->prepare(
		"SELECT COUNT(*) FROM {$wpdb->users} u
		 INNER JOIN {$wpdb->usermeta} m ON m.user_id = u.ID AND m.meta_key = 'user_status'
		 WHERE u.ID > %d AND m.meta_value IN ('member','premium')",
		$mark
	) );

	$cct = $wpdb->prefix . 'jet_cct_member';
	$stats['referred'] = (int) $wpdb->get_var( $wpdb->prepare(
		"SELECT COUNT(*) FROM {$cct} WHERE user_id > %d AND referrer_id > 0", $mark
	) );

	$stats['today'] = (int) $wpdb->get_var( $wpdb->prepare(
		"SELECT COUNT(*) FROM {$wpdb->users} WHERE ID > %d AND DATE(user_registered) = %s",
		$mark, current_time( 'Y-m-d' )
	) );

	return $stats;
}

/**
 * Sofia lead counts per type - the "gauge interest" numbers.
 *
 * Read from the mfa_sofia_leads user meta rather than FluentCRM, so the
 * panel keeps working if the CRM is ever deactivated, and so it counts
 * leads from accounts of any age (someone who joined the waitlist may
 * have existed long before the watermark).
 */
function mfa_signup_lead_counts() {
	global $wpdb;

	$counts = array();
	$types  = function_exists( 'mfa_lead_types' ) ? mfa_lead_types() : array();

	if ( empty( $types ) ) {
		return $counts;
	}

	$rows = $wpdb->get_col(
		"SELECT meta_value FROM {$wpdb->usermeta} WHERE meta_key = 'mfa_sofia_leads'"
	);

	foreach ( $types as $key => $cfg ) {
		$counts[ $key ] = array( 'label' => $cfg['label'], 'emoji' => $cfg['emoji'], 'count' => 0 );
	}

	foreach ( $rows as $raw ) {
		$leads = maybe_unserialize( $raw );
		if ( ! is_array( $leads ) ) {
			continue;
		}
		foreach ( $leads as $type => $lead ) {
			if ( isset( $counts[ $type ] ) ) {
				$counts[ $type ]['count']++;
			}
		}
	}

	return $counts;
}

/** The most recent real signups, newest first. */
function mfa_signup_recent( $limit = 20 ) {
	global $wpdb;

	$mark = mfa_signup_watermark();
	$cct  = $wpdb->prefix . 'jet_cct_member';

	return $wpdb->get_results( $wpdb->prepare(
		"SELECT u.ID, u.user_email, u.display_name, u.user_registered,
		        m.meta_value AS status, c.referrer_id
		 FROM {$wpdb->users} u
		 LEFT JOIN {$wpdb->usermeta} m ON m.user_id = u.ID AND m.meta_key = 'user_status'
		 LEFT JOIN {$cct} c ON c.user_id = u.ID
		 WHERE u.ID > %d
		 ORDER BY u.ID DESC
		 LIMIT %d",
		$mark, (int) $limit
	), ARRAY_A );
}

/* ---------------- Render ---------------- */

add_shortcode( 'mfa_admin_signups', 'mfa_admin_signups_shortcode' );
function mfa_admin_signups_shortcode() {
	if ( ! is_user_logged_in() ) {
		return '';
	}
	// Same gate as the rest of /admin/ - the dashboard section.
	if ( function_exists( 'mfa_user_can_access_admin_section' ) && ! mfa_user_can_access_admin_section( 'dashboard' ) ) {
		return '';
	}

	$stats = mfa_signup_stats();
	$leads = mfa_signup_lead_counts();
	$rows  = mfa_signup_recent( 15 );

	ob_start();
	?>
	<section class="mfa-signups">
		<div class="mfa-signups-head">
			<h2 class="mfa-signups-title">Real signups</h2>
			<p class="mfa-signups-note">
				Counted from user ID <strong>#<?php echo esc_html( number_format_i18n( $stats['watermark'] ) ); ?></strong> onward &mdash;
				everything below that is imported, with registration dates that were written in bulk and cannot be used to measure growth.
			</p>
		</div>

		<div class="mfa-signups-stats">
			<div class="mfa-signups-stat">
				<span class="mfa-signups-num"><?php echo esc_html( number_format_i18n( $stats['total'] ) ); ?></span>
				<span class="mfa-signups-lbl">New accounts</span>
			</div>
			<div class="mfa-signups-stat">
				<span class="mfa-signups-num"><?php echo esc_html( number_format_i18n( $stats['self'] ) ); ?></span>
				<span class="mfa-signups-lbl">Registered themselves</span>
			</div>
			<div class="mfa-signups-stat">
				<span class="mfa-signups-num"><?php echo esc_html( number_format_i18n( $stats['whatsapp'] ) ); ?></span>
				<span class="mfa-signups-lbl">Created via WhatsApp</span>
			</div>
			<div class="mfa-signups-stat">
				<span class="mfa-signups-num"><?php echo esc_html( number_format_i18n( $stats['members'] ) ); ?></span>
				<span class="mfa-signups-lbl">Became a member</span>
			</div>
			<div class="mfa-signups-stat">
				<span class="mfa-signups-num"><?php echo esc_html( number_format_i18n( $stats['referred'] ) ); ?></span>
				<span class="mfa-signups-lbl">Came via a referral</span>
			</div>
			<div class="mfa-signups-stat">
				<span class="mfa-signups-num"><?php echo esc_html( number_format_i18n( $stats['today'] ) ); ?></span>
				<span class="mfa-signups-lbl">Today</span>
			</div>
		</div>

		<?php if ( ! empty( $leads ) ) : ?>
			<h3 class="mfa-signups-subtitle">Interest captured by Sofia</h3>
			<div class="mfa-signups-stats mfa-signups-stats--leads">
				<?php foreach ( $leads as $lead ) : ?>
					<div class="mfa-signups-stat">
						<span class="mfa-signups-num"><?php echo esc_html( number_format_i18n( $lead['count'] ) ); ?></span>
						<span class="mfa-signups-lbl"><?php echo $lead['emoji']; // phpcs:ignore WordPress.Security.EscapeOutput ?> <?php echo esc_html( $lead['label'] ); ?></span>
					</div>
				<?php endforeach; ?>
			</div>
		<?php endif; ?>

		<h3 class="mfa-signups-subtitle">Most recent</h3>
		<?php if ( empty( $rows ) ) : ?>
			<p class="mfa-signups-empty">No signups yet above the watermark. The first real one will appear here.</p>
		<?php else : ?>
			<div class="mfa-signups-tablewrap">
				<table class="mfa-signups-table">
					<thead>
						<tr>
							<th>ID</th><th>Name</th><th>Email</th><th>How</th><th>Status</th><th>Referred</th><th>When</th>
						</tr>
					</thead>
					<tbody>
					<?php foreach ( $rows as $r ) :
						$is_wa  = ( false !== stripos( $r['user_email'], '@mfa.com' ) );
						$status = $r['status'] ? $r['status'] : 'prospect';
						?>
						<tr>
							<td data-label="ID">#<?php echo esc_html( $r['ID'] ); ?></td>
							<td data-label="Name"><?php echo esc_html( $r['display_name'] ); ?></td>
							<td data-label="Email"><?php echo $is_wa ? '<span class="mfa-signups-muted">&mdash;</span>' : esc_html( $r['user_email'] ); ?></td>
							<td data-label="How">
								<span class="mfa-signups-tag mfa-signups-tag--<?php echo $is_wa ? 'wa' : 'self'; ?>">
									<?php echo $is_wa ? 'WhatsApp' : 'Registered'; ?>
								</span>
							</td>
							<td data-label="Status"><?php echo esc_html( ucfirst( $status ) ); ?></td>
							<td data-label="Referred"><?php echo ! empty( $r['referrer_id'] ) ? '#' . esc_html( $r['referrer_id'] ) : '<span class="mfa-signups-muted">&mdash;</span>'; ?></td>
							<td data-label="When"><?php echo esc_html( date_i18n( 'j M, H:i', strtotime( $r['user_registered'] ) ) ); ?></td>
						</tr>
					<?php endforeach; ?>
					</tbody>
				</table>
			</div>
		<?php endif; ?>
	</section>
	<?php
	return ob_get_clean();
}
