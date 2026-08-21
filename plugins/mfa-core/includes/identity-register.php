<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * One way to bring a person into the system.
 *
 * Before this (2026-08-21) there were four creators — niz_user_create_prospect(),
 * niz_wa_create_contact_user(), niz_user_register() and enaizi-identity's own
 * wp_insert_user() calls — each with its own username convention, its own idea
 * of which meta to set, and its own bugs. niz_user_register() also skipped
 * niz_user_complete_registration() entirely, so accounts made that way got no
 * registration date reset, no route, no Welcome Bonus and no referrer award.
 *
 * The split here is deliberate and is the one thing to understand before
 * changing anything:
 *
 *   mfa_person_upsert()  a record comes into existence. ALWAYS a prospect.
 *   mfa_register()       upsert + activate, i.e. they are now a member.
 *
 * They are separate because the WhatsApp case needs the first WITHOUT the
 * second: an unknown number messaging Sofia must get an account so the
 * conversation has somewhere to live, but someone who asks one question and
 * never returns must not become a "member". Collapsing these into a single
 * register() is what would break that.
 *
 * Activation itself is NOT reimplemented here — mfa_register() calls
 * niz_user_complete_registration(), which remains the single chokepoint that
 * sets user_status, promotes the jet_cct_member row, resets user_registered
 * to the activation moment in GMT, records the route, backfills
 * referrer/country and fires mfa_user_activated.
 *
 * ONE sanctioned exception, do not "fix" it: mfa_member_import_create_user()
 * in member-import.php still calls wp_insert_user() directly. It has to set
 * user_registered to a back-date, which nothing here can do — stamping today
 * would put the whole imported cohort straight into the member totals the
 * import exists to stay out of. It is also the only bulk path, where the
 * per-user password hashing this file does would cost minutes.
 */

/**
 * Which existing account, if any, do this email and phone point at?
 *
 * Placeholder addresses (<phone>@mfa.com, <phone>@noemail.com) are ignored
 * for lookup: they are generated FROM the phone number, so matching on one
 * would just be the phone lookup wearing a hat, and two different people can
 * legitimately both lack a real address.
 *
 * @return array {
 *     @type int      $user_id  Resolved user, or 0.
 *     @type string   $by       'phone' | 'email' | 'both' | ''
 *     @type int|null $conflict The OTHER user id when the two disagree.
 * }
 */
function mfa_identity_resolve( $email = '', $phone = '' ) {
	$out = array( 'user_id' => 0, 'by' => '', 'conflict' => null );

	$phone = function_exists( 'niz_user_normalize_phone' ) ? niz_user_normalize_phone( $phone ) : preg_replace( '/\D/', '', (string) $phone );
	$email = sanitize_email( (string) $email );

	$by_phone = 0;
	if ( '' !== $phone && function_exists( 'niz_user_check' ) ) {
		$by_phone = (int) niz_user_check( $phone );
	}

	$by_email = 0;
	$real_email = ( '' !== $email && is_email( $email ) );
	if ( $real_email && function_exists( 'mfa_is_placeholder_email' ) && mfa_is_placeholder_email( $email ) ) {
		$real_email = false;
	}
	if ( $real_email ) {
		$u = get_user_by( 'email', $email );
		$by_email = $u ? (int) $u->ID : 0;
	}

	if ( $by_phone && $by_email ) {
		if ( $by_phone === $by_email ) {
			$out['user_id'] = $by_phone;
			$out['by']      = 'both';
		} else {
			// Two real accounts. Deliberately NOT merged here - see
			// mfa_person_upsert()'s identity_conflict error.
			$out['user_id']  = $by_phone;
			$out['by']       = 'phone';
			$out['conflict'] = $by_email;
		}
		return $out;
	}

	if ( $by_phone ) {
		$out['user_id'] = $by_phone;
		$out['by']      = 'phone';
	} elseif ( $by_email ) {
		$out['user_id'] = $by_email;
		$out['by']      = 'email';
	}

	return $out;
}

/**
 * Find or create the person. Never activates; a new record is a prospect.
 *
 * @param array $args name, email, phone, password, lead_source,
 *                    whatsapp_verified.
 * @return int|WP_Error User ID.
 */
