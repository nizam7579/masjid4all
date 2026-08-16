<?php

/*
add_action('plugins_loaded', function () {
    add_shortcode('country', 'country_shortcode');
}); 
 
function country_shortcode() {
    
    global $wpdb;
    
    $results = $wpdb->get_results("
        SELECT country, COUNT(*) as mosque_count 
        FROM wp_jet_cct_mosque
        GROUP BY country
    ");
    
    if (!$results) {
        return "No data found.";
    }

    $output = "<ul>";
    foreach ($results as $row) {
        $output .= "<li>{$row->country} ({$row->mosque_count} mosques)</li>";
    }
    $output .= "</ul>";

    return $output;
    
}


add_shortcode( 'intro_video_player', function( $atts ) {
    $video_url = 'http://masjid4all.com/wp-content/uploads/2025/06/K-Startup-Masjid4ALL-Sejati-Song.mp4';

    ob_start();
    ?>
    <div class="plyr__video-embed" id="video-container" style="max-width:800px; margin:auto;">
        <video id="no-download-player" controls playsinline>
            <source src="<?php echo esc_url( $video_url ); ?>" type="video/mp4" />
        </video>
    </div>
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const player = new Plyr('#no-download-player', {
                controls: ['play', 'progress', 'current-time', 'mute', 'volume', 'fullscreen'],
                disableContextMenu: true
            });
        });
    </script>
    <style>
        #no-download-player {
            border-radius: 12px;
            box-shadow: 0 4px 16px rgba(0, 0, 0, 0.2);
        }
    </style>
    <?php
    return ob_get_clean();
} );


function enqueue_plyr_assets() {
    wp_enqueue_style( 'plyr-css', 'https://cdn.plyr.io/3.7.8/plyr.css' );
    wp_enqueue_script( 'plyr-js', 'https://cdn.plyr.io/3.7.8/plyr.polyfilled.js', [], null, true );
}
add_action( 'wp_enqueue_scripts', 'enqueue_plyr_assets' );

add_action('fluentform/submission_inserted', 'intro_download_video', 20, 3);
function intro_download_video($entryId, $formData, $form) {
    
  if ($form->id != 30) {
      return;
   }
   
   $name = $formData['name'];
   $phone = $formData['phone'];
   $currentPage = $formData['current_page'];
   $entryId = $entryId;
   
   echo "<pre>";
   print_r($formData);
   echo "</pre>";
   
   $post_id = wp_insert_post( [
        'post_title'   => $entryId,
        'post_status'  => 'publish',
        'post_type'    => 'contactus',
    ], true );   
   
   if ($post_id) {
       update_post_meta($post_id, 'name', $name);
       update_post_meta($post_id, 'phone', $phone);
       update_post_meta($post_id, 'remark', $currentPage);
   }
}

add_action( 'fluentform/before_submission_confirmation', 'intro_force_video_download', 10, 3 );
function intro_force_video_download( $entryId, $formData, $form ) {

    if ( $form->id != 30 ) {
        return;
    }

    $video_id = absint( $formData['video_id'] ?? 0 );

    if (!$video_id) {
        return;
    }

    $download_url = add_query_arg(
        [ 'intro_dl' => $video_id ],
        home_url( '/' )
    );
    
    wp_send_json_success( [
        'result' => [
            'redirectUrl' => $download_url,
        ]
    ] );
}

add_action( 'plugins_loaded', function () {

    if ( empty( $_GET['intro_dl'] ) ) {
        return;
    }

    $file_id   = absint( $_GET['intro_dl'] );
    $file_path = get_attached_file( $file_id );

    if ( ! $file_path || ! file_exists( $file_path ) ) {
        wp_die( 'File not found.' );
    }

    // force–download headers
    header( 'Content-Description: File Transfer' );
    header( 'Content-Type: application/octet-stream' );
    header( 'Content-Disposition: attachment; filename="' . basename( $file_path ) . '"' );
    header( 'Content-Transfer-Encoding: binary' );
    header( 'Expires: 0' );
    header( 'Cache-Control: must-revalidate' );
    header( 'Pragma: public' );
    header( 'Content-Length: ' . filesize( $file_path ) );

    // send the file
    readfile( $file_path );
    exit;
}, 0 );

*/

 


