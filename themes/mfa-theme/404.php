<?php
/**
 * 404 Not Found.
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

get_header();
?>
<div class="mfa-notfound">
	<h1>Page Not Found</h1>
	<p>The page you&rsquo;re looking for doesn&rsquo;t exist or has moved.</p>
	<a href="<?php echo esc_url( home_url( '/' ) ); ?>" class="mfa-btn mfa-btn-primary">Back to Home</a>
</div>
<?php
get_footer();
