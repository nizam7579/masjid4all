<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * [mfa_member_namecard] - replaces enaizi-user's [niz_create_namecard] on
 * /member/digital-card/ (post 217778), per the 2026-08-10 decision to build
 * this in mfa-core rather than keep extending the legacy shortcode. Two
 * states based on whether jet_cct_member.namecard is set:
 *
 * - Not set: slug-selection create form. Same rules as the old shortcode
 *   (lowercase/hyphens only, checked against ALL wp_posts.post_name values
 *   regardless of status, since the created "card" is a real post at that
 *   slug) - not a redesign, just a clean re-implementation.
 * - Set: edit form for job title/introduction/contact/social fields - this
 *   is the genuinely new part. None of this was ever editable before; the
 *   one real-world card with custom content (user "azmiak") was hand-edited
 *   directly in the database, not through any UI.
 *
 * Deliberately does NOT touch [niz_user_namecard] (enaizi-user/shortcodes/
 * user.php) - that shortcode both renders the public card preview AND
 * bakes its output into the underlying post's post_content every time it
 * runs (see its own wp_update_post() call at the end), which is how
 * visiting the public /{slug} URL shows a card at all. This shortcode is
 * the first one on page 217778 and does its form-handling synchronously
 * before returning HTML, so a save here is already in jet_cct_member by
 * the time [niz_user_namecard] renders later in the same page load and
 * re-bakes the post - no redirect or extra re-render needed.
 *
 * Points: mfa_award_points() (not enaizi-user's niz_user_add_points(),
 * which has the cross-user dedup bug documented in barakah.php's own
 * history) - one-time 100pt "Create Name Card" bonus, still guarded by
 * chk_card same as before.
 */

add_shortcode( 'mfa_member_namecard', 'mfa_member_namecard_shortcode' );

/**
 * Return the canonical public URL for a member's name card post.
 *
 * The site permalink structure is authoritative; building /{slug} by hand
 * can point at the homepage when WordPress uses a different post permalink.
 */
function mfa_get_member_namecard_post_id( $user_id ) {
	$post_id = (int) niz_user_field_by_userid( $user_id, 'post_id' );
	if ( $post_id && 'publish' === get_post_status( $post_id ) ) {
		return $post_id;
	}

	$owned_posts = get_posts( array(
		'author'         => (int) $user_id,
		'category_name'  => 'affiliate',
		'fields'         => 'ids',
		'posts_per_page' => 1,
		'post_status'    => 'publish',
	) );

	return $owned_posts ? (int) $owned_posts[0] : 0;
}

function mfa_get_member_namecard_url( $user_id ) {
	$post_id = mfa_get_member_namecard_post_id( $user_id );
	if ( $post_id ) {
		$permalink = get_permalink( $post_id );
		if ( $permalink ) {
			return $permalink;
		}
	}

	$slug = niz_user_field_by_userid( $user_id, 'namecard' );
	return $slug ? home_url( '/' . ltrim( $slug, '/' ) . '/' ) : '';
}

