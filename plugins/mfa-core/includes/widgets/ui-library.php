<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * [mfa_ui_library] - the /ui/ pattern library. Administrator-only.
 *
 * Built to settle an argument the codebase keeps having with itself: 44
 * stylesheets, 22 of which define their own button, and 35 distinct card
 * classes, while global-v3.css already defines the tokens they all ignore -
 * #25988b is hardcoded 38 times across other sheets despite --mfa-teal
 * existing. Every new page therefore starts by reinventing spacing, buttons
 * and cards, and formatting drifts a little further each time.
 *
 * This page shows the candidates side by side under plain names (btn1, btn2,
 * card1 ...) so a set can be chosen by looking rather than by argument. Once
 * chosen, the winners are promoted into global-v3.css as the canonical
 * classes and pages use those instead of inventing variants.
 *
 * Candidates deliberately live in this page's own stylesheet, not the global
 * one. Only the agreed set graduates - so the rejects never become something
 * a future page can accidentally depend on.
 */

add_shortcode( 'mfa_ui_library', 'mfa_ui_library_shortcode' );
function mfa_ui_library_shortcode() {
	if ( ! current_user_can( 'administrator' ) ) {
		return '<p>This page is for administrators only.</p>';
	}

	ob_start();
	?>
	<div class="mfa-ui">
		<header class="mfa-ui-header">
			<h1 class="mfa-h1">UI Library</h1>
			<p class="mfa-body-muted">
				Candidates for the shared design system. Pick one of each and it becomes the
				canonical class in <code>global-v3.css</code>; everything else here is discarded.
				Nothing on this page is used by the live site yet.
			</p>
		</header>

		<?php
		echo mfa_ui_section_audit();   // phpcs:ignore WordPress.Security.EscapeOutput
		echo mfa_ui_section_colour();  // phpcs:ignore WordPress.Security.EscapeOutput
		echo mfa_ui_section_scale();   // phpcs:ignore WordPress.Security.EscapeOutput
		echo mfa_ui_section_type();    // phpcs:ignore WordPress.Security.EscapeOutput
		echo mfa_ui_section_buttons(); // phpcs:ignore WordPress.Security.EscapeOutput
		echo mfa_ui_section_cards();   // phpcs:ignore WordPress.Security.EscapeOutput
		echo mfa_ui_section_bands();   // phpcs:ignore WordPress.Security.EscapeOutput
		?>
	</div>
	<?php
	return ob_get_clean();
}

/** Wraps each block so the page reads as a list of decisions to make. */
function mfa_ui_block( $id, $title, $note, $body ) {
	return '<section class="mfa-ui-block" id="' . esc_attr( $id ) . '">'
		. '<h2 class="mfa-ui-block-title">' . esc_html( $title ) . '</h2>'
		. ( '' !== $note ? '<p class="mfa-ui-block-note">' . wp_kses_post( $note ) . '</p>' : '' )
		. $body
		. '</section>';
}

/** One labelled specimen, so options can be referred to by name in review. */
function mfa_ui_specimen( $name, $desc, $html ) {
	return '<div class="mfa-ui-specimen">'
		. '<div class="mfa-ui-specimen-head"><code>' . esc_html( $name ) . '</code>'
		. '<span>' . esc_html( $desc ) . '</span></div>'
		. '<div class="mfa-ui-specimen-body">' . $html . '</div>'
		. '</div>';
}

/**
 * Why this page exists, measured rather than asserted. Counted live so the
 * numbers cannot rot while the problem is still being worked on.
 */
