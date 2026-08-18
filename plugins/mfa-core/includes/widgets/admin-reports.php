<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * [mfa_admin_reports] - the /admin/reports/ page (post 229449). One shared
 * Start/End Date filter at the top (single `start`/`end` GET params), then
 * four tabs below it (Member/Mosque/Business/Website, reusing the
 * [mfa_place_hub]-derived .mfa-admin-tabs pattern - see
 * admin-tabs-v1.js/admin-reports-v2.css) each showing that same date
 * range's growth report (stat cards + monthly bar chart + cumulative line
 * + country breakdown). No date range given -> defaults to the current
 * year, same window the original single-report version (2026-08-12)
 * always showed.
 *
 * 2026-08-17: rebuilt from a Member-only report into this 4-tab version;
 * originally each tab had its own independent date filter, changed same
 * day to one shared filter above the tabs per explicit follow-up request
 * ("put the content in its own tab after the date selection") - pick a
 * range once, flip between tabs to see each type's report for it. Member
 * additionally gets a CSV "Export List" button (Name/Country/Phone, same
 * shared date range, optional phone-masking) - see
 * mfa_admin_export_members_csv() below. CSV, not real .xlsx: opens
 * directly in Excel, no new library dependency (project's standing
 * no-new-third-party-plugin rule).
 *
 * Chart math is the same self-contained CSS/SVG approach as the original
 * (no chart library) - only the fixed "12 hardcoded Jan..Dec columns"
 * assumption changed, since an arbitrary date range can span any number
 * of months. mfa_admin_report_buckets() generates however many monthly
 * buckets the selected range actually covers.
 */
add_shortcode( 'mfa_admin_reports', 'mfa_admin_reports_shortcode' );
function mfa_admin_reports_shortcode() {
	if ( function_exists( 'mfa_admin_require_section_access' ) ) {
		$no_access = mfa_admin_require_section_access( 'reports' );
		if ( $no_access ) {
			return $no_access;
		}
	} elseif ( ! current_user_can( 'manage_options' ) ) {
		return '';
	}

	$active_tab = isset( $_GET['active_tab'] ) ? sanitize_key( wp_unslash( $_GET['active_tab'] ) ) : 'member';
	$known_tabs = array( 'member', 'mosque', 'business', 'website' );
	if ( ! in_array( $active_tab, $known_tabs, true ) ) {
		$active_tab = 'member';
	}

	$start_raw = isset( $_GET['start'] ) ? sanitize_text_field( wp_unslash( $_GET['start'] ) ) : '';
	$end_raw   = isset( $_GET['end'] ) ? sanitize_text_field( wp_unslash( $_GET['end'] ) ) : '';
	$range     = mfa_admin_report_normalize_range( $start_raw, $end_raw );

	global $wpdb;

	$member_data   = mfa_admin_report_compute_member( $wpdb, $range );
	$mosque_data   = mfa_admin_report_compute_cct( $wpdb, $wpdb->prefix . 'jet_cct_mosque', $range );
	$business_data = mfa_admin_report_compute_cct( $wpdb, $wpdb->prefix . 'jet_cct_business', $range );
	$website_data  = mfa_admin_report_compute_cct( $wpdb, $wpdb->prefix . 'jet_cct_web', $range );

	ob_start();
	?>
	<div class="mfa-report-page">
		<form method="get" class="mfa-report-filters">
			<input type="hidden" name="active_tab" value="<?php echo esc_attr( $active_tab ); ?>">
			<div class="mfa-form-group">
				<label for="mfa-report-start">Start Date</label>
				<input type="date" id="mfa-report-start" name="start" value="<?php echo esc_attr( $range['start'] ); ?>">
			</div>
			<div class="mfa-form-group">
				<label for="mfa-report-end">End Date</label>
				<input type="date" id="mfa-report-end" name="end" value="<?php echo esc_attr( $range['end'] ); ?>">
			</div>
			<button type="submit" class="mfa-btn mfa-btn-primary">Filter</button>
		</form>

		<div class="mfa-admin-tabs">
			<div class="mfa-admin-tablist" role="tablist">
				<button type="button" class="mfa-admin-tab<?php echo 'member' === $active_tab ? ' is-active' : ''; ?>" data-tab="member" role="tab" aria-selected="<?php echo 'member' === $active_tab ? 'true' : 'false'; ?>">Member</button>
				<button type="button" class="mfa-admin-tab<?php echo 'mosque' === $active_tab ? ' is-active' : ''; ?>" data-tab="mosque" role="tab" aria-selected="<?php echo 'mosque' === $active_tab ? 'true' : 'false'; ?>">Mosque</button>
				<button type="button" class="mfa-admin-tab<?php echo 'business' === $active_tab ? ' is-active' : ''; ?>" data-tab="business" role="tab" aria-selected="<?php echo 'business' === $active_tab ? 'true' : 'false'; ?>">Business</button>
				<button type="button" class="mfa-admin-tab<?php echo 'website' === $active_tab ? ' is-active' : ''; ?>" data-tab="website" role="tab" aria-selected="<?php echo 'website' === $active_tab ? 'true' : 'false'; ?>">Website</button>
			</div>

			<div class="mfa-admin-tabpanel<?php echo 'member' === $active_tab ? ' is-active' : ''; ?>" data-tabpanel="member">
				<?php echo mfa_admin_report_render( 'member', 'Member Growth', $member_data ); ?>
			</div>
			<div class="mfa-admin-tabpanel<?php echo 'mosque' === $active_tab ? ' is-active' : ''; ?>" data-tabpanel="mosque">
				<?php echo mfa_admin_report_render( 'mosque', 'Mosque Growth', $mosque_data ); ?>
			</div>
			<div class="mfa-admin-tabpanel<?php echo 'business' === $active_tab ? ' is-active' : ''; ?>" data-tabpanel="business">
				<?php echo mfa_admin_report_render( 'business', 'Business Growth', $business_data ); ?>
			</div>
			<div class="mfa-admin-tabpanel<?php echo 'website' === $active_tab ? ' is-active' : ''; ?>" data-tabpanel="website">
				<?php echo mfa_admin_report_render( 'website', 'Website Growth', $website_data ); ?>
			</div>
		</div>
	</div>
	<?php
	return ob_get_clean();
}

