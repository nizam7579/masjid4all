<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Conditional asset loading for the widget shortcodes moved from
 * enaizi-mfa/enaizi-user. Each CSS/JS pair only loads on pages that use
 * the matching shortcode, instead of the old sitewide-unconditional
 * enqueue. LiteSpeed exclusion is preserved for prayer-times/qibla since
 * their client-side hydration timing depends on not being combined/deferred.
 */
add_action( 'wp_enqueue_scripts', 'mfa_core_enqueue_widget_assets' );
function mfa_core_enqueue_widget_assets() {
	if ( is_admin() ) {
		return;
	}

	$post = get_post();
	$content = $post ? $post->post_content : '';
	$post_name = $post ? $post->post_name : '';

	$get_version = function ( $path ) {
		return file_exists( $path ) ? filemtime( $path ) : MFA_CORE_VERSION;
	};

	// The post_content check alone misses pages that embed the widget via
	// a PHP-wrapped page shortcode (e.g. [mfa_prayer_times_page]) rather
	// than the raw [niz_mfa_prayer_times] text — has_shortcode() only sees
	// literal post_content, not shortcodes nested inside another
	// shortcode's PHP callback. Same class of bug fixed for /quran/ earlier.
	if ( has_shortcode( $content, 'niz_mfa_prayer_times' ) || 'prayer-times' === $post_name || 'homepage' === $post_name ) {
		$css = MFA_CORE_PATH . 'assets/css/prayer-times-v3.css';
		$js  = MFA_CORE_PATH . 'assets/js/prayer-times.js';
		wp_enqueue_style( 'mfa-core-prayer-times', MFA_CORE_URL . 'assets/css/prayer-times-v3.css', array(), $get_version( $css ) );
		wp_enqueue_script( 'mfa-core-prayer-times', MFA_CORE_URL . 'assets/js/prayer-times.js', array(), $get_version( $js ), true );
	}

	if ( has_shortcode( $content, 'niz_mfa_qibla' ) || 'qibla-finder' === $post_name || 'homepage' === $post_name ) {
		$css = MFA_CORE_PATH . 'assets/css/qibla-v4.css';
		$js  = MFA_CORE_PATH . 'assets/js/qibla-v2.js';
		wp_enqueue_style( 'mfa-core-qibla', MFA_CORE_URL . 'assets/css/qibla-v4.css', array(), $get_version( $css ) );
		wp_enqueue_script( 'mfa-core-qibla', MFA_CORE_URL . 'assets/js/qibla-v2.js', array(), $get_version( $js ), true );
	}

	$is_quran_page = $post && ( 'quran' === $post->post_name || 'quran' === $post->post_type );
	if ( has_shortcode( $content, 'daily_quran' ) || has_shortcode( $content, 'quran_surah_selector' ) || $is_quran_page || 'homepage' === $post_name ) {
		$css = MFA_CORE_PATH . 'assets/css/quran.css';
		$js  = MFA_CORE_PATH . 'assets/js/quran.js';
		wp_enqueue_style( 'mfa-core-quran', MFA_CORE_URL . 'assets/css/quran.css', array(), $get_version( $css ) );
		wp_enqueue_script( 'mfa-core-quran', MFA_CORE_URL . 'assets/js/quran.js', array(), $get_version( $js ), true );
	}

	// Global design tokens (colors, spacing, buttons, etc) — sitewide,
	// loaded first so every other mfa-core stylesheet can build on it.
	// See global-v1.css's own header comment for what's in it and why.
	$global_css_path = MFA_CORE_PATH . 'assets/css/global-v2.css';
	wp_enqueue_style( 'mfa-core-global', MFA_CORE_URL . 'assets/css/global-v2.css', array(), $get_version( $global_css_path ) );

	// /member/* and /admin/* both have their own custom header/footer shell
	// (see includes/member-template.php, includes/admin-template.php) that
	// bypasses the public site's chrome entirely - the sitewide header and
	// floating buttons below belong to that public chrome, so they're
	// skipped here rather than loaded unused.
	$is_member_area = function_exists( 'mfa_is_member_area' ) && mfa_is_member_area();
	$is_admin_area  = function_exists( 'mfa_is_admin_area' ) && mfa_is_admin_area();

	if ( $is_admin_area ) {
		$admin_shell_css = MFA_CORE_PATH . 'assets/css/admin-shell-v1.css';
		wp_enqueue_style( 'mfa-core-admin-shell', MFA_CORE_URL . 'assets/css/admin-shell-v1.css', array( 'mfa-core-global' ), $get_version( $admin_shell_css ) );

		if ( $post && 'member' === $post->post_name ) {
			$admin_member_list_css = MFA_CORE_PATH . 'assets/css/admin-member-list-v1.css';
			wp_enqueue_style( 'mfa-core-admin-member-list', MFA_CORE_URL . 'assets/css/admin-member-list-v1.css', array( 'mfa-core-global', 'mfa-core-admin-shell' ), $get_version( $admin_member_list_css ) );
		}

		// /admin/member/info/ - child of the Members page, reuses its
		// .mfa-admin-status-* badge classes plus its own small layout CSS.
		if ( $post && 'info' === $post->post_name && 217771 === (int) $post->post_parent ) {
			$admin_member_list_css = MFA_CORE_PATH . 'assets/css/admin-member-list-v1.css';
			wp_enqueue_style( 'mfa-core-admin-member-list', MFA_CORE_URL . 'assets/css/admin-member-list-v1.css', array( 'mfa-core-global', 'mfa-core-admin-shell' ), $get_version( $admin_member_list_css ) );

			$admin_member_info_css = MFA_CORE_PATH . 'assets/css/admin-member-info-v1.css';
			wp_enqueue_style( 'mfa-core-admin-member-info', MFA_CORE_URL . 'assets/css/admin-member-info-v1.css', array( 'mfa-core-global', 'mfa-core-admin-shell', 'mfa-core-admin-member-list' ), $get_version( $admin_member_info_css ) );
		}

		if ( $post && 'mosque' === $post->post_name && 9343 === (int) $post->post_parent ) {
			$admin_mosque_list_css = MFA_CORE_PATH . 'assets/css/admin-mosque-list-v1.css';
			wp_enqueue_style( 'mfa-core-admin-mosque-list', MFA_CORE_URL . 'assets/css/admin-mosque-list-v1.css', array( 'mfa-core-global', 'mfa-core-admin-shell' ), $get_version( $admin_mosque_list_css ) );
		}

		if ( $post && 'business' === $post->post_name && 9343 === (int) $post->post_parent ) {
			$admin_business_list_css = MFA_CORE_PATH . 'assets/css/admin-business-list-v1.css';
			wp_enqueue_style( 'mfa-core-admin-business-list', MFA_CORE_URL . 'assets/css/admin-business-list-v1.css', array( 'mfa-core-global', 'mfa-core-admin-shell' ), $get_version( $admin_business_list_css ) );
		}

		if ( $post && 'website' === $post->post_name && 9343 === (int) $post->post_parent ) {
			$admin_website_list_css = MFA_CORE_PATH . 'assets/css/admin-website-list-v1.css';
			wp_enqueue_style( 'mfa-core-admin-website-list', MFA_CORE_URL . 'assets/css/admin-website-list-v1.css', array( 'mfa-core-global', 'mfa-core-admin-shell' ), $get_version( $admin_website_list_css ) );
		}

		if ( $post && 'reports' === $post->post_name ) {
			$admin_reports_css = MFA_CORE_PATH . 'assets/css/admin-reports-v1.css';
			wp_enqueue_style( 'mfa-core-admin-reports', MFA_CORE_URL . 'assets/css/admin-reports-v1.css', array( 'mfa-core-global', 'mfa-core-admin-shell' ), $get_version( $admin_reports_css ) );
		}

		// Crawler overview page, plus its child /admin/crawler/start/ page -
		// both are plain PHP-rendered forms (no JS), sharing one stylesheet.
		// Matched by slug, not a fixed parent ID, since staging/live created
		// this page independently and it has a different ID on each site.
		$is_crawler_start = $post && 'start' === $post->post_name && $post->post_parent
			&& 'crawler' === get_post_field( 'post_name', $post->post_parent );
		if ( $post && ( 'crawler' === $post->post_name || $is_crawler_start ) ) {
			$crawler_css = MFA_CORE_PATH . 'assets/css/admin-crawler-v1.css';
			wp_enqueue_style( 'mfa-core-admin-crawler', MFA_CORE_URL . 'assets/css/admin-crawler-v1.css', array( 'mfa-core-global', 'mfa-core-admin-shell' ), $get_version( $crawler_css ) );
		}
	}

	if ( ! $is_member_area && ! $is_admin_area ) {
		// Sitewide header — same reasoning as the floating share button below:
		// not tied to any specific page's post_content.
		$header_css_path = MFA_CORE_PATH . 'assets/css/header-v11.css';
		$header_js_path  = MFA_CORE_PATH . 'assets/js/header-v3.js';
		wp_enqueue_style( 'mfa-core-header', MFA_CORE_URL . 'assets/css/header-v11.css', array(), $get_version( $header_css_path ) );
		wp_enqueue_script( 'mfa-core-header', MFA_CORE_URL . 'assets/js/header-v3.js', array(), $get_version( $header_js_path ), true );

		// Floating footer share button — sitewide, since the Kadence Theme
		// Builder footer isn't part of any specific page's post_content.
		$share_btn_css = MFA_CORE_PATH . 'assets/css/share-button-v15.css';
		$share_btn_js  = MFA_CORE_PATH . 'assets/js/share-button-v13.js';
		wp_enqueue_style( 'mfa-core-share-button', MFA_CORE_URL . 'assets/css/share-button-v15.css', array(), $get_version( $share_btn_css ) );
		wp_enqueue_script( 'mfa-core-share-button', MFA_CORE_URL . 'assets/js/share-button-v13.js', array(), $get_version( $share_btn_js ), true );

		// Floating "Sofia" WhatsApp chat button — sitewide, same reasoning as
		// the share button above (embedded in post 83 alongside it).
		$sofia_btn_css = MFA_CORE_PATH . 'assets/css/sofia-button-v1.css';
		$sofia_btn_js  = MFA_CORE_PATH . 'assets/js/sofia-button-v1.js';
		wp_enqueue_style( 'mfa-core-sofia-button', MFA_CORE_URL . 'assets/css/sofia-button-v1.css', array(), $get_version( $sofia_btn_css ) );
		wp_enqueue_script( 'mfa-core-sofia-button', MFA_CORE_URL . 'assets/js/sofia-button-v1.js', array(), $get_version( $sofia_btn_js ), true );
	} else {
		$member_shell_css = MFA_CORE_PATH . 'assets/css/member-shell-v1.css';
		wp_enqueue_style( 'mfa-core-member-shell', MFA_CORE_URL . 'assets/css/member-shell-v1.css', array( 'mfa-core-global' ), $get_version( $member_shell_css ) );

		$member_dashboard_css = MFA_CORE_PATH . 'assets/css/member-dashboard-v1.css';
		wp_enqueue_style( 'mfa-core-member-dashboard', MFA_CORE_URL . 'assets/css/member-dashboard-v1.css', array( 'mfa-core-global', 'mfa-core-member-shell' ), $get_version( $member_dashboard_css ) );

		$member_modals_css = MFA_CORE_PATH . 'assets/css/member-account-modals-v1.css';
		wp_enqueue_style( 'mfa-core-member-account-modals', MFA_CORE_URL . 'assets/css/member-account-modals-v1.css', array( 'mfa-core-global' ), $get_version( $member_modals_css ) );

		$member_modals_js = MFA_CORE_PATH . 'assets/js/member-account-modals-v1.js';
		wp_enqueue_script( 'mfa-core-member-account-modals', MFA_CORE_URL . 'assets/js/member-account-modals-v1.js', array(), $get_version( $member_modals_js ), true );
		wp_localize_script( 'mfa-core-member-account-modals', 'mfaMemberModals', array(
			'ajaxUrl' => admin_url( 'admin-ajax.php' ),
		) );
	}

	// Logged-out /member visitors get the full public site header (see
	// templates/member-page.php) instead of the minimal member header, so
	// they need its stylesheet + toggle JS - which the public-chrome block
	// above intentionally skips for the member area.
	if ( $is_member_area && ! is_user_logged_in() ) {
		$header_css_path = MFA_CORE_PATH . 'assets/css/header-v11.css';
		$header_js_path  = MFA_CORE_PATH . 'assets/js/header-v3.js';
		wp_enqueue_style( 'mfa-core-header', MFA_CORE_URL . 'assets/css/header-v11.css', array(), $get_version( $header_css_path ) );
		wp_enqueue_script( 'mfa-core-header', MFA_CORE_URL . 'assets/js/header-v3.js', array(), $get_version( $header_js_path ), true );
	}

	// Reusable content/ad two-column layout utility — sitewide, not tied
	// to a specific page or shortcode. See page-layout-v2.css for usage.
	$page_layout_css = MFA_CORE_PATH . 'assets/css/page-layout-v2.css';
	wp_enqueue_style( 'mfa-core-page-layout', MFA_CORE_URL . 'assets/css/page-layout-v2.css', array(), $get_version( $page_layout_css ) );

	// [mfa_coming_soon] badge — sitewide, since it's used inside Theme
	// Builder template content (Review/Claim tabs) that has_shortcode()
	// can't see from the front-end post being viewed.
	$coming_soon_css = MFA_CORE_PATH . 'assets/css/coming-soon-v1.css';
	wp_enqueue_style( 'mfa-core-coming-soon', MFA_CORE_URL . 'assets/css/coming-soon-v1.css', array(), $get_version( $coming_soon_css ) );

	// Auth / confirmation utility pages: return-to-member button + payment
	// status message (see includes/widgets/auth-pages.php).
	$is_auth_return_page = $post && in_array( $post->post_name, array( 'email-verified', 'forgot-password', 'reset-password', 'payment-success', 'payment-failed' ), true );
	if ( $is_auth_return_page ) {
		$auth_return_css = MFA_CORE_PATH . 'assets/css/auth-return-v1.css';
		wp_enqueue_style( 'mfa-core-auth-return', MFA_CORE_URL . 'assets/css/auth-return-v1.css', array( 'mfa-core-global' ), $get_version( $auth_return_css ) );
	}

	// Streamlined Founding Member page at /member/premium/ (member-area page).
	if ( $post && 'premium' === $post->post_name ) {
		$premium_css = MFA_CORE_PATH . 'assets/css/premium-page-v1.css';
		wp_enqueue_style( 'mfa-core-premium', MFA_CORE_URL . 'assets/css/premium-page-v1.css', array( 'mfa-core-global' ), $get_version( $premium_css ) );
	}

	// Contribute-flow pages: Add Mosque / Add Business / Add Website heading
	// + Add Website info section (see includes/widgets/contribute-pages.php).
	if ( $post && in_array( $post->post_name, array( 'add-mosque', 'add-business', 'add-website' ), true ) ) {
		$contribute_css = MFA_CORE_PATH . 'assets/css/contribute-page-v1.css';
		wp_enqueue_style( 'mfa-core-contribute', MFA_CORE_URL . 'assets/css/contribute-page-v1.css', array( 'mfa-core-global' ), $get_version( $contribute_css ) );
	}

	if ( $post && 'homepage' === $post->post_name ) {
		$css = MFA_CORE_PATH . 'assets/css/homepage-v15.css';
		wp_enqueue_style( 'mfa-core-homepage', MFA_CORE_URL . 'assets/css/homepage-v15.css', array(), $get_version( $css ) );
	}

	if ( $is_quran_page ) {
		$css = MFA_CORE_PATH . 'assets/css/quran-page-v8.css';
		wp_enqueue_style( 'mfa-core-quran-page', MFA_CORE_URL . 'assets/css/quran-page-v8.css', array(), $get_version( $css ) );
	}

	$is_tool_page = $post && in_array( $post->post_name, array( 'prayer-times', 'qibla-finder', 'masjid', 'business', 'web', 'knowledge-hub' ), true );
	if ( $is_tool_page ) {
		$css = MFA_CORE_PATH . 'assets/css/tool-page-v9.css';
		wp_enqueue_style( 'mfa-core-tool-page', MFA_CORE_URL . 'assets/css/tool-page-v9.css', array(), $get_version( $css ) );
	}

	$is_brand_page = $post && in_array( $post->post_name, array( 'about-us', 'contact-us' ), true );
	if ( $is_brand_page ) {
		$header_css = MFA_CORE_PATH . 'assets/css/tool-page-v9.css';
		wp_enqueue_style( 'mfa-core-tool-page', MFA_CORE_URL . 'assets/css/tool-page-v9.css', array(), $get_version( $header_css ) );
		$css = MFA_CORE_PATH . 'assets/css/brand-page-v5.css';
		wp_enqueue_style( 'mfa-core-brand-page', MFA_CORE_URL . 'assets/css/brand-page-v5.css', array(), $get_version( $css ) );
	}

	// 'member' uses the full [mfa_member_logged_out] (auth tabs + marketing
	// panel); add-mosque/add-business/add-website and the Review/Claim tabs
	// on single mosque/business/website posts use just [mfa_auth_tabs] for
	// their logged-out visitors (2026-08-09) - same CSS/JS either way. The
	// single-post templates are matched by post_type, not post_name, same
	// pattern as the mosque/business/website single CSS enqueues below.
	$is_auth_tabs_page = $post && ( in_array( $post->post_name, array( 'member', 'add-mosque', 'add-business', 'add-website' ), true ) || in_array( $post->post_type, array( 'masjid', 'business', 'web' ), true ) );
	if ( $is_auth_tabs_page ) {
		$css = MFA_CORE_PATH . 'assets/css/member-logged-out-v5.css';
		wp_enqueue_style( 'mfa-core-member-logged-out', MFA_CORE_URL . 'assets/css/member-logged-out-v5.css', array(), $get_version( $css ) );

		$auth_toggle_js = MFA_CORE_PATH . 'assets/js/member-auth-toggle-v1.js';
		wp_enqueue_script( 'mfa-core-member-auth-toggle', MFA_CORE_URL . 'assets/js/member-auth-toggle-v1.js', array(), $get_version( $auth_toggle_js ), true );
	}

	$is_legal_page = $post && in_array( $post->post_name, array( 'privacy-policy', 'terms-of-service' ), true );
	if ( $is_legal_page ) {
		$header_css = MFA_CORE_PATH . 'assets/css/tool-page-v9.css';
		wp_enqueue_style( 'mfa-core-tool-page', MFA_CORE_URL . 'assets/css/tool-page-v9.css', array(), $get_version( $header_css ) );
		$css = MFA_CORE_PATH . 'assets/css/legal-page-v3.css';
		wp_enqueue_style( 'mfa-core-legal-page', MFA_CORE_URL . 'assets/css/legal-page-v3.css', array(), $get_version( $css ) );
	}

	// Single business post (Kadence Theme Builder "Business" template,
	// [mfa_business_home_tab]). Not detectable via has_shortcode() since it
	// renders through the CPT template, not literal post_content — checked
	// by post_type instead, same pattern as $is_quran_page above.
	if ( $post && 'business' === $post->post_type ) {
		$css = MFA_CORE_PATH . 'assets/css/business-single-v5.css';
		wp_enqueue_style( 'mfa-core-business-single', MFA_CORE_URL . 'assets/css/business-single-v5.css', array(), $get_version( $css ) );

		// [mfa_business_update_form]'s AJAX submit (replaces FluentForm 68).
		// Its .mfa-form-group/.mfa-form-row/.mfa-modal-message field styling
		// comes from mfa-core-member-account-modals (enqueued below,
		// shared with the /member/ Edit Profile form) rather than a
		// duplicate stylesheet.
		$biz_form_js = MFA_CORE_PATH . 'assets/js/business-update-form-v1.js';
		wp_enqueue_script( 'mfa-core-business-update-form', MFA_CORE_URL . 'assets/js/business-update-form-v1.js', array(), $get_version( $biz_form_js ), true );

		// Explicit safety-net enqueue: the Upload Image modal (Update Info
		// converted to [mfa_modal] above) still relies on Kadence
		// Blocks Pro's own kt-modal-init.min.js (MicroModal, handle
		// "kadence-blocks-pro-modal"), which that plugin only auto-enqueues
		// when it detects a literal wp:kadence/modal block in parsed page
		// content — there isn't one here, only matching DOM/attributes, so
		// force it in rather than relying on that detection firing.
		if ( wp_script_is( 'kadence-blocks-pro-modal', 'registered' ) ) {
			wp_enqueue_script( 'kadence-blocks-pro-modal' );

			// Failsafe: confirmed on staging that MicroModal's close handler
			// (awaitCloseAnimation) never removes .is-open here — it waits for
			// an `animationend` event that never fires (reproduced identically
			// on pre-existing, untouched Kadence modals like #TanyaAlya, so
			// this is a site-wide Kadence/LiteSpeed CSS issue, not something
			// caused by this markup). Without this, clicking the close button
			// on Update Info/Upload Image/Share leaves aria-hidden correctly
			// set to "true" but the modal still visually open (display:block).
			// Scoped to just .mfa-biz-modal-wrap so it can't affect any other
			// Kadence modal on the site (Sofia popup, chatbot, etc).
			wp_add_inline_script( 'kadence-blocks-pro-modal', "document.addEventListener('click',function(e){var c=e.target.closest('.mfa-biz-modal-wrap [data-modal-close]');if(!c)return;var m=c.closest('.kadence-block-pro-modal');if(!m)return;setTimeout(function(){m.classList.remove('is-open');},350);});" );
		}
	}

	// Single mosque post (Kadence Theme Builder "Mosque" template,
	// [mfa_mosque_home_tab] / [mfa_mosque_local_business_tab]). Same
	// post_type-based detection and modal safety-net pattern as the
	// business post block above.
	if ( $post && 'masjid' === $post->post_type ) {
		$css = MFA_CORE_PATH . 'assets/css/mosque-single-v2.css';
		wp_enqueue_style( 'mfa-core-mosque-single', MFA_CORE_URL . 'assets/css/mosque-single-v2.css', array(), $get_version( $css ) );

		if ( wp_script_is( 'kadence-blocks-pro-modal', 'registered' ) ) {
			wp_enqueue_script( 'kadence-blocks-pro-modal' );
			wp_add_inline_script( 'kadence-blocks-pro-modal', "document.addEventListener('click',function(e){var c=e.target.closest('.mfa-mosque-modal-wrap [data-modal-close]');if(!c)return;var m=c.closest('.kadence-block-pro-modal');if(!m)return;setTimeout(function(){m.classList.remove('is-open');},350);});" );
		}
	}

	// Single website post (Kadence Theme Builder "Web" template,
	// [mfa_website_home_tab]). Same post_type-based detection and modal
	// safety-net pattern as the business/mosque post blocks above.
	if ( $post && 'web' === $post->post_type ) {
		$css = MFA_CORE_PATH . 'assets/css/website-single-v2.css';
		wp_enqueue_style( 'mfa-core-website-single', MFA_CORE_URL . 'assets/css/website-single-v2.css', array(), $get_version( $css ) );

		if ( wp_script_is( 'kadence-blocks-pro-modal', 'registered' ) ) {
			wp_enqueue_script( 'kadence-blocks-pro-modal' );
			wp_add_inline_script( 'kadence-blocks-pro-modal', "document.addEventListener('click',function(e){var c=e.target.closest('.mfa-web-modal-wrap [data-modal-close]');if(!c)return;var m=c.closest('.kadence-block-pro-modal');if(!m)return;setTimeout(function(){m.classList.remove('is-open');},350);});" );
		}
	}

	// Single knowledge post (Kadence Theme Builder "Knowledge" template,
	// post 193845 - still raw Kadence blocks, not yet rebuilt off Kadence
	// like mosque/business/website). Only fixes the mobile content-row
	// overflow described in knowledge-single.css; no modal on this template.
	if ( $post && 'knowledge' === $post->post_type ) {
		$css = MFA_CORE_PATH . 'assets/css/knowledge-single-v1.css';
		wp_enqueue_style( 'mfa-core-knowledge-single', MFA_CORE_URL . 'assets/css/knowledge-single-v1.css', array(), $get_version( $css ) );
	}

	// Reusable modal ([mfa_modal]) used by the mfa-theme directory single
	// component (Share / Edit / Claim / Add New). Enqueued on the directory
	// CPT singles; harmless while those pages still render via Kadence.
	if ( $post && in_array( $post->post_type, array( 'masjid', 'business', 'web', 'city' ), true ) ) {
		$modal_css = MFA_CORE_PATH . 'assets/css/modal-v1.css';
		wp_enqueue_style( 'mfa-core-modal', MFA_CORE_URL . 'assets/css/modal-v1.css', array( 'mfa-core-global' ), $get_version( $modal_css ) );
		$modal_js = MFA_CORE_PATH . 'assets/js/modal-v1.js';
		wp_enqueue_script( 'mfa-core-modal', MFA_CORE_URL . 'assets/js/modal-v1.js', array(), $get_version( $modal_js ), true );

		$dir_css = MFA_CORE_PATH . 'assets/css/directory-single-v1.css';
		wp_enqueue_style( 'mfa-core-directory-single', MFA_CORE_URL . 'assets/css/directory-single-v1.css', array( 'mfa-core-global' ), $get_version( $dir_css ) );
		$dir_js = MFA_CORE_PATH . 'assets/js/directory-single-v1.js';
		wp_enqueue_script( 'mfa-core-directory-single', MFA_CORE_URL . 'assets/js/directory-single-v1.js', array(), $get_version( $dir_js ), true );
	}
}

