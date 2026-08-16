<?php

// LIST WHATSAPP TRANS
// admin/whatsapp/detail
add_shortcode('admin_member_phone', 'admin_member_phone_shortcode');

function admin_member_phone_shortcode() {
    // Check if PID exists in URL
    if (!isset($_GET['wa'])) {
        return '<div class="alert">No PID parameter found in URL. Add ?pid=123 to filter.</div>';
    }

    $phone = (int)$_GET['wa'];
    return 'PHONE : <b>' . $phone . '</b>';   
    $whatsapp = niz_user_field_by_userid($pid, 'phone');
    $whatsapp = preg_replace('/\D/', '', $whatsapp);
    
    // Query the CCT table directly
    global $wpdb;
    $table = 'wp_jet_cct_whatsapp';
    
    $results = $wpdb->get_results(
        $wpdb->prepare(
            "SELECT sender, user_id, direction, type, media_url, cct_created, message 
             FROM $table 
             WHERE sender = %d 
             ORDER BY cct_created DESC",
            $whatsapp
        )
    );

    if (empty($results)) {
        return "<div class='notice'>No WhatsApp records found</div>";
    }

    // Display results in a table
     
    $ret = "<p><b>Whatsapp No. : " .  $whatsapp . '</b></p>';
    
    foreach ($results as $record) {
        $date = new DateTime($record->cct_created);
        // Format the date as d/m/Y H:i pm/am
        $date = $date->format('d/m g:i a');
        $type = $record->type;
        $direction = $record->direction;
        $message = $record->message;
        $url = esc_url($record->media_url); // Always escape URLs
        //$caption = !empty($record->caption) ? sanitize_text_field($record->caption) : '';
        
        $display .= '<div style="font-size: 14px; color: gray;">' . $date . ' ' . $direction . ' ' . $type . '</div>';
       
        
        if ($type=='text'){
            //$message
        }else if ($type=='image'){
            $img = '<img src="'. $url . '">';
            $message = $message . $img .'<br>';
        }else if ($type=='document'){
            $message = do_shortcode('[video src="' . $url . ' title="Annual Report" height="800px"' . '"]');
        }else if ($type=='video'){
            $message = do_shortcode('[video src="' . $url . '"]');
        }else if ($type=='audio'){
            $message = do_shortcode('[audio src="' . $url . '"]');
        }else{
            $message = $url;   
        }
        
        $display .= '<p>' . $message . '</p>';
    }

    return $ret . $display;
};

// MEMBER - DISPLAY BUSINESS INFO
add_shortcode('admin_member_info', 'admin_member_info_shortcode');

function admin_member_info_shortcode() {
 
    global $wpdb;
 
    $current_user = wp_get_current_user();  
    if ( current_user_can('administrator') || current_user_can('editor')  ) {
        //echo 'You are an admin.';
    }else{
        wp_redirect('/member/');
        exit;
    } 
    
    // UPDATE TYPE
    //member_cct_update($item_id, 'type', 'Business Owner', '%s');
    //$time = current_time('mysql');
    //member_cct_update($item_id, 'last_contact', $time, '%s');
 
    $user_id = $_GET['pid'];
    $item_id = get_user_meta($user_id, 'item_id', true); 

    $name = niz_user_field_by_itemid($item_id, 'name');
    $phone = niz_user_field_by_itemid($item_id, 'phone');
    $email = niz_user_field_by_itemid($item_id, 'email');
    $whatsapp = niz_user_field_by_itemid($item_id, 'whatsapp');
    $country = niz_user_field_by_itemid($item_id, 'country');
    $type = niz_user_field_by_itemid($item_id, 'type');
    $category = niz_user_field_by_itemid($item_id, 'category');
    $last_contact = niz_user_field_by_itemid($item_id, 'last_contact');
     
    $user = get_userdata($user_id);
    $reg_date = $user->user_registered;
    $reg_date = date('d/m/Y', strtotime($reg_date));

    $datetime = new DateTime($last_contact, new DateTimeZone('UTC')); // assuming stored in UTC
    $datetime->setTimezone(new DateTimeZone('Asia/Kuala_Lumpur'));
   
     //$ret.= '<b>'. $item_id . '</b><br>';
    //$ret.= $user_id . '/' . $item_id . '<br>'; 
    $name = strtoupper($name);
    $ret.= '<b>'. $name . '</b><br>';
    $ret.= '📞 ' . $phone . '<br>';
    $ret.= '📧 ' . $email . '<br>';
    $ret.= '🌎 ' . $country . '<br>';
    $ret.= '<a href="' . esc_url( site_url( '/admin/whatsapp/detail/?wa=' . $phone ) ) . '">Whatsapp</a>';

    $ret.= '<br><br><table>';
    $ret.= '<tr><td>UserID/ItemID</td><td>' . $user_id . '/' . $item_id. '</td></tr>';
    $ret.= '<tr><td>Category</td><td>' . $category . '</td></tr>';
    $ret.= '<tr><td>Type</td><td>' . $type . '</td></tr>';
    $ret.= '<tr><td>Registered</td><td>' . $reg_date . '</td></tr>';
    $ret.= '<tr><td>Last Contact</td><td>' . $datetime->format('d/m/Y h:i A') . '</td></tr>';
    $ret.= '</table>';
    
    $ret.= '<b>BUSINESS</b><br>';
    
    // LIST BUSINESS
    $args = array(
        'post_type'      => 'business',
        'posts_per_page' => -1,
        'meta_query'     => array(
            array(
                'key'   => 'owner_id',
                'value' => $user_id,
                'compare' => '='
            )
        )
    );

    // GET LIST OF BUSINESSES FOR THE OWNER
    $table = $wpdb->prefix . 'jet_cct_business'; // JetEngine CCT business table
    $results = $wpdb->get_results(
        $wpdb->prepare(
            "SELECT * FROM $table WHERE owner_id = %d",
            $user_id
        ),
        ARRAY_A
    );
     
    if ( ! empty( $results ) ) {
        foreach ( $results as $business ) {
            // Assuming your CCT has fields `title` and `post_id` (adjust to your fields)
            $title = ! empty( $business['name'] ) ? $business['name'] : 'Untitled';
            //$link  = ! empty( $business['post_id'] ) ? get_permalink( $business['post_id'] ) : '#';
            $link = "/admin/business/info/?pid=" . $business['_ID'];
            $ret .= '<li><a href="' . esc_url( $link ) . '" target="">' . esc_html( $title ) . '</a></li>';
        }
    
        member_cct_update($item_id, 'type', 'Business Owner', '%s');
    } else {
        $ret .= 'None';
        member_cct_update($item_id, 'type', '', '%s');
    }
    
    return $ret;
} 

