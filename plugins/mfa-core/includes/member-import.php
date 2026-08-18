<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Imports phone numbers from the mosque, business and website directories as
 * Prospect members, for the bulk WhatsApp campaign.
 *
 * Progress is tracked so a run never re-reads what it has already seen. Each
 * source moves through two phases:
 *
 *   1. Sweep    - walk the whole table once by _ID, remembering the cursor.
 *   2. Follow-up - once swept, only rows created or edited since the last run,
 *                  the same watermark pattern the website import already uses.
 *
 * Batched rather than run in one pass: the first sweep covers ~93,000
 * phone-bearing rows and creates tens of thousands of WordPress users, and
 * wp_insert_user() hashes a password every time. That is minutes of work, well
 * past any request timeout, so the runner page processes one batch per load and
 * advances itself - the same shape as the crawler and website generator pages.
 *
 * Only numbers classified as mobile are imported. See phone-extract.php for
 * why US/Canada numbers and countries without a mobile/fixed prefix split are
 * rejected rather than guessed at.
 */

/**
 * The registered date stamped on every imported prospect.
 *
 * Deliberately in the future. Both member counters - the homepage "Our Members"
 * figure and the /admin/reports/ member tab - bound their queries by
 * `user_registered <= now`, so a future date keeps this cohort out of the
 * running total until the date arrives, which is the whole point of importing
 * them separately.
 *
 * It is written to BOTH wp_users.user_registered and jet_cct_member.registered:
 * the counters read the wp_users column, so setting only the CCT field would
 * leave the cohort in the totals.
 */
function mfa_member_import_cohort_date() {
	return '2026-12-01 00:00:00';
}

/**
 * The directories that carry phone numbers, in the order the panel lists them.
 */
function mfa_member_import_sources() {
	return array(
		'mosque'   => 'Mosque',
		'business' => 'Business',
		'web'      => 'Website',
	);
}

/**
 * Per-source progress. Kept in one option so the panel can render the whole
 * picture without three reads.
 */
function mfa_member_import_state() {
	$default = array();

	foreach ( array_keys( mfa_member_import_sources() ) as $source ) {
		$default[ $source ] = array(
			'cursor'      => 0,
			'sweep_done'  => false,
			'incr_cursor' => 0,
			'last_run'    => '',
			'scanned'     => 0,
			'added'       => 0,
		);
	}

	$state = get_option( 'mfa_member_import_state', array() );

	if ( ! is_array( $state ) ) {
		$state = array();
	}

	foreach ( $default as $source => $row ) {
		$state[ $source ] = isset( $state[ $source ] ) && is_array( $state[ $source ] )
			? array_merge( $row, $state[ $source ] )
			: $row;
	}

	return $state;
}

function mfa_member_import_save_state( $state ) {
	update_option( 'mfa_member_import_state', $state, false );
}

/**
 * Clears all progress so the next run sweeps every table again. Only reachable
 * from the panel's Full rescan button.
 */
function mfa_member_import_reset_state() {
	delete_option( 'mfa_member_import_state' );
}

/**
 * How many phone-bearing rows each source holds, for the progress display.
 * Cached briefly - these are COUNT(*) over six-figure tables and the runner
 * page asks on every load.
 */
function mfa_member_import_totals() {
	$totals = get_transient( 'mfa_member_import_totals' );

	if ( is_array( $totals ) ) {
		return $totals;
	}

	global $wpdb;
	$totals = array();

	foreach ( array_keys( mfa_member_import_sources() ) as $source ) {
		$table           = $wpdb->prefix . 'jet_cct_' . $source;
		$totals[ $source ] = (int) $wpdb->get_var(
			"SELECT COUNT(*) FROM {$table} WHERE ( phone IS NOT NULL AND phone <> '' ) OR ( whatsapp IS NOT NULL AND whatsapp <> '' )"
		);
	}

	set_transient( 'mfa_member_import_totals', $totals, HOUR_IN_SECONDS );

	return $totals;
}