add_filter( 'litespeed_optimize_css_excludes', 'mfa_core_litespeed_css_excludes' );
function mfa_core_litespeed_css_excludes( $excludes ) {
	$excludes[] = 'mfa-core/assets/css/prayer-times-v3.css';
	$excludes[] = 'mfa-core/assets/css/qibla-v4.css';
	$excludes[] = 'mfa-core/assets/css/homepage-v15.css';
	// Newly excluded 2026-08-07: a rule targeting this page's unique
	// Kadence column ID was silently dropped when this file went through
	// LiteSpeed's combine/minify pipeline (confirmed present in the raw
	// file, absent from the served combined bundle) - same class of bug
	// documented elsewhere in this project for other files.
	$excludes[] = 'mfa-core/assets/css/member-logged-out-v5.css';
	$excludes[] = 'mfa-core/assets/css/page-layout-v2.css';
	$excludes[] = 'mfa-core/assets/css/quran-page-v8.css';
	$excludes[] = 'mfa-core/assets/css/tool-page-v9.css';
	$excludes[] = 'mfa-core/assets/css/brand-page-v5.css';
	$excludes[] = 'mfa-core/assets/css/legal-page-v3.css';
	$excludes[] = 'mfa-core/assets/css/share-button-v15.css';
	$excludes[] = 'mfa-core/assets/css/sofia-button-v1.css';
	$excludes[] = 'mfa-core/assets/css/business-single-v5.css';
	$excludes[] = 'mfa-core/assets/css/mosque-single-v2.css';
	$excludes[] = 'mfa-core/assets/css/website-single-v2.css';
	$excludes[] = 'mfa-core/assets/css/coming-soon-v1.css';
	$excludes[] = 'mfa-core/assets/css/knowledge-single-v1.css';
	$excludes[] = 'mfa-core/assets/css/header-v11.css';
	$excludes[] = 'mfa-core/assets/css/member-shell-v1.css';
	$excludes[] = 'mfa-core/assets/css/member-dashboard-v1.css';
	$excludes[] = 'mfa-core/assets/css/member-account-modals-v1.css';
	$excludes[] = 'mfa-core/assets/css/global-v2.css';
	$excludes[] = 'mfa-core/assets/css/admin-shell-v1.css';
	$excludes[] = 'mfa-core/assets/css/admin-member-list-v1.css';
	$excludes[] = 'mfa-core/assets/css/admin-member-info-v1.css';
	$excludes[] = 'mfa-core/assets/css/admin-mosque-list-v1.css';
	$excludes[] = 'mfa-core/assets/css/admin-business-list-v1.css';
	$excludes[] = 'mfa-core/assets/css/admin-website-list-v1.css';
	$excludes[] = 'mfa-core/assets/css/admin-reports-v1.css';
	$excludes[] = 'mfa-core/assets/css/admin-crawler-v1.css';
	return $excludes;
}
