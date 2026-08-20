<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * [mfa_admin_member_list] - the /admin/member/ table (see
 * includes/widgets/admin-shell.php for the surrounding header/sidebar/
 * footer chrome). Reads wp_jet_cct_member directly via $wpdb, never the
 * JetEngine PHP API, per the project's standing rule for this CCT.
 * GET-based filtering (no AJAX) - search/status/rank/paged in the query
 * string, so the page works with JS disabled and needs no nonce since
 * nothing here mutates data.
 */

function mfa_admin_member_status_options() {
	return array( 'Prospect', 'Member', 'Premium Member', 'Premium Lifetime' );
}

/**
 * SQL predicate for "a prospect who actually reached out".
 *
 * Someone who messaged Sofia is a real lead, not a name on an imported
 * list, and they are who the team follows up. Imported contacts are
 * excluded by the lead_source meta the importer wrote (34,582 of them);
 * a Sofia-created contact has none. Note the test is on the VALUE, not on
 * the meta being present - Sofia contacts carry lead_source too, so
 * testing presence would exclude every real lead and look correct because
 * the count would not move.
 *
 * Shared with the dashboard Overview, which must show the same number this
 * list shows - the two disagreeing about how many real prospects exist is
 * exactly the confusion this whole panel is meant to remove.
 */
function mfa_admin_member_reached_out_sql() {
	global $wpdb;

	return "( status = 'Prospect' AND user_id IN ("
		. " SELECT c.user_id FROM {$wpdb->prefix}nwa_conversations c"
		. " WHERE c.user_id > 0 AND c.user_id NOT IN ("
		. " SELECT um.user_id FROM {$wpdb->usermeta} um WHERE um.meta_key = 'lead_source' AND um.meta_value LIKE 'directory:%'"
		. " ) ) )";
}

add_shortcode( 'mfa_admin_member_list', 'mfa_admin_member_list_shortcode' );

/**
 * The /admin/ member table.
 *
 * @param array $atts 'statuses' - comma-separated cct status values to
 *                    restrict the table to, and 'title' for the heading.
 *                    Unrestricted (the default) lists every status and
 *                    shows the member-import panel: that is the
 *                    /admin/prospects/ view. /admin/member/ passes the
 *                    member statuses so it shows conversions only, and
 *                    the import panel is hidden there because importing
 *                    creates prospects, not members.
 */
