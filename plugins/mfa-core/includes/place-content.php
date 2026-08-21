<?php
/**
 * AI-written editorial intros for the /places/ country and state hubs.
 *
 * ## Why the hubs, and why only the top two levels
 *
 * The 566 hubs are NOT subject to the thin-page noindex rule - seo-index-
 * control.php exempts them by name, because they are hand-made and every one
 * carries at least twenty listings. So this is not about unlocking pages that
 * search engines are being kept away from; they have always been indexable.
 *
 * It is about them being interchangeable. A hub currently differs from its
 * neighbour only by a place name and two numbers, which is the mass-produced
 * pattern the whole listing-noindex effort exists to avoid, applied to the very
 * pages whose job is to hand out link equity (see place-hub.php).
 *
 * Countries and states only - 3 + 79 = 82 pages - and the depth guard in
 * mfa_place_content_is_eligible() enforces it rather than trusting the caller.
 * The 484 city hubs are deliberately excluded: a model asked to write about the
 * Muslim community of a small district has nothing real to draw on and will
 * invent a founding date, a population figure or a mosque's history. State-level
 * general knowledge about Kelantan or Aceh is genuinely well attested. If city
 * hubs are ever done, they should be grounded in our own listing data rather
 * than the model's memory, which is a different job from this one.
 *
 * ## Nothing here touches SEO meta
 *
 * All 566 hubs already carry a rank_math title, description and focus keyword,
 * and the description is count-accurate ("Find 6 mosques and 16 halal
 * businesses in Kemasik"). mfa_place_seed_seo_meta() only ever fills an EMPTY
 * value, so saving a hub from here cannot overwrite them. This writes
 * post_content and post_excerpt, nothing else.
 *
 * @package mfa-core
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The DeepSeek key, constant first then niz-wa's stored setting.
 *
 * Deliberately a second implementation of mfa_knowledge_ai_deepseek_key()
 * rather than a call to it: knowledge-ai.php is staging-only by decision and is
 * NOT in production's include list, so depending on it would make this work on
 * staging and fail on production - the exact split that is easiest to miss.
 * The constant is defined on both.
 */
function mfa_place_content_deepseek_key() {
	if ( defined( 'DEEPSEEK_API_KEY' ) && DEEPSEEK_API_KEY ) {
		return DEEPSEEK_API_KEY;
	}

	$nwa = get_option( 'nwa_settings', array() );
	if ( is_array( $nwa ) && 'deepseek' === ( $nwa['ai_provider'] ?? '' ) && ! empty( $nwa['ai_api_key'] ) ) {
		return $nwa['ai_api_key'];
	}

	return '';
}

/** One DeepSeek chat call. Returns the raw assistant string or WP_Error. */
function mfa_place_content_call( $system, $user, $max_tokens = 1200 ) {
	$key = mfa_place_content_deepseek_key();
	if ( '' === $key ) {
		return new WP_Error( 'mfa_place_no_key', 'No DeepSeek API key - set DEEPSEEK_API_KEY in wp-config.php.' );
	}

	$response = wp_remote_post(
		'https://api.deepseek.com/v1/chat/completions',
		array(
			'headers' => array(
				'Authorization' => 'Bearer ' . $key,
				'Content-Type'  => 'application/json',
			),
			'body'    => wp_json_encode(
				array(
					'model'       => 'deepseek-chat',
					'messages'    => array(
						array( 'role' => 'system', 'content' => $system ),
						array( 'role' => 'user', 'content' => $user ),
					),
					// Lower than the knowledge generator's 0.7. This is
					// reference copy about real places, so the interesting
					// failure mode is invention, not dullness.
					'temperature' => 0.4,
					'max_tokens'  => (int) $max_tokens,
				)
			),
			'timeout' => 90,
		)
	);

	if ( is_wp_error( $response ) ) {
		return $response;
	}

	$code = wp_remote_retrieve_response_code( $response );
	$body = wp_remote_retrieve_body( $response );

	if ( $code < 200 || $code >= 300 ) {
		return new WP_Error( 'mfa_place_http', 'DeepSeek HTTP ' . $code . ': ' . mb_substr( $body, 0, 300 ) );
	}

	$data = json_decode( $body, true );
	$text = $data['choices'][0]['message']['content'] ?? '';

	if ( '' === trim( (string) $text ) ) {
		return new WP_Error( 'mfa_place_empty', 'DeepSeek returned an empty response.' );
	}

	return $text;
}

/**
 * Country hubs have no parent; state hubs' parent has none. Anything deeper is
 * a city and is refused.
 *
 * @return int 0 = country, 1 = state, -1 = not eligible.
 */
function mfa_place_content_depth( $post_id ) {
	$post = get_post( $post_id );

	if ( ! $post instanceof WP_Post || MFA_PLACE_POST_TYPE !== $post->post_type || 'publish' !== $post->post_status ) {
		return -1;
	}

	if ( ! $post->post_parent ) {
		return 0;
	}

	$parent = get_post( $post->post_parent );

	return ( $parent instanceof WP_Post && ! $parent->post_parent ) ? 1 : -1;
}

