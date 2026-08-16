<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * [run-update] - a standing "run any pending DB schema change or one-off
 * data fix" page for administrators, meant to live at a page like /update/.
 * Reads mfa_updates_registry() (includes/updates.php): if every entry's
 * is_done() is already true, shows "No Update". Otherwise shows a Start
 * button that drives run-update-v1.js's poll loop, which calls each
 * pending entry's run_batch() repeatedly (one AJAX round trip per call)
 * until it reports done - covers both instant one-shot fixes and slow,
 * rate-limited jobs with the same mechanism. See includes/updates.php's
 * own docblock for why entries are shaped this way, and for how to
 * register a new one.
 */
add_shortcode( 'run-update', 'mfa_run_update_shortcode' );
function mfa_run_update_shortcode() {
	if ( ! current_user_can( 'manage_options' ) ) {
		return '<p class="mfa-body-muted">You do not have permission to view this page.</p>';
	}

	$pending = array();
	foreach ( function_exists( 'mfa_updates_registry' ) ? mfa_updates_registry() : array() as $entry ) {
		if ( ! call_user_func( $entry['is_done'] ) ) {
			$pending[] = $entry;
		}
	}

	ob_start();
	?>
	<div class="mfa-run-update" data-ajaxurl="<?php echo esc_url( admin_url( 'admin-ajax.php' ) ); ?>" data-nonce="<?php echo esc_attr( wp_create_nonce( 'mfa_run_update' ) ); ?>">
		<?php if ( ! $pending ) : ?>
			<p class="mfa-run-update-status">No Update</p>
		<?php else : ?>
			<ul class="mfa-run-update-list">
				<?php foreach ( $pending as $entry ) : ?>
					<li data-update-key="<?php echo esc_attr( $entry['key'] ); ?>">
						<span class="mfa-run-update-label"><?php echo esc_html( $entry['label'] ); ?></span>
						<span class="mfa-run-update-progress"></span>
					</li>
				<?php endforeach; ?>
			</ul>
			<button type="button" class="mfa-btn mfa-btn-primary mfa-run-update-start">Start</button>
			<p class="mfa-run-update-status" hidden></p>
		<?php endif; ?>
	</div>
	<?php
	return ob_get_clean();
}

/**
 * AJAX: runs exactly ONE batch for ONE registry entry. The JS drives the
 * loop - this only ever does a single run_batch() call per request, so a
 * slow job's pacing (e.g. Nominatim's rate limit) is enforced by however
 * long that one batch naturally takes, never by trying to cram an entire
 * long-running job into one request.
 */
add_action( 'wp_ajax_mfa_run_update_batch', 'mfa_ajax_run_update_batch' );
function mfa_ajax_run_update_batch() {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_send_json_error( array( 'message' => 'You are not authorized to do this.' ) );
	}

	check_ajax_referer( 'mfa_run_update', 'nonce' );

	$key   = isset( $_POST['key'] ) ? sanitize_key( $_POST['key'] ) : '';
	$entry = null;
	foreach ( function_exists( 'mfa_updates_registry' ) ? mfa_updates_registry() : array() as $candidate ) {
		if ( $candidate['key'] === $key ) {
			$entry = $candidate;
			break;
		}
	}

	if ( ! $entry ) {
		wp_send_json_error( array( 'message' => 'Unknown update.' ) );
	}

	try {
		$result = call_user_func( $entry['run_batch'] );
	} catch ( \Throwable $e ) {
		wp_send_json_error( array( 'message' => $e->getMessage() ) );
	}

	wp_send_json_success( $result );
}