function mfa_person_upsert( $args = array() ) {
	$args = wp_parse_args( $args, array(
		'name'              => '',
		'email'             => '',
		'phone'             => '',
		'password'          => '',
		'lead_source'       => '',
		'whatsapp_verified' => false,
	) );

	$name  = sanitize_text_field( (string) $args['name'] );
	$email = sanitize_email( (string) $args['email'] );
	$phone = function_exists( 'niz_user_normalize_phone' ) ? niz_user_normalize_phone( $args['phone'] ) : preg_replace( '/\D/', '', (string) $args['phone'] );

	$has_email = ( '' !== $email && is_email( $email ) );
	$has_phone = ( '' !== $phone );

	if ( ! $has_email && ! $has_phone ) {
		return new WP_Error( 'missing_identity', 'A phone number or an email address is required.' );
	}

	// Same bounds every other path already enforced, so a number rejected
	// there is rejected here too rather than creating a second class of user.
	if ( $has_phone && ( strlen( $phone ) < 8 || strlen( $phone ) > 15 ) ) {
		return new WP_Error( 'invalid_phone', 'Invalid phone number. Must be 8-15 digits.' );
	}

	$resolved = mfa_identity_resolve( $email, $phone );

	// The email belongs to one account and the phone to another. Never merged
	// automatically: a merge moves Barakah points, listing ownership and the
	// jet_cct_member row, and is not reversible. Sofia's account flow already
	// handles this properly by ASKING ("link your WhatsApp number to that
	// account?"), so callers get the facts and can route there.
	if ( ! empty( $resolved['conflict'] ) ) {
		return new WP_Error(
			'identity_conflict',
			'This phone number and email address belong to two different accounts.',
			array(
				'phone_user_id' => (int) $resolved['user_id'],
				'email_user_id' => (int) $resolved['conflict'],
			)
		);
	}

	$user_id = (int) $resolved['user_id'];

	if ( $user_id ) {
		mfa_person_fill_gaps( $user_id, $name, $email, $phone, $args );
		return $user_id;
	}

	/* ---------------- Create ---------------- */

	// A phone-only signup gets the placeholder address every other path in
	// this codebase already generates, so mfa_is_placeholder_email() keeps
	// recognising it and these accounts stay out of email campaigns.
	$login_email = $has_email ? $email : $phone . '@mfa.com';

	// Login prefix is load-bearing: 'mfa_<phone>' is how this codebase records
	// "created from a phone number" (niz-wa's own fallback uses 'nwa_', which
	// only happens when no nwa_resolve_user_id filter is hooked - never here).
	$login = $has_phone ? 'mfa_' . $phone : sanitize_user( current( explode( '@', $login_email ) ), true );
	if ( '' === $login ) {
		$login = 'mfa_user';
	}
	if ( username_exists( $login ) ) {
		$login .= '_' . wp_generate_password( 5, false, false );
	}

	$display = ( '' !== $name ) ? $name : ( $has_phone ? 'Prospect ' . $phone : $login );

	$user_id = wp_insert_user( array(
		'user_login'   => $login,
		'user_pass'    => ( '' !== $args['password'] ) ? $args['password'] : wp_generate_password( 24 ),
		'user_email'   => $login_email,
		'display_name' => $display,
		'first_name'   => $display,
		'role'         => 'subscriber',
	) );

	if ( is_wp_error( $user_id ) ) {
		error_log( 'mfa_person_upsert: wp_insert_user failed - ' . $user_id->get_error_message() );
		return $user_id;
	}

	$user_id = (int) $user_id;

	if ( $has_phone ) {
		update_user_meta( $user_id, 'user_phone', $phone );
	}

	// Explicit 'prospect', never left blank: /admin/ filters match exact
	// values, so a blank status drops the row out of every filtered view.
	update_user_meta( $user_id, 'user_status', 'prospect' );

	if ( '' !== $args['lead_source'] ) {
		update_user_meta( $user_id, 'lead_source', sanitize_key( $args['lead_source'] ) );
	}

	// Only ever set by the WhatsApp path, and it is a statement of fact:
	// Meta delivers a message only from a number that controls that WhatsApp,
	// so receiving one already proves ownership.
	if ( $args['whatsapp_verified'] ) {
		update_user_meta( $user_id, 'niz_whatsapp_verified', 'Yes' );
	}

	/**
	 * A person record was just created (NOT activated).
	 *
	 * @param int   $user_id
	 * @param array $args
	 */
	do_action( 'mfa_person_created', $user_id, $args );

	return $user_id;
}

