<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * [mfa_admin_business_list] - the /admin/business/ table, replacing the
 * [mfa_coming_soon] placeholder, same pattern as admin-mosque-list.php.
 * Reads wp_jet_cct_business directly via $wpdb, never the JetEngine PHP
 * API, per the project's standing rule.
 *
 * Unlike mosque, listing_status here has 4 real values (Pending/New/
 * Approved/Rejected, checked against actual data, not assumed) so the
 * status dropdown is built dynamically rather than hardcoded. category
 * exists as a field but is populated on <0.2% of rows (80 of 39,896) -
 * not exposed as a filter, same "check the real distribution before
 * adding a filter" reasoning as the mosque build.
 *
 * Prev/Next pagination (not link-per-page) - ~39,896 rows at 25/page is
 * ~1,600 pages. GET-based filtering, NOT 's' for search (WP's reserved
 * search query var - see admin-member-list.php's comment on this).
 */

add_shortcode( 'mfa_admin_business_list', 'mfa_admin_business_list_shortcode' );
function mfa_admin_business_list_shortcode() {
	if ( function_exists( 'mfa_admin_require_section_access' ) ) {
		$no_access = mfa_admin_require_section_access( 'business' );
		if ( $no_access ) {
			return $no_access;
		}
	}

	global $wpdb;
	$cct_table = $wpdb->prefix . 'jet_cct_business';

	$search         = isset( $_GET['business_search'] ) ? sanitize_text_field( wp_unslash( $_GET['business_search'] ) ) : '';
	$status_filter  = isset( $_GET['status'] ) ? sanitize_text_field( wp_unslash( $_GET['status'] ) ) : '';
	$country_filter = isset( $_GET['country'] ) ? sanitize_text_field( wp_unslash( $_GET['country'] ) ) : '';
	$paged          = isset( $_GET['paged'] ) ? max( 1, absint( $_GET['paged'] ) ) : 1;
	$per_page       = 25;

	// Fixed managed status set so every status is selectable even when none
	// exist in the data yet (New/Pending/Approved/Verified/Premium/Rejected/
	// Error/Deleted).
	$status_options  = array( 'New', 'Pending', 'Approved', 'Verified', 'Premium', 'Rejected', 'Error', 'Deleted' );
	$country_options = $wpdb->get_col( "SELECT DISTINCT country FROM {$cct_table} WHERE country IS NOT NULL AND TRIM(country) != '' ORDER BY country ASC" );

	$where  = array( '1=1' );
	$params = array();

	if ( '' !== $search ) {
		$where[]  = '(name LIKE %s OR address LIKE %s OR city LIKE %s)';
		$like     = '%' . $wpdb->esc_like( $search ) . '%';
		$params[] = $like;
		$params[] = $like;
		$params[] = $like;
	}

	if ( '' !== $status_filter && in_array( $status_filter, $status_options, true ) ) {
		$where[]  = 'listing_status = %s';
		$params[] = $status_filter;
	}

	if ( '' !== $country_filter && in_array( $country_filter, $country_options, true ) ) {
		$where[]  = 'country = %s';
		$params[] = $country_filter;
	}

	$where_sql = implode( ' AND ', $where );

	$count_sql = "SELECT COUNT(*) FROM {$cct_table} WHERE {$where_sql}";
	$total     = $params ? (int) $wpdb->get_var( $wpdb->prepare( $count_sql, $params ) ) : (int) $wpdb->get_var( $count_sql );

	$total_pages = max( 1, (int) ceil( $total / $per_page ) );
	$paged       = min( $paged, $total_pages );
	$offset      = ( $paged - 1 ) * $per_page;

	$data_sql    = "SELECT _ID, name, city, country, rating, listing_status, page_url FROM {$cct_table} WHERE {$where_sql} ORDER BY _ID DESC LIMIT %d OFFSET %d";
	$data_params = array_merge( $params, array( $per_page, $offset ) );
	$rows        = $wpdb->get_results( $wpdb->prepare( $data_sql, $data_params ), ARRAY_A );

	ob_start();
	?>
	<div class="mfa-admin-business-list">
		<div class="mfa-admin-business-list-heading">
			<div>
				<h1 class="mfa-h2">Businesses</h1>
				<p class="mfa-body-muted"><?php echo esc_html( number_format_i18n( $total ) ); ?> business<?php echo 1 === $total ? '' : 'es'; ?></p>
			</div>
			<a href="<?php echo esc_url( home_url( '/add-business/' ) ); ?>" target="_blank" rel="noopener" class="mfa-btn mfa-btn-primary">Add New</a>
		</div>

		<form method="get" class="mfa-admin-business-filters">
			<input type="text" name="business_search" value="<?php echo esc_attr( $search ); ?>" placeholder="Search name, address, or city" class="mfa-admin-business-search">

			<select name="status" class="mfa-admin-business-select">
				<option value="">All statuses</option>
				<?php foreach ( $status_options as $status_option ) : ?>
					<option value="<?php echo esc_attr( $status_option ); ?>" <?php selected( $status_filter, $status_option ); ?>><?php echo esc_html( $status_option ); ?></option>
				<?php endforeach; ?>
			</select>

			<select name="country" class="mfa-admin-business-select">
				<option value="">All countries</option>
				<?php foreach ( $country_options as $country_option ) : ?>
					<option value="<?php echo esc_attr( $country_option ); ?>" <?php selected( $country_filter, $country_option ); ?>><?php echo esc_html( $country_option ); ?></option>
				<?php endforeach; ?>
			</select>

			<button type="submit" class="mfa-btn mfa-btn-primary mfa-admin-business-filter-btn">Filter</button>
			<?php if ( '' !== $search || '' !== $status_filter || '' !== $country_filter ) : ?>
				<a href="<?php echo esc_url( remove_query_arg( array( 'business_search', 'status', 'country', 'paged' ) ) ); ?>" class="mfa-admin-business-clear">Clear</a>
			<?php endif; ?>
		</form>

		<div class="mfa-admin-business-table-wrap">
			<table class="mfa-admin-business-table">
				<thead>
					<tr>
						<th>Name</th>
						<th>City</th>
						<th>Country</th>
						<th>Rating</th>
						<th>Status</th>
						<th></th>
					</tr>
				</thead>
				<tbody>
					<?php if ( empty( $rows ) ) : ?>
						<tr><td colspan="6" class="mfa-admin-business-empty">No businesses found.</td></tr>
					<?php else : ?>
						<?php foreach ( $rows as $row ) : ?>
							<tr>
								<td data-label="Name"><?php echo esc_html( $row['name'] ? $row['name'] : '—' ); ?></td>
								<td data-label="City"><?php echo esc_html( $row['city'] ? $row['city'] : '—' ); ?></td>
								<td data-label="Country"><?php echo esc_html( $row['country'] ? $row['country'] : '—' ); ?></td>
								<td data-label="Rating"><?php echo esc_html( $row['rating'] ? $row['rating'] : '—' ); ?></td>
								<td data-label="Status">
									<?php if ( ! empty( $row['listing_status'] ) ) : ?>
										<span class="mfa-admin-status-badge mfa-admin-status-<?php echo esc_attr( sanitize_html_class( strtolower( $row['listing_status'] ) ) ); ?>"><?php echo esc_html( $row['listing_status'] ); ?></span>
									<?php else : ?>
										<span class="mfa-admin-status-badge mfa-admin-status-none">—</span>
									<?php endif; ?>
								</td>
								<td data-label="" class="mfa-admin-business-actions">
									<?php if ( ! empty( $row['page_url'] ) ) : ?>
										<a href="<?php echo esc_url( $row['page_url'] ); ?>" target="_blank" rel="noopener" class="mfa-btn mfa-btn-solid-dark mfa-admin-business-view-btn">View</a>
									<?php endif; ?>
								</td>
							</tr>
						<?php endforeach; ?>
					<?php endif; ?>
				</tbody>
			</table>
		</div>

		<?php if ( $total_pages > 1 ) : ?>
			<nav class="mfa-admin-business-pagination" aria-label="Businesses pagination">
				<?php if ( $paged > 1 ) : ?>
					<a href="<?php echo esc_url( add_query_arg( 'paged', $paged - 1 ) ); ?>" class="mfa-admin-business-page-link">&larr; Prev</a>
				<?php endif; ?>
				<span class="mfa-admin-business-page-status">Page <?php echo esc_html( number_format_i18n( $paged ) ); ?> of <?php echo esc_html( number_format_i18n( $total_pages ) ); ?></span>
				<?php if ( $paged < $total_pages ) : ?>
					<a href="<?php echo esc_url( add_query_arg( 'paged', $paged + 1 ) ); ?>" class="mfa-admin-business-page-link">Next &rarr;</a>
				<?php endif; ?>
			</nav>
		<?php endif; ?>
	</div>
	<?php
	return ob_get_clean();
}
