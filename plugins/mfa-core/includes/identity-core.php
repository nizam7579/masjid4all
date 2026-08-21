<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Core identity API — moved verbatim from enaizi-user/includes/user.php.
 * These exact function names/signatures are a hard external contract:
 * niz-wa's mfa-core integration (niz-wa-integration.php) calls
 * niz_user_check()/niz_user_create_prospect() by name, so they must
 * keep working identically here. enaizi-user remains active for its
 * other features (member.php, shortcodes, AJAX, FluentForm hooks) —
 * only user.php's require was removed there to avoid redeclaring these
 * same functions in two places at once.
 *
 * 1. Check User
 * 2. Register New User
 * 3. Get user_info from user_id
 * 4. Send password
 * 5. Register Prospect
 */

/**
 * Normalize phone number: remove all non-digits.
 */
function niz_user_normalize_phone($phone) {
    return preg_replace('/\D/', '', trim($phone));
}

/** 1. Check User
 *     $user_id = niz_user_check($phone)
 *     if empty, not a member
 */

function niz_user_check($phone) {
    $phone = niz_user_normalize_phone($phone);
    if (empty($phone)) {
        return null;
    }
    $users = get_users([
        'meta_key'     => 'user_phone',
        'meta_value'   => $phone,
        'meta_compare' => '=',
        'number'       => 1,
        'fields'       => 'ID',
    ]);
    return !empty($users) ? $users[0] : null;
}


/**
 * Register a new user or upgrade an existing prospect securely.
 *
 * @param string $phone          The user's mobile/WhatsApp number.
 * @param string $name           The full name of the user.
 * @param bool   $send_template  Whether to trigger a WhatsApp password template.
 * @return int|WP_Error          Returns user ID on success, WP_Error object on failure.
 */
/**
 * Phone-based registration. Name preserved as a published contract; the body
 * now delegates to mfa_register() (includes/identity-register.php).
 *
 * This function was the LAST creator bypassing
 * niz_user_complete_registration(): it set user_status itself and called
 * niz_sync_cct_member_record() directly, so accounts made through it got no
 * user_registered reset, no mfa_registration_route, no Welcome Bonus, no
 * referrer award and no mfa_user_activated. Routing it through mfa_register()
 * fixes all of that at once.
 *
 * Two deliberate behaviour changes, both strictly more useful:
 *
 *  - An existing NON-prospect no longer returns WP_Error('user_exists'); the
 *    existing user id is returned instead. That error only existed because
 *    the old body could not cope with an already-registered member upgrading,
 *    and every caller had to work around it. niz_user_complete_registration()
 *    returns early for someone already a member, so nothing is re-run.
 *  - $send_template no longer attempts a WhatsApp password send. It could not
 *    work anyway: niz_wa_send_password() lives only in the retired enaizi_wa,
 *    so the generated password was set on the account and delivered to nobody
 *    while lead_source was mislabelled 'whatsapp'. The flag now only selects
 *    lead_source, which is all it ever really achieved here.
 */
function niz_user_register( $phone, $name, $send_template = false ) {
    if ( empty( $name ) ) {
        return new WP_Error( 'empty_name', __( 'Name is required.', 'enaizi-user' ) );
    }

    if ( ! function_exists( 'mfa_register' ) ) {
        return new WP_Error( 'missing_fn', 'mfa_register() is not available.' );
    }

    $phone = niz_user_normalize_phone( $phone );

    $user_id = mfa_register([
        'name'              => $name,
        'phone'             => $phone,
        'route'             => $send_template ? 'web' : 'whatsapp',
        'lead_source'       => $send_template ? 'website' : 'whatsapp',
        'whatsapp_verified' => false,
    ]);

    if ( is_wp_error( $user_id ) ) {
        return $user_id;
    }

    // Kept from the original: these members have never chosen a password.
    update_user_meta( $user_id, 'change_password', 'yes' );

    // Kept from the original: the member pages read this cookie to prefill
    // the phone field. Guarded because this can now run from a webhook.
    if ( ! headers_sent() ) {
        setcookie( 'user_phone', $phone, time() + 2592000, '/', '', is_ssl(), false );
    }

    return $user_id;
}

/**
 * Helper function to handle JetEngine CCT record creation and validation cleanly.
 * Depends on add_cct_member(), which lives in mfa-core/includes/member-cct-core.php
 * (moved from enaizi-user/includes/member.php 2026-08-10).
 */
