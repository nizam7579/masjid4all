<?php
// shortcodes/install-button.php
if (!defined('ABSPATH')) exit;

add_shortcode('niz_mfa_install_button', 'niz_mfa_install_button_shortcode');

function niz_mfa_install_button_shortcode($atts, $content = null) {
    $atts = shortcode_atts([
        'text'  => '📱 Install App',
        'class' => 'install-button',
        'style' => ''
    ], $atts);

    $button_text  = esc_html($atts['text']);
    $button_class = esc_attr($atts['class']);
    
    // Inject display:none globally by default so it doesn't flash awkwardly on desktop platforms
    $base_style   = 'display: none; cursor: pointer;';
    $custom_style = $atts['style'] ? esc_attr($atts['style']) : '';
    $inline_style = 'style="' . $base_style . ' ' . $custom_style . '"';

    ob_start();
    ?>
    <button id="niz-mfa-install-btn" class="<?php echo $button_class; ?>" <?php echo $inline_style; ?>>
        <?php echo $button_text; ?>
    </button>

    <script>
    (function() {
        let deferredPrompt = null;
        const installBtn = document.getElementById('niz-mfa-install-btn');
        
        if (!installBtn) return;

        // Catch the native browser PWA installation offer
        window.addEventListener('beforeinstallprompt', (e) => {
            // Prevent Chrome 67 and earlier from automatically showing the prompt
            e.preventDefault();
            // Stash the event so it can be triggered executionally later
            deferredPrompt = e;
            
            // Unhide the button now that we definitely know the application is installable
            installBtn.style.display = 'inline-block';
        });

        installBtn.addEventListener('click', async () => {
            if (!deferredPrompt) {
                // If a manual global bridge exists, fallback to it
                if (typeof window.triggerInstallPrompt === 'function') {
                    window.triggerInstallPrompt();
                } else {
                    console.log('PWA installation criteria or deferred prompt asset is currently unavailable.');
                }
                return;
            }
            
            // Show the native browser installation prompt overlay
            deferredPrompt.prompt();
            
            // Wait for the user to respond to the prompt action
            const { outcome } = await deferredPrompt.userChoice;
            console.log(`User installation decision outcome state: ${outcome}`);
            
            // We can only use the beforeinstallprompt event object once; clear it out
            deferredPrompt = null;
            // Re-hide the button structure gracefully
            installBtn.style.display = 'none';
        });

        // Hide the button permanently if the application gets installed successfully
        window.addEventListener('appinstalled', (evt) => {
            installBtn.style.display = 'none';
            console.log('Application was successfully installed into system workspace.');
        });
    })();
    </script>
    <?php
    return ob_get_clean();
}