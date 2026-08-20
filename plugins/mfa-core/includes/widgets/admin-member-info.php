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

						<?php
						// Directly under the name: these are what the page is FOR, and
						// they used to sit below the details, the milestones and the
						// CRM panel, so on a member with a full record they were
						// several screens down.
						if ( function_exists( 'mfa_admin_member_actions_render' ) ) {
							echo mfa_admin_member_actions_render( $row, $user_id ); // phpcs:ignore WordPress.Security.EscapeOutput
						}
						?>

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
								<span class="mfa-label">Country</span>
								<span class="mfa-body"><?php echo esc_html( ! empty( $row['country'] ) ? $row['country'] : '—' ); ?></span>
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
							<?php
							// Above Registered on purpose: "when did we last speak"
							// is the first thing the helpline needs.
							$last = function_exists( 'mfa_member_last_contact' ) ? mfa_member_last_contact( $user_id ) : null;
							?>
							<div class="mfa-admin-member-info-item">
								<span class="mfa-label">Last contact</span>
								<span class="mfa-body">
									<?php if ( $last ) : ?>
										<?php echo esc_html( date_i18n( 'j M Y, g:i a', strtotime( $last['at'] ) ) ); ?>
										<small class="mfa-admin-member-ago"><?php echo esc_html( mfa_member_time_ago( $last['at'] ) ); ?></small>
										<small class="mfa-admin-member-via">via <?php echo esc_html( $last['channel'] ); ?></small>
										<?php
										// A prospect often messages Sofia before registering,
										// and activation resets user_registered - so a contact
										// older than the join date is normal, not a data fault.
										if ( ! empty( $row['cct_created'] ) && strtotime( $last['at'] ) < strtotime( $row['cct_created'] ) ) :
											?>
											<small class="mfa-admin-member-ago">before they registered</small>
										<?php endif; ?>
									<?php else : ?>
										<span class="mfa-admin-member-none">No contact recorded</span>
									<?php endif; ?>
								</span>
							</div>
							<div class="mfa-admin-member-info-item">
								<span class="mfa-label">Registered</span>
								<span class="mfa-body"><?php echo esc_html( $row['cct_created'] ? date_i18n( 'j M Y, g:i a', strtotime( $row['cct_created'] ) ) : '—' ); ?></span>
							</div>
							<div class="mfa-admin-member-info-item">
								<span class="mfa-label">Barakah points</span>
								<span class="mfa-body"><?php echo esc_html( number_format_i18n( function_exists( 'mfa_get_barakah_points' ) ? mfa_get_barakah_points( $user_id ) : 0 ) ); ?></span>
							</div>
							<div class="mfa-admin-member-info-item">
								<span class="mfa-label">Affiliate downline</span>
								<span class="mfa-body"><?php echo esc_html( number_format_i18n( function_exists( 'mfa_member_downline_count' ) ? mfa_member_downline_count( $user_id ) : 0 ) ); ?></span>
							</div>
						</div>

						<?php
						// Only for the members this is actually a problem for. The
						// link is the one way to reach somebody the platform cannot
						// message at all - see mfa_member_email_capture_link().
						// Tested on the WORDPRESS address, not the CCT one. Everything
						// that sends reads wp_users, and the two disagree: a member can
						// have a real address in the CCT while the account still carries
						// the placeholder, which is exactly the case this panel is for.
						$account_user = get_userdata( $user_id );
						$needs_email  = $account_user && function_exists( 'mfa_is_placeholder_email' ) && mfa_is_placeholder_email( $account_user->user_email );
						$capture_link = ( $needs_email && function_exists( 'mfa_member_email_capture_link' ) ) ? mfa_member_email_capture_link( $user_id ) : '';
						$unused_email = ( $needs_email && function_exists( 'mfa_member_unused_cct_email' ) ) ? mfa_member_unused_cct_email( $user_id ) : '';
						if ( $needs_email ) :
							?>
							<div class="mfa-admin-member-needs-email">
								<h2 class="mfa-label">No email address</h2>
								<p class="mfa-admin-member-crm-note">
									The account address is <strong><?php echo esc_html( $account_user->user_email ); ?></strong>,
									a placeholder &mdash; nothing can be emailed to it, and they have no WhatsApp
									thread we can reply into either.
								</p>

								<?php if ( $unused_email ) : ?>
									<p class="mfa-admin-member-crm-note">
										Their member record holds <strong><?php echo esc_html( $unused_email ); ?></strong>,
										which was never copied onto the account. It has not been confirmed against
										this account, so it is shown rather than used &mdash; verify it with them first.
									</p>
								<?php endif; ?>

								<?php if ( $capture_link ) : ?>
									<p class="mfa-admin-member-crm-note">Send them this link from your own phone. Tapping it opens a chat with Sofia, who asks for their email.</p>
									<input type="text" class="mfa-admin-member-capture-link" readonly value="<?php echo esc_attr( $capture_link ); ?>" onclick="this.select();">
									<a class="mfa-btn mfa-btn-secondary mfa-dash-btn-sm" href="<?php echo esc_url( $capture_link ); ?>" target="_blank" rel="noopener">Open in WhatsApp</a>
								<?php else : ?>
									<p class="mfa-admin-member-crm-note">
										No usable phone number either &mdash; a national-format number (leading 0)
										cannot be turned into a wa.me link without guessing the country.
									</p>
								<?php endif; ?>
							</div>
						<?php endif; ?>

						<?php
						$milestones = function_exists( 'mfa_member_milestones' ) ? mfa_member_milestones( $user_id ) : array();
						if ( $milestones ) :
							$done = count( array_filter( $milestones ) );
							?>
							<div class="mfa-admin-member-milestones">
								<h2 class="mfa-label">Status
									<span class="mfa-admin-tab-count"><?php echo esc_html( $done . '/' . count( $milestones ) ); ?></span>
								</h2>
								<ul>
									<?php foreach ( $milestones as $label => $has ) : ?>
										<li class="<?php echo $has ? 'is-done' : 'is-todo'; ?>">
											<span aria-hidden="true"><?php echo $has ? '&#10003;' : '&#183;'; ?></span>
											<?php echo esc_html( $label ); ?>
										</li>
									<?php endforeach; ?>
								</ul>
							</div>
						<?php endif; ?>

						<?php
						// Shown before the send buttons on purpose: whoever is about to
						// message this member should see what automation already has
						// them first.
						$crm = function_exists( 'mfa_member_crm_profile' ) ? mfa_member_crm_profile( $user_id ) : null;
						if ( $crm ) :
							?>
							<div class="mfa-admin-member-crm">
								<h2 class="mfa-label">FluentCRM</h2>
								<?php if ( 'found' === $crm['state'] ) : ?>
									<p class="mfa-admin-member-crm-status">
										<?php echo esc_html( ucfirst( $crm['status'] ) ); ?><?php
										if ( $crm['type'] ) {
											echo ' &middot; ' . esc_html( ucfirst( $crm['type'] ) );
										}
										?>
									</p>
									<?php if ( $crm['tags'] ) : ?>
										<div class="mfa-admin-member-crm-tags">
											<?php foreach ( $crm['tags'] as $tag ) : ?>
												<span class="mfa-admin-check-badge is-ok"><?php echo esc_html( $tag ); ?></span>
											<?php endforeach; ?>
										</div>
									<?php else : ?>
										<p class="mfa-admin-member-crm-note">In the CRM, but no tags &mdash; no automation is keyed to them.</p>
									<?php endif; ?>
									<?php if ( $crm['lists'] ) : ?>
										<p class="mfa-admin-member-crm-note">Lists: <?php echo esc_html( implode( ', ', $crm['lists'] ) ); ?></p>
									<?php endif; ?>
								<?php elseif ( 'none_possible' === $crm['state'] ) : ?>
									<?php // Not the same as "not in the CRM" - it cannot be, until a real address exists. ?>
									<p class="mfa-admin-member-crm-note mfa-admin-member-crm-blocked"><?php echo esc_html( $crm['reason'] ); ?></p>
								<?php else : ?>
									<p class="mfa-admin-member-crm-note"><?php echo esc_html( $crm['reason'] ); ?></p>
								<?php endif; ?>
							</div>
						<?php endif; ?>

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
								<?php
								// Built from the types this member actually has, not a
								// hardcoded list - the same reasoning as the country and
								// rank filters on the list page. A new activity type
								// starts being filterable the first time it is recorded,
								// with no edit here.
								$activity_types = array();
								foreach ( $activity as $entry ) {
									$activity_types[ $entry['type'] ] = ucwords( str_replace( '_', ' ', $entry['type'] ) );
								}
								asort( $activity_types );
								?>
								<?php if ( count( $activity_types ) > 1 ) : ?>
									<div class="mfa-admin-activity-filter">
										<label for="mfa-activity-filter-<?php echo esc_attr( $user_id ); ?>">Show</label>
										<select id="mfa-activity-filter-<?php echo esc_attr( $user_id ); ?>" data-activity-filter>
											<option value="">All (<?php echo esc_html( number_format_i18n( count( $activity ) ) ); ?>)</option>
											<?php foreach ( $activity_types as $type => $label ) : ?>
												<option value="<?php echo esc_attr( $type ); ?>"><?php echo esc_html( $label ); ?></option>
											<?php endforeach; ?>
										</select>
									</div>
								<?php endif; ?>
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
													<tr data-activity-type="<?php echo esc_attr( $entry['type'] ); ?>">
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
