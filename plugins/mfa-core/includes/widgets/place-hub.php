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
	if ( is_admin() ) {
		return $template;
	}

	// The archive (/places/) uses the same template; the shortcode below
	// renders the country index instead of a hub body.
	if ( ! is_singular( MFA_PLACE_POST_TYPE ) && ! is_post_type_archive( MFA_PLACE_POST_TYPE ) ) {
		return $template;
	}

	$custom = MFA_CORE_PATH . 'templates/place-page.php';

	return file_exists( $custom ) ? $custom : $template;
}

/** Listings per page, per section. Deliberately generous - each one is an
 * internal link, and the whole job of this page is to hand out link equity. */
const MFA_PLACE_PER_PAGE = 24;

/**
 * A page number past the end of a section's list must 404, not render an
 * empty shell at HTTP 200.
 *
 * This matters more since the single-state attribution fix: hubs used to
 * match every listing whose coordinates fell in their bounding box, so
 * Pahang paginated to 139 pages; matching on `state` alone cut it to ~36.
 * Pages 37-139 are still indexed and would otherwise keep answering 200
 * with nothing on them, which is exactly the soft-404 pattern search
 * engines penalise.
 *
 * 404 rather than a redirect to page 1 on purpose: those listings did not
 * move to page 1, they moved to a different state's hub. Redirecting there
 * would point the crawler at a page that does not contain what it asked
 * for, which Google treats as a soft 404 anyway.
 *
 * Page 1 is always valid, even for a hub with no listings at all - an empty
 * hub is a real page that should say so, not a missing one.
 *
 * set_404() alone is enough to hand rendering to the theme's 404.php:
 * mfa_place_template_include() above only claims the template while
 * is_singular() is still true, and set_404() ends that.
 */
add_action( 'template_redirect', 'mfa_place_404_on_out_of_range_page' );
function mfa_place_404_on_out_of_range_page() {
	if ( is_admin() || ! is_singular( MFA_PLACE_POST_TYPE ) ) {
		return;
	}

	$counts = mfa_place_counts( get_queried_object_id() );

	foreach ( array( 'mosque', 'business' ) as $type ) {
		$requested = mfa_place_current_page( $type );
		if ( 1 === $requested ) {
			continue;
		}

		$max = max( 1, (int) ceil( ( isset( $counts[ $type ] ) ? (int) $counts[ $type ] : 0 ) / MFA_PLACE_PER_PAGE ) );
		if ( $requested > $max ) {
			global $wp_query;
			$wp_query->set_404();
			status_header( 404 );
			nocache_headers();

			// The 404 status is authoritative, but the page still rendered
			// Rank Math's normal head - so it advertised index,follow while
			// returning 404. Contradicting yourself is never useful.
			add_filter( 'rank_math/frontend/robots', 'mfa_place_noindex_out_of_range' );

			return;
		}
	}
}

/** @internal Only ever attached from the out-of-range branch above. */
function mfa_place_noindex_out_of_range( $robots ) {
	$robots['index']  = 'noindex';
	$robots['follow'] = 'follow';

	return $robots;
}

/**
 * Point a paginated view's canonical at ITSELF, not at page 1.
 *
 * Pagination is a query arg, which Rank Math does not recognise as pagination
 * the way it does /page/N/ - so every one of the 5,875 paginated URLs across
 * the hubs was emitting `canonical` to the unpaginated hub, i.e. telling
 * Google that page 42 of Jawa Timur is a duplicate of page 1. Google's own
 * guidance is that each page of a series is its own canonical.
 */
add_filter( 'rank_math/frontend/canonical', 'mfa_place_paged_canonical' );
function mfa_place_paged_canonical( $canonical ) {
	if ( ! is_singular( MFA_PLACE_POST_TYPE ) || ! $canonical ) {
		return $canonical;
	}

	$args = array();
	foreach ( array( 'mosque', 'business' ) as $type ) {
		$paged = mfa_place_current_page( $type );
		if ( $paged > 1 ) {
			$args[ $type . '_pg' ] = $paged;
		}
	}

	return $args ? add_query_arg( $args, $canonical ) : $canonical;
}

/**
 * Page numbers to render: first, last, and a window around the current page.
 * A null marks an elided run, drawn as an ellipsis.
 *
 * Previous/Next alone left deep pages unreachable in practice - Indonesia's
 * country hub is 998 pages, so page 500 sat 499 sequential hops from the
 * first, which no crawler walks. A window gives several entry points per
 * view. It does NOT make 998 pages crawlable on its own: the real answer to a
 * list that long is a finer hub (city level), not deeper pagination.
 *
 * @return array<int|null>
 */
function mfa_place_pager_range( $paged, $pages, $span = 2 ) {
	$show = array( 1, $pages );

	for ( $i = $paged - $span; $i <= $paged + $span; $i++ ) {
		if ( $i >= 1 && $i <= $pages ) {
			$show[] = $i;
		}
	}

	$show = array_unique( $show );
	sort( $show );

	$out  = array();
	$prev = 0;
	foreach ( $show as $n ) {
		if ( $prev && $n > $prev + 1 ) {
			// An ellipsis standing in for exactly one page is worse than the
			// page itself - "1 2 3 ... 5" hides nothing and costs a click.
			$out[] = ( $n === $prev + 2 ) ? $prev + 1 : null;
		}
		$out[] = $n;
		$prev  = $n;
	}

	return $out;
}

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

/**
 * /places/ - the countries that have a hub.
 *
 * Only built hubs are listed, never every country that happens to hold
 * listings: the whole point of building them one at a time is that a hub is
 * a page we are willing to promote, and an index full of empty countries
 * would undo that. So this grows as hubs are added, with no edit here.
 */
