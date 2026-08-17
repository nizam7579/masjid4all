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
 *
 * SEO structure rewritten 2026-08-17 after the user shared a reference
 * prompt (previously used for Surah pages) that scores 80+ on RankMath,
 * versus this generator's original ~7. The gap: RankMath's checks are
 * mechanical, not qualitative - an exact focus keyword decided BEFORE
 * writing and then placed deliberately (title start, SEO title, meta
 * description, first sentence, every subheading, URL), a real word-count
 * floor, and at least one external + several internal links. Structural
 * pieces that RankMath checks for (the H2 headings + their anchor ids, the
 * table of contents, the closing internal-links block) are now built
 * deterministically in PHP from the locked-in focus keyword rather than
 * trusted to the AI to keep in sync - only the prose inside each section is
 * AI-written. This mirrors the reference prompt's own approach of handing
 * the AI fixed section headings/closing HTML to fill in, not invent.
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
 * human can review title/excerpt/focus keyword on the AI Content page
 * before any content-generation call is spent on it. Each topic now also
 * carries a `focus_keyword` - a short (2-5 word), literal phrase decided
 * up front and locked in as `rank_math_focus_keyword` immediately, since
 * RankMath's checks need ONE exact phrase to track through title, URL,
 * meta description, and body - picking it after the fact (the old
 * behaviour) made consistent placement unreliable. Returns
 * array('created' => post IDs, 'skipped' => titles rejected as duplicates).
 */
function mfa_knowledge_ai_suggest_topics( $count = 10 ) {
	$existing_summary = mfa_knowledge_ai_existing_summary();
	$category_names    = get_terms( array( 'taxonomy' => 'knowledge-category', 'hide_empty' => false, 'fields' => 'names' ) );
	if ( is_wp_error( $category_names ) ) {
		$category_names = array();
	}
	$category_list = implode( ', ', $category_names );

	$system = 'You are an Islamic content strategist and SEO editor for a knowledge hub covering Fiqh, Quran, Hadith, '
		. "and daily Muslim life topics. You propose NEW article topics that are not duplicates or close variations of "
		. "existing articles. Favor categories that have fewer existing articles, to help the hub reach even coverage "
		. "across all categories.\n\n"
		. "For each topic, first decide a `focus_keyword` - a short, natural 2-5 word phrase a Muslim reader would "
		. "actually type into Google for this exact topic (e.g. \"rights of neighbors in Islam\", \"how to perform "
		. "ghusl\", \"tawakkul in Islam\") - this single phrase will be used throughout the article for SEO, so it must "
		. "be specific and searchable, not generic. Then write the `title` so it STARTS with that exact focus_keyword "
		. "(naturally capitalized), followed by a colon and a short compelling subtitle - e.g. "
		. "\"Rights of Neighbors in Islam: A Complete Guide\".\n\n"
		. "Respond ONLY with a JSON array, no markdown fences, no explanation, in this exact shape:\n"
		. '[{"title":"...","focus_keyword":"...","excerpt":"...","keywords":"comma, separated, secondary keywords","category":"exact category name from the list"}]';

	$user = "Existing categories: {$category_list}\n\n"
		. "Existing articles by category:\n{$existing_summary}\n\n"
		. "Propose {$count} new article topics. Each title/focus_keyword must be clearly distinct in subject from every "
		. "existing title above - not a rephrasing, not a narrower or broader version of an existing title. "
		. "excerpt: 1-2 sentences summarizing what the article would cover, naturally including the focus_keyword. "
		. "keywords: 3-5 realistic secondary search phrases (variations, related terms) a Muslim reader might also use. "
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

		$excerpt       = sanitize_textarea_field( $topic['excerpt'] ?? '' );
		$focus_keyword = sanitize_text_field( $topic['focus_keyword'] ?? '' );
		$keywords      = sanitize_text_field( $topic['keywords'] ?? '' );
		$category      = sanitize_text_field( $topic['category'] ?? '' );

		$post_args = array(
			'post_type'    => 'knowledge',
			'post_status'  => 'draft',
			'post_title'   => $title,
			'post_excerpt' => $excerpt,
		);
		// Slug tightly matched to the focus keyword (not the full title, per
		// the reference prompt's "lowercase-hyphenated-keyword" convention) -
		// RankMath checks the URL for the exact focus keyword, and a short
		// keyword-only slug satisfies that more reliably than a long title-
		// derived one would.
		if ( '' !== $focus_keyword ) {
			$post_args['post_name'] = sanitize_title( $focus_keyword );
		}

		$post_id = wp_insert_post( $post_args, true );

		if ( is_wp_error( $post_id ) ) {
			continue;
		}

		if ( '' !== $category && term_exists( $category, 'knowledge-category' ) ) {
			wp_set_object_terms( $post_id, $category, 'knowledge-category' );
		}

		update_post_meta( $post_id, '_mfa_ai_status', 'pending' );
		update_post_meta( $post_id, '_mfa_ai_keywords', $keywords );
		if ( '' !== $focus_keyword ) {
			update_post_meta( $post_id, 'rank_math_focus_keyword', $focus_keyword );
		}

		$created[]      = $post_id;
		$existing_norm[] = $norm; // also guard against near-dupes within this same batch.
	}

	return array( 'created' => $created, 'skipped' => $skipped );
}