/** Eligible, and not already written (unless forced). */
function mfa_place_content_is_eligible( $post_id, $force = false ) {
	if ( mfa_place_content_depth( $post_id ) < 0 ) {
		return false;
	}

	if ( $force ) {
		return true;
	}

	return '' === trim( (string) get_post_field( 'post_content', $post_id ) );
}

/**
 * The facts the model is allowed to build on - all read from our own tables.
 *
 * Real mosque and business names are included so the copy can be specific
 * without the model reaching for its own recollection of the area. They are
 * ordered by rating_count, so these are the places a reader is most likely to
 * have heard of.
 */
function mfa_place_content_grounding( $post_id ) {
	$depth   = mfa_place_content_depth( $post_id );
	$name    = get_the_title( $post_id );
	$parent  = wp_get_post_parent_id( $post_id );
	$country = $parent ? get_the_title( $parent ) : $name;
	$counts  = mfa_place_counts( $post_id );

	$facts = array(
		'place'          => $name,
		'type'           => 0 === $depth ? 'country' : 'state or region',
		'country'        => $country,
		'mosque_count'   => (int) $counts['mosque'],
		'business_count' => (int) $counts['business'],
	);

	$children    = mfa_place_children( $post_id );
	$child_lines = array();
	foreach ( array_slice( $children, 0, 12 ) as $child ) {
		$c             = mfa_place_counts( $child->ID );
		$child_lines[] = sprintf( '%s (%d mosques, %d halal businesses)', get_the_title( $child->ID ), $c['mosque'], $c['business'] );
	}
	$facts['child_areas'] = $child_lines;

	$mosques                  = mfa_place_listings( $post_id, 'mosque', 1, 10 );
	$facts['example_mosques'] = wp_list_pluck( $mosques['rows'], 'name' );

	$businesses                  = mfa_place_listings( $post_id, 'business', 1, 8 );
	$facts['example_businesses'] = wp_list_pluck( $businesses['rows'], 'name' );

	return $facts;
}

/**
 * The system prompt.
 *
 * The prohibitions are the point of this function. Two of them are specific to
 * an Islamic site rather than generic anti-hallucination hygiene: it must not
 * issue religious rulings, because a directory page is not a place for fiqh and
 * we have no scholar reviewing it; and it must not state prayer times, which
 * the site computes and which would be wrong the moment they were written down.
 */
function mfa_place_content_system_prompt() {
	return implode(
		"\n",
		array(
			'You write short editorial introductions for Masjid4All, a directory of mosques and halal businesses.',
			'',
			'Write about the Muslim community and Islamic heritage of the place: how Islam is woven into daily life there, what a Muslim visitor or traveller would want to know, and what the region is known for.',
			'',
			'HARD RULES - breaking any of these makes the text unusable:',
			'1. Never invent a date, a founding story, a population figure, a percentage or any other statistic. If you are not certain, write about the place in general terms instead.',
			'2. Never make a claim about a specific named mosque or business beyond the fact that it is there. The names are given to you so the text can be concrete, not so you can describe their history.',
			'3. Never give a religious ruling, fatwa or fiqh opinion of any kind.',
			'4. Never state prayer times, and never say the site has features it may not have.',
			'5. Use only the counts supplied. Do not round them upward, and do not invent others. A vague quantity ("thousands of", "hundreds of") must be true of the actual number given, so check it before writing it.',
			'6. NEVER describe a business as halal-certified, JAKIM-certified or approved. They are listed as halal; we do not verify certification, and claiming it on their behalf is a claim we cannot stand behind. For the same reason, do not say food is "prepared in accordance with Islamic guidelines".',
			'7. Respect Islamic terminology. "Masjid" and "mosque" are both fine.',
			'',
			'Style: plain English, warm but factual, no marketing superlatives, no "nestled" or "vibrant tapestry". Do not open with the place name in bold or as a heading. Around 160-220 words across 3 paragraphs.',
			'',
			'Return ONLY raw JSON, no code fence, in exactly this shape:',
			'{"paragraphs":["...","...","..."],"excerpt":"one or two sentences, under 200 characters, with no numbers or quantity claims"}',
		)
	);
}

/** The per-hub user message: the grounding facts, nothing else. */
function mfa_place_content_user_prompt( $facts ) {
	$lines = array(
		'Place: ' . $facts['place'],
		'Type: ' . $facts['type'],
		'Country: ' . $facts['country'],
		'Mosques listed: ' . $facts['mosque_count'],
		'Halal businesses listed: ' . $facts['business_count'],
	);

	if ( ! empty( $facts['child_areas'] ) ) {
		$lines[] = 'Main areas covered: ' . implode( '; ', $facts['child_areas'] );
	}
	if ( ! empty( $facts['example_mosques'] ) ) {
		$lines[] = 'Some mosques listed (names only, no other information known): ' . implode( '; ', $facts['example_mosques'] );
	}
	if ( ! empty( $facts['example_businesses'] ) ) {
		$lines[] = 'Some halal businesses listed (names only): ' . implode( '; ', $facts['example_businesses'] );
	}

	return implode( "\n", $lines );
}