function mfa_ui_section_audit() {
	$dir    = MFA_CORE_PATH . 'assets/css/';
	$sheets = glob( $dir . '*.css' );
	$sheets = is_array( $sheets ) ? $sheets : array();

	$with_button = 0;
	$cards       = array();
	$hex         = array();

	foreach ( $sheets as $sheet ) {
		// This page's own candidate sheet is not part of the sprawl it is
		// measuring - counting itself would overstate the case.
		if ( 'ui-library-v1.css' === basename( $sheet ) ) {
			continue;
		}

		$css = (string) file_get_contents( $sheet );

		if ( preg_match( '/\.mfa-[a-z0-9-]*btn/i', $css ) ) {
			$with_button++;
		}

		if ( preg_match_all( '/\.mfa-[a-z0-9-]*card[a-z0-9-]*/i', $css, $m ) ) {
			foreach ( $m[0] as $c ) {
				$cards[ strtolower( $c ) ] = true;
			}
		}

		if ( basename( $sheet ) !== 'global-v3.css' && preg_match_all( '/#[0-9a-f]{6}/i', $css, $m ) ) {
			foreach ( $m[0] as $h ) {
				$h         = strtolower( $h );
				$hex[ $h ] = isset( $hex[ $h ] ) ? $hex[ $h ] + 1 : 1;
			}
		}
	}

	arsort( $hex );
	$top = array_slice( $hex, 0, 4, true );

	$rows = '';

	foreach ( $top as $colour => $count ) {
		$rows .= '<li><span class="mfa-ui-chip" style="background:' . esc_attr( $colour ) . '"></span>'
			. '<code>' . esc_html( $colour ) . '</code> hardcoded '
			. esc_html( number_format_i18n( $count ) ) . ' times outside the global sheet</li>';
	}

	$body = '<ul class="mfa-ui-stats">'
		. '<li><strong>' . esc_html( max( 0, count( $sheets ) - 1 ) ) . '</strong> stylesheets in this plugin</li>'
		. '<li><strong>' . esc_html( $with_button ) . '</strong> of them define their own button</li>'
		. '<li><strong>' . esc_html( count( $cards ) ) . '</strong> distinct card class names</li>'
		. '</ul>'
		. '<ul class="mfa-ui-stats mfa-ui-stats-colour">' . $rows . '</ul>';

	return mfa_ui_block(
		'audit',
		'Why this page exists',
		'The tokens below already exist in <code>global-v3.css</code>. These are the same values written out by hand somewhere else, which is what makes every new page format differently.',
		$body
	);
}

/** The palette, read from the stylesheet so it cannot drift from the source. */
function mfa_ui_section_colour() {
	$tokens = array(
		'--mfa-teal'       => 'Primary brand, CTAs, links',
		'--mfa-teal-dark'  => 'Hover for teal elements',
		'--mfa-green-dark' => 'Headings, nav, dark accents',
		'--mfa-ink'        => 'Body text',
		'--mfa-muted'      => 'Secondary text',
		'--mfa-label'      => 'Uppercase labels',
		'--mfa-mint-bg'    => 'Light card / section background',
		'--mfa-border'     => 'Card and divider borders',
		'--mfa-gold'       => 'Barakah points and rank only',
	);

	$out = '<div class="mfa-ui-swatches">';

	foreach ( $tokens as $token => $use ) {
		$out .= '<div class="mfa-ui-swatch">'
			. '<div class="mfa-ui-swatch-chip" style="background: var(' . esc_attr( $token ) . ')"></div>'
			. '<code>' . esc_html( $token ) . '</code>'
			. '<span>' . esc_html( $use ) . '</span>'
			. '</div>';
	}

	return mfa_ui_block(
		'colour',
		'Colour tokens',
		'Use the token, never the hex. <code>--mfa-gold</code> is deliberately scoped to Barakah points and rank - it is not a general accent.',
		$out . '</div>'
	);
}

/** Spacing, radius and shadow, shown at true size rather than described. */
function mfa_ui_section_scale() {
	$out = '<div class="mfa-ui-scale">';

	foreach ( range( 1, 10 ) as $step ) {
		$out .= '<div class="mfa-ui-scale-row"><code>--mfa-space-' . (int) $step . '</code>'
			. '<span class="mfa-ui-scale-bar" style="width: var(--mfa-space-' . (int) $step . ')"></span></div>';
	}

	$out .= '</div><div class="mfa-ui-radii">'
		. '<div class="mfa-ui-radius" style="border-radius: var(--mfa-radius-sm)"><code>sm</code> buttons</div>'
		. '<div class="mfa-ui-radius" style="border-radius: var(--mfa-radius-md)"><code>md</code> cards</div>'
		. '<div class="mfa-ui-radius" style="border-radius: var(--mfa-radius-pill)"><code>pill</code></div>'
		. '</div><div class="mfa-ui-radii">'
		. '<div class="mfa-ui-radius" style="box-shadow: var(--mfa-shadow-card)"><code>shadow-card</code></div>'
		. '<div class="mfa-ui-radius" style="box-shadow: var(--mfa-shadow-float)"><code>shadow-float</code></div>'
		. '</div>';

	return mfa_ui_block(
		'scale',
		'Spacing, radius, shadow',
		'The spacing scale is 8px-based. Breakpoints are a written convention, not tokens - <code>600px</code> for mobile and <code>900px</code> for tablet; deviating needs a reason in a comment.',
		$out
	);
}

