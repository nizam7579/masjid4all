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
            $referrer_id = isset($_COOKIE['affiliateid']) ? absint($_COOKIE['affiliateid']) : 14270;

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
