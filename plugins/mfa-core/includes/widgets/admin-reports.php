<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * [mfa_admin_reports] - the /admin/reports/ page (post 229449), replacing the
 * [mfa_coming_soon] placeholder. Member-growth report: new member registrations
 * (wp_users.user_registered) by month for the current year, rendered as a
 * self-contained CSS bar chart (no chart library, per the no-new-dependency
 * rule) plus a few summary stats. Admin-only.
 */
add_shortcode( 'mfa_admin_reports', 'mfa_admin_reports_shortcode' );
function mfa_admin_reports_shortcode() {
	if ( ! current_user_can( 'manage_options' ) ) {
		return '';
	}

	global $wpdb;
	$year = (int) gmdate( 'Y' );

	$rows = $wpdb->get_results(
		$wpdb->prepare(
			"SELECT MONTH(user_registered) AS m, COUNT(*) AS n FROM {$wpdb->users} WHERE YEAR(user_registered) = %d GROUP BY m",
			$year
		),
		OBJECT_K
	);

	$labels = array( 'Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec' );
	$counts = array();
	for ( $i = 1; $i <= 12; $i++ ) {
		$counts[ $i ] = isset( $rows[ $i ] ) ? (int) $rows[ $i ]->n : 0;
	}

	$max        = max( 1, max( $counts ) );
	$total_year = array_sum( $counts );
	$total_all  = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->users}" );
	$peak_month = (int) array_keys( $counts, max( $counts ) )[0];

	// Cumulative running total, and its polyline points (own scale: 0..total).
	// The SVG uses viewBox 0..100 with preserveAspectRatio="none", so points are
	// percentages; column i's centre is at (i-0.5)/12 of the width.
	$cumulative  = array();
	$running     = 0;
	for ( $i = 1; $i <= 12; $i++ ) {
		$running          += $counts[ $i ];
		$cumulative[ $i ]  = $running;
	}
	$line_points = array();
	for ( $i = 1; $i <= 12; $i++ ) {
		$x             = round( ( $i - 0.5 ) / 12 * 100, 2 );
		$y             = round( ( 1 - ( $cumulative[ $i ] / max( 1, $total_year ) ) ) * 100, 2 );
		$line_points[] = $x . ',' . $y;
	}
	$line_points = implode( ' ', $line_points );

	ob_start();
	?>
	<div class="mfa-report">
		<div class="mfa-report-head">
			<h1 class="mfa-h1">Member Growth</h1>
			<p class="mfa-body-muted">New member registrations by month &mdash; <?php echo esc_html( $year ); ?></p>
		</div>

		<div class="mfa-report-stats">
			<div class="mfa-report-stat">
				<span class="mfa-report-stat-num"><?php echo esc_html( number_format_i18n( $total_all ) ); ?></span>
				<span class="mfa-report-stat-lbl">Total Members</span>
			</div>
			<div class="mfa-report-stat">
				<span class="mfa-report-stat-num"><?php echo esc_html( number_format_i18n( $total_year ) ); ?></span>
				<span class="mfa-report-stat-lbl">Registered in <?php echo esc_html( $year ); ?></span>
			</div>
			<div class="mfa-report-stat">
				<span class="mfa-report-stat-num"><?php echo esc_html( $labels[ $peak_month - 1 ] ); ?></span>
				<span class="mfa-report-stat-lbl">Peak month (<?php echo esc_html( number_format_i18n( $counts[ $peak_month ] ) ); ?>)</span>
			</div>
		</div>

		<div class="mfa-report-chart-card">
			<div class="mfa-report-legend">
				<span class="mfa-report-legend-item"><span class="mfa-report-legend-swatch mfa-report-legend-bar"></span>Monthly registrations</span>
				<span class="mfa-report-legend-item"><span class="mfa-report-legend-swatch mfa-report-legend-line"></span>Cumulative total</span>
			</div>
			<div class="mfa-report-chart" role="img" aria-label="New member registrations by month for <?php echo esc_attr( $year ); ?>, with cumulative total">
				<div class="mfa-report-vals">
					<?php for ( $i = 1; $i <= 12; $i++ ) : ?>
						<span class="mfa-report-bar-val"><?php echo esc_html( number_format_i18n( $counts[ $i ] ) ); ?></span>
					<?php endfor; ?>
				</div>
				<div class="mfa-report-plot">
					<?php for ( $i = 1; $i <= 12; $i++ ) :
						$height = round( $counts[ $i ] / $max * 100, 1 );
						?>
						<div class="mfa-report-bar-col">
							<div class="mfa-report-bar" style="height: <?php echo esc_attr( $height ); ?>%;"></div>
						</div>
					<?php endfor; ?>
					<svg class="mfa-report-line" viewBox="0 0 100 100" preserveAspectRatio="none" aria-hidden="true">
						<polyline points="<?php echo esc_attr( $line_points ); ?>" fill="none" stroke="#f0932b" stroke-width="2.5" stroke-linejoin="round" stroke-linecap="round" vector-effect="non-scaling-stroke"></polyline>
					</svg>
				</div>
				<div class="mfa-report-xaxis">
					<?php for ( $i = 1; $i <= 12; $i++ ) : ?>
						<span class="mfa-report-bar-lbl"><?php echo esc_html( $labels[ $i - 1 ] ); ?></span>
					<?php endfor; ?>
				</div>
			</div>
		</div>
	</div>
	<?php
	return ob_get_clean();
}
