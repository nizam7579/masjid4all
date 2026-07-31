<?php

// ============================================
// SHORTCODE: Add New Mosque (mosque‑only version)
// $api_key = NIZ_GOOGLE_MAPS_API_KEY;
// ============================================

add_shortcode( 'niz_mfa_add_mosque', 'directory_add_mosque_shortcode' );

function directory_add_mosque_shortcode() {
	if ( ! is_user_logged_in() ) {
		return '<p class="must-login-msg">You must be <a href="' . wp_login_url( get_permalink() ) . '">logged in</a> to add a mosque.</p>';
	}

	global $directory_form_feedback;
	$output = '';

	if ( ! empty( $directory_form_feedback ) ) {
		$output .= $directory_form_feedback;
	}

	$api_key = NIZ_GOOGLE_MAPS_API_KEY;
	wp_enqueue_script( 'google-legacy-places', 'https://maps.googleapis.com/maps/api/js?key=' . $api_key . '&libraries=places', array(), null, true );

	$ajax_url = admin_url( 'admin-ajax.php' );

	wp_add_inline_script( 'google-legacy-places', "
		document.addEventListener('DOMContentLoaded', function() {
			var input = document.getElementById('mosque-search-input');
			if (!input) return;

			// Autocomplete – we can't restrict to 'mosque' in the dropdown,
			// so we use 'establishment' and filter afterwards.
			var autocomplete = new google.maps.places.Autocomplete(input, {
				types: ['establishment']
			});

			autocomplete.addListener('place_changed', function() {
				var place = autocomplete.getPlace();

				if (!place.place_id || !place.geometry) {
					alert('Please select a suggestion from the dropdown list.');
					return;
				}

				// --- STRICT MOSQUE-ONLY FILTER ---
				var structuralTypes = place.types || [];
				var lowercaseName = (place.name || '').toLowerCase();

				var islamicWorshipTypes = ['mosque'];
				var islamicWorshipKeywords = ['masjid', 'mosque', 'surau', 'musolla', 'madrasah', 'islamic centre', 'islamic center'];

				var isIslamicWorship = structuralTypes.some(function(type) { return islamicWorshipTypes.includes(type); }) ||
				                       islamicWorshipKeywords.some(function(word) { return lowercaseName.includes(word); });

				if ( ! isIslamicWorship ) {
					alert('Only mosques, surau, musolla or madrasah can be added using this form.');
					document.getElementById('mosque-search-input').value = '';
					document.getElementById('mosque-submit-container').style.display = 'none';
					document.getElementById('mosque-selection-feedback').innerHTML = '';
					return;
				}

				// Populate hidden fields
				document.getElementById('wp-mosque-place-id').value = place.place_id;
				document.getElementById('wp-mosque-name').value = place.name;
				document.getElementById('wp-mosque-lat').value = place.geometry.location.lat();
				document.getElementById('wp-mosque-lng').value = place.geometry.location.lng();
				document.getElementById('wp-mosque-address').value = place.formatted_address;

				var cityName = '';
				var countryName = '';
				var adminLevel2 = '';

				if (place.address_components) {
					for (var i = 0; i < place.address_components.length; i++) {
						var componentTypes = place.address_components[i].types;
						if (componentTypes.includes('locality')) { cityName = place.address_components[i].long_name; }
						if (componentTypes.includes('administrative_area_level_2')) { adminLevel2 = place.address_components[i].long_name; }
						if (componentTypes.includes('country')) { countryName = place.address_components[i].long_name; }
					}
				}
				var finalCity = cityName || adminLevel2 || '';
				document.getElementById('wp-mosque-city').value = finalCity;
				document.getElementById('wp-mosque-country').value = countryName;

				document.getElementById('wp-mosque-phone').value = place.international_phone_number || place.formatted_phone_number || '';
				document.getElementById('wp-mosque-website').value = place.website || '';
				document.getElementById('wp-mosque-rating').value = place.rating || '';
				document.getElementById('wp-mosque-rating-count').value = place.user_ratings_total || '0';
				document.getElementById('wp-mosque-status').value = place.mosque_status || '';

				var introText = (place.editorial_summary && place.editorial_summary.overview) ? place.editorial_summary.overview : '';
				document.getElementById('wp-mosque-introduction').value = introText;

				var standardHours = (place.opening_hours && place.opening_hours.weekday_text) ? JSON.stringify(place.opening_hours.weekday_text) : '';
				document.getElementById('wp-mosque-opening-hours').value = standardHours;

				// Force primary type and all types to 'Mosque'
				document.getElementById('wp-mosque-primary-type').value = 'Mosque';
				document.getElementById('wp-mosque-all-types').value = JSON.stringify(['Mosque']);

				// --- LIVE CHECK: is this mosque already listed? ---
				var formData = new FormData();
				formData.append('action', 'directory_check_existing_mosque');
				formData.append('place_id', place.place_id);

				fetch('" . esc_url( $ajax_url ) . "', {
					method: 'POST',
					body: formData
				})
				.then(function(response) { return response.json(); })
				.then(function(data) {
					var submitBtn = document.querySelector('button[name=\"submit_mosque\"]');
					var feedbackEl = document.getElementById('mosque-selection-feedback');

					if (data.success && data.data.exists) {
						// Already exists
						var feedbackHtml = '<div style=\"background-color: #fff3cd; color: #856404; padding: 12px; border-left: 5px solid #ffc107; margin-bottom: 10px; border-radius: 4px;\">';
						feedbackHtml += '<strong>This mosque is already in our directory.</strong><br>';
						feedbackHtml += '<a href=\"' + data.data.page_url + '\" target=\"_blank\" style=\"color: #856404; font-weight: bold; text-decoration: underline;\">Visit Mosque Page</a>';
						feedbackHtml += '</div>';
						feedbackHtml += '<span style=\"color:#999; font-size:0.9em;\">Selected: ' + place.name + '</span>';

						feedbackEl.innerHTML = feedbackHtml;

						submitBtn.disabled = true;
						submitBtn.innerText = 'Mosque Already Listed';
						submitBtn.style.background = '#cccccc';
						submitBtn.style.cursor = 'not-allowed';
					} else {
						// New mosque – enable submission
						submitBtn.disabled = false;
						submitBtn.innerText = 'Add Mosque to Directory';
						submitBtn.style.background = '#0073aa';
						submitBtn.style.cursor = 'pointer';

						var feedbackHtml = '<strong>Selected Mosque:</strong> ' + place.name + '<br>';
						if (introText) { feedbackHtml += '<em>\"' + introText + '\"</em><br>'; }
						if (place.rating) { feedbackHtml += '⭐ ' + place.rating + ' (' + place.user_ratings_total + ' reviews) <br>'; }
						feedbackHtml += '<small style=\"color:#666;\">' + place.formatted_address + '</small>';

						feedbackEl.innerHTML = feedbackHtml;
					}
					document.getElementById('mosque-submit-container').style.display = 'block';
				})
				.catch(function(err) {
					console.error('Directory verification error:', err);
				});
			});

			// Prevent accidental form submission on Enter key in the search field
			input.addEventListener('keydown', function(e) {
				if (e.keyCode === 13) { e.preventDefault(); }
			});
		});
	" );

	ob_start();
	?>
	<div class="directory-shortcode-wrapper" style="max-width: 600px; margin: 0 auto; padding: 10px;">
		<?php echo $output; ?>

		<form id="add-mosque-form" method="POST" action="">
			<div class="form-group" style="margin-bottom: 15px;">
				<label for="mosque-search-input" style="display:block; font-weight:bold; margin-bottom: 8px; font-size:1.1em;">Find a Mosque on Google Maps:</label>
				<input type="text" id="mosque-search-input" placeholder="Type mosque name (e.g., Masjid Al-Falah)..." required style="width: 100%; padding: 12px; border: 1px solid #ccc; border-radius: 4px; font-size: 1em; box-sizing: border-box;">
				<div id="mosque-selection-feedback" style="margin-top: 10px; font-size: 0.95em; line-height: 1.4;"></div>
			</div>

			<!-- Hidden fields – note the 'mosque' prefix to avoid collisions with other forms -->
			<input type="hidden" id="wp-mosque-place-id" name="mosque_place_id">
			<input type="hidden" id="wp-mosque-name" name="mosque_name">
			<input type="hidden" id="wp-mosque-lat" name="mosque_lat">
			<input type="hidden" id="wp-mosque-lng" name="mosque_lng">
			<input type="hidden" id="wp-mosque-primary-type" name="mosque_primary_type">
			<input type="hidden" id="wp-mosque-all-types" name="mosque_all_types">
			<input type="hidden" id="wp-mosque-address" name="mosque_address">
			<input type="hidden" id="wp-mosque-city" name="mosque_city">
			<input type="hidden" id="wp-mosque-country" name="mosque_country">
			<input type="hidden" id="wp-mosque-phone" name="mosque_phone">
			<input type="hidden" id="wp-mosque-website" name="mosque_website">
			<input type="hidden" id="wp-mosque-rating" name="mosque_rating">
			<input type="hidden" id="wp-mosque-rating-count" name="mosque_rating_count">
			<input type="hidden" id="wp-mosque-status" name="mosque_status">
			<input type="hidden" id="wp-mosque-introduction" name="mosque_introduction">
			<input type="hidden" id="wp-mosque-opening-hours" name="mosque_opening_hours">

			<div id="mosque-submit-container" style="display: none; margin-top: 20px;">
				<button type="submit" name="submit_mosque" class="button button-primary" style="padding: 12px 30px; background: #0073aa; color: #fff; border: none; border-radius: 4px; font-weight: bold; cursor: pointer; font-size:1em; width:100%;">Add Mosque to Directory</button>
			</div>
		</form>
	</div>
	<?php
	return ob_get_clean();
}


// ============================================
// 1. BACKEND FORM PROCESSING ENGINE – MOSQUE
// ============================================
add_action('wp_loaded', 'directory_process_mosque_submission');

function directory_process_mosque_submission() {
    global $wpdb, $directory_form_feedback;
    
    if ( isset($_POST['submit_mosque']) && is_user_logged_in() ) {
        
        $place_id        = sanitize_text_field($_POST['mosque_place_id']);
        $name            = sanitize_text_field($_POST['mosque_name']);
        $latitude        = sanitize_text_field($_POST['mosque_lat']);
        $longitude       = sanitize_text_field($_POST['mosque_lng']);
        $type            = sanitize_text_field($_POST['mosque_primary_type']);
        $types           = $_POST['mosque_all_types']; 
        $address         = sanitize_text_field($_POST['mosque_address']);
        $city            = sanitize_text_field($_POST['mosque_city']);
        $country         = sanitize_text_field($_POST['mosque_country']);
        $phone           = sanitize_text_field($_POST['mosque_phone']);
        $website         = esc_url_raw($_POST['mosque_website']); 
        $rating          = sanitize_text_field($_POST['mosque_rating']);
        $rating_count    = sanitize_text_field($_POST['mosque_rating_count']);
        $mosque_status = sanitize_text_field($_POST['mosque_status']);
        $opening_hours   = $_POST['mosque_opening_hours']; 

        if ( empty($place_id) ) {
            return;
        }

        $table_name = $wpdb->prefix . 'jet_cct_mosque'; 

        // Check if place_id already exists
        $existing_mosque = $wpdb->get_row(
            $wpdb->prepare("SELECT _ID, page_url FROM `$table_name` WHERE place_id = %s", $place_id)
        );

        if ( $existing_mosque ) {
            // UPDATE EXISTING
            $cct_id = $existing_mosque->_ID;
            
            $wpdb->update(
                $table_name,
                array(
                    'name'            => $name,
                    'latitude'        => $latitude,
                    'longitude'       => $longitude,
                    'type'            => $type,
                    'types'           => $types,
                    'address'         => $address,
                    'city'            => $city,
                    'country'         => $country,
                    'phone'           => $phone,
                    'website'         => $website,
                    'rating'          => $rating,
                    'rating_count'    => $rating_count,
                    'mosque_status' => $mosque_status,
                    'opening_hours'   => $opening_hours
                ),
                array('_ID' => $cct_id),
                array('%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s'),
                array('%d')
            );

            $mosque_url = !empty($existing_mosque->page_url) ? $existing_mosque->page_url : '#';
            $directory_form_feedback = '<div style="background-color: #e2f0d9; color: #385723; padding: 15px; border-left: 5px solid #a9d18e; margin-bottom: 20px; border-radius:4px;">';
            $directory_form_feedback .= '<strong>Listing Updated!</strong><br>';
            $directory_form_feedback .= '<a href="' . esc_url($mosque_url) . '" style="font-weight:bold; text-decoration:underline;">View Business Page</a>';
            $directory_form_feedback .= '</div>';

        } else {
            // INSERT NEW
            $inserted = $wpdb->insert(
                $table_name,
                array(
                    'place_id'        => $place_id,
                    'name'            => $name,
                    'latitude'        => $latitude,
                    'longitude'       => $longitude,
                    'type'            => $type,
                    'types'           => $types,
                    'address'         => $address,
                    'city'            => $city,
                    'country'         => $country,
                    'phone'           => $phone,
                    'website'         => $website,
                    'rating'          => $rating,
                    'rating_count'    => $rating_count,
                    'business_status' => $mosque_status,
                   'opening_hours'   => $opening_hours
                ),
                array('%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s')
            );

            if ( $inserted ) {
                $new_cct_id = $wpdb->insert_id;

                // Create CPT entry
                $post_args = array(
                    'post_title'   => $name,
                    'post_status'  => 'publish',
                    'post_type'    => 'masjid',
                    'meta_input'   => array(
                        'item_id'  => $new_cct_id
                    )
                );
                
                $new_post_id = wp_insert_post($post_args);
                $post_full_url = '#';

                if ( ! is_wp_error($new_post_id) && $new_post_id > 0 ) {
                    $post_full_url = get_permalink($new_post_id);

                    $wpdb->update(
                        $table_name,
                        array(
                            'cct_single_post_id' => $new_post_id,
                            'page_url'           => $post_full_url
                        ),
                        array('_ID' => $new_cct_id),
                        array('%d', '%s'),
                        array('%d')
                    );
                }

                $directory_form_feedback = '<div style="background-color: #d4edda; color: #155724; padding: 15px; border-left: 5px solid #c3e6cb; margin-bottom: 20px; border-radius:4px;">';
                $directory_form_feedback .= '<strong>Business Page Created – ' . esc_html($name) . '</strong><br>';
                $directory_form_feedback .= '<a href="' . esc_url($post_full_url) . '" style="font-weight:bold; text-decoration:underline;">View Business Page</a>';
                $directory_form_feedback .= '</div>';
            }
        }
    }
}




// ============================================
// 2. LIVE ASYNCHRONOUS CHECK ENDPOINT (AJAX HANDLERS)
//    (Works for both mosquees and mosques)
// ============================================
add_action('wp_ajax_directory_check_existing_mosque', 'directory_ajax_check_existing_mosque');
add_action('wp_ajax_nopriv_directory_check_existing_mosque', 'directory_ajax_check_existing_mosque');

function directory_ajax_check_existing_mosque() {
    global $wpdb;
    
    $place_id = isset($_POST['place_id']) ? sanitize_text_field($_POST['place_id']) : '';
    
    if ( empty($place_id) ) {
        wp_send_json_error(array('message' => 'Missing Google Place ID'));
    }
    
    $table_name = $wpdb->prefix . 'jet_cct_mosque';
    
    $existing = $wpdb->get_row(
        $wpdb->prepare("SELECT page_url FROM `$table_name` WHERE place_id = %s LIMIT 1", $place_id)
    );
    
    if ( $existing ) {
        wp_send_json_success(array(
            'exists'   => true,
            'page_url' => !empty($existing->page_url) ? esc_url($existing->page_url) : '#'
        ));
    } else {
        wp_send_json_success(array('exists' => false));
    }
}