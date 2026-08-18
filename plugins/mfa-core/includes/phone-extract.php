<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Phone normalisation and mobile/fixed classification for the directory ->
 * member import.
 *
 * Deliberately NOT reusing niz_user_normalize_phone(): that strips non-digits
 * and nothing else, so a US business listed as "(201) 200-0333" becomes
 * "2012000333" - a number carrying no country code, which WhatsApp will either
 * reject or, worse, deliver to whoever happens to own that digit string in
 * another country. An import writing tens of thousands of rows has to know the
 * country code is real before it writes one.
 *
 * Classification is prefix-based because that is the only method available:
 * Meta's Cloud API has no contact-check endpoint, so "is this number on
 * WhatsApp" cannot be asked in advance - only answered by sending.
 *
 * Where a country has no dependable mobile/fixed prefix split, the rule is
 * null rather than a guess, and null is rejected. The two that matter most
 * here are the US and Canada: under the NANP mobile and landline share area
 * codes and numbers port freely between them, so no prefix can separate the
 * two. They are rejected by country code before any other test.
 */

/**
 * Mobile-prefix rules, keyed by ISO code.
 *
 * 'cc'     - international dialling code.
 * 'trunk'  - national trunk prefix to strip when the number is in local form.
 * 'mobile' - national-significant-number prefixes that denote a mobile, or
 *            null where the country has no usable split (see file header).
 * 'names'  - lowercased values seen in the directory's own country column.
 *
 * Kept to the countries that actually appear in the data plus the obvious
 * neighbours; anything outside the table resolves to unknown and is skipped,
 * which is the agreed behaviour rather than a gap.
 */
