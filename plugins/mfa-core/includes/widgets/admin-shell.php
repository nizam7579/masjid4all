<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * [mfa_admin_header] / [mfa_admin_footer] - chrome for the /admin/* staff
 * area (see includes/admin-template.php). Header is now a horizontal top
 * nav across the 7 sub-sections + account cluster (2026-08-10 - replaced
 * the old left sidebar entirely; [mfa_admin_sidebar] removed, nothing
 * calls it anymore). Root (/admin/, id 9343) and /admin/member/info/
 * (id 217911) render with no header at all - see
 * mfa_admin_page_hides_chrome() in admin-template.php. [mfa_admin_home]
 * is the landing content for the /admin/ root page itself.
 */

function mfa_admin_nav_items() {
	return array(
		array( 'label' => 'Dashboard', 'url' => home_url( '/admin/' ),          'icon' => 'dashboard', 'section' => 'dashboard' ),
		array( 'label' => 'Inquiry',   'url' => home_url( '/admin/inquiry/' ),  'icon' => 'mail',      'section' => 'inquiry' ),
		array( 'label' => 'Members',   'url' => home_url( '/admin/member/' ),   'icon' => 'users',     'section' => 'member' ),
		array( 'label' => 'WhatsApp',  'url' => home_url( '/admin/whatsapp/' ), 'icon' => 'chat',      'section' => 'whatsapp' ),
		array( 'label' => 'Mosque',    'url' => home_url( '/admin/mosque/' ),   'icon' => 'mosque',    'section' => 'mosque' ),
		array( 'label' => 'Business',  'url' => home_url( '/admin/business/' ), 'icon' => 'briefcase', 'section' => 'business' ),
		array( 'label' => 'Website',        'url' => home_url( '/admin/website/' ),   'icon' => 'globe', 'section' => 'website' ),
		array( 'label' => 'Knowledge Base', 'url' => home_url( '/admin/knowledge/' ), 'icon' => 'book',   'section' => 'knowledge' ),
		array( 'label' => 'Blogs',          'url' => home_url( '/admin/blog/' ),      'icon' => 'edit',   'section' => 'blog' ),
		array( 'label' => 'Reports',        'url' => home_url( '/admin/reports/' ),   'icon' => 'chart',  'section' => 'reports' ),
		array( 'label' => 'Crawler',        'url' => home_url( '/admin/crawler/' ),   'icon' => 'radar',  'section' => 'crawler' ),
	);
}

/**
 * Whether the current user can reach a nav item's section - true when the
 * role model isn't loaded (defensive default, shouldn't happen since
 * admin-access-control.php loads before this file in mfa-core.php's
 * includes array) so a missing dependency fails open to "show it" rather
 * than silently hiding every section.
 */
function mfa_admin_nav_item_has_access( $item ) {
	return ! function_exists( 'mfa_user_can_access_admin_section' ) || mfa_user_can_access_admin_section( $item['section'] );
}

/**
 * Path-prefix match instead of a hardcoded post ID: staging and production
 * assign different post IDs to the same /admin/* page (their ID sequences
 * diverged after the first few pages), so matching by ID silently breaks
 * active-tab highlighting on whichever environment doesn't have the ID
 * baked in here. The URL is already environment-correct (home_url()), so
 * comparing paths works identically on both. Dashboard's '/admin/' is a
 * prefix of every other admin path, so it's always active alongside
 * whichever section is current - same behavior the old ancestor check had
 * (every admin page is a descendant of the root).
 */
function mfa_admin_nav_is_active( $item_url ) {
	$request_path = isset( $_SERVER['REQUEST_URI'] ) ? wp_parse_url( wp_unslash( $_SERVER['REQUEST_URI'] ), PHP_URL_PATH ) : '';
	$request_path = '/' . trim( (string) $request_path, '/' ) . '/';
	$item_path    = '/' . trim( (string) wp_parse_url( $item_url, PHP_URL_PATH ), '/' ) . '/';

	return 0 === strpos( $request_path, $item_path );
}

