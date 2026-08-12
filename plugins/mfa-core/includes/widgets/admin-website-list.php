<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * [mfa_admin_website_list] - the /admin/website/ table, replacing the
 * [mfa_coming_soon] placeholder, same pattern as admin-mosque-list.php /
 * admin-business-list.php. Reads wp_jet_cct_web directly via $wpdb, never
 * the JetEngine PHP API, per the project's standing rule.
 *
 * This table has two status-ish fields (`status` and `listing_status`).
 * `listing_status` is now the authoritative field - the web directory,
 * single template, and add-website flow were all migrated onto it (Approved/
 * Verified/Premium gate content + directory visibility; New/Pending are
 * updatable), so the admin filter/badge use listing_status too, consistent
 * with mosque/business. The older `status` column is left in place untouched.
 *
 * `category` is well distributed here (unlike business's <0.2% fill rate)
 * so it's exposed as a filter. There's no cached page_url column like
 * mosque/business have - `url` on this table is the external site being
 * catalogued (e.g. https://noor-class.com), not the masjid4all page - so
 * "View" links via get_permalink(cct_single_post_id) instead (all 593 rows
 * have cct_single_post_id populated, confirmed).
 *
 * Prev/Next pagination, consistent with mosque/business, even though 593
 * rows is far smaller - keeps the pattern uniform across CCT admin tables.
 * GET-based filtering, NOT 's' for search (WP's reserved search query var).
 */

add_shortcode( 'mfa_admin_website_list', 'mfa_admin_website_list_shortcode' );
function mfa_admin_website_list_shortcode() {
	global $wpdb;
	$cct_table = $wpdb->prefix . 'jet_cct_web';

	$search         = isset( $_GET['website_search'] ) ? sanitize_text_field( wp_unslash( $_GET['website_search'] ) ) : '';
	$status_filter  = isset( $_GET['status'] ) ? sanitize_text_field( wp_unslash( $_GET['status'] ) ) : '';
	$category_filter = isset( $_GET['category'] ) ? sanitize_text_field( wp_unslash( $_GET['category'] ) ) : '';
	$country_filter = isset( $_GET['country'] ) ? sanitize_text_field( wp_unslash( $_GET['country'] ) ) : '';
	$paged          = isset( $_GET['paged'] ) ? max( 1, absint( $_GET['paged'] ) ) : 1;
	$per_page       = 25;

	// Fixed managed status set, filtered on listing_status (now the authoritative
	// field for the web directory/single, same as business/mosque).
	$status_options   = array( 'New', 'Pending', 'Approved', 'Verified', 'Premium', 'Rejected', 'Error', 'Deleted' );
	$category_options = $wpdb->get_col( "SELECT DISTINCT category FROM {$cct_table} WHERE category IS NOT NULL AND TRIM(category) != '' ORDER BY category ASC" );
	$country_options  = $wpdb->get_col( "SELECT DISTINCT country FROM {$cct_table} WHERE country IS NOT NULL AND TRIM(country) != '' ORDER BY country ASC" );

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

	if ( '' !== $category_filter && in_array( $category_filter, $category_options, true ) ) {
		$where[]  = 'category = %s';
		$params[] = $category_filter;
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

	$data_sql    = "SELECT _ID, name, city, country, category, listing_status, cct_single_post_id FROM {$cct_table} WHERE {$where_sql} ORDER BY _ID DESC LIMIT %d OFFSET %d";
	$data_params = array_merge( $params, array( $per_page, $offset ) );
	$rows        = $wpdb->get_results( $wpdb->prepare( $data_sql, $data_params ), ARRAY_A );

	ob_start();
	?>
	<div class="mfa-admin-website-list">
		<div class="mfa-admin-website-list-heading">
			<div>
				<h1 class="mfa-h2">Websites</h1>
				<p class="mfa-body-muted"><?php echo esc_html( number_format_i18n( $total ) ); ?> website<?php echo 1 === $total ? '' : 's'; ?></p>
			</div>
			<a href="<?php echo esc_url( home_url( '/add-website/' ) ); ?>" target="_blank" rel="noopener" class="mfa-btn mfa-btn-primary">Add New</a>
		</div>

		<form method="get" class="mfa-admin-website-filters">
			<input type="text" name="website_search" value="<?php echo esc_attr( $search ); ?>" placeholder="Search name, address, or city" class="mfa-admin-website-search">

			<select name="status" class="mfa-admin-website-select">
				<option value="">All statuses</option>
				<?php foreach ( $status_options as $status_option ) : ?>
					<option value="<?php echo esc_attr( $status_option ); ?>" <?php selected( $status_filter, $status_option ); ?>><?php echo esc_html( $status_option ); ?></option>
				<?php endforeach; ?>
			</select>

			<select name="category" class="mfa-admin-website-select">
				<option value="">All categories</option>
				<?php foreach ( $category_options as $category_option ) : ?>
					<option value="<?php echo esc_attr( $category_option ); ?>" <?php selected( $category_filter, $category_option ); ?>><?php echo esc_html( $category_option ); ?></option>
				<?php endforeach; ?>
			</select>

			<select name="country" class="mfa-admin-website-select">
				<option value="">All countries</option>
				<?php foreach ( $country_options as $country_option ) : ?>
					<option value="<?php echo esc_attr( $country_option ); ?>" <?php selected( $country_filter, $country_option ); ?>><?php echo esc_html( $country_option ); ?></option>
				<?php endforeach; ?>
			</select>

			<button type="submit" class="mfa-btn mfa-btn-primary mfa-admin-website-filter-btn">Filter</button>
			<?php if ( '' !== $search || '' !== $status_filter || '' !== $category_filter || '' !== $country_filter ) : ?>
				<a href="<?php echo esc_url( remove_query_arg( array( 'website_search', 'status', 'category', 'country', 'paged' ) ) ); ?>" class="mfa-admin-website-clear">Clear</a>
			<?php endif; ?>
		</form>

		<div class="mfa-admin-website-table-wrap">
			<table class="mfa-admin-website-table">
				<thead>
					<tr>
						<th>Name</th>
						<th>City</th>
						<th>Country</th>
						<th>Category</th>
						<th>Status</th>
						<th></th>
					</tr>
				</thead>
				<tbody>
					<?php if ( empty( $rows ) ) : ?>
						<tr><td colspan="6" class="mfa-admin-website-empty">No websites found.</td></tr>
					<?php else : ?>
						<?php foreach ( $rows as $row ) :
							$view_url = ! empty( $row['cct_single_post_id'] ) ? get_permalink( (int) $row['cct_single_post_id'] ) : '';
							?>
							<tr>
								<td data-label="Name"><?php echo esc_html( $row['name'] ? $row['name'] : '—' ); ?></td>
								<td data-label="City"><?php echo esc_html( $row['city'] ? $row['city'] : '—' ); ?></td>
								<td data-label="Country"><?php echo esc_html( $row['country'] ? $row['country'] : '—' ); ?></td>
								<td data-label="Category"><?php echo esc_html( $row['category'] ? $row['category'] : '—' ); ?></td>
								<td data-label="Status">
									<?php if ( ! empty( $row['listing_status'] ) ) : ?>
										<span class="mfa-admin-status-badge mfa-admin-status-<?php echo esc_attr( sanitize_html_class( strtolower( $row['listing_status'] ) ) ); ?>"><?php echo esc_html( $row['listing_status'] ); ?></span>
									<?php else : ?>
										<span class="mfa-admin-status-badge mfa-admin-status-none">—</span>
									<?php endif; ?>
								</td>
								<td data-label="" class="mfa-admin-website-actions">
									<?php if ( $view_url ) : ?>
										<a href="<?php echo esc_url( $view_url ); ?>" target="_blank" rel="noopener" class="mfa-btn mfa-btn-solid-dark mfa-admin-website-view-btn">View</a>
									<?php endif; ?>
								</td>
							</tr>
						<?php endforeach; ?>
					<?php endif; ?>
				</tbody>
			</table>
		</div>

		<?php if ( $total_pages > 1 ) : ?>
			<nav class="mfa-admin-website-pagination" aria-label="Websites pagination">
				<?php if ( $paged > 1 ) : ?>
					<a href="<?php echo esc_url( add_query_arg( 'paged', $paged - 1 ) ); ?>" class="mfa-admin-website-page-link">&larr; Prev</a>
				<?php endif; ?>
				<span class="mfa-admin-website-page-status">Page <?php echo esc_html( number_format_i18n( $paged ) ); ?> of <?php echo esc_html( number_format_i18n( $total_pages ) ); ?></span>
				<?php if ( $paged < $total_pages ) : ?>
					<a href="<?php echo esc_url( add_query_arg( 'paged', $paged + 1 ) ); ?>" class="mfa-admin-website-page-link">Next &rarr;</a>
				<?php endif; ?>
			</nav>
		<?php endif; ?>
	</div>
	<?php
	return ob_get_clean();
}
