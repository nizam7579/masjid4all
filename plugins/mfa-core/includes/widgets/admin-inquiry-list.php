<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * [mfa_admin_inquiry_list] - the /admin/inquiry/ table, replacing the
 * [mfa_coming_soon] placeholder, same table+search+filter+pagination
 * pattern as admin-website-list.php.
 *
 * Reads wp_jet_cct_contact_us directly via $wpdb, never the JetEngine PHP
 * API, per the project's standing rule. This table was confirmed empty
 * and unused (2026-08-14) before wiring [mfa_contact_form]
 * (contact-form.php, /contact-us/) to write into it - no pre-existing
 * JetEngine field config to inherit a status vocabulary from, so
 * New/Read/Replied/Archived below is this project's own convention, not
 * something recovered from JetEngine.
 *
 * Unlike mosque/business/website, there's no public single-listing page
 * to link a "View" button to - inquiries aren't published content. "View"
 * instead opens /admin/inquiry/info/?id={_ID} (the CCT's own `_ID`, not a
 * user_id - an inquiry isn't necessarily tied to a logged-in member),
 * mirroring the /admin/member/info/ detail-subpage pattern.
 *
 * GET-based filtering, NOT 's' for search (WP's reserved search query
 * var, same reasoning as every other admin list page here).
 */

add_shortcode( 'mfa_admin_inquiry_list', 'mfa_admin_inquiry_list_shortcode' );
function mfa_admin_inquiry_list_shortcode() {
	if ( function_exists( 'mfa_admin_require_section_access' ) ) {
		$no_access = mfa_admin_require_section_access( 'inquiry' );
		if ( $no_access ) {
			return $no_access;
		}
	}

	global $wpdb;
	$cct_table = $wpdb->prefix . 'jet_cct_contact_us';

	$search        = isset( $_GET['inquiry_search'] ) ? sanitize_text_field( wp_unslash( $_GET['inquiry_search'] ) ) : '';
	$status_filter = isset( $_GET['status'] ) ? sanitize_text_field( wp_unslash( $_GET['status'] ) ) : '';
	$paged         = isset( $_GET['paged'] ) ? max( 1, absint( $_GET['paged'] ) ) : 1;
	$per_page      = 25;

	$status_options = array( 'New', 'Read', 'Replied', 'Archived' );

	$where  = array( '1=1' );
	$params = array();

	if ( '' !== $search ) {
		$where[]  = '(name LIKE %s OR email LIKE %s OR phone LIKE %s OR subject LIKE %s OR message LIKE %s)';
		$like     = '%' . $wpdb->esc_like( $search ) . '%';
		$params[] = $like;
		$params[] = $like;
		$params[] = $like;
		$params[] = $like;
		$params[] = $like;
	}

	if ( '' !== $status_filter && in_array( $status_filter, $status_options, true ) ) {
		$where[]  = 'cct_status = %s';
		$params[] = $status_filter;
	}

	$where_sql = implode( ' AND ', $where );

	$count_sql = "SELECT COUNT(*) FROM {$cct_table} WHERE {$where_sql}";
	$total     = $params ? (int) $wpdb->get_var( $wpdb->prepare( $count_sql, $params ) ) : (int) $wpdb->get_var( $count_sql );

	$total_pages = max( 1, (int) ceil( $total / $per_page ) );
	$paged       = min( $paged, $total_pages );
	$offset      = ( $paged - 1 ) * $per_page;

	$data_sql    = "SELECT _ID, name, email, phone, subject, cct_status, cct_created FROM {$cct_table} WHERE {$where_sql} ORDER BY _ID DESC LIMIT %d OFFSET %d";
	$data_params = array_merge( $params, array( $per_page, $offset ) );
	$rows        = $wpdb->get_results( $wpdb->prepare( $data_sql, $data_params ), ARRAY_A );

	ob_start();
	?>
	<div class="mfa-admin-inquiry-list">
		<div class="mfa-admin-inquiry-list-heading">
			<div>
				<h1 class="mfa-h2">Inquiries</h1>
				<p class="mfa-body-muted"><?php echo esc_html( number_format_i18n( $total ) ); ?> inquir<?php echo 1 === $total ? 'y' : 'ies'; ?></p>
			</div>
		</div>

		<form method="get" class="mfa-admin-inquiry-filters">
			<input type="text" name="inquiry_search" value="<?php echo esc_attr( $search ); ?>" placeholder="Search name, email, phone, subject, or message" class="mfa-admin-inquiry-search">

			<select name="status" class="mfa-admin-inquiry-select">
				<option value="">All statuses</option>
				<?php foreach ( $status_options as $status_option ) : ?>
					<option value="<?php echo esc_attr( $status_option ); ?>" <?php selected( $status_filter, $status_option ); ?>><?php echo esc_html( $status_option ); ?></option>
				<?php endforeach; ?>
			</select>

			<button type="submit" class="mfa-btn mfa-btn-primary mfa-admin-inquiry-filter-btn">Filter</button>
			<?php if ( '' !== $search || '' !== $status_filter ) : ?>
				<a href="<?php echo esc_url( remove_query_arg( array( 'inquiry_search', 'status', 'paged' ) ) ); ?>" class="mfa-admin-inquiry-clear">Clear</a>
			<?php endif; ?>
		</form>

		<div class="mfa-admin-inquiry-table-wrap">
			<table class="mfa-admin-inquiry-table">
				<thead>
					<tr>
						<th>Name</th>
						<th>Email</th>
						<th>Subject</th>
						<th>Date</th>
						<th>Status</th>
						<th></th>
					</tr>
				</thead>
				<tbody>
					<?php if ( empty( $rows ) ) : ?>
						<tr><td colspan="6" class="mfa-admin-inquiry-empty">No inquiries found.</td></tr>
					<?php else : ?>
						<?php foreach ( $rows as $row ) :
							$view_url = add_query_arg( 'id', (int) $row['_ID'], home_url( '/admin/inquiry/info/' ) );
							?>
							<tr>
								<td data-label="Name"><?php echo esc_html( $row['name'] ? $row['name'] : '—' ); ?></td>
								<td data-label="Email"><?php echo esc_html( $row['email'] ? $row['email'] : '—' ); ?></td>
								<td data-label="Subject"><?php echo esc_html( $row['subject'] ? $row['subject'] : '—' ); ?></td>
								<td data-label="Date"><?php echo esc_html( $row['cct_created'] ? date_i18n( 'j M Y', strtotime( $row['cct_created'] ) ) : '—' ); ?></td>
								<td data-label="Status">
									<?php if ( ! empty( $row['cct_status'] ) ) : ?>
										<span class="mfa-admin-status-badge mfa-admin-status-<?php echo esc_attr( sanitize_html_class( strtolower( $row['cct_status'] ) ) ); ?>"><?php echo esc_html( $row['cct_status'] ); ?></span>
									<?php else : ?>
										<span class="mfa-admin-status-badge mfa-admin-status-none">—</span>
									<?php endif; ?>
								</td>
								<td data-label="" class="mfa-admin-inquiry-actions">
									<a href="<?php echo esc_url( $view_url ); ?>" target="_blank" rel="noopener" class="mfa-btn mfa-btn-solid-dark mfa-admin-inquiry-view-btn">View</a>
								</td>
							</tr>
						<?php endforeach; ?>
					<?php endif; ?>
				</tbody>
			</table>
		</div>

		<?php if ( $total_pages > 1 ) : ?>
			<nav class="mfa-admin-inquiry-pagination" aria-label="Inquiries pagination">
				<?php if ( $paged > 1 ) : ?>
					<a href="<?php echo esc_url( add_query_arg( 'paged', $paged - 1 ) ); ?>" class="mfa-admin-inquiry-page-link">&larr; Prev</a>
				<?php endif; ?>
				<span class="mfa-admin-inquiry-page-status">Page <?php echo esc_html( number_format_i18n( $paged ) ); ?> of <?php echo esc_html( number_format_i18n( $total_pages ) ); ?></span>
				<?php if ( $paged < $total_pages ) : ?>
					<a href="<?php echo esc_url( add_query_arg( 'paged', $paged + 1 ) ); ?>" class="mfa-admin-inquiry-page-link">Next &rarr;</a>
				<?php endif; ?>
			</nav>
		<?php endif; ?>
	</div>
	<?php
	return ob_get_clean();
}
