<?php
/**
 * Detail pages for a mosque, business or website - and, the reason they
 * exist, the people attached to each one.
 *
 *   /admin/mosque/info/?id=<jet_cct_mosque._ID>
 *   /admin/business/info/?id=<jet_cct_business._ID>
 *   /admin/website/info/?id=<jet_cct_web._ID>
 *
 * `id` is the CCT's own _ID, matching what the list pages link with, not a
 * WP post id. The three types share one renderer because they differ only
 * in table, labels, and where their people come from:
 *
 *   mosque            -> jet_cct_community, joined by mosque_id = _ID
 *   business/website  -> jet_cct_listing_owner, joined by post_id =
 *                        cct_single_post_id (that table stores the WP post
 *                        id, NOT the CCT _ID - see includes/listing-status.php)
 *
 * ## No sending from here, on purpose
 *
 * Every contact action lives on /admin/member/info/ and nowhere else. A
 * person row here links there instead of repeating Send Email / Send
 * WhatsApp / Send Template, because those carry the placeholder-address
 * rule, Meta's 24-hour window rule, a capability re-check and activity
 * logging. Four copies of that would be four places for the rules to
 * drift apart. What this page does show is a read-only badge of whether
 * someone is reachable, so the decision to click through can be made
 * before clicking.
 *
 * @package mfa-core
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Per-type configuration. `section` is the admin access-control section,
 * so the existing role model gates these pages unchanged.
 */
function mfa_admin_listing_types() {
	return array(
		'mosque'   => array(
			'table'        => 'jet_cct_mosque',
			'section'      => 'mosque',
			'label'        => 'Mosque',
			'list_url'     => '/admin/mosque/',
			'people_title' => 'Community members',
			'people_empty' => 'Nobody has joined this community yet.',
			'people_note'  => 'A mosque becomes Active as soon as one person joins.',
		),
		'business' => array(
			'table'        => 'jet_cct_business',
			'section'      => 'business',
			'label'        => 'Business',
			'list_url'     => '/admin/business/',
			'owner_type'   => 'business',
			'people_title' => 'Claimed by',
			'people_empty' => 'Nobody has claimed this listing yet.',
			'people_note'  => 'A claimed listing becomes Verified once it has been approved.',
		),
		'website'  => array(
			'table'        => 'jet_cct_web',
			'section'      => 'website',
			'label'        => 'Website',
			'list_url'     => '/admin/website/',
			'owner_type'   => 'web',
			'people_title' => 'Claimed by',
			'people_empty' => 'Nobody has claimed this listing yet.',
			'people_note'  => 'A claimed listing becomes Verified once it has been approved.',
		),
	);
}

/**
 * The people attached to one listing.
 *
 * Returns rows of user_id / name / since, newest first. A row whose user
 * no longer exists is still returned - knowing a claim points at a deleted
 * account is more useful than the row quietly vanishing.
 */
function mfa_admin_listing_people( $type, $row ) {
	global $wpdb;

	$people = array();

	if ( 'mosque' === $type ) {
		$found = $wpdb->get_results( $wpdb->prepare(
			"SELECT user_id, full_name, status, cct_created
			 FROM {$wpdb->prefix}jet_cct_community
			 WHERE mosque_id = %d
			 ORDER BY cct_created DESC",
			(int) $row['_ID']
		), ARRAY_A );

		foreach ( $found as $f ) {
			$people[] = array(
				'user_id' => (int) $f['user_id'],
				'name'    => (string) $f['full_name'],
				'since'   => (string) $f['cct_created'],
				'role'    => (string) $f['status'],
			);
		}

		return $people;
	}

	$types = mfa_admin_listing_types();
	$owner_type = isset( $types[ $type ]['owner_type'] ) ? $types[ $type ]['owner_type'] : '';
	$post_id    = isset( $row['cct_single_post_id'] ) ? (int) $row['cct_single_post_id'] : 0;

	if ( ! $owner_type || $post_id <= 0 ) {
		return $people;
	}

	$found = $wpdb->get_results( $wpdb->prepare(
		"SELECT user_id, cct_created FROM {$wpdb->prefix}jet_cct_listing_owner
		 WHERE post_type = %s AND post_id = %d
		 ORDER BY cct_created DESC",
		$owner_type,
		$post_id
	), ARRAY_A );

	foreach ( $found as $f ) {
		$user = get_userdata( (int) $f['user_id'] );
		$people[] = array(
			'user_id' => (int) $f['user_id'],
			'name'    => $user ? $user->display_name : '',
			'since'   => (string) $f['cct_created'],
			'role'    => 'owner',
		);
	}

	return $people;
}

