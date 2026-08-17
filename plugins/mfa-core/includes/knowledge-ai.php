<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * AI Content for the Knowledge Hub ([mfa_admin_knowledge_ai] /
 * [mfa_admin_knowledge_ai_generate], widgets/admin-knowledge-ai*.php).
 * Two-step flow: (1) suggest new, non-duplicate topics as draft `knowledge`
 * posts with title/excerpt/keywords only, for a human to see BEFORE any
 * content is written; (2) generate the full article body + RankMath SEO
 * meta for an approved-by-appearing suggestion, still saved as a draft for
 * final human review/publish (deliberately not auto-published - see
 * [[project admin module]] AI Content discussion, 2026-08-17).
 *
 * Uses DeepSeek directly via the DEEPSEEK_API_KEY wp-config constant
 * (already defined for niz-wa's own optional AI-provider setting) rather
 * than routing through niz-wa's NWA_AI class - this is unrelated to
 * WhatsApp/niz-wa settings and mfa-core must not depend on niz-wa
 * internals, per the plugin-boundary rule.
 */

/**
 * Falls back to niz-wa's own configured DeepSeek key (Niz WA > Settings)
 * when no DEEPSEEK_API_KEY wp-config constant is defined - same
 * constant-wins/option-fallback shape already used elsewhere in this
 * codebase (e.g. mfa_website_perplexity()'s PERPLEXITY_API_KEY lookup),
 * just reading niz-wa's DB option rather than calling into its PHP, so
 * this stays a one-way, read-only convenience rather than a real
 * dependency on niz-wa being active. Only used when niz-wa's own provider
 * is actually set to deepseek, so an Anthropic/OpenRouter key never gets
 * sent here as a DeepSeek bearer token by mistake.
 */
function mfa_knowledge_ai_deepseek_key() {
	if ( defined( 'DEEPSEEK_API_KEY' ) && DEEPSEEK_API_KEY ) {
		return DEEPSEEK_API_KEY;
	}

	$nwa_settings = get_option( 'nwa_settings', array() );
	if ( is_array( $nwa_settings ) && 'deepseek' === ( $nwa_settings['ai_provider'] ?? '' ) && ! empty( $nwa_settings['ai_api_key'] ) ) {
		return $nwa_settings['ai_api_key'];
	}

	return '';
}

function mfa_knowledge_ai_call_deepseek( $system_prompt, $user_message, $max_tokens = 3000 ) {
	$api_key = mfa_knowledge_ai_deepseek_key();
	if ( empty( $api_key ) ) {
		return new WP_Error( 'mfa_knowledge_ai_no_key', 'No DeepSeek API key found - set DEEPSEEK_API_KEY in wp-config.php or configure it under Niz WA > Settings.' );
	}

	$response = wp_remote_post( 'https://api.deepseek.com/v1/chat/completions', array(
		'headers' => array(
			'Authorization' => 'Bearer ' . $api_key,
			'Content-Type'  => 'application/json',
		),
		'body'    => wp_json_encode( array(
			'model'       => 'deepseek-chat',
			'messages'    => array(
				array( 'role' => 'system', 'content' => $system_prompt ),
				array( 'role' => 'user', 'content' => $user_message ),
			),
			'temperature' => 0.7,
			'max_tokens'  => $max_tokens,
		) ),
		'timeout' => 60,
	) );

	if ( is_wp_error( $response ) ) {
		return $response;
	}

	$code = wp_remote_retrieve_response_code( $response );
	$body = wp_remote_retrieve_body( $response );

	if ( $code < 200 || $code >= 300 ) {
		return new WP_Error( 'mfa_knowledge_ai_http_error', 'DeepSeek HTTP ' . $code . ': ' . $body );
	}

	$data    = json_decode( $body, true );
	$content = $data['choices'][0]['message']['content'] ?? '';
	if ( '' === $content ) {
		return new WP_Error( 'mfa_knowledge_ai_empty', 'DeepSeek returned an empty response.' );
	}

	return $content;
}

/**
 * DeepSeek is instructed to return raw JSON but sometimes wraps it in a
 * ```json fence anyway - strip that before decoding, same defensive step
 * niz-wa's own AI class takes for the same reason.
 */
function mfa_knowledge_ai_strip_json_fences( $text ) {
	$text = trim( (string) $text );
	$text = preg_replace( '/^```(?:json)?\s*/i', '', $text );
	$text = preg_replace( '/```\s*$/', '', $text );
	return trim( (string) $text );
}

function mfa_knowledge_ai_normalize_title( $title ) {
	$title = strtolower( (string) $title );
	$title = preg_replace( '/[^a-z0-9\s]/', '', $title );
	$title = preg_replace( '/\s+/', ' ', $title );
	return trim( (string) $title );
}

/**
 * Compact "category => existing titles" text block fed to the suggestion
 * prompt so the AI can see what already exists and both avoid duplicating
 * it and favor categories with fewer existing articles. Capped per category
 * (this site currently has at most 16 articles in any one category, so the
 * cap is a safety margin, not an active limit today).
 */
function mfa_knowledge_ai_existing_summary( $limit_per_category = 40 ) {
	$terms = get_terms( array( 'taxonomy' => 'knowledge-category', 'hide_empty' => false ) );
	if ( is_wp_error( $terms ) || empty( $terms ) ) {
		return '';
	}

	$lines = array();
	foreach ( $terms as $term ) {
		$ids = get_posts( array(
			'post_type'      => 'knowledge',
			'post_status'    => array( 'publish', 'draft', 'pending' ),
			'posts_per_page' => $limit_per_category,
			'fields'         => 'ids',
			'tax_query'      => array( array(
				'taxonomy' => 'knowledge-category',
				'field'    => 'slug',
				'terms'    => $term->slug,
			) ),
		) );
		$titles  = array_map( 'get_the_title', $ids );
		$lines[] = "### {$term->name} ({$term->count} existing)\n" . ( $titles ? '- ' . implode( "\n- ", $titles ) : '(none yet)' );
	}

	return implode( "\n\n", $lines );
}

/**
 * Step 1: ask DeepSeek for $count new topic ideas, create each as a draft
 * `knowledge` post (title + excerpt + category only, no content yet) so a
 * human can review title/excerpt/keywords on the AI Content page before any
 * content-generation call is spent on it. Returns
 * array('created' => post IDs, 'skipped' => titles rejected as duplicates).
 */
function mfa_knowledge_ai_suggest_topics( $count = 10 ) {
	$existing_summary = mfa_knowledge_ai_existing_summary();
	$category_names    = get_terms( array( 'taxonomy' => 'knowledge-category', 'hide_empty' => false, 'fields' => 'names' ) );
	if ( is_wp_error( $category_names ) ) {
		$category_names = array();
	}
	$category_list = implode( ', ', $category_names );

	$system = 'You are an Islamic content strategist for a knowledge hub covering Fiqh, Quran, Hadith, and daily Muslim '
		. "life topics. You propose NEW article topics that are not duplicates or close variations of existing articles. "
		. "Favor categories that have fewer existing articles, to help the hub reach even coverage across all categories.\n\n"
		. "Respond ONLY with a JSON array, no markdown fences, no explanation, in this exact shape:\n"
		. '[{"title":"...","excerpt":"...","keywords":"comma, separated, keywords","category":"exact category name from the list"}]';

	$user = "Existing categories: {$category_list}\n\n"
		. "Existing articles by category:\n{$existing_summary}\n\n"
		. "Propose {$count} new article topics. Each title must be clearly distinct in subject from every existing title "
		. "above - not a rephrasing, not a narrower or broader version of an existing title. "
		. "excerpt: 1-2 sentences summarizing what the article would cover. "
		. "keywords: 3-5 realistic search phrases a Muslim reader might use to find this topic. "
		. "category must exactly match one of the existing category names given above.";

	$raw = mfa_knowledge_ai_call_deepseek( $system, $user, 3000 );
	if ( is_wp_error( $raw ) ) {
		return $raw;
	}

	$topics = json_decode( mfa_knowledge_ai_strip_json_fences( $raw ), true );
	if ( ! is_array( $topics ) || empty( $topics ) ) {
		return new WP_Error( 'mfa_knowledge_ai_parse_error', 'Could not parse DeepSeek\'s response as a topic list.' );
	}

	global $wpdb;
	$existing_titles = $wpdb->get_col( "SELECT post_title FROM {$wpdb->posts} WHERE post_type = 'knowledge' AND post_status != 'trash'" );
	$existing_norm    = array_map( 'mfa_knowledge_ai_normalize_title', $existing_titles );

	$created = array();
	$skipped = array();

	foreach ( $topics as $topic ) {
		$title = sanitize_text_field( $topic['title'] ?? '' );
		if ( '' === $title ) {
			continue;
		}

		$norm = mfa_knowledge_ai_normalize_title( $title );
		if ( in_array( $norm, $existing_norm, true ) ) {
			$skipped[] = $title;
			continue;
		}

		$excerpt  = sanitize_textarea_field( $topic['excerpt'] ?? '' );
		$keywords = sanitize_text_field( $topic['keywords'] ?? '' );
		$category = sanitize_text_field( $topic['category'] ?? '' );

		$post_id = wp_insert_post( array(
			'post_type'    => 'knowledge',
			'post_status'  => 'draft',
			'post_title'   => $title,
			'post_excerpt' => $excerpt,
		), true );

		if ( is_wp_error( $post_id ) ) {
			continue;
		}

		if ( '' !== $category && term_exists( $category, 'knowledge-category' ) ) {
			wp_set_object_terms( $post_id, $category, 'knowledge-category' );
		}

		update_post_meta( $post_id, '_mfa_ai_status', 'pending' );
		update_post_meta( $post_id, '_mfa_ai_keywords', $keywords );

		$created[]      = $post_id;
		$existing_norm[] = $norm; // also guard against near-dupes within this same batch.
	}

	return array( 'created' => $created, 'skipped' => $skipped );
}

/**
 * Step 2: write the full article for one already-suggested draft. Uses one
 * random existing published article (same category when possible) purely
 * as a tone/structure/length reference, not to copy its content, so new
 * articles read consistently with what's already on the site. Sets
 * post_content plus RankMath's focus-keyword/meta-description postmeta
 * (the same fields the site's other AI-content flows, e.g. website.php's
 * Perplexity generator, already populate for real SEO value). Leaves the
 * post as a draft either way - publishing is a separate, human step.
 */
function mfa_knowledge_ai_generate_content( $post_id ) {
	$post = get_post( $post_id );
	if ( ! $post || 'knowledge' !== $post->post_type ) {
		return new WP_Error( 'mfa_knowledge_ai_invalid_post', 'Not a valid Knowledge Hub post.' );
	}

	$keywords = get_post_meta( $post_id, '_mfa_ai_keywords', true );
	$terms    = get_the_terms( $post_id, 'knowledge-category' );
	$category = ( $terms && ! is_wp_error( $terms ) && ! empty( $terms ) ) ? $terms[0]->name : '';

	$reference_args = array(
		'post_type'      => 'knowledge',
		'post_status'    => 'publish',
		'posts_per_page' => 1,
		'orderby'        => 'rand',
		'post__not_in'   => array( $post_id ),
	);
	if ( '' !== $category ) {
		$reference_args['tax_query'] = array( array(
			'taxonomy' => 'knowledge-category',
			'field'    => 'name',
			'terms'    => $category,
		) );
	}
	$reference_posts = get_posts( $reference_args );
	$reference_post  = $reference_posts ? $reference_posts[0] : null;
	$reference_text  = $reference_post
		? "Title: {$reference_post->post_title}\n\n" . wp_strip_all_tags( $reference_post->post_content )
		: '';

	$system = 'You are an expert Islamic content writer and SEO editor for a Muslim-friendly knowledge hub. Write clear, '
		. "accurate, well-structured articles for a general Muslim audience, respecting mainstream Sunni scholarly "
		. "consensus where rulings are discussed, and noting where scholars differ rather than presenting one view as "
		. "universally agreed. Use proper HTML for WordPress: <h2>/<h3> subheadings, <p> paragraphs, <ul>/<li> lists "
		. "where useful. Do not fabricate Quran verses or hadith citations - only cite ones you are confident are "
		. "accurately attributed; prefer general guidance over a specific citation if unsure.\n\n"
		. "Respond ONLY with a JSON object, no markdown fences, no explanation, in this exact shape:\n"
		. '{"content":"<full HTML article body>","meta_description":"...","focus_keyword":"..."}';

	$user = "Write a full article for this topic:\n"
		. "Title: {$post->post_title}\n"
		. "Excerpt/brief: {$post->post_excerpt}\n"
		. "Category: {$category}\n"
		. "Target keywords: {$keywords}\n\n"
		. ( $reference_text ? "For tone, structure, and approximate length only (not content to copy), here is an existing article on this site:\n\n{$reference_text}\n\n" : '' )
		. 'meta_description must be under 155 characters. focus_keyword should be the single most important phrase from the target keywords.';

	$raw = mfa_knowledge_ai_call_deepseek( $system, $user, 3500 );
	if ( is_wp_error( $raw ) ) {
		return $raw;
	}

	$json = json_decode( mfa_knowledge_ai_strip_json_fences( $raw ), true );
	if ( ! is_array( $json ) || empty( $json['content'] ) ) {
		return new WP_Error( 'mfa_knowledge_ai_parse_error', 'Could not parse DeepSeek\'s response as article content.' );
	}

	wp_update_post( array(
		'ID'           => $post_id,
		'post_content' => wp_kses_post( $json['content'] ),
	) );

	if ( ! empty( $json['meta_description'] ) ) {
		update_post_meta( $post_id, 'rank_math_description', sanitize_text_field( $json['meta_description'] ) );
	}
	if ( ! empty( $json['focus_keyword'] ) ) {
		update_post_meta( $post_id, 'rank_math_focus_keyword', sanitize_text_field( $json['focus_keyword'] ) );
	}

	update_post_meta( $post_id, '_mfa_ai_status', 'generated' );

	return true;
}
