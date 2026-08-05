<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * [mfa_homepage_stats] and [mfa_impact_feature] - live directory counts
 * pulled from the JetEngine CCT tables (the actual directory data store;
 * the masjid/business/web post types are not what the directory listing
 * UI writes to), cached 6h so the homepage doesn't run raw COUNT(*)
 * queries against multi-hundred-thousand-row tables on every pageview.
 */
function mfa_homepage_live_counts() {
	$counts = get_transient( 'mfa_homepage_cct_counts' );

	if ( false === $counts ) {
		global $wpdb;
		$counts = array(
			'mosque'   => (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->prefix}jet_cct_mosque" ),
			'business' => (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->prefix}jet_cct_business" ),
			'web'      => (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->prefix}jet_cct_web" ),
		);
		set_transient( 'mfa_homepage_cct_counts', $counts, 6 * HOUR_IN_SECONDS );
	}

	return $counts;
}

function mfa_format_count_rounded( $n ) {
	if ( $n >= 1000 ) {
		return number_format( floor( $n / 1000 ) * 1000 ) . '+';
	}
	return (string) $n;
}

add_shortcode( 'mfa_homepage_stats', 'mfa_homepage_stats_shortcode' );
function mfa_homepage_stats_shortcode() {
	$counts = mfa_homepage_live_counts();

	$items = array(
		array( 'value' => mfa_format_count_rounded( $counts['mosque'] ), 'label' => 'Mosques Listed' ),
		array( 'value' => mfa_format_count_rounded( $counts['business'] ), 'label' => 'Halal Businesses' ),
		array( 'value' => mfa_format_count_rounded( $counts['web'] ), 'label' => 'Trusted Websites' ),
		array( 'value' => '1,000,000', 'label' => 'Mosque Directory Goal' ),
	);

	ob_start();
	?>
	<div class="mfa-stats-strip">
		<?php foreach ( $items as $item ) : ?>
			<div class="mfa-stat-item">
				<span class="mfa-stat-value"><?php echo esc_html( $item['value'] ); ?></span>
				<span class="mfa-stat-label"><?php echo esc_html( $item['label'] ); ?></span>
			</div>
		<?php endforeach; ?>
	</div>
	<?php
	return ob_get_clean();
}

add_shortcode( 'mfa_impact_feature', 'mfa_impact_feature_shortcode' );
function mfa_impact_feature_shortcode() {
	$counts    = mfa_homepage_live_counts();
	$listed    = $counts['mosque'];
	$remaining = max( 0, 1000000 - $listed );
	// Round down to a clean thousand so the "to go" figure doesn't imply
	// false precision (e.g. "899,713" reads as fake-precise; "899,000" reads
	// as the honest round number it is).
	$remaining_rounded = floor( $remaining / 1000 ) * 1000;

	ob_start();
	?>
	<span class="mfa-impact-number"><?php echo esc_html( mfa_format_count_rounded( $listed ) ); ?></span>
	<h3>Mosques listed, <?php echo esc_html( number_format( $remaining_rounded ) ); ?> to go</h3>
	<p>Help us build the world's largest mosque directory by adding mosques you know that aren't listed yet.</p>
	<?php
	return ob_get_clean();
}