/**
 * Compact, read-only reachability badge for a person row.
 *
 * Reuses mfa_admin_member_contact_state() so this page and the member page
 * can never disagree about whether somebody can be contacted - but it only
 * reads. The buttons stay where the rules are enforced.
 */
function mfa_admin_listing_reach_badge( $user_id ) {
	if ( ! function_exists( 'mfa_admin_member_contact_state' ) ) {
		return '';
	}

	$state = mfa_admin_member_contact_state( $user_id );
	$out   = '';

	// Always says something about each channel. An absent badge would read as
	// "unknown" when it actually means "cannot reach them that way".
	if ( $state['can_whatsapp'] ) {
		$out .= '<span class="mfa-admin-check-badge is-ok">WhatsApp open</span>';
	} elseif ( $state['can_template'] ) {
		$out .= '<span class="mfa-admin-check-badge is-warn">Template only</span>';
	} else {
		$out .= '<span class="mfa-admin-check-badge is-no">No WhatsApp</span>';
	}

	if ( $state['can_email'] ) {
		$out .= '<span class="mfa-admin-check-badge is-ok">Email</span>';
	} else {
		$out .= '<span class="mfa-admin-check-badge is-no">No email</span>';
	}

	return $out;
}

/** Shared renderer for all three types. */
function mfa_admin_listing_info_render( $type ) {
	$types = mfa_admin_listing_types();
	if ( ! isset( $types[ $type ] ) ) {
		return '';
	}

	$cfg = $types[ $type ];

	if ( function_exists( 'mfa_admin_require_section_access' ) ) {
		$no_access = mfa_admin_require_section_access( $cfg['section'] );
		if ( $no_access ) {
			return $no_access;
		}
	}

	global $wpdb;

	$id = isset( $_GET['id'] ) ? absint( $_GET['id'] ) : 0;

	ob_start();
	?>
	<div class="mfa-admin-listing-info">
		<?php
		if ( ! $id ) {
			echo '<p class="mfa-body-muted">No ' . esc_html( strtolower( $cfg['label'] ) ) . ' specified.</p>';
		} else {
			$table = $wpdb->prefix . $cfg['table'];
			$row   = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE _ID = %d", $id ), ARRAY_A );

			if ( ! $row ) {
				echo '<p class="mfa-body-muted">' . esc_html( $cfg['label'] ) . ' not found.</p>';
			} else {
				$people    = mfa_admin_listing_people( $type, $row );
				$post_id   = isset( $row['cct_single_post_id'] ) ? (int) $row['cct_single_post_id'] : 0;
				$public    = $post_id ? get_permalink( $post_id ) : '';
				if ( ! $public && ! empty( $row['page_url'] ) ) {
					$public = $row['page_url'];
				}
				$status = isset( $row['listing_status'] ) ? (string) $row['listing_status'] : '';
				?>
				<div class="mfa-admin-listing-head">
					<div>
						<a class="mfa-admin-listing-back" href="<?php echo esc_url( home_url( $cfg['list_url'] ) ); ?>">&larr; All <?php echo esc_html( strtolower( $cfg['label'] ) ); ?>s</a>
						<h1 class="mfa-h2"><?php echo esc_html( ! empty( $row['name'] ) ? $row['name'] : '—' ); ?></h1>
					</div>
					<?php if ( $public ) : ?>
						<a href="<?php echo esc_url( $public ); ?>" target="_blank" rel="noopener" class="mfa-btn mfa-btn-secondary">View public page</a>
					<?php endif; ?>
				</div>

				<div class="mfa-admin-listing-grid">
					<div class="mfa-admin-listing-item">
						<span class="mfa-label">Status</span>
						<span class="mfa-body">
							<?php if ( '' !== $status ) : ?>
								<span class="mfa-admin-status-badge mfa-admin-status-<?php echo esc_attr( sanitize_html_class( strtolower( $status ) ) ); ?>"><?php echo esc_html( $status ); ?></span>
							<?php else : ?>
								&mdash;
							<?php endif; ?>
						</span>
					</div>
					<?php
					// Only the columns a given table actually has - jet_cct_web
					// carries no city/state, and only mosque has member_count.
					$fields = array(
						'city'    => 'City',
						'state'   => 'State',
						'country' => 'Country',
						'email'   => 'Email',
						'phone'   => 'Phone',
						'whatsapp'=> 'WhatsApp',
						'url'     => 'URL',
						'category'=> 'Category',
					);
					foreach ( $fields as $key => $label ) :
						if ( ! array_key_exists( $key, $row ) || '' === trim( (string) $row[ $key ] ) ) {
							continue;
						}
						?>
						<div class="mfa-admin-listing-item">
							<span class="mfa-label"><?php echo esc_html( $label ); ?></span>
							<span class="mfa-body"><?php echo esc_html( $row[ $key ] ); ?></span>
						</div>
					<?php endforeach; ?>
					<?php if ( array_key_exists( 'member_count', $row ) ) : ?>
						<div class="mfa-admin-listing-item">
							<span class="mfa-label">Community</span>
							<span class="mfa-body">
								<?php echo esc_html( number_format_i18n( (int) $row['member_count'] ) ); ?>
								<?php echo esc_html( _n( 'member', 'members', (int) $row['member_count'], 'mfa-core' ) ); ?>
								<?php if ( ! empty( $row['community_status'] ) ) : ?>
									&middot; <?php echo esc_html( ucfirst( $row['community_status'] ) ); ?>
								<?php endif; ?>
							</span>
						</div>
					<?php endif; ?>
					<div class="mfa-admin-listing-item">
						<span class="mfa-label">Added</span>
						<span class="mfa-body"><?php echo esc_html( ! empty( $row['cct_created'] ) ? date_i18n( 'j M Y, g:i a', strtotime( $row['cct_created'] ) ) : '—' ); ?></span>
					</div>
				</div>

				<section class="mfa-admin-listing-people">
					<h2 class="mfa-h3"><?php echo esc_html( $cfg['people_title'] ); ?>
						<span class="mfa-admin-tab-count"><?php echo esc_html( number_format_i18n( count( $people ) ) ); ?></span>
					</h2>

					<?php if ( ! $people ) : ?>
						<p class="mfa-body-muted"><?php echo esc_html( $cfg['people_empty'] ); ?></p>
					<?php else : ?>
						<div class="mfa-admin-listing-tablewrap">
							<table class="mfa-admin-listing-table">
								<thead>
									<tr><th>Name</th><th>Since</th><th>Reachable</th><th></th></tr>
								</thead>
								<tbody>
								<?php foreach ( $people as $person ) :
									$user = $person['user_id'] ? get_userdata( $person['user_id'] ) : null;
									$name = $person['name'] ? $person['name'] : ( $user ? $user->display_name : '' );
									?>
									<tr>
										<td data-label="Name">
											<?php echo esc_html( $name ? $name : '—' ); ?>
											<?php if ( ! $user ) : ?>
												<span class="mfa-admin-check-badge is-no">account deleted</span>
											<?php endif; ?>
										</td>
										<td data-label="Since"><?php echo esc_html( $person['since'] ? date_i18n( 'j M Y', strtotime( $person['since'] ) ) : '—' ); ?></td>
										<td data-label="Reachable">
											<?php echo $user ? wp_kses_post( mfa_admin_listing_reach_badge( $person['user_id'] ) ) : '&mdash;'; ?>
										</td>
										<td data-label="">
											<?php if ( $user ) : ?>
												<a class="mfa-btn mfa-btn-solid-dark mfa-admin-listing-contact" href="<?php echo esc_url( add_query_arg( 'id', $person['user_id'], home_url( '/admin/member/info/' ) ) ); ?>">Open &amp; contact</a>
											<?php endif; ?>
										</td>
									</tr>
								<?php endforeach; ?>
								</tbody>
							</table>
						</div>
					<?php endif; ?>

					<?php if ( ! empty( $cfg['people_note'] ) ) : ?>
						<p class="mfa-admin-listing-note"><?php echo esc_html( $cfg['people_note'] ); ?></p>
					<?php endif; ?>
				</section>
				<?php
			}
		}
		?>
	</div>
	<?php
	return ob_get_clean();
}

add_shortcode( 'mfa_admin_mosque_info', 'mfa_admin_mosque_info_shortcode' );
function mfa_admin_mosque_info_shortcode() {
	return mfa_admin_listing_info_render( 'mosque' );
}

add_shortcode( 'mfa_admin_business_info', 'mfa_admin_business_info_shortcode' );
function mfa_admin_business_info_shortcode() {
	return mfa_admin_listing_info_render( 'business' );
}

add_shortcode( 'mfa_admin_website_info', 'mfa_admin_website_info_shortcode' );
function mfa_admin_website_info_shortcode() {
	return mfa_admin_listing_info_render( 'website' );
}