/**
 * Fixed Islamic reference domains DeepSeek may cite as the article's one
 * required external dofollow link - keeps the choice to genuinely
 * authoritative, well-known sites rather than whatever the model invents.
 */
function mfa_knowledge_ai_external_reference_options() {
	return array(
		'https://quran.com'   => 'Quran.com - for Quran/tafsir-related topics',
		'https://sunnah.com'  => 'Sunnah.com - for hadith-related topics',
		'https://islamqa.info' => 'IslamQA.info - for general fiqh/rulings topics',
	);
}

/**
 * The five fixed H2 sections every generated article uses, keyed to their
 * TOC anchor id and built from the locked-in focus keyword so the TOC,
 * headings, and content are guaranteed to match - the AI only supplies the
 * prose inside each section (see mfa_knowledge_ai_generate_content()),
 * never the headings/ids themselves, removing the main failure mode of the
 * original single-shot approach (AI drifting the TOC out of sync with the
 * actual headings it wrote).
 */
function mfa_knowledge_ai_section_headings( $focus_keyword_title_case ) {
	return array(
		'introduction'  => "Introduction to {$focus_keyword_title_case}",
		'key-themes'    => "Key Lessons of {$focus_keyword_title_case}",
		'benefits'      => "Benefits of Understanding {$focus_keyword_title_case}",
		'daily-practice' => "How to Apply {$focus_keyword_title_case} in Daily Life",
		'resources'     => 'Explore More Islamic Resources',
	);
}

/**
 * Step 2: write the full article for one already-suggested draft.
 *
 * Structure is deterministic PHP scaffolding (table of contents, H2
 * headings + anchor ids, closing internal-links block) built from the
 * post's locked-in `rank_math_focus_keyword`; DeepSeek only supplies the
 * prose for four sections plus the SEO title/meta description, matching
 * the reference prompt's own "give the AI exact section titles to fill
 * in, not invent" approach. One random existing published article (same
 * category when possible) is passed purely as a tone/length reference, not
 * to copy its content.
 */