/**
 * A canonical country name for the ISO the phone resolved to, used when the
 * record's own country column is empty or unrecognised but the number carried
 * its country code.
 */
function mfa_member_import_country_name( $iso ) {
	$rules = mfa_phone_country_rules();

	if ( ! isset( $rules[ $iso ]['names'][0] ) ) {
		return '';
	}

	return ucwords( $rules[ $iso ]['names'][0] );
}

/**
 * Process one batch from one source.
 *
 * @param string $source One of mfa_member_import_sources().
 * @param int    $limit  Rows to read this batch.
 * @param bool   $apply  False for a dry run - classifies and reports but
 *                       creates nothing and does not move the cursor.
 *
 * @return array Report for the runner page and the panel.
 */
function mfa_member_import_batch( $source, $limit = 200, $apply = true ) {
	global $wpdb;

	$sources = mfa_member_import_sources();

	if ( ! isset( $sources[ $source ] ) ) {
		return array( 'error' => 'Unknown source.' );
	}

	// A dry run writes nothing and does not move the cursor, so it needs no
	// lock and must not be blocked by a run in progress.
	if ( ! $apply ) {
		return mfa_member_import_run_batch( $source, $limit, false );
	}

	// Two runners on the same source read the same cursor, resolve the same
	// numbers, and both pass the dedupe check before either has committed -
	// whichever loses the race then fails at insert on username_exists. Not
	// corrupting (nothing duplicate is created) but pure wasted work, and it
	// happens as soon as someone opens the import page in two tabs. Serialise
	// per source; a timeout of 0 means the second caller reports busy rather
	// than queueing up behind a batch it would only repeat.
	$lock = 'mfa_member_import_' . $source;

	if ( ! (int) $wpdb->get_var( $wpdb->prepare( 'SELECT GET_LOCK(%s, 0)', $lock ) ) ) {
		return array(
			'source'       => $source,
			'label'        => $sources[ $source ],
			'phase'        => 'busy',
			'busy'         => true,
			'scanned'      => 0,
			'added'        => 0,
			'dup_existing' => 0,
			'dup_batch'    => 0,
			'failed'       => 0,
			'reasons'      => array(),
			'complete'     => false,
			'samples'      => array(),
		);
	}

	$report = mfa_member_import_run_batch( $source, $limit, $apply );

	$wpdb->query( $wpdb->prepare( 'SELECT RELEASE_LOCK(%s)', $lock ) );

	return $report;
}

/**
 * The batch itself. Always call it through mfa_member_import_batch(), which
 * owns the per-source lock.
 */
