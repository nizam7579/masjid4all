<?php

///////////////////
add_shortcode('niz_create_namecard', 'niz_handle_namecard_shortcode');

function niz_handle_namecard_shortcode() {

    $user_id    = get_current_user_id();
    $user_info  = get_userdata($user_id);
    $first_name = niz_user_field_by_userid($user_id, 'name');
    $item_id    = niz_user_field_by_userid($user_id, '_ID');
    $namecard   = niz_user_field_by_userid($user_id, 'namecard');
    $phone      = niz_user_field_by_userid($user_id, 'phone');
    
    // Check if namecard already exists via your custom helper function
    $existing_namecard = niz_user_field_by_userid($user_id, 'namecard');
    $url = 'https://staging.masjid4all.com/' . $namecard;
    $link = '<a href="' . $url . '" target="_blank">' . $url . '</a>';
    
    // If already exists, show success message immediately
    if ( ! empty($existing_namecard) ) {
        // Give Points
        $update_info = niz_user_field_by_userid($user_id,'chk_card') ;
        if ($update_info!='Yes'){
            // Issue Points
            niz_user_add_points($user_id, 'Create Name Card', 100);
            // Mark updated
            niz_user_update_field($user_id,'chk_card', 'Yes');
        }
        echo '<style>.nocard { display: none !important; }</style>';
        return '<div class="namecard-success" style="color: green; font-weight: bold; margin: 15px 0;">View your actual namecard at<br>' . $link . '</div>';
    }else{
        echo '<style>.namecard { display: none !important; }</style>';
    }

    $error = '';

    // 2. Handle Form Submission
    if ( isset($_POST['niz_submit_namecard']) ) {
        // Verify nonce for security
        if ( ! isset($_POST['niz_namecard_nonce']) || ! wp_verify_nonce($_POST['niz_namecard_nonce'], 'niz_namecard_nonce_action') ) {
            $error = 'Security check failed. Please try again.';
        } else {
            $namecard_input = sanitize_text_field($_POST['namecard_slug']);
            $slug = sanitize_title($namecard_input); 

            if ( empty($slug) ) {
                $error = 'Please enter a valid name.';
            }elseif ( ! preg_match('/^[a-z0-9\-]+$/', $namecard_input) ) {
                $error = 'Invalid characters detected! Please use only lowercase letters, numbers, and no spaces (use hyphens instead).';    
            } else {
                
                // --- STRICKT SLUG CHECK ACROSS ALL POST TYPES ---
                global $wpdb;
                $slug_exists = $wpdb->get_var( $wpdb->prepare(
                    "SELECT ID FROM $wpdb->posts WHERE post_name = %s AND post_status IN ('publish', 'future', 'draft', 'pending')",
                    $slug
                ) );

                if ( $slug_exists ) {
                    $error = 'This name card URL is already taken. Please try another variant.';
                } else {
                    // Create the post inside your dedicated Custom Post Type ('card')
                    $category = get_category_by_slug('affiliate');
                    $post_data = array(
                        'post_title'    => $first_name,
                        'post_name'     => $slug,
                        'post_status'   => 'publish',
                        'post_type'     => 'post', 
                        'post_author'   => $user_id,
                        'post_category' => array($category->term_id),
                    );
                    $post_id = wp_insert_post($post_data);

                    if ( ! is_wp_error($post_id) && $post_id !== 0 ) {
                        // Update the custom post 'card' postmeta 'item_id' with your pre-set variable
                        update_post_meta($post_id, 'item_id', $item_id);
                        
                        $image_url = 'https://staging.masjid4all.com/wp-content/uploads/2026/05/1.jpg'; 

                        if ( ! empty( $image_url ) ) {
                            
                            // Look up the existing attachment ID from your database via its URL
                            $attachment_id = attachment_url_to_postid( $image_url );
                    
                            // If an ID is found (it returns greater than 0), set it as the featured image
                            if ( $attachment_id > 0 ) {
                                set_post_thumbnail( $post_id, $attachment_id );
                            }
                        }
    
                        // --- Direct MySQL Update to JetEngine CCT ---
                        $cct_table_name = $wpdb->prefix . 'jet_cct_member'; 

                        if ( $wpdb->get_var( $wpdb->prepare( "SHOW TABLES LIKE %s", $cct_table_name ) ) === $cct_table_name ) {
                            $wpdb->update(
                                $cct_table_name,
                                // 1. Data to update (Key => Value)
                                array( 
                                    'namecard'        => $slug,
                                    'post_id'         => $post_id,
                                    'job_title'       => 'Your Job Title or Tagline',
                                    'introduction'    => 'Short introduction abour yourself',
                                    'affiliate_wa'    => $phone,
                                    'affiliate_phone' => $phone,
                                     
                                ), 
                                // 2. WHERE clauses combined into ONE single array
                                array( 
                                    'user_id' => $user_id 
                                ), 
                                // 3. Format definition for the Data array
                                array( '%s','%s','%s','%s','%s','%s'), 
                                // 4. Format definitions for the WHERE array (matches order of your keys)
                                array( '%d') 
                            );
                            
                            // Give Points
                            $update_info = niz_user_field_by_userid($user_id,'chk_card') ;
                            if ($update_info!='Yes'){
                                // Issue Points
                                niz_user_add_points($user_id, 'Create Name Card', 100);
                                // Mark updated
                                niz_user_update_field($user_id,'chk_card', 'Yes');
                            }
                        }
                        

                        // Backup: Update traditional user meta standard table
                        // update_user_meta($user_id, 'namecard', $slug);

                        // Success output string
                        $url = 'https://staging.masjid4all.com/' . $slug;
                        $link = '<a href="' . $url . '" target="_blank">' . $url . '</a>';
                        return '<div class="namecard-success" style="color: green; font-weight: bold; margin: 15px 0;">Your namecard is created at<br>' . $link .'</div>';
                    } else {
                        $error = 'Something went wrong while creating your post. Please check permissions.';
                    }
                }
            }
        }
    }

    
    
    // 3. Render Form
    $output = '<div class="niz-namecard-form-wrapper" style="margin: 20px 0;">';
    
    if ( ! empty($error) ) {
        $output .= '<p class="niz-error" style="color: red; font-weight: bold;">' . esc_html($error) . '</p>';
    }
    
    $output .= '<form action="" method="POST">';
    $output .= wp_nonce_field('niz_namecard_nonce_action', 'niz_namecard_nonce', true, false);
    $output .= '<label for="namecard_slug" style="display:block; margin-bottom: 8px; font-weight:bold;">Choose your Name Card extension:</label>';
    $output .= '<div style="display: flex; align-items: center; margin-bottom: 15px;">';
    $output .= '<span style="background: #eee; padding: 8px; border: 1px solid #ccc; border-right: none; color: #333;">staging.masjid4all.com/</span>';
    $output .= '<input type="text" id="namecard_slug" name="namecard_slug" placeholder="yourname" required style="padding: 8px; border: 1px solid #ccc; min-width: 150px;">';
    $output .= '</div>';
    $output .= '<input type="submit" name="niz_submit_namecard" value="Create my Name Card" class="button button-primary" style="padding: 10px 20px; cursor: pointer;">';
    $output .= '</form>';
    $output .= '</div>';
    
    return $output;

}