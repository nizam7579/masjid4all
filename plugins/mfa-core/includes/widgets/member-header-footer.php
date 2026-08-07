<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * [mfa_member_header] / [mfa_member_footer] - the custom chrome for
 * /member/* pages (see includes/member-template.php), replacing the
 * public site's [mfa_site_header]/Kadence footer entirely on this area.
 * Deliberately minimal: a single way back to the public site plus
 * account controls, not the full public nav - the member area is a
 * separate shell, not a themed variant of the public header.
 */

add_shortcode( 'mfa_member_header', 'mfa_member_header_shortcode' );
function mfa_member_header_shortcode() {
	$is_logged_in = is_user_logged_in();
	$initial      = 'M';
	$display_name = '';

	if ( $is_logged_in ) {
		$user         = wp_get_current_user();
		$display_name = $user->display_name ? $user->display_name : $user->user_login;
		$initial      = strtoupper( mb_substr( $display_name, 0, 1 ) );
	}

	ob_start();
	?>
	<header class="mfa-member-header">
		<div class="mfa-member-header-inner">
			<a href="/" class="mfa-member-header-back">
				<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="19" y1="12" x2="5" y2="12"></line><polyline points="12 19 5 12 12 5"></polyline></svg>
				<span>Masjid4All</span>
			</a>

			<?php if ( $is_logged_in ) : ?>
				<div class="mfa-member-header-account">
					<span class="mfa-member-header-avatar" aria-hidden="true"><?php echo esc_html( $initial ); ?></span>
					<span class="mfa-member-header-name"><?php echo esc_html( $display_name ); ?></span>
					<?php echo do_shortcode( '[niz_user_logout]' ); ?>
				</div>
			<?php endif; ?>
		</div>
	</header>
	<?php
	return ob_get_clean();
}

add_shortcode( 'mfa_member_footer', 'mfa_member_footer_shortcode' );
function mfa_member_footer_shortcode() {
	ob_start();
	?>
	<footer class="mfa-member-footer">
		<div class="mfa-member-footer-inner">
			<span class="mfa-member-footer-copy">&copy; <?php echo esc_html( date( 'Y' ) ); ?> Masjid4All</span>
			<nav class="mfa-member-footer-links" aria-label="Legal">
				<a href="/privacy-policy/">Privacy</a>
				<a href="/terms-of-service/">Terms</a>
				<a href="/contact-us/">Contact</a>
			</nav>
		</div>
	</footer>
	<?php
	return ob_get_clean();
}