/**
 * Normalises whatever the shared Start/End Date fields submitted (or
 * nothing) to a valid ['start'=>'Y-m-d','end'=>'Y-m-d'] pair, swapped if
 * reversed. Empty/unparseable input falls back to the current calendar
 * year, same default window every tab showed before date filtering
 * existed.
 */
function mfa_admin_report_normalize_range( $start_raw, $end_raw ) {
	$start_ts = $start_raw ? strtotime( $start_raw ) : false;
	$end_ts   = $end_raw ? strtotime( $end_raw ) : false;

	if ( ! $start_ts || ! $end_ts ) {
		$year = (int) gmdate( 'Y' );
		return array( 'start' => $year . '-01-01', 'end' => $year . '-12-31' );
	}

	if ( $end_ts < $start_ts ) {
		$tmp = $start_ts;
		$start_ts = $end_ts;
		$end_ts = $tmp;
	}

	return array( 'start' => gmdate( 'Y-m-d', $start_ts ), 'end' => gmdate( 'Y-m-d', $end_ts ) );
}

/**
 * Monthly buckets ['Y-m' => 'Mon Y'] spanning $start to $end inclusive,
 * replacing the original hardcoded 12-column Jan..Dec assumption - an
 * arbitrary user-picked range can cover any number of months. Capped at
 * 240 buckets (20 years) as a sanity guard against a mistyped huge range
 * generating an unusably wide chart.
 */
function mfa_admin_report_buckets( $start, $end ) {
	$cursor = strtotime( gmdate( 'Y-m-01', strtotime( $start ) ) );
	$last   = strtotime( gmdate( 'Y-m-01', strtotime( $end ) ) );

	$buckets = array();
	$guard   = 0;
	while ( $cursor <= $last && $guard < 240 ) {
		$buckets[ gmdate( 'Y-m', $cursor ) ] = gmdate( 'M Y', $cursor );
		$cursor = strtotime( '+1 month', $cursor );
		$guard++;
	}

	return $buckets;
}