/**
 * Fill in what an existing record is missing, without ever overwriting
 * something better with something worse.
 *
 * The three rules here are each a bug that has happened on this site:
 *   - never replace a real email with a placeholder;
 *   - never replace a name the person gave us with a generated one
 *     ("Prospect 60123...", the bare number, "user_353...");
 *   - never move a phone number off an account that already has one.
 */
function mfa_person_fill_gaps( $user_id, $name, $email, $phone, $args = array() ) {
	$user = get_userdata( $user_id );
	if ( ! $user ) {
		return;
	}

	if ( '' !== $phone && '' === (string) get_user_meta( $user_id, 'user_phone', true ) ) {
		update_user_meta( $user_id, 'user_phone', $phone );
	}

	$placeholder_now = function_exists( 'mfa_is_placeholder_email' ) && mfa_is_placeholder_email( $user->user_email );
	if ( '' !== $email && is_email( $email ) && $placeholder_now ) {
		$taken = get_user_by( 'email', $email );
		if ( ! $taken || (int) $taken->ID === (int) $user_id ) {
			wp_update_user( array( 'ID' => $user_id, 'user_email' => $email ) );
		}
	}

	if ( '' !== $name && mfa_name_is_generated( $user->display_name ) ) {
		wp_update_user( array( 'ID' => $user_id, 'display_name' => $name, 'first_name' => $name ) );
	}

	if ( ! empty( $args['whatsapp_verified'] ) ) {
		update_user_meta( $user_id, 'niz_whatsapp_verified', 'Yes' );
	}

	if ( '' === (string) get_user_meta( $user_id, 'user_status', true ) ) {
		update_user_meta( $user_id, 'user_status', 'prospect' );
	}
}

/**
 * Is this a name we generated rather than one the person gave us?
 *
 * Mirrors niz_wa_account_known_name()'s test, which exists so nobody is ever
 * asked to confirm their own phone number as their name.
 */
function mfa_name_is_generated( $name ) {
	$name = trim( (string) $name );

	if ( '' === $name ) {
		return true;
	}
	if ( preg_match( '/^(Prospect|Guest)\b/i', $name ) ) {
		return true;
	}
	if ( preg_match( '/^(user|mfa|nwa)[_-]?\d+$/i', $name ) ) {
		return true;
	}
	// A bare phone number, with or without punctuation.
	if ( preg_match( '/^\+?[\d\s\-()]{7,}$/', $name ) ) {
		return true;
	}

	return false;
}

/**
 * Register someone: find-or-create, then activate them as a member.
 *
 * This is the one call every route should use. Pass activate => false to
 * create a prospect without activating (what the WhatsApp first-contact path
 * wants).
 *
 * @param array $args {
 *     @type string $name
 *     @type string $email
 *     @type string $phone
 *     @type string $password
 *     @type string $route     web|google|whatsapp|whatsapp_one_tap|login|...
 *     @type bool   $activate  Default true.
 *     @type string $lead_source
 *     @type bool   $whatsapp_verified
 * }
 * @return int|WP_Error User ID.
 */
function mfa_register( $args = array() ) {
	$args = wp_parse_args( $args, array(
		'name'              => '',
		'email'             => '',
		'phone'             => '',
		'password'          => '',
		'route'             => '',
		'activate'          => true,
		'lead_source'       => '',
		'whatsapp_verified' => false,
	) );

	$user_id = mfa_person_upsert( $args );

	if ( is_wp_error( $user_id ) ) {
		return $user_id;
	}

	if ( ! empty( $args['activate'] ) && function_exists( 'niz_user_complete_registration' ) ) {
		$done = niz_user_complete_registration( (int) $user_id, array(
			'name'  => $args['name'],
			'email' => $args['email'],
			'route' => sanitize_key( $args['route'] ),
		) );

		if ( is_wp_error( $done ) ) {
			return $done;
		}
	}

	return (int) $user_id;
}
