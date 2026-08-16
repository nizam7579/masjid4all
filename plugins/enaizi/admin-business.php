<?php

//ADMIN - DISPLAY BUSINESS INFO
add_shortcode('admin_business_info', 'admin_business_info_shortcode');
 
function admin_business_info_shortcode() {
    $item_id = isset($_GET['pid']) ? sanitize_text_field($_GET['pid']) : '';
    if ( current_user_can('administrator') || current_user_can('editor')  ) {
        //echo 'You are an admin.';
    }else{
        wp_redirect('/member/');
        exit;
    } 
    
    if (!empty($item_id)) {
        // Redirect safely to member page if no pid provided
        //echo 'XXX NO PID';
        //wp_safe_redirect(home_url('/member'));
        //exit;
     
        $post_id = get_cct_business_data($item_id, 'post_id');
    
        $name = get_cct_business_data($item_id, 'name');
        $tags = get_cct_business_data($item_id, 'tags');
        $email = get_cct_business_data($item_id, 'email');
        $website = get_cct_business_data($item_id, 'website');
        $phone = get_cct_business_data($item_id, 'phone');
        $whatsapp = get_cct_business_data($item_id, 'whatsapp');
        $address = get_cct_business_data($item_id, 'address');
        $country = get_cct_business_data($item_id, 'country');
        $page_url = get_cct_business_data($item_id, 'page_url');
        $page_url = '<a href="' . $page_url . '" target="_blank" rel="noopener noreferrer">Visit Webpage</a>';
    
        $business_status = get_cct_business_data($item_id, 'business_status');
        
        $owner_id = get_cct_business_data($item_id, 'owner_id');
        $user_info = get_userdata($owner_id);
        if ($user_info) {
            if ($user_info) {
                $url = '/admin/member/update/?pid=' . $owner_id;
                $owner = get_user_meta($owner_id, 'first_name', true);
                $owner = '<a href="' . $url . '" target="" rel="noopener noreferrer">' . $owner . '</a>';
            }
        }
     
        if ($country=='Malaysia' AND $whatsapp==''){
            $clean = preg_replace('/[^0-9]/', '', $phone);
            // Convert international format +60 -> 0
            if (strpos($clean, '60') === 0) {
                $clean = '0' . substr($clean, 2);
            }
            // Check if it starts with 01
            if (strpos($clean, '01') === 0) {
                $whatsapp = $phone;
                // UPDATE CCT BUSINESS
                $data = [
                    'whatsapp' => $whatsapp
                ];
                //$ret.= 'WA ' . $whatsapp . '<br>';
                update_cct_business($post_id, $data);
            }   
        }elseif ($country=='United Kingdom' AND $whatsapp==''){
            $clean = preg_replace('/[^0-9]/', '', $phone);
            // Check if it starts with 447
            if (strpos($clean, '447') === 0) {
                $whatsapp = $phone;
                // UPDATE CCT BUSINESS
                $data = [
                    'whatsapp' => $whatsapp
                ];
                //$ret.= 'WA ' . $whatsapp . '<br>';
                update_cct_business($post_id, $data);
            }   
            
        }
     
        $ret.= '<b>'. $name . '</b><br>';
        $ret.= ''. $address . '<br>';
        $ret.= '<b>'. $country . '</b><br>';
        $ret.= 'Tags : <b>'. $tags . '</b><br>';
        $ret.= $page_url . '<br><br>';
        
        $ret.= '<table>';
        $ret.= '<tr><td style="width:100px;">PostID</td><td>' . $post_id . '/' . $item_id . '</td></tr>';
        $ret.= '<tr><td>Email</td><td>' . $email . '</td></tr>';
        $ret.= '<tr><td>Website</td><td>' . $website . '</td></tr>';
        $ret.= '<tr><td>Phone</td><td>' . $phone . '</td></tr>';
        $ret.= '<tr><td>Whatsapp</td><td>' . $whatsapp . '</td></tr>';
        $ret.= '<tr><td>Status</td><td>' . $business_status . '</td></tr>';
        $ret.= '<tr><td>Owner</td><td>' . $owner . '</td></tr>';
    
        $ret.= '</table>';
        
        
        return $ret;
    }
}

add_shortcode('admin_business_wa', 'admin_business_wa_shortcode');

function admin_business_wa_shortcode() {
    // Check if PID exists in URL
    if (!isset($_GET['pid'])) {
        return '<div class="alert">No PID parameter found in URL. Add ?pid=123 to filter.</div>';
    }

    $pid = (int)$_GET['pid'];
    // get whatsapp number
    $whatsapp = get_cct_business_data($pid, 'whatsapp');
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

// ADMIN/BUSINESS - DISPLAY BUSINESS CONTENT
add_shortcode('admin_business_content', 'admin_business_content_shortcode');
 
function admin_business_content_shortcode() {
    $item_id = isset($_GET['pid']) ? sanitize_text_field($_GET['pid']) : '';
    if (empty(!$item_id)) {
        $post_id = get_cct_business_data($item_id, 'post_id');
        $content = get_post_field( 'post_content', $post_id );
     
        // Apply WordPress formatting (shortcodes, embeds, paragraphs, etc.)
        $display = apply_filters( 'the_content', $content );
        return $display;
    }    
}


// UPDATE CONTENT (/admin/business/info)
add_action('fluentform/before_insert_submission', function ($insertData, $data, $form) {

    if ((int) $form->id !== 42) {
        return;
    }

    $content = \FluentForm\Framework\Helpers\ArrayHelper::get($data, 'bcontent');
    $item_id = sanitize_text_field(\FluentForm\Framework\Helpers\ArrayHelper::get($data, 'pid'));
    $post_id = get_cct_business_data($item_id, 'post_id');

    // UPDATE POST
    $post_data = array(
        'ID'           => $post_id,
        'post_content' => $content,
    );
    wp_update_post($post_data);
    
}, 10, 3);

////////////////////////////////////////////////////
// BUSINESS PIE CHART WITH TITLE & PERCENTAGE     //
////////////////////////////////////////////////////
add_shortcode('business_pie_chart', 'business_continent_pie_chart_final_shortcode');

function business_continent_pie_chart_final_shortcode() {
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

    // 1. Ambil data
    $results = $wpdb->get_results("
        SELECT continent, COUNT(*) AS count 
        FROM {$wpdb->prefix}jet_cct_business 
        WHERE continent IS NOT NULL AND continent <> ''
        " . $date_query . "        
        GROUP BY continent
    ");

    if (!$results) return "No data available for chart.";

    $labels = [];
    $counts = [];
    foreach ($results as $row) {
        $labels[] = $row->continent;
        $counts[] = (int)$row->count;
    }

    $chart_id = 'bizPieChartTitle_' . uniqid();

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
        Chart.register(ChartDataLabels);

        new Chart(ctx, {
            type: 'pie',
            data: {
                labels: <?php echo json_encode($labels); ?>,
                datasets: [{
                    data: <?php echo json_encode($counts); ?>,
                    backgroundColor: [
                        '#FF6384', '#36A2EB', '#FFCE56', '#4BC0C0', '#9966FF', '#FF9F40', '#C9CBCF', '#2ecc71'
                    ],
                    borderWidth: 2,
                    borderColor: '#ffffff'
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: true,
                plugins: {
                    // --- KONFIGURASI TAJUK (TITLE) ---
                    title: {
                        display: true,
                        text: 'Business Distribution Across Continents',
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
                                return ` ${context.label}: ${value} Vendors (${percentage})`;
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