<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
</main>

<?php
echo do_shortcode( '[mfa_site_footer]' );

// NOTE: the legacy on-page chat ([ai_chat], enaizi/xai-chat.php) was
// deliberately dropped here - it's an old, separate chatbot with a hardcoded
// AI agent, superseded by the niz-wa WhatsApp assistant (Sofia button). If a
// real on-page website chat is wanted later, build a new one wired to niz-wa.

wp_footer();
?>
</body>
</html>