function mfa_phone_country_rules() {
	static $rules = null;

	if ( null !== $rules ) {
		return $rules;
	}

	$rules = array(
		// --- Southeast Asia -------------------------------------------------
		'MY' => array( 'cc' => '60',  'trunk' => '0', 'mobile' => array( '1' ),                'names' => array( 'malaysia' ) ),
		'ID' => array( 'cc' => '62',  'trunk' => '0', 'mobile' => array( '8' ),                'names' => array( 'indonesia' ) ),
		'SG' => array( 'cc' => '65',  'trunk' => '',  'mobile' => array( '8', '9' ),           'names' => array( 'singapore' ) ),
		'TH' => array( 'cc' => '66',  'trunk' => '0', 'mobile' => array( '6', '8', '9' ),      'names' => array( 'thailand' ) ),
		'PH' => array( 'cc' => '63',  'trunk' => '0', 'mobile' => array( '9' ),                'names' => array( 'philippines' ) ),
		'VN' => array( 'cc' => '84',  'trunk' => '0', 'mobile' => array( '3', '5', '7', '8', '9' ), 'names' => array( 'vietnam', 'viet nam' ) ),
		'BN' => array( 'cc' => '673', 'trunk' => '',  'mobile' => array( '7', '8' ),           'names' => array( 'brunei', 'brunei darussalam' ) ),
		'KH' => array( 'cc' => '855', 'trunk' => '0', 'mobile' => array( '1', '6', '7', '8', '9' ), 'names' => array( 'cambodia' ) ),
		'MM' => array( 'cc' => '95',  'trunk' => '0', 'mobile' => array( '9' ),                'names' => array( 'myanmar', 'burma' ) ),

		// --- South Asia -----------------------------------------------------
		'IN' => array( 'cc' => '91',  'trunk' => '0', 'mobile' => array( '6', '7', '8', '9' ), 'names' => array( 'india' ) ),
		'BD' => array( 'cc' => '880', 'trunk' => '0', 'mobile' => array( '1' ),                'names' => array( 'bangladesh' ) ),
		'PK' => array( 'cc' => '92',  'trunk' => '0', 'mobile' => array( '3' ),                'names' => array( 'pakistan' ) ),
		'LK' => array( 'cc' => '94',  'trunk' => '0', 'mobile' => array( '7' ),                'names' => array( 'sri lanka' ) ),
		'NP' => array( 'cc' => '977', 'trunk' => '0', 'mobile' => array( '97', '98' ),         'names' => array( 'nepal' ) ),
		'MV' => array( 'cc' => '960', 'trunk' => '',  'mobile' => array( '7', '9' ),           'names' => array( 'maldives' ) ),
		'AF' => array( 'cc' => '93',  'trunk' => '0', 'mobile' => array( '7' ),                'names' => array( 'afghanistan' ) ),

		// --- Middle East ----------------------------------------------------
		'SA' => array( 'cc' => '966', 'trunk' => '0', 'mobile' => array( '5' ),                'names' => array( 'saudi arabia', 'ksa', 'kingdom of saudi arabia' ) ),
		'AE' => array( 'cc' => '971', 'trunk' => '0', 'mobile' => array( '5' ),                'names' => array( 'united arab emirates', 'uae' ) ),
		'QA' => array( 'cc' => '974', 'trunk' => '',  'mobile' => array( '3', '5', '6', '7' ), 'names' => array( 'qatar' ) ),
		'KW' => array( 'cc' => '965', 'trunk' => '',  'mobile' => array( '5', '6', '9' ),      'names' => array( 'kuwait' ) ),
		'BH' => array( 'cc' => '973', 'trunk' => '',  'mobile' => array( '3' ),                'names' => array( 'bahrain' ) ),
		'OM' => array( 'cc' => '968', 'trunk' => '',  'mobile' => array( '7', '9' ),           'names' => array( 'oman' ) ),
		'JO' => array( 'cc' => '962', 'trunk' => '0', 'mobile' => array( '7' ),                'names' => array( 'jordan' ) ),
		'LB' => array( 'cc' => '961', 'trunk' => '0', 'mobile' => array( '3', '7' ),           'names' => array( 'lebanon' ) ),
		'IQ' => array( 'cc' => '964', 'trunk' => '0', 'mobile' => array( '7' ),                'names' => array( 'iraq' ) ),
		'PS' => array( 'cc' => '970', 'trunk' => '0', 'mobile' => array( '5' ),                'names' => array( 'palestine', 'palestinian territory', 'state of palestine' ) ),
		'YE' => array( 'cc' => '967', 'trunk' => '0', 'mobile' => array( '7' ),                'names' => array( 'yemen' ) ),
		'SY' => array( 'cc' => '963', 'trunk' => '0', 'mobile' => array( '9' ),                'names' => array( 'syria', 'syrian arab republic' ) ),
		'TR' => array( 'cc' => '90',  'trunk' => '0', 'mobile' => array( '5' ),                'names' => array( 'turkey', 'turkiye', 'türkiye' ) ),
		'IR' => array( 'cc' => '98',  'trunk' => '0', 'mobile' => array( '9' ),                'names' => array( 'iran' ) ),
		'IL' => array( 'cc' => '972', 'trunk' => '0', 'mobile' => array( '5' ),                'names' => array( 'israel' ) ),

		// --- Africa ---------------------------------------------------------
		'EG' => array( 'cc' => '20',  'trunk' => '0', 'mobile' => array( '1' ),                'names' => array( 'egypt' ) ),
		'MA' => array( 'cc' => '212', 'trunk' => '0', 'mobile' => array( '6', '7' ),           'names' => array( 'morocco' ) ),
		'DZ' => array( 'cc' => '213', 'trunk' => '0', 'mobile' => array( '5', '6', '7' ),      'names' => array( 'algeria' ) ),
		'TN' => array( 'cc' => '216', 'trunk' => '',  'mobile' => array( '2', '4', '5', '9' ), 'names' => array( 'tunisia' ) ),
		'LY' => array( 'cc' => '218', 'trunk' => '0', 'mobile' => array( '9' ),                'names' => array( 'libya' ) ),
		'NG' => array( 'cc' => '234', 'trunk' => '0', 'mobile' => array( '7', '8', '9' ),      'names' => array( 'nigeria' ) ),
		'KE' => array( 'cc' => '254', 'trunk' => '0', 'mobile' => array( '1', '7' ),           'names' => array( 'kenya' ) ),
		'TZ' => array( 'cc' => '255', 'trunk' => '0', 'mobile' => array( '6', '7' ),           'names' => array( 'tanzania' ) ),
		'UG' => array( 'cc' => '256', 'trunk' => '0', 'mobile' => array( '7' ),                'names' => array( 'uganda' ) ),
		'ZA' => array( 'cc' => '27',  'trunk' => '0', 'mobile' => array( '6', '7', '8' ),      'names' => array( 'south africa' ) ),
		'SN' => array( 'cc' => '221', 'trunk' => '',  'mobile' => array( '7' ),                'names' => array( 'senegal' ) ),
		'SO' => array( 'cc' => '252', 'trunk' => '0', 'mobile' => array( '6', '7', '9' ),      'names' => array( 'somalia' ) ),
		'SD' => array( 'cc' => '249', 'trunk' => '0', 'mobile' => array( '9', '1' ),           'names' => array( 'sudan' ) ),
		'GH' => array( 'cc' => '233', 'trunk' => '0', 'mobile' => array( '2', '5' ),           'names' => array( 'ghana' ) ),

		// --- Europe ---------------------------------------------------------
		'GB' => array( 'cc' => '44',  'trunk' => '0', 'mobile' => array( '7' ),                'names' => array( 'united kingdom', 'uk', 'great britain', 'england', 'scotland', 'wales', 'northern ireland' ) ),
		'IE' => array( 'cc' => '353', 'trunk' => '0', 'mobile' => array( '8' ),                'names' => array( 'ireland', 'republic of ireland' ) ),
		'FR' => array( 'cc' => '33',  'trunk' => '0', 'mobile' => array( '6', '7' ),           'names' => array( 'france' ) ),
		'DE' => array( 'cc' => '49',  'trunk' => '0', 'mobile' => array( '15', '16', '17' ),   'names' => array( 'germany', 'deutschland' ) ),
		'ES' => array( 'cc' => '34',  'trunk' => '',  'mobile' => array( '6', '7' ),           'names' => array( 'spain' ) ),
		'IT' => array( 'cc' => '39',  'trunk' => '',  'mobile' => array( '3' ),                'names' => array( 'italy' ) ),
		'NL' => array( 'cc' => '31',  'trunk' => '0', 'mobile' => array( '6' ),                'names' => array( 'netherlands', 'the netherlands', 'holland' ) ),
		'BE' => array( 'cc' => '32',  'trunk' => '0', 'mobile' => array( '4' ),                'names' => array( 'belgium' ) ),
		'CH' => array( 'cc' => '41',  'trunk' => '0', 'mobile' => array( '7' ),                'names' => array( 'switzerland' ) ),
		'AT' => array( 'cc' => '43',  'trunk' => '0', 'mobile' => array( '6' ),                'names' => array( 'austria' ) ),
		'SE' => array( 'cc' => '46',  'trunk' => '0', 'mobile' => array( '7' ),                'names' => array( 'sweden' ) ),
		'NO' => array( 'cc' => '47',  'trunk' => '',  'mobile' => array( '4', '9' ),           'names' => array( 'norway' ) ),
		'FI' => array( 'cc' => '358', 'trunk' => '0', 'mobile' => array( '4', '5' ),           'names' => array( 'finland' ) ),
		'PT' => array( 'cc' => '351', 'trunk' => '',  'mobile' => array( '9' ),                'names' => array( 'portugal' ) ),
		'GR' => array( 'cc' => '30',  'trunk' => '',  'mobile' => array( '69' ),               'names' => array( 'greece' ) ),
		'RU' => array( 'cc' => '7',   'trunk' => '8', 'mobile' => array( '9' ),                'names' => array( 'russia', 'russian federation' ) ),
		'UA' => array( 'cc' => '380', 'trunk' => '0', 'mobile' => array( '39', '50', '63', '66', '67', '68', '73', '89', '91', '92', '93', '94', '95', '96', '97', '98', '99' ), 'names' => array( 'ukraine' ) ),
		'AL' => array( 'cc' => '355', 'trunk' => '0', 'mobile' => array( '6' ),                'names' => array( 'albania' ) ),
		'XK' => array( 'cc' => '383', 'trunk' => '0', 'mobile' => array( '4' ),                'names' => array( 'kosovo' ) ),
		'BA' => array( 'cc' => '387', 'trunk' => '0', 'mobile' => array( '6' ),                'names' => array( 'bosnia and herzegovina', 'bosnia' ) ),

		// --- Asia-Pacific ---------------------------------------------------
		'AU' => array( 'cc' => '61',  'trunk' => '0', 'mobile' => array( '4' ),                'names' => array( 'australia' ) ),
		'NZ' => array( 'cc' => '64',  'trunk' => '0', 'mobile' => array( '2' ),                'names' => array( 'new zealand' ) ),
		'CN' => array( 'cc' => '86',  'trunk' => '0', 'mobile' => array( '1' ),                'names' => array( 'china' ) ),
		'JP' => array( 'cc' => '81',  'trunk' => '0', 'mobile' => array( '70', '80', '90' ),   'names' => array( 'japan' ) ),
		'KR' => array( 'cc' => '82',  'trunk' => '0', 'mobile' => array( '1' ),                'names' => array( 'south korea', 'korea', 'republic of korea' ) ),
		'UZ' => array( 'cc' => '998', 'trunk' => '',  'mobile' => array( '9', '8', '7' ),      'names' => array( 'uzbekistan' ) ),
		'AZ' => array( 'cc' => '994', 'trunk' => '0', 'mobile' => array( '4', '5', '6', '7' ), 'names' => array( 'azerbaijan' ) ),
		'KG' => array( 'cc' => '996', 'trunk' => '0', 'mobile' => array( '2', '5', '7', '9' ), 'names' => array( 'kyrgyzstan' ) ),

		// --- Long tail ------------------------------------------------------
		// Added after measuring which countries the first pass dropped as
		// unknown. Individually small, together a few thousand numbers.
		'RO' => array( 'cc' => '40',  'trunk' => '0', 'mobile' => array( '7' ),                'names' => array( 'romania' ) ),
		'LT' => array( 'cc' => '370', 'trunk' => '8', 'mobile' => array( '6' ),                'names' => array( 'lithuania' ) ),
		'RS' => array( 'cc' => '381', 'trunk' => '0', 'mobile' => array( '6' ),                'names' => array( 'serbia' ) ),
		'GE' => array( 'cc' => '995', 'trunk' => '0', 'mobile' => array( '5' ),                'names' => array( 'georgia' ) ),
		'HK' => array( 'cc' => '852', 'trunk' => '',  'mobile' => array( '5', '6', '9' ),      'names' => array( 'hong kong' ) ),
		'CO' => array( 'cc' => '57',  'trunk' => '0', 'mobile' => array( '3' ),                'names' => array( 'colombia' ) ),
		'ET' => array( 'cc' => '251', 'trunk' => '0', 'mobile' => array( '9' ),                'names' => array( 'ethiopia' ) ),
		'CM' => array( 'cc' => '237', 'trunk' => '',  'mobile' => array( '6' ),                'names' => array( 'cameroon' ) ),
		'ML' => array( 'cc' => '223', 'trunk' => '',  'mobile' => array( '6', '7', '9' ),      'names' => array( 'mali' ) ),
		'BF' => array( 'cc' => '226', 'trunk' => '',  'mobile' => array( '5', '6', '7' ),      'names' => array( 'burkina faso' ) ),
		'CI' => array( 'cc' => '225', 'trunk' => '',  'mobile' => array( '01', '05', '07' ),   'names' => array( "côte d'ivoire", "cote d'ivoire", 'ivory coast' ) ),
		'GN' => array( 'cc' => '224', 'trunk' => '',  'mobile' => array( '6' ),                'names' => array( 'guinea' ) ),
		'MR' => array( 'cc' => '222', 'trunk' => '',  'mobile' => array( '2', '3', '4' ),      'names' => array( 'mauritania' ) ),
		'MZ' => array( 'cc' => '258', 'trunk' => '',  'mobile' => array( '8' ),                'names' => array( 'mozambique' ) ),
		'MU' => array( 'cc' => '230', 'trunk' => '',  'mobile' => array( '5' ),                'names' => array( 'mauritius' ) ),
		'MG' => array( 'cc' => '261', 'trunk' => '0', 'mobile' => array( '3' ),                'names' => array( 'madagascar' ) ),

		// --- No usable split ------------------------------------------------
		// Listed on purpose: the country IS recognised, but its numbering plan
		// does not separate mobile from fixed by prefix, so classification
		// returns null and the number is skipped. Without these entries the
		// same numbers would fall out as "unknown country", which would read
		// as missing data rather than a deliberate decision.
		'DK' => array( 'cc' => '45',  'trunk' => '',  'mobile' => null, 'names' => array( 'denmark' ) ),
		'PL' => array( 'cc' => '48',  'trunk' => '',  'mobile' => null, 'names' => array( 'poland' ) ),
		// Mexico dropped its mobile prefix in 2019 - mobile and fixed are both
		// plain 10-digit numbers now, same as the NANP problem.
		'MX' => array( 'cc' => '52',  'trunk' => '01', 'mobile' => null, 'names' => array( 'mexico' ) ),
		// Brazil IS separable, but by length not prefix: a mobile is 11 digits
		// (2-digit area code then a leading 9), a landline 10. This table only
		// matches prefixes, so Brazil is reported as undecidable rather than
		// guessed. Worth revisiting if Brazilian numbers ever matter enough.
		'BR' => array( 'cc' => '55',  'trunk' => '0', 'mobile' => null, 'names' => array( 'brazil' ) ),
	);

	return $rules;
}