add_shortcode('admin_member_wa', 'admin_member_wa_shortcode');

function admin_member_wa_shortcode() {
    // Check if PID exists in URL
    if (!isset($_GET['pid'])) {
        return '<div class="alert">No PID parameter found in URL. Add ?pid=123 to filter.</div>';
    }

    $pid = (int)$_GET['pid'];
    // get whatsapp number
    
    $whatsapp = niz_user_field_by_userid($pid, 'phone');
    $whatsapp = preg_replace('/\D/', '', $whatsapp);
    
    // Query the CCT table directly
    global $wpdb;
    $table = 'wp_jet_cct_whatsapp';
    
    $results = $wpdb->get_results(
        $wpdb->prepare(
            "SELECT sender, user_id, direction, type, media_url, cct_created, message 
             FROM $table 
             WHERE sender = %d 
             ORDER BY cct_created DESC",
            $whatsapp
        )
    );

    if (empty($results)) {
        return "<div class='notice'>No WhatsApp records found</div>";
    }

    // Display results in a table
     
    $ret = "<p><b>Whatsapp No. : " .  $whatsapp . '</b></p>';
    
    foreach ($results as $record) {
        $date = new DateTime($record->cct_created);
        // Format the date as d/m/Y H:i pm/am
        $date = $date->format('d/m g:i a');
        $type = $record->type;
        $direction = $record->direction;
        $message = $record->message;
        $url = esc_url($record->media_url); // Always escape URLs
        //$caption = !empty($record->caption) ? sanitize_text_field($record->caption) : '';
        
        $display .= '<div style="font-size: 14px; color: gray;">' . $date . ' ' . $direction . ' ' . $type . '</div>';
       
        
        if ($type=='text'){
            //$message
        }else if ($type=='image'){
            $img = '<img src="'. $url . '">';
            $message = $message . $img .'<br>';
        }else if ($type=='document'){
            $message = do_shortcode('[video src="' . $url . ' title="Annual Report" height="800px"' . '"]');
        }else if ($type=='video'){
            $message = do_shortcode('[video src="' . $url . '"]');
        }else if ($type=='audio'){
            $message = do_shortcode('[audio src="' . $url . '"]');
        }else{
            $message = $url;   
        }
        
        $display .= '<p>' . $message . '</p>';
    }

    return $ret . $display;
};

add_shortcode('document', function($atts) {
    $atts = shortcode_atts([
        'url' => '',
        'title' => 'Download Document',
        'type' => '', // pdf/docx/ppt etc
        'height' => '600px'
    ], $atts);

    if(empty($atts['url'])) return '';

    $file_icon = '';
    $file_type = $atts['type'] ?: pathinfo($atts['url'], PATHINFO_EXTENSION);
    
    // Set icons based on file type
    switch(strtolower($file_type)) {
        case 'pdf':
            $file_icon = '📄';
            break;
        case 'doc':
        case 'docx':
            $file_icon = '📝';
            break;
        case 'xls':
        case 'xlsx':
            $file_icon = '📊';
            break;
        default:
            $file_icon = '📎';
    }

    //return 'XXX ' . $file_icon;
    // For PDF embedding
    //if(strtolower($file_type) === 'pdf') {
    //    return '<div class="document-embed" style="height: '.esc_attr($atts['height']).'">
    //            <embed src="'.esc_url($atts['url']).'" type="application/pdf" width="100%" height="100%">
    //            <p class="document-link">'.$file_icon.' <a href="'.esc_url($atts['url']).'" target="_blank">'.esc_html($atts['title']).'</a></p>
    //        </div>';
    ////}

    // For other document types
    return '<p class="document-link">'.$file_icon.' <a href="'.esc_url($atts['url']).'" target="_blank">'.esc_html($atts['title']).'</a></p>';
});

