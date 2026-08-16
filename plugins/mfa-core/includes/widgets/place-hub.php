<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * [mfa_place_hub] - the whole rendered body of a /places/... hub page, and
 * the template that carries it. One shortcode owns the page per the project's
 * standing rule; the `place` post's own editor content, if any, renders as an
 * intro above the generated sections, which is where hand-written promo copy
 * for a launched city goes.
 *
 * Everything here is server-rendered on purpose. The entire point of these
 * pages is to give Google crawlable links to listings - loading them over
 * AJAX the way the /masjid/ and /business/ grids do would reproduce exactly
 * the problem this is meant to fix (see includes/places.php's docblock).
 *
 * Sections, in order: breadcrumb, heading + counts, editorial intro, child
 * hubs ("all states"), mosques, businesses, sibling cross-links. Empty
 * sections omit themselves rather than rendering an empty shell.
 */

add_filter( 'template_include', 'mfa_place_template_include' );
function mfa_place_template_include( $template ) {
	if ( is_admin() || ! is_singular( MFA_PLACE_POST_TYPE ) ) {
		return $template;
	}

	$custom = MFA_CORE_PATH . 'templates/place-page.php';

	return file_exists( $custom ) ? $custom : $template;
}

/** Listings per page, per section. Deliberately generous - each one is an
 * internal link, and the whole job of this page is to hand out link equity. */
const MFA_PLACE_PER_PAGE = 24;

/** Each listing type paginates independently ($type is 'mosque' or
 * 'business') via its own ?mosque_pg=/?business_pg= query arg - a shared
 * ?pg= meant paging through one tab's list silently moved the other tab's
 * list to the same page number too. */
function mfa_place_current_page( $type ) {
	$key = $type . '_pg';
	return isset( $_GET[ $key ] ) ? max( 1, absint( $_GET[ $key ] ) ) : 1;
}

/** A listing's public URL - page_url is what the directory's own AJAX loader
 * uses, with the CPT permalink as the fallback for rows that predate it. */
function mfa_place_listing_url( $row ) {
	if ( ! empty( $row['page_url'] ) ) {
		return $row['page_url'];
	}
	if ( ! empty( $row['cct_single_post_id'] ) ) {
		return (string) get_permalink( (int) $row['cct_single_post_id'] );
	}
	return '';
}

add_shortcode( 'mfa_place_hub', 'mfa_place_hub_shortcode' );
function mfa_place_hub_shortcode() {
	$place_id = get_queried_object_id();
	if ( ! $place_id ) {
		return '';
	}

	$title    = get_the_title( $place_id );
	$geo      = mfa_place_geo( $place_id );
	$counts   = mfa_place_counts( $place_id );
	$children = mfa_place_children( $place_id );

	$mosque_paged   = mfa_place_current_page( 'mosque' );
	$business_paged = mfa_place_current_page( 'business' );

	$mosques    = mfa_place_listings( $place_id, 'mosque', $mosque_paged, MFA_PLACE_PER_PAGE );
	$businesses = mfa_place_listings( $place_id, 'business', $business_paged, MFA_PLACE_PER_PAGE );

	$ancestors = array_reverse( get_post_ancestors( $place_id ) );

	ob_start();
	?>
	<div class="mfa-place">

		<nav class="mfa-place-crumbs" aria-label="Breadcrumb">
			<?php
			// No /places/ root crumb - the post type has no archive, so linking
			// one would 404 on every hub page. The chain starts at the country.
			foreach ( $ancestors as $ancestor_id ) :
				?>
				<a href="<?php echo esc_url( get_permalink( $ancestor_id ) ); ?>"><?php echo esc_html( get_the_title( $ancestor_id ) ); ?></a>
				<span aria-hidden="true">&rsaquo;</span>
			<?php endforeach; ?>
			<span aria-current="page"><?php echo esc_html( $title ); ?></span>
		</nav>

		<header class="mfa-place-head">
			<h1 class="mfa-place-title">Mosques &amp; Halal Businesses in <?php echo esc_html( $title ); ?></h1>
			<p class="mfa-place-counts">
				<strong><?php echo esc_html( number_format_i18n( $counts['mosque'] ) ); ?></strong> mosques
				&middot;
				<strong><?php echo esc_html( number_format_i18n( $counts['business'] ) ); ?></strong> halal businesses
			</p>
		</header>

		<?php
		$intro = get_post_field( 'post_content', $place_id );
		if ( trim( (string) $intro ) !== '' ) :
			?>
			<div class="mfa-place-intro"><?php echo apply_filters( 'the_content', $intro ); ?></div>
		<?php endif; ?>

		<?php if ( $children ) : ?>
			<section class="mfa-place-section">
				<h2 class="mfa-place-h2">Explore <?php echo esc_html( $title ); ?></h2>
				<ul class="mfa-place-children">
					<?php
					foreach ( $children as $child ) :
						$child_counts = mfa_place_counts( $child->ID );
						?>
						<li>
							<a href="<?php echo esc_url( get_permalink( $child->ID ) ); ?>">
								<span class="mfa-place-child-name"><?php echo esc_html( $child->post_title ); ?></span>
								<span class="mfa-place-child-count"><?php echo esc_html( number_format_i18n( $child_counts['mosque'] ) ); ?> mosques</span>
							</a>
						</li>
					<?php endforeach; ?>
				</ul>
			</section>
		<?php endif; ?>

		<?php
		$mosque_html   = mfa_place_listing_section( 'Mosques in ' . $title, $mosques, $mosque_paged, 'mosque' );
		$business_html = mfa_place_listing_section( 'Halal businesses in ' . $title, $businesses, $business_paged, 'business' );

		if ( $mosque_html && $business_html ) :
			// Both lists exist - tabs, so a user can switch between them
			// without scrolling. Both panels stay in the markup either way
			// (only CSS/JS toggles which shows) so this stays exactly as
			// crawlable as the untabbed version - see this file's top
			// docblock on why nothing here loads over AJAX.
			//
			// Default tab follows whichever pager was just clicked (a
			// pagination link is a plain full-page reload, not an in-tab
			// JS swap) - otherwise "Next" on the Businesses tab would land
			// back on the Mosques tab. Falls back to Mosques first when
			// neither pager is in the URL.
			$default_tab = isset( $_GET['business_pg'] ) && ! isset( $_GET['mosque_pg'] ) ? 'business' : 'mosque';
			?>
			<div class="mfa-place-tabs">
				<div class="mfa-place-tablist" role="tablist">
					<button type="button" class="mfa-place-tab<?php echo 'mosque' === $default_tab ? ' is-active' : ''; ?>" data-tab="mosque" role="tab" aria-selected="<?php echo 'mosque' === $default_tab ? 'true' : 'false'; ?>">
						Mosques <span class="mfa-place-tab-count"><?php echo esc_html( number_format_i18n( $counts['mosque'] ) ); ?></span>
					</button>
					<button type="button" class="mfa-place-tab<?php echo 'business' === $default_tab ? ' is-active' : ''; ?>" data-tab="business" role="tab" aria-selected="<?php echo 'business' === $default_tab ? 'true' : 'false'; ?>">
						Halal Businesses <span class="mfa-place-tab-count"><?php echo esc_html( number_format_i18n( $counts['business'] ) ); ?></span>
					</button>
				</div>
				<div class="mfa-place-tabpanel<?php echo 'mosque' === $default_tab ? ' is-active' : ''; ?>" data-tabpanel="mosque"><?php echo $mosque_html; // phpcs:ignore WordPress.Security.EscapeOutput ?></div>
				<div class="mfa-place-tabpanel<?php echo 'business' === $default_tab ? ' is-active' : ''; ?>" data-tabpanel="business"><?php echo $business_html; // phpcs:ignore WordPress.Security.EscapeOutput ?></div>
			</div>
			<?php
		else :
			echo $mosque_html . $business_html; // phpcs:ignore WordPress.Security.EscapeOutput
		endif;
		?>

		<?php
		$siblings = mfa_place_siblings( $place_id );
		if ( $siblings ) :
			?>
			<section class="mfa-place-section">
				<h2 class="mfa-place-h2">Nearby areas</h2>
				<ul class="mfa-place-siblings">
					<?php foreach ( $siblings as $sibling ) : ?>
						<li><a href="<?php echo esc_url( get_permalink( $sibling->ID ) ); ?>">Mosques in <?php echo esc_html( $sibling->post_title ); ?></a></li>
					<?php endforeach; ?>
				</ul>
			</section>
		<?php endif; ?>

		<?php if ( 0 === $counts['mosque'] && 0 === $counts['business'] && ! $children ) : ?>
			<p class="mfa-place-empty">
				<?php if ( ! $geo['is_root'] && ! mfa_place_has_bbox( $geo ) ) : ?>
					This area has no map boundary yet, so its listings can&rsquo;t be matched. Add one on the place&rsquo;s edit screen.
				<?php else : ?>
					Nothing listed here yet. This area hasn&rsquo;t been indexed.
				<?php endif; ?>
			</p>
		<?php endif; ?>

	</div>

	<?php echo mfa_place_json_ld( $place_id, $mosques['rows'] ); // phpcs:ignore WordPress.Security.EscapeOutput ?>
	<?php
	return ob_get_clean();
}

/**
 * One listing section with its own pagination. Pagination is plain links
 * (?mosque_pg=N / ?business_pg=N - separate per type, see
 * mfa_place_current_page()), not a JS "load more", so every page of results
 * is reachable by a crawler that doesn't run scripts.
 */
function mfa_place_listing_section( $heading, $result, $paged, $type ) {
	if ( ! $result['rows'] ) {
		return '';
	}

	$pages = (int) ceil( $result['total'] / MFA_PLACE_PER_PAGE );

	ob_start();
	?>
	<section class="mfa-place-section">
		<h2 class="mfa-place-h2"><?php echo esc_html( $heading ); ?></h2>
		<ul class="mfa-place-list">
			<?php
			foreach ( $result['rows'] as $row ) :
				$url = mfa_place_listing_url( $row );
				if ( '' === $url ) {
					continue;
				}
				?>
				<li class="mfa-place-item mfa-place-item-<?php echo esc_attr( $type ); ?>">
					<a href="<?php echo esc_url( $url ); ?>">
						<span class="mfa-place-item-name"><?php echo esc_html( $row['name'] ); ?></span>
						<?php if ( ! empty( $row['address'] ) ) : ?>
							<span class="mfa-place-item-addr"><?php echo esc_html( $row['address'] ); ?></span>
						<?php endif; ?>
					</a>
				</li>
			<?php endforeach; ?>
		</ul>

		<?php if ( $pages > 1 ) : ?>
			<nav class="mfa-place-pager" aria-label="<?php echo esc_attr( $heading ); ?> pages">
				<?php if ( $paged > 1 ) : ?>
					<a href="<?php echo esc_url( add_query_arg( $type . '_pg', $paged - 1 ) ); ?>" rel="prev">&larr; Previous</a>
				<?php endif; ?>
				<span>Page <?php echo esc_html( number_format_i18n( $paged ) ); ?> of <?php echo esc_html( number_format_i18n( $pages ) ); ?></span>
				<?php if ( $paged < $pages ) : ?>
					<a href="<?php echo esc_url( add_query_arg( $type . '_pg', $paged + 1 ) ); ?>" rel="next">Next &rarr;</a>
				<?php endif; ?>
			</nav>
		<?php endif; ?>
	</section>
	<?php
	return ob_get_clean();
}

/**
 * BreadcrumbList + ItemList for the hub. Written by hand rather than through
 * Rank Math's schema UI deliberately - the site's existing LocalBusiness
 * schema has unreplaced merge tags shipping on live pages, which is the
 * failure mode that comes from configuring schema in a plugin UI instead of
 * emitting it from the data.
 */
function mfa_place_json_ld( $place_id, $mosque_rows ) {
	$crumbs    = array();
	$position  = 1;
	$ancestors = array_reverse( get_post_ancestors( $place_id ) );

	foreach ( array_merge( $ancestors, array( $place_id ) ) as $node_id ) {
		$crumbs[] = array(
			'@type'    => 'ListItem',
			'position' => $position++,
			'name'     => wp_strip_all_tags( get_the_title( $node_id ) ),
			'item'     => get_permalink( $node_id ),
		);
	}

	$items = array();
	$i     = 1;
	foreach ( $mosque_rows as $row ) {
		$url = mfa_place_listing_url( $row );
		if ( '' === $url ) {
			continue;
		}
		$items[] = array(
			'@type'    => 'ListItem',
			'position' => $i++,
			'name'     => wp_strip_all_tags( $row['name'] ),
			'url'      => $url,
		);
	}

	$graph = array(
		array(
			'@type'           => 'BreadcrumbList',
			'itemListElement' => $crumbs,
		),
	);

	if ( $items ) {
		$graph[] = array(
			'@type'           => 'ItemList',
			'name'            => 'Mosques in ' . wp_strip_all_tags( get_the_title( $place_id ) ),
			'itemListElement' => $items,
		);
	}

	return '<script type="application/ld+json">'
		. wp_json_encode( array( '@context' => 'https://schema.org', '@graph' => $graph ) )
		. '</script>';
}
