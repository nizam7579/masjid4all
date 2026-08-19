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
				The set chosen on 19 Aug 2026, now canonical in <code>global-v3.css</code>. Specimens
				marked <strong>chosen</strong> are what pages should use; the rest are kept only so the
				decision can be re-read, and are defined in this page&rsquo;s own stylesheet so nothing
				else can depend on them.
			</p>
		</header>

		<?php
		echo mfa_ui_section_audit();   // phpcs:ignore WordPress.Security.EscapeOutput
		echo mfa_ui_section_colour();  // phpcs:ignore WordPress.Security.EscapeOutput
		echo mfa_ui_section_scale();   // phpcs:ignore WordPress.Security.EscapeOutput
		echo mfa_ui_section_type();    // phpcs:ignore WordPress.Security.EscapeOutput
		echo mfa_ui_section_layout();  // phpcs:ignore WordPress.Security.EscapeOutput
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
		'CHOSEN - .mfa-btn .mfa-btn-primary, plus the new .mfa-btn-secondary',
		'<a class="mfa-btn mfa-btn-primary" href="#buttons">Primary action</a> '
		. '<a class="mfa-btn mfa-btn-secondary" href="#buttons">Secondary</a> <a class="mfa-btn mfa-btn-solid-dark" href="#buttons">Dark</a>'
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
		'<strong>btn1 chosen</strong>, unchanged. It is already the standard in eighteen places and matches the pill radius used elsewhere in the chrome, so restyling it would mean repainting the site to fix a problem nobody reported. What was genuinely missing is a secondary for light backgrounds - <code>--ghost</code> is white-on-dark and vanishes on a card, <code>--solid-dark</code> is too heavy beside a primary. That gap is why pages kept inventing a quiet button, so btn2&rsquo;s secondary was taken as <code>.mfa-btn-secondary</code>.',
		$out
	);
}

/** Card candidates. */
function mfa_ui_section_cards() {
	$sample = '<h3 class="mfa-h3">Masjid Al-Hidayah</h3>'
		. '<p class="mfa-body-muted">Kuala Lumpur, Malaysia &middot; 2.4 km away</p>';

	$out = mfa_ui_specimen( 'card1', 'CHOSEN - .mfa-card', '<div class="mfa-card">' . $sample . '</div>' );
	$out .= mfa_ui_specimen( 'card2', 'CHOSEN as a modifier - .mfa-card--tinted', '<div class="mfa-ui-card2">' . $sample . '</div>' );
	$out .= mfa_ui_specimen( 'card3', 'Not chosen', '<div class="mfa-ui-card3">' . $sample . '</div>' );

	return mfa_ui_block(
		'cards',
		'Cards',
		'<strong>card1 chosen</strong> as <code>.mfa-card</code>. A hairline border reads cleaner than a shadow at this density, and the ad column stacks several in a row where shadows would pile into mush. card2 survives as <code>.mfa-card--tinted</code> for nested or secondary blocks, which is the job it was actually good at. card3 dropped.',
		$out
	);
}

/** Section bands - the full-width blocks pages are assembled from. */
function mfa_ui_section_bands() {
	$out = mfa_ui_specimen(
		'section1',
		'CHOSEN - .mfa-band',
		'<div class="mfa-ui-band"><h3 class="mfa-h3">Section heading</h3><p class="mfa-body-muted">Body copy sits at a readable measure rather than the full page width.</p></div>'
	);

	$out .= mfa_ui_specimen(
		'section2',
		'CHOSEN - .mfa-band--tinted',
		'<div class="mfa-ui-band is-tinted"><h3 class="mfa-h3">Section heading</h3><p class="mfa-body-muted">Same structure, mint background.</p></div>'
	);

	$out .= mfa_ui_specimen(
		'cta1',
		'The live CTA, now built from .mfa-band rather than its own component',
		'<div class="mfa-band"><h2 class="mfa-band-title">Which way is the qiblah?</h2>'
		. '<p class="mfa-band-text">Point your phone and face the Kaaba from wherever you are.</p>'
		. '<a class="mfa-btn mfa-btn-primary" href="#bands">Open the Qibla Finder</a></div>'
	);

	return mfa_ui_block(
		'bands',
		'Sections and CTAs',
		'<strong>Both chosen</strong>, as <code>.mfa-band</code> and <code>.mfa-band--tinted</code>. The CTA boxes turned out to be a band plus a heading, a line of text and a button rather than their own component, so they now use these - which also fixed a real problem: two mint boxes stacked on /prayer-times/ read as one repeated block, so the tool cross-link is plain and the travel CTA tinted.',
		$out
	);
}

/**
 * The page skeleton: container, hero, the two-column row, and the two inner
 * blocks. These already exist in tool-page-v9.css, which is the problem -
 * they are page styles, so a template that is not a tool page gets none of
 * them and improvises instead.
 *
 * Several carry no padding or margin by design, so each is drawn inside a
 * labelled dashed frame; otherwise there would be nothing on screen to judge.
 */
function mfa_ui_section_layout() {
	$filler = '<div class="mfa-ui-fill">content</div>';

	$out = mfa_ui_specimen(
		'container1',
		'Outermost wrapper - centres to the max width, nothing else',
		'<div class="mfa-ui-frame mfa-ui-container" data-label="container1">'
		. '<div class="mfa-ui-fill">No margin, no padding. Bottom clearance for the floating buttons is already applied sitewide to <code>body</code> in site-chrome-v1.css, so it must not be repeated here or it doubles.</div>'
		. '</div>'
	);

	$out .= mfa_ui_specimen(
		'hero1',
		'Full-bleed band, no padding or margin of its own',
		'<div class="mfa-ui-frame mfa-ui-hero" data-label="hero1">'
		. '<div class="mfa-ui-hero-inner"><h3 class="mfa-h3">Prayer Times</h3>'
		. '<p>The inner block owns the spacing, so the hero can hold edge-to-edge artwork or padded text without either fighting the shell.</p></div>'
		. '</div>'
	);

	$out .= mfa_ui_specimen(
		'row1',
		'Two columns, narrower right - the mosque and tool page shape',
		'<div class="mfa-ui-row">'
		. '<div class="mfa-ui-frame mfa-ui-row-main" data-label="main 70%"><div class="mfa-ui-fill">Content column</div></div>'
		. '<div class="mfa-ui-frame mfa-ui-row-side" data-label="side 30%"><div class="mfa-ui-fill">Ads / related</div></div>'
		. '</div>'
	);

	$out .= mfa_ui_specimen(
		'inner1',
		'Inner block with padding - for reading',
		'<div class="mfa-ui-frame mfa-ui-inner" data-label="inner1">' . $filler . '</div>'
	);

	$out .= mfa_ui_specimen(
		'inner2',
		'Inner block with no padding - fills the full width',
		'<div class="mfa-ui-frame mfa-ui-inner-flush" data-label="inner2">' . $filler . '</div>'
	);

	return mfa_ui_block(
		'layout',
		'Layout',
		'The skeleton every page is assembled from. <code>row1</code> stacks at 900px, the documented tablet breakpoint. Pick <code>inner1</code> or <code>inner2</code> per section rather than site-wide - a table or map wants the flush one, body copy wants the padded one.',
		$out
	);
}
