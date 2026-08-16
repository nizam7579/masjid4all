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
 * ~75% holds three tabs - Activity, WhatsApp, Inquiry, in that order
 * (reusing [mfa_place_hub]'s tab pattern - see
 * admin-tabs-v1.js/admin-member-info-v2.css). Activity was its own
 * untabbed section above the other two until 2026-08-17, when it joined
 * them as a third tab per explicit request - reads
 * wp_mfa_member_activity (includes/activity-log.php). WhatsApp reads
 * niz-wa's own wp_nwa_messages, NOT the dead wp_jet_cct_whatsapp table -
 * see the WhatsApp tab note in this project's memory for why. Inquiry
 * reads wp_jet_cct_contact_us (same table as [mfa_admin_inquiry_list])
 * filtered to this member's own submissions via cct_author_id - the
 * column mfa_contact_us_store() (contact-form.php) stamps with
 * get_current_user_id() at submission time, 0 for logged-out/WhatsApp-
 * channel submissions (those won't show here, only ones made while
 * logged in as this member).
 */

add_shortcode( 'mfa_admin_member_info', 'mfa_admin_member_info_shortcode' );
function mfa_admin_member_info_shortcode() {
	if ( function_exists( 'mfa_admin_require_section_access' ) ) {
		$no_access = mfa_admin_require_section_access( 'member' );
		if ( $no_access ) {
			return $no_access;
		}
	}

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
				$activity  = function_exists( 'mfa_get_member_activity' ) ? mfa_get_member_activity( $user_id ) : array();
				$inquiries = mfa_admin_member_info_get_inquiries( $user_id );
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
						<div class="mfa-admin-tabs">
							<div class="mfa-admin-tablist" role="tablist">
								<button type="button" class="mfa-admin-tab is-active" data-tab="activity" role="tab" aria-selected="true">
									Activity <span class="mfa-admin-tab-count"><?php echo esc_html( number_format_i18n( count( $activity ) ) ); ?></span>
								</button>
								<button type="button" class="mfa-admin-tab" data-tab="whatsapp" role="tab" aria-selected="false">
									WhatsApp <span class="mfa-admin-tab-count"><?php echo esc_html( number_format_i18n( count( $wa_messages ) ) ); ?></span>
								</button>
								<button type="button" class="mfa-admin-tab" data-tab="inquiry" role="tab" aria-selected="false">
									Inquiry <span class="mfa-admin-tab-count"><?php echo esc_html( number_format_i18n( count( $inquiries ) ) ); ?></span>
								</button>
							</div>

							<div class="mfa-admin-tabpanel is-active" data-tabpanel="activity">
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

							<div class="mfa-admin-tabpanel" data-tabpanel="whatsapp">
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

							<div class="mfa-admin-tabpanel" data-tabpanel="inquiry">
								<div class="mfa-admin-member-table-wrap">
									<table class="mfa-admin-member-table">
										<thead>
											<tr>
												<th>Subject</th>
												<th>Status</th>
												<th>Date</th>
												<th></th>
											</tr>
										</thead>
										<tbody>
											<?php if ( empty( $inquiries ) ) : ?>
												<tr>
													<td colspan="4" class="mfa-admin-member-empty">No inquiries recorded yet.</td>
												</tr>
											<?php else : ?>
												<?php foreach ( $inquiries as $inquiry ) :
													$view_url = add_query_arg( 'id', (int) $inquiry['_ID'], home_url( '/admin/inquiry/info/' ) );
													?>
													<tr>
														<td data-label="Subject" class="mfa-admin-member-info-wrap-cell"><?php echo esc_html( $inquiry['subject'] ? $inquiry['subject'] : '—' ); ?></td>
														<td data-label="Status">
															<?php if ( ! empty( $inquiry['cct_status'] ) ) : ?>
																<span class="mfa-admin-status-badge mfa-admin-status-<?php echo esc_attr( sanitize_html_class( strtolower( $inquiry['cct_status'] ) ) ); ?>"><?php echo esc_html( $inquiry['cct_status'] ); ?></span>
															<?php else : ?>
																—
															<?php endif; ?>
														</td>
														<td data-label="Date"><?php echo esc_html( $inquiry['cct_created'] ? date_i18n( 'j M Y', strtotime( $inquiry['cct_created'] ) ) : '—' ); ?></td>
														<td data-label=""><a href="<?php echo esc_url( $view_url ); ?>" target="_blank" rel="noopener" class="mfa-btn mfa-btn-solid-dark mfa-admin-inquiry-view-btn">View</a></td>
													</tr>
												<?php endforeach; ?>
											<?php endif; ?>
										</tbody>
									</table>
								</div>
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
 * Contact-Us submissions made by this member while logged in - see this
 * file's top docblock for why cct_author_id (not phone/email matching) is
 * the right key. Same table/columns [mfa_admin_inquiry_list] reads.
 */
function mfa_admin_member_info_get_inquiries( $user_id, $limit = 100 ) {
	global $wpdb;
	$table = $wpdb->prefix . 'jet_cct_contact_us';

	return $wpdb->get_results( $wpdb->prepare(
		"SELECT _ID, subject, cct_status, cct_created FROM {$table} WHERE cct_author_id = %d ORDER BY cct_created DESC LIMIT %d",
		$user_id,
		$limit
	), ARRAY_A );
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