/** The existing type classes, so a page never invents its own heading size. */
function mfa_ui_section_type() {
	$out = '<div class="mfa-ui-type">'
		. '<p class="mfa-h1">Heading 1 &mdash; .mfa-h1</p>'
		. '<p class="mfa-h2">Heading 2 &mdash; .mfa-h2</p>'
		. '<p class="mfa-h3">Heading 3 &mdash; .mfa-h3</p>'
		. '<p class="mfa-body">Body text &mdash; .mfa-body. The quick brown fox jumps over the lazy dog.</p>'
		. '<p class="mfa-body-muted">Muted body &mdash; .mfa-body-muted, for secondary detail.</p>'
		. '<p class="mfa-label">Label &mdash; .mfa-label</p>'
		. '</div>';

	return mfa_ui_block( 'type', 'Typography', 'Already in the global sheet and safe to use today.', $out );
}

/** Button candidates. The decision this page most needs. */
function mfa_ui_section_buttons() {
	$out = mfa_ui_specimen(
		'btn1',
		'Current .mfa-btn-primary - pill, teal, shadow',
		'<a class="mfa-btn mfa-btn-primary" href="#buttons">Primary action</a> '
		. '<a class="mfa-btn mfa-btn-solid-dark" href="#buttons">Dark</a>'
	);

	$out .= mfa_ui_specimen(
		'btn2',
		'Squarer radius, no shadow, flatter',
		'<a class="mfa-ui-btn2" href="#buttons">Primary action</a> '
		. '<a class="mfa-ui-btn2 is-secondary" href="#buttons">Secondary</a>'
	);

	$out .= mfa_ui_specimen(
		'btn3',
		'Outline primary, filled on hover',
		'<a class="mfa-ui-btn3" href="#buttons">Primary action</a> '
		. '<a class="mfa-ui-btn3 is-quiet" href="#buttons">Quiet</a>'
	);

	$out .= mfa_ui_specimen(
		'states',
		'The states every candidate must cover',
		'<a class="mfa-btn mfa-btn-primary" href="#buttons">Default</a> '
		. '<a class="mfa-btn mfa-btn-primary is-hover" href="#buttons">Hover</a> '
		. '<span class="mfa-btn mfa-btn-primary is-disabled">Disabled</span> '
		. '<a class="mfa-btn mfa-btn-primary mfa-ui-btn-block" href="#buttons">Full width on mobile</a>'
	);

	return mfa_ui_block(
		'buttons',
		'Buttons',
		'Pick one. Whichever wins becomes <code>.mfa-btn</code> and its modifiers, and the 22 sheets defining their own button get pointed at it.',
		$out
	);
}

/** Card candidates. */
function mfa_ui_section_cards() {
	$sample = '<h3 class="mfa-h3">Masjid Al-Hidayah</h3>'
		. '<p class="mfa-body-muted">Kuala Lumpur, Malaysia &middot; 2.4 km away</p>';

	$out = mfa_ui_specimen( 'card1', 'Current .mfa-card - white, 16px radius, hairline border', '<div class="mfa-card">' . $sample . '</div>' );
	$out .= mfa_ui_specimen( 'card2', 'Mint background, no border', '<div class="mfa-ui-card2">' . $sample . '</div>' );
	$out .= mfa_ui_specimen( 'card3', 'White with shadow instead of border', '<div class="mfa-ui-card3">' . $sample . '</div>' );

	return mfa_ui_block(
		'cards',
		'Cards',
		'There are 35 card class names in the plugin today. This should end as one, plus a modifier or two.',
		$out
	);
}

/** Section bands - the full-width blocks pages are assembled from. */
function mfa_ui_section_bands() {
	$out = mfa_ui_specimen(
		'section1',
		'Plain band on page background',
		'<div class="mfa-ui-band"><h3 class="mfa-h3">Section heading</h3><p class="mfa-body-muted">Body copy sits at a readable measure rather than the full page width.</p></div>'
	);

	$out .= mfa_ui_specimen(
		'section2',
		'Tinted band, used to separate a section from the one above',
		'<div class="mfa-ui-band is-tinted"><h3 class="mfa-h3">Section heading</h3><p class="mfa-body-muted">Same structure, mint background.</p></div>'
	);

	$out .= mfa_ui_specimen(
		'cta1',
		'The CTA box now on /prayer-times/, mosque pages and /qibla-finder/',
		'<div class="mfa-tool-cta"><h2 class="mfa-tool-cta-title">Which way is the qiblah?</h2>'
		. '<p class="mfa-tool-cta-text">Point your phone and face the Kaaba from wherever you are.</p>'
		. '<a class="mfa-btn mfa-btn-primary mfa-tool-cta-btn" href="#bands">Open the Qibla Finder</a></div>'
	);

	return mfa_ui_block(
		'bands',
		'Sections and CTAs',
		'The page-level building blocks. <code>cta1</code> is live already - included so it can be judged against the rest rather than in isolation.',
		$out
	);
}
