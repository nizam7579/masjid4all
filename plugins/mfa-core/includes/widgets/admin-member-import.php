<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The Import Users tools on /admin/member/:
 *
 *   - mfa_admin_member_import_panel()  - the panel on the member list page,
 *     showing progress and the buttons.
 *   - [mfa_admin_member_import]        - the /admin/member/import/ runner page,
 *     which processes one batch per load and advances itself.
 *
 * Administrator-only, tighter than the rest of the member section: Editors and
 * Helpline staff legitimately manage individual members here, but this creates
 * WordPress users in bulk and is not something to leave one mis-click away.
 */
function mfa_admin_member_tools_allowed() {
	return current_user_can( 'administrator' );
}

/**
 * Where the runner page lives. Kept in one place so the panel button and the
 * page's own self-advancing redirect cannot drift apart.
 */
function mfa_admin_member_import_url() {
	return home_url( '/admin/member/import/' );
}

/**
 * Rows per batch. Modest on purpose: each import creates WordPress users, and
 * wp_insert_user() hashes a password every time, so a larger batch buys
 * nothing but a longer wait per page and a bigger loss if the tab is closed
 * mid-batch.
 */
function mfa_admin_member_import_batch_size() {
	return 200;
}

/**
 * Handles the panel's buttons. Dry run classifies the next batch without
 * writing anything; Full rescan clears the cursors so the next run re-reads
 * every table from the start.
 *
 * @return array|null Report to render, or null when nothing was submitted.
 */
function mfa_admin_member_import_maybe_run() {
	if ( empty( $_POST['mfa_member_import_dry'] ) && empty( $_POST['mfa_member_import_reset'] ) ) {
		return null;
	}

	if ( ! isset( $_POST['mfa_member_import_nonce'] )
		|| ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['mfa_member_import_nonce'] ) ), 'mfa_member_import' ) ) {
		return array( 'error' => 'Security check failed. Reload the page and try again.' );
	}

	if ( ! mfa_admin_member_tools_allowed() ) {
		return array( 'error' => 'Only an administrator can run the member import.' );
	}

	if ( ! function_exists( 'mfa_member_import_batch' ) ) {
		return array( 'error' => 'Member import is unavailable.' );
	}

	if ( ! empty( $_POST['mfa_member_import_reset'] ) ) {
		mfa_member_import_reset_state();
		delete_transient( 'mfa_member_import_totals' );

		return array( 'reset' => true );
	}

	@set_time_limit( 120 );

	$reports = array();

	foreach ( array_keys( mfa_member_import_sources() ) as $source ) {
		$reports[ $source ] = mfa_member_import_batch( $source, mfa_admin_member_import_batch_size(), false );
	}

	return array( 'dry' => $reports );
}

/**
 * The panel shown above the member list.
 */