function niz_sync_cct_member_record( $user_id, $name, $phone ) {
    $country     = isset( $_COOKIE['country'] ) ? sanitize_text_field( wp_unslash( $_COOKIE['country'] ) ) : '';
    $partner_id  = isset( $_COOKIE['partnerid'] ) ? intval( $_COOKIE['partnerid'] ) : 0;

    // Same guard as niz_user_member_cct() - the affiliateid cookie names the
    // signed-in user themselves whenever one is logged in, so reading it raw
    // recorded self-referrals. 14270 remains the "no referrer" sentinel.
    $referrer_id = function_exists( 'mfa_referrer_from_cookie' )
        ? mfa_referrer_from_cookie( $user_id )
        : 0;

    if ( empty( $referrer_id ) ) {
        $referrer_id = 14270;
    }

    $existing_item_id = get_user_meta( $user_id, 'item_id', true );

    if ( empty( $existing_item_id ) ) {
        $response = add_cct_member([
            'name'        => $name,
            'phone'       => $phone,
            'status'      => 'Member',
            'user_id'     => $user_id,
            'referrer_id' => $referrer_id,
            'partner_id'  => $partner_id,
            'country'     => $country,
            'cct_created' => current_time('mysql')
        ]);

        if ( ! empty( $response['success'] ) ) {
            update_user_meta( $user_id, 'item_id', $response['insert_id'] );
            return $response['insert_id'];
        } else {
            $error_msg = ! empty( $response['message'] ) ? $response['message'] : 'Unknown CCT Insertion Error.';
            return new WP_Error( 'cct_insertion_failed', $error_msg );
        }
    }

    return $existing_item_id;
}

function niz_user_info($user_id) {
    $user = get_user_by('id', $user_id);
    if (!$user) {
        return false;
    }
    $phone = get_user_meta($user_id, 'user_phone', true);
    return [
        'id'           => $user->ID,
        'username'     => $user->user_login,
        'email'        => $user->user_email,
        'name'         => $user->first_name,
        'phone'        => $phone,
        'registered'   => $user->user_registered,
    ];
}

function niz_user_password($phone) {
    $phone = niz_user_normalize_phone($phone);
    $user_id = niz_user_check($phone);

    if (!$user_id) {
        return new WP_Error('user_not_found', __('User not found.', 'enaizi-user'));
    }

    $new_password = (string) random_int(100000, 999999);

    wp_set_password($new_password, $user_id);
    update_user_meta($user_id, 'change_password', 'yes');

    return $new_password;
}

/**
 * Kept as the published name (niz-wa and the imports call it), but the body
 * now delegates to mfa_person_upsert() so there is ONE creation path -
 * see includes/identity-register.php. Contract preserved exactly: user id on
 * success, WP_Error for a bad phone number, '' if the insert itself failed.
 */
function niz_user_create_prospect($phone, $name = '') {
    if ( ! function_exists('mfa_person_upsert') ) {
        return new WP_Error('missing_fn', 'mfa_person_upsert() is not available.');
    }

    $result = mfa_person_upsert([
        'name'        => $name,
        'phone'       => $phone,
        'lead_source' => 'whatsapp',
    ]);

    if (is_wp_error($result)) {
        // A bad phone number is a caller error and is reported as one; an
        // insert failure was historically reported as '' and some callers
        // test for that, so that distinction is preserved.
        if ('invalid_phone' === $result->get_error_code() || 'missing_identity' === $result->get_error_code()) {
            return $result;
        }
        error_log('niz_user_create_prospect failed: ' . $result->get_error_message());
        return '';
    }

    return $result;
}

/**
 * Check if a user exists by phone number. Kept as an alias of
 * niz_user_check() — same lookup, different name — in case other code
 * still calls this specific name.
 */
function niz_user_exists_by_phone($phone) {
    $phone = niz_user_normalize_phone($phone);
    if (empty($phone)) {
        return false;
    }
    $users = get_users([
        'meta_key'     => 'user_phone',
        'meta_value'   => $phone,
        'meta_compare' => '=',
        'number'       => 1,
        'fields'       => 'ID',
    ]);
    return !empty($users) ? $users[0] : false;
}

function niz_user_login($phone, $password) {
    $phone = niz_user_normalize_phone($phone);
    $user_id = niz_user_exists_by_phone($phone);
    if (!$user_id) {
        return new WP_Error('user_not_found', __('User not found.', 'enaizi-user'));
    }
    $user = get_user_by('id', $user_id);
    if (wp_check_password($password, $user->user_pass, $user->ID)) {
        return $user;
    }
    return new WP_Error('incorrect_password', __('Invalid password.', 'enaizi-user'));
}

