<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Keeps not-yet-written directory pages out of the search index.
 *
 * The crawler publishes a page per mosque/business/website as soon as it finds
 * one, carrying only boilerplate - a heading, an address, and a line saying the
 * entry will be reviewed soon. Real content arrives later, one page at a time,
 * from the Perplexity generator. That left ~202,000 URLs in the sitemap of
 * which only a couple of thousand had anything to read:
 *
 *   masjid   123,262 published, 118,990 under 400 characters (avg 244)
 *   business  78,786 published,  73,164 under 400 characters
 *   web       24,959 published,  23,845 under 400 characters
 *
 * At that ratio the useful pages are competing against a hundred thousand
 * near-identical siblings, and the whole site reads as mass-produced. So a page
 * is only offered to search engines once it actually has content.
 *
 * Deliberately computed at runtime rather than written to rank_math_robots
 * meta on 200,000 rows: the moment the generator fills a page in, that page
 * becomes indexable by itself, with no migration to re-run and no risk of the
 * stored flag drifting away from the content it describes.
 */

/**
 * The crawler-populated post types. Pages, knowledge posts, blog posts and the
 * /places/ hubs are hand-made and never subject to this.
 */
function mfa_seo_thin_post_types() {
	return array( 'masjid', 'business', 'web' );
}

/**
 * Minimum visible characters before a directory page is worth indexing.
 *
 * Boilerplate runs to roughly 100 visible characters; a generated page runs to
 * well over a thousand. 500 sits in the empty space between the two, so the
 * exact value is not load-bearing.
 */
function mfa_seo_min_content_chars() {
	return (int) apply_filters( 'mfa_seo_min_content_chars', 500 );
}

/**
 * Phrases the crawler writes when it has nothing to say yet. A page carrying
 * one of these is boilerplate no matter how long the address pushes it.
 */
function mfa_seo_placeholder_phrases() {
	return array(
		'We will review and update this',
		'will be updated soon',
	);
}

/**
 * Is this directory page still boilerplate?
 *
 * @param WP_Post|int|null $post
 * @return bool False for anything that is not a crawler-populated page.
 */
function mfa_seo_is_thin_post( $post = null ) {
	$post = get_post( $post );

	if ( ! $post instanceof WP_Post ) {
		return false;
	}

	if ( ! in_array( $post->post_type, mfa_seo_thin_post_types(), true ) ) {
		return false;
	}

	$content = (string) $post->post_content;

	foreach ( mfa_seo_placeholder_phrases() as $phrase ) {
		if ( false !== stripos( $content, $phrase ) ) {
			return true;
		}
	}

	// Measure what a reader would see, not the markup: the boilerplate is
	// mostly tags and <br>-separated address lines.
	$visible = trim( wp_strip_all_tags( strip_shortcodes( $content ) ) );
	$visible = preg_replace( '/\s+/u', ' ', $visible );

	return mb_strlen( $visible ) < mfa_seo_min_content_chars();
}

/**
 * Force noindex on the rendered page. follow is kept deliberately: the links
 * out to the /places/ hubs and neighbouring listings should still be crawled,
 * it is only this page that is not worth listing yet.
 */
add_filter( 'rank_math/frontend/robots', 'mfa_seo_robots_for_thin_pages' );
function mfa_seo_robots_for_thin_pages( $robots ) {
	if ( ! is_singular( mfa_seo_thin_post_types() ) ) {
		return $robots;
	}

	if ( ! mfa_seo_is_thin_post( get_queried_object() ) ) {
		return $robots;
	}

	$robots['index']  = 'noindex';
	$robots['follow'] = 'follow';

	return $robots;
}

/**
 * Drop the same pages from the XML sitemap. Without this they would still be
 * submitted for crawling and then found to be noindex - wasted crawl budget on
 * a site with six figures of URLs.
 */
add_filter( 'rank_math/sitemap/entry', 'mfa_seo_sitemap_skip_thin_pages', 10, 3 );
function mfa_seo_sitemap_skip_thin_pages( $entry, $type, $object ) {
	if ( 'post' !== $type || ! isset( $object->post_type ) ) {
		return $entry;
	}

	if ( ! in_array( $object->post_type, mfa_seo_thin_post_types(), true ) ) {
		return $entry;
	}

	return mfa_seo_is_thin_post( $object ) ? false : $entry;
}

