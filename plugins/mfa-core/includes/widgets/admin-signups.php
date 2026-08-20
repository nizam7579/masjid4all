<?php
/**
 * [mfa_admin_signups] - the /admin/ dashboard: what we have, where new
 * members came from, what interest we've captured, and what needs chasing.
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
 * ## Where the numbers come from, and why
 *
 * Every count here reads the SAME column the matching list page filters
 * on - jet_cct_member.status for people, listing_status for the three
 * directories. That is not incidental: an earlier version counted
 * prospects from the user_status usermeta, which gave 34,597 while
 * /admin/prospects listed 109,407 off the CCT. Two screens disagreeing
 * about the headline number is worse than either being slightly off.
 *
 * Each section's TOTAL is the sum of its own buckets, computed in one
 * GROUP BY pass rather than a separate COUNT(*). The crawler inserts rows
 * continuously, so two queries a second apart genuinely disagree - taking
 * the total from the same pass means the rows always add up to the total
 * printed beneath them.
 *
 * ## Dates
 *
 * user_registered is meaningless across most of wp_users - the imports
 * wrote synthetic dates in bulk, and 265 users currently carry a
 * 31 Dec 2026 date. It is trustworthy for MEMBERS only, because
 * niz_user_complete_registration() resets it at activation. So:
 *   - members converted today -> user_registered (GMT)
 *   - prospects added today   -> cct_created
 * Never the other way round.
 *
 * @package mfa-core
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Cache TTL for the count queries. Four GROUP BYs over ~360k rows. */
function mfa_overview_cache_ttl() {
	return (int) apply_filters( 'mfa_overview_cache_ttl', 5 * MINUTE_IN_SECONDS );
}

/** Wraps a count callback in a transient so a dashboard load isn't four table scans. */
function mfa_overview_cached( $key, $callback ) {
	$ttl = mfa_overview_cache_ttl();
	if ( $ttl < 1 ) {
		return call_user_func( $callback );
	}

	$cache_key = 'mfa_overview_' . $key;
	$cached    = get_transient( $cache_key );
	if ( false !== $cached ) {
		return $cached;
	}

	$value = call_user_func( $callback );
	set_transient( $cache_key, $value, $ttl );

	return $value;
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

/* ---------------- Overview: people ---------------- */

/**
 * Member/prospect counts for the traction view.
 *
 * Scoped to exactly what /admin/member lists: members, premium members, and
 * prospects who actually reached out - NOT the imported contacts. The
 * imports are a mailing list, not traction; counting them here made the
 * headline read 109,407 and buried the handful of people who genuinely
 * turned up. They still exist and are still the campaign target (see the
 * note under the card), they just are not what this panel measures.
 *
 * The "reached out" predicate is shared with the list page
 * (mfa_admin_member_reached_out_sql) so the two always agree.
 */
function mfa_overview_members() {
	return mfa_overview_cached( 'members', function () {
		global $wpdb;

		$cct    = $wpdb->prefix . 'jet_cct_member';
		$counts = array( 'prospects' => 0, 'members' => 0, 'premium' => 0, 'unset' => 0, 'total' => 0, 'imported' => 0 );

		$reached = function_exists( 'mfa_admin_member_reached_out_sql' )
			? mfa_admin_member_reached_out_sql()
			: "( status = 'Prospect' AND 1 = 0 )";

		$counts['prospects'] = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$cct} WHERE {$reached}" );

		$rows = $wpdb->get_results( "SELECT status, COUNT(*) AS n FROM {$cct} GROUP BY status", ARRAY_A );

		foreach ( $rows as $row ) {
			$n = (int) $row['n'];

			switch ( (string) $row['status'] ) {
				case 'Prospect':
					// Counted above by the reached-out predicate instead.
					$counts['imported'] += $n;
					break;
				case 'Member':
					$counts['members'] += $n;
					break;
				case 'Premium Member':
				case 'Premium Lifetime':
					$counts['premium'] += $n;
					break;
				default:
					// A row with no status is invisible in BOTH list pages -
					// /admin/member restricts to member statuses and
					// /admin/prospects is locked to Prospect - so nobody would
					// ever find these people. Surfacing the count is the only
					// thing standing between them and being lost.
					$counts['unset'] += $n;
					break;
			}
		}

		// The imported pool minus the ones who have since reached out.
		$counts['imported'] = max( 0, $counts['imported'] - $counts['prospects'] );

		// Deliberately excludes 'unset': the total has to tally with what
		// /admin/member lists, and a row with no status is listed nowhere.
		// It is reported in the card's caveat line instead of being silently
		// dropped - see the render.
		$counts['total'] = $counts['prospects'] + $counts['members'] + $counts['premium'];

		// Members converted today: user_registered is reset at activation, so
		// it is real for these rows specifically. GMT, like the column.
		$counts['members_today'] = (int) $wpdb->get_var( $wpdb->prepare(
			"SELECT COUNT(*) FROM {$wpdb->users} u
			 INNER JOIN {$cct} c ON c.user_id = u.ID
			 WHERE c.status IN ('Member','Premium Member','Premium Lifetime')
			   AND DATE(u.user_registered) = %s",
			gmdate( 'Y-m-d' )
		) );

		// Prospects added today: cct_created is clean, unlike user_registered.
		$counts['prospects_today'] = (int) $wpdb->get_var( $wpdb->prepare(
			"SELECT COUNT(*) FROM {$cct} WHERE status = 'Prospect' AND DATE(cct_created) = %s",
			gmdate( 'Y-m-d' )
		) );

		$counts['today'] = $counts['members_today'] + $counts['prospects_today'];

		return $counts;
	} );
}

