<?php
/**
 * Central API-key resolution for the enaizi plugin.
 *
 * Before this file existed, keys were hardcoded as string literals in nine
 * files across this plugin (Perplexity, several Google/Gemini keys, Google
 * Maps, and a YTL AI Labs key), which is the pattern CLAUDE.md records as
 * already cleaned out of the four `enaizi-*` plugins. This plugin is the
 * fifth and was missed - it was never in the git mirror, so it was never
 * audited. Sanitised 2026-08-16, before the folder's first commit.
 *
 * Resolution order matches mfa_serper_key() in mfa-core: a wp-config.php
 * constant wins, then a DB option so a key can be set without a wp-config
 * edit, then empty string. Every consumer already degrades gracefully on an
 * empty key ("No API Key" / "API Key is missing"), so an unset key fails
 * loudly at the call rather than silently sending an empty request.
 *
 * The constants below are defined only if wp-config.php has not already
 * defined them, which is what makes the constant take precedence. They are
 * defined here rather than inline in each consumer so there is exactly one
 * place to look - note enaizi.php glob-requires every PHP file in this
 * directory, so load order is alphabetical, and every consumer reads its key
 * inside a function at runtime rather than at load time.
 *
 * DISTINCT KEYS ARE KEPT DISTINCT ON PURPOSE. The original code used several
 * different Google/Gemini keys in different functions - possibly different
 * projects, quotas or billing accounts. Merging them into one constant would
 * be a silent behaviour change, so each keeps its own name. Consolidate later
 * as a deliberate decision, not as a side effect of this cleanup.
 *
 * WHAT ACTUALLY NEEDS A VALUE (confirmed with the user 2026-08-16):
 * ONLY Perplexity. It is the live content-generation engine
 * (mosques_perplexity() / mfa_business_perplexity()), and the demand-driven
 * generation queue will depend on it. Every Gemini, Google Maps and YTL key
 * below is confirmed unused - their constants stay DEFINED but resolve to an
 * empty string, which every consumer already handles ("No API Key").
 *
 * Do NOT delete the unused constants while their files remain: PHP 8 raises a
 * fatal on an undefined constant, so removing GEMINI_API_KEY would turn the
 * still-registered `get_masjid_info` AJAX action from a graceful no-op into a
 * 500. Delete the dead functions first, then the constants.
 *
 * And note xgemini.php itself CANNOT be deleted despite its Gemini keys being
 * dead - is_gemini_error() (perplexity.php:295) and removeCodeBlockTags()
 * (business.php:2663, mosque.php:1990) are called by the live Perplexity path.
 * The x- prefix marks abandoned features, not abandoned files.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * @param string $constant wp-config.php constant name.
 * @param string $option   DB option name used as the fallback.
 * @return string Empty string when neither is set.
 */
function enaizi_api_key( $constant, $option ) {
	if ( defined( $constant ) && '' !== (string) constant( $constant ) ) {
		return (string) constant( $constant );
	}

	return (string) get_option( $option, '' );
}

/* Perplexity - mosque + business AI content generation. The live one: this
 * is the key the demand-driven generation queue will depend on. */
if ( ! defined( 'PERPLEXITY_API_KEY' ) ) {
	define( 'PERPLEXITY_API_KEY', enaizi_api_key( 'ENAIZI_PERPLEXITY_API_KEY', 'enaizi_perplexity_api_key' ) );
}

/* Gemini - ask_gemini_mosque() / ask_gemini_mosquexx() in xgemini.php.
 * LIVE: ask_gemini_mosque() is called from mosque.php's mosques_search_info()
 * and get_masjid_info() (verified with token_get_all, not text search - this
 * plugin hides a lot of dead code inside block comments). */
if ( ! defined( 'GEMINI_API_KEY' ) ) {
	define( 'GEMINI_API_KEY', enaizi_api_key( 'ENAIZI_GEMINI_API_KEY', 'enaizi_gemini_api_key' ) );
}

/* Gemini - ask_gemini_business() in xgemini.php. LIVE: called from
 * business.php's xget_business_info(), business_ai_review_shortcode() and
 * business_content_update_shortcode(). Named for its consumer, not for what
 * the endpoint does - the original code used a distinct key here. */
if ( ! defined( 'GEMINI_BUSINESS_API_KEY' ) ) {
	define( 'GEMINI_BUSINESS_API_KEY', enaizi_api_key( 'ENAIZI_GEMINI_BUSINESS_API_KEY', 'enaizi_gemini_business_api_key' ) );
}

/* Gemini - the shared key behind gemini_prompt(), ask_gemini_faraid(),
 * ask_gemini_images() in xgemini.php and ask_sofia() in sofia.php.
 * ask_gemini_images() is LIVE (business.php's business_image_scrapper_shortcode());
 * the sofia.php path is dead, since all three of its hooks are
 * fluentform/submission_inserted and FluentForm was deactivated 2026-08-14. */
if ( ! defined( 'GEMINI_GENERAL_API_KEY' ) ) {
	define( 'GEMINI_GENERAL_API_KEY', enaizi_api_key( 'ENAIZI_GEMINI_GENERAL_API_KEY', 'enaizi_gemini_general_api_key' ) );
}

/* Gemini - xflaxxa.php. Distinct again. */
if ( ! defined( 'GEMINI_FLAXXA_API_KEY' ) ) {
	define( 'GEMINI_FLAXXA_API_KEY', enaizi_api_key( 'ENAIZI_GEMINI_FLAXXA_API_KEY', 'enaizi_gemini_flaxxa_api_key' ) );
}

/* Google Maps - test.php already read this constant, with a hardcoded
 * fallback that has now been removed. */
if ( ! defined( 'GOOGLE_MAPS_API_KEY' ) ) {
	define( 'GOOGLE_MAPS_API_KEY', enaizi_api_key( 'ENAIZI_GOOGLE_MAPS_API_KEY', 'enaizi_google_maps_api_key' ) );
}

/* Google Static Maps - xmap.php used a different key from test.php's. */
if ( ! defined( 'GOOGLE_STATIC_MAPS_API_KEY' ) ) {
	define( 'GOOGLE_STATIC_MAPS_API_KEY', enaizi_api_key( 'ENAIZI_GOOGLE_STATIC_MAPS_API_KEY', 'enaizi_google_static_maps_api_key' ) );
}

/* YTL AI Labs chat completions - xflaxxa.php. */
if ( ! defined( 'YTL_AI_API_KEY' ) ) {
	define( 'YTL_AI_API_KEY', enaizi_api_key( 'ENAIZI_YTL_AI_API_KEY', 'enaizi_ytl_ai_api_key' ) );
}