/**
 * Exclude thin pages from the sitemap's own SQL, not just from each rendered
 * entry.
 *
 * The entry filter above is the authoritative check, but RankMath paginates
 * from a COUNT(*) taken before any entry is filtered - so with 123,000 mostly
 * boilerplate posts it published 124 sitemap files that then rendered about
 * seven URLs each. Applying the same restriction to the count and fetch
 * queries collapses that to the two or three files the real content warrants.
 *
 * SQL can only see the raw content length, where the runtime check measures
 * visible text. The two agree comfortably in practice - boilerplate is 145-340
 * characters of markup, a generated page over 1,700 - and anything that slips
 * through is still caught by the entry filter.
 */
add_filter( 'rank_math/sitemap/post_count/where', 'mfa_seo_sitemap_where_skip_thin', 10, 2 );
add_filter( 'rank_math/sitemap/get_posts/where', 'mfa_seo_sitemap_where_skip_thin', 10, 2 );
function mfa_seo_sitemap_where_skip_thin( $where, $post_types ) {
	$types = is_array( $post_types ) ? $post_types : array( $post_types );

	if ( ! array_intersect( $types, mfa_seo_thin_post_types() ) ) {
		return $where;
	}

	global $wpdb;

	$where .= $wpdb->prepare(
		' AND CHAR_LENGTH( p.post_content ) >= %d AND p.post_content NOT LIKE %s ',
		mfa_seo_min_content_chars() * 2,
		'%We will review and update this%'
	);

	return $where;
}

/**
 * Never announce a page to IndexNow that we are telling search engines not to
 * index.
 *
 * Rank Math's auto-submit already guards with Helper::is_post_indexable(), but
 * that reads STORED `rank_math_robots` meta - and this file's noindex is
 * computed at render time on purpose (see the docblock at the top), so Rank
 * Math cannot see it and returns true for a boilerplate page. Verified on
 * production: a thin business post reports our mfa_seo_is_thin_post() = true
 * and Rank Math's is_post_indexable() = true at the same time.
 *
 * The cost of leaving it: of 102,447 published business pages only 245 are
 * indexable, so 99.8% of the submissions were announcing pages that carry
 * noindex - and the crawler fires one on every save.
 *
 * Returning an empty URL makes Instant Indexing skip the post entirely.
 *
 * @param string  $url  URL Rank Math intends to submit.
 * @param WP_Post $post
 */
add_filter( 'rank_math/instant_indexing/publish_url', 'mfa_seo_skip_thin_instant_indexing', 10, 2 );
function mfa_seo_skip_thin_instant_indexing( $url, $post ) {
	$post_id = is_object( $post ) ? $post->ID : (int) $post;

	if ( ! $post_id ) {
		return $url;
	}

	return mfa_seo_is_thin_post( $post_id ) ? '' : $url;
}

/**
 * Counts for the admin, so the effect of the generator is visible: as pages get
 * written they leave the "hidden" column and enter the sitemap on their own.
 *
 * Cached - these are CHAR_LENGTH scans over six-figure tables.
 *
 * @return array post_type => array( total, indexable, hidden )
 */
function mfa_seo_index_counts( $refresh = false ) {
	$counts = $refresh ? false : get_transient( 'mfa_seo_index_counts' );

	if ( is_array( $counts ) ) {
		return $counts;
	}

	global $wpdb;
	$counts = array();
	$min    = mfa_seo_min_content_chars();

	foreach ( mfa_seo_thin_post_types() as $type ) {
		// Approximated in SQL with raw content length rather than the visible
		// count used at render time - close enough for a status figure, and it
		// avoids loading 123,000 posts into PHP to draw one table.
		$total = (int) $wpdb->get_var(
			$wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type = %s AND post_status = 'publish'", $type )
		);
		$thin  = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$wpdb->posts}
				 WHERE post_type = %s AND post_status = 'publish'
				   AND ( CHAR_LENGTH( post_content ) < %d OR post_content LIKE %s )",
				$type,
				$min * 2,
				'%We will review and update this%'
			)
		);

		$counts[ $type ] = array(
			'total'     => $total,
			'hidden'    => $thin,
			'indexable' => max( 0, $total - $thin ),
		);
	}

	set_transient( 'mfa_seo_index_counts', $counts, 6 * HOUR_IN_SECONDS );

	return $counts;
}