function mfa_place_index_render() {
	$countries = mfa_place_countries();

	$total = array( 'mosque' => 0, 'business' => 0, 'regions' => 0 );
	$rows  = array();

	foreach ( $countries as $country ) {
		$counts   = mfa_place_counts( $country->ID );
		$children = mfa_place_children( $country->ID );

		$rows[] = array( 'post' => $country, 'counts' => $counts, 'regions' => count( $children ) );

		$total['mosque']   += (int) $counts['mosque'];
		$total['business'] += (int) $counts['business'];
		$total['regions']  += count( $children );
	}

	ob_start();
	?>
	<div class="mfa-shell mfa-stack">

		<header class="mfa-hero mfa-hero--brand mfa-hero--bleed">
			<div class="mfa-hero-inner">
				<h1 class="mfa-hero-title">Browse by place</h1>
				<p class="mfa-hero-tagline">
					<?php echo esc_html( number_format_i18n( $total['mosque'] ) ); ?> mosques
					&middot;
					<?php echo esc_html( number_format_i18n( $total['business'] ) ); ?> halal businesses
					<?php if ( $rows ) : ?>
						&middot;
						<?php echo esc_html( number_format_i18n( count( $rows ) ) ); ?>
						<?php echo esc_html( _n( 'country', 'countries', count( $rows ), 'mfa-core' ) ); ?>
					<?php endif; ?>
				</p>
			</div>
		</header>

		<div class="mfa-place">
			<?php if ( ! $rows ) : ?>
				<p class="mfa-place-empty">No place guides have been published yet.</p>
			<?php else : ?>
				<section class="mfa-place-section">
					<ul class="mfa-place-countries">
						<?php foreach ( $rows as $row ) : ?>
							<li>
								<a href="<?php echo esc_url( get_permalink( $row['post']->ID ) ); ?>" class="mfa-place-country">
									<span class="mfa-place-country-name"><?php echo esc_html( $row['post']->post_title ); ?></span>
									<span class="mfa-place-country-meta">
										<?php if ( $row['regions'] ) : ?>
											<span><?php echo esc_html( number_format_i18n( $row['regions'] ) ); ?> regions</span>
										<?php endif; ?>
										<span><?php echo esc_html( number_format_i18n( $row['counts']['mosque'] ) ); ?> mosques</span>
										<span><?php echo esc_html( number_format_i18n( $row['counts']['business'] ) ); ?> businesses</span>
									</span>
								</a>
							</li>
						<?php endforeach; ?>
					</ul>
				</section>
			<?php endif; ?>
		</div>
	</div>
	<?php
	return ob_get_clean();
}

add_shortcode( 'mfa_place_hub', 'mfa_place_hub_shortcode' );
function mfa_place_hub_shortcode() {
	if ( is_post_type_archive( MFA_PLACE_POST_TYPE ) ) {
		return mfa_place_index_render();
	}

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
	<div class="mfa-shell mfa-stack">

		<header class="mfa-hero mfa-hero--brand mfa-hero--bleed">
			<div class="mfa-hero-inner">
				<h1 class="mfa-hero-title">Mosques &amp; Halal Businesses in <?php echo esc_html( $title ); ?></h1>
				<p class="mfa-hero-tagline">
					<?php echo esc_html( number_format_i18n( $counts['mosque'] ) ); ?> mosques
					&middot;
					<?php echo esc_html( number_format_i18n( $counts['business'] ) ); ?> halal businesses
				</p>
			</div>
		</header>

		<div class="mfa-place">

		<nav class="mfa-place-crumbs" aria-label="Breadcrumb">
			<?php
			// The root crumb exists now that /places/ is a real index page;
			// it was omitted while the post type had no archive, because
			// linking one would have 404'd on every hub page.
			$places_root = get_post_type_archive_link( MFA_PLACE_POST_TYPE );
			if ( $places_root ) :
				?>
				<a href="<?php echo esc_url( $places_root ); ?>">Places</a>
				<span aria-hidden="true">&rsaquo;</span>
			<?php endif; ?>
			<?php
			foreach ( $ancestors as $ancestor_id ) :
				?>
				<a href="<?php echo esc_url( get_permalink( $ancestor_id ) ); ?>"><?php echo esc_html( get_the_title( $ancestor_id ) ); ?></a>
				<span aria-hidden="true">&rsaquo;</span>
			<?php endforeach; ?>
			<span aria-current="page"><?php echo esc_html( $title ); ?></span>
		</nav>

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
								<span class="mfa-place-child-count">
									<span><?php echo esc_html( number_format_i18n( $child_counts['mosque'] ) ); ?> mosques</span>
									<span><?php echo esc_html( number_format_i18n( $child_counts['business'] ) ); ?> businesses</span>
								</span>
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
				<span class="mfa-place-pager-pages">
					<?php foreach ( mfa_place_pager_range( $paged, $pages ) as $n ) : ?>
						<?php if ( null === $n ) : ?>
							<span class="mfa-place-pager-gap" aria-hidden="true">&hellip;</span>
						<?php elseif ( $n === $paged ) : ?>
							<span class="mfa-place-pager-current" aria-current="page"><?php echo esc_html( number_format_i18n( $n ) ); ?></span>
						<?php else : ?>
							<a href="<?php echo esc_url( add_query_arg( $type . '_pg', $n ) ); ?>"><?php echo esc_html( number_format_i18n( $n ) ); ?></a>
						<?php endif; ?>
					<?php endforeach; ?>
				</span>
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
