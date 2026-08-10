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

add_shortcode( 'mfa_admin_member_list', 'mfa_admin_member_list_shortcode' );
function mfa_admin_member_list_shortcode() {
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
	$paged         = isset( $_GET['paged'] ) ? max( 1, absint( $_GET['paged'] ) ) : 1;
	$per_page      = 25;

	$status_options = mfa_admin_member_status_options();

	// Rank values in this table are inconsistent (trailing spaces, emoji,
	// values outside JetEngine's configured rank options) - build the
	// filter from what's actually in the data, not a hardcoded list.
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

	$data_sql    = "SELECT _ID, user_id, name, status, rank, cct_created FROM {$cct_table} WHERE {$where_sql} ORDER BY user_id DESC LIMIT %d OFFSET %d";
	$data_params = array_merge( $params, array( $per_page, $offset ) );
	$rows        = $wpdb->get_results( $wpdb->prepare( $data_sql, $data_params ), ARRAY_A );

	ob_start();
	?>
	<div class="mfa-admin-member-list">
		<h1 class="mfa-h2">Members</h1>
		<p class="mfa-body-muted"><?php echo esc_html( number_format_i18n( $total ) ); ?> member<?php echo 1 === $total ? '' : 's'; ?></p>

		<form method="get" class="mfa-admin-member-filters">
			<input type="text" name="member_search" value="<?php echo esc_attr( $search ); ?>" placeholder="Search name, phone, or email" class="mfa-admin-member-search">

			<select name="status" class="mfa-admin-member-select">
				<option value="">All statuses</option>
				<?php foreach ( $status_options as $status_option ) : ?>
					<option value="<?php echo esc_attr( $status_option ); ?>" <?php selected( $status_filter, $status_option ); ?>><?php echo esc_html( $status_option ); ?></option>
				<?php endforeach; ?>
			</select>

			<select name="rank" class="mfa-admin-member-select">
				<option value="">All ranks</option>
				<?php foreach ( $rank_options as $rank_option ) : ?>
					<option value="<?php echo esc_attr( $rank_option ); ?>" <?php selected( $rank_filter, $rank_option ); ?>><?php echo esc_html( trim( $rank_option ) ); ?></option>
				<?php endforeach; ?>
			</select>

			<button type="submit" class="mfa-btn mfa-btn-primary mfa-admin-member-filter-btn">Filter</button>
			<?php if ( '' !== $search || '' !== $status_filter || '' !== $rank_filter ) : ?>
				<a href="<?php echo esc_url( remove_query_arg( array( 'member_search', 'status', 'rank', 'paged' ) ) ); ?>" class="mfa-admin-member-clear">Clear</a>
			<?php endif; ?>
		</form>

		<div class="mfa-admin-member-table-wrap">
			<table class="mfa-admin-member-table">
				<thead>
					<tr>
						<th>Name</th>
						<th>Status</th>
						<th>Rank</th>
						<th>Registered</th>
						<th></th>
					</tr>
				</thead>
				<tbody>
					<?php if ( empty( $rows ) ) : ?>
						<tr><td colspan="5" class="mfa-admin-member-empty">No members found.</td></tr>
					<?php else : ?>
						<?php foreach ( $rows as $row ) : ?>
							<tr>
								<td data-label="Name"><?php echo esc_html( $row['name'] ? $row['name'] : '—' ); ?></td>
								<td data-label="Status">
									<?php if ( ! empty( $row['status'] ) ) : ?>
										<span class="mfa-admin-status-badge mfa-admin-status-<?php echo esc_attr( sanitize_html_class( strtolower( str_replace( ' ', '-', $row['status'] ) ) ) ); ?>"><?php echo esc_html( $row['status'] ); ?></span>
									<?php else : ?>
										<span class="mfa-admin-status-badge mfa-admin-status-none">—</span>
									<?php endif; ?>
								</td>
								<td data-label="Rank"><?php echo esc_html( trim( (string) $row['rank'] ) ? trim( (string) $row['rank'] ) : '—' ); ?></td>
								<td data-label="Registered"><?php echo esc_html( $row['cct_created'] ? date_i18n( 'j M Y', strtotime( $row['cct_created'] ) ) : '—' ); ?></td>
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
