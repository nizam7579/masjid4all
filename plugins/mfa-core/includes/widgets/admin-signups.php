<?php
/**
 * [mfa_admin_signups] - real member conversions, shown on /admin/.
 *
 * The model this reflects (agreed 2026-08-19):
 *
 *   Everyone starts as a **prospect** - imported contacts, and accounts
 *   created automatically the first time someone messages Sofia from an
 *   unknown number. A prospect is just a contact record.
 *
 *   They become a **member** only by completing a real registration, by
 *   one of three routes: Sofia (WhatsApp), Google, or the web form. All
 *   three funnel through niz_user_complete_registration(), which sets
 *   user_status to 'member' AND resets user_registered to that moment.
 *
 * That reset is what makes this panel trustworthy. user_registered is
 * otherwise meaningless across most of wp_users: the imports wrote
 * synthetic dates in bulk (every month from May to October 2026 spans the
 * same full ID range, user ID 2 carries an October date, and two of those
 * months are in the future). Filtering on status AND a reset date sidesteps
 * all of it - prospects are excluded by status, and any member's date is
 * the moment they actually converted.
 *
 * An earlier version of this panel counted by user ID above a watermark.
 * That worked, but status + date is strictly better: it survives future
 * imports, and it counts the conversion rather than the account.
 *
 * @package mfa-core
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Start of the period treated as "new". Stored so it can move without a deploy. */
function mfa_signup_since() {
	$date = get_option( 'mfa_signup_since_date', '' );

	if ( '' === $date ) {
		// The day the status+date model went live.
		$date = '2026-08-19';
		update_option( 'mfa_signup_since_date', $date, false );
	}

	return (string) apply_filters( 'mfa_signup_since', $date );
}

/** Human-readable registration routes. */
function mfa_signup_routes() {
	return array(
		'whatsapp' => 'Sofia (WhatsApp)',
		'google'   => 'Google',
		'web'      => 'Web form',
		'unknown'  => 'Before tracking',
	);
}

/**
 * Conversion counts.
 *
 * "Member" means user_status is member or premium - the statuses that mean
 * someone completed a registration. Everything else (prospect, or no status
 * meta at all, which is true of 74,812 imported rows) is a contact, not a
 * conversion, and is deliberately excluded.
 */
function mfa_signup_stats() {
	global $wpdb;

	$since = mfa_signup_since();
	$stats = array( 'since' => $since );

	$member_join = "INNER JOIN {$wpdb->usermeta} s ON s.user_id = u.ID
	                AND s.meta_key = 'user_status'
	                AND s.meta_value IN ('member','premium')";

	$stats['members_total'] = (int) $wpdb->get_var(
		"SELECT COUNT(*) FROM {$wpdb->users} u {$member_join}"
	);

	$stats['members_new'] = (int) $wpdb->get_var( $wpdb->prepare(
		"SELECT COUNT(*) FROM {$wpdb->users} u {$member_join} WHERE u.user_registered >= %s",
		$since . ' 00:00:00'
	) );

	// user_registered is GMT, so compare against the GMT date.
	$stats['today'] = (int) $wpdb->get_var( $wpdb->prepare(
		"SELECT COUNT(*) FROM {$wpdb->users} u {$member_join} WHERE DATE(u.user_registered) = %s",
		gmdate( 'Y-m-d' )
	) );

	$stats['prospects'] = (int) $wpdb->get_var(
		"SELECT COUNT(*) FROM {$wpdb->usermeta} WHERE meta_key = 'user_status' AND meta_value = 'prospect'"
	);

	$cct = $wpdb->prefix . 'jet_cct_member';
	$stats['referred'] = (int) $wpdb->get_var( $wpdb->prepare(
		"SELECT COUNT(*) FROM {$wpdb->users} u {$member_join}
		 INNER JOIN {$cct} c ON c.user_id = u.ID
		 WHERE u.user_registered >= %s AND c.referrer_id > 0 AND c.referrer_id <> 14270",
		$since . ' 00:00:00'
	) );

	// Split the new members by how they arrived.
	$stats['routes'] = array();
	foreach ( mfa_signup_routes() as $key => $label ) {
		$stats['routes'][ $key ] = (int) $wpdb->get_var( $wpdb->prepare(
			"SELECT COUNT(*) FROM {$wpdb->users} u {$member_join}
			 LEFT JOIN {$wpdb->usermeta} r ON r.user_id = u.ID AND r.meta_key = 'mfa_registration_route'
			 WHERE u.user_registered >= %s AND COALESCE(NULLIF(r.meta_value,''),'unknown') = %s",
			$since . ' 00:00:00',
			$key
		) );
	}

	return $stats;
}