/**
 * Directory counts off listing_status.
 *
 * listing_status is one lifecycle, not several columns:
 *
 *   New -> (content generated) Pending | Approved | Rejected | Error
 *       -> Deleted, by an admin reviewing a Rejected or Error record
 *
 * plus the states an owner drives - Verified (claimed) and Premium
 * (subscribed) for business and website, and Active for a mosque whose
 * community has at least one member.
 *
 * @param string $table CCT suffix: mosque | business | web.
 */
function mfa_overview_listing( $table ) {
	return mfa_overview_cached( 'listing_' . $table, function () use ( $table ) {
		global $wpdb;

		$cct    = $wpdb->prefix . 'jet_cct_' . $table;
		$counts = array( 'buckets' => array(), 'total' => 0 );

		$rows = $wpdb->get_results( "SELECT listing_status AS s, COUNT(*) AS n FROM {$cct} GROUP BY listing_status", ARRAY_A );

		foreach ( $rows as $row ) {
			$n     = (int) $row['n'];
			$label = ( null === $row['s'] || '' === $row['s'] ) ? 'Unset' : (string) $row['s'];

			$counts['buckets'][ $label ] = $n;
			$counts['total']            += $n;
		}

		return $counts;
	} );
}

/**
 * Mosques whose community has at least one member.
 *
 * The lifecycle says such a mosque is Active, but nothing in the codebase
 * ever writes that status - so the condition and the status can disagree,
 * and only this tells you by how much.
 */
function mfa_overview_mosque_with_members() {
	return mfa_overview_cached( 'mosque_members', function () {
		global $wpdb;

		$cct = $wpdb->prefix . 'jet_cct_mosque';

		return (int) $wpdb->get_var(
			"SELECT COUNT(*) FROM {$cct}
			 WHERE member_count IS NOT NULL AND member_count <> ''
			   AND CAST(member_count AS UNSIGNED) > 0"
		);
	} );
}

/* ---------------- How new members arrived ---------------- */

/**
 * Where members came from.
 *
 * Note mfa_registration_route only started being written on 2026-08-19,
 * so every member who predates it counts as "Before tracking" rather than
 * being guessed at. Direct/Referral and "from an imported prospect" work
 * for all members regardless, since they read data that always existed.
 */
function mfa_overview_arrival() {
	return mfa_overview_cached( 'arrival', function () {
		global $wpdb;

		$cct  = $wpdb->prefix . 'jet_cct_member';
		$out  = array( 'routes' => array(), 'direct' => 0, 'referral' => 0, 'from_prospect' => 0, 'total' => 0 );

		foreach ( array_keys( mfa_signup_routes() ) as $key ) {
			$out['routes'][ $key ] = 0;
		}

		$rows = $wpdb->get_results(
			"SELECT COALESCE(NULLIF(r.meta_value,''),'unknown') AS route,
			        COALESCE(ls.meta_value,'') AS lead_source,
			        c.referrer_id
			 FROM {$wpdb->users} u
			 INNER JOIN {$cct} c ON c.user_id = u.ID
			        AND c.status IN ('Member','Premium Member','Premium Lifetime')
			 LEFT JOIN {$wpdb->usermeta} r  ON r.user_id  = u.ID AND r.meta_key = 'mfa_registration_route'
			 LEFT JOIN {$wpdb->usermeta} ls ON ls.user_id = u.ID AND ls.meta_key = 'lead_source'",
			ARRAY_A
		);

		foreach ( $rows as $row ) {
			$out['total']++;

			$route = isset( $out['routes'][ $row['route'] ] ) ? $row['route'] : 'unknown';
			$out['routes'][ $route ]++;

			// 14270 is the "no real referrer" sentinel used across the codebase.
			if ( ! empty( $row['referrer_id'] ) && 14270 !== (int) $row['referrer_id'] ) {
				$out['referral']++;
			} else {
				$out['direct']++;
			}

			// The imports stamped lead_source as directory:<type>:<id>. A member
			// carrying one converted from an imported prospect - the number the
			// fbads / bulk WhatsApp / email campaigns are trying to move.
			if ( 0 === strpos( (string) $row['lead_source'], 'directory:' ) ) {
				$out['from_prospect']++;
			}
		}

		return $out;
	} );
}

