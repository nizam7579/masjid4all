<?php
/*
Plugin Name: Enaizi Identity
Plugin URI: https://enaizi.com
Description: Universal authentication, identity and verification framework for WordPress.
Version: 1.0.0
Author: Enaizi
License: GPL2
Text Domain: enaizi-identity
*/

if (!defined('ABSPATH')) {
    exit;
}


/**
 * Plugin Constants
 */

define(
    'NIZ_GOOGLE_CLIENT_ID',
    'REDACTED_ROTATE_THIS_SECRET'
);


define(
    'NIZ_GOOGLE_CLIENT_SECRET',
    'REDACTED_ROTATE_THIS_SECRET'
);


define('NIZ_IDENTITY_VERSION', '1.0.0');

define(
    'NIZ_IDENTITY_PATH',
    plugin_dir_path(__FILE__)
);

define(
    'NIZ_IDENTITY_URL',
    plugin_dir_url(__FILE__)
);

define(
    'NIZ_IDENTITY_FILE',
    __FILE__
);


/**
 * Load Core Classes
 */

require_once NIZ_IDENTITY_PATH . 'includes/class-loader.php';


/**
 * Initialize Plugin
 */

function niz_identity_init() {

    Enaizi_Identity_Loader::init();

}

add_action(
    'plugins_loaded',
    'niz_identity_init'
);