/**
 * Country column value -> ISO code. The directory stores full English names,
 * so matching is on the lowercased name plus the aliases seen in the data.
 *
 * @return string ISO code, or '' when the name is not in the table.
 */
function mfa_phone_iso_from_country( $country ) {
	static $map = null;

	if ( null === $map ) {
		$map = array();
		foreach ( mfa_phone_country_rules() as $iso => $rule ) {
			foreach ( $rule['names'] as $name ) {
				$map[ $name ] = $iso;
			}
		}
		// The NANP pair are not in the rules table (no split is possible), but
		// they must still be recognisable by name so the import can report
		// them as deliberately excluded instead of unknown.
		$map['united states']                = 'US';
		$map['united states of america']     = 'US';
		$map['usa']                          = 'US';
		$map['u.s.a.']                       = 'US';
		$map['us']                           = 'US';
		$map['america']                      = 'US';
		$map['canada']                       = 'CA';
	}

	$key = strtolower( trim( (string) $country ) );
	$key = preg_replace( '/\s+/', ' ', $key );

	return isset( $map[ $key ] ) ? $map[ $key ] : '';
}

/**
 * Dialling code -> ISO, longest match first so 880 (Bangladesh) is not read as
 * 88, and 971 (UAE) not as 97.
 *
 * @return string ISO code, or '' when no known code matches.
 */
