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
	if ( function_exists( 'mfa_admin_require_section_access' ) ) {
		$no_access = mfa_admin_require_section_access( 'website' );
		if ( $no_access ) {
			return $no_access;
		}
	}

	global $wpdb;
	$cct_table = $wpdb->prefix . 'jet_cct_web';

	$mfa_web_import_report = mfa_admin_website_maybe_run_import();
	$mfa_web_linkcheck_report = mfa_admin_website_maybe_run_linkcheck();

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
		<?php echo mfa_admin_website_import_panel( $mfa_web_import_report ); // phpcs:ignore WordPress.Security.EscapeOutput ?>
		<?php echo mfa_admin_website_linkcheck_panel( $mfa_web_linkcheck_report ); // phpcs:ignore WordPress.Security.EscapeOutput ?>
		<div class="mfa-admin-website-list-heading">
			<div>
				<h1 class="mfa-h2">Websites</h1>
				<p class="mfa-body-muted"><?php echo esc_html( number_format_i18n( $total ) ); ?> website<?php echo 1 === $total ? '' : 's'; ?></p>
			</div>
			<div class="mfa-admin-website-heading-actions">
				<a href="<?php echo esc_url( home_url( '/add-website/' ) ); ?>" target="_blank" rel="noopener" class="mfa-btn mfa-btn-primary">Add New</a>
				<?php // Generate Content button hidden for now (2026-08-17, user request); /admin/website/generate/ still works if linked to directly, just not surfaced here. ?>
			</div>
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

/**
 * Runs the business -> website import when the panel button is submitted.
 *
 * Synchronous rather than the batched AJAX the crawler pages use, because the
 * scan is bounded by the mfa_web_extract_last_run watermark: a routine run
 * only looks at businesses created or edited since last time, which is seconds
 * of work rather than the whole 78K table. The first run after a long gap is
 * the slow case, hence the raised time limit.
 *
 * @return array|null Report to render, or null when nothing was submitted.
 */
function mfa_admin_website_maybe_run_import() {
	// Either button submits only its own name, so both must be accepted here -
	// checking only the first meant Full rescan silently did nothing.
	if ( empty( $_POST['mfa_web_import'] ) && empty( $_POST['mfa_web_import_full'] ) ) {
		return null;
	}

	if ( ! isset( $_POST['mfa_web_import_nonce'] )
		|| ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['mfa_web_import_nonce'] ) ), 'mfa_web_import' ) ) {
		return array( 'error' => 'Security check failed. Reload the page and try again.' );
	}

	// The shortcode's section gate has already run, but this one writes rows -
	// check again rather than trust having been reached from the right page.
	if ( function_exists( 'mfa_user_can_access_admin_section' ) && ! mfa_user_can_access_admin_section( 'website' ) ) {
		return array( 'error' => 'You do not have permission to run the import.' );
	}

	if ( ! function_exists( 'mfa_web_extract_daily_run' ) ) {
		return array( 'error' => 'Website extract is unavailable.' );
	}

	@set_time_limit( 300 );

	// Full rescan ignores the watermark and reads every business with a
	// website. Wanted after the exclusion lists change, since the incremental
	// run would never revisit rows it has already passed over.
	if ( ! empty( $_POST['mfa_web_import_full'] ) ) {
		$checkpoint = current_time( 'mysql' );
		$r          = mfa_web_extract_from_business( true, 0, 0, '' );
		update_option( 'mfa_web_extract_last_run', $checkpoint );
		$r['full']  = true;
		$r['since'] = '';
		return $r;
	}

	return mfa_web_extract_daily_run();
}

/**
 * The import panel: when it last ran, the button, and the result of the run
 * just performed. Scanned/skipped counts are shown rather than only the number
 * added, so a run that adds nothing still reads as "it worked and there was
 * nothing new" instead of looking broken.
 */
function mfa_admin_website_import_panel( $report ) {
	$last = get_option( 'mfa_web_extract_last_run', '' );

	ob_start();
	?>
	<div class="mfa-admin-web-import">
		<div class="mfa-admin-web-import-head">
			<div>
				<strong>Import websites from business listings</strong>
				<p class="mfa-admin-web-import-note">
					Scans business records for a website address and adds any new ones to this directory.
					Only businesses added or edited since the last run are checked, so this is quick to repeat.
					Social media and ordering-platform links are skipped.
				</p>
			</div>
			<form method="post" class="mfa-admin-web-import-form">
				<?php wp_nonce_field( 'mfa_web_import', 'mfa_web_import_nonce' ); ?>
				<button type="submit" name="mfa_web_import" value="1" class="mfa-btn mfa-btn-primary">Run import</button>
				<button type="submit" name="mfa_web_import_full" value="1" class="mfa-btn mfa-admin-web-import-full" onclick="return confirm('Re-check every business with a website, ignoring the last-run marker? This takes longer and is only needed after the exclusion lists change.');">Full rescan</button>
			</form>
		</div>

		<p class="mfa-admin-web-import-last">
			<?php if ( $last ) : ?>
				Last run: <?php echo esc_html( $last ); ?>
			<?php else : ?>
				Never run.
			<?php endif; ?>
		</p>

		<?php if ( is_array( $report ) && isset( $report['error'] ) ) : ?>
			<p class="mfa-admin-web-import-error"><?php echo esc_html( $report['error'] ); ?></p>
		<?php elseif ( is_array( $report ) && 0 === (int) $report['scanned'] ) : ?>
			<p class="mfa-admin-web-import-empty">
				Nothing new to import. Every business added or edited since the last run has already been checked.
				Use <strong>Full rescan</strong> to re-read every business regardless.
			</p>
		<?php elseif ( is_array( $report ) ) : ?>
			<ul class="mfa-admin-web-import-report">
				<li><strong><?php echo esc_html( number_format_i18n( (int) $report['applied'] ) ); ?></strong> websites added</li>
				<li><?php echo esc_html( number_format_i18n( (int) $report['scanned'] ) ); ?> business records scanned</li>
				<li><?php echo esc_html( number_format_i18n( (int) $report['duplicate_existing'] + (int) $report['duplicate_in_batch'] ) ); ?> already listed</li>
				<li><?php echo esc_html( number_format_i18n( (int) $report['social_excluded'] ) ); ?> social media links skipped</li>
				<li><?php echo esc_html( number_format_i18n( isset( $report['platform_excluded'] ) ? (int) $report['platform_excluded'] : 0 ) ); ?> ordering-platform links skipped</li>
			</ul>
		<?php endif; ?>
	</div>
	<?php
	return ob_get_clean();
}

