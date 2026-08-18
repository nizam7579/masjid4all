<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Internal links into the /places/ hubs, so those pages stop being orphans.
 *
 * The hubs carry real SEO value but nothing on the site linked to them, so
 * neither visitors nor crawlers had a route in. This surfaces them where the
 * intent already matches: someone looking at a mosque is plausibly interested
 * in other mosques near it.
 *
 * Rendered as a full-width band beneath the listing content, not in the
 * sidebar - 16 states read badly as a tall narrow column, and the ad unit
 * earns more beside the content than under it. The list is a responsive grid,
 * so it still collapses to a single column on mobile.
 *
 * Country-gated: if the listing's country has a hub, show its child hubs. If
 * not, render nothing at all (the ads in the sidebar are unaffected either
 * way). Nothing here hardcodes Malaysia - adding a country hub lights it up.
 */

/** Top-level hub post for a country name, or null. */
function mfa_place_country_hub( $country ) {
	$country = trim( (string) $country );
	if ( '' === $country ) {
		return null;
	}

	$hub = get_page_by_path( sanitize_title( $country ), OBJECT, 'place' );

	// Must be a country-level hub. A state slug could otherwise match here and
	// we would render a state's (empty) children instead of the country's.
	return ( $hub && 0 === (int) $hub->post_parent ) ? $hub : null;
}

/**
 * The country/state a given mosque post belongs to, read from its CCT row.
 * Returns array( country, state ), either of which may be ''.
 */
function mfa_place_links_context_for_post( $post_id ) {
	global $wpdb;
	$row = $wpdb->get_row( $wpdb->prepare(
		"SELECT country, state FROM {$wpdb->prefix}jet_cct_mosque WHERE cct_single_post_id = %d LIMIT 1",
		(int) $post_id
	) );

	return array(
		$row ? trim( (string) $row->country ) : '',
		$row ? trim( (string) $row->state ) : '',
	);
}

/**
 * The child hubs of a country, ordered with $highlight first and the rest
 * alphabetical. Counts come from mfa_place_counts(), which is transient-cached,
 * so this stays cheap even on a 119K-page template.
 */
function mfa_place_links_block( $country, $highlight = '' ) {
	$hub = mfa_place_country_hub( $country );
	if ( ! $hub ) {
		return '';
	}

	$children = get_children( array(
		'post_parent' => $hub->ID,
		'post_type'   => 'place',
		'post_status' => 'publish',
		'numberposts' => -1,
		'orderby'     => 'title',
		'order'       => 'ASC',
	) );
	if ( ! $children ) {
		return '';
	}

	$items = array();
	foreach ( $children as $child ) {
		$counts = mfa_place_counts( $child->ID );
		$items[] = array(
			'title'   => $child->post_title,
			'url'     => get_permalink( $child->ID ),
			'mosques' => isset( $counts['mosque'] ) ? (int) $counts['mosque'] : 0,
			'is_here' => ( '' !== $highlight && 0 === strcasecmp( $child->post_title, $highlight ) ),
		);
	}

	usort( $items, function ( $a, $b ) {
		if ( $a['is_here'] !== $b['is_here'] ) {
			return $a['is_here'] ? -1 : 1;
		}
		return strcasecmp( $a['title'], $b['title'] );
	} );

	$directory = home_url( '/masjid/' );

	ob_start();
	?>
	<section class="mfa-place-links">
		<h2 class="mfa-place-links-heading">Browse mosques in <?php echo esc_html( $hub->post_title ); ?></h2>
		<p class="mfa-place-links-intro">Pick a state to see every mosque we have listed there, or <a href="<?php echo esc_url( $directory ); ?>">search for the nearest mosque to you</a>.</p>
		<ul class="mfa-place-links-list">
			<?php foreach ( $items as $item ) : ?>
				<li class="mfa-place-links-item<?php echo $item['is_here'] ? ' is-here' : ''; ?>">
					<a href="<?php echo esc_url( $item['url'] ); ?>">
						<span class="mfa-place-links-name"><?php echo esc_html( $item['title'] ); ?></span>
						<span class="mfa-place-links-count"><?php echo esc_html( number_format_i18n( $item['mosques'] ) ); ?></span>
					</a>
				</li>
			<?php endforeach; ?>
		</ul>
		<div class="mfa-place-links-actions">
			<a class="mfa-place-links-all" href="<?php echo esc_url( get_permalink( $hub->ID ) ); ?>">View all of <?php echo esc_html( $hub->post_title ); ?> &rarr;</a>
			<a class="mfa-place-links-near" href="<?php echo esc_url( $directory ); ?>">Search the nearest mosque to you &rarr;</a>
		</div>
	</section>
	<?php
	return ob_get_clean();
}

/**
 * [mfa_place_links country="" highlight=""]
 *
 * With no country given it takes the active mosque's own country and state,
 * falling back to the visitor's country cookie when no single listing is in
 * context (the /masjid/ directory). LiteSpeed already has a no-cache rule for
 * that cookie, so varying on it is safe. Renders nothing when the country has
 * no hub - the caller does not need to handle that case.
 */
add_shortcode( 'mfa_place_links', 'mfa_place_links_shortcode' );
function mfa_place_links_shortcode( $atts ) {
	$atts = shortcode_atts( array(
		'country'   => '',
		'highlight' => '',
	), $atts );

	$country   = $atts['country'];
	$highlight = $atts['highlight'];

	if ( '' === $country ) {
		if ( is_singular( 'masjid' ) ) {
			list( $country, $post_state ) = mfa_place_links_context_for_post( get_the_ID() );
			if ( '' === $highlight ) {
				$highlight = $post_state;
			}
		} elseif ( isset( $_COOKIE['country'] ) ) {
			$country = sanitize_text_field( wp_unslash( $_COOKIE['country'] ) );
		}
	}

	return mfa_place_links_block( $country, $highlight );
}
