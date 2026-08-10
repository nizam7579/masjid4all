<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * [mfa_admin_member_info] - /admin/member/info/?id={user_id}, linked from
 * the "View" button in [mfa_admin_member_list] (admin-member-list.php).
 * `id` is the WP user_id, not the CCT `_ID`, matching how the rest of the
 * codebase keys on user_id (niz_user_field_by_*, wp_delete_user, etc).
 * Reads wp_jet_cct_member directly via $wpdb, never the JetEngine PHP API,
 * per the project's standing rule for this CCT.
 *
 * Two-column layout (2026-08-10): left ~25% is the member's own info, right
 * ~75% holds two read-only tables - Activity (wp_mfa_member_activity, see
 * includes/activity-log.php) and WhatsApp (niz-wa's own wp_nwa_messages,
 * NOT the dead wp_jet_cct_whatsapp table - see the WhatsApp tab note in
 * this project's memory for why).
 */

add_shortcode( 'mfa_admin_member_info', 'mfa_admin_member_info_shortcode' );
function mfa_admin_member_info_shortcode() {
	global $wpdb;
	$cct_table = $wpdb->prefix . 'jet_cct_member';

	$user_id = isset( $_GET['id'] ) ? absint( $_GET['id'] ) : 0;

	ob_start();
	?>
	<div class="mfa-admin-member-info">
		<?php
		if ( ! $user_id ) {
			echo '<p class="mfa-body-muted">No member specified.</p>';
		} else {
			$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$cct_table} WHERE user_id = %d", $user_id ), ARRAY_A );

			if ( ! $row ) {
				echo '<p class="mfa-body-muted">Member not found.</p>';
			} else {
				$activity = function_exists( 'mfa_get_member_activity' ) ? mfa_get_member_activity( $user_id ) : array();
				$wa_messages = mfa_admin_member_info_get_whatsapp_messages( $user_id );
				?>
				<div class="mfa-admin-member-info-layout">
					<div class="mfa-admin-member-info-col-left">
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
					</div>

					<div class="mfa-admin-member-info-col-right">
						<div class="mfa-admin-member-info-section">
							<h2 class="mfa-h3">Activity</h2>
							<div class="mfa-admin-member-table-wrap">
								<table class="mfa-admin-member-table">
									<thead>
										<tr>
											<th>Type</th>
											<th>Description</th>
											<th>Time</th>
										</tr>
									</thead>
									<tbody>
										<?php if ( empty( $activity ) ) : ?>
											<tr>
												<td colspan="3" class="mfa-admin-member-empty">No activity recorded yet.</td>
											</tr>
										<?php else : ?>
											<?php foreach ( $activity as $entry ) : ?>
												<tr>
													<td data-label="Type"><span class="mfa-admin-activity-type mfa-admin-activity-type-<?php echo esc_attr( $entry['type'] ); ?>"><?php echo esc_html( ucwords( str_replace( '_', ' ', $entry['type'] ) ) ); ?></span></td>
													<td data-label="Description" class="mfa-admin-member-info-wrap-cell"><?php echo esc_html( $entry['description'] ); ?></td>
													<td data-label="Time"><?php echo esc_html( date_i18n( 'j M Y, g:i a', strtotime( $entry['created_at'] ) ) ); ?></td>
												</tr>
											<?php endforeach; ?>
										<?php endif; ?>
									</tbody>
								</table>
							</div>
						</div>

						<div class="mfa-admin-member-info-section">
							<h2 class="mfa-h3">WhatsApp</h2>
							<div class="mfa-admin-member-table-wrap">
								<table class="mfa-admin-member-table">
									<thead>
										<tr>
											<th>Direction</th>
											<th>Message</th>
											<th>Time</th>
										</tr>
									</thead>
									<tbody>
										<?php if ( empty( $wa_messages ) ) : ?>
											<tr>
												<td colspan="3" class="mfa-admin-member-empty">No WhatsApp messages recorded yet.</td>
											</tr>
										<?php else : ?>
											<?php foreach ( $wa_messages as $message ) : ?>
												<tr>
													<td data-label="Direction"><span class="mfa-admin-activity-type mfa-admin-activity-type-<?php echo esc_attr( $message['direction'] ); ?>"><?php echo esc_html( ucfirst( $message['direction'] ) ); ?></span></td>
													<td data-label="Message" class="mfa-admin-member-info-wrap-cell"><?php echo esc_html( $message['content'] ? $message['content'] : '[' . $message['msg_type'] . ']' ); ?></td>
													<td data-label="Time"><?php echo esc_html( date_i18n( 'j M Y, g:i a', strtotime( $message['created_at'] ) ) ); ?></td>
												</tr>
											<?php endforeach; ?>
										<?php endif; ?>
									</tbody>
								</table>
							</div>
						</div>
					</div>
				</div>
				<?php
			}
		}
		?>
	</div>
	<?php
	return ob_get_clean();
}

/**
 * Live data is niz-wa's own wp_nwa_messages table, not the dead
 * wp_jet_cct_whatsapp CCT (zero rows since the 2026-08-04 niz-wa cutover).
 * Guarded on NWA_DB existing since niz-wa is a separate plugin - this page
 * should degrade to an empty table, not fatal, if niz-wa is ever inactive.
 */
function mfa_admin_member_info_get_whatsapp_messages( $user_id, $limit = 100 ) {
	if ( ! class_exists( 'NWA_DB' ) ) {
		return array();
	}

	global $wpdb;
	$table = NWA_DB::messages_table();

	return $wpdb->get_results( $wpdb->prepare(
		"SELECT direction, msg_type, content, created_at FROM {$table} WHERE user_id = %d ORDER BY created_at DESC LIMIT %d",
		$user_id,
		$limit
	), ARRAY_A );
}