function mfa_knowledge_ai_generate_content( $post_id ) {
	$post = get_post( $post_id );
	if ( ! $post || 'knowledge' !== $post->post_type ) {
		return new WP_Error( 'mfa_knowledge_ai_invalid_post', 'Not a valid Knowledge Hub post.' );
	}

	$keywords      = get_post_meta( $post_id, '_mfa_ai_keywords', true );
	$focus_keyword = get_post_meta( $post_id, 'rank_math_focus_keyword', true );

	// Backward compatibility: suggestions created before this rewrite have
	// no focus keyword yet - derive one from the title (everything before
	// the first colon, or the whole title) and lock it in now, same as a
	// freshly suggested topic would have.
	if ( '' === $focus_keyword ) {
		$focus_keyword = trim( strtok( $post->post_title, ':' ) );
		if ( '' === $focus_keyword ) {
			$focus_keyword = $post->post_title;
		}
		update_post_meta( $post_id, 'rank_math_focus_keyword', $focus_keyword );
		wp_update_post( array( 'ID' => $post_id, 'post_name' => sanitize_title( $focus_keyword ) ) );
	}

	$focus_keyword_title_case = ucwords( $focus_keyword );

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

	$headings         = mfa_knowledge_ai_section_headings( $focus_keyword_title_case );
	$external_options = mfa_knowledge_ai_external_reference_options();
	$external_list    = '';
	foreach ( $external_options as $url => $desc ) {
		$external_list .= "- {$url} ({$desc})\n";
	}

	$system = 'You are an expert Islamic content writer and SEO editor for a Muslim-friendly knowledge hub. Write clear, '
		. "accurate, well-structured prose for a general Muslim audience, respecting mainstream Sunni scholarly "
		. "consensus where rulings are discussed, and noting where scholars differ rather than presenting one view as "
		. "universally agreed. Do not fabricate Quran verses or hadith citations - only cite ones you are confident are "
		. "accurately attributed; prefer general guidance over a specific citation if unsure.\n\n"
		. "CRITICAL SEO RULES - the focus keyword is: \"{$focus_keyword}\"\n"
		. "- The exact focus keyword must appear in the very FIRST SENTENCE of introduction_html.\n"
		. "- The exact focus keyword must appear naturally at least once in EACH of the four sections below.\n"
		. "- Total word count across all four HTML fields combined must be at least 600 words.\n"
		. "- benefits_html must include exactly ONE external link to one of these authoritative sites (pick whichever "
		. "fits the topic), written as <a href=\"URL\" target=\"_blank\" rel=\"noopener dofollow\">Site Name</a>, "
		. "woven naturally into a sentence, not just appended:\n{$external_list}\n"
		. "- Use clean HTML only: <p> paragraphs, <strong> for emphasis, <ul>/<li> for lists. No <h1>/<h2>/<h3> tags in "
		. "your output - the headings are added separately.\n"
		. "- Keep the tone warm, welcoming, and easy to understand for beginners.\n\n"
		. "Respond ONLY with a JSON object, no markdown fences, no explanation, in this exact shape:\n"
		. '{"seo_title_descriptor":"2-4 words","meta_description":"...","introduction_html":"...","key_points_html":"...","benefits_html":"...","daily_practice_html":"..."}';

	$user = "Write the article for this topic:\n"
		. "Title: {$post->post_title}\n"
		. "Focus keyword (use exactly as given): {$focus_keyword}\n"
		. "Excerpt/brief: {$post->post_excerpt}\n"
		. "Category: {$category}\n"
		. "Secondary keywords for natural variety: {$keywords}\n\n"
		. "Section 1 - \"{$headings['introduction']}\": introduction_html = 2 paragraphs giving context and a general "
		. "overview, focus keyword in the first sentence.\n"
		. "Section 2 - \"{$headings['key-themes']}\": key_points_html = one short intro sentence + a bulleted list "
		. "(<ul><li>) of the central lessons/themes.\n"
		. "Section 3 - \"{$headings['benefits']}\": benefits_html = 1-2 paragraphs on the spiritual/practical benefits, "
		. "including the required external link.\n"
		. "Section 4 - \"{$headings['daily-practice']}\": daily_practice_html = 1-2 paragraphs of actionable, practical "
		. "tips for daily life.\n\n"
		. "seo_title_descriptor: a short (2-4 word) compelling phrase for the SEO title tag, e.g. \"Meaning, Benefits "
		. "and Practice\" - do not repeat the focus keyword in it, it will be prepended automatically.\n"
		. "meta_description: 150-160 characters including spaces, naturally including the focus keyword, compelling "
		. "enough to earn a click in Google search results.\n\n"
		. ( $reference_text ? "For tone, structure, and approximate length only (not content to copy), here is an existing article on this site:\n\n{$reference_text}\n\n" : '' );

	$raw = mfa_knowledge_ai_call_deepseek( $system, $user, 3500 );
	if ( is_wp_error( $raw ) ) {
		return $raw;
	}

	$json = json_decode( mfa_knowledge_ai_strip_json_fences( $raw ), true );
	if ( ! is_array( $json ) || empty( $json['introduction_html'] ) ) {
		return new WP_Error( 'mfa_knowledge_ai_parse_error', 'Could not parse DeepSeek\'s response as article content.' );
	}

	$content_html = mfa_knowledge_ai_assemble_content( $post, $headings, $json, $focus_keyword_title_case );

	wp_update_post( array(
		'ID'           => $post_id,
		'post_content' => $content_html,
	) );

	if ( ! empty( $json['meta_description'] ) ) {
		update_post_meta( $post_id, 'rank_math_description', sanitize_text_field( $json['meta_description'] ) );
	}

	// SEO title built in PHP, not trusted to the AI's character counting:
	// "{Focus Keyword} | {descriptor} | Masjid4All", falling back to just
	// "{Focus Keyword} | Masjid4All" if the descriptor would push it past
	// RankMath's ~60-character SERP-truncation limit.
	$descriptor  = sanitize_text_field( $json['seo_title_descriptor'] ?? '' );
	$brand_title = $focus_keyword_title_case . ' | Masjid4All';
	$full_title  = $descriptor ? "{$focus_keyword_title_case} | {$descriptor} | Masjid4All" : $brand_title;
	update_post_meta( $post_id, 'rank_math_title', strlen( $full_title ) <= 60 ? $full_title : $brand_title );

	update_post_meta( $post_id, '_mfa_ai_status', 'generated' );

	return true;
}