/**
 * Member tab's data - the one tab needing a JOIN (registration date lives
 * on wp_users, country/phone/name live on wp_jet_cct_member, linked by
 * user_id) rather than a single CCT table's own columns. Takes the shared
 * $range computed once in the main shortcode, not its own GET read.
 */
function mfa_admin_report_compute_member( $wpdb, $range ) {
	$buckets = mfa_admin_report_buckets( $range['start'], $range['end'] );

	$rows = $wpdb->get_results(
		$wpdb->prepare(
			"SELECT DATE_FORMAT(user_registered, '%%Y-%%m') AS ym, COUNT(*) AS n FROM {$wpdb->users} WHERE DATE(user_registered) BETWEEN %s AND %s GROUP BY ym",
			$range['start'],
			$range['end']
		),
		OBJECT_K
	);

	$counts = array();
	foreach ( $buckets as $ym => $label ) {
		$counts[ $ym ] = isset( $rows[ $ym ] ) ? (int) $rows[ $ym ]->n : 0;
	}

	$country_rows = $wpdb->get_results(
		$wpdb->prepare(
			"SELECT COALESCE(NULLIF(TRIM(m.country), ''), 'Unknown') AS c, COUNT(*) AS n
			 FROM {$wpdb->users} u
			 INNER JOIN {$wpdb->prefix}jet_cct_member m ON m.user_id = u.ID
			 WHERE DATE(u.user_registered) BETWEEN %s AND %s
			 GROUP BY c ORDER BY n DESC",
			$range['start'],
			$range['end']
		),
		ARRAY_A
	);

	return array(
		'start'        => $range['start'],
		'end'          => $range['end'],
		'buckets'      => $buckets,
		'counts'       => $counts,
		'country_rows' => $country_rows,
		// Bounded by "now" for the same reason the range query above is, and the
		// homepage counter too: bulk-imported prospects carry a deliberately
		// future user_registered date so they stay out of the running total
		// until it arrives. An unbounded COUNT(*) contradicted both - it read
		// 74,852 while the homepage said 39,488, the gap being 35,364
		// future-dated prospects from the directory and Indonesian imports.
		'total_all'    => (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$wpdb->users} WHERE user_registered <= %s",
				gmdate( 'Y-m-d H:i:s' )
			)
		),
	);
}

/**
 * Mosque/Business/Website tabs' data - all three share the same shape
 * (cct_created for the date, country on the same row), just a different
 * table name, so one function covers all three. Takes the shared $range
 * computed once in the main shortcode, not its own GET read.
 */
function mfa_admin_report_compute_cct( $wpdb, $table, $range ) {
	$buckets = mfa_admin_report_buckets( $range['start'], $range['end'] );

	$rows = $wpdb->get_results(
		$wpdb->prepare(
			"SELECT DATE_FORMAT(cct_created, '%%Y-%%m') AS ym, COUNT(*) AS n FROM {$table} WHERE DATE(cct_created) BETWEEN %s AND %s GROUP BY ym",
			$range['start'],
			$range['end']
		),
		OBJECT_K
	);

	$counts = array();
	foreach ( $buckets as $ym => $label ) {
		$counts[ $ym ] = isset( $rows[ $ym ] ) ? (int) $rows[ $ym ]->n : 0;
	}

	$country_rows = $wpdb->get_results(
		$wpdb->prepare(
			"SELECT COALESCE(NULLIF(TRIM(country), ''), 'Unknown') AS c, COUNT(*) AS n FROM {$table} WHERE DATE(cct_created) BETWEEN %s AND %s GROUP BY c ORDER BY n DESC",
			$range['start'],
			$range['end']
		),
		ARRAY_A
	);

	return array(
		'start'        => $range['start'],
		'end'          => $range['end'],
		'buckets'      => $buckets,
		'counts'       => $counts,
		'country_rows' => $country_rows,
		'total_all'    => (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table}" ),
	);
}

/**
 * Shared renderer - takes one tab's already-computed data (from either
 * compute function above, same shape) and builds the stat cards + monthly
 * bar chart + cumulative line + country breakdown. No filter form here
 * anymore (2026-08-17: moved to one shared form above the tabs in the
 * main shortcode). Member additionally gets the CSV export block.
 */
