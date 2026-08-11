<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * [mfa_modal] - reusable, accessible centered modal. Replaces Kadence Blocks
 * Pro modals on the directory single pages (Share / Edit / Claim / Add New),
 * generalising the Sofia popup into a data-attribute-driven dialog. modal-v1.js
 * handles open/close/ESC/scroll-lock via event delegation, so any number of
 * modals can coexist on one page.
 *
 * Usage (enclosing):
 *   [mfa_modal id="share" label="Share" title="Share this Page"
 *              button_class="mfa-btn mfa-btn-ghost"]
 *     ... inner content, may contain shortcodes ...
 *   [/mfa_modal]
 *
 * The trigger button renders inline; the dialog is appended right after it.
 * `id` should be unique on the page (auto-generated if omitted).
 */
add_shortcode( 'mfa_modal', 'mfa_modal_shortcode' );
function mfa_modal_shortcode( $atts, $content = '' ) {
	$a = shortcode_atts(
		array(
			'id'           => '',
			'label'        => 'Open',
			'title'        => '',
			'button_class' => 'mfa-btn mfa-btn-primary',
			'icon'         => '', // optional trusted leading markup (SVG/emoji) for the trigger
		),
		$atts,
		'mfa_modal'
	);

	$id = sanitize_html_class( $a['id'] );
	if ( '' === $id ) {
		$id = 'm' . wp_generate_password( 6, false, false );
	}
	$modal_id = 'mfa-modal-' . $id;

	$inner     = do_shortcode( trim( (string) $content ) );
	$close_svg = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"></line><line x1="6" y1="6" x2="18" y2="18"></line></svg>';

	ob_start();
	?>
	<button type="button" class="<?php echo esc_attr( $a['button_class'] ); ?> mfa-modal-trigger" data-mfa-modal-open="<?php echo esc_attr( $modal_id ); ?>" aria-haspopup="dialog"><?php echo $a['icon']; ?><?php echo esc_html( $a['label'] ); ?></button>
	<div class="mfa-modal-overlay" id="<?php echo esc_attr( $modal_id ); ?>" role="dialog" aria-modal="true" aria-hidden="true"<?php echo $a['title'] ? ' aria-label="' . esc_attr( $a['title'] ) . '"' : ''; ?>>
		<div class="mfa-modal-dialog" role="document">
			<button type="button" class="mfa-modal-close" data-mfa-modal-close aria-label="Close"><?php echo $close_svg; ?></button>
			<?php if ( $a['title'] ) : ?>
				<h2 class="mfa-modal-title"><?php echo esc_html( $a['title'] ); ?></h2>
			<?php endif; ?>
			<div class="mfa-modal-body"><?php echo $inner; ?></div>
		</div>
	</div>
	<?php
	return ob_get_clean();
}