/**
 * Assembles the final post_content from AI-written section prose + PHP-
 * built structure (table of contents, H2/id headings, closing internal
 * links) - see the class docblock for why the structure itself isn't
 * trusted to the AI. Internal links point at this site's real public
 * pages (verified live, not guessed): /prayer-times/, /qibla-finder/,
 * /masjid/, /business/, /web/, /knowledge-hub/.
 */
function mfa_knowledge_ai_assemble_content( $post, $headings, $json, $focus_keyword_title_case ) {
	$toc = '<div class="m4a-toc" style="background:#f9f9f9; padding:15px; border-radius:4px; margin-bottom:20px;">'
		. '<p style="margin-top:0; font-weight:bold;">Table of Contents</p>';
	$i = 1;
	foreach ( $headings as $id => $label ) {
		$toc .= '<p style="margin: 4px 0;"><a href="#' . esc_attr( $id ) . '">' . $i . '. ' . esc_html( $label ) . '</a></p>';
		$i++;
	}
	$toc .= '</div>';

	$sections = '';
	$map      = array(
		'introduction'   => $json['introduction_html'] ?? '',
		'key-themes'     => $json['key_points_html'] ?? '',
		'benefits'       => $json['benefits_html'] ?? '',
		'daily-practice' => $json['daily_practice_html'] ?? '',
	);
	foreach ( $map as $id => $html ) {
		$sections .= '<h2 id="' . esc_attr( $id ) . '">' . esc_html( $headings[ $id ] ) . '</h2>' . wp_kses_post( $html );
	}

	$resources = '<h2 id="resources">' . esc_html( $headings['resources'] ) . '</h2>'
		. '<p>Now that you have explored <strong>' . esc_html( $focus_keyword_title_case ) . '</strong>, we encourage you '
		. 'to continue your spiritual journey. Here at Masjid4All, we provide a variety of helpful, free community '
		. 'tools to support your lifestyle:</p>'
		. '<ul>'
		. '<li>Keep up with your daily prayers using our <a href="' . esc_url( home_url( '/prayer-times/' ) ) . '">Prayer Time Calculator</a>.</li>'
		. '<li>Ensure your prayer direction is accurate from any location with our <a href="' . esc_url( home_url( '/qibla-finder/' ) ) . '">Online Qibla Finder</a>.</li>'
		. '<li>Find and connect with your local Muslim community using our <a href="' . esc_url( home_url( '/masjid/' ) ) . '">Mosque Directory</a>.</li>'
		. '<li>Discover more articles and explainers on our <a href="' . esc_url( home_url( '/knowledge-hub/' ) ) . '">Knowledge Hub</a>.</li>'
		. '<li>Support your community by visiting verified listings in our <a href="' . esc_url( home_url( '/business/' ) ) . '">Business Directory</a> or explore trusted spaces via our <a href="' . esc_url( home_url( '/web/' ) ) . '">Islamic Websites Directory</a>.</li>'
		. '</ul>';

	return $toc . $sections . $resources;
}