function mfa_phone_iso_from_dial_code( $digits ) {
	static $codes = null;

	if ( null === $codes ) {
		$codes = array();
		foreach ( mfa_phone_country_rules() as $iso => $rule ) {
			$codes[ $rule['cc'] ] = $iso;
		}
		// Kept out of the rules table but needed here, so that a +1 number is
		// identified and rejected as NANP rather than falling through as an
		// unrecognised country code.
		$codes['1'] = 'US';

		krsort( $codes, SORT_STRING );
		uksort( $codes, function ( $a, $b ) {
			return strlen( $b ) - strlen( $a );
		} );
	}

	foreach ( $codes as $cc => $iso ) {
		if ( 0 === strpos( $digits, (string) $cc ) ) {
			return $iso;
		}
	}

	return '';
}

/**
 * True when a value is a placeholder rather than a number. The directory data
 * carries literal "No phone available" strings from the crawl.
 */
function mfa_phone_is_placeholder( $raw ) {
	return (bool) preg_match( '/no phone|not available|unknown|n\/a|none/i', $raw );
}

/**
 * Normalise a raw directory phone value to E.164 digits and classify it.
 *
 * @param string $raw     The phone/whatsapp column value as stored.
 * @param string $country The record's country column, used only when the
 *                        number carries no international prefix of its own.
 *
 * @return array {
 *     @type bool        $ok        Whether a mobile number was resolved.
 *     @type string      $phone     E.164 digits, no '+', matching how member
 *                                  phones are already stored ("60123456789").
 *     @type string      $iso       Resolved country.
 *     @type bool|null   $is_mobile True/false, or null when undecidable.
 *     @type string      $reason    Why it was rejected, for the run report.
 * }
 */