/**
 * Sofia lead counts per type - the "gauge interest" numbers.
 *
 * Read from the mfa_sofia_leads user meta rather than FluentCRM, so it
 * keeps working if the CRM is deactivated, and counts leads from contacts
 * of any status - a prospect who joins the waitlist is exactly the signal
 * being measured.
 */
function mfa_signup_lead_counts() {
	global $wpdb;

	$types = function_exists( 'mfa_lead_types' ) ? mfa_lead_types() : array();
	if ( empty( $types ) ) {
		return array();
	}

	$counts = array();
	foreach ( $types as $key => $cfg ) {
		$counts[ $key ] = array( 'label' => $cfg['label'], 'emoji' => $cfg['emoji'], 'count' => 0 );
	}

	$rows = $wpdb->get_col( "SELECT meta_value FROM {$wpdb->usermeta} WHERE meta_key = 'mfa_sofia_leads'" );

	foreach ( $rows as $raw ) {
		$leads = maybe_unserialize( $raw );
		if ( ! is_array( $leads ) ) {
			continue;
		}
		foreach ( array_keys( $leads ) as $type ) {
			if ( isset( $counts[ $type ] ) ) {
				$counts[ $type ]['count']++;
			}
		}
	}

	return $counts;
}

/** Most recent conversions, newest first. */
function mfa_signup_recent( $limit = 15 ) {
	global $wpdb;

	$cct = $wpdb->prefix . 'jet_cct_member';

	return $wpdb->get_results( $wpdb->prepare(
		"SELECT u.ID, u.user_email, u.display_name, u.user_registered,
		        s.meta_value AS status,
		        COALESCE(NULLIF(r.meta_value,''),'unknown') AS route,
		        o.meta_value AS first_seen,
		        c.referrer_id
		 FROM {$wpdb->users} u
		 INNER JOIN {$wpdb->usermeta} s ON s.user_id = u.ID AND s.meta_key = 'user_status'
		        AND s.meta_value IN ('member','premium')
		 LEFT JOIN {$wpdb->usermeta} r ON r.user_id = u.ID AND r.meta_key = 'mfa_registration_route'
		 LEFT JOIN {$wpdb->usermeta} o ON o.user_id = u.ID AND o.meta_key = 'mfa_original_registered'
		 LEFT JOIN {$cct} c ON c.user_id = u.ID
		 ORDER BY u.user_registered DESC
		 LIMIT %d",
		(int) $limit
	), ARRAY_A );
}

/* ---------------- Render ---------------- */