function mfa_member_import_run_batch( $source, $limit, $apply ) {
	global $wpdb;

	$sources = mfa_member_import_sources();

	$limit = max( 1, min( 1000, (int) $limit ) );
	$table = $wpdb->prefix . 'jet_cct_' . $source;
	$state = mfa_member_import_state();
	$s     = $state[ $source ];

	$report = array(
		'source'    => $source,
		'label'     => $sources[ $source ],
		'phase'     => $s['sweep_done'] ? 'follow-up' : 'sweep',
		'scanned'   => 0,
		'added'     => 0,
		'dup_existing' => 0,
		'dup_batch'    => 0,
		'failed'    => 0,
		'reasons'   => array(),
		'complete'  => false,
		'samples'   => array(),
	);

	// Only rows that actually carry a number - scanning the 96,000 mosques
	// without one would be 480 empty batches before reaching any work.
	$has_phone = "( ( phone IS NOT NULL AND phone <> '' ) OR ( whatsapp IS NOT NULL AND whatsapp <> '' ) )";

	if ( ! $s['sweep_done'] ) {
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$table} WHERE _ID > %d AND {$has_phone} ORDER BY _ID ASC LIMIT %d",
				(int) $s['cursor'],
				$limit
			),
			ARRAY_A
		);
	} else {
		$since = $s['last_run'] ? $s['last_run'] : '1970-01-01 00:00:00';
		$rows  = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$table}
				 WHERE _ID > %d AND {$has_phone} AND ( cct_created >= %s OR cct_modified >= %s )
				 ORDER BY _ID ASC LIMIT %d",
				(int) $s['incr_cursor'],
				$since,
				$since,
				$limit
			),
			ARRAY_A
		);
	}

	if ( empty( $rows ) ) {
		$report['complete'] = true;

		if ( $apply ) {
			if ( ! $s['sweep_done'] ) {
				$state[ $source ]['sweep_done'] = true;
				$state[ $source ]['last_run']   = current_time( 'mysql' );
			} else {
				$state[ $source ]['last_run']    = current_time( 'mysql' );
				$state[ $source ]['incr_cursor'] = 0;
			}
			mfa_member_import_save_state( $state );
		}

		return $report;
	}

	// Resolve every number first, then dedupe the whole batch in one query.
	// Checking phones one at a time would be 200 full scans of a 75,000-row
	// unindexed text column per batch.
	$candidates = array();

	foreach ( $rows as $row ) {
		$report['scanned']++;

		$raw = '';

		// The whatsapp column, where filled, is an explicit WhatsApp number and
		// therefore a better target than the general phone line.
		if ( ! empty( $row['whatsapp'] ) ) {
			$raw = $row['whatsapp'];
		} elseif ( ! empty( $row['phone'] ) ) {
			$raw = $row['phone'];
		}

		$country = isset( $row['country'] ) ? (string) $row['country'] : '';
		$result  = mfa_phone_normalize( $raw, $country );

		if ( ! $result['ok'] ) {
			$reason = $result['reason'];
			$report['reasons'][ $reason ] = isset( $report['reasons'][ $reason ] ) ? $report['reasons'][ $reason ] + 1 : 1;
			continue;
		}

		$candidates[] = array(
			'phone'     => $result['phone'],
			'iso'       => $result['iso'],
			'country'   => '' !== $country && mfa_phone_iso_from_country( $country ) === $result['iso']
				? $country
				: mfa_member_import_country_name( $result['iso'] ),
			'name'      => isset( $row['name'] ) ? (string) $row['name'] : '',
			'source_id' => (int) $row['_ID'],
		);
	}

	$last_id            = (int) $rows[ count( $rows ) - 1 ]['_ID'];
	$report['reasons']['ok'] = count( $candidates );

	if ( empty( $candidates ) ) {
		if ( $apply ) {
			$state = mfa_member_import_advance( $state, $source, $last_id, $report );
			mfa_member_import_save_state( $state );
		}

		return $report;
	}

	$phones       = wp_list_pluck( $candidates, 'phone' );
	$placeholders = implode( ', ', array_fill( 0, count( $phones ), '%s' ) );
	$member_table = $wpdb->prefix . 'jet_cct_member';

	$existing = $wpdb->get_col(
		$wpdb->prepare(
			"SELECT phone FROM {$member_table} WHERE phone IN ( {$placeholders} )",
			$phones
		)
	);

	$existing = array_flip( $existing );
	$seen     = array();

	foreach ( $candidates as $candidate ) {
		$phone = $candidate['phone'];

		if ( isset( $existing[ $phone ] ) ) {
			$report['dup_existing']++;
			continue;
		}

		if ( isset( $seen[ $phone ] ) ) {
			$report['dup_batch']++;
			continue;
		}

		$seen[ $phone ] = true;

		if ( ! $apply ) {
			$report['added']++;

			if ( count( $report['samples'] ) < 5 ) {
				$report['samples'][] = $phone . ' (' . $candidate['iso'] . ') ' . $candidate['name'];
			}

			continue;
		}

		$user_id = mfa_member_import_create( $candidate, $source );

		if ( is_wp_error( $user_id ) ) {
			$report['failed']++;
			continue;
		}

		$report['added']++;

		if ( count( $report['samples'] ) < 5 ) {
			$report['samples'][] = $phone . ' (' . $candidate['iso'] . ') ' . $candidate['name'];
		}
	}

	if ( $apply ) {
		$state = mfa_member_import_advance( $state, $source, $last_id, $report );
		mfa_member_import_save_state( $state );
	}

	return $report;
}

