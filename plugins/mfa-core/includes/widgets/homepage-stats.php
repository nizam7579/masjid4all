<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * [mfa_homepage_stats] - live directory counts, cached 6h so the homepage
 * doesn't run 3 COUNT queries on every uncached pageview.
 */
add_shortcode( 'mfa_homepage_stats', 'mfa_homepage_stats_shortcode' );
function mfa_homepage_stats_shortcode() {
	$stats = get_transient( 'mfa_homepage_stats' );

	if ( false === $stats ) {
		$stats = array(
			'masjid'   => (int) wp_count_posts( 'masjid' )->publish,
			'business' => (int) wp_count_posts( 'business' )->publish,
			'web'      => (int) wp_count_posts( 'web' )->publish,
		);
		set_transient( 'mfa_homepage_stats', $stats, 6 * HOUR_IN_SECONDS );
	}

	$format_count = function ( $n ) {
		if ( $n >= 1000 ) {
			return floor( $n / 1000 ) . ',000+';
		}
		return (string) $n;
	};

	$items = array(
		array( 'value' => $format_count( $stats['masjid'] ), 'label' => 'Mosques Listed' ),
		array( 'value' => $format_count( $stats['business'] ), 'label' => 'Halal Businesses' ),
		array( 'value' => $format_count( $stats['web'] ), 'label' => 'Trusted Websites' ),
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