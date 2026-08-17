<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * [mfa_admin_knowledge_ai] - /admin/knowledge/ai/, the "AI Content" page
 * linked from /admin/knowledge/'s heading button. Two-step Knowledge Hub
 * content pipeline (see includes/knowledge-ai.php for the actual DeepSeek
 * calls): a "Suggest Topics" button creates draft `knowledge` posts with
 * only a title/excerpt/keywords/category (no content yet) so a human sees
 * the proposal BEFORE any content-generation call is made; a "Generate
 * Content" link opens /admin/knowledge/ai/generate/ (self-triggering,
 * one-record-per-load, same pattern as /admin/website/generate/) to write
 * the full article for each pending suggestion, still saved as a draft for
 * a human to review and publish - not auto-published.
 */

add_shortcode( 'mfa_admin_knowledge_ai', 'mfa_admin_knowledge_ai_shortcode' );
function mfa_admin_knowledge_ai_shortcode() {
	if ( function_exists( 'mfa_admin_require_section_access' ) ) {
		$no_access = mfa_admin_require_section_access( 'knowledge' );
		if ( $no_access ) {
			return $no_access;
		}
	}

	$notice = '';

	if (
		isset( $_POST['mfa_knowledge_ai_nonce'], $_POST['mfa_knowledge_ai_suggest'] )
		&& wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['mfa_knowledge_ai_nonce'] ) ), 'mfa_knowledge_ai_action' )
		&& current_user_can( 'edit_posts' )
	) {
		$result = mfa_knowledge_ai_suggest_topics( 10 );

		if ( is_wp_error( $result ) ) {
			$notice = '<div class="mfa-crawler-banner is-paused">&#9208; ' . esc_html( $result->get_error_message() ) . '</div>';
		} else {
			$created_count = count( $result['created'] );
			$skipped_count = count( $result['skipped'] );
			$notice        = '<div class="mfa-crawler-hint">&#9989; ' . (int) $created_count . ' new topic(s) suggested'
				. ( $skipped_count ? ', ' . (int) $skipped_count . ' skipped as too similar to existing content' : '' ) . '.</div>';
		}
	}

	$pending = get_posts( array(
		'post_type'      => 'knowledge',
		'post_status'    => 'draft',
		'posts_per_page' => -1,
		'meta_key'       => '_mfa_ai_status',
		'meta_value'     => 'pending',
		'orderby'        => 'ID',
		'order'          => 'ASC',
	) );

	$generated = get_posts( array(
		'post_type'      => 'knowledge',
		'post_status'    => 'draft',
		'posts_per_page' => -1,
		'meta_key'       => '_mfa_ai_status',
		'meta_value'     => 'generated',
		'orderby'        => 'ID',
		'order'          => 'DESC',
	) );

	$generate_url = home_url( '/admin/knowledge/ai/generate/' );

	ob_start();
	?>
	<div class="mfa-admin-knowledge-ai">
		<div class="mfa-admin-knowledge-list-heading">
			<div>
				<h1 class="mfa-h2">AI Content</h1>
				<p class="mfa-body-muted">Suggest new Knowledge Hub topics and generate SEO-ready content with DeepSeek.</p>
			</div>
			<a href="<?php echo esc_url( home_url( '/admin/knowledge/' ) ); ?>" class="mfa-btn mfa-btn-solid-dark">&larr; Back to Knowledge Hub</a>
		</div>

		<?php echo $notice; // phpcs:ignore -- built from escaped pieces above. ?>

		<form method="post" class="mfa-admin-knowledge-ai-actions">
			<?php wp_nonce_field( 'mfa_knowledge_ai_action', 'mfa_knowledge_ai_nonce' ); ?>
			<button type="submit" name="mfa_knowledge_ai_suggest" value="1" class="mfa-btn mfa-btn-primary">Suggest 10 New Topics</button>
			<?php if ( $pending ) : ?>
				<a href="<?php echo esc_url( $generate_url ); ?>" target="_blank" rel="noopener" class="mfa-btn mfa-btn-solid-dark">Generate Content for Pending (<?php echo (int) count( $pending ); ?>)</a>
			<?php endif; ?>
		</form>

		<?php if ( $pending ) : ?>
			<h2 class="mfa-h3">Pending suggestions &mdash; awaiting content</h2>
			<div class="mfa-admin-knowledge-ai-cards">
				<?php foreach ( $pending as $p ) :
					$keywords = get_post_meta( $p->ID, '_mfa_ai_keywords', true );
					$cats     = get_the_terms( $p->ID, 'knowledge-category' );
					$cat_name = ( $cats && ! is_wp_error( $cats ) && ! empty( $cats ) ) ? $cats[0]->name : '-';
					?>
					<div class="mfa-admin-knowledge-ai-card">
						<span class="mfa-admin-status-badge mfa-admin-status-pending"><?php echo esc_html( $cat_name ); ?></span>
						<h3><?php echo esc_html( $p->post_title ); ?></h3>
						<p class="mfa-body-muted"><?php echo esc_html( $p->post_excerpt ); ?></p>
						<?php if ( $keywords ) : ?>
							<p class="mfa-admin-knowledge-ai-keywords"><?php echo esc_html( $keywords ); ?></p>
						<?php endif; ?>
					</div>
				<?php endforeach; ?>
			</div>
		<?php endif; ?>

		<?php if ( $generated ) : ?>
			<h2 class="mfa-h3">Content generated &mdash; ready for review</h2>
			<div class="mfa-admin-knowledge-ai-cards">
				<?php foreach ( $generated as $p ) :
					$cats     = get_the_terms( $p->ID, 'knowledge-category' );
					$cat_name = ( $cats && ! is_wp_error( $cats ) && ! empty( $cats ) ) ? $cats[0]->name : '-';
					$edit_url = admin_url( 'post.php?post=' . $p->ID . '&action=edit' );
					?>
					<div class="mfa-admin-knowledge-ai-card">
						<span class="mfa-admin-status-badge mfa-admin-status-draft"><?php echo esc_html( $cat_name ); ?></span>
						<h3><?php echo esc_html( $p->post_title ); ?></h3>
						<p class="mfa-body-muted"><?php echo esc_html( $p->post_excerpt ); ?></p>
						<a href="<?php echo esc_url( $edit_url ); ?>" target="_blank" rel="noopener" class="mfa-btn mfa-btn-solid-dark">Review &amp; Publish</a>
					</div>
				<?php endforeach; ?>
			</div>
		<?php endif; ?>

		<?php if ( ! $pending && ! $generated ) : ?>
			<p class="mfa-body-muted">No AI suggestions yet. Click "Suggest 10 New Topics" to get started.</p>
		<?php endif; ?>
	</div>
	<?php
	return ob_get_clean();
}
