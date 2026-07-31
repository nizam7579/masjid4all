<?php

if (!defined('ABSPATH')) {
    exit;
}
 

add_action('wp_ajax_niz_user_logout', 'niz_user_ajax_logout');
function niz_user_ajax_logout() {
    wp_logout();
    wp_send_json_success();
}

add_action('wp_ajax_nopriv_niz_user_check', 'niz_user_ajax_check');
function niz_user_ajax_check() {
    if (empty($_POST['phone'])) {
        wp_send_json_error('Phone number is required.');
    }
    $phone = niz_user_normalize_phone($_POST['phone']);
    if (strlen($phone) < 8 || strlen($phone) > 15) {
        wp_send_json_error('Invalid phone number format.');
    }
    $exists = niz_user_exists_by_phone($phone);
    wp_send_json_success(['exists' => (bool)$exists]);
}

add_action('wp_ajax_nopriv_niz_user_register', 'niz_user_ajax_register');
function niz_user_ajax_register() {
    // Verify required fields
    if (empty($_POST['phone']) || empty($_POST['name'])) {
        wp_send_json_error('Phone and name are required.');
    }
    $phone = niz_user_normalize_phone($_POST['phone']);
    $name  = sanitize_text_field($_POST['name']);
    
    $result = niz_user_register($phone, $name, true);
    
    if (is_wp_error($result)) {
        wp_send_json_error($result->get_error_message());
    }
    wp_send_json_success();
}

add_action('wp_ajax_nopriv_niz_user_login', 'niz_user_ajax_login');
function niz_user_ajax_login() {
    if (empty($_POST['phone']) || empty($_POST['password'])) {
        wp_send_json_error('Phone and password are required.');
    }
    $phone    = niz_user_normalize_phone($_POST['phone']);
    $password = $_POST['password'];
    
    $user = niz_user_login($phone, $password);
    if (is_wp_error($user)) {
        wp_send_json_error($user->get_error_message());
    }
    wp_set_current_user($user->ID);
    wp_set_auth_cookie($user->ID);
    wp_send_json_success('Login success');
}

add_action('wp_ajax_nopriv_niz_user_reset', 'niz_user_ajax_reset');
function niz_user_ajax_reset() {
    if (empty($_POST['phone'])) {
        wp_send_json_error('Phone number is required.');
    }
    $phone = niz_user_normalize_phone($_POST['phone']);
    $result = niz_user_reset_password($phone);
    if (is_wp_error($result)) {
        wp_send_json_error($result->get_error_message());
    }
    wp_send_json_success('New password sent via WhatsApp.');
}