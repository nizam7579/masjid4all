<?php
/**
 * Secrets/config. Define constants in a file OUTSIDE the plugin folder
 * (e.g. required from wp-config.php) for production. Falls back to the
 * wa_connect-style DB option only for quick-start/testing.
 *
 * Example (in a file required from wp-config.php):
 *   define( 'NWA_PHONE_NUMBER_ID', '...' );
 *   define( 'NWA_ACCESS_TOKEN', '...' );
 *   define( 'NWA_APP_SECRET', '...' );
 *   define( 'NWA_VERIFY_TOKEN', '...' );
 *   define( 'NWA_API_VERSION', 'v21.0' );
 *   define( 'NWA_AI_PROVIDER', 'anthropic' );
 *   define( 'NWA_AI_API_KEY', '...' );
 *   define( 'NWA_AI_MODEL', 'claude-sonnet-4-6' );
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class NWA_Config {

	const OPTION_KEY = 'nwa_settings';

	private static $map = array(
		'phone_number_id' => 'NWA_PHONE_NUMBER_ID',
		'access_token'    => 'NWA_ACCESS_TOKEN',
		'app_secret'      => 'NWA_APP_SECRET',
		'verify_token'    => 'NWA_VERIFY_TOKEN',
		'api_version'     => 'NWA_API_VERSION',
		'ai_provider'     => 'NWA_AI_PROVIDER',
		'ai_api_key'      => 'NWA_AI_API_KEY',
		'ai_model'        => 'NWA_AI_MODEL',
	);

	public static function get( $key ) {
		if ( isset( self::$map[ $key ] ) && defined( self::$map[ $key ] ) ) {
			return constant( self::$map[ $key ] );
		}
		$settings = get_option( self::OPTION_KEY, array() );
		return $settings[ $key ] ?? '';
	}

	public static function is_locked( $key ) {
		return isset( self::$map[ $key ] ) && defined( self::$map[ $key ] );
	}
}