/**
 * Runs a link-check batch when its button is submitted. Separate from the
 * import handler because they answer different questions - one adds listings,
 * the other retires them - and mixing the two into one button would make it
 * impossible to run either on its own.
 */
function mfa_admin_website_maybe_run_linkcheck() {
	if ( empty( $_POST['mfa_web_linkcheck'] ) ) {
		return null;
	}

	if ( ! isset( $_POST['mfa_web_linkcheck_nonce'] )
		|| ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['mfa_web_linkcheck_nonce'] ) ), 'mfa_web_linkcheck' ) ) {
		return array( 'error' => 'Security check failed. Reload the page and try again.' );
	}

	if ( function_exists( 'mfa_user_can_access_admin_section' ) && ! mfa_user_can_access_admin_section( 'website' ) ) {
		return array( 'error' => 'You do not have permission to run the link check.' );
	}

	if ( ! function_exists( 'mfa_web_linkcheck_batch' ) ) {
		return array( 'error' => 'Link checker is unavailable.' );
	}

	@set_time_limit( 300 );

	return mfa_web_linkcheck_batch( 200, true );
}

/**
 * Link-check panel: how far through the directory we are, the button, and the
 * result of the run just performed.
 *
 * The blocked count is shown deliberately. Roughly one site in eight answers
 * 403 to an automated request while being perfectly alive, and anyone reading
 * this panel needs to see that those were recognised rather than quietly
 * counted as broken.
 */
function mfa_admin_website_linkcheck_panel( $report ) {
	if ( ! function_exists( 'mfa_web_linkcheck_progress' ) ) {
		return '';
	}
	$p = mfa_web_linkcheck_progress();

	ob_start();
	?>
	<div class="mfa-admin-web-import">
		<div class="mfa-admin-web-import-head">
			<div>
				<strong>Check website links</strong>
				<p class="mfa-admin-web-import-note">
					Visits each listed website and records what it returns. Sites that fail three times
					on three separate days are marked as an error and removed from the public directory.
					Sites that merely block automated visitors are left alone.
				</p>
			</div>
			<form method="post" class="mfa-admin-web-import-form">
				<?php wp_nonce_field( 'mfa_web_linkcheck', 'mfa_web_linkcheck_nonce' ); ?>
				<button type="submit" name="mfa_web_linkcheck" value="1" class="mfa-btn mfa-btn-primary">Check 200 links</button>
			</form>
		</div>

		<p class="mfa-admin-web-import-last">
			<?php echo esc_html( number_format_i18n( $p['checked'] ) ); ?> of
			<?php echo esc_html( number_format_i18n( $p['checkable'] ) ); ?> checked
			&middot; <?php echo esc_html( number_format_i18n( $p['failing'] ) ); ?> currently failing
			&middot; <?php echo esc_html( number_format_i18n( $p['errors'] ) ); ?> marked as error
		</p>

		<?php if ( is_array( $report ) && isset( $report['error'] ) ) : ?>
			<p class="mfa-admin-web-import-error"><?php echo esc_html( $report['error'] ); ?></p>
		<?php elseif ( is_array( $report ) && 0 === (int) $report['checked'] ) : ?>
			<p class="mfa-admin-web-import-empty">
				Nothing due a check right now. Working sites are re-checked every 30 days, failing ones every day.
			</p>
		<?php elseif ( is_array( $report ) ) : ?>
			<ul class="mfa-admin-web-import-report">
				<li><strong><?php echo esc_html( number_format_i18n( (int) $report['checked'] ) ); ?></strong> sites checked</li>
				<li><?php echo esc_html( number_format_i18n( (int) $report['alive'] ) ); ?> responded normally</li>
				<li><?php echo esc_html( number_format_i18n( (int) $report['blocked'] ) ); ?> blocked automated visits (treated as alive)</li>
				<li><?php echo esc_html( number_format_i18n( (int) $report['dead'] ) ); ?> returned not-found</li>
				<li><?php echo esc_html( number_format_i18n( (int) $report['transient'] ) ); ?> timed out or failed to connect</li>
				<li><?php echo esc_html( number_format_i18n( (int) $report['unpublished'] ) ); ?> removed from the directory this run</li>
			</ul>
		<?php endif; ?>
	</div>
	<?php
	return ob_get_clean();
}