function mfa_admin_member_import_panel( $report = null ) {
	// Hidden rather than shown-and-refused - a button that always errors is
	// worse than no button.
	if ( ! mfa_admin_member_tools_allowed() ) {
		return '';
	}

	$state  = mfa_member_import_state();
	$totals = mfa_member_import_totals();
	$labels = mfa_member_import_sources();
	$cohort = mfa_member_import_cohort_date();

	ob_start();
	?>
	<div class="mfa-admin-mem-import">
		<div class="mfa-admin-mem-import-head">
			<div>
				<strong>Import users from the directories</strong>
				<p class="mfa-admin-mem-import-note">
					Adds mobile numbers found in the mosque, business and website directories as
					Prospect members. Landlines, US and Canadian numbers, and countries with no
					mobile/landline split are skipped. Imported members are registered as
					<?php echo esc_html( date_i18n( 'j M Y', strtotime( $cohort ) ) ); ?>, so they
					stay out of the current member totals until then.
				</p>
			</div>
			<form method="post" class="mfa-admin-mem-import-form">
				<?php wp_nonce_field( 'mfa_member_import', 'mfa_member_import_nonce' ); ?>
				<a class="mfa-btn mfa-btn-primary" href="<?php echo esc_url( mfa_admin_member_import_url() ); ?>" target="_blank" rel="noopener">Import Users</a>
				<button type="submit" name="mfa_member_import_dry" value="1" class="mfa-btn">Dry run</button>
				<button type="submit" name="mfa_member_import_reset" value="1" class="mfa-btn mfa-admin-mem-import-full" onclick="return confirm('Clear all import progress? The next run re-reads every mosque, business and website record from the start. Members already created are NOT removed.');">Full rescan</button>
			</form>
		</div>

		<table class="mfa-admin-member-import-progress">
			<thead>
				<tr>
					<th>Directory</th>
					<th>With a number</th>
					<th>Checked</th>
					<th>Added</th>
					<th>Status</th>
				</tr>
			</thead>
			<tbody>
			<?php foreach ( $labels as $source => $label ) : ?>
				<?php
				$row   = $state[ $source ];
				$total = isset( $totals[ $source ] ) ? (int) $totals[ $source ] : 0;
				?>
				<tr>
					<td><?php echo esc_html( $label ); ?></td>
					<td><?php echo esc_html( number_format_i18n( $total ) ); ?></td>
					<td><?php echo esc_html( number_format_i18n( (int) $row['scanned'] ) ); ?></td>
					<td><strong><?php echo esc_html( number_format_i18n( (int) $row['added'] ) ); ?></strong></td>
					<td>
						<?php if ( $row['sweep_done'] ) : ?>
							Swept<?php echo $row['last_run'] ? ' &middot; last run ' . esc_html( $row['last_run'] ) : ''; ?>
						<?php elseif ( (int) $row['cursor'] > 0 ) : ?>
							In progress
						<?php else : ?>
							Not started
						<?php endif; ?>
					</td>
				</tr>
			<?php endforeach; ?>
			</tbody>
		</table>

		<?php if ( is_array( $report ) && isset( $report['error'] ) ) : ?>
			<p class="mfa-admin-mem-import-error"><?php echo esc_html( $report['error'] ); ?></p>
		<?php elseif ( is_array( $report ) && isset( $report['reset'] ) ) : ?>
			<p class="mfa-admin-mem-import-empty">Progress cleared. The next run starts again from the first record of each directory.</p>
		<?php elseif ( is_array( $report ) && isset( $report['dry'] ) ) : ?>
			<div class="mfa-admin-member-import-dry">
				<p><strong>Dry run</strong> - nothing was created. This is what the next batch of
					<?php echo esc_html( number_format_i18n( mfa_admin_member_import_batch_size() ) ); ?> records from each directory would do:</p>
				<?php foreach ( $report['dry'] as $source => $r ) : ?>
					<?php if ( isset( $r['error'] ) ) : ?>
						<p class="mfa-admin-mem-import-error"><?php echo esc_html( $r['error'] ); ?></p>
						<?php continue; ?>
					<?php endif; ?>
					<p class="mfa-admin-member-import-dry-head">
						<strong><?php echo esc_html( $r['label'] ); ?></strong>
						&mdash; <?php echo esc_html( number_format_i18n( (int) $r['scanned'] ) ); ?> scanned,
						<strong><?php echo esc_html( number_format_i18n( (int) $r['added'] ) ); ?></strong> would be added,
						<?php echo esc_html( number_format_i18n( (int) $r['dup_existing'] + (int) $r['dup_batch'] ) ); ?> already members
						<?php if ( $r['complete'] ) : ?>
							<em>(nothing left to check)</em>
						<?php endif; ?>
					</p>
					<?php echo mfa_admin_member_import_reason_list( $r ); // phpcs:ignore WordPress.Security.EscapeOutput ?>
				<?php endforeach; ?>
			</div>
		<?php endif; ?>
	</div>
	<?php
	return ob_get_clean();
}

/**
 * Why numbers were skipped, worst offender first. Shown rather than only the
 * added count so a batch that adds little still explains itself instead of
 * looking broken.
 */
function mfa_admin_member_import_reason_list( $report ) {
	if ( empty( $report['reasons'] ) ) {
		return '';
	}

	$labels  = mfa_phone_reason_labels();
	$reasons = $report['reasons'];
	unset( $reasons['ok'] );

	if ( empty( $reasons ) ) {
		return '';
	}

	arsort( $reasons );

	$out = '<ul class="mfa-admin-member-import-reasons">';

	foreach ( $reasons as $reason => $count ) {
		$label = isset( $labels[ $reason ] ) ? $labels[ $reason ] : $reason;
		$out  .= '<li>' . esc_html( number_format_i18n( (int) $count ) ) . ' &mdash; ' . esc_html( $label ) . '</li>';
	}

	return $out . '</ul>';
}

/**
 * [mfa_admin_member_import] - the runner page.
 *
 * One batch per page load, then a short JS redirect back to itself, the same
 * pattern as the website generator. Closing the tab stops it, and the cursor
 * means the next run resumes where this one left off rather than starting over.
 */
