<?php

// =============================================
// SHORTCODE: [niz_review]
// =============================================
add_shortcode('niz_review', 'niz_review_display');

function niz_review_display() {
    global $post;
    
    if (!is_object($post)) {
        return '<p>Invalid post.</p>';
    }
    
    $post_id = $post->ID;
    $post_type = $post->post_type;
    
    $allowed_types = array('masjid', 'business', 'web');
    if (!in_array($post_type, $allowed_types)) {
        return '<p>Reviews are not available for this content type.</p>';
    }
    
    ob_start();
    ?>
    <script type="text/javascript">
    if (typeof niz_review_ajax === 'undefined') {
        var niz_review_ajax = {
            ajax_url: '<?php echo admin_url('admin-ajax.php'); ?>',
            nonce: '<?php echo wp_create_nonce('niz_review_nonce'); ?>'
        };
    }
    </script>
    
    <div class="niz-review-container" data-post-id="<?php echo esc_attr($post_id); ?>" data-post-type="<?php echo esc_attr($post_type); ?>">
        
        <?php
        // Average rating
        $avg_rating = niz_review_get_average_rating($post_id, $post_type);
        if ($avg_rating > 0) : ?>
            <div class="niz-review-average">
                <strong>Average Rating:</strong>
                <div class="niz-stars niz-stars-display">
                    <?php echo niz_review_display_stars($avg_rating); ?>
                </div>
                <span>(<?php echo number_format($avg_rating, 1); ?>/5)</span>
            </div>
        <?php endif; ?>
        
        <div id="niz-reviews-list">
            <?php niz_review_display_reviews($post_id, $post_type); ?>
        </div>
        
        <?php if (is_user_logged_in()) : 
            $current_user_id = get_current_user_id();
            $has_reviewed = niz_review_user_has_reviewed($current_user_id, $post_id, $post_type);
            ?>
            
            <?php if (!$has_reviewed) : ?>
                <div class="niz-review-action">
                    <button type="button" class="niz-toggle-form-btn niz-add-review-btn">📝 Post a Review</button>
                    <div class="niz-review-form-wrapper" style="display:none;">
                        <form class="niz-review-form" data-action="add">
                            <input type="hidden" name="action_type" value="add">
                            <input type="hidden" name="post_id" value="<?php echo esc_attr($post_id); ?>">
                            <input type="hidden" name="post_type" value="<?php echo esc_attr($post_type); ?>">
                            
                            <div class="niz-form-group">
                                <label>Your Rating:</label>
                                <div class="niz-star-rating">
                                    <?php for ($i = 1; $i <= 5; $i++) : ?>
                                        <span class="niz-star" data-value="<?php echo $i; ?>">☆</span>
                                    <?php endfor; ?>
                                    <input type="hidden" name="rating" value="0">
                                </div>
                            </div>
                            
                            <div class="niz-form-group">
                                <label>Your Review:</label>
                                <textarea name="review_text" rows="4" required></textarea>
                            </div>
                            
                            <div class="niz-form-group">
                                <button type="submit" class="niz-submit-btn">Submit Review</button>
                                <button type="button" class="niz-cancel-btn">Cancel</button>
                            </div>
                        </form>
                    </div>
                </div>
            <?php endif; ?>
            
        <?php else : ?>
            <p><a href="<?php echo wp_login_url(get_permalink()); ?>">Login to leave a review</a></p>
        <?php endif; ?>
        
    </div>
    
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script>
    jQuery(document).ready(function($) {
        // Toggle form
        $('.niz-toggle-form-btn').on('click', function() {
            $(this).closest('.niz-review-action').find('.niz-review-form-wrapper').slideToggle();
        });
        
        $('.niz-cancel-btn').on('click', function() {
            $(this).closest('.niz-review-form-wrapper').slideUp();
            $(this).closest('form')[0].reset();
            $(this).closest('form').find('.niz-star.selected').removeClass('selected');
            $(this).closest('form').find('input[name="rating"]').val(0);
        });
        
        // Star rating selection
        $('.niz-star-rating').on('click', '.niz-star', function() {
            var value = $(this).data('value');
            var $container = $(this).closest('.niz-star-rating');
            $container.find('.niz-star').each(function(index) {
                if (index < value) {
                    $(this).html('★').addClass('selected');
                } else {
                    $(this).html('☆').removeClass('selected');
                }
            });
            $container.find('input[name="rating"]').val(value);
        });
        
        // Submit review (add or reply)
        $(document).on('submit', '.niz-review-form', function(e) {
            e.preventDefault();
            var $form = $(this);
            var $button = $form.find('.niz-submit-btn, .niz-submit-reply');
            var formData = $form.serialize();
            var actionType = $form.find('input[name="action_type"]').val();
            
            if (actionType === 'add') {
                var rating = $form.find('input[name="rating"]').val();
                if (!rating || rating == 0) {
                    // No alert, just visual feedback (optional)
                    alert('Please select a star rating.');  // Keep this minimal feedback? User didn't ask to remove this, only success pop-ups. We'll keep validation alerts.
                    return false;
                }
            }
            
            var reviewText = $form.find('textarea[name="review_text"]').val();
            if (!reviewText.trim()) {
                alert('Please enter your review.');
                return false;
            }
            
            $button.prop('disabled', true).text('Submitting...');
            
            $.ajax({
                url: niz_review_ajax.ajax_url,
                type: 'POST',
                data: formData + '&action=niz_review_submit&nonce=' + niz_review_ajax.nonce,
                dataType: 'json',
                success: function(response) {
                    if (response.success) {
                        if (actionType === 'add') {
                            // Redirect to review tab and reload
                            window.location.hash = 'reviews';
                            window.location.reload();
                        } else if (actionType === 'reply') {
                            $('#niz-reviews-list').html(response.data.html);
                            $form.closest('.niz-reply-form').slideUp(function() { $(this).remove(); });
                            // No alert
                        }
                    } else {
                        // Show error in a non‑obtrusive way? For now, keep alert for errors.
                        alert(response.data.message || 'Error submitting review.');
                    }
                },
                error: function(xhr, status, error) {
                    alert('AJAX error: ' + error);
                },
                complete: function() {
                    $button.prop('disabled', false).text(actionType === 'reply' ? 'Submit Reply' : 'Submit Review');
                }
            });
        });
        
        // Edit review
        $(document).on('click', '.niz-edit-review', function() {
            var $reviewDiv = $(this).closest('.niz-review-item');
            var reviewId = $reviewDiv.data('review-id');
            var currentText = $reviewDiv.find('.niz-review-text').text();
            var currentRating = $reviewDiv.find('.niz-review-rating-value').data('rating');
            
            $reviewDiv.find('.niz-review-content').hide();
            $reviewDiv.find('.niz-review-actions').hide();
            
            var starsHtml = '';
            for (var i = 1; i <= 5; i++) {
                starsHtml += '<span class="niz-star" data-value="' + i + '">☆</span>';
            }
            
            var $editForm = $('<form class="niz-edit-form">\
                <div class="niz-star-rating-edit">' + starsHtml + '\
                    <input type="hidden" name="rating" value="0">\
                </div>\
                <textarea name="review_text" rows="3" required>' + currentText + '</textarea>\
                <div class="niz-form-group">\
                    <button type="submit" class="niz-save-edit">Save Changes</button>\
                    <button type="button" class="niz-cancel-edit">Cancel</button>\
                </div>\
            </form>');
            
            $reviewDiv.append($editForm);
            
            for (var i = 1; i <= currentRating; i++) {
                $editForm.find('.niz-star').eq(i-1).html('★').addClass('selected');
            }
            $editForm.find('input[name="rating"]').val(currentRating);
            
            $editForm.find('.niz-star-rating-edit').on('click', '.niz-star', function() {
                var value = $(this).data('value');
                var $container = $(this).closest('.niz-star-rating-edit');
                $container.find('.niz-star').each(function(index) {
                    if (index < value) {
                        $(this).html('★').addClass('selected');
                    } else {
                        $(this).html('☆').removeClass('selected');
                    }
                });
                $container.find('input[name="rating"]').val(value);
            });
            
            $editForm.find('.niz-save-edit').on('click', function(e) {
                e.preventDefault();
                var newText = $editForm.find('textarea[name="review_text"]').val();
                var newRating = $editForm.find('input[name="rating"]').val();
                if (!newText.trim()) { alert('Please enter review text.'); return; }
                
                $.ajax({
                    url: niz_review_ajax.ajax_url,
                    type: 'POST',
                    data: {
                        action: 'niz_review_edit',
                        review_id: reviewId,
                        review_text: newText,
                        rating: newRating,
                        nonce: niz_review_ajax.nonce
                    },
                    dataType: 'json',
                    success: function(response) {
                        if (response.success) {
                            $('#niz-reviews-list').html(response.data.html);
                            // No alert
                        } else {
                            alert(response.data.message);
                        }
                    }
                });
            });
            
            $editForm.find('.niz-cancel-edit').on('click', function() {
                $editForm.remove();
                $reviewDiv.find('.niz-review-content').show();
                $reviewDiv.find('.niz-review-actions').show();
            });
        });
        
        // Delete review
        $(document).on('click', '.niz-delete-review', function() {
            if (!confirm('Delete this review? Cannot delete if it has replies.')) return;
            var reviewId = $(this).data('review-id');
            $.ajax({
                url: niz_review_ajax.ajax_url,
                type: 'POST',
                data: {
                    action: 'niz_review_delete',
                    review_id: reviewId,
                    nonce: niz_review_ajax.nonce
                },
                dataType: 'json',
                success: function(response) {
                    if (response.success) {
                        window.location.hash = 'reviews';
                        window.location.reload();
                    } else {
                        alert(response.data.message);
                    }
                }
            });
        });
        
        // Reply to review
        $(document).on('click', '.niz-reply-review', function() {
            var $reviewDiv = $(this).closest('.niz-review-item');
            var parentId = $reviewDiv.data('review-id');
            if ($reviewDiv.find('.niz-reply-form').length) {
                $reviewDiv.find('.niz-reply-form').slideToggle();
                return;
            }
            var $replyForm = $('<div class="niz-reply-form">\
                <form class="niz-review-form" data-action="reply">\
                    <input type="hidden" name="action_type" value="reply">\
                    <input type="hidden" name="post_id" value="' + $('.niz-review-container').data('post-id') + '">\
                    <input type="hidden" name="post_type" value="' + $('.niz-review-container').data('post-type') + '">\
                    <input type="hidden" name="parent_id" value="' + parentId + '">\
                    <div class="niz-form-group">\
                        <textarea name="review_text" rows="3" placeholder="Write a reply..." required></textarea>\
                    </div>\
                    <div class="niz-form-group">\
                        <button type="submit" class="niz-submit-reply">Submit Reply</button>\
                        <button type="button" class="niz-cancel-reply">Cancel</button>\
                    </div>\
                </form>\
            </div>');
            $reviewDiv.append($replyForm);
            $replyForm.hide().slideDown();
            $replyForm.find('.niz-cancel-reply').on('click', function() {
                $replyForm.slideUp(function() { $(this).remove(); });
            });
        });
    });

    </script>
    
    <style>
    .niz-review-container { margin: 20px 0; }
    .niz-review-average { margin-bottom: 20px; padding: 10px; background: #f5f5f5; border-radius: 5px; }
    .niz-stars-display .niz-star { font-size: 18px; color: #ffc107; }
    .niz-review-item { margin: 15px 0; padding: 15px; border-left: 3px solid #ddd; background: #fafafa; }
    .niz-review-item[data-level="1"] { margin-left: 30px; }
    .niz-review-item[data-level="2"] { margin-left: 60px; }
    .niz-review-meta { font-size: 12px; color: #666; margin-bottom: 8px; }
    .niz-review-rating { margin: 5px 0; }
    .niz-star-rating .niz-star, .niz-star-rating-edit .niz-star { font-size: 24px; cursor: pointer; color: #ddd; }
    .niz-star-rating .niz-star.selected, .niz-star-rating-edit .niz-star.selected { color: #ffc107; }
    .niz-review-actions { margin-top: 10px; }
    .niz-review-actions button, .niz-review-action button { margin-right: 10px; padding: 5px 10px; cursor: pointer; }
    .niz-review-form-wrapper, .niz-edit-form, .niz-reply-form { margin-top: 15px; padding: 15px; background: #fff; border: 1px solid #ddd; }
    .niz-form-group { margin-bottom: 15px; }
    .niz-form-group label { display: block; margin-bottom: 5px; font-weight: bold; }
    .niz-form-group textarea { width: 100%; padding: 8px; }
    .niz-submit-btn, .niz-save-edit { background: #0073aa; color: white; border: none; padding: 8px 15px; cursor: pointer; }
    .niz-cancel-btn, .niz-cancel-edit { background: #ccc; color: black; border: none; padding: 8px 15px; cursor: pointer; margin-left: 10px; }
    </style>
    
    <?php
    return ob_get_clean();
}

// =============================================
// HELPER FUNCTIONS
// =============================================

function niz_review_get_average_rating($post_id, $post_type) {
    global $wpdb;
    $table = $wpdb->prefix . 'jet_cct_review';
    $avg = $wpdb->get_var($wpdb->prepare(
        "SELECT AVG(rating) FROM `{$table}` 
         WHERE post_id = %d AND post_type = %s AND parent_id = 0 AND rating IS NOT NULL",
        $post_id, $post_type
    ));
    return $avg ? round($avg, 1) : 0;
}

function niz_review_user_has_reviewed($user_id, $post_id, $post_type) {
    global $wpdb;
    $table = $wpdb->prefix . 'jet_cct_review';
    $exists = $wpdb->get_var($wpdb->prepare(
        "SELECT _ID FROM `{$table}` 
         WHERE user_id = %d AND post_id = %d AND post_type = %s AND parent_id = 0",
        $user_id, $post_id, $post_type
    ));
    return $exists ? true : false;
}

function niz_review_display_reviews($post_id, $post_type, $parent_id = 0, $level = 0) {
    global $wpdb;
    $table = $wpdb->prefix . 'jet_cct_review';
    $reviews = $wpdb->get_results($wpdb->prepare(
        "SELECT * FROM `{$table}` 
         WHERE post_id = %d AND post_type = %s AND parent_id = %d 
         ORDER BY cct_created ASC",
        $post_id, $post_type, $parent_id
    ));
    
    if (!$reviews && $parent_id == 0) {
        echo '<p>⭐ Be the first to review!</p>';
        return;
    }
    
    foreach ($reviews as $review) {
        $user = get_userdata($review->user_id);
        $user_name = $user ? $user->display_name : 'Anonymous';
        $current_user_id = get_current_user_id();
        $can_edit = ($current_user_id == $review->user_id);
        $can_delete = $can_edit && !niz_review_has_replies($review->_ID);
        ?>
        <div class="niz-review-item" data-review-id="<?php echo $review->_ID; ?>" data-level="<?php echo $level; ?>">
            <div class="niz-review-content">
                <div class="niz-review-meta">
                    <strong><?php echo esc_html($user_name); ?></strong> 
                    on <?php echo date('F j, Y', strtotime($review->cct_created)); ?>
                    <?php if ($review->cct_modified != $review->cct_created) : ?>
                        <em>(edited)</em>
                    <?php endif; ?>
                </div>
                
                <?php if ($review->rating && $review->parent_id == 0) : ?>
                    <div class="niz-review-rating">
                        <?php echo niz_review_display_stars($review->rating); ?>
                        <span class="niz-review-rating-value" data-rating="<?php echo $review->rating; ?>"></span>
                    </div>
                <?php endif; ?>
                
                <div class="niz-review-text"><?php echo nl2br(esc_html($review->review_text)); ?></div>
                
                <div class="niz-review-actions">
                    <?php if (is_user_logged_in()) : ?>
                        <?php if ($can_edit) : ?>
                            <button type="button" class="niz-edit-review">✏️ Edit</button>
                        <?php endif; ?>
                        <?php if ($can_delete) : ?>
                            <button type="button" class="niz-delete-review" data-review-id="<?php echo $review->_ID; ?>">🗑️ Delete</button>
                        <?php endif; ?>
                        <button type="button" class="niz-reply-review">💬 Reply</button>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <?php
        niz_review_display_reviews($post_id, $post_type, $review->_ID, $level + 1);
    }
}

function niz_review_has_replies($review_id) {
    global $wpdb;
    $table = $wpdb->prefix . 'jet_cct_review';
    $count = $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM `{$table}` WHERE parent_id = %d",
        $review_id
    ));
    return $count > 0;
}

function niz_review_display_stars($rating) {
    $stars = '';
    $full = floor($rating);
    $half = ($rating - $full) >= 0.5;
    for ($i = 1; $i <= 5; $i++) {
        if ($i <= $full) $stars .= '★';
        elseif ($half && $i == $full + 1) $stars .= '½';
        else $stars .= '☆';
    }
    return '<span class="niz-star-display">' . $stars . '</span>';
}

// =============================================
// AJAX HANDLERS
// =============================================
add_action('wp_ajax_niz_review_submit', 'niz_review_ajax_submit');

function niz_review_ajax_submit() {
    if (ob_get_level()) ob_clean();
    header('Content-Type: application/json');
    
    if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'niz_review_nonce')) {
        echo json_encode(['success' => false, 'data' => ['message' => 'Security check failed']]);
        wp_die();
    }
    if (!is_user_logged_in()) {
        echo json_encode(['success' => false, 'data' => ['message' => 'Please login']]);
        wp_die();
    }
    
    $action_type = sanitize_text_field($_POST['action_type']);
    $post_id = intval($_POST['post_id']);
    $post_type = sanitize_text_field($_POST['post_type']);
    $user_id = get_current_user_id();
    $review_text = sanitize_textarea_field($_POST['review_text']);
    
    if (empty($review_text)) {
        echo json_encode(['success' => false, 'data' => ['message' => 'Review text required']]);
        wp_die();
    }
    
    global $wpdb;
    $table = $wpdb->prefix . 'jet_cct_review';
    
    if ($action_type == 'add') {
        $rating = intval($_POST['rating']);
        if ($rating < 1 || $rating > 5) {
            echo json_encode(['success' => false, 'data' => ['message' => 'Please select a rating']]);
            wp_die();
        }
        $exists = $wpdb->get_var($wpdb->prepare(
            "SELECT _ID FROM `{$table}` WHERE user_id = %d AND post_id = %d AND post_type = %s AND parent_id = 0",
            $user_id, $post_id, $post_type
        ));
        if ($exists) {
            echo json_encode(['success' => false, 'data' => ['message' => 'You already reviewed this']]);
            wp_die();
        }
        $result = $wpdb->insert($table, [
            'post_id'      => $post_id,
            'post_type'    => $post_type,
            'user_id'      => $user_id,
            'parent_id'    => 0,
            'rating'       => $rating,
            'review_text'  => $review_text,
            'cct_created'  => current_time('mysql'),
            'cct_modified' => current_time('mysql')
        ]);
        if ($result === false) {
            echo json_encode(['success' => false, 'data' => ['message' => 'DB error: ' . $wpdb->last_error]]);
            wp_die();
        }
    } elseif ($action_type == 'reply') {
        $parent_id = intval($_POST['parent_id']);
        if (!$parent_id) {
            echo json_encode(['success' => false, 'data' => ['message' => 'Invalid parent']]);
            wp_die();
        }
        $result = $wpdb->insert($table, [
            'post_id'      => $post_id,
            'post_type'    => $post_type,
            'user_id'      => $user_id,
            'parent_id'    => $parent_id,
            'rating'       => null,
            'review_text'  => $review_text,
            'cct_created'  => current_time('mysql'),
            'cct_modified' => current_time('mysql')
        ]);
        if ($result === false) {
            echo json_encode(['success' => false, 'data' => ['message' => 'DB error: ' . $wpdb->last_error]]);
            wp_die();
        }
    } else {
        echo json_encode(['success' => false, 'data' => ['message' => 'Invalid action']]);
        wp_die();
    }
    
    ob_start();
    niz_review_display_reviews($post_id, $post_type);
    $html = ob_get_clean();
    echo json_encode(['success' => true, 'data' => ['message' => 'Review submitted', 'html' => $html]]);
    wp_die();
}

add_action('wp_ajax_niz_review_edit', 'niz_review_ajax_edit');
function niz_review_ajax_edit() {
    if (ob_get_level()) ob_clean();
    header('Content-Type: application/json');
    
    if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'niz_review_nonce')) {
        echo json_encode(['success' => false, 'data' => ['message' => 'Security failed']]);
        wp_die();
    }
    if (!is_user_logged_in()) {
        echo json_encode(['success' => false, 'data' => ['message' => 'Login required']]);
        wp_die();
    }
    
    $review_id = intval($_POST['review_id']);
    $review_text = sanitize_textarea_field($_POST['review_text']);
    $rating = intval($_POST['rating']);
    
    global $wpdb;
    $table = $wpdb->prefix . 'jet_cct_review';
    $review = $wpdb->get_row($wpdb->prepare("SELECT * FROM `{$table}` WHERE _ID = %d", $review_id));
    if (!$review || $review->user_id != get_current_user_id()) {
        echo json_encode(['success' => false, 'data' => ['message' => 'Not allowed']]);
        wp_die();
    }
    
    $update = ['review_text' => $review_text, 'cct_modified' => current_time('mysql')];
    if ($review->parent_id == 0) {
        if ($rating >=1 && $rating <=5) $update['rating'] = $rating;
    }
    $wpdb->update($table, $update, ['_ID' => $review_id]);
    
    ob_start();
    niz_review_display_reviews($review->post_id, $review->post_type);
    $html = ob_get_clean();
    echo json_encode(['success' => true, 'data' => ['html' => $html]]);
    wp_die();
}

add_action('wp_ajax_niz_review_delete', 'niz_review_ajax_delete');
function niz_review_ajax_delete() {
    if (ob_get_level()) ob_clean();
    header('Content-Type: application/json');
    
    if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'niz_review_nonce')) {
        echo json_encode(['success' => false, 'data' => ['message' => 'Security failed']]);
        wp_die();
    }
    if (!is_user_logged_in()) {
        echo json_encode(['success' => false, 'data' => ['message' => 'Login required']]);
        wp_die();
    }
    
    $review_id = intval($_POST['review_id']);
    global $wpdb;
    $table = $wpdb->prefix . 'jet_cct_review';
    $review = $wpdb->get_row($wpdb->prepare("SELECT * FROM `{$table}` WHERE _ID = %d", $review_id));
    if (!$review || $review->user_id != get_current_user_id()) {
        echo json_encode(['success' => false, 'data' => ['message' => 'Not allowed']]);
        wp_die();
    }
    $has_replies = niz_review_has_replies($review_id);
    if ($has_replies) {
        echo json_encode(['success' => false, 'data' => ['message' => 'Cannot delete review with replies']]);
        wp_die();
    }
    $wpdb->delete($table, ['_ID' => $review_id]);
    echo json_encode(['success' => true, 'data' => ['message' => 'Deleted']]);
    wp_die();
}