/* ---------------- Interest ---------------- */

/**
 * Lead counts per type - the "gauge interest" numbers.
 *
 * Driven by the lead registry (mfa_lead_types()) rather than a hardcoded
 * list, so registering a new lead magnet adds its tile here with no edit
 * to this file. Read from the mfa_sofia_leads user meta rather than
 * FluentCRM, so it keeps working if the CRM is deactivated, and counts
 * leads from contacts of any status - a prospect who joins the waitlist is
 * exactly the signal being measured.
 */
function mfa_signup_lead_counts() {
	$types = function_exists( 'mfa_lead_types' ) ? mfa_lead_types() : array();
	if ( empty( $types ) ) {
		return array();
	}

	return mfa_overview_cached( 'leads', function () use ( $types ) {
		global $wpdb;

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
	} );
}

/* ---------------- Needs follow-up ---------------- */

/**
 * The work queue: what someone should act on today.
 *
 * The open-window count is the only number here with an expiry - outside
 * 24 hours a free-form WhatsApp message is refused by Meta and it has to
 * be an approved template, so it is deliberately first.
 */
function mfa_overview_followup() {
	return mfa_overview_cached( 'followup', function () {
		global $wpdb;

		$cct = $wpdb->prefix . 'jet_cct_member';
		$out = array( 'open_window' => 0, 'no_email' => 0, 'open_inquiries' => 0 );

		$conv = $wpdb->prefix . 'nwa_conversations';
		if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $conv ) ) ) {
			// window_expires_at is GMT (see CLAUDE.md) - compare against GMT.
			$out['open_window'] = (int) $wpdb->get_var(
				"SELECT COUNT(*) FROM {$conv} WHERE user_id > 0 AND window_expires_at > UTC_TIMESTAMP()"
			);
		}

		$members = $wpdb->get_results(
			"SELECT u.ID, u.user_email FROM {$wpdb->users} u
			 INNER JOIN {$cct} c ON c.user_id = u.ID
			 WHERE c.status IN ('Member','Premium Member','Premium Lifetime')",
			ARRAY_A
		);

		foreach ( $members as $m ) {
			if ( function_exists( 'mfa_is_placeholder_email' ) && mfa_is_placeholder_email( $m['user_email'] ) ) {
				$out['no_email']++;
			}
		}

		$inq = $wpdb->prefix . 'jet_cct_contact_us';
		if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $inq ) ) ) {
			$out['open_inquiries'] = (int) $wpdb->get_var(
				"SELECT COUNT(*) FROM {$inq} WHERE cct_status IS NULL OR cct_status NOT IN ('Replied','Archived')"
			);
		}

		return $out;
	} );
}

/* ---------------- Render helpers ---------------- */

/** One label/number row, linked to its filtered list when one exists. */
function mfa_overview_row( $label, $value, $url = '', $note = '' ) {
	$num = '<span class="mfa-ov-num">' . esc_html( number_format_i18n( (int) $value ) ) . '</span>';
	$lbl = '<span class="mfa-ov-lbl">' . esc_html( $label )
		. ( $note ? ' <small class="mfa-ov-note">' . esc_html( $note ) . '</small>' : '' )
		. '</span>';

	if ( $url ) {
		return '<a class="mfa-ov-row is-link" href="' . esc_url( $url ) . '">' . $lbl . $num . '</a>';
	}

	return '<div class="mfa-ov-row">' . $lbl . $num . '</div>';
}