add_shortcode( 'mfa_admin_member_import', 'mfa_admin_member_import_shortcode' );
function mfa_admin_member_import_shortcode() {
	if ( function_exists( 'mfa_admin_require_section_access' ) ) {
		$no_access = mfa_admin_require_section_access( 'member' );
		if ( $no_access ) {
			return $no_access;
		}
	}

	if ( ! mfa_admin_member_tools_allowed() ) {
		return '<div class="mfa-admin-mem-import"><p class="mfa-admin-mem-import-error">Only an administrator can run the member import.</p></div>';
	}

	if ( ! function_exists( 'mfa_member_import_batch' ) ) {
		return '<div class="mfa-admin-mem-import"><p class="mfa-admin-mem-import-error">Member import is unavailable.</p></div>';
	}

	@set_time_limit( 180 );

	// Work one directory at a time, in the listed order. "Has work" is decided
	// by running the batch rather than by inspecting the state: that way the
	// same loop covers both the first sweep and the later follow-up passes,
	// and a source that is finished costs one empty query to skip.
	$report = null;

	foreach ( array_keys( mfa_member_import_sources() ) as $source ) {
		$attempt = mfa_member_import_batch( $source, mfa_admin_member_import_batch_size(), true );

		if ( isset( $attempt['error'] ) ) {
			$report = $attempt;
			break;
		}

		if ( (int) $attempt['scanned'] > 0 ) {
			$report = $attempt;
			break;
		}
	}

	ob_start();
	?>
	<div class="mfa-admin-mem-import mfa-admin-member-import-run">
		<h2 class="mfa-h2">Importing users</h2>

		<?php if ( null === $report ) : ?>
			<p class="mfa-admin-mem-import-empty">
				All three directories have been swept. New and edited records are picked up the
				next time this page is opened - there is nothing to do right now.
			</p>
			<p><a class="mfa-btn" href="<?php echo esc_url( home_url( '/admin/member/' ) ); ?>">Back to members</a></p>
		<?php elseif ( isset( $report['error'] ) ) : ?>
			<p class="mfa-admin-mem-import-error"><?php echo esc_html( $report['error'] ); ?></p>
		<?php else : ?>
			<p class="mfa-admin-member-import-dry-head">
				<strong><?php echo esc_html( $report['label'] ); ?></strong> &mdash;
				<?php echo esc_html( number_format_i18n( (int) $report['scanned'] ) ); ?> checked,
				<strong><?php echo esc_html( number_format_i18n( (int) $report['added'] ) ); ?></strong> added,
				<?php echo esc_html( number_format_i18n( (int) $report['dup_existing'] + (int) $report['dup_batch'] ) ); ?> already members<?php
				if ( (int) $report['failed'] > 0 ) {
					echo ', ' . esc_html( number_format_i18n( (int) $report['failed'] ) ) . ' failed';
				}
				?>.
			</p>

			<?php echo mfa_admin_member_import_reason_list( $report ); // phpcs:ignore WordPress.Security.EscapeOutput ?>

			<?php if ( ! empty( $report['samples'] ) ) : ?>
				<ul class="mfa-admin-member-import-samples">
					<?php foreach ( $report['samples'] as $sample ) : ?>
						<li><?php echo esc_html( $sample ); ?></li>
					<?php endforeach; ?>
				</ul>
			<?php endif; ?>

			<p class="mfa-admin-mem-import-last">Continuing automatically. Close this tab to stop - progress is saved after every batch.</p>
			<script>setTimeout( function () { location.href = <?php echo wp_json_encode( mfa_admin_member_import_url() ); ?>; }, <?php echo (int) wp_rand( 800, 1600 ); ?> );</script>
		<?php endif; ?>

		<?php echo mfa_admin_member_import_progress_table(); // phpcs:ignore WordPress.Security.EscapeOutput ?>
	</div>
	<?php
	return ob_get_clean();
}

/**
 * Running totals, repeated on the runner page so progress is visible without
 * switching back to the member list.
 */
function mfa_admin_member_import_progress_table() {
	$state  = mfa_member_import_state();
	$totals = mfa_member_import_totals();

	$out = '<table class="mfa-admin-member-import-progress"><thead><tr>'
		. '<th>Directory</th><th>With a number</th><th>Checked</th><th>Added</th></tr></thead><tbody>';

	foreach ( mfa_member_import_sources() as $source => $label ) {
		$total = isset( $totals[ $source ] ) ? (int) $totals[ $source ] : 0;
		$out  .= '<tr><td>' . esc_html( $label ) . '</td>'
			. '<td>' . esc_html( number_format_i18n( $total ) ) . '</td>'
			. '<td>' . esc_html( number_format_i18n( (int) $state[ $source ]['scanned'] ) ) . '</td>'
			. '<td><strong>' . esc_html( number_format_i18n( (int) $state[ $source ]['added'] ) ) . '</strong></td></tr>';
	}

	return $out . '</tbody></table>';
}
