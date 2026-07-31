<?php
// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// 1. Register the shortcode: [mosque_community]
add_shortcode( 'mosque_community', 'pewarisan_mosque_community_shortcode' );

function pewarisan_mosque_community_shortcode() {
    global $wpdb;

    if ( ! is_user_logged_in() ) {
        return '<p>Please log in to view or join the community.</p>';
    }

    $current_user_id = get_current_user_id();
    $current_post_id = get_the_ID(); // This gets the current CPT Post ID

    if ( ! $current_post_id ) {
        return '<p>Error: Mosque Post ID not found.</p>';
    }

    // MAP POST ID TO CCT ITEM ID
    $cct_mosque_id = get_post_meta( $current_post_id, 'item_id', true );
    $cct_mosque_id = ! empty( $cct_mosque_id ) ? intval( $cct_mosque_id ) : 0;

    if ( ! $cct_mosque_id ) {
        return '<p>Error: Linked Mosque CCT record not found.</p>';
    }

    // Check membership using the CCT Mosque ID
    $community_table = $wpdb->prefix . 'jet_cct_community';
    $membership = $wpdb->get_row( $wpdb->prepare(
        "SELECT * FROM $community_table WHERE mosque_id = %d AND user_id = %d LIMIT 1",
        $cct_mosque_id,
        $current_user_id
    ) );

    // Fetch mosque metrics from cct_mosque using the native '_ID' column
    $mosque_table = $wpdb->prefix . 'jet_cct_mosque';
    $mosque_data = $wpdb->get_row( $wpdb->prepare(
        "SELECT member_count, community_status FROM $mosque_table WHERE _ID = %d LIMIT 1", 
        $cct_mosque_id
    ) );
    
    $member_count = ! empty( $mosque_data->member_count ) ? intval( $mosque_data->member_count ) : 0;
    $community_status = ! empty( $mosque_data->community_status ) ? $mosque_data->community_status : 'not_created';

    // Get current user display name to pre-fill form
    $current_user = wp_get_current_user();
    $default_name = ! empty( $current_user->display_name ) ? $current_user->display_name : '';

    ob_start();
    ?>
    <div id="mosque-community-module" class="mosque-community-container" style="max-width:600px; margin:20px 0; font-family:sans-serif;">
        
        <?php if ( ! $membership ) : ?>
            <!-- CONDITION 1: USER IS NOT A MEMBER (SHOW FORM) -->
            <div id="community-join-wrapper" class="community-join-section">
                <h3 style="margin-bottom:5px;">Join Our Community</h3>
                <p style="color:#666; margin-top:0;">Be part of our mosque's growing family. Help us activate this digital space!</p>
                
                <!--<div style="background:#f9f9f9; padding:12px; border-radius:4px; margin-bottom:20px; font-size:14px; border-left:4px solid #ccc;">
                    <strong>Current Mosque Status:</strong> 
                    <span style="text-transform: capitalize; color:#222;">
                        <?php echo esc_html( str_replace('_', ' ', $community_status) ); ?>
                    </span> 
                    (<?php echo $member_count; ?> Members)
                </div>-->

                <form id="mosque-join-form" style="display:flex; flex-direction:column; gap:15px;">
                    <div>
                        <label style="display:block; font-weight:bold; margin-bottom:5px; font-size:14px;">Your Full Name</label>
                        <input type="text" name="full_name" value="<?php echo esc_attr( $default_name ); ?>" required style="width:100%; padding:10px; border:1px solid #ccc; border-radius:4px;">
                    </div>

                    <div style="display:flex; align-items:flex-start; gap:8px;">
                        <input type="checkbox" id="community_terms" name="terms" required style="margin-top:4px;">
                        <label for="community_terms" style="font-size:13px; color:#555; line-height:1.4;">
                            I agree to the Community Terms and Conditions and promise to respect other members and uphold the harmony of the mosque space.
                        </label>
                    </div>

                    <!-- Pass the Post ID via form, handler will decode it to item_id safely -->
                    <input type="hidden" name="mosque_post_id" value="<?php echo esc_attr( $current_post_id ); ?>">
                    <input type="hidden" name="action" value="join_mosque_community">
                    <?php wp_nonce_field( 'mosque_join_nonce_action', 'mosque_join_nonce' ); ?>

                    <button type="submit" id="join-submit-btn" style="background:#00a859; color:#fff; border:none; padding:12px 20px; border-radius:4px; font-weight:bold; cursor:pointer; font-size:15px; align-self:flex-start;">
                        Join This Community
                    </button>
                    <div id="form-message" style="margin-top: 10px; font-weight: bold; font-size: 14px;"></div>
                </form>
            </div>

        <?php else : ?>
            <!-- CONDITION 2: USER IS A MEMBER (SHOW WELCOME) -->
            <div class="community-welcome-section">
                <h2 style="margin-bottom:10px; color:#222;">Welcome Back, <?php echo esc_html( $membership->full_name ); ?>!</h2>
                
                <?php if ( $community_status === 'active' ) : ?>
                    <div class="status-box" style="background: #e6f7ed; color: #1e7e34; padding: 15px; border-radius: 5px; margin-bottom: 15px; border-left: 5px solid #28a745;">
                        <strong>✓ Official Active Community</strong>
                        <p style="margin: 5px 0 0 0; font-size: 14px;">Thank you for being a vital part of this active mosque network.</p>
                    </div>
                    <div style="background: #f4f4f4; padding: 30px; text-align: center; border: 1px dashed #ccc; border-radius: 4px;">
                        <em>[PLACEHOLDER: Main Active Community Content Panel]</em>
                    </div>

                <?php else : ?>
                    
                    <div class="status-box status-pending" style="background: #fffcf0; color: #856404; padding: 20px; border-radius: 8px; margin-bottom: 15px; border: 1px solid #ffeeba;">
                        <strong style="font-size: 18px; display: block; margin-bottom: 5px; color: #b7791f;">🚀 Pioneer Phase</strong>
                        <p style="margin: 0 0 15px 0; font-size: 14px; color: #555;">
                            We need <strong><?php echo ( 10 - $member_count ); ?> more pioneers</strong> to activate the official space for this mosque. Help us spread the word!
                        </p>
                    
                        <?php $progress_percentage = min( ( $member_count / 10 ) * 100, 100 ); ?>
                        <div style="background: #e2e8f0; border-radius: 10px; height: 12px; width: 100%; margin-bottom: 25px; overflow: hidden; box-shadow: inset 0 1px 2px rgba(0,0,0,0.1);">
                            <div style="background: #00a859; width: <?php echo $progress_percentage; ?>%; height: 100%; transition: width 0.5s ease;"></div>
                        </div>
                    
                        <div style="display: flex; flex-direction: column; gap: 12px;">
                            <label style="font-weight: bold; font-size: 13px; color: #4a5568; text-transform: uppercase; letter-spacing: 0.5px;">Invite Others to Join:</label>
                            
                            <?php 
                                // Generate a sharing text
                                $mosque_title = get_the_title($current_post_id);
                                $share_url = esc_url( get_permalink($current_post_id) );
                                $whatsapp_message = urlencode("Assalamualaikum! Let's build the digital community for *{$mosque_title}*. We need 10 pioneers to unlock our official mosque community hub. Join here: " . $share_url);
                            ?>
                    
                            <div style="display: flex; gap: 10px; flex-wrap: wrap;">
                                <a href="https://api.whatsapp.com/send?text=<?php echo $whatsapp_message; ?>" target="_blank" style="background: #25D366; color: #fff; text-decoration: none; padding: 10px 16px; border-radius: 5px; font-weight: bold; font-size: 14px; display: inline-flex; align-items: center; gap: 6px; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">
                                    🟢 Share via WhatsApp
                                </a>
                    
                                <button onclick="pewarisanCopyShareLink('<?php echo $share_url; ?>')" id="copy-share-btn" style="background: #4a5568; color: #fff; border: none; padding: 10px 16px; border-radius: 5px; font-weight: bold; font-size: 14px; cursor: pointer; display: inline-flex; align-items: center; gap: 6px; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">
                                    📋 Copy Link
                                </button>
                            </div>
                        </div>
                    </div>
                    
                    <script>
                    function pewarisanCopyShareLink(url) {
                        navigator.clipboard.writeText(url).then(function() {
                            const btn = document.getElementById('copy-share-btn');
                            const originalText = btn.innerHTML;
                            btn.innerHTML = '✨ Link Copied!';
                            btn.style.background = '#2d3748';
                            setTimeout(() => {
                                btn.innerHTML = originalText;
                                btn.style.background = '#4a5568';
                            }, 2000);
                        }, function(err) {
                            alert('Could not copy text automatically.');
                        });
                    }
                    </script>
                    
                <?php endif; ?>
            </div>
        <?php endif; ?>

    </div>

    <!-- AJAX processing script -->
    <script style="display:none;">
    document.addEventListener('DOMContentLoaded', function() {
        const form = document.getElementById('mosque-join-form');
        if(!form) return;

        form.addEventListener('submit', function(e) {
            e.preventDefault();
            
            const btn = document.getElementById('join-submit-btn');
            const msgContainer = document.getElementById('form-message');
            
            btn.disabled = true;
            btn.innerText = 'Joining...';
            msgContainer.innerText = '';

            const formData = new FormData(form);

            fetch('<?php echo admin_url('admin-ajax.php'); ?>', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if(data.success) {
                    msgContainer.style.color = '#1e7e34';
                    msgContainer.innerText = data.data.message;
                    setTimeout(() => {
                        window.location.reload();
                    }, 1000);
                } else {
                    msgContainer.style.color = '#dc3545';
                    msgContainer.innerText = data.data.message;
                    btn.disabled = false;
                    btn.innerText = 'Join This Community';
                }
            })
            .catch(error => {
                msgContainer.style.color = '#dc3545';
                msgContainer.innerText = 'An unexpected error occurred.';
                btn.disabled = false;
                btn.innerText = 'Join This Community';
            });
        });
    });
    </script>
    <?php
    return ob_get_clean();
}


