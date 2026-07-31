<?php

add_shortcode('member_category_summary', 'm4a_member_category_summary_shortcode');
function m4a_member_category_summary_shortcode() {
    global $wpdb;

    $table = $wpdb->prefix . 'jet_cct_member';

    $results = $wpdb->get_results("
        SELECT
            CASE
                WHEN status IS NULL OR TRIM(status) = '' OR TRIM(status) = 'New User'
                THEN 'New User'
                ELSE status
            END AS status,
            COUNT(*) AS total
        FROM {$table}
        GROUP BY
            CASE
                WHEN status IS NULL OR TRIM(status) = '' OR TRIM(status) = 'New User'
                THEN 'New User'
                ELSE status
            END
        ORDER BY total DESC
    ");

    if (empty($results)) {
        return '<p>No members found.</p>';
    }

    $output = '<h3>Members</h3>'; 
    $output .= '<table class="m4a-summary">';

    $tot = 0;
    
    foreach ($results as $row) {
        $output .= sprintf(
            '<tr><td>%s</td><td style="text-align: right;">%s</td></tr>',
            esc_html($row->status),
            number_format($row->total)
        );
         $tot =  $tot + (int)$row->total;
    }
    
    $output .= '<tr><td><b>TOTAL</b></td><td style="text-align: right;"><b>' . number_format($tot) . '</b></td></tr>';
    $output .= '</tbody></table>';

    return $output;
}


add_shortcode('mosque_category_summary', 'm4a_mosque_category_summary_shortcode');
function m4a_mosque_category_summary_shortcode() {
    global $wpdb;

    $table = $wpdb->prefix . 'jet_cct_mosque';

    $results = $wpdb->get_results("
        SELECT
            CASE
                WHEN listing_status IS NULL OR TRIM(listing_status) = '' OR TRIM(listing_status) = 'New'
                THEN 'New'
                ELSE listing_status
            END AS listing_status,
            COUNT(*) AS total
        FROM {$table}
        GROUP BY
            CASE
                WHEN listing_status IS NULL OR TRIM(listing_status) = '' OR TRIM(listing_status) = 'New'
                THEN 'New User'
                ELSE listing_status
            END
        ORDER BY total DESC
    ");

    if (empty($results)) {
        return '<p>No members found.</p>';
    }

    $output = '<h3>Mosque</h3>'; 
    $output .= '<table class="m4a-summary">';

    $tot = 0;
    
    foreach ($results as $row) {
        $output .= sprintf(
            '<tr><td>%s</td><td style="text-align: right;">%s</td></tr>',
            esc_html($row->listing_status),
            number_format($row->total)
        );
         $tot =  $tot + (int)$row->total;
    }
    
    $output .= '<tr><td><b>TOTAL</b></td><td style="text-align: right;"><b>' . number_format($tot) . '</b></td></tr>';
    $output .= '</tbody></table>';

    return $output;
}


add_shortcode('business_category_summary', 'm4a_business_category_summary_shortcode');
function m4a_business_category_summary_shortcode() {
    global $wpdb;

    $table = $wpdb->prefix . 'jet_cct_business';

    $results = $wpdb->get_results("
        SELECT
            CASE
                WHEN listing_status IS NULL OR TRIM(listing_status) = ''  OR TRIM(listing_status) = 'New'
                THEN 'New'
                ELSE listing_status
            END AS listing_status,
            COUNT(*) AS total
        FROM {$table}
        GROUP BY
            CASE
                WHEN listing_status IS NULL OR TRIM(listing_status) = '' OR TRIM(listing_status) = 'New'
                THEN 'New User'
                ELSE listing_status
            END
        ORDER BY total DESC
    ");

    if (empty($results)) {
        return '<p>No members found.</p>';
    }

    $output = '<h3>Business</h3>'; 
    $output .= '<table class="m4a-summary">';

    $tot = 0;
    
    foreach ($results as $row) {
        $output .= sprintf(
            '<tr><td>%s</td><td style="text-align: right;">%s</td></tr>',
            esc_html($row->listing_status),
            number_format($row->total)
        );
         $tot =  $tot + (int)$row->total;
    }
    
    $output .= '<tr><td><b>TOTAL</b></td><td style="text-align: right;"><b>' . number_format($tot) . '</b></td></tr>';
    $output .= '</tbody></table>';

    return $output;
}

add_shortcode('website_category_summary', 'm4a_website_category_summary_shortcode');
function m4a_website_category_summary_shortcode() {
    global $wpdb;

    $table = $wpdb->prefix . 'jet_cct_web';

    $results = $wpdb->get_results("
        SELECT
            CASE
                WHEN listing_status IS NULL OR TRIM(listing_status) = ''  OR TRIM(listing_status) = 'New'
                THEN 'New'
                ELSE listing_status
            END AS listing_status,
            COUNT(*) AS total
        FROM {$table}
        GROUP BY
            CASE
                WHEN listing_status IS NULL OR TRIM(listing_status) = ''  OR TRIM(listing_status) = 'New'
                THEN 'New User'
                ELSE listing_status
            END
        ORDER BY total DESC
    ");

    if (empty($results)) {
        return '<p>No members found.</p>';
    }

    $output = '<h3>Website</h3>'; 
    $output .= '<table class="m4a-summary">';

    $tot = 0;
    
    foreach ($results as $row) {
        $output .= sprintf(
            '<tr><td>%s</td><td style="text-align: right;">%s</td></tr>',
            esc_html($row->listing_status),
            number_format($row->total)
        );
         $tot =  $tot + (int)$row->total;
    }
    
    $output .= '<tr><td><b>TOTAL</b></td><td style="text-align: right;"><b>' . number_format($tot) . '</b></td></tr>';
    $output .= '</tbody></table>';

    return $output;
}