function mfa_admin_report_render( $id, $title, $data ) {
	$buckets      = $data['buckets'];
	$counts       = $data['counts'];
	$ym_keys      = array_keys( $buckets );
	$bucket_count = count( $ym_keys );

	$max          = max( 1, empty( $counts ) ? 0 : max( $counts ) );
	$range_total  = array_sum( $counts );
	$peak_ym      = $ym_keys ? array_search( max( $counts ), $counts, true ) : '';

	$cumulative = array();
	$running    = 0;
	foreach ( $ym_keys as $ym ) {
		$running += $counts[ $ym ];
		$cumulative[ $ym ] = $running;
	}

	$line_points = array();
	foreach ( $ym_keys as $i => $ym ) {
		$x = $bucket_count > 0 ? round( ( $i + 0.5 ) / $bucket_count * 100, 2 ) : 0;
		$y = round( ( 1 - ( $cumulative[ $ym ] / max( 1, $range_total ) ) ) * 100, 2 );
		$line_points[] = $x . ',' . $y;
	}
	$line_points_str = implode( ' ', $line_points );
	$first_x     = $bucket_count > 0 ? round( 0.5 / $bucket_count * 100, 2 ) : 0;
	$last_x      = $bucket_count > 0 ? round( ( $bucket_count - 0.5 ) / $bucket_count * 100, 2 ) : 100;
	$area_points = $first_x . ',100 ' . $line_points_str . ' ' . $last_x . ',100';

	$top_countries = array_slice( $data['country_rows'], 0, 15 );
	$other_total   = 0;
	foreach ( array_slice( $data['country_rows'], 15 ) as $r ) {
		$other_total += (int) $r['n'];
	}
	$country_max = ! empty( $top_countries ) ? max( 1, (int) $top_countries[0]['n'] ) : 1;

	ob_start();
	?>
	<div class="mfa-report">
		<div class="mfa-report-head">
			<h1 class="mfa-h1"><?php echo esc_html( $title ); ?></h1>
			<p class="mfa-body-muted"><?php echo esc_html( date_i18n( 'j M Y', strtotime( $data['start'] ) ) ); ?> &ndash; <?php echo esc_html( date_i18n( 'j M Y', strtotime( $data['end'] ) ) ); ?></p>
		</div>

		<div class="mfa-report-stats">
			<div class="mfa-report-stat">
				<span class="mfa-report-stat-num"><?php echo esc_html( number_format_i18n( $data['total_all'] ) ); ?></span>
				<span class="mfa-report-stat-lbl">Total <?php echo esc_html( $title ); ?> (all time)</span>
			</div>
			<div class="mfa-report-stat">
				<span class="mfa-report-stat-num"><?php echo esc_html( number_format_i18n( $range_total ) ); ?></span>
				<span class="mfa-report-stat-lbl">In selected range</span>
			</div>
			<div class="mfa-report-stat">
				<span class="mfa-report-stat-num"><?php echo esc_html( '' !== $peak_ym && isset( $buckets[ $peak_ym ] ) ? $buckets[ $peak_ym ] : '&mdash;' ); ?></span>
				<span class="mfa-report-stat-lbl">Peak month<?php echo '' !== $peak_ym ? ' (' . esc_html( number_format_i18n( $counts[ $peak_ym ] ) ) . ')' : ''; ?></span>
			</div>
		</div>

		<?php if ( 'member' === $id ) : ?>
			<div class="mfa-report-chart-card mfa-report-export-card">
				<h2 class="mfa-report-chart-title">Export List</h2>
				<form method="get" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="mfa-report-export-form">
					<input type="hidden" name="action" value="mfa_admin_export_members">
					<input type="hidden" name="start" value="<?php echo esc_attr( $data['start'] ); ?>">
					<input type="hidden" name="end" value="<?php echo esc_attr( $data['end'] ); ?>">
					<input type="hidden" name="nonce" value="<?php echo esc_attr( wp_create_nonce( 'mfa_admin_export_members' ) ); ?>">
					<div class="mfa-form-group">
						<label for="mfa-report-hide-phone">Hide Phone</label>
						<select id="mfa-report-hide-phone" name="hide_phone">
							<option value="0">No</option>
							<option value="1">Yes</option>
						</select>
					</div>
					<button type="submit" class="mfa-btn mfa-btn-solid-dark">Export List to Excel</button>
				</form>
				<p class="mfa-body-muted mfa-report-export-hint">Exports Name, Country, and Phone for members registered between the dates above (<?php echo esc_html( date_i18n( 'j M Y', strtotime( $data['start'] ) ) ); ?> &ndash; <?php echo esc_html( date_i18n( 'j M Y', strtotime( $data['end'] ) ) ); ?>).</p>
			</div>
		<?php endif; ?>

		<div class="mfa-report-chart-card">
			<h2 class="mfa-report-chart-title">Monthly <?php echo esc_html( strtolower( $title ) ); ?></h2>
			<?php if ( empty( $ym_keys ) ) : ?>
				<p class="mfa-body-muted">No data for this range.</p>
			<?php else : ?>
				<div class="mfa-report-chart mfa-report-chart--scroll" role="img" aria-label="<?php echo esc_attr( $title . ' by month' ); ?>">
					<div class="mfa-report-vals">
						<?php foreach ( $ym_keys as $ym ) : ?>
							<span class="mfa-report-bar-val"><?php echo esc_html( number_format_i18n( $counts[ $ym ] ) ); ?></span>
						<?php endforeach; ?>
					</div>
					<div class="mfa-report-plot">
						<?php foreach ( $ym_keys as $ym ) :
							$height = round( $counts[ $ym ] / $max * 100, 1 );
							?>
							<div class="mfa-report-bar-col">
								<div class="mfa-report-bar" style="height: <?php echo esc_attr( $height ); ?>%;"></div>
							</div>
						<?php endforeach; ?>
					</div>
					<div class="mfa-report-xaxis">
						<?php foreach ( $ym_keys as $ym ) : ?>
							<span class="mfa-report-bar-lbl"><?php echo esc_html( $buckets[ $ym ] ); ?></span>
						<?php endforeach; ?>
					</div>
				</div>
			<?php endif; ?>
		</div>

		<?php if ( ! empty( $ym_keys ) ) : ?>
			<div class="mfa-report-chart-card">
				<h2 class="mfa-report-chart-title">Cumulative total</h2>
				<div class="mfa-report-chart mfa-report-chart--scroll" role="img" aria-label="Cumulative <?php echo esc_attr( strtolower( $title ) ); ?>">
					<div class="mfa-report-vals">
						<?php foreach ( $ym_keys as $ym ) : ?>
							<span class="mfa-report-bar-val"><?php echo esc_html( number_format_i18n( $cumulative[ $ym ] ) ); ?></span>
						<?php endforeach; ?>
					</div>
					<div class="mfa-report-plot mfa-report-plot--line">
						<svg class="mfa-report-line" viewBox="0 0 100 100" preserveAspectRatio="none" aria-hidden="true">
							<polygon points="<?php echo esc_attr( $area_points ); ?>" fill="rgba(240,147,43,0.14)" stroke="none"></polygon>
							<polyline points="<?php echo esc_attr( $line_points_str ); ?>" fill="none" stroke="#f0932b" stroke-width="2.5" stroke-linejoin="round" stroke-linecap="round" vector-effect="non-scaling-stroke"></polyline>
						</svg>
					</div>
					<div class="mfa-report-xaxis">
						<?php foreach ( $ym_keys as $ym ) : ?>
							<span class="mfa-report-bar-lbl"><?php echo esc_html( $buckets[ $ym ] ); ?></span>
						<?php endforeach; ?>
					</div>
				</div>
			</div>
		<?php endif; ?>

		<div class="mfa-report-chart-card">
			<h2 class="mfa-report-chart-title"><?php echo esc_html( $title ); ?> by country</h2>
			<?php if ( empty( $top_countries ) ) : ?>
				<p class="mfa-body-muted">No data for this range.</p>
			<?php else : ?>
				<div class="mfa-report-countries">
					<?php foreach ( $top_countries as $r ) :
						$w = round( (int) $r['n'] / $country_max * 100, 1 );
						?>
						<div class="mfa-report-country-row">
							<span class="mfa-report-country-name"><?php echo esc_html( $r['c'] ); ?></span>
							<span class="mfa-report-country-track"><span class="mfa-report-country-bar" style="width: <?php echo esc_attr( $w ); ?>%;"></span></span>
							<span class="mfa-report-country-val"><?php echo esc_html( number_format_i18n( (int) $r['n'] ) ); ?></span>
						</div>
					<?php endforeach; ?>
					<?php if ( $other_total > 0 ) : ?>
						<div class="mfa-report-country-row mfa-report-country-row--other">
							<span class="mfa-report-country-name">Other countries</span>
							<span class="mfa-report-country-track"><span class="mfa-report-country-bar" style="width: <?php echo esc_attr( round( $other_total / $country_max * 100, 1 ) ); ?>%;"></span></span>
							<span class="mfa-report-country-val"><?php echo esc_html( number_format_i18n( $other_total ) ); ?></span>
						</div>
					<?php endif; ?>
				</div>
			<?php endif; ?>
		</div>
	</div>
	<?php
	return ob_get_clean();
}