// =========================================================================
// 2. AJAX Form Processing & Database Calculation Handler
// =========================================================================
add_action( 'wp_ajax_join_mosque_community', 'pewarisan_handle_mosque_join_request' );

function pewarisan_handle_mosque_join_request() {
    global $wpdb;

    // Verify security token
    if ( ! isset($_POST['mosque_join_nonce']) || ! wp_verify_nonce( $_POST['mosque_join_nonce'], 'mosque_join_nonce_action' ) ) {
        wp_send_json_error( array( 'message' => 'Security check failed. Please refresh the page.' ) );
    }

    if ( ! is_user_logged_in() ) {
        wp_send_json_error( array( 'message' => 'You must be logged in to join.' ) );
    }

    $user_id   = get_current_user_id();
    $post_id   = isset( $_POST['mosque_post_id'] ) ? intval( $_POST['mosque_post_id'] ) : 0;
    $full_name = isset( $_POST['full_name'] ) ? sanitize_text_field( $_POST['full_name'] ) : '';

    // Convert Post ID into CCT Item ID using meta structure
    $cct_mosque_id = get_post_meta( $post_id, 'item_id', true );
    $cct_mosque_id = ! empty( $cct_mosque_id ) ? intval( $cct_mosque_id ) : 0;

    if ( ! $cct_mosque_id || empty( $full_name ) ) {
        wp_send_json_error( array( 'message' => 'Valid Mosque identifier or name missing.' ) );
    }

    $community_table = $wpdb->prefix . 'jet_cct_community';
    $mosque_table    = $wpdb->prefix . 'jet_cct_mosque';

    // Anti-duplicate protection
    $exists = $wpdb->get_var( $wpdb->prepare(
        "SELECT COUNT(*) FROM $community_table WHERE mosque_id = %d AND user_id = %d",
        $cct_mosque_id,
        $user_id
    ) );

    if ( $exists > 0 ) {
        wp_send_json_success( array( 'message' => 'You are already a member! Syncing...' ) );
    }

    // A. Insert member using CCT ID reference
    $inserted = $wpdb->insert(
        $community_table,
        array(
            'mosque_id'   => $cct_mosque_id,
            'user_id'     => $user_id,
            'full_name'   => $full_name,
            'status'      => 'member',
            'cct_created' => current_time( 'mysql' )
        ),
        array( '%d', '%d', '%s', '%s', '%s' )
    );

    if ( false === $inserted ) {
        wp_send_json_error( array( 'message' => 'Database error: Unable to join community.' ) );
    }

    // B. Re-calculate total members based on CCT ID
    $total_members = intval( $wpdb->get_var( $wpdb->prepare(
        "SELECT COUNT(*) FROM $community_table WHERE mosque_id = %d",
        $cct_mosque_id
    ) ) );

    // C. Threshold status configuration
    $new_status = 'not_created';
    if ( $total_members >= 10 ) {
        $new_status = 'active';
    } elseif ( $total_members > 0 && $total_members < 10 ) {
        $new_status = 'pending';
    }

    // D. Update row target targeting the primary auto-increment field '_ID'
    $wpdb->update(
        $mosque_table,
        array(
            'member_count'     => $total_members,
            'community_status' => $new_status
        ),
        array( '_ID' => $cct_mosque_id ), 
        array( '%d', '%s' ),
        array( '%d' )
    );

    wp_send_json_success( array( 'message' => 'Welcome to the community! Successfully joined.' ) );
}