function mfa_admin_member_list_shortcode( $atts = array() ) {
	if ( function_exists( 'mfa_admin_require_section_access' ) ) {
		$no_access = mfa_admin_require_section_access( 'member' );
		if ( $no_access ) {
			return $no_access;
		}
	}

	global $wpdb;
	$cct_table = $wpdb->prefix . 'jet_cct_member';

	// NOT 's' - that's WordPress's own reserved global search query var;
	// a GET form posting to the current URL with ?s=... makes WP treat
	// the whole request as a native search query (is_search() becomes
	// true) instead of passing it through as a plain custom param, which
	// sent visitors to the theme's search results template instead of
	// staying on this page. Confirmed via a live user report.
	$search        = isset( $_GET['member_search'] ) ? sanitize_text_field( wp_unslash( $_GET['member_search'] ) ) : '';
	$status_filter = isset( $_GET['status'] ) ? sanitize_text_field( wp_unslash( $_GET['status'] ) ) : '';
	$rank_filter   = isset( $_GET['rank'] ) ? sanitize_text_field( wp_unslash( $_GET['rank'] ) ) : '';
	$country_filter = isset( $_GET['mcountry'] ) ? sanitize_text_field( wp_unslash( $_GET['mcountry'] ) ) : '';
	$paged         = isset( $_GET['paged'] ) ? max( 1, absint( $_GET['paged'] ) ) : 1;
	$per_page      = 25;

	$status_options = mfa_admin_member_status_options();

	$atts = shortcode_atts(
		array( 'statuses' => '', 'title' => '' ),
		is_array( $atts ) ? $atts : array(),
		'mfa_admin_member_list'
	);

	// Restricting the table restricts the dropdown too - offering a filter
	// that could only ever return nothing is worse than not offering it.
	$restrict = array_filter( array_map( 'trim', explode( ',', (string) $atts['statuses'] ) ) );
	$restrict = array_values( array_intersect( $restrict, $status_options ) );
	$is_restricted = ! empty( $restrict );

	if ( $is_restricted ) {
		$status_options = $restrict;
	}

	// The import panel creates prospects, so it belongs wherever prospects are
	// listed - the unrestricted view or a Prospect-only one - and nowhere else.
	// Tying it to $is_restricted alone would have hidden it from the very page
	// it exists for.
	$shows_prospects = ! $is_restricted || in_array( 'Prospect', $restrict, true );

	// The Members view reaches past its status list to include prospects who
	// have messaged Sofia. The Prospects view already lists them.
	$includes_reached_out = $is_restricted && ! in_array( 'Prospect', $restrict, true );

	// What one row is called, so the count line reads honestly on each view.
	if ( $is_restricted && ! in_array( 'Prospect', $restrict, true ) ) {
		$noun = 'member';
	} elseif ( array( 'Prospect' ) === $restrict ) {
		$noun = 'prospect';
	} else {
		$noun = 'record';
	}

	// Rank values in this table are inconsistent (trailing spaces, emoji,
	// values outside JetEngine's configured rank options) - build the
	// filter from what's actually in the data, not a hardcoded list.
	// Countries present in this view's own slice of the table.
	$country_scope = $is_restricted
		? $wpdb->prepare( 'status IN (' . implode( ',', array_fill( 0, count( $restrict ), '%s' ) ) . ')', $restrict )
		: '1=1';
	$country_options = $wpdb->get_col(
		"SELECT DISTINCT country FROM {$cct_table} WHERE {$country_scope} AND country IS NOT NULL AND TRIM(country) != '' ORDER BY country ASC"
	);

	$rank_options = $wpdb->get_col( "SELECT DISTINCT rank FROM {$cct_table} WHERE rank IS NOT NULL AND TRIM(rank) != '' ORDER BY rank ASC" );

	$where  = array( '1=1' );
	$params = array();

	if ( '' !== $search ) {
		$where[]  = '(name LIKE %s OR phone LIKE %s OR email LIKE %s)';
		$like     = '%' . $wpdb->esc_like( $search ) . '%';
		$params[] = $like;
		$params[] = $like;
		$params[] = $like;
	}

	if ( '' !== $status_filter && in_array( $status_filter, $status_options, true ) ) {
		$where[]  = 'status = %s';
		$params[] = $status_filter;
	} elseif ( $is_restricted ) {
		// No explicit filter chosen, so bound the table to the allowed set.
		$status_in = 'status IN (' . implode( ',', array_fill( 0, count( $restrict ), '%s' ) ) . ')';

		if ( $includes_reached_out ) {
			$where[] = '( ' . $status_in . ' OR ' . mfa_admin_member_reached_out_sql() . ' )';
		} else {
			$where[] = $status_in;
		}

		foreach ( $restrict as $allowed_status ) {
			$params[] = $allowed_status;
		}
	}

	if ( '' !== $country_filter && in_array( $country_filter, $country_options, true ) ) {
		$where[]  = 'country = %s';
		$params[] = $country_filter;
	}

	if ( '' !== $rank_filter && in_array( $rank_filter, $rank_options, true ) ) {
		$where[]  = 'rank = %s';
		$params[] = $rank_filter;
	}

	$where_sql = implode( ' AND ', $where );

	$count_sql = "SELECT COUNT(*) FROM {$cct_table} WHERE {$where_sql}";
	$total     = $params ? (int) $wpdb->get_var( $wpdb->prepare( $count_sql, $params ) ) : (int) $wpdb->get_var( $count_sql );

	$total_pages = max( 1, (int) ceil( $total / $per_page ) );
	$paged       = min( $paged, $total_pages );
	$offset      = ( $paged - 1 ) * $per_page;

	$data_sql    = "SELECT _ID, user_id, name, email, phone, country, status, rank, registered, cct_created FROM {$cct_table} WHERE {$where_sql} ORDER BY user_id DESC LIMIT %d OFFSET %d";
	$data_params = array_merge( $params, array( $per_page, $offset ) );
	$rows        = $wpdb->get_results( $wpdb->prepare( $data_sql, $data_params ), ARRAY_A );

	// Runs before any output so a dry run or a progress reset is reflected in
	// the panel rendered below, not one page load late.
	$import_report = function_exists( 'mfa_admin_member_import_maybe_run' )
		? mfa_admin_member_import_maybe_run()
		: null;

	ob_start();
	?>
	<div class="mfa-admin-member-list">
		<h1 class="mfa-h2"><?php echo esc_html( '' !== $atts['title'] ? $atts['title'] : 'Members' ); ?></h1>
<?php if ( $shows_prospects && function_exists( 'mfa_admin_member_import_panel' ) ) : ?>
		<?php echo mfa_admin_member_import_panel( $import_report ); // phpcs:ignore WordPress.Security.EscapeOutput ?>
<?php endif; ?>
		<p class="mfa-body-muted"><?php echo esc_html( number_format_i18n( $total ) ); ?> <?php echo esc_html( $noun ); ?><?php echo 1 === $total ? '' : 's'; ?></p>

		<form method="get" class="mfa-admin-member-filters">
			<input type="text" name="member_search" value="<?php echo esc_attr( $search ); ?>" placeholder="Search name, phone, or email" class="mfa-admin-member-search">

			<select name="status" class="mfa-admin-member-select">
				<option value="">All statuses</option>
				<?php foreach ( $status_options as $status_option ) : ?>
					<option value="<?php echo esc_attr( $status_option ); ?>" <?php selected( $status_filter, $status_option ); ?>><?php echo esc_html( $status_option ); ?></option>
				<?php endforeach; ?>
			</select>

			<select name="mcountry" class="mfa-admin-member-select">
				<option value="">All countries</option>
				<?php foreach ( $country_options as $country_option ) : ?>
					<option value="<?php echo esc_attr( $country_option ); ?>" <?php selected( $country_filter, $country_option ); ?>><?php echo esc_html( $country_option ); ?></option>
				<?php endforeach; ?>
			</select>

			<?php if ( ! $shows_prospects ) : ?>
			<select name="rank" class="mfa-admin-member-select">
				<option value="">All ranks</option>
				<?php foreach ( $rank_options as $rank_option ) : ?>
					<option value="<?php echo esc_attr( $rank_option ); ?>" <?php selected( $rank_filter, $rank_option ); ?>><?php echo esc_html( trim( $rank_option ) ); ?></option>
				<?php endforeach; ?>
			</select>
			<?php endif; ?>

			<button type="submit" class="mfa-btn mfa-btn-primary mfa-admin-member-filter-btn">Filter</button>
			<?php if ( '' !== $search || '' !== $status_filter || '' !== $rank_filter || '' !== $country_filter ) : ?>
				<a href="<?php echo esc_url( remove_query_arg( array( 'member_search', 'status', 'rank', 'mcountry', 'paged' ) ) ); ?>" class="mfa-admin-member-clear">Clear</a>
			<?php endif; ?>
		</form>

		<div class="mfa-admin-member-table-wrap">
			<table class="mfa-admin-member-table">
				<thead>
					<tr>
						<th>Name</th>
						<?php if ( $shows_prospects ) : ?>
							<th>Email</th>
							<th>Phone</th>
						<?php endif; ?>
						<th>Country</th>
						<th>Status</th>
						<?php if ( ! $shows_prospects ) : ?>
							<th>Rank</th>
						<?php endif; ?>
						<th>Registered</th>
						<th></th>
					</tr>
				</thead>
				<tbody>
					<?php if ( empty( $rows ) ) : ?>
						<tr><td colspan="<?php echo $shows_prospects ? 7 : 6; ?>" class="mfa-admin-member-empty">Nothing found.</td></tr>
					<?php else : ?>
						<?php foreach ( $rows as $row ) : ?>
							<tr>
								<td data-label="Name"><?php echo esc_html( $row['name'] ? $row['name'] : '—' ); ?></td>
								<?php if ( $shows_prospects ) : ?>
									<td data-label="Email"><?php echo esc_html( ( ! empty( $row['email'] ) && ! ( function_exists( 'mfa_is_placeholder_email' ) && mfa_is_placeholder_email( $row['email'] ) ) ) ? $row['email'] : '—' ); ?></td>
									<td data-label="Phone"><?php echo esc_html( ! empty( $row['phone'] ) ? $row['phone'] : '—' ); ?></td>
								<?php endif; ?>
								<td data-label="Country"><?php echo esc_html( ! empty( $row['country'] ) ? $row['country'] : '—' ); ?></td>
								<td data-label="Status">
									<?php if ( ! empty( $row['status'] ) ) : ?>
										<span class="mfa-admin-status-badge mfa-admin-status-<?php echo esc_attr( sanitize_html_class( strtolower( str_replace( ' ', '-', $row['status'] ) ) ) ); ?>"><?php echo esc_html( $row['status'] ); ?></span>
									<?php else : ?>
										<span class="mfa-admin-status-badge mfa-admin-status-none">—</span>
									<?php endif; ?>
								</td>
								<?php if ( ! $shows_prospects ) : ?>
									<td data-label="Rank"><?php echo esc_html( trim( (string) $row['rank'] ) ? trim( (string) $row['rank'] ) : '—' ); ?></td>
								<?php endif; ?>
								<?php
								// The registration date is the 'registered' column, not
								// cct_created - cct_created is when the row was written,
								// which for any imported member is the day of the import
								// rather than the date they count from. The two differ for
								// 74,502 of the existing members, so this column has been
								// showing the wrong date all along; it only became obvious
								// once the directory import made the gap months wide.
								// Falls back to cct_created for the 91 rows with no
								// registered value.
								$registered_at = ! empty( $row['registered'] ) ? $row['registered'] : $row['cct_created'];
								?>
								<td data-label="Registered"><?php echo esc_html( $registered_at ? date_i18n( 'j M Y', strtotime( $registered_at ) ) : '—' ); ?></td>
								<td data-label="" class="mfa-admin-member-actions">
									<?php if ( ! empty( $row['user_id'] ) ) : ?>
										<a href="<?php echo esc_url( add_query_arg( 'id', (int) $row['user_id'], home_url( '/admin/member/info/' ) ) ); ?>" target="_blank" rel="noopener" class="mfa-btn mfa-btn-solid-dark mfa-admin-member-view-btn">View</a>
									<?php endif; ?>
								</td>
							</tr>
						<?php endforeach; ?>
					<?php endif; ?>
				</tbody>
			</table>
		</div>

		<?php if ( $total_pages > 1 ) : ?>
			<nav class="mfa-admin-member-pagination" aria-label="Members pagination">
				<?php
				$base_url = remove_query_arg( 'paged' );
				for ( $p = 1; $p <= $total_pages; $p++ ) :
					$page_url = 1 === $p ? $base_url : add_query_arg( 'paged', $p, $base_url );
					?>
					<a href="<?php echo esc_url( $page_url ); ?>" class="mfa-admin-member-page-link<?php echo $p === $paged ? ' is-active' : ''; ?>"><?php echo esc_html( $p ); ?></a>
				<?php endfor; ?>
			</nav>
		<?php endif; ?>
	</div>
	<?php
	return ob_get_clean();
}
