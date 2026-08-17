<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * [mfa_admin_knowledge_ai_generate] - /admin/knowledge/ai/generate/, a
 * child of /admin/knowledge/ai/. Two modes, both sharing
 * mfa_knowledge_ai_generate_content():
 *
 * - Bulk mode (no `id` query arg): same self-redirecting, one-record-per-
 *   page-load pattern as /admin/website/generate/ (see
 *   admin-website-generate-start.php) - each load claims the oldest
 *   pending AI-suggested draft (_mfa_ai_status = 'pending'), generates it,
 *   then redirects back to this same URL. Opened from /admin/knowledge/ai/'s
 *   "Generate Content for Pending" link.
 * - Single-post mode (`?id=123`): generates content for exactly that one
 *   `knowledge` post - any Draft post with empty content, not just ones
 *   that went through the "Suggest Topics" flow - then redirects to
 *   /admin/knowledge/ (the list) once done, success or error, rather than
 *   looping to a next record. Opened from the "AI Content" button
 *   admin-knowledge-list.php shows per-row on Draft/empty-content posts.
 *
 * Content stays a draft either way - publishing is a separate, human step,
 * unlike the website/mosque "Generate Content" flows which auto-approve.
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
		$id = isset( $_GET['id'] ) ? absint( $_GET['id'] ) : 0;
		return $id ? mfa_admin_knowledge_ai_generate_single( $id ) : mfa_admin_knowledge_ai_generate_attempt();
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

/**
 * Single-post mode: generate content for exactly one `knowledge` post
 * (`?id=`), then redirect to the Knowledge Hub list either way - no
 * "next record" to move to here, unlike bulk mode.
 */
function mfa_admin_knowledge_ai_generate_single( $id ) {
	$list_url = home_url( '/admin/knowledge/' );
	$post     = get_post( $id );

	if ( ! $post || 'knowledge' !== $post->post_type ) {
		return '<div class="mfa-crawler-banner is-paused">&#9208; Not a valid Knowledge Hub post.</div><p class="mfa-crawler-hint"><a href="' . esc_url( $list_url ) . '">&larr; Back to Knowledge Hub</a></p>';
	}

	if ( 'draft' !== $post->post_status || '' !== trim( wp_strip_all_tags( $post->post_content ) ) ) {
		ob_start();
		?>
		<div class="mfa-crawler-banner is-paused">&#9208; "<?php echo esc_html( $post->post_title ); ?>" is no longer a Draft with empty content - nothing to do.</div>
		<script>setTimeout( function () { location.href = <?php echo wp_json_encode( $list_url ); ?>; }, 2000 );</script>
		<?php
		return ob_get_clean();
	}

	if ( ! function_exists( 'mfa_knowledge_ai_generate_content' ) ) {
		return '<div class="mfa-crawler-banner is-paused">&#9208; Content generation is unavailable (mfa_knowledge_ai_generate_content() missing).</div>';
	}

	$result = mfa_knowledge_ai_generate_content( $id );

	if ( is_wp_error( $result ) ) {
		update_post_meta( $id, '_mfa_ai_status', 'error' );
		update_post_meta( $id, '_mfa_ai_error', $result->get_error_message() );

		ob_start();
		?>
		<div class="mfa-crawler-banner is-paused">
			&#9208; "<?php echo esc_html( $post->post_title ); ?>" failed: <?php echo esc_html( $result->get_error_message() ); ?>
		</div>
		<p class="mfa-crawler-hint">Going back to the Knowledge Hub&hellip;</p>
		<script>setTimeout( function () { location.href = <?php echo wp_json_encode( $list_url ); ?>; }, <?php echo (int) wp_rand( 2500, 4000 ); ?> );</script>
		<?php
		return ob_get_clean();
	}

	ob_start();
	?>
	<p class="mfa-crawler-hint">
		&#9989; "<?php echo esc_html( $post->post_title ); ?>" &mdash; content generated, saved as draft. Going back to the Knowledge Hub&hellip;
	</p>
	<script>setTimeout( function () { location.href = <?php echo wp_json_encode( $list_url ); ?>; }, <?php echo (int) wp_rand( 1500, 2500 ); ?> );</script>
	<?php
	return ob_get_clean();
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