/** A card of rows with a total underneath, and an optional caveat line. */
function mfa_overview_card( $title, $rows_html, $total, $total_url = '', $flag = '' ) {
	$total_row = mfa_overview_row( 'Total', $total, $total_url );

	return '<div class="mfa-ov-card">'
		. '<h3 class="mfa-ov-card-title">' . esc_html( $title ) . '</h3>'
		. '<div class="mfa-ov-rows">' . $rows_html . '</div>'
		. '<div class="mfa-ov-total">' . $total_row . '</div>'
		. ( $flag ? '<p class="mfa-ov-flag">' . esc_html( $flag ) . '</p>' : '' )
		. '</div>';
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

	$people   = mfa_overview_members();
	$mosque   = mfa_overview_listing( 'mosque' );
	$business = mfa_overview_listing( 'business' );
	$website  = mfa_overview_listing( 'web' );
	$arrival  = mfa_overview_arrival();
	$leads    = mfa_signup_lead_counts();
	$follow   = mfa_overview_followup();
	$routes   = mfa_signup_routes();

	$member_url   = home_url( '/admin/member/' );
	$prospect_url = home_url( '/admin/prospects/' );

	/**
	 * Renders one directory card's rows.
	 *
	 * The bucket list comes from the same function the list page builds its
	 * filter dropdown from, so the dashboard and the list can never offer
	 * different states. Every state in the lifecycle is shown even when it
	 * holds nothing - a status reading 0 is information ("no owner has
	 * claimed a business yet"), whereas a missing row just looks like the
	 * dashboard forgot about it. Anything in the data that ISN'T in the
	 * canonical list is still shown, so a stray value can't hide.
	 */
	$render_listing = function ( $stats, $section, $order ) {
		$base = home_url( '/admin/' . $section . '/' );
		$html = '';

		foreach ( $order as $bucket ) {
			$n     = isset( $stats['buckets'][ $bucket ] ) ? $stats['buckets'][ $bucket ] : 0;
			$html .= mfa_overview_row( $bucket, $n, $n ? add_query_arg( 'status', $bucket, $base ) : '' );
		}

		foreach ( $stats['buckets'] as $bucket => $n ) {
			if ( ! in_array( $bucket, $order, true ) ) {
				$note  = ( 'Unset' === $bucket ) ? 'not in the lifecycle' : 'unexpected status';
				$url   = ( 'Unset' === $bucket ) ? '' : add_query_arg( 'status', $bucket, $base );
				$html .= mfa_overview_row( $bucket, $n, $url, $note );
			}
		}

		return $html;
	};

	$mosque_order   = function_exists( 'mfa_admin_mosque_status_options' ) ? mfa_admin_mosque_status_options() : array();
	$business_order = function_exists( 'mfa_admin_business_status_options' ) ? mfa_admin_business_status_options() : array();
	$website_order  = function_exists( 'mfa_admin_website_status_options' ) ? mfa_admin_website_status_options() : array();

	$with_members = mfa_overview_mosque_with_members();
	$active_now   = isset( $mosque['buckets']['Active'] ) ? $mosque['buckets']['Active'] : 0;
	$mosque_flag  = ( $with_members > $active_now )
		? sprintf(
			/* translators: %d: number of mosques with at least one member. */
			'%d have a member but are not marked Active.',
			$with_members - $active_now
		)
		: '';

	ob_start();
	?>
	<section class="mfa-signups">

		<h2 class="mfa-ov-title">Overview</h2>
		<div class="mfa-ov-grid">
			<?php
			// --- People
			$people_rows  = mfa_overview_row( 'Today', $people['today'], '', sprintf( '%d members, %d prospects', $people['members_today'], $people['prospects_today'] ) );
			$people_rows .= mfa_overview_row( 'Prospects', $people['prospects'], $member_url, 'reached out to us' );
			$people_rows .= mfa_overview_row( 'Members', $people['members'], add_query_arg( 'status', 'Member', $member_url ) );
			$people_rows .= mfa_overview_row( 'Premium', $people['premium'], add_query_arg( 'status', 'Premium Member', $member_url ) );
			$people_flag = $people['imported']
				? sprintf(
					/* translators: %s: formatted count of imported contacts. */
					'Excludes %s imported contacts, counted at /admin/prospects.',
					number_format_i18n( $people['imported'] )
				)
				: '';
			if ( $people['unset'] > 0 ) {
				// Listed by neither page, so it can only be surfaced here.
				$people_flag .= sprintf(
					/* translators: %d: number of member rows with no status. */
					' %d row(s) have no status and appear in no list.',
					$people['unset']
				);
			}
			echo mfa_overview_card( 'Members', $people_rows, $people['total'], '', $people_flag ); // phpcs:ignore WordPress.Security.EscapeOutput

			echo mfa_overview_card( 'Mosque', $render_listing( $mosque, 'mosque', $mosque_order ), $mosque['total'], home_url( '/admin/mosque/' ), $mosque_flag ); // phpcs:ignore WordPress.Security.EscapeOutput
			echo mfa_overview_card( 'Business', $render_listing( $business, 'business', $business_order ), $business['total'], home_url( '/admin/business/' ) ); // phpcs:ignore WordPress.Security.EscapeOutput
			echo mfa_overview_card( 'Website', $render_listing( $website, 'website', $website_order ), $website['total'], home_url( '/admin/website/' ) ); // phpcs:ignore WordPress.Security.EscapeOutput
			?>
		</div>

		<h2 class="mfa-ov-title">How the new members arrived</h2>
		<div class="mfa-signups-stats mfa-signups-stats--routes">
			<?php foreach ( $routes as $key => $label ) : ?>
				<div class="mfa-signups-stat">
					<span class="mfa-signups-num"><?php echo esc_html( number_format_i18n( $arrival['routes'][ $key ] ) ); ?></span>
					<span class="mfa-signups-lbl"><?php echo esc_html( $label ); ?></span>
				</div>
			<?php endforeach; ?>
			<div class="mfa-signups-stat">
				<span class="mfa-signups-num"><?php echo esc_html( number_format_i18n( $arrival['direct'] ) ); ?></span>
				<span class="mfa-signups-lbl">Direct</span>
			</div>
			<div class="mfa-signups-stat">
				<span class="mfa-signups-num"><?php echo esc_html( number_format_i18n( $arrival['referral'] ) ); ?></span>
				<span class="mfa-signups-lbl">Referral</span>
			</div>
			<div class="mfa-signups-stat mfa-signups-stat--highlight">
				<span class="mfa-signups-num"><?php echo esc_html( number_format_i18n( $arrival['from_prospect'] ) ); ?></span>
				<span class="mfa-signups-lbl">From a prospect</span>
			</div>
		</div>
		<p class="mfa-ov-foot">
			&ldquo;From a prospect&rdquo; counts members who started as one of the
			<?php echo esc_html( number_format_i18n( $people['imported'] ) ); ?> imported contacts &mdash;
			the number the ads, bulk WhatsApp and email campaigns are trying to move.
			Route tracking began on 19 Aug, so anyone who joined earlier counts as &ldquo;Before tracking&rdquo;.
		</p>

		<?php if ( ! empty( $leads ) ) : ?>
			<h2 class="mfa-ov-title">Interest &amp; leads</h2>
			<div class="mfa-signups-stats mfa-signups-stats--leads">
				<?php foreach ( $leads as $lead ) : ?>
					<div class="mfa-signups-stat">
						<span class="mfa-signups-num"><?php echo esc_html( number_format_i18n( $lead['count'] ) ); ?></span>
						<span class="mfa-signups-lbl"><?php echo $lead['emoji']; // phpcs:ignore WordPress.Security.EscapeOutput ?> <?php echo esc_html( $lead['label'] ); ?></span>
					</div>
				<?php endforeach; ?>
			</div>
		<?php endif; ?>

		<h2 class="mfa-ov-title">Needs follow-up</h2>
		<div class="mfa-signups-stats mfa-signups-stats--followup">
			<div class="mfa-signups-stat<?php echo $follow['open_window'] ? ' mfa-signups-stat--urgent' : ''; ?>">
				<span class="mfa-signups-num"><?php echo esc_html( number_format_i18n( $follow['open_window'] ) ); ?></span>
				<span class="mfa-signups-lbl">WhatsApp window open <small>reply free-form now</small></span>
			</div>
			<a class="mfa-signups-stat" href="<?php echo esc_url( home_url( '/admin/inquiry/' ) ); ?>">
				<span class="mfa-signups-num"><?php echo esc_html( number_format_i18n( $follow['open_inquiries'] ) ); ?></span>
				<span class="mfa-signups-lbl">Inquiries not replied</span>
			</a>
			<a class="mfa-signups-stat" href="<?php echo esc_url( $member_url ); ?>">
				<span class="mfa-signups-num"><?php echo esc_html( number_format_i18n( $follow['no_email'] ) ); ?></span>
				<span class="mfa-signups-lbl">Members with no real email</span>
			</a>
		</div>

	</section>
	<?php
	return ob_get_clean();
}
