<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * [mfa_admin_member_info] - /admin/member/info/?id={user_id}, linked from
 * the "View" button in [mfa_admin_member_list] (admin-member-list.php).
 * Deliberately minimal for now (basic read-only fields) - more detail and
 * actions to be added later. `id` is the WP user_id, not the CCT `_ID`,
 * matching how the rest of the codebase keys on user_id (niz_user_field_by_*,
 * wp_delete_user, etc). Reads wp_jet_cct_member directly via $wpdb, never
 * the JetEngine PHP API, per the project's standing rule for this CCT.
 */

add_shortcode( 'mfa_admin_member_info', 'mfa_admin_member_info_shortcode' );
function mfa_admin_member_info_shortcode() {
	global $wpdb;
	$cct_table = $wpdb->prefix . 'jet_cct_member';

	$user_id = isset( $_GET['id'] ) ? absint( $_GET['id'] ) : 0;

	ob_start();
	?>
	<div class="mfa-admin-member-info">
		<a href="<?php echo esc_url( home_url( '/admin/member/' ) ); ?>" class="mfa-admin-member-info-back">&larr; Back to Members</a>

		<?php
		if ( ! $user_id ) {
			echo '<p class="mfa-body-muted">No member specified.</p>';
		} else {
			$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$cct_table} WHERE user_id = %d", $user_id ), ARRAY_A );

			if ( ! $row ) {
				echo '<p class="mfa-body-muted">Member not found.</p>';
			} else {
				?>
				<h1 class="mfa-h2"><?php echo esc_html( $row['name'] ? $row['name'] : '—' ); ?></h1>

				<div class="mfa-admin-member-info-grid">
					<div class="mfa-admin-member-info-item">
						<span class="mfa-label">Email</span>
						<span class="mfa-body"><?php echo esc_html( $row['email'] ? $row['email'] : '—' ); ?></span>
					</div>
					<div class="mfa-admin-member-info-item">
						<span class="mfa-label">WhatsApp / Phone</span>
						<span class="mfa-body"><?php echo esc_html( $row['phone'] ? $row['phone'] : '—' ); ?></span>
					</div>
					<div class="mfa-admin-member-info-item">
						<span class="mfa-label">Status</span>
						<span class="mfa-body">
							<?php if ( ! empty( $row['status'] ) ) : ?>
								<span class="mfa-admin-status-badge mfa-admin-status-<?php echo esc_attr( sanitize_html_class( strtolower( str_replace( ' ', '-', $row['status'] ) ) ) ); ?>"><?php echo esc_html( $row['status'] ); ?></span>
							<?php else : ?>
								—
							<?php endif; ?>
						</span>
					</div>
					<div class="mfa-admin-member-info-item">
						<span class="mfa-label">Rank</span>
						<span class="mfa-body"><?php echo esc_html( trim( (string) $row['rank'] ) ? trim( (string) $row['rank'] ) : '—' ); ?></span>
					</div>
					<div class="mfa-admin-member-info-item">
						<span class="mfa-label">Registered</span>
						<span class="mfa-body"><?php echo esc_html( $row['cct_created'] ? date_i18n( 'j M Y, g:i a', strtotime( $row['cct_created'] ) ) : '—' ); ?></span>
					</div>
				</div>

				<h2 class="mfa-h3 mfa-admin-member-info-activity-heading">Recent Activity</h2>
				<?php
				$activity = function_exists( 'mfa_get_member_activity' ) ? mfa_get_member_activity( $user_id ) : array();

				if ( empty( $activity ) ) {
					echo '<p class="mfa-body-muted">No activity recorded yet.</p>';
				} else {
					?>
					<ul class="mfa-admin-member-info-activity">
						<?php foreach ( $activity as $entry ) : ?>
							<li class="mfa-admin-member-info-activity-item">
								<span class="mfa-admin-activity-type mfa-admin-activity-type-<?php echo esc_attr( $entry['type'] ); ?>"><?php echo esc_html( ucwords( str_replace( '_', ' ', $entry['type'] ) ) ); ?></span>
								<span class="mfa-body"><?php echo esc_html( $entry['description'] ); ?></span>
								<span class="mfa-admin-member-info-activity-time"><?php echo esc_html( date_i18n( 'j M Y, g:i a', strtotime( $entry['created_at'] ) ) ); ?></span>
							</li>
						<?php endforeach; ?>
					</ul>
					<?php
				}
				?>
				<?php
			}
		}
		?>
	</div>
	<?php
	return ob_get_clean();
}
