<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * [mfa_admin_knowledge_ai_generate] - /admin/knowledge/ai/generate/, a
 * child of /admin/knowledge/ai/. Same self-redirecting, one-record-per-
 * page-load pattern as /admin/website/generate/ (see
 * admin-website-generate-start.php): each load claims the oldest pending
 * AI-suggested `knowledge` draft (_mfa_ai_status = 'pending'), writes its
 * full content via mfa_knowledge_ai_generate_content(), then redirects
 * itself back to this same URL after a short pause. Opened in a new tab
 * from /admin/knowledge/ai/'s "Generate Content for Pending" link so the
 * admin can watch progress live and stop at any point just by closing the
 * tab. Content stays a draft either way - publishing is a separate, human
 * step, unlike the website/mosque "Generate Content" flows which do
 * auto-approve.
 */

add_shortcode( 'mfa_admin_knowledge_ai_generate', 'mfa_admin_knowledge_ai_generate_shortcode' );
function mfa_admin_knowledge_ai_generate_shortcode() {
	if ( function_exists( 'mfa_admin_require_section_access' ) ) {
		$no_access = mfa_admin_require_section_access( 'knowledge' );
		if ( $no_access ) {
			return $no_access;
		}
	} elseif ( ! current_user_can( 'manage_options' ) ) {
		return '<p>You do not have permission to view this page.</p>';
	}

	$list_url = home_url( '/admin/knowledge/ai/' );

	ob_start();
	?>
	<div class="mfa-crawler">
		<h1 class="mfa-h2">Generate Knowledge Hub Content</h1>
		<?php echo mfa_admin_knowledge_ai_generate_render(); ?>
		<p class="mfa-crawler-hint">Close this tab any time to stop. <a href="<?php echo esc_url( $list_url ); ?>">&larr; Back to AI Content</a></p>
	</div>
	<?php
	return ob_get_clean();
}

/**
 * Catches genuine PHP-level failures the same way
 * mfa_admin_website_generate_start_render() does - without this, an
 * unhandled Throwable here would fatal the whole page out with no output
 * at all, including no redirect script, silently killing the tab for good.
 */
function mfa_admin_knowledge_ai_generate_render() {
	try {
		return mfa_admin_knowledge_ai_generate_attempt();
	} catch ( Throwable $e ) {
		$next_url = home_url( '/admin/knowledge/ai/generate/' );
		ob_start();
		?>
		<div class="mfa-crawler-banner is-paused">&#9208; Unexpected error &mdash; retrying automatically.</div>
		<p class="mfa-crawler-hint"><?php echo esc_html( $e->getMessage() ); ?></p>
		<script>setTimeout( function () { location.href = <?php echo wp_json_encode( $next_url ); ?>; }, <?php echo (int) wp_rand( 15000, 30000 ); ?> );</script>
		<?php
		return ob_get_clean();
	}
}

function mfa_admin_knowledge_ai_generate_attempt() {
	$next_url = home_url( '/admin/knowledge/ai/generate/' );

	$pending = get_posts( array(
		'post_type'      => 'knowledge',
		'post_status'    => 'draft',
		'posts_per_page' => 1,
		'meta_key'       => '_mfa_ai_status',
		'meta_value'     => 'pending',
		'orderby'        => 'ID',
		'order'          => 'ASC',
	) );

	if ( ! $pending ) {
		return '<p class="mfa-crawler-hint">&#127881; All done &mdash; no pending suggestions left to generate.</p>';
	}

	$post = $pending[0];

	if ( ! function_exists( 'mfa_knowledge_ai_generate_content' ) ) {
		return '<div class="mfa-crawler-banner is-paused">&#9208; Content generation is unavailable (mfa_knowledge_ai_generate_content() missing).</div>';
	}

	$result = mfa_knowledge_ai_generate_content( $post->ID );

	if ( is_wp_error( $result ) ) {
		update_post_meta( $post->ID, '_mfa_ai_status', 'error' );
		update_post_meta( $post->ID, '_mfa_ai_error', $result->get_error_message() );

		ob_start();
		?>
		<div class="mfa-crawler-banner is-paused">
			&#9208; "<?php echo esc_html( $post->post_title ); ?>" failed: <?php echo esc_html( $result->get_error_message() ); ?> &mdash; marked Error, moving on.
		</div>
		<script>setTimeout( function () { location.href = <?php echo wp_json_encode( $next_url ); ?>; }, <?php echo (int) wp_rand( 1500, 3000 ); ?> );</script>
		<?php
		return ob_get_clean();
	}

	$remaining_ids = get_posts( array(
		'post_type'      => 'knowledge',
		'post_status'    => 'draft',
		'posts_per_page' => -1,
		'meta_key'       => '_mfa_ai_status',
		'meta_value'     => 'pending',
		'fields'         => 'ids',
	) );
	$remaining     = count( $remaining_ids );
	$edit_url      = admin_url( 'post.php?post=' . (int) $post->ID . '&action=edit' );

	ob_start();
	?>
	<p class="mfa-crawler-hint">
		&#9989; "<?php echo esc_html( $post->post_title ); ?>" &mdash; content generated, saved as draft.
		<a href="<?php echo esc_url( $edit_url ); ?>" target="_blank" rel="noopener">Review &rarr;</a>
		<?php echo number_format_i18n( $remaining ); ?> more to go. Loading the next record&hellip;
	</p>
	<script>setTimeout( function () { location.href = <?php echo wp_json_encode( $next_url ); ?>; }, <?php echo (int) wp_rand( 2000, 4000 ); ?> );</script>
	<?php
	return ob_get_clean();
}
