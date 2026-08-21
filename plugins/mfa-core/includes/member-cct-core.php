<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * CCT member record API — moved verbatim from enaizi-user/includes/member.php
 * (2026-08-10 mfa-core consolidation; member_cct_data()/get_cct_member_data()
 * duplicates collapsed into niz_user_field_by_itemid()/niz_user_field_by_userid()
 * the same day, all callers across enaizi/* and enaizi-user/* updated).
 *
 * - niz_user_member_cct($user_id)     // get or create cct_member
 * - niz_user_itemid_by_phone($phone)  // get item_id from phone
 * - niz_user_field_by_itemid($item_id,$field)
 * - niz_user_field_by_userid($user_id,$field)
 * - niz_user_update_field($user_id, $field, $value)
 * - add_cct_member($data)
 * - update_cct_member($item_id, $user_data)
 */

/**
 * Get or Create Member_cct from user_id.
 * Use only when register or first login
 */
function niz_user_member_cct($user_id) {
    global $wpdb;

    $user_id = absint($user_id);
    if (!$user_id) {
        return [
            'success' => false,
            'message' => 'Invalid user ID.',
        ];
    }

    $table = $wpdb->prefix . 'jet_cct_member';
    $item_id = absint(get_user_meta($user_id, 'item_id', true));

    if (!$item_id) {
        $phone = sanitize_text_field(get_user_meta($user_id, 'user_phone', true));

        if ($phone) {
            $item_id = absint(niz_user_itemid_by_phone($phone));
        }

        if (!$item_id) {
            $current_user = get_userdata($user_id);
            if (!$current_user) {
                return ['success' => false, 'message' => 'User data context not found.'];
            }

            $name        = !empty($current_user->display_name) ? $current_user->display_name : 'Guest';
            $country     = isset($_COOKIE['country']) ? sanitize_text_field(wp_unslash($_COOKIE['country'])) : '';
            $partner_id  = isset($_COOKIE['partnerid']) ? absint($_COOKIE['partnerid']) : 0;

            // Never store a self-referral. The affiliateid cookie holds the
            // LOGGED-IN user's own id whenever one is signed in (enaizi-mfa's
            // niz_mfa_location_init(), which needs it that way to build share
            // links), so reading it raw here recorded people as their own
            // referrer. mfa_referrer_from_cookie() applies that guard plus the
            // sentinel and account-exists checks; 14270 stays the "nobody
            // referred them" value when nothing usable is present.
            $referrer_id = mfa_referrer_from_cookie($user_id);
            if (!$referrer_id) {
                $referrer_id = 14270;
            }

            $insert_data = [
                'name'        => $name,
                'phone'       => $phone,
                'user_id'     => $user_id,
                'country'     => $country,
                'referrer_id' => $referrer_id,
                'partner_id'  => $partner_id,
                'cct_created' => current_time('mysql'),
            ];

            $insert_format = ['%s', '%s', '%d', '%s', '%d', '%d', '%s'];
            $result = $wpdb->insert($table, $insert_data, $insert_format);

            if ($result === false) {
                return [
                    'success' => false,
                    'message' => $wpdb->last_error,
                ];
            }

            $item_id = (int) $wpdb->insert_id;
        }

        update_user_meta($user_id, 'item_id', $item_id);
    }

    $member = $wpdb->get_row(
        $wpdb->prepare("SELECT * FROM {$table} WHERE _ID = %d LIMIT 1", $item_id),
        ARRAY_A
    );

    if (!$member) {
        return [
            'success' => false,
            'message' => 'Member record not found.',
        ];
    }

    return $member;
}

/**
 * Get item_id from phone
 */
function niz_user_itemid_by_phone($phone) {
    global $wpdb;

    $phone = preg_replace('/\D+/', '', (string) $phone);
    if ($phone === '') {
        return null;
    }

    $table = $wpdb->prefix . 'jet_cct_member';
    $item_id = $wpdb->get_var(
        $wpdb->prepare("SELECT _ID FROM {$table} WHERE phone = %s LIMIT 1", $phone)
    );

    return !empty($item_id) ? absint($item_id) : null;
}

/**
 * Get Field from Item ID (Secured via precise column whitelist filtering)
 */
function niz_user_field_by_itemid($item_id,$field) {
    global $wpdb;

    // Secure custom dynamic field variables from target structural query drops
    $allowed_fields = ['_ID', 'name', 'phone', 'status', 'user_id', 'referrer_id', 'partner_id', 'country', 'email', 'sex', 'birthdate'];
    if (!in_array($field, $allowed_fields, true)) {
        return null;
    }

    $table = $wpdb->prefix . 'jet_cct_member';
    // Use standard concatenation safely since structure inputs have been validated
    $result = $wpdb->get_var(
        $wpdb->prepare("SELECT {$field} FROM {$table} WHERE _ID = %d", absint($item_id))
    );

    return ($result !== null) ? $result : null;
}

/**
 * Function to get a specific field value from CCT securely
 */
function niz_user_field_by_userid($user_id, $field) {
    global $wpdb;

    //$allowed_fields = ['_ID', 'name', 'phone', 'status', 'user_id', 'referrer_id', 'partner_id', 'country', 'email', 'sex', 'birthdate'];
    //if (!in_array($field, $allowed_fields, true)) {
    //    return "";
    //}

    $table = $wpdb->prefix . 'jet_cct_member';
    $result = $wpdb->get_var(
        $wpdb->prepare("SELECT {$field} FROM {$table} WHERE user_id = %d", absint($user_id))
    );

    return $result ? $result : "";
}

function niz_user_update_field($user_id, $field, $value) {
    global $wpdb;

    if (empty($user_id) || empty($field)) {
        return false;
    }

    return $wpdb->update(
        $wpdb->prefix . 'jet_cct_member',
        [ $field => $value ],
        [ 'user_id' => (int) $user_id ],
        [ '%s' ],           // Format for $data (status field - string)
        [ '%d' ]            // Format for $where (user_id - integer)
    );
}

/**
 * Add New CCT Member
 */
function add_cct_member($data) {
    global $wpdb;

    $table = $wpdb->prefix . 'jet_cct_member';
    $defaults = [
        'item_id'     => null,
        'name'        => '',
        'phone'       => '',
        'status'      => '',
        'user_id'     => 0,
        'referrer_id' => '',
        'partner_id'  => '',
        'country'     => '',
    ];

    $data = wp_parse_args($data, $defaults);

    $insert_data = [
        'name'        => sanitize_text_field($data['name']),
        'phone'       => sanitize_text_field($data['phone']),
        'status'      => sanitize_text_field($data['status']),
        'user_id'     => intval($data['user_id']),
        'country'     => sanitize_text_field($data['country']),
        'referrer_id' => sanitize_text_field($data['referrer_id']),
        'partner_id'  => sanitize_text_field($data['partner_id']),
        'cct_created' => current_time('mysql')
    ];

    $format = ['%s', '%s', '%s', '%d', '%s', '%s', '%s'];

    if (!empty($data['item_id'])) {
        $insert_data['item_id'] = intval($data['item_id']);
        $format[] = '%d';
    }

    $result = $wpdb->insert($table, $insert_data, $format);

    if ($result === false) {
        return [
            'success' => false,
            'message' => $wpdb->last_error
        ];
    }

    return [
        'success'   => true,
        'insert_id' => $wpdb->insert_id
    ];
}

/**
 * Update CCT Member Data
 */
function update_cct_member($item_id, $user_data) {
    global $wpdb;

    if (empty($item_id) || empty($user_data) || !is_array($user_data)) {
        return "Error: item_id and user_data are required.";
    }

    $table = $wpdb->prefix . 'jet_cct_member';

    // Map data types accurately dynamic parsing arrays
    $formats = [];
    foreach ($user_data as $key => $value) {
        if (is_int($value)) {
            $formats[] = '%d';
        } elseif (is_float($value)) {
            $formats[] = '%f';
        } else {
            $formats[] = '%s';
        }
    }

    $result = $wpdb->update(
        $table,
        $user_data,
        ['_ID' => absint($item_id)],
        $formats,
        ['%d']
    );

    if ($result === false) {
        return "Error updating record: " . $wpdb->last_error;
    } elseif ($result === 0) {
        return "No changes made or record not found.";
    } else {
        return "Record updated successfully.";
    }
}

/**
 * The referring user id carried by the current request, or 0 when there
 * isn't a usable one.
 *
 * Every caller used to read $_COOKIE['affiliateid'] raw, which is why a
 * self-referral could be stored: enaizi-mfa's niz_mfa_location_init()
 * deliberately overwrites that cookie with the LOGGED-IN user's own id, so
 * the floating share button can build "/?id=<me>" links. That is correct for
 * sharing and wrong for attribution - the same cookie answers two different
 * questions. Until the two are split into separate cookies, attribution is
 * guarded here, at the point of use.
 *
 * Rejects, in order: nothing set, the 14270 "no referrer" sentinel, the user
 * referring themselves, and an id that is no longer a real account.
 *
 * @param int $user_id The user being attributed.
 * @return int Referrer user id, or 0.
 */
function mfa_referrer_from_cookie( $user_id ) {
    // ONLY the dedicated attribution cookie. affiliateid is deliberately NOT
    // consulted, not even as a fallback: enaizi-mfa sets it to the LOGGED-IN
    // user's own id so the share button can build "/?id=<me>" links, which
    // means that for any signed-in request it names the wrong person entirely.
    //
    // That is not theoretical. Attribution now also runs at first login (the
    // prospect->member promotion), i.e. while somebody IS signed in - and on a
    // shared or admin browser affiliateid holds whoever used it last. A test
    // registration on staging was duly credited to the admin, user 2. The
    // self-referral guard below cannot catch that, because the id belongs to a
    // real and different user.
    //
    // Cost of dropping the fallback: a visitor who arrived on a "?id=" link
    // before mfa_capture_referrer_cookie() shipped (2026-08-21) is not
    // attributed. That is the right trade - affiliateid was never a reliable
    // attribution signal, which is the whole reason mfa_referrer exists.
    $ref = ! empty( $_COOKIE['mfa_referrer'] ) ? absint( wp_unslash( $_COOKIE['mfa_referrer'] ) ) : 0;

    if ( ! $ref || 14270 === $ref || $ref === (int) $user_id ) {
        return 0;
    }

    return get_userdata( $ref ) ? $ref : 0;
}

/**
 * Remember who referred this visitor, in a cookie of our own.
 *
 * Attribution cannot share the affiliateid cookie, and this is worth stating
 * plainly because it looks like a bug until you check what reads it. That
 * cookie answers "which id should the share button put in my links", so
 * enaizi-mfa's niz_mfa_location_init() sets it to the LOGGED-IN user's own id
 * whenever somebody is signed in - mfa-core/assets/js/site-chrome-v1.js reads
 * it to build "/?id=<me>". Attribution asks the opposite question: who
 * referred THIS visitor. One cookie cannot hold both answers, which is why a
 * referral link opened by an already-signed-in visitor was being discarded.
 *
 * So affiliateid is left exactly as it is - changing it would credit the
 * referrer instead of the sharer on every share - and referrals get their own
 * cookie here.
 *
 * First referrer wins: once set it is not overwritten, so somebody who
 * arrives through one member's link and later browses in through another is
 * still attributed to the first. Nothing is written for a visitor who never
 * arrived on a "?id=" link.
 */
add_action( 'init', 'mfa_capture_referrer_cookie', 5 );
function mfa_capture_referrer_cookie() {
    if ( is_admin() || wp_doing_ajax() || wp_doing_cron() || ( defined( 'REST_REQUEST' ) && REST_REQUEST ) ) {
        return;
    }

    if ( ! empty( $_COOKIE['mfa_referrer'] ) || headers_sent() ) {
        return;
    }

    $ref = (int) filter_input( INPUT_GET, 'id', FILTER_VALIDATE_INT );
    if ( $ref < 1 ) {
        return;
    }

    setcookie( 'mfa_referrer', (string) $ref, array(
        'expires'  => time() + YEAR_IN_SECONDS,
        'path'     => '/',
        'secure'   => is_ssl(),
        'httponly' => true,
        'samesite' => 'Lax',
    ) );

    $_COOKIE['mfa_referrer'] = (string) $ref;
}

/**
 * Record the referrer on a member row that doesn't have one yet.
 *
 * Only ever fills an empty value - a row that already names a real referrer
 * is never re-attributed. No-op when the row doesn't exist yet, because
 * niz_user_member_cct() reads the cookie itself when it creates one.
 *
 * Deliberately called only from niz_user_complete_registration(), which runs
 * once per user: attributing on every login would let somebody who joined
 * with no referrer be credited to whoever's link they happened to click
 * months later.
 *
 * @return int The referrer id now on the row, or 0.
 */
function mfa_member_backfill_referrer( $user_id ) {
    global $wpdb;

    $user_id = absint( $user_id );
    if ( ! $user_id ) {
        return 0;
    }

    $table   = $wpdb->prefix . 'jet_cct_member';
    $current = $wpdb->get_var( $wpdb->prepare(
        "SELECT referrer_id FROM `{$table}` WHERE user_id = %d LIMIT 1",
        $user_id
    ) );

    if ( null === $current ) {
        return 0;
    }

    $current = (int) $current;

    // 14270 counts as empty - it is the sentinel meaning "nobody referred
    // this person", not a real account.
    if ( $current && 14270 !== $current ) {
        return $current;
    }

    $ref = mfa_referrer_from_cookie( $user_id );
    if ( ! $ref ) {
        return 0;
    }

    $wpdb->update(
        $table,
        array( 'referrer_id' => $ref, 'cct_modified' => current_time( 'mysql' ) ),
        array( 'user_id' => $user_id ),
        array( '%d', '%s' ),
        array( '%d' )
    );

    return $ref;
}

/**
 * Record the visitor's country on a member row that doesn't have one yet.
 *
 * The country cookie is written client-side by the geolocation widget
 * (set-cookies.php), so it exists on any browser request and never on the
 * WhatsApp or Stripe webhooks. Safe to call on every login: it only fills a
 * blank, so a country the member set themselves is never overwritten.
 *
 * @return string The country now on the row, or '' if nothing was written.
 */
function mfa_member_backfill_country( $user_id ) {
    global $wpdb;

    $user_id = absint( $user_id );
    if ( ! $user_id ) {
        return '';
    }

    $table   = $wpdb->prefix . 'jet_cct_member';
    $current = $wpdb->get_var( $wpdb->prepare(
        "SELECT country FROM `{$table}` WHERE user_id = %d LIMIT 1",
        $user_id
    ) );

    if ( null === $current || '' !== trim( (string) $current ) ) {
        return (string) $current;
    }

    $country = isset( $_COOKIE['country'] )
        ? sanitize_text_field( wp_unslash( $_COOKIE['country'] ) )
        : '';

    if ( '' === $country ) {
        return '';
    }

    $wpdb->update(
        $table,
        array( 'country' => $country, 'cct_modified' => current_time( 'mysql' ) ),
        array( 'user_id' => $user_id ),
        array( '%s', '%s' ),
        array( '%d' )
    );

    return $country;
}