/** DeepSeek is told to return bare JSON and sometimes fences it anyway. */
function mfa_place_content_strip_fences( $text ) {
	$text = trim( (string) $text );
	$text = preg_replace( '/^```(?:json)?\s*/i', '', $text );
	$text = preg_replace( '/\s*```$/', '', $text );

	return trim( $text );
}

/**
 * Generates and saves one hub's intro.
 *
 * The paragraphs are rebuilt as <p> blocks from plain text rather than trusting
 * the model to return markup - it cannot then introduce a heading that fights
 * the page's own h1, or a link we did not intend.
 *
 * @return array|WP_Error array( chars, words, excerpt ) on success.
 */
function mfa_place_content_generate( $post_id, $force = false ) {
	$post_id = (int) $post_id;

	if ( ! mfa_place_content_is_eligible( $post_id, $force ) ) {
		return new WP_Error( 'mfa_place_not_eligible', 'Not an empty country or state hub.' );
	}

	$facts = mfa_place_content_grounding( $post_id );
	$raw   = mfa_place_content_call( mfa_place_content_system_prompt(), mfa_place_content_user_prompt( $facts ) );

	if ( is_wp_error( $raw ) ) {
		return $raw;
	}

	$json = json_decode( mfa_place_content_strip_fences( $raw ), true );

	if ( ! is_array( $json ) || empty( $json['paragraphs'] ) || ! is_array( $json['paragraphs'] ) ) {
		return new WP_Error( 'mfa_place_bad_json', 'Could not parse the response: ' . mb_substr( $raw, 0, 200 ) );
	}

	$paragraphs = array();
	foreach ( $json['paragraphs'] as $p ) {
		$p = trim( wp_strip_all_tags( (string) $p ) );
		if ( '' !== $p ) {
			$paragraphs[] = '<p>' . esc_html( $p ) . '</p>';
		}
	}

	if ( ! $paragraphs ) {
		return new WP_Error( 'mfa_place_bad_json', 'The response contained no usable paragraphs.' );
	}

	$content = implode( "\n\n", $paragraphs );
	$plain   = trim( preg_replace( '/\s+/u', ' ', wp_strip_all_tags( $content ) ) );

	// Guards against a truncated or one-line answer being saved as if it were
	// a finished intro. 400 characters is comfortably under the ~1,000 a real
	// three-paragraph answer runs to, and well over a stub.
	if ( mb_strlen( $plain ) < 400 ) {
		return new WP_Error( 'mfa_place_too_short', 'Only ' . mb_strlen( $plain ) . ' characters came back - not saving.' );
	}

	$excerpt = trim( wp_strip_all_tags( (string) ( $json['excerpt'] ?? '' ) ) );
	if ( '' === $excerpt ) {
		$excerpt = mb_substr( $plain, 0, 180 );
	}
	$excerpt = mb_substr( $excerpt, 0, 300 );

	$updated = wp_update_post(
		array(
			'ID'           => $post_id,
			'post_content' => $content,
			'post_excerpt' => $excerpt,
		),
		true
	);

	if ( is_wp_error( $updated ) ) {
		return $updated;
	}

	update_post_meta( $post_id, '_mfa_place_content_generated', current_time( 'mysql' ) );

	return array(
		'chars'   => mb_strlen( $plain ),
		'words'   => str_word_count( $plain ),
		'excerpt' => $excerpt,
	);
}

/**
 * Country and state hub IDs, countries first so the highest-value pages are
 * written before anyone loses patience with the tab.
 *
 * One query rather than a tree walk: a state's parent is a country exactly
 * when that parent has no parent of its own.
 */
function mfa_place_content_target_ids() {
	global $wpdb;

	return array_map(
		'intval',
		$wpdb->get_col(
			$wpdb->prepare(
				"SELECT p.ID FROM {$wpdb->posts} p
				 LEFT JOIN {$wpdb->posts} parent ON parent.ID = p.post_parent
				 WHERE p.post_type = %s AND p.post_status = 'publish'
				   AND ( p.post_parent = 0 OR parent.post_parent = 0 )
				 ORDER BY ( p.post_parent <> 0 ) ASC, p.ID ASC",
				MFA_PLACE_POST_TYPE
			)
		)
	);
}

/** How many of those are still empty. */
function mfa_place_content_remaining() {
	$n = 0;
	foreach ( mfa_place_content_target_ids() as $id ) {
		if ( '' === trim( (string) get_post_field( 'post_content', $id ) ) ) {
			$n++;
		}
	}

	return $n;
}
