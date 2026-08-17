<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * [mfa_admin_knowledge_list] - the /admin/knowledge/ table, replacing the
 * [mfa_coming_soon] placeholder, same table+search+filter+pagination
 * pattern as admin-website-list.php / admin-business-list.php.
 *
 * Lists the native `knowledge` CPT (wp_posts, not a JetEngine CCT table -
 * this content type has no CCT, just a real WordPress post type + the
 * `knowledge-category` taxonomy), read directly via $wpdb for consistency
 * with the other admin list pages rather than WP_Query, so search/filter/
 * pagination behave identically across all of them. Confirmed live
 * (2026-08-14): 158 published + 4 draft posts; taxonomy is singular
 * `knowledge-category` (NOT the plural `knowledge-categories`, which
 * exists as a separate, entirely unused taxonomy with 0 real term
 * assignments - checked both via wp_count_posts()/get_object_taxonomies()
 * before writing this query, don't assume from the name alone).
 *
 * No "Add New" public flow exists for this content type (unlike business/
 * website's /add-business//add-website/) - Knowledge articles are staff/
 * AI-authored, so "Add New" opens the native wp-admin post editor instead
 * of a custom front-end form.
 *
 * GET-based filtering, NOT 's' for search (WP's reserved search query var,
 * same reasoning as every other admin list page here).
 */

add_shortcode( 'mfa_admin_knowledge_list', 'mfa_admin_knowledge_list_shortcode' );
function mfa_admin_knowledge_list_shortcode() {
	if ( function_exists( 'mfa_admin_require_section_access' ) ) {
		$no_access = mfa_admin_require_section_access( 'knowledge' );
		if ( $no_access ) {
			return $no_access;
		}
	}

	global $wpdb;

	$search          = isset( $_GET['knowledge_search'] ) ? sanitize_text_field( wp_unslash( $_GET['knowledge_search'] ) ) : '';
	$status_filter   = isset( $_GET['status'] ) ? sanitize_text_field( wp_unslash( $_GET['status'] ) ) : '';
	$category_filter = isset( $_GET['category'] ) ? sanitize_text_field( wp_unslash( $_GET['category'] ) ) : '';
	$paged           = isset( $_GET['paged'] ) ? max( 1, absint( $_GET['paged'] ) ) : 1;
	$per_page        = 25;

	// Standard WordPress post statuses, not a custom listing_status field
	// like the CCT-backed pages - this is a real wp_posts post type.
	$status_options = array(
		'publish' => 'Published',
		'draft'   => 'Draft',
		'pending' => 'Pending Review',
		'private' => 'Private',
		'trash'   => 'Trash',
	);

	$category_terms = get_terms( array(
		'taxonomy'   => 'knowledge-category',
		'hide_empty' => true,
	) );
	if ( is_wp_error( $category_terms ) ) {
		$category_terms = array();
	}

	$where  = array( "p.post_type = 'knowledge'" );
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

	if ( '' !== $category_filter ) {
		$where[]  = "p.ID IN ( SELECT tr.object_id FROM {$wpdb->term_relationships} tr
			INNER JOIN {$wpdb->term_taxonomy} tt ON tr.term_taxonomy_id = tt.term_taxonomy_id
			INNER JOIN {$wpdb->terms} t ON tt.term_id = t.term_id
			WHERE tt.taxonomy = 'knowledge-category' AND t.slug = %s )";
		$params[] = $category_filter;
	}

	$where_sql = implode( ' AND ', $where );

	$count_sql = "SELECT COUNT(*) FROM {$wpdb->posts} p WHERE {$where_sql}";
	$total     = $params ? (int) $wpdb->get_var( $wpdb->prepare( $count_sql, $params ) ) : (int) $wpdb->get_var( $count_sql );

	$total_pages = max( 1, (int) ceil( $total / $per_page ) );
	$paged       = min( $paged, $total_pages );
	$offset      = ( $paged - 1 ) * $per_page;

	$data_sql    = "SELECT p.ID, p.post_title, p.post_excerpt, p.post_content, p.post_status, p.post_date FROM {$wpdb->posts} p WHERE {$where_sql} ORDER BY p.post_date DESC LIMIT %d OFFSET %d";
	$data_params = array_merge( $params, array( $per_page, $offset ) );
	$rows        = $wpdb->get_results( $wpdb->prepare( $data_sql, $data_params ), ARRAY_A );

	$add_new_url = admin_url( 'post-new.php?post_type=knowledge' );

	ob_start();
	?>
	<div class="mfa-admin-knowledge-list">
		<div class="mfa-admin-knowledge-list-heading">
			<div>
				<h1 class="mfa-h2">Knowledge Hub</h1>
				<p class="mfa-body-muted"><?php echo esc_html( number_format_i18n( $total ) ); ?> article<?php echo 1 === $total ? '' : 's'; ?></p>
			</div>
			<div class="mfa-admin-knowledge-list-heading-actions">
				<a href="<?php echo esc_url( home_url( '/admin/knowledge/ai/' ) ); ?>" class="mfa-btn mfa-btn-solid-dark">AI Content</a>
				<a href="<?php echo esc_url( $add_new_url ); ?>" target="_blank" rel="noopener" class="mfa-btn mfa-btn-primary">Add New</a>
			</div>
		</div>

		<form method="get" class="mfa-admin-knowledge-filters">
			<input type="text" name="knowledge_search" value="<?php echo esc_attr( $search ); ?>" placeholder="Search title" class="mfa-admin-knowledge-search">

			<select name="status" class="mfa-admin-knowledge-select">
				<option value="">All statuses</option>
				<?php foreach ( $status_options as $value => $label ) : ?>
					<option value="<?php echo esc_attr( $value ); ?>" <?php selected( $status_filter, $value ); ?>><?php echo esc_html( $label ); ?></option>
				<?php endforeach; ?>
			</select>

			<select name="category" class="mfa-admin-knowledge-select">
				<option value="">All categories</option>
				<?php foreach ( $category_terms as $term ) : ?>
					<option value="<?php echo esc_attr( $term->slug ); ?>" <?php selected( $category_filter, $term->slug ); ?>><?php echo esc_html( $term->name ); ?></option>
				<?php endforeach; ?>
			</select>

			<button type="submit" class="mfa-btn mfa-btn-primary mfa-admin-knowledge-filter-btn">Filter</button>
			<?php if ( '' !== $search || '' !== $status_filter || '' !== $category_filter ) : ?>
				<a href="<?php echo esc_url( remove_query_arg( array( 'knowledge_search', 'status', 'category', 'paged' ) ) ); ?>" class="mfa-admin-knowledge-clear">Clear</a>
			<?php endif; ?>
		</form>

		<div class="mfa-admin-knowledge-table-wrap">
			<table class="mfa-admin-knowledge-table">
				<thead>
					<tr>
						<th>Title</th>
						<th>Excerpt</th>
						<th>Category</th>
						<th>Date</th>
						<th>Status</th>
						<th></th>
					</tr>
				</thead>
				<tbody>
					<?php if ( empty( $rows ) ) : ?>
						<tr><td colspan="6" class="mfa-admin-knowledge-empty">No articles found.</td></tr>
					<?php else : ?>
						<?php foreach ( $rows as $row ) :
							$terms        = get_the_terms( (int) $row['ID'], 'knowledge-category' );
							$cat_label    = ( $terms && ! is_wp_error( $terms ) ) ? wp_list_pluck( $terms, 'name' ) : array();
							$view_url     = 'publish' === $row['post_status'] ? get_permalink( (int) $row['ID'] ) : '';
							$status_key   = sanitize_html_class( strtolower( $row['post_status'] ) );
							$is_empty_draft = 'draft' === $row['post_status'] && '' === trim( wp_strip_all_tags( $row['post_content'] ) );
							$ai_url       = home_url( '/admin/knowledge/ai/generate/?id=' . (int) $row['ID'] );
							?>
							<tr>
								<td data-label="Title"><?php echo esc_html( $row['post_title'] ? $row['post_title'] : '—' ); ?></td>
								<td data-label="Excerpt" class="mfa-admin-knowledge-excerpt"><?php echo esc_html( $row['post_excerpt'] ? $row['post_excerpt'] : '—' ); ?></td>
								<td data-label="Category"><?php echo esc_html( $cat_label ? implode( ', ', $cat_label ) : '—' ); ?></td>
								<td data-label="Date"><?php echo esc_html( mysql2date( 'j M Y', $row['post_date'] ) ); ?></td>
								<td data-label="Status">
									<span class="mfa-admin-status-badge mfa-admin-status-<?php echo esc_attr( $status_key ); ?>"><?php echo esc_html( $status_options[ $row['post_status'] ] ?? ucfirst( $row['post_status'] ) ); ?></span>
								</td>
								<td data-label="" class="mfa-admin-knowledge-actions">
									<?php if ( $view_url ) : ?>
										<a href="<?php echo esc_url( $view_url ); ?>" target="_blank" rel="noopener" class="mfa-btn mfa-btn-solid-dark mfa-admin-knowledge-view-btn">View</a>
									<?php endif; ?>
									<a href="<?php echo esc_url( admin_url( 'post.php?post=' . (int) $row['ID'] . '&action=edit' ) ); ?>" target="_blank" rel="noopener" class="mfa-btn mfa-btn-solid-dark mfa-admin-knowledge-view-btn">Edit</a>
									<?php if ( $is_empty_draft ) : ?>
										<a href="<?php echo esc_url( $ai_url ); ?>" target="_blank" rel="noopener" class="mfa-btn mfa-btn-solid-dark mfa-admin-knowledge-view-btn">AI Content</a>
									<?php endif; ?>
								</td>
							</tr>
						<?php endforeach; ?>
					<?php endif; ?>
				</tbody>
			</table>
		</div>

		<?php if ( $total_pages > 1 ) : ?>
			<nav class="mfa-admin-knowledge-pagination" aria-label="Knowledge Hub pagination">
				<?php if ( $paged > 1 ) : ?>
					<a href="<?php echo esc_url( add_query_arg( 'paged', $paged - 1 ) ); ?>" class="mfa-admin-knowledge-page-link">&larr; Prev</a>
				<?php endif; ?>
				<span class="mfa-admin-knowledge-page-status">Page <?php echo esc_html( number_format_i18n( $paged ) ); ?> of <?php echo esc_html( number_format_i18n( $total_pages ) ); ?></span>
				<?php if ( $paged < $total_pages ) : ?>
					<a href="<?php echo esc_url( add_query_arg( 'paged', $paged + 1 ) ); ?>" class="mfa-admin-knowledge-page-link">Next &rarr;</a>
				<?php endif; ?>
			</nav>
		<?php endif; ?>
	</div>
	<?php
	return ob_get_clean();
}
