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
			<div class="mfa-report-chart" role="img" aria-label="New member registrations by month for <?php echo esc_attr( $year ); ?>">
				<?php for ( $i = 1; $i <= 12; $i++ ) :
					$height = round( $counts[ $i ] / $max * 100, 1 );
					?>
					<div class="mfa-report-bar-col">
						<span class="mfa-report-bar-val"><?php echo esc_html( number_format_i18n( $counts[ $i ] ) ); ?></span>
						<div class="mfa-report-bar-track">
							<div class="mfa-report-bar" style="height: <?php echo esc_attr( $height ); ?>%;"></div>
						</div>
						<span class="mfa-report-bar-lbl"><?php echo esc_html( $labels[ $i - 1 ] ); ?></span>
					</div>
				<?php endfor; ?>
			</div>
		</div>
	</div>
	<?php
	return ob_get_clean();
}
