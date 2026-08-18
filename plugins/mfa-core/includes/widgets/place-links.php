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
 * The rule is per country. If the listing's country has a hub, show its child
 * hubs (for Malaysia, the 16 states) with live counts. If it does not, fall
 * back to the ad unit, so the slot is never wasted on a country we cannot
 * link into yet. New country hubs light this up automatically - nothing here
 * hardcodes Malaysia.
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

	ob_start();
	?>
	<div class="mfa-place-links">
		<h3 class="mfa-place-links-heading">Mosques in <?php echo esc_html( $hub->post_title ); ?></h3>
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
		<a class="mfa-place-links-all" href="<?php echo esc_url( get_permalink( $hub->ID ) ); ?>">View all of <?php echo esc_html( $hub->post_title ); ?> &rarr;</a>
	</div>
	<?php
	return ob_get_clean();
}

/**
 * Hub links when we have them, the ad unit when we do not.
 *
 * $args: country (string, optional), highlight (string, optional),
 *        ad_count (int), ad_layout (string).
 * When country is omitted it is taken from the current mosque post, falling
 * back to the visitor's country cookie - which is what the directory page
 * needs, since no single listing is in context there. LiteSpeed already has a
 * no-cache rule for that cookie, so varying on it is safe.
 */
function mfa_place_links_or_ads( $args = array() ) {
	$args = wp_parse_args( $args, array(
		'country'   => '',
		'highlight' => '',
		'ad_count'  => 4,
		'ad_layout' => 'vertical',
	) );

	$country   = $args['country'];
	$highlight = $args['highlight'];

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

	$links = mfa_place_links_block( $country, $highlight );
	if ( '' !== $links ) {
		return $links;
	}

	return '<h3 class="mfa-tool-page-ad-heading">Recommended Products/Services</h3>'
		. do_shortcode( '[enaizi_ads count="' . (int) $args['ad_count'] . '" layout="' . sanitize_key( $args['ad_layout'] ) . '"]' );
}

/** [mfa_place_links country="" highlight="" ad_count="4" ad_layout="vertical"] */
add_shortcode( 'mfa_place_links', 'mfa_place_links_shortcode' );
function mfa_place_links_shortcode( $atts ) {
	$atts = shortcode_atts( array(
		'country'   => '',
		'highlight' => '',
		'ad_count'  => 4,
		'ad_layout' => 'vertical',
	), $atts );

	return mfa_place_links_or_ads( $atts );
}