////////////////////////////////////////////////////
// MEMBER PIE CHART WITH TITLE & PERCENTAGE       //
////////////////////////////////////////////////////
add_shortcode('member_pie_chart', 'member_continent_pie_chart_final_shortcode');

function member_continent_pie_chart_final_shortcode() {
    global $wpdb;

    $quarter = isset($_GET['quarter']) ? sanitize_text_field($_GET['quarter']) : 'all';
    $date_query = ""; 

    switch ($quarter) {
        case 'q1':
            $date_query = " AND YEAR(cct_created) = 2026 AND MONTH(cct_created) BETWEEN 1 AND 3 ";
            break;
        case 'q2':
            $date_query = " AND YEAR(cct_created) = 2026 AND MONTH(cct_created) BETWEEN 4 AND 6 ";
            break;
        case 'q3':
            $date_query = " AND YEAR(cct_created) = 2026 AND MONTH(cct_created) BETWEEN 7 AND 9 ";
            break;
        case 'q4':
            $date_query = " AND YEAR(cct_created) = 2026 AND MONTH(cct_created) BETWEEN 10 AND 12 ";
            break;
        case 'all':
        default:
            $date_query = ""; 
            break;
    }

    // 1. Fetch member data
    $results = $wpdb->get_results("
        SELECT continent, COUNT(*) AS count 
        FROM {$wpdb->prefix}jet_cct_member 
        WHERE continent IS NOT NULL AND continent <> '' 
        " . $date_query . "
        GROUP BY continent
    ");

    if (!$results) return "No member data available for chart.";

    $labels = [];
    $counts = [];
    foreach ($results as $row) {
        $labels[] = $row->continent;
        $counts[] = (int)$row->count;
    }

    $chart_id = 'memPieChartTitle_' . uniqid();

    ob_start();
    ?>
    <div class="m4a-chart-container" style="width: 100%; max-width: 550px; margin: 40px auto; padding: 20px; background: #fff; border-radius: 12px; box-shadow: 0 10px 25px rgba(0,0,0,0.05); font-family: 'Segoe UI', Roboto, Helvetica, Arial, sans-serif;">
        <canvas id="<?php echo $chart_id; ?>"></canvas>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chartjs-plugin-datalabels@2"></script>

    <script>
    document.addEventListener("DOMContentLoaded", function() {
        const ctx = document.getElementById('<?php echo $chart_id; ?>').getContext('2d');
        
        // Check if ChartDataLabels is registered
        if (typeof ChartDataLabels !== 'undefined') {
            Chart.register(ChartDataLabels);
        }

        new Chart(ctx, {
            type: 'pie',
            data: {
                labels: <?php echo json_encode($labels); ?>,
                datasets: [{
                    data: <?php echo json_encode($counts); ?>,
                    backgroundColor: [
                        '#36A2EB', '#FF6384', '#FFCE56', '#4BC0C0', '#9966FF', '#FF9F40', '#C9CBCF', '#2ecc71'
                    ],
                    borderWidth: 2,
                    borderColor: '#ffffff'
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: true,
                plugins: {
                    title: {
                        display: true,
                        text: 'Member Distribution Across Continents',
                        color: '#2c3e50',
                        font: {
                            size: 20,
                            weight: 'bold',
                            family: 'Helvetica Neue'
                        },
                        padding: { top: 10, bottom: 30 }
                    },
                    legend: {
                        position: 'bottom',
                        labels: { boxWidth: 15, padding: 20, font: { size: 13 } }
                    },
                    tooltip: {
                        backgroundColor: 'rgba(0, 0, 0, 0.8)',
                        callbacks: {
                            label: function(context) {
                                let value = context.parsed;
                                let total = context.dataset.data.reduce((a, b) => a + b, 0);
                                let percentage = ((value / total) * 100).toFixed(1) + "%";
                                return ` ${context.label}: ${value} Members (${percentage})`;
                            }
                        }
                    },
                    datalabels: {
                        color: '#fff',
                        font: { weight: 'bold', size: 14 },
                        formatter: (value, ctx) => {
                            let sum = ctx.chart.data.datasets[0].data.reduce((a, b) => a + b, 0);
                            return ((value * 100) / sum).toFixed(1) + "%";
                        }
                    }
                }
            }
        });
    });
    </script>
    <?php
    return ob_get_clean();
}  
  