/**
 * Masks a phone number for the "Hide Phone: Yes" export option - keeps
 * the first 2 and last 5 characters, masks whatever's between with `*`.
 * Matches the requested example exactly: 60123456789 -> 60****56789.
 * Short numbers (<=7 chars, no room for a meaningful mask) are returned
 * unchanged rather than masking the whole thing unreadable.
 */
function mfa_admin_mask_phone( $phone ) {
	$phone = trim( (string) $phone );
	$len   = strlen( $phone );
	if ( $len <= 7 ) {
		return $phone;
	}

	$prefix = substr( $phone, 0, 2 );
	$suffix = substr( $phone, -5 );
	$masked = str_repeat( '*', $len - 7 );

	return $prefix . $masked . $suffix;
}

/**
 * CSV export for the Member tab - admin-post.php target (not wp_ajax,
 * since this streams a file download rather than a JSON response).
 * Re-checks the same access gate the report page itself uses; the nonce
 * only guards against CSRF, not authorization, so both checks matter.
 */
add_action( 'admin_post_mfa_admin_export_members', 'mfa_admin_export_members_csv' );
function mfa_admin_export_members_csv() {
	$authorized = function_exists( 'mfa_user_can_access_admin_section' ) ? mfa_user_can_access_admin_section( 'reports' ) : current_user_can( 'manage_options' );
	if ( ! $authorized ) {
		wp_die( esc_html__( 'You are not authorized to do this.' ), '', array( 'response' => 403 ) );
	}

	check_admin_referer( 'mfa_admin_export_members', 'nonce' );

	$start_raw  = isset( $_GET['start'] ) ? sanitize_text_field( wp_unslash( $_GET['start'] ) ) : '';
	$end_raw    = isset( $_GET['end'] ) ? sanitize_text_field( wp_unslash( $_GET['end'] ) ) : '';
	$range      = mfa_admin_report_normalize_range( $start_raw, $end_raw );
	$hide_phone = ! empty( $_GET['hide_phone'] ) && '1' === $_GET['hide_phone'];

	global $wpdb;
	$rows = $wpdb->get_results(
		$wpdb->prepare(
			"SELECT m.name, m.country, m.phone
			 FROM {$wpdb->users} u
			 INNER JOIN {$wpdb->prefix}jet_cct_member m ON m.user_id = u.ID
			 WHERE DATE(u.user_registered) BETWEEN %s AND %s
			 ORDER BY u.user_registered ASC",
			$range['start'],
			$range['end']
		),
		ARRAY_A
	);

	nocache_headers();
	header( 'Content-Type: text/csv; charset=utf-8' );
	header( 'Content-Disposition: attachment; filename="members-' . $range['start'] . '-to-' . $range['end'] . '.csv"' );

	$out = fopen( 'php://output', 'w' );
	fputcsv( $out, array( 'Name', 'Country', 'Phone' ) );
	foreach ( $rows as $row ) {
		$phone = $hide_phone ? mfa_admin_mask_phone( $row['phone'] ) : $row['phone'];
		fputcsv( $out, array( $row['name'], $row['country'], $phone ) );
	}
	fclose( $out );
	exit;
}