function mfa_member_namecard_shortcode() {
	$user_id = get_current_user_id();
	if ( ! $user_id ) {
		return '';
	}

	$slug          = niz_user_field_by_userid( $user_id, 'namecard' );
	$error         = '';
	$saved_message = '';

	/* ---------------- Create ---------------- */
	if ( empty( $slug ) && isset( $_POST['mfa_namecard_create'] ) ) {
		if ( ! isset( $_POST['mfa_namecard_nonce'] ) || ! wp_verify_nonce( $_POST['mfa_namecard_nonce'], 'mfa_namecard_create' ) ) {
			$error = 'Security check failed. Please try again.';
		} else {
			$slug_input     = sanitize_text_field( wp_unslash( $_POST['namecard_slug'] ?? '' ) );
			$candidate_slug = sanitize_title( $slug_input );

			if ( empty( $candidate_slug ) ) {
				$error = 'Please enter a valid name.';
			} elseif ( ! preg_match( '/^[a-z0-9\-]+$/', $slug_input ) ) {
				$error = 'Please use only lowercase letters, numbers, and hyphens (no spaces).';
			} else {
				global $wpdb;
				$slug_exists = $wpdb->get_var( $wpdb->prepare(
					"SELECT ID FROM {$wpdb->posts} WHERE post_name = %s AND post_status IN ('publish', 'future', 'draft', 'pending')",
					$candidate_slug
				) );

				if ( $slug_exists ) {
					$error = 'This name card URL is already taken. Please try another.';
				} else {
					$name     = niz_user_field_by_userid( $user_id, 'name' );
					$category = get_category_by_slug( 'affiliate' );

					$post_id = wp_insert_post( array(
						'post_title'    => $name ? $name : 'Name Card',
						'post_name'     => $candidate_slug,
						'post_content'  => '[niz_user_namecard]',
						'post_status'   => 'publish',
						'post_type'     => 'post',
						'post_author'   => $user_id,
						'post_category' => $category ? array( $category->term_id ) : array(),
					) );

					if ( is_wp_error( $post_id ) || ! $post_id ) {
						$error = 'Something went wrong creating your name card. Please try again.';
					} else {
						$phone = niz_user_field_by_userid( $user_id, 'phone' );

						niz_user_update_field( $user_id, 'namecard', $candidate_slug );
						niz_user_update_field( $user_id, 'post_id', $post_id );
						niz_user_update_field( $user_id, 'affiliate_wa', $phone );
						niz_user_update_field( $user_id, 'affiliate_phone', $phone );

						if ( function_exists( 'mfa_award_points' ) && 'Yes' !== niz_user_field_by_userid( $user_id, 'chk_card' ) ) {
							mfa_award_points( $user_id, 'Create Name Card', 100 );
							niz_user_update_field( $user_id, 'chk_card', 'Yes' );
						}

						$slug          = $candidate_slug;
						$saved_message = 'Your name card has been created! Fill in the details below to personalize it.';
					}
				}
			}
		}
	}

	/* ---------------- Update ---------------- */
	if ( ! empty( $slug ) && isset( $_POST['mfa_namecard_update'] ) ) {
		if ( ! isset( $_POST['mfa_namecard_nonce'] ) || ! wp_verify_nonce( $_POST['mfa_namecard_nonce'], 'mfa_namecard_update' ) ) {
			$error = 'Security check failed. Please try again.';
		} else {
			$fields = array(
				'job_title'           => 'sanitize_text_field',
				'introduction'        => 'sanitize_textarea_field',
				'affiliate_phone'     => 'sanitize_text_field',
				'affiliate_wa'        => 'sanitize_text_field',
				'affiliate_email'     => 'sanitize_email',
				'affiliate_website'   => 'esc_url_raw',
				'affiliate_fb'        => 'esc_url_raw',
				'affiliate_linkedin'  => 'esc_url_raw',
				'affiliate_x'         => 'esc_url_raw',
				'affiliate_tiktok'    => 'esc_url_raw',
				'affiliate_youtube'   => 'esc_url_raw',
				'affiliate_instagram' => 'esc_url_raw',
			);

			foreach ( $fields as $field => $sanitizer_function ) {
				$value = isset( $_POST[ $field ] ) ? $sanitizer_function( wp_unslash( $_POST[ $field ] ) ) : '';
				niz_user_update_field( $user_id, $field, $value );
			}

			$post_id = mfa_get_member_namecard_post_id( $user_id );
			if ( $post_id && 'post' === get_post_type( $post_id ) ) {
				niz_user_update_field( $user_id, 'post_id', $post_id );
				niz_user_update_field( $user_id, 'namecard', get_post_field( 'post_name', $post_id ) );
				wp_update_post( array(
					'ID'           => $post_id,
					'post_content' => '[niz_user_namecard]',
				) );
			}

			$saved_message = 'Your name card has been updated.';
		}
	}

	$card_url = mfa_get_member_namecard_url( $user_id );

	ob_start();
	?>
	<div class="mfa-namecard-manager">
		<?php if ( $error ) : ?>
			<p class="mfa-modal-message"><?php echo esc_html( $error ); ?></p>
		<?php endif; ?>
		<?php if ( $saved_message ) : ?>
			<p class="mfa-modal-message is-success"><?php echo esc_html( $saved_message ); ?></p>
		<?php endif; ?>

		<?php if ( empty( $slug ) ) : ?>
			<form method="post" class="mfa-modal-form">
				<?php wp_nonce_field( 'mfa_namecard_create', 'mfa_namecard_nonce' ); ?>
				<div class="mfa-form-group">
					<label for="namecard_slug">Choose your Name Card URL</label>
					<div class="mfa-referral-link-row">
						<span class="mfa-namecard-url-prefix"><?php echo esc_html( home_url( '/' ) ); ?></span>
						<input type="text" id="namecard_slug" name="namecard_slug" placeholder="yourname" required>
					</div>
				</div>
				<button type="submit" name="mfa_namecard_create" value="1" class="mfa-btn mfa-btn-primary mfa-modal-submit">Create My Name Card</button>
			</form>
		<?php else : ?>
			<p class="mfa-body-muted">Your name card is live at <a href="<?php echo esc_url( $card_url ); ?>" target="_blank" rel="noopener"><?php echo esc_html( $card_url ); ?></a></p>

			<form method="post" class="mfa-modal-form">
				<?php wp_nonce_field( 'mfa_namecard_update', 'mfa_namecard_nonce' ); ?>
				<div class="mfa-form-group">
					<label for="job_title">Job Title / Tagline</label>
					<input type="text" id="job_title" name="job_title" value="<?php echo esc_attr( niz_user_field_by_userid( $user_id, 'job_title' ) ); ?>">
				</div>
				<div class="mfa-form-group">
					<label for="introduction">Introduction</label>
					<textarea id="introduction" name="introduction" rows="4"><?php echo esc_textarea( niz_user_field_by_userid( $user_id, 'introduction' ) ); ?></textarea>
				</div>
				<div class="mfa-form-row">
					<div class="mfa-form-group">
						<label for="affiliate_phone">Phone</label>
						<input type="text" id="affiliate_phone" name="affiliate_phone" value="<?php echo esc_attr( niz_user_field_by_userid( $user_id, 'affiliate_phone' ) ); ?>">
					</div>
					<div class="mfa-form-group">
						<label for="affiliate_wa">WhatsApp</label>
						<input type="text" id="affiliate_wa" name="affiliate_wa" value="<?php echo esc_attr( niz_user_field_by_userid( $user_id, 'affiliate_wa' ) ); ?>">
					</div>
				</div>
				<div class="mfa-form-row">
					<div class="mfa-form-group">
						<label for="affiliate_email">Email</label>
						<input type="email" id="affiliate_email" name="affiliate_email" value="<?php echo esc_attr( niz_user_field_by_userid( $user_id, 'affiliate_email' ) ); ?>">
					</div>
					<div class="mfa-form-group">
						<label for="affiliate_website">Website</label>
						<input type="url" id="affiliate_website" name="affiliate_website" value="<?php echo esc_attr( niz_user_field_by_userid( $user_id, 'affiliate_website' ) ); ?>">
					</div>
				</div>
				<div class="mfa-form-row">
					<div class="mfa-form-group">
						<label for="affiliate_fb">Facebook</label>
						<input type="url" id="affiliate_fb" name="affiliate_fb" value="<?php echo esc_attr( niz_user_field_by_userid( $user_id, 'affiliate_fb' ) ); ?>">
					</div>
					<div class="mfa-form-group">
						<label for="affiliate_linkedin">LinkedIn</label>
						<input type="url" id="affiliate_linkedin" name="affiliate_linkedin" value="<?php echo esc_attr( niz_user_field_by_userid( $user_id, 'affiliate_linkedin' ) ); ?>">
					</div>
				</div>
				<div class="mfa-form-row">
					<div class="mfa-form-group">
						<label for="affiliate_x">X / Twitter</label>
						<input type="url" id="affiliate_x" name="affiliate_x" value="<?php echo esc_attr( niz_user_field_by_userid( $user_id, 'affiliate_x' ) ); ?>">
					</div>
					<div class="mfa-form-group">
						<label for="affiliate_instagram">Instagram</label>
						<input type="url" id="affiliate_instagram" name="affiliate_instagram" value="<?php echo esc_attr( niz_user_field_by_userid( $user_id, 'affiliate_instagram' ) ); ?>">
					</div>
				</div>
				<div class="mfa-form-row">
					<div class="mfa-form-group">
						<label for="affiliate_tiktok">TikTok</label>
						<input type="url" id="affiliate_tiktok" name="affiliate_tiktok" value="<?php echo esc_attr( niz_user_field_by_userid( $user_id, 'affiliate_tiktok' ) ); ?>">
					</div>
					<div class="mfa-form-group">
						<label for="affiliate_youtube">YouTube</label>
						<input type="url" id="affiliate_youtube" name="affiliate_youtube" value="<?php echo esc_attr( niz_user_field_by_userid( $user_id, 'affiliate_youtube' ) ); ?>">
					</div>
				</div>
				<button type="submit" name="mfa_namecard_update" value="1" class="mfa-btn mfa-btn-primary mfa-modal-submit">Save Name Card</button>
			</form>
		<?php endif; ?>
	</div>
	<?php
	return ob_get_clean();
}