/**
 * Move the cursor for whichever phase is running and fold the batch's counts
 * into the running totals.
 */
function mfa_member_import_advance( $state, $source, $last_id, $report ) {
	if ( $state[ $source ]['sweep_done'] ) {
		$state[ $source ]['incr_cursor'] = $last_id;
	} else {
		$state[ $source ]['cursor'] = $last_id;
	}

	$state[ $source ]['scanned'] += (int) $report['scanned'];
	$state[ $source ]['added']   += (int) $report['added'];

	return $state;
}

/**
 * Create one Prospect: a WordPress user plus its jet_cct_member row.
 *
 * wp_insert_user() is called directly rather than through
 * niz_user_create_prospect(), because that helper cannot set user_registered -
 * it would stamp today, putting the whole cohort straight into the member
 * totals this import exists to stay out of.
 *
 * @return int|WP_Error New user ID.
 */
function mfa_member_import_create( $candidate, $source ) {
	global $wpdb;

	$phone = preg_replace( '/\D/', '', $candidate['phone'] );

	if ( strlen( $phone ) < 8 || strlen( $phone ) > 15 ) {
		return new WP_Error( 'invalid_phone', 'Phone outside the 8-15 digit range.' );
	}

	$username = 'mfa_' . $phone;

	// Belt and braces after the bulk dedupe: a member row could be missing
	// while the user exists, and wp_insert_user() would fail anyway.
	if ( username_exists( $username ) ) {
		return new WP_Error( 'user_exists', 'Username already taken.' );
	}

	$name = sanitize_text_field( $candidate['name'] );
	$name = '' !== $name ? mb_substr( $name, 0, 100 ) : 'Prospect ' . $phone;

	$cohort = mfa_member_import_cohort_date();

	$user_id = wp_insert_user(
		array(
			'user_login'      => $username,
			'user_pass'       => wp_generate_password( 20 ),
			'user_email'      => $phone . '@mfa.com',
			'display_name'    => $name,
			'first_name'      => $name,
			'role'            => 'subscriber',
			'user_registered' => $cohort,
		)
	);

	if ( is_wp_error( $user_id ) ) {
		return $user_id;
	}

	update_user_meta( $user_id, 'user_phone', $phone );
	update_user_meta( $user_id, 'user_status', 'prospect' );
	// Records which directory row this prospect came from, so a cohort can be
	// identified - or reversed - later without guessing from the date alone.
	update_user_meta( $user_id, 'lead_source', 'directory:' . $source . ':' . (int) $candidate['source_id'] );

	$now = current_time( 'mysql' );

	$inserted = $wpdb->insert(
		$wpdb->prefix . 'jet_cct_member',
		array(
			'cct_status'   => 'publish',
			'user_id'      => $user_id,
			'name'         => $name,
			'phone'        => $phone,
			'country'      => sanitize_text_field( $candidate['country'] ),
			'status'       => 'Prospect',
			'registered'   => $cohort,
			'cct_created'  => $now,
			'cct_modified' => $now,
		),
		array( '%s', '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s' )
	);

	if ( false === $inserted ) {
		// Leaving a user with no member row would make it invisible to every
		// member screen while still counting as a user, so undo it.
		require_once ABSPATH . 'wp-admin/includes/user.php';
		wp_delete_user( $user_id );

		return new WP_Error( 'cct_insert_failed', $wpdb->last_error );
	}

	update_user_meta( $user_id, 'item_id', (int) $wpdb->insert_id );

	return $user_id;
}
