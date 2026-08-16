<?php
//created by Mohd Hafizi Mohammad Nor

// ADMIN/mosque - DISPLAY mosque CONTENT
add_shortcode('admin_mosque_content', 'admin_mosque_content_shortcode');
 
function admin_mosque_content_shortcode() {
    $item_id = isset($_GET['id']) ? sanitize_text_field($_GET['id']) : '';
    if (empty(!$item_id)) {
        $post_id = get_cct_mosque_data($item_id, 'post_id');
        $content = get_post_field( 'post_content', $post_id );
     
        // Apply WordPress formatting (shortcodes, embeds, paragraphs, etc.)
        $display = apply_filters( 'the_content', $content );
        return $display;
    }    
}

// UPDATE CONTENT (/admin/mosque/info)
add_action('fluentform/before_insert_submission', function ($insertData, $data, $form) {

    if ((int) $form->id !== 60) {
        return;
    }

    $content = \FluentForm\Framework\Helpers\ArrayHelper::get($data, 'mcontent');
    $item_id = sanitize_text_field(\FluentForm\Framework\Helpers\ArrayHelper::get($data, 'id'));
    $post_id = get_cct_mosque_data($item_id, 'post_id');

    // UPDATE POST
    $post_data = array(
        'ID'           => $post_id,
        'post_content' => $content,
    );
    wp_update_post($post_data);
    
}, 10, 3);

/**
 * SHORTCODE: Mosque Distribution by Continent Pie Chart
 * --------------------------------------------------------------------------
 * Fetches data from wp_jet_cct_mosque and renders a Chart.js Pie Chart.
 * --------------------------------------------------------------------------
 */
add_shortcode('mosque_pie_chart', 'mosque_continent_pie_chart_shortcode');

function mosque_continent_pie_chart_shortcode() {
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

    // 1. Data Acquisition from Mosque CCT
    $mosque_table = $wpdb->prefix . 'jet_cct_mosque';
    $results = $wpdb->get_results("
        SELECT continent, COUNT(*) AS count 
        FROM $mosque_table 
        WHERE continent IS NOT NULL AND continent <> ''
        " . $date_query . "        
        GROUP BY continent
    ");

    if (!$results) return "No mosque data available for chart.";

    $labels = [];
    $counts = [];
    foreach ($results as $row) {
        $labels[] = $row->continent;
        $counts[] = (int)$row->count;
    }

    $chart_id = 'mosquePieChart_' . uniqid();

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
        
        // Register the datalabels plugin
        Chart.register(ChartDataLabels);

        new Chart(ctx, {
            type: 'pie',
            data: {
                labels: <?php echo json_encode($labels); ?>,
                datasets: [{
                    data: <?php echo json_encode($counts); ?>,
                    backgroundColor: [
                        '#125C59', '#2ecc71', '#3498db', '#9b59b6', '#f1c40f', '#e67e22', '#e74c3c', '#95a5a6'
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
                        text: 'Mosque Distribution Across Continents',
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
                                return ` ${context.label}: ${value} Mosques (${percentage})`;
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
