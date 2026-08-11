<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
</main>

<?php
echo do_shortcode( '[mfa_site_footer]' );

// Floating AI chatbot, if the providing plugin registers it (kept sitewide
// on public pages, matching the previous Kadence "AI Chatbot" element).
if ( shortcode_exists( 'ai_chat' ) ) {
	echo do_shortcode( '[ai_chat]' );
}

wp_footer();
?>
</body>
</html>
