<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * [mfa_admin_blog_list] - the /admin/blog/ table, replacing the
 * [mfa_coming_soon] placeholder, same table+search+filter+pagination
 * pattern as admin-website-list.php / admin-knowledge-list.php.
 *
 * Lists the native `blog` CPT (wp_posts) - confirmed live (2026-08-14):
 * 17 published posts, real article content (e.g. "8 Questions to Ask
 * Before Hiring an Online Quran Tutor"). Not the same as native `post`,
 * which this site instead uses for member namecards - `get_post_types()`
 * confirms `blog` is its own registered post type. No taxonomy is
 * attached to it (checked via get_object_taxonomies('blog') - empty), so
 * unlike Knowledge there's no category filter here.
 *
 * No "Add New" public flow exists for this content type - Blog articles
 * are staff/AI-authored, so "Add New" opens the native wp-admin post
 * editor instead of a custom front-end form.
 *
 * GET-based filtering, NOT 's' for search (WP's reserved search query var,
 * same reasoning as every other admin list page here).
 */

add_shortcode( 'mfa_admin_blog_list', 'mfa_admin_blog_list_shortcode' );
function mfa_admin_blog_list_shortcode() {
	global $wpdb;

	$search        = isset( $_GET['blog_search'] ) ? sanitize_text_field( wp_unslash( $_GET['blog_search'] ) ) : '';
	$status_filter = isset( $_GET['status'] ) ? sanitize_text_field( wp_unslash( $_GET['status'] ) ) : '';
	$paged         = isset( $_GET['paged'] ) ? max( 1, absint( $_GET['paged'] ) ) : 1;
	$per_page      = 25;

	// Standard WordPress post statuses, not a custom listing_status field
	// like the CCT-backed pages - this is a real wp_posts post type.
	$status_options = array(
		'publish' => 'Published',
		'draft'   => 'Draft',
		'pending' => 'Pending Review',
		'private' => 'Private',
		'trash'   => 'Trash',
	);

	$where  = array( "p.post_type = 'blog'" );
	$params = array();

	if ( '' !== $search ) {
		$where[]  = 'p.post_title LIKE %s';
		$params[] = '%' . $wpdb->esc_like( $search ) . '%';
	}

	if ( '' !== $status_filter && array_key_exists( $status_filter, $status_options ) ) {
		$where[]  = 'p.post_status = %s';
		$params[] = $status_filter;
	} else {
		// Default view mirrors wp-admin's own list table convention: hide
		// Trash unless explicitly selected.
		$where[] = "p.post_status != 'trash'";
	}

	$where_sql = implode( ' AND ', $where );

	$count_sql = "SELECT COUNT(*) FROM {$wpdb->posts} p WHERE {$where_sql}";
	$total     = $params ? (int) $wpdb->get_var( $wpdb->prepare( $count_sql, $params ) ) : (int) $wpdb->get_var( $count_sql );

	$total_pages = max( 1, (int) ceil( $total / $per_page ) );
	$paged       = min( $paged, $total_pages );
	$offset      = ( $paged - 1 ) * $per_page;

	$data_sql    = "SELECT p.ID, p.post_title, p.post_status, p.post_date FROM {$wpdb->posts} p WHERE {$where_sql} ORDER BY p.post_date DESC LIMIT %d OFFSET %d";
	$data_params = array_merge( $params, array( $per_page, $offset ) );
	$rows        = $wpdb->get_results( $wpdb->prepare( $data_sql, $data_params ), ARRAY_A );

	$add_new_url = admin_url( 'post-new.php?post_type=blog' );

	ob_start();
	?>
	<div class="mfa-admin-blog-list">
		<div class="mfa-admin-blog-list-heading">
			<div>
				<h1 class="mfa-h2">Blog</h1>
				<p class="mfa-body-muted"><?php echo esc_html( number_format_i18n( $total ) ); ?> post<?php echo 1 === $total ? '' : 's'; ?></p>
			</div>
			<a href="<?php echo esc_url( $add_new_url ); ?>" target="_blank" rel="noopener" class="mfa-btn mfa-btn-primary">Add New</a>
		</div>

		<form method="get" class="mfa-admin-blog-filters">
			<input type="text" name="blog_search" value="<?php echo esc_attr( $search ); ?>" placeholder="Search title" class="mfa-admin-blog-search">

			<select name="status" class="mfa-admin-blog-select">
				<option value="">All statuses</option>
				<?php foreach ( $status_options as $value => $label ) : ?>
					<option value="<?php echo esc_attr( $value ); ?>" <?php selected( $status_filter, $value ); ?>><?php echo esc_html( $label ); ?></option>
				<?php endforeach; ?>
			</select>

			<button type="submit" class="mfa-btn mfa-btn-primary mfa-admin-blog-filter-btn">Filter</button>
			<?php if ( '' !== $search || '' !== $status_filter ) : ?>
				<a href="<?php echo esc_url( remove_query_arg( array( 'blog_search', 'status', 'paged' ) ) ); ?>" class="mfa-admin-blog-clear">Clear</a>
			<?php endif; ?>
		</form>

		<div class="mfa-admin-blog-table-wrap">
			<table class="mfa-admin-blog-table">
				<thead>
					<tr>
						<th>Title</th>
						<th>Date</th>
						<th>Status</th>
						<th></th>
					</tr>
				</thead>
				<tbody>
					<?php if ( empty( $rows ) ) : ?>
						<tr><td colspan="4" class="mfa-admin-blog-empty">No posts found.</td></tr>
					<?php else : ?>
						<?php foreach ( $rows as $row ) :
							$view_url   = 'publish' === $row['post_status'] ? get_permalink( (int) $row['ID'] ) : '';
							$status_key = sanitize_html_class( strtolower( $row['post_status'] ) );
							?>
							<tr>
								<td data-label="Title"><?php echo esc_html( $row['post_title'] ? $row['post_title'] : '—' ); ?></td>
								<td data-label="Date"><?php echo esc_html( mysql2date( 'j M Y', $row['post_date'] ) ); ?></td>
								<td data-label="Status">
									<span class="mfa-admin-status-badge mfa-admin-status-<?php echo esc_attr( $status_key ); ?>"><?php echo esc_html( $status_options[ $row['post_status'] ] ?? ucfirst( $row['post_status'] ) ); ?></span>
								</td>
								<td data-label="" class="mfa-admin-blog-actions">
									<?php if ( $view_url ) : ?>
										<a href="<?php echo esc_url( $view_url ); ?>" target="_blank" rel="noopener" class="mfa-btn mfa-btn-solid-dark mfa-admin-blog-view-btn">View</a>
									<?php endif; ?>
									<a href="<?php echo esc_url( admin_url( 'post.php?post=' . (int) $row['ID'] . '&action=edit' ) ); ?>" target="_blank" rel="noopener" class="mfa-btn mfa-btn-solid-dark mfa-admin-blog-view-btn">Edit</a>
								</td>
							</tr>
						<?php endforeach; ?>
					<?php endif; ?>
				</tbody>
			</table>
		</div>

		<?php if ( $total_pages > 1 ) : ?>
			<nav class="mfa-admin-blog-pagination" aria-label="Blog pagination">
				<?php if ( $paged > 1 ) : ?>
					<a href="<?php echo esc_url( add_query_arg( 'paged', $paged - 1 ) ); ?>" class="mfa-admin-blog-page-link">&larr; Prev</a>
				<?php endif; ?>
				<span class="mfa-admin-blog-page-status">Page <?php echo esc_html( number_format_i18n( $paged ) ); ?> of <?php echo esc_html( number_format_i18n( $total_pages ) ); ?></span>
				<?php if ( $paged < $total_pages ) : ?>
					<a href="<?php echo esc_url( add_query_arg( 'paged', $paged + 1 ) ); ?>" class="mfa-admin-blog-page-link">Next &rarr;</a>
				<?php endif; ?>
			</nav>
		<?php endif; ?>
	</div>
	<?php
	return ob_get_clean();
}
