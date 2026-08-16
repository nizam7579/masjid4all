<?php

/**
 * FILTER: Customize Fluent Forms Email Body
 * --------------------------------------------------------------------------
 * This function injects a dynamic CCT (Custom Content Type) ID into the 
 * notification email body. It retrieves the 'item_id' from the metadata 
 * of the post where the form was originally embedded.
 * --------------------------------------------------------------------------
 */
add_filter('fluentform/email_body', function($emailBody, $notification, $submittedData, $form) {
    
    /**
     * 1. TARGETING: Define specific Form IDs for processing
     * ID 58: Mosque Update Form | ID 59: Business Update Form
     */
    $target_form_ids = array(58, 59); 

    if (!in_array($form->id, $target_form_ids)) {
        return $emailBody;
    }

    /**
     * 2. DATA ACQUISITION: Identify the Source Post ID
     * Fluent Forms automatically captures the Page/Post ID where the form 
     * is hosted via the '__fluent_form_embded_post_id' key.
     */
    $post_id = isset($submittedData['__fluent_form_embded_post_id']) ? intval($submittedData['__fluent_form_embded_post_id']) : 0;
    
    $item_id = 'N/A'; // Default fallback value

    if ($post_id) {
        /**
         * 3. META RETRIEVAL: Fetch the 'item_id' from the Post Meta
         * This ID serves as the unique reference key for the JetEngine CCT record.
         */
        $fetched_id = get_post_meta($post_id, 'item_id', true);
        
        if ($fetched_id) {
            $item_id = $fetched_id;
        } else {
            /**
             * LOGIC FALLBACK: Attempt to find 'id' meta if 'item_id' is null
             * depending on the specific CCT-to-Post mapping structure.
             */
            $item_id = get_post_meta($post_id, 'id', true) ?: 'ID_NOT_FOUND';
        }
    }

    /**
     * 4. STRING TRANSFORMATION: Placeholder Replacement
     * Replaces the static string 'custom_cct_id' within the Fluent Forms 
     * Email Editor with the actual dynamic ID.
     */
    $emailBody = str_replace(
        'custom_cct_id', 
        $item_id, 
        $emailBody
    );

    return $emailBody;

}, 10, 4);

// 1. Reset tetapan setiap kali halaman dimuatkan
add_action('wp', function() {
    global $m4a_current_index, $m4a_ads_shown, $m4a_ad_positions;
    $m4a_current_index = 0;
    $m4a_ads_shown = 0;
    $m4a_ad_positions = array();
});

// 2. Shortcode Pintar (Versi JetEngine Master Override)
add_shortcode('papar_iklan_pewarisan', function() {
    global $m4a_current_index, $m4a_ads_shown, $m4a_ad_positions;
    
    if (!isset($m4a_current_index)) {
        $m4a_current_index = 0;
        $m4a_ads_shown = 0;
        $m4a_ad_positions = array();
    }
    
    // Generate 4 posisi rawak
    if (empty($m4a_ad_positions)) {
        $pos1 = rand(2, 4);                     
        $pos2 = rand($pos1 + 2, $pos1 + 4);     
        $pos3 = rand($pos2 + 2, $pos2 + 4);     
        $pos4 = rand($pos3 + 2, $pos3 + 4);     
        $m4a_ad_positions = array($pos1, $pos2, $pos3, $pos4);
    }
    
    $m4a_current_index++;

    // Jika sudah keluar 4 iklan, matikan fungsi
    if ($m4a_ads_shown >= 4) {
        return '';
    }

    if (in_array($m4a_current_index, $m4a_ad_positions)) {
        
        $m4a_ads_shown++;

        $ad_folder_path = WP_PLUGIN_DIR . '/enaizi/iklan/';
        $ad_folder_url  = plugins_url('/enaizi/iklan/');
        $ad_files = array();

        if (is_dir($ad_folder_path)) {
            $files = scandir($ad_folder_path);
            foreach ($files as $file) {
                $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
                if (in_array($ext, array('jpg', 'jpeg', 'png', 'webp'))) {
                    $ad_files[] = $file;
                }
            }
        }

        if (!empty($ad_files)) {
            $random_ad_file = $ad_files[array_rand($ad_files)];
            $image_url = $ad_folder_url . $random_ad_file;
            
            // --- LOGIC PAUTAN DINAMIK ---
            $filename_no_ext = pathinfo($random_ad_file, PATHINFO_FILENAME);
            $base_name = preg_replace('/[0-9]+$/', '', $filename_no_ext);
            $slug = str_replace('_', '/', $base_name);
            $slug = trim($slug, '/');
            
            $target_link = empty($slug) ? 'https://pewarisan.my/' : 'https://pewarisan.my/' . $slug . '/';
            // -----------------------------

// Hasilkan ID Unik untuk setiap iklan yang keluar
            $ad_unique_id = 'm4a_ad_' . wp_generate_password(8, false);

            // KEMASKINI HTML: Tukar object-fit:cover kepada object-fit:contain
            // Dan tambah display:flex untuk memastikan iklan sentiasa 'center'
            $html = '
            <div id="' . $ad_unique_id . '" class="m4a-ad-master-wrapper" style="position:absolute; width:100%; height:100%; top:0; left:0; z-index:999999; background:#ffffff; border-radius:inherit; display:flex; align-items:center; justify-content:center;">
                <a class="m4a-ad-link" href="' . esc_url($target_link) . '" target="_blank" rel="nofollow" style="display:flex; width:100%; height:100%; align-items:center; justify-content:center; text-decoration:none;" onclick="event.stopPropagation();">
                    <img src="' . esc_url($image_url) . '" alt="Iklan Tajaan" style="width:100%; height:100%; object-fit:contain; border-radius:inherit;">
                </a>
            </div>
            
            <script>
            (function() {
                var initAd = function() {
                    var wrapper = document.getElementById("' . $ad_unique_id . '");
                    if (!wrapper) return;

                    // 1. Cari bekas paling luar (JetEngine item)
                    var card = wrapper.closest(".jet-listing-grid__item");

                    if (card) {
                        // 2. Jadikan kad ini sebagai tapak sauh, dan halang isi terkeluar
                        card.style.position = "relative";
                        card.style.overflow = "hidden";

                        // 3. Cari dan GHAIBKAN butang Visit Page atau mana-mana link JetEngine asal
                        var targetElements = card.querySelectorAll("a:not(.m4a-ad-link), .jet-listing-dynamic-link__label, .jet-listing-dynamic-link");
                        targetElements.forEach(function(el) {
                            el.style.setProperty("display", "none", "important");
                            el.style.opacity = "0";
                            el.style.pointerEvents = "none";
                        });

                        // 4. Pindahkan kotak iklan memeluk terus jet-listing-grid__item
                        card.appendChild(wrapper);
                    }
                };

                if (document.readyState === "loading") {
                    document.addEventListener("DOMContentLoaded", initAd);
                } else {
                    initAd();
                }
                
                setTimeout(initAd, 500); 
            })();
            </script>';
            
            return $html;
        }
    }

    return '';
});