<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * [mfa_homepage_stats] - live directory counts
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