function niz_user_reset_password($phone) {
    $phone = niz_user_normalize_phone($phone);
    $user_id = niz_user_exists_by_phone($phone);
    if (!$user_id) {
        return new WP_Error('user_not_found', __('User not found.', 'enaizi-user'));
    }
    $new_password = wp_generate_password(8, false);
    wp_set_password($new_password, $user_id);
    update_user_meta($user_id, 'change_password', 'yes');

    if (function_exists('niz_wa_template')) {
        niz_wa_template($phone, 'temporary_password', ['password' => $new_password]);
    } else {
        error_log('niz_user_reset_password: niz_wa_template not found – WhatsApp not sent.');
    }
    return true;
}

/**
 * Deleting a WordPress user used to leave its records behind.
 *
 * wp_delete_user() removes the user and its meta and nothing else, so the
 * jet_cct_member row, the Barakah ledger and the WhatsApp conversation all
 * survived pointing at an ID that no longer resolved. The damage was quiet
 * rather than loud: an orphaned member row sat in the Prospects list forever
 * and could never be followed up, and 105 ledger rows worth 8,120 points
 * inflated every total that summed the ledger without joining to wp_users
 * (audited and cleaned 2026-08-20).
 *
 * Hooked on `delete_user`, which fires BEFORE the row goes, so the phone
 * number is still readable - that is the only way to find a surviving twin,
 * since duplicates are created by the same number arriving twice.
 *
 * A twin gets everything reassigned. With no twin, the member row and ledger
 * are removed (nobody can reach or spend them), but the WhatsApp history is
 * deliberately LEFT and logged instead: deleting a person's message history
 * as a side effect of deleting an account is not a decision a hook should
 * make silently.
 */
add_action( 'delete_user', 'mfa_cleanup_records_for_deleted_user', 10, 1 );

function mfa_cleanup_records_for_deleted_user( $user_id ) {
	global $wpdb;

	$user_id = (int) $user_id;
	if ( $user_id <= 0 ) {
		return;
	}

	$member  = $wpdb->prefix . 'jet_cct_member';
	$barakah = $wpdb->prefix . 'jet_cct_barakah';
	$convs   = $wpdb->prefix . 'nwa_conversations';
	$msgs    = $wpdb->prefix . 'nwa_messages';

	// Read the phone while the meta still exists, and fall back to the CCT
	// row, which survives deletion and sometimes holds it when the meta does
	// not.
	$phone = preg_replace( '/\D+/', '', (string) get_user_meta( $user_id, 'user_phone', true ) );
	if ( '' === $phone ) {
		$phone = preg_replace( '/\D+/', '', (string) $wpdb->get_var( $wpdb->prepare( "SELECT phone FROM {$member} WHERE user_id = %d", $user_id ) ) );
	}

	$twin = 0;
	if ( '' !== $phone ) {
		$candidates = $wpdb->get_col( $wpdb->prepare(
			"SELECT user_id FROM {$wpdb->usermeta} WHERE meta_key = 'user_phone' AND meta_value = %s",
			$phone
		) );
		foreach ( $candidates as $candidate ) {
			$candidate = (int) $candidate;
			if ( $candidate !== $user_id && get_userdata( $candidate ) ) {
				$twin = $candidate;
				break;
			}
		}
	}

	if ( $twin ) {
		// A duplicate of somebody who still exists: move everything across
		// rather than destroying earns and history that belong to a real
		// person. Barakah is deduped on (user_id, description), so drop any
		// row the twin already has before moving the rest.
		$wpdb->query( $wpdb->prepare(
			"DELETE x FROM {$barakah} x INNER JOIN {$barakah} y ON y.user_id = %d AND y.description = x.description WHERE x.user_id = %d",
			$twin,
			$user_id
		) );
		$wpdb->update( $barakah, array( 'user_id' => $twin ), array( 'user_id' => $user_id ) );
		$wpdb->update( $convs, array( 'user_id' => $twin ), array( 'user_id' => $user_id ) );
		$wpdb->update( $msgs, array( 'user_id' => $twin ), array( 'user_id' => $user_id ) );
		$wpdb->delete( $member, array( 'user_id' => $user_id ) );

		error_log( "mfa_cleanup_records_for_deleted_user: user {$user_id} merged into twin {$twin} (phone {$phone})" );

		return;
	}

	$wpdb->delete( $member, array( 'user_id' => $user_id ) );
	$wpdb->delete( $barakah, array( 'user_id' => $user_id ) );

	$left = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$convs} WHERE user_id = %d", $user_id ) );
	if ( $left ) {
		error_log( "mfa_cleanup_records_for_deleted_user: user {$user_id} deleted with no twin; {$left} WhatsApp conversation(s) left in place for review" );
	}
}
