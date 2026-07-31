<?php

add_shortcode( 'niz_user_weblink', 'niz_user_weblink_shortcode' );

function niz_user_weblink_shortcode( $atts ) {
    // Extract the 'value' attribute passed by JetEngine
    $args = shortcode_atts( array(
        'value' => '', 
    ), $atts );

    $post_id = $args['value'];

    if ( empty( $post_id ) ) {
        return '';
    }

    // 1. Get the post link from the ID
    $post_url = get_permalink( $post_id );

    if ( ! $post_url ) {
        return '';
    }

    // 2. Generate Gutenberg Button Block structure
    // This utilizes core classes ('wp-block-button' and 'wp-block-button__link') so it inherits your theme's global styles
    $button_html = sprintf(
        '<div class="wp-block-button niz-web-btn">
            <a class="wp-block-button__link wp-element-button" href="%1$s">
                Visit Page
            </a>
        </div>',
        esc_url( $post_url )
    );

    return $button_html;
}

add_shortcode('niz_user_affiliate', 'niz_user_affiliate_shortcode');
function niz_user_affiliate_shortcode($atts) {
    // 1. Crucial: Bring $wpdb into the function scope
    global $wpdb; 

    $user_id = get_current_user_id();
   
    $table = $wpdb->prefix . 'jet_cct_member';
    $aff = $wpdb->get_var(
        $wpdb->prepare(
            "SELECT COUNT(_ID) FROM {$table} WHERE referrer_id = %d", 
            $user_id
        )
    );

    $aff = $aff ? intval($aff) : 0;

    
    $stat = niz_user_field_by_userid($user_id, 'status');
    
    if ($stat=="Member"){
       $comm = '5 %'; 
       $rem2 = '<b>Upgrade to Premium Membership to increase your commission rate to 20%.</b>';  
    }else{
       $comm = '20 %'; 
       $rem2 = '';
    }

    if ($aff == 0){
        $rem = 'Start building your affiliate network today and unlock more opportunities to earn attractive income.';
    }else{
        $rem = 'Whenever your referrals subscribe to any of our services, you will earn ' . $comm . ' of the sales amount. ';
        $rem .= $rem2;        
    }
     
    $ret  = 'Total Referrals : ' . number_format($aff) . '<br>';
    $ret .= 'Commisstion Rate : ' . $comm . '<br>'; // <-- Fixed </b> here
    $ret .= $rem;

    return $ret;
}