function mfa_phone_normalize( $raw, $country = '' ) {
	$fail = function ( $reason ) {
		return array( 'ok' => false, 'phone' => '', 'iso' => '', 'is_mobile' => null, 'reason' => $reason );
	};

	$raw = trim( (string) $raw );

	if ( '' === $raw ) {
		return $fail( 'empty' );
	}

	if ( mfa_phone_is_placeholder( $raw ) ) {
		return $fail( 'placeholder' );
	}

	// Some records hold two numbers in one field ("021-3917853, 021-31905266").
	// Take the first: it is the primary line in every sample checked, and
	// splitting one record into two members would break the 1:1 dedupe.
	$parts = preg_split( '#\s*(?:,|;|/|\bor\b|\band\b)\s*#i', $raw );
	$raw   = isset( $parts[0] ) ? $parts[0] : $raw;

	$intl   = ( '' !== $raw && '+' === substr( ltrim( $raw ), 0, 1 ) );
	$digits = preg_replace( '/\D/', '', $raw );

	if ( '' === $digits ) {
		return $fail( 'unparseable' );
	}

	// 00 is the same intent as +.
	if ( ! $intl && 0 === strpos( $digits, '00' ) ) {
		$digits = substr( $digits, 2 );
		$intl   = true;
	}

	// "+03 8870 7000" appears in the data: a plus in front of a national trunk
	// code. The plus is the data error, not the trunk digit, so treat these as
	// national numbers and let the country column resolve them.
	if ( $intl && 0 === strpos( $digits, '0' ) ) {
		$intl = false;
	}

	$iso = '';

	if ( $intl ) {
		$iso = mfa_phone_iso_from_dial_code( $digits );

		if ( '' === $iso ) {
			return $fail( 'unknown_country' );
		}
	} else {
		$iso = mfa_phone_iso_from_country( $country );

		if ( '' === $iso ) {
			// No international prefix and no usable country column - the number
			// cannot be dialled from anywhere, so there is nothing to salvage.
			return $fail( 'no_country' );
		}
	}

	// Excluded by decision, and the only honest answer either way: US and
	// Canadian mobiles and landlines share area codes.
	if ( 'US' === $iso || 'CA' === $iso ) {
		return $fail( 'nanp_excluded' );
	}

	$rules = mfa_phone_country_rules();

	if ( ! isset( $rules[ $iso ] ) ) {
		return $fail( 'unknown_country' );
	}

	$rule = $rules[ $iso ];
	$cc   = $rule['cc'];

	if ( $intl ) {
		$nsn = substr( $digits, strlen( $cc ) );
	} else {
		$nsn = $digits;

		// Strip the national trunk prefix if present, e.g. Malaysian
		// "0123456789" -> "123456789" before the 60 is prepended.
		if ( '' !== $rule['trunk'] && 0 === strpos( $nsn, $rule['trunk'] ) ) {
			$nsn = substr( $nsn, strlen( $rule['trunk'] ) );
		}
	}

	if ( strlen( $nsn ) < 6 ) {
		return $fail( 'too_short' );
	}

	if ( strlen( $nsn ) > 13 ) {
		return $fail( 'too_long' );
	}

	$e164 = $cc . $nsn;

	// Same bounds niz_user_create_prospect() enforces, so anything this
	// function accepts is guaranteed to survive the existing member code.
	if ( strlen( $e164 ) < 8 || strlen( $e164 ) > 15 ) {
		return $fail( 'bad_length' );
	}

	$is_mobile = mfa_phone_is_mobile_nsn( $nsn, $rule['mobile'] );

	if ( null === $is_mobile ) {
		return array( 'ok' => false, 'phone' => $e164, 'iso' => $iso, 'is_mobile' => null, 'reason' => 'undecidable' );
	}

	if ( ! $is_mobile ) {
		return array( 'ok' => false, 'phone' => $e164, 'iso' => $iso, 'is_mobile' => false, 'reason' => 'landline' );
	}

	return array( 'ok' => true, 'phone' => $e164, 'iso' => $iso, 'is_mobile' => true, 'reason' => 'ok' );
}

/**
 * Does this national-significant number start with one of the country's
 * mobile prefixes?
 *
 * @param string     $nsn      Number with country code and trunk removed.
 * @param array|null $prefixes Mobile prefixes, or null when the country has
 *                             no usable split.
 *
 * @return bool|null Null means undecidable, which callers must not treat as
 *                   false - "we cannot tell" and "it is a landline" get
 *                   reported separately.
 */
function mfa_phone_is_mobile_nsn( $nsn, $prefixes ) {
	if ( null === $prefixes || ! is_array( $prefixes ) || empty( $prefixes ) ) {
		return null;
	}

	foreach ( $prefixes as $prefix ) {
		if ( 0 === strpos( $nsn, (string) $prefix ) ) {
			return true;
		}
	}

	return false;
}

/**
 * Human-readable labels for the rejection reasons, used by the import report.
 */
function mfa_phone_reason_labels() {
	return array(
		'ok'             => 'Mobile',
		'empty'          => 'No phone number',
		'placeholder'    => 'Placeholder text, not a number',
		'unparseable'    => 'No digits found',
		'no_country'     => 'No country code and no country on the record',
		'unknown_country' => 'Country not in the mobile-prefix rules',
		'nanp_excluded'  => 'US / Canada - mobile and landline cannot be told apart',
		'undecidable'    => 'Country has no mobile/landline split',
		'landline'       => 'Landline',
		'too_short'      => 'Too short to be a real number',
		'too_long'       => 'Too long to be a real number',
		'bad_length'     => 'Outside the 8-15 digit range',
	);
}
