<?php
/**
 * Boilerplate appended to AI-generated directory content.
 *
 * The "About Masjid4All" card used to be pasted into the AI prompt with the
 * instruction "At at end of content. Please do not change the content." That
 * is a request, not a guarantee: on 2026-08-19 the model put it FIRST on
 * /web/iqra-wa-rattel-institute/, above the listing's own <h1>. One in twelve
 * recent listings came out that way - every other one had it near the end.
 *
 * Asking a model to reproduce fixed boilerplate in a fixed position is the
 * wrong tool. The block is emitted here in PHP after generation instead, so
 * its position cannot vary. Any copy the model emits anyway is stripped
 * first, so the prompt can keep mentioning it (or stop) without producing
 * duplicates either way.
 *
 * @package mfa-core
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** The canonical card. One definition, used by every directory type. */
function mfa_about_block_html() {
	$html = '<div class="m4a-card">' . "\n"
		. '    <h3>About Masjid4All</h3>' . "\n\n"
		. '    <p>Masjid4All connects Muslims worldwide by uniting essential Islamic resources into one digital hub:</p>' . "\n\n"
		. '    <div class="m4a-lists">' . "\n"
		. '        <ul>' . "\n"
		. '            <li><a href="/masjid/">Masjid Directory</a></li>' . "\n"
		. '            <li><a href="/business/">Business Directory</a></li>' . "\n"
		. '            <li><a href="/web/">Website Directory</a></li>' . "\n"
		. '            <li><a href="/knowledge-hub/">Knowledge Hub</a></li>' . "\n"
		. '        </ul>' . "\n\n"
		. '        <ul>' . "\n"
		. '            <li><a href="/prayer-times/">Prayer Time</a></li>' . "\n"
		. '            <li><a href="/qibla-finder/">Qibla Finder</a></li>' . "\n"
		. '            <li><a href="/quran/">Daily Quran</a></li>' . "\n"
		. '            <li><a href="/member/">Member&rsquo;s Page</a></li>' . "\n"
		. '        </ul>' . "\n"
		. '    </div>' . "\n"
		. '</div>';

	return apply_filters( 'mfa_about_block_html', $html );
}

/**
 * Remove any copy of the card the model produced, wherever it put it.
 *
 * Done with DOM rather than a regex. The first attempt matched
 * `</div>\s*</div>\s*</div>` on the assumption the card nests two levels;
 * the real markup closes twice, so that pattern missed, the heading-based
 * fallback fired instead, and it removed the heading while leaving the
 * opening <div class="m4a-card"> behind - unbalanced HTML that would have
 * swallowed the rest of the page. Balancing tags is exactly what a parser
 * is for.
 *
 * Matched on the heading text, not the markup, because the model reformats
 * whitespace, swaps quote styles and sometimes drops the inner wrapper.
 */
function mfa_strip_about_block( $html ) {
	$html = (string) $html;

	if ( false === stripos( $html, 'About Masjid4All' ) ) {
		return $html;
	}

	// Without DOM, leave the content alone rather than risk mangling it.
	if ( ! class_exists( 'DOMDocument' ) ) {
		return $html;
	}

	$doc  = new DOMDocument();
	$prev = libxml_use_internal_errors( true );

	// The XML encoding hint keeps UTF-8 intact; the wrapper gives a single
	// root to read back, and NOIMPLIED/NODEFDTD stop DOM adding html/body.
	$loaded = $doc->loadHTML(
		'<?xml encoding="utf-8" ?><div id="mfa-about-root">' . $html . '</div>',
		LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD
	);

	libxml_clear_errors();
	libxml_use_internal_errors( $prev );

	if ( ! $loaded ) {
		return $html;
	}

	$xpath = new DOMXPath( $doc );
	$heads = $xpath->query(
		'//*[self::h1 or self::h2 or self::h3 or self::h4][contains(normalize-space(.), "About Masjid4All")]'
	);

	foreach ( $heads as $head ) {
		// Prefer removing the whole .m4a-card wrapper when there is one.
		$card = $head->parentNode;
		while ( $card instanceof DOMElement && 'mfa-about-root' !== $card->getAttribute( 'id' ) ) {
			if ( false !== strpos( $card->getAttribute( 'class' ), 'm4a-card' ) ) {
				break;
			}
			$card = $card->parentNode;
		}

		if ( $card instanceof DOMElement
			&& 'mfa-about-root' !== $card->getAttribute( 'id' )
			&& false !== strpos( $card->getAttribute( 'class' ), 'm4a-card' )
		) {
			$card->parentNode->removeChild( $card );
			continue;
		}

		// No wrapper: take the heading and everything up to the next heading.
		$doomed = array( $head );
		for ( $node = $head->nextSibling; $node; $node = $node->nextSibling ) {
			if ( $node instanceof DOMElement && preg_match( '/^h[1-4]$/i', $node->nodeName ) ) {
				break;
			}
			$doomed[] = $node;
		}
		foreach ( $doomed as $node ) {
			if ( $node->parentNode ) {
				$node->parentNode->removeChild( $node );
			}
		}
	}

	$roots = $xpath->query( '//*[@id="mfa-about-root"]' );
	if ( ! $roots->length ) {
		return $html;
	}

	$inner = '';
	foreach ( $roots->item( 0 )->childNodes as $child ) {
		$inner .= $doc->saveHTML( $child );
	}

	return trim( $inner );
}

/**
 * Strip any stray copy, then append the canonical card at the very end.
 *
 * Safe to call twice - the strip runs first, so a re-generated post does not
 * accumulate cards.
 */
function mfa_append_about_block( $html ) {
	$html = mfa_strip_about_block( (string) $html );

	return rtrim( $html ) . "\n\n" . mfa_about_block_html() . "\n";
}
