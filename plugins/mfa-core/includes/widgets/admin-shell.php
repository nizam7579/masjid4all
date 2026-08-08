<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * [mfa_admin_header] / [mfa_admin_sidebar] / [mfa_admin_footer] - chrome
 * for the /admin/* staff area (see includes/admin-template.php). Header
 * mirrors [mfa_member_header]'s back-link + account cluster; the sidebar
 * is the persistent "menu" across the 6 sub-sections - new for this area,
 * since /member/'s own chrome is deliberately nav-less (its nav lives in
 * dashboard content instead, see member-dashboard.php). [mfa_admin_home]
 * is the landing content for the /admin/ root page itself.
 */

function mfa_admin_nav_items() {
	return array(
		array( 'id' => 229457, 'label' => 'Inquiry',  'url' => home_url( '/admin/inquiry/' ),  'icon' => 'mail' ),
		array( 'id' => 217771, 'label' => 'Members',  'url' => home_url( '/admin/member/' ),   'icon' => 'users' ),
		array( 'id' => 66564,  'label' => 'WhatsApp',  'url' => home_url( '/admin/whatsapp/' ), 'icon' => 'chat' ),
		array( 'id' => 33096,  'label' => 'Business',  'url' => home_url( '/admin/business/' ), 'icon' => 'briefcase' ),
		array( 'id' => 229366, 'label' => 'Website',   'url' => home_url( '/admin/website/' ),  'icon' => 'globe' ),
		array( 'id' => 229449, 'label' => 'Reports',   'url' => home_url( '/admin/reports/' ),  'icon' => 'chart' ),
	);
}

function mfa_admin_nav_icon_svg( $icon ) {
	$icons = array(
		'mail'      => '<rect x="2" y="4" width="20" height="16" rx="2"></rect><path d="m22 7-8.97 5.7a1.94 1.94 0 0 1-2.06 0L2 7"></path>',
		'users'     => '<path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"></path><circle cx="9" cy="7" r="4"></circle><path d="M22 21v-2a4 4 0 0 0-3-3.87"></path><path d="M16 3.13a4 4 0 0 1 0 7.75"></path>',
		'chat'      => '<path d="M21 11.5a8.38 8.38 0 0 1-.9 3.8 8.5 8.5 0 0 1-7.6 4.7 8.38 8.38 0 0 1-3.8-.9L3 21l1.9-5.7a8.38 8.38 0 0 1-.9-3.8 8.5 8.5 0 0 1 4.7-7.6 8.38 8.38 0 0 1 3.8-.9h.5a8.48 8.48 0 0 1 8 8v.5z"></path>',
		'briefcase' => '<rect x="2" y="7" width="20" height="14" rx="2" ry="2"></rect><path d="M16 21V5a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v16"></path>',
		'globe'     => '<circle cx="12" cy="12" r="10"></circle><line x1="2" y1="12" x2="22" y2="12"></line><path d="M12 2a15.3 15.3 0 0 1 4 10 15.3 15.3 0 0 1-4 10 15.3 15.3 0 0 1-4-10 15.3 15.3 0 0 1 4-10z"></path>',
		'chart'     => '<line x1="18" y1="20" x2="18" y2="10"></line><line x1="12" y1="20" x2="12" y2="4"></line><line x1="6" y1="20" x2="6" y2="14"></line>',
	);
	return isset( $icons[ $icon ] ) ? $icons[ $icon ] : '';
}

add_shortcode( 'mfa_admin_header', 'mfa_admin_header_shortcode' );
function mfa_admin_header_shortcode() {
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
	<header class="mfa-admin-header">
		<div class="mfa-admin-header-inner">
			<a href="/" class="mfa-admin-header-back">
				<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="19" y1="12" x2="5" y2="12"></line><polyline points="12 19 5 12 12 5"></polyline></svg>
				<span>Masjid4All Admin</span>
			</a>

			<?php if ( $is_logged_in ) : ?>
				<div class="mfa-admin-header-account">
					<span class="mfa-admin-header-avatar" aria-hidden="true"><?php echo esc_html( $initial ); ?></span>
					<span class="mfa-admin-header-name"><?php echo esc_html( $display_name ); ?></span>
					<?php echo do_shortcode( '[niz_user_logout]' ); ?>
				</div>
			<?php endif; ?>
		</div>
	</header>
	<?php
	return ob_get_clean();
}

add_shortcode( 'mfa_admin_sidebar', 'mfa_admin_sidebar_shortcode' );
function mfa_admin_sidebar_shortcode() {
	$current_id = get_queried_object_id();
	$ancestors  = $current_id ? get_post_ancestors( $current_id ) : array();

	ob_start();
	?>
	<nav class="mfa-admin-sidebar" aria-label="Admin sections">
		<ul class="mfa-admin-sidebar-list">
			<?php foreach ( mfa_admin_nav_items() as $item ) :
				$is_active = ( $current_id === $item['id'] ) || in_array( $item['id'], $ancestors, true );
				?>
				<li>
					<a href="<?php echo esc_url( $item['url'] ); ?>" class="mfa-admin-sidebar-link<?php echo $is_active ? ' is-active' : ''; ?>">
						<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><?php echo mfa_admin_nav_icon_svg( $item['icon'] ); ?></svg>
						<span><?php echo esc_html( $item['label'] ); ?></span>
					</a>
				</li>
			<?php endforeach; ?>
		</ul>
	</nav>
	<?php
	return ob_get_clean();
}

add_shortcode( 'mfa_admin_footer', 'mfa_admin_footer_shortcode' );
function mfa_admin_footer_shortcode() {
	ob_start();
	?>
	<footer class="mfa-admin-footer">
		<span class="mfa-admin-footer-copy">&copy; <?php echo esc_html( gmdate( 'Y' ) ); ?> Masjid4All &mdash; Staff Admin</span>
	</footer>
	<?php
	return ob_get_clean();
}

add_shortcode( 'mfa_admin_home', 'mfa_admin_home_shortcode' );
function mfa_admin_home_shortcode() {
	$user = wp_get_current_user();
	$name = $user->display_name ? $user->display_name : $user->user_login;

	ob_start();
	?>
	<div class="mfa-admin-home">
		<h1 class="mfa-h2">Assalamualaikum, <?php echo esc_html( $name ); ?></h1>
		<p class="mfa-body-muted">Choose a section to get started.</p>
		<div class="mfa-admin-home-grid">
			<?php foreach ( mfa_admin_nav_items() as $item ) : ?>
				<a href="<?php echo esc_url( $item['url'] ); ?>" class="mfa-admin-home-card">
					<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><?php echo mfa_admin_nav_icon_svg( $item['icon'] ); ?></svg>
					<span><?php echo esc_html( $item['label'] ); ?></span>
				</a>
			<?php endforeach; ?>
		</div>
	</div>
	<?php
	return ob_get_clean();
}