function mfa_admin_nav_icon_svg( $icon ) {
	$icons = array(
		'dashboard' => '<rect x="3" y="3" width="7" height="7"></rect><rect x="14" y="3" width="7" height="7"></rect><rect x="14" y="14" width="7" height="7"></rect><rect x="3" y="14" width="7" height="7"></rect>',
		'mail'      => '<rect x="2" y="4" width="20" height="16" rx="2"></rect><path d="m22 7-8.97 5.7a1.94 1.94 0 0 1-2.06 0L2 7"></path>',
		'users'     => '<path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"></path><circle cx="9" cy="7" r="4"></circle><path d="M22 21v-2a4 4 0 0 0-3-3.87"></path><path d="M16 3.13a4 4 0 0 1 0 7.75"></path>',
		'chat'      => '<path d="M21 11.5a8.38 8.38 0 0 1-.9 3.8 8.5 8.5 0 0 1-7.6 4.7 8.38 8.38 0 0 1-3.8-.9L3 21l1.9-5.7a8.38 8.38 0 0 1-.9-3.8 8.5 8.5 0 0 1 4.7-7.6 8.38 8.38 0 0 1 3.8-.9h.5a8.48 8.48 0 0 1 8 8v.5z"></path>',
		'mosque'    => '<path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"></path><polyline points="9 22 9 12 15 12 15 22"></polyline>',
		'briefcase' => '<rect x="2" y="7" width="20" height="14" rx="2" ry="2"></rect><path d="M16 21V5a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v16"></path>',
		'globe'     => '<circle cx="12" cy="12" r="10"></circle><line x1="2" y1="12" x2="22" y2="12"></line><path d="M12 2a15.3 15.3 0 0 1 4 10 15.3 15.3 0 0 1-4 10 15.3 15.3 0 0 1-4-10 15.3 15.3 0 0 1 4-10z"></path>',
		'chart'     => '<line x1="18" y1="20" x2="18" y2="10"></line><line x1="12" y1="20" x2="12" y2="4"></line><line x1="6" y1="20" x2="6" y2="14"></line>',
		'book'      => '<path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20"></path><path d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2z"></path>',
		'edit'      => '<path d="M12 20h9"></path><path d="M16.5 3.5a2.121 2.121 0 0 1 3 3L7 19l-4 1 1-4L16.5 3.5z"></path>',
		'radar'     => '<circle cx="12" cy="12" r="10"></circle><circle cx="12" cy="12" r="6"></circle><circle cx="12" cy="12" r="2"></circle>',
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
			<nav class="mfa-admin-topnav" aria-label="Admin sections">
				<?php foreach ( mfa_admin_nav_items() as $item ) :
					$is_active  = mfa_admin_nav_is_active( $item['url'] );
					$has_access = mfa_admin_nav_item_has_access( $item );
					?>
					<a href="<?php echo esc_url( $item['url'] ); ?>" class="mfa-admin-topnav-link<?php echo $is_active ? ' is-active' : ''; ?><?php echo ! $has_access ? ' is-no-access' : ''; ?>">
						<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><?php echo mfa_admin_nav_icon_svg( $item['icon'] ); ?></svg>
						<span><?php echo esc_html( $item['label'] ); ?></span>
						<?php if ( ! $has_access ) : ?><small class="mfa-admin-topnav-noaccess">No Access</small><?php endif; ?>
					</a>
				<?php endforeach; ?>
			</nav>

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

	// Skip "Dashboard" in this page's own card grid - a link back to the
	// page you're already on isn't useful here (it's only meaningful in
	// the top nav shown on every *other* admin page).
	$nav_items = array_filter( mfa_admin_nav_items(), function ( $item ) {
		return 'Dashboard' !== $item['label'];
	} );

	ob_start();
	?>
	<div class="mfa-admin-home">
		<h1 class="mfa-h2">Assalamualaikum, <?php echo esc_html( $name ); ?></h1>
		<p class="mfa-body-muted">Choose a section to get started.</p>
		<div class="mfa-admin-home-grid">
			<?php foreach ( $nav_items as $item ) :
				$has_access = mfa_admin_nav_item_has_access( $item );
				?>
				<a href="<?php echo esc_url( $item['url'] ); ?>" class="mfa-admin-home-card<?php echo ! $has_access ? ' is-no-access' : ''; ?>">
					<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><?php echo mfa_admin_nav_icon_svg( $item['icon'] ); ?></svg>
					<span><?php echo esc_html( $item['label'] ); ?></span>
					<?php if ( ! $has_access ) : ?><small class="mfa-admin-home-card-noaccess">No Access</small><?php endif; ?>
				</a>
			<?php endforeach; ?>
		</div>
	</div>
	<?php
	return ob_get_clean();
}