add_shortcode( 'mfa_admin_signups', 'mfa_admin_signups_shortcode' );
function mfa_admin_signups_shortcode() {
	if ( ! is_user_logged_in() ) {
		return '';
	}
	if ( function_exists( 'mfa_user_can_access_admin_section' ) && ! mfa_user_can_access_admin_section( 'dashboard' ) ) {
		return '';
	}

	$stats  = mfa_signup_stats();
	$leads  = mfa_signup_lead_counts();
	$rows   = mfa_signup_recent( 15 );
	$routes = mfa_signup_routes();

	$since_label = date_i18n( 'j M Y', strtotime( $stats['since'] ) );

	ob_start();
	?>
	<section class="mfa-signups">
		<div class="mfa-signups-head">
			<h2 class="mfa-signups-title">Members</h2>
			<p class="mfa-signups-note">
				Someone becomes a member only by completing a registration &mdash; through Sofia, Google or the web form.
				Contacts we created for them (imports, or a first WhatsApp message) stay <strong>prospects</strong> and are not counted here.
				&ldquo;New&rdquo; means converted since <strong><?php echo esc_html( $since_label ); ?></strong>.
			</p>
		</div>

		<div class="mfa-signups-stats">
			<div class="mfa-signups-stat">
				<span class="mfa-signups-num"><?php echo esc_html( number_format_i18n( $stats['members_new'] ) ); ?></span>
				<span class="mfa-signups-lbl">New members</span>
			</div>
			<div class="mfa-signups-stat">
				<span class="mfa-signups-num"><?php echo esc_html( number_format_i18n( $stats['today'] ) ); ?></span>
				<span class="mfa-signups-lbl">Today</span>
			</div>
			<div class="mfa-signups-stat">
				<span class="mfa-signups-num"><?php echo esc_html( number_format_i18n( $stats['referred'] ) ); ?></span>
				<span class="mfa-signups-lbl">Via a referral</span>
			</div>
			<div class="mfa-signups-stat">
				<span class="mfa-signups-num"><?php echo esc_html( number_format_i18n( $stats['members_total'] ) ); ?></span>
				<span class="mfa-signups-lbl">Members all time</span>
			</div>
			<div class="mfa-signups-stat">
				<span class="mfa-signups-num"><?php echo esc_html( number_format_i18n( $stats['prospects'] ) ); ?></span>
				<span class="mfa-signups-lbl">Prospects to convert</span>
			</div>
		</div>

		<h3 class="mfa-signups-subtitle">How the new members arrived</h3>
		<div class="mfa-signups-stats mfa-signups-stats--routes">
			<?php foreach ( $routes as $key => $label ) : ?>
				<div class="mfa-signups-stat">
					<span class="mfa-signups-num"><?php echo esc_html( number_format_i18n( $stats['routes'][ $key ] ) ); ?></span>
					<span class="mfa-signups-lbl"><?php echo esc_html( $label ); ?></span>
				</div>
			<?php endforeach; ?>
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

		<h3 class="mfa-signups-subtitle">Most recent conversions</h3>
		<?php if ( empty( $rows ) ) : ?>
			<p class="mfa-signups-empty">No members yet. The first completed registration will appear here.</p>
		<?php else : ?>
			<div class="mfa-signups-tablewrap">
				<table class="mfa-signups-table">
					<thead>
						<tr>
							<th>Name</th><th>Email</th><th>Via</th><th>Status</th><th>Referred</th><th>First seen</th><th>Converted</th>
						</tr>
					</thead>
					<tbody>
					<?php foreach ( $rows as $r ) :
						$route_key   = isset( $routes[ $r['route'] ] ) ? $r['route'] : 'unknown';
						$is_placeholder = ( false !== stripos( $r['user_email'], '@mfa.com' ) );
						// 14270 is the "no real referrer" sentinel used across the codebase.
						$has_ref = ! empty( $r['referrer_id'] ) && 14270 !== (int) $r['referrer_id'];
						?>
						<tr>
							<td data-label="Name"><?php echo esc_html( $r['display_name'] ); ?></td>
							<td data-label="Email"><?php echo $is_placeholder ? '<span class="mfa-signups-muted">no email yet</span>' : esc_html( $r['user_email'] ); ?></td>
							<td data-label="Via">
								<span class="mfa-signups-tag mfa-signups-tag--<?php echo esc_attr( $route_key ); ?>"><?php echo esc_html( $routes[ $route_key ] ); ?></span>
							</td>
							<td data-label="Status"><?php echo esc_html( ucfirst( (string) $r['status'] ) ); ?></td>
							<td data-label="Referred"><?php echo $has_ref ? '#' . esc_html( $r['referrer_id'] ) : '<span class="mfa-signups-muted">&mdash;</span>'; ?></td>
							<td data-label="First seen">
								<?php echo ! empty( $r['first_seen'] )
									? esc_html( date_i18n( 'j M Y', strtotime( $r['first_seen'] ) ) )
									: '<span class="mfa-signups-muted">&mdash;</span>'; ?>
							</td>
							<td data-label="Converted"><?php echo esc_html( date_i18n( 'j M, H:i', strtotime( $r['user_registered'] ) ) ); ?></td>
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
