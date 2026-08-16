<?php

//////////////////////////////////
// MOSQUE SUMMARY (HARD-CODED)  //
//////////////////////////////////
add_action('plugins_loaded', function () {
    add_shortcode('mosque_summary_country_hc', 'mosque_summary_country_hc_shortcode');
});

function mosque_summary_country_hc_shortcode() {
    // Data Statik berdasarkan input pengguna
    $mosque_data = [
        "Indonesia" => 25372, "India" => 15206, "Malaysia" => 8692, "Bangladesh" => 4442,
        "United States" => 3223, "Iran" => 2738, "Pakistan" => 2425, "Afghanistan" => 2238,
        "Saudi Arabia" => 2099, "Nigeria" => 1971, "Türkiye" => 1797, "France" => 1628,
        "Philippines" => 1569, "United Kingdom" => 1533, "Germany" => 1421, "Algeria" => 1417,
        "Morocco" => 1409, "Azerbaijan" => 1327, "Russia" => 1286, "Turkey" => 1179,
        "China" => 1133, "Bosnia and Herzegovina" => 1113, "United Arab Emirates" => 1078,
        "Ethiopia" => 1067, "South Africa" => 1023, "Thailand" => 1017, "Kazakhstan" => 966,
        "Tunisia" => 962, "Italy" => 892, "Burkina Faso" => 851, "Tanzania" => 817,
        "Somalia" => 764, "Sudan" => 756, "Egypt" => 752, "Mali" => 746, "Uganda" => 699,
        "Iraq" => 698, "Uzbekistan" => 680, "Kenya" => 669, "Benin" => 665, "Canada" => 662,
        "Libya" => 608, "Spain" => 579, "Myanmar (Burma)" => 577, "Ghana" => 565,
        "Cameroon" => 548, "Australia" => 533, "Albania" => 517, "Bulgaria" => 493,
        "Mexico" => 469, "Brazil" => 452, "Belgium" => 442, "Mozambique" => 407,
        "Syria" => 403, "Côte d'Ivoire" => 400, "Yemen" => 378, "Senegal" => 373,
        "Japan" => 356, "Austria" => 348, "Jordan" => 341, "Guinea" => 339,
        "Mauritania" => 338, "Sri Lanka" => 335, "Kyrgyzstan" => 331, "Chad" => 330,
        "Netherlands" => 304, "Sweden" => 289, "Malawi" => 288, "Niger" => 286,
        "Madagascar" => 283, "Nepal" => 252, "Maldives" => 251, "Bahrain" => 246,
        "Tajikistan" => 244, "Cambodia" => 239, "South Korea" => 238, "Gambia" => 233,
        "Oman" => 230, "Democratic Republic of the Congo" => 228, "Burundi" => 227,
        "Vietnam" => 215, "Sierra Leone" => 209, "Zambia" => 190, "Switzerland" => 187,
        "Norway" => 186, "Zimbabwe" => 174, "North Macedonia" => 158, "Qatar" => 153,
        "Turkmenistan" => 149, "Lebanon" => 148, "Denmark" => 145, "Serbia" => 138,
        "Comoros" => 130, "New Zealand" => 130, "Colombia" => 124, "Eritrea" => 121,
        "Chile" => 120, "Portugal" => 117, "Argentina" => 114, "Ireland" => 114,
        "Israel" => 112, "Fiji" => 112, "Romania" => 112, "Brunei" => 108, "Togo" => 106,
        "Kuwait" => 102, "Finland" => 101, "Peru" => 101, "Singapore" => 95, "Angola" => 95,
        "Liberia" => 92, "Taiwan" => 91, "Trinidad and Tobago" => 88, "Poland" => 87,
        "Venezuela" => 86, "Guinea-Bissau" => 82, "Georgia" => 81, "Ukraine" => 78,
        "Djibouti" => 77, "Hong Kong" => 77, "Mauritius" => 76, "Rwanda" => 76,
        "South Sudan" => 75, "Guyana" => 67, "Suriname" => 61, "Montenegro" => 58,
        "Gabon" => 58, "Cyprus" => 57, "Ecuador" => 57, "Mongolia" => 56, "Greece" => 54,
        "Armenia" => 51, "Hungary" => 50, "Laos" => 49, "Bolivia" => 49, "Guatemala" => 48,
        "Republic of the Congo" => 48, "Réunion" => 44, "Mayotte" => 43,
        "Central African Republic" => 41, "Botswana" => 39, "Myanmar" => 39, "Kosovo" => 34,
        "Haiti" => 33, "Czechia" => 31, "Lesotho" => 30, "Paraguay" => 28, "Belarus" => 27,
        "Slovenia" => 26, "Lithuania" => 26, "Namibia" => 26, "Panama" => 26, "Moldova" => 24,
        "Croatia" => 23, "Jamaica" => 23, "Latvia" => 23, "Luxembourg" => 23,
        "Costa Rica" => 22, "Uruguay" => 22, "Cuba" => 20, "Timor-Leste" => 20,
        "Palestine" => 19, "Dominican Republic" => 18, "El Salvador" => 18, "Malta" => 17,
        "Honduras" => 16, "Nicaragua" => 16, "Barbados" => 15, "Equatorial Guinea" => 15,
        "Estonia" => 14, "Eswatini" => 14, "Papua New Guinea" => 14, "Slovakia" => 13,
        "Puerto Rico" => 13, "Cape Verde" => 8, "North Korea" => 8, "French Guiana" => 8,
        "Bahamas" => 7, "São Tomé and Príncipe" => 7, "Seychelles" => 7, "Vanuatu" => 7,
        "Bermuda" => 6, "Grenada" => 5, "Iceland" => 5, "Solomon Islands" => 5, "Aruba" => 5,
        "Belize" => 4, "Macao" => 4, "Guam" => 4, "Antigua and Barbuda" => 3, "Dominica" => 3,
        "Saint Kitts and Nevis" => 3, "Samoa" => 3, "Tonga" => 3, "Martinique" => 3,
        "Jersey" => 3, "Gaza Strip" => 3, "Northern Mariana Islands" => 3, "Cayman Islands" => 3
    ];

    $total_mosques = 113695; // Total hardcoded
    
    ob_start();
    
    $tbl = '<table class="mosque-summary-table" style="width:100%; border-collapse:collapse;">';
    $cnt = 0;
    
    foreach ($mosque_data as $country => $count) {
        $cnt++;
        if ($cnt > 200) break; // Hadkan paparan kepada 200 negara pertama

        $formatted_count = number_format($count);
        $tbl .= "<tr>";
        $tbl .= "<td style='padding:8px; border-bottom:1px solid #eee;'>{$country}</td>";
        $tbl .= "<td style='padding:8px; border-bottom:1px solid #eee; text-align: right; font-weight:bold;'>{$formatted_count}</td>";
        $tbl .= "</tr>";
    }
    $tbl .= '</table>';

    $header = '<div class="mosque-summary-header" style="margin-bottom:15px;">';
    $header .= 'Total Mosques : <b style="font-size:1.2em; color:#125C59;">' . number_format($total_mosques) . '</b>';
    $header .= '</div>';

    echo $header . $tbl;

    return ob_get_clean();
}

//////////////////////////////////
// BUSINESS SUMMARY (HARD-CODED)//
//////////////////////////////////
add_action('plugins_loaded', function () {
    add_shortcode('business_summary_country_hc', 'business_summary_country_hc_shortcode');
});

function business_summary_country_hc_shortcode() {
    // Data statik berdasarkan input yang diberikan
    $business_data = [
        "India" => 55132, "Indonesia" => 37201, "Malaysia" => 12764, "Japan" => 1778,
        "Belgium" => 1109, "Australia" => 963, "South Korea" => 576, "Cambodia" => 520,
        "Azerbaijan" => 120, "Canada" => 115, "Sri Lanka" => 97, "Ireland" => 94,
        "Kenya" => 92, "Brazil" => 90, "United Kingdom" => 81, "Thailand" => 80,
        "Bangladesh" => 62, "Kazakhstan" => 55, "Hong Kong" => 52, "Nigeria" => 49,
        "Lithuania" => 47, "Austria" => 46, "Cameroon" => 42, "France" => 41,
        "Colombia" => 41, "Germany" => 38, "Albania" => 38, "South Africa" => 36,
        "Luxembourg" => 31, "Vietnam" => 31, "Latvia" => 25, "Burkina Faso" => 24,
        "Spain" => 21, "Laos" => 21, "Bulgaria" => 20, "Guyana" => 19, "Kyrgyzstan" => 19,
        "Angola" => 18, "Serbia" => 16, "Romania" => 16, "Benin" => 14, "Croatia" => 13,
        "Israel" => 13, "Lesotho" => 13, "Pakistan" => 12, "Argentina" => 12,
        "Netherlands" => 11, "Philippines" => 10, "Uzbekistan" => 8, "Brunei" => 8,
        "China" => 7, "Togo" => 7, "Jamaica" => 6, "Rwanda" => 6, "Andorra" => 6,
        "Armenia" => 6, "Nepal" => 6, "Suriname" => 5, "Afghanistan" => 4, "Jordan" => 4,
        "Lebanon" => 4, "Switzerland" => 3, "Niger" => 3, "Kuwait" => 3, "Senegal" => 2,
        "Slovenia" => 2, "Dominican Republic" => 2, "Namibia" => 2, "Paraguay" => 2,
        "Timor-Leste" => 2, "Uganda" => 2, "Bahrain" => 2, "Burundi" => 2, "Bolivia" => 2,
        "Peru" => 2, "Poland" => 2, "Libya" => 2, "Honduras" => 1, "Guinea" => 1,
        "Saudi Arabia" => 1, "Madagascar" => 1, "Haiti" => 1, "Greece" => 1, "Georgia" => 1,
        "Uruguay" => 1, "Tajikistan" => 1, "Mayotte" => 1, "Comoros" => 1, "Ghana" => 1,
        "Liberia" => 1, "Venezuela" => 1, "Morocco" => 1
    ];

    $total_business = 111904; // Total keseluruhan yang di-hardcode

    ob_start();
    ?>
    <div class="business-summary-wrapper">
        <p>Total Business : <b><?php echo number_format($total_business); ?></b></p>
        <table class="business-summary-table" style="width: 100%; border-collapse: collapse; font-family: Arial, sans-serif;">
            <?php 
            $cnt = 0;
            foreach ($business_data as $country => $count) : 
                $cnt++;
                if ($cnt > 300) break; // Batasan tampilan sesuai logika awal
            ?>
                <tr>
                    <td style="padding: 6px 0; border-bottom: 1px solid #eee;"><?php echo esc_html($country); ?></td>
                    <td style="padding: 6px 0; border-bottom: 1px solid #eee; text-align: right; font-weight: bold;">
                        <?php echo number_format($count); ?>
                    </td>
                </tr>
            <?php endforeach; ?>
        </table>
    </div>
    <?php
    return ob_get_clean();
}

//////////////////////////////////
// MEMBER SUMMARY (HARD-CODED)  //
//////////////////////////////////
add_action('plugins_loaded', function () {
    add_shortcode('member_summary_country_hc', 'member_summary_country_hc_shortcode');
});

function member_summary_country_hc_shortcode() {
    // Data keahlian statik berdasarkan input yang diberikan
    $member_data = [
        "Malaysia"       => 10699,
        "United Kingdom" => 9,
        "Indonesia"      => 10,
        "Canada"         => 4,
        "Turkey"         => 4,
        "Nigeria"        => 4,
        "Thailand"       => 5,
        "United States"  => 1,
        "New Zealand"    => 3,
        "Belize"         => 1
    ];

    $total_members = 10740; // Total keseluruhan ahli 

    ob_start();
    ?>
    <div class="member-summary-wrapper">
        <p>Total Member : <b><?php echo number_format($total_members); ?></b></p>
        <table class="member-summary-table" style="width: 100%; border-collapse: collapse; font-family: Arial, sans-serif;">
            <?php 
            $cnt = 0;
            foreach ($member_data as $country => $count) : 
                $cnt++;
                // Mengikut logik asal anda yang mengehadkan paparan (contoh: 10 item)
                if ($cnt > 20) break; 
            ?>
                <tr>
                    <td style="padding: 6px 0; border-bottom: 1px solid #eee;"><?php echo esc_html($country); ?></td>
                    <td style="padding: 6px 0; border-bottom: 1px solid #eee; text-align: right; font-weight: bold;">
                        <?php echo number_format($count); ?>
                    </td>
                </tr>
            <?php endforeach; ?>
        </table>
    </div>
    <?php
    return ob_get_clean();
}

//////////////////////////////////
// MOSQUE SUMMARY BY REGION     //
//////////////////////////////////
add_action('plugins_loaded', function () {
    add_shortcode('mosque_summary_continent_hc', 'mosque_summary_continent_hc_shortcode');
}); 

function mosque_summary_continent_hc_shortcode() {
    ob_start();

    // Data Hardcoded Penuh (Disusun ikut Benua)
    $grouped_data = [
        "Asia" => [
            "Indonesia" => 25372, "India" => 15206, "Malaysia" => 8692, "Bangladesh" => 4442, 
            "Iran" => 2738, "Pakistan" => 2425, "Afghanistan" => 2238, "Saudi Arabia" => 2099, 
            "Türkiye" => 1797, "Turkey" => 1179, "Philippines" => 1569, "Azerbaijan" => 1327, 
            "China" => 1133, "United Arab Emirates" => 1078, "Thailand" => 1017, "Kazakhstan" => 966, 
            "Iraq" => 698, "Uzbekistan" => 680, "Myanmar (Burma)" => 577, "Syria" => 403, 
            "Yemen" => 378, "Japan" => 356, "Jordan" => 341, "Sri Lanka" => 335, "Kyrgyzstan" => 331, 
            "Nepal" => 252, "Maldives" => 251, "Bahrain" => 246, "Tajikistan" => 244, "Cambodia" => 239, 
            "South Korea" => 238, "Oman" => 230, "Vietnam" => 215, "Qatar" => 153, "Turkmenistan" => 149, 
            "Lebanon" => 148, "Israel" => 112, "Brunei" => 108, "Kuwait" => 102, "Singapore" => 95, 
            "Taiwan" => 91, "Georgia" => 81, "Hong Kong" => 77, "Mongolia" => 56, "Armenia" => 51, 
            "Laos" => 49, "Myanmar" => 39, "Timor-Leste" => 20, "Palestine" => 19, "North Korea" => 8, 
            "Macao" => 4, "Gaza Strip" => 3
        ],
        "Africa" => [
            "Nigeria" => 1971, "Algeria" => 1417, "Morocco" => 1409, "Ethiopia" => 1067, 
            "South Africa" => 1023, "Tunisia" => 962, "Burkina Faso" => 851, "Tanzania" => 817, 
            "Somalia" => 764, "Sudan" => 756, "Egypt" => 752, "Mali" => 746, "Uganda" => 699, 
            "Kenya" => 669, "Benin" => 665, "Libya" => 608, "Ghana" => 565, "Cameroon" => 548, 
            "Mozambique" => 407, "Côte d'Ivoire" => 400, "Senegal" => 373, "Guinea" => 339, 
            "Mauritania" => 338, "Chad" => 330, "Malawi" => 288, "Niger" => 286, "Madagascar" => 283, 
            "Gambia" => 233, "Democratic Republic of the Congo" => 228, "Burundi" => 227, 
            "Sierra Leone" => 209, "Zambia" => 190, "Zimbabwe" => 174, "Eritrea" => 121, 
            "Comoros" => 130, "Togo" => 106, "Angola" => 95, "Liberia" => 92, "Guinea-Bissau" => 82, 
            "Djibouti" => 77, "Mauritius" => 76, "Rwanda" => 76, "South Sudan" => 75, "Gabon" => 58, 
            "Republic of the Congo" => 48, "Réunion" => 44, "Mayotte" => 43, "Central African Republic" => 41, 
            "Botswana" => 39, "Lesotho" => 30, "Namibia" => 26, "Equatorial Guinea" => 15, "Eswatini" => 14, 
            "Cape Verde" => 8, "São Tomé and Príncipe" => 7, "Seychelles" => 7
        ],
        "Europe" => [
            "France" => 1628, "United Kingdom" => 1533, "Germany" => 1421, "Russia" => 1286, 
            "Bosnia and Herzegovina" => 1113, "Italy" => 892, "Spain" => 579, "Albania" => 517, 
            "Bulgaria" => 493, "Belgium" => 442, "Austria" => 348, "Netherlands" => 304, "Sweden" => 289, 
            "Switzerland" => 187, "Norway" => 186, "North Macedonia" => 158, "Denmark" => 145, 
            "Serbia" => 138, "Portugal" => 117, "Ireland" => 114, "Romania" => 112, "Finland" => 101, 
            "Poland" => 87, "Ukraine" => 78, "Montenegro" => 58, "Cyprus" => 57, "Greece" => 54, 
            "Hungary" => 50, "Kosovo" => 34, "Czechia" => 31, "Belarus" => 27, "Slovenia" => 26, 
            "Lithuania" => 26, "Moldova" => 24, "Croatia" => 23, "Latvia" => 23, "Luxembourg" => 23, 
            "Malta" => 17, "Estonia" => 14, "Slovakia" => 13, "Iceland" => 5, "Jersey" => 3
        ],
        "North America" => [
            "United States" => 3223, "Canada" => 662, "Mexico" => 469, "Trinidad and Tobago" => 88, 
            "Panama" => 26, "Costa Rica" => 22, "Jamaica" => 23, "Guatemala" => 48, "Haiti" => 33, 
            "Cuba" => 20, "Dominican Republic" => 18, "El Salvador" => 18, "Honduras" => 16, 
            "Nicaragua" => 16, "Barbados" => 15, "Puerto Rico" => 13, "Bahamas" => 7, "Bermuda" => 6, 
            "Grenada" => 5, "Aruba" => 5, "Belize" => 4, "Antigua and Barbuda" => 3, "Dominica" => 3, 
            "Saint Kitts and Nevis" => 3, "Martinique" => 3, "Cayman Islands" => 3
        ],
        "South America" => [
            "Brazil" => 452, "Colombia" => 124, "Chile" => 120, "Argentina" => 114, "Peru" => 101, 
            "Venezuela" => 86, "Guyana" => 67, "Suriname" => 61, "Ecuador" => 57, "Bolivia" => 49, 
            "Paraguay" => 28, "Uruguay" => 22, "French Guiana" => 8
        ],
        "Oceania" => [
            "Australia" => 533, "New Zealand" => 130, "Fiji" => 112, "Papua New Guinea" => 14, 
            "Vanuatu" => 7, "Solomon Islands" => 5, "Guam" => 4, "Samoa" => 3, "Tonga" => 3, 
            "Northern Mariana Islands" => 3
        ]
    ];

    // Mengira jumlah keseluruhan mengikut benua
    $continent_totals = [];
    $total_mosque = 0;
    
    foreach ($grouped_data as $continent => $countries) {
        $sum = array_sum($countries);
        $continent_totals[$continent] = $sum;
        $total_mosque += $sum;
    }

    // Susun benua dari terbesar ke terkecil
    arsort($continent_totals);
    $total_regions = count($continent_totals);

    // Header Info (Teal Theme)
    echo '<div style="padding: 15px; background: #e6f2f1; border-left: 5px solid #125C59; border-radius: 4px; margin-bottom:15px;">';
    echo 'Total Global Mosques: <b>' . number_format(122192) . '</b> in <b>' . $total_regions . ' Regions</b>';
    echo '</div>';

    if (!empty($continent_totals)) {
        echo '<table style="width:100%; border-collapse: collapse; margin-top:10px;">';
        echo '<tr style="background:#f9f9f9; border-bottom: 2px solid #eee;">
                    <th style="text-align: left; padding: 10px;">Region / Continent</th>
                    <th style="text-align: right; padding: 10px;">Total Mosques</th>
                 </tr>';
        
        foreach ($continent_totals as $continent => $count) {
            $tot = number_format($count);
            echo '<tr>';
            echo "<td style='padding: 10px; border-bottom: 1px solid #eee;'>" . esc_html($continent) . "</td>";
            echo "<td style='padding: 10px; border-bottom: 1px solid #eee; text-align: right'>
                    <a href='javascript:void(0);' class='m4a-mosque-drilldown-btn' data-continent='" . esc_attr($continent) . "' style='text-decoration: underline; color: #125C59; font-weight: bold; cursor: pointer;' title='Click to view countries'>{$tot}</a>
                  </td>";
            echo '</tr>';
        }
        echo '</table>';
    }

    // HTML & CSS Modal Mosque
    ?>
    <style>
        .m4a-modal-overlay {
            display: none;
            position: fixed;
            z-index: 99999;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            overflow: auto;
            background-color: rgba(0,0,0,0.6);
        }
        .m4a-modal-content {
            background-color: #fff;
            margin: 5% auto;
            padding: 20px;
            border-radius: 8px;
            width: 90%;
            max-width: 600px;
            position: relative;
            box-shadow: 0 4px 15px rgba(0,0,0,0.2);
        }
        .m4a-close-modal {
            color: #aaa;
            float: right;
            font-size: 28px;
            font-weight: bold;
            cursor: pointer;
            line-height: 1;
        }
        .m4a-close-modal:hover { color: #125C59; text-decoration: none; }
        .m4a-chart-canvas-box {
            width: 100%;
            height: 250px; /* Saiz wajib supaya carta render dengan betul */
            position: relative;
        }
    </style>

    <div id="m4aMosqueDrilldownModal" class="m4a-modal-overlay">
        <div class="m4a-modal-content">
            <span class="m4a-close-modal m4a-mosque-close">&times;</span>
            <h2 id="m4aMosqueModalTitle" style="margin-top:0; color:#125C59; border-bottom: 2px solid #f1f1f1; padding-bottom: 10px;">Continent Details</h2>

            <div id="m4aMosqueModalBody" class="m4a-modal-body" style="display:flex; flex-direction: column;">
                <div class="m4a-modal-chart-wrap">
                    <h4 style="margin: 0 0 15px 0; color:#444;">Top 10 Countries</h4>
                    <div class="m4a-chart-canvas-box">
                        <canvas id="m4aMosqueDrilldownChart"></canvas>
                    </div>
                </div>
                <div class="m4a-modal-table-wrap" style="margin-top: 20px;">
                    <div id="m4aMosqueModalTableContent"></div>
                </div>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    
    <script>
    document.addEventListener("DOMContentLoaded", function() {
        
        const hardcodedData = <?php echo json_encode($grouped_data); ?>;
        const modal = document.getElementById("m4aMosqueDrilldownModal");
        
        if (modal && !modal.closest('body > .m4a-modal-overlay')) { 
            document.body.appendChild(modal); 
        }

        const closeBtn = document.querySelector(".m4a-mosque-close");
        const drillBtns = document.querySelectorAll(".m4a-mosque-drilldown-btn");
        let chartInst = null; 

        drillBtns.forEach(btn => {
            btn.addEventListener("click", function() {
                const continent = this.getAttribute("data-continent");
                document.getElementById("m4aMosqueModalTitle").innerText = "Mosques in " + continent;
                
                // PENTING: Buka modal dahulu supaya saiz canvas dapat dikesan
                modal.style.display = "block"; 
                
                const continentCountries = hardcodedData[continent] || {};
                const sortedCountries = Object.entries(continentCountries).sort((a, b) => b[1] - a[1]);

                let chartLabels = [];
                let chartData = [];
                
                let tableHtml = '<table style="width:100%; border-collapse: collapse;">';
                tableHtml += '<tr style="background:#f9f9f9; border-bottom: 2px solid #eee;"><th style="text-align: left; padding: 10px;">Country</th><th style="text-align: right; padding: 10px;">Total Mosques</th></tr>';
                
                sortedCountries.forEach((item, index) => {
                    let countryName = item[0];
                    let count = item[1];
                    
                    if(index < 10) {
                        chartLabels.push(countryName);
                        chartData.push(count);
                    }
                    
                    tableHtml += `<tr>
                        <td style="padding: 10px; border-bottom: 1px solid #eee;">${countryName}</td>
                        <td style="padding: 10px; border-bottom: 1px solid #eee; text-align: right">${count.toLocaleString()}</td>
                    </tr>`;
                });
                tableHtml += '</table>';

                document.getElementById("m4aMosqueModalTableContent").innerHTML = tableHtml;

                // BERI MASA KEPADA BROWSER (100ms) UNTUK RENDER MODAL SEBELUM LUKIS CARTA
                setTimeout(function() {
                    const canvas = document.getElementById("m4aMosqueDrilldownChart");
                    if(!canvas) return;
                    
                    const ctx = canvas.getContext("2d");
                    if(chartInst != null) { chartInst.destroy(); }

                    chartInst = new Chart(ctx, {
                        type: 'pie',
                        data: {
                            labels: chartLabels,
                            datasets: [{
                                data: chartData,
                                backgroundColor: ['#125C59', '#2ecc71', '#3498db', '#9b59b6', '#f1c40f', '#e67e22', '#e74c3c', '#95a5a6', '#34495e', '#FF6384'],
                                borderWidth: 2, borderColor: '#ffffff'
                            }]
                        },
                        options: { responsive: true, maintainAspectRatio: false, plugins: { datalabels: { display: false }, legend: { position: 'right', labels: { boxWidth: 12, font: {size: 11} } } } }
                    });
                }, 100);

            });
        });

        const closeModal = function() { modal.style.display = "none"; };
        if(closeBtn) closeBtn.onclick = closeModal;
        window.addEventListener('click', function(e) { if (e.target == modal) closeModal(); });
    });
    </script>
    <?php
    return ob_get_clean();
}

//////////////////////////////////
// BUSINESS SUMMARY BY REGION   //
//////////////////////////////////
add_action('plugins_loaded', function () {
    add_shortcode('business_summary_continent_hc', 'business_summary_continent_hc_shortcode');
}); 

function business_summary_continent_hc_shortcode() {
    ob_start();

    // Data Hardcoded Penuh (Business - Disusun ikut Benua)
    $grouped_data = [
        "Asia" => [
            "India" => 55132, "Indonesia" => 37201, "Malaysia" => 12764, "Japan" => 1778, "South Korea" => 576, "Cambodia" => 520, "Azerbaijan" => 120, "Sri Lanka" => 97, "Thailand" => 80, "Bangladesh" => 62, "Kazakhstan" => 55, "Hong Kong" => 52, "Vietnam" => 31, "Laos" => 21, "Kyrgyzstan" => 19, "Israel" => 13, "Pakistan" => 12, "Philippines" => 10, "Uzbekistan" => 8, "Brunei" => 8, "China" => 7, "Armenia" => 6, "Nepal" => 6, "Afghanistan" => 4, "Jordan" => 4, "Lebanon" => 4, "Kuwait" => 3, "Timor-Leste" => 2, "Bahrain" => 2, "Saudi Arabia" => 1, "Georgia" => 1, "Tajikistan" => 1
        ],
        "Europe" => [
            "Belgium" => 1109, "Ireland" => 94, "United Kingdom" => 81, "Lithuania" => 47, "Austria" => 46, "France" => 41, "Germany" => 38, "Albania" => 38, "Luxembourg" => 31, "Latvia" => 25, "Spain" => 21, "Bulgaria" => 20, "Serbia" => 16, "Romania" => 16, "Croatia" => 13, "Netherlands" => 11, "Andorra" => 6, "Switzerland" => 3, "Slovenia" => 2, "Poland" => 2, "Greece" => 1
        ],
        "Africa" => [
            "Kenya" => 92, "Nigeria" => 49, "Cameroon" => 42, "South Africa" => 36, "Burkina Faso" => 24, "Angola" => 18, "Benin" => 14, "Lesotho" => 13, "Togo" => 7, "Rwanda" => 6, "Niger" => 3, "Senegal" => 2, "Namibia" => 2, "Uganda" => 2, "Burundi" => 2, "Libya" => 2, "Guinea" => 1, "Madagascar" => 1, "Mayotte" => 1, "Comoros" => 1, "Ghana" => 1, "Liberia" => 1, "Morocco" => 1
        ],
        "Oceania" => [
            "Australia" => 963
        ],
        "North America" => [
            "Canada" => 115, "Jamaica" => 6, "Dominican Republic" => 2, "Honduras" => 1, "Haiti" => 1
        ],
        "South America" => [
            "Brazil" => 90, "Colombia" => 41, "Guyana" => 19, "Argentina" => 12, "Suriname" => 5, "Paraguay" => 2, "Bolivia" => 2, "Peru" => 2, "Uruguay" => 1, "Venezuela" => 1
        ]
    ];

    // Mengira jumlah keseluruhan mengikut benua
    $continent_totals = [];
    
    foreach ($grouped_data as $continent => $countries) {
        $sum = array_sum($countries);
        $continent_totals[$continent] = $sum;
    }

    // Susun benua dari terbesar ke terkecil
    arsort($continent_totals);
    $total_regions = count($continent_totals);
    $total_business = 111904; // Hardcoded total based on user data

    // Header Info (Teal Theme)
    echo '<div style="padding: 15px; background: #e6f2f1; border-left: 5px solid #125C59; border-radius: 4px; margin-bottom:15px;">';
    echo 'Total Global Businesses: <b>' . number_format($total_business) . '</b> in <b>' . $total_regions . ' Regions</b>';
    echo '</div>';

    if (!empty($continent_totals)) {
        echo '<table style="width:100%; border-collapse: collapse; margin-top:10px;">';
        echo '<tr style="background:#f9f9f9; border-bottom: 2px solid #eee;">
                    <th style="text-align: left; padding: 10px;">Region / Continent</th>
                    <th style="text-align: right; padding: 10px;">Total Businesses</th>
                 </tr>';
        
        foreach ($continent_totals as $continent => $count) {
            $tot = number_format($count);
            echo '<tr>';
            echo "<td style='padding: 10px; border-bottom: 1px solid #eee;'>" . esc_html($continent) . "</td>";
            echo "<td style='padding: 10px; border-bottom: 1px solid #eee; text-align: right'>
                    <a href='javascript:void(0);' class='m4a-biz-drilldown-btn' data-continent='" . esc_attr($continent) . "' style='text-decoration: underline; color: #125C59; font-weight: bold; cursor: pointer;' title='Click to view countries'>{$tot}</a>
                  </td>";
            echo '</tr>';
        }
        echo '</table>';
    }

    // HTML & CSS Modal Business
    ?>
    <style>
        .m4a-biz-modal-overlay {
            display: none;
            position: fixed;
            z-index: 99999;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            overflow: auto;
            background-color: rgba(0,0,0,0.6);
        }
        .m4a-biz-modal-content {
            background-color: #fff;
            margin: 5% auto;
            padding: 20px;
            border-radius: 8px;
            width: 90%;
            max-width: 600px;
            position: relative;
            box-shadow: 0 4px 15px rgba(0,0,0,0.2);
        }
        .m4a-biz-close-modal {
            color: #aaa;
            float: right;
            font-size: 28px;
            font-weight: bold;
            cursor: pointer;
            line-height: 1;
        }
        .m4a-biz-close-modal:hover { color: #125C59; text-decoration: none; }
        .m4a-biz-chart-canvas-box {
            width: 100%;
            height: 250px;
            position: relative;
        }
    </style>

    <div id="m4aBizDrilldownModal" class="m4a-biz-modal-overlay">
        <div class="m4a-biz-modal-content">
            <span class="m4a-biz-close-modal m4a-biz-close">&times;</span>
            <h2 id="m4aBizModalTitle" style="margin-top:0; color:#125C59; border-bottom: 2px solid #f1f1f1; padding-bottom: 10px;">Continent Details</h2>

            <div id="m4aBizModalBody" class="m4a-modal-body" style="display:flex; flex-direction: column;">
                <div class="m4a-modal-chart-wrap">
                    <h4 style="margin: 0 0 15px 0; color:#444;">Top 10 Countries</h4>
                    <div class="m4a-biz-chart-canvas-box">
                        <canvas id="m4aBizDrilldownChart"></canvas>
                    </div>
                </div>
                <div class="m4a-modal-table-wrap" style="margin-top: 20px;">
                    <div id="m4aBizModalTableContent"></div>
                </div>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    
    <script>
    document.addEventListener("DOMContentLoaded", function() {
        
        const hardcodedBizData = <?php echo json_encode($grouped_data); ?>;
        const bizModal = document.getElementById("m4aBizDrilldownModal");
        
        if (bizModal && !bizModal.closest('body > .m4a-biz-modal-overlay')) { 
            document.body.appendChild(bizModal); 
        }

        const closeBizBtn = document.querySelector(".m4a-biz-close");
        const drillBizBtns = document.querySelectorAll(".m4a-biz-drilldown-btn");
        let chartBizInst = null; 

        drillBizBtns.forEach(btn => {
            btn.addEventListener("click", function() {
                const continent = this.getAttribute("data-continent");
                document.getElementById("m4aBizModalTitle").innerText = "Businesses in " + continent;
                
                bizModal.style.display = "block"; 
                
                const continentCountries = hardcodedBizData[continent] || {};
                const sortedCountries = Object.entries(continentCountries).sort((a, b) => b[1] - a[1]);

                let chartLabels = [];
                let chartData = [];
                
                let tableHtml = '<table style="width:100%; border-collapse: collapse;">';
                tableHtml += '<tr style="background:#f9f9f9; border-bottom: 2px solid #eee;"><th style="text-align: left; padding: 10px;">Country</th><th style="text-align: right; padding: 10px;">Total Businesses</th></tr>';
                
                sortedCountries.forEach((item, index) => {
                    let countryName = item[0];
                    let count = item[1];
                    
                    if(index < 10) {
                        chartLabels.push(countryName);
                        chartData.push(count);
                    }
                    
                    tableHtml += `<tr>
                        <td style="padding: 10px; border-bottom: 1px solid #eee;">${countryName}</td>
                        <td style="padding: 10px; border-bottom: 1px solid #eee; text-align: right">${count.toLocaleString()}</td>
                    </tr>`;
                });
                tableHtml += '</table>';

                document.getElementById("m4aBizModalTableContent").innerHTML = tableHtml;

                // Kelewatan 100ms agar browser ada masa memaparkan saiz modal
                setTimeout(function() {
                    const canvas = document.getElementById("m4aBizDrilldownChart");
                    if(!canvas) return;
                    
                    const ctx = canvas.getContext("2d");
                    if(chartBizInst != null) { chartBizInst.destroy(); }

                    chartBizInst = new Chart(ctx, {
                        type: 'pie',
                        data: {
                            labels: chartLabels,
                            datasets: [{
                                data: chartData,
                                backgroundColor: ['#125C59', '#2ecc71', '#3498db', '#9b59b6', '#f1c40f', '#e67e22', '#e74c3c', '#95a5a6', '#34495e', '#FF6384'],
                                borderWidth: 2, borderColor: '#ffffff'
                            }]
                        },
                        options: { responsive: true, maintainAspectRatio: false, plugins: { datalabels: { display: false }, legend: { position: 'right', labels: { boxWidth: 12, font: {size: 11} } } } }
                    });
                }, 100);

            });
        });

        const closeBizModal = function() { bizModal.style.display = "none"; };
        if(closeBizBtn) closeBizBtn.onclick = closeBizModal;
        window.addEventListener('click', function(e) { if (e.target == bizModal) closeBizModal(); });
    });
    </script>
    <?php
    return ob_get_clean();
}

//////////////////////////////////
// MEMBER SUMMARY BY REGION     //
//////////////////////////////////
add_action('plugins_loaded', function () {
    add_shortcode('member_summary_continent_hc', 'member_summary_continent_hc_shortcode');
}); 

function member_summary_continent_hc_shortcode() {
    ob_start();

    // Data Hardcoded Penuh (Member - Disusun ikut Benua)
$grouped_data = [
    "Asia" => [
        "Malaysia"   => 10699,
        "Indonesia"  => 10,
        "Thailand"   => 5,
        "Turkey"     => 4
    ],
    "Europe" => [
        "United Kingdom" => 9
    ],
    "North America" => [
        "Belize"        => 1,
        "Canada"        => 4,
        "United States" => 1
    ],
    "Africa" => [
        "Nigeria" => 4
    ],
    "Oceania" => [
        "New Zealand" => 3
    ]
];

    // Mengira jumlah keseluruhan mengikut benua
    $continent_totals = [];
    
    foreach ($grouped_data as $continent => $countries) {
        $sum = array_sum($countries);
        $continent_totals[$continent] = $sum;
    }

    // Susun benua dari terbesar ke terkecil
    arsort($continent_totals);
    $total_regions = count($continent_totals);
    $total_member = 10740; // Hardcoded total based on user data

    // Header Info (Teal Theme)
    echo '<div style="padding: 15px; background: #e6f2f1; border-left: 5px solid #125C59; border-radius: 4px; margin-bottom:15px;">';
    echo 'Total Global Members: <b>' . number_format($total_member) . '</b> in <b>' . $total_regions . ' Regions</b>';
    echo '</div>';

    if (!empty($continent_totals)) {
        echo '<table style="width:100%; border-collapse: collapse; margin-top:10px;">';
        echo '<tr style="background:#f9f9f9; border-bottom: 2px solid #eee;">
                    <th style="text-align: left; padding: 10px;">Region / Continent</th>
                    <th style="text-align: right; padding: 10px;">Total Members</th>
                 </tr>';
        
        foreach ($continent_totals as $continent => $count) {
            $tot = number_format($count);
            echo '<tr>';
            echo "<td style='padding: 10px; border-bottom: 1px solid #eee;'>" . esc_html($continent) . "</td>";
            echo "<td style='padding: 10px; border-bottom: 1px solid #eee; text-align: right'>
                    <a href='javascript:void(0);' class='m4a-member-drilldown-btn' data-continent='" . esc_attr($continent) . "' style='text-decoration: underline; color: #125C59; font-weight: bold; cursor: pointer;' title='Click to view countries'>{$tot}</a>
                  </td>";
            echo '</tr>';
        }
        echo '</table>';
    }

    // HTML & CSS Modal Member (Tanpa Carta)
    ?>
    <style>
        .m4a-member-modal-overlay {
            display: none;
            position: fixed;
            z-index: 99999;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            overflow: auto;
            background-color: rgba(0,0,0,0.6);
        }
        .m4a-member-modal-content {
            background-color: #fff;
            margin: 5% auto;
            padding: 20px;
            border-radius: 8px;
            width: 90%;
            max-width: 600px;
            position: relative;
            box-shadow: 0 4px 15px rgba(0,0,0,0.2);
        }
        .m4a-member-close-modal {
            color: #aaa;
            float: right;
            font-size: 28px;
            font-weight: bold;
            cursor: pointer;
            line-height: 1;
        }
        .m4a-member-close-modal:hover { color: #125C59; text-decoration: none; }
    </style>

    <div id="m4aMemberDrilldownModal" class="m4a-member-modal-overlay">
        <div class="m4a-member-modal-content">
            <span class="m4a-member-close-modal m4a-member-close">&times;</span>
            <h2 id="m4aMemberModalTitle" style="margin-top:0; color:#125C59; border-bottom: 2px solid #f1f1f1; padding-bottom: 10px;">Continent Details</h2>

            <div id="m4aMemberModalBody" class="m4a-modal-body" style="display:flex; flex-direction: column;">
                <div class="m4a-modal-table-wrap" style="margin-top: 10px;">
                    <div id="m4aMemberModalTableContent"></div>
                </div>
            </div>
        </div>
    </div>
    
    <script>
    document.addEventListener("DOMContentLoaded", function() {
        
        const hardcodedMemberData = <?php echo json_encode($grouped_data); ?>;
        const memberModal = document.getElementById("m4aMemberDrilldownModal");
        
        if (memberModal && !memberModal.closest('body > .m4a-member-modal-overlay')) { 
            document.body.appendChild(memberModal); 
        }

        const closeMemberBtn = document.querySelector(".m4a-member-close");
        const drillMemberBtns = document.querySelectorAll(".m4a-member-drilldown-btn");

        drillMemberBtns.forEach(btn => {
            btn.addEventListener("click", function() {
                const continent = this.getAttribute("data-continent");
                document.getElementById("m4aMemberModalTitle").innerText = "Members in " + continent;
                
                memberModal.style.display = "block"; 
                
                const continentCountries = hardcodedMemberData[continent] || {};
                const sortedCountries = Object.entries(continentCountries).sort((a, b) => b[1] - a[1]);

                let tableHtml = '<table style="width:100%; border-collapse: collapse;">';
                tableHtml += '<tr style="background:#f9f9f9; border-bottom: 2px solid #eee;"><th style="text-align: left; padding: 10px;">Country</th><th style="text-align: right; padding: 10px;">Total Members</th></tr>';
                
                sortedCountries.forEach((item) => {
                    let countryName = item[0];
                    let count = item[1];
                    
                    tableHtml += `<tr>
                        <td style="padding: 10px; border-bottom: 1px solid #eee;">${countryName}</td>
                        <td style="padding: 10px; border-bottom: 1px solid #eee; text-align: right">${count.toLocaleString()}</td>
                    </tr>`;
                });
                tableHtml += '</table>';

                document.getElementById("m4aMemberModalTableContent").innerHTML = tableHtml;
            });
        });

        const closeMemberModal = function() { memberModal.style.display = "none"; };
        if(closeMemberBtn) closeMemberBtn.onclick = closeMemberModal;
        window.addEventListener('click', function(e) { if (e.target == memberModal) closeMemberModal(); });
    });
    </script>
    <?php
    return ob_get_clean();
}

/**
 * SHORTCODE: Mosque Distribution by Continent Pie Chart (Hardcoded)
 * --------------------------------------------------------------------------
 * Uses hardcoded data and renders a Chart.js Pie Chart safely alongside others.
 * --------------------------------------------------------------------------
 */
add_shortcode('mosque_pie_chart_hc', 'mosque_continent_pie_chart_hc_shortcode');

function mosque_continent_pie_chart_hc_shortcode() {
    // Data Hardcoded
    $continent_data = [
        "Asia" => 79271,
        "Africa" => 20381,
        "Europe" => 11262,
        "North America" => 4754,
        "South America" => 1289,
        "Oceania" => 814
    ];

    $labels = array_keys($continent_data);
    $counts = array_values($continent_data);

    $chart_id = 'mosquePieChart_' . uniqid();

    ob_start();
    ?>
    <style>
        /* CSS Wrapper untuk Responsive Mobile */
        .m4a-pie-wrapper {
            width: 100%;
            max-width: 550px;
            margin: 40px auto;
            padding: 20px;
            background: #fff;
            border-radius: 12px;
            box-shadow: 0 10px 25px rgba(0,0,0,0.05);
            font-family: 'Segoe UI', Roboto, Helvetica, Arial, sans-serif;
            box-sizing: border-box;
        }
        
        /* Container canvas MESTI position:relative untuk maintainAspectRatio:false berfungsi */
        .m4a-pie-canvas-container {
            position: relative;
            width: 100%;
            height: 450px; /* Ketinggian optimum untuk Desktop */
        }

        /* Skrin Mobile */
        @media (max-width: 768px) {
            .m4a-pie-wrapper {
                margin: 20px auto;
                padding: 15px 10px;
            }
            .m4a-pie-canvas-container {
                height: 380px; /* Ketinggian khusus mobile agar pai tidak terlalu kecil */
            }
        }
    </style>

    <div class="m4a-pie-wrapper">
        <div class="m4a-pie-canvas-container">
            <canvas id="<?php echo $chart_id; ?>"></canvas>
        </div>
    </div>

    <script>
    document.addEventListener("DOMContentLoaded", function() {
        
        // 1. Masukkan library Chart.js & Datalabels secara dinamik (Hanya jika belum wujud)
        if (!document.getElementById('chartjs-core-lib')) {
            let scriptChart = document.createElement('script');
            scriptChart.id = 'chartjs-core-lib';
            scriptChart.src = 'https://cdn.jsdelivr.net/npm/chart.js';
            document.head.appendChild(scriptChart);
        }
        
        if (!document.getElementById('chartjs-datalabels-lib')) {
            let scriptLabels = document.createElement('script');
            scriptLabels.id = 'chartjs-datalabels-lib';
            scriptLabels.src = 'https://cdn.jsdelivr.net/npm/chartjs-plugin-datalabels@2';
            document.head.appendChild(scriptLabels);
        }

        // 2. Fungsi melukis carta (Akan tunggu sehingga library selesai dimuat turun)
        function renderMosquePieChart() {
            // Periksa jika library Chart.js & Datalabels sudah sedia
            if (typeof Chart === 'undefined' || typeof ChartDataLabels === 'undefined') {
                setTimeout(renderMosquePieChart, 50); // Cuba lagi dalam 50ms
                return;
            }

            const canvasEl = document.getElementById('<?php echo $chart_id; ?>');
            if (!canvasEl) return;
            const ctxPie = canvasEl.getContext('2d');

            // Tetapkan saiz font secara dinamik berdasar saiz skrin
            let isMobile = window.innerWidth < 768;

            new Chart(ctxPie, {
                type: 'pie',
                plugins: [ChartDataLabels], // PENTING: Daftar datalabels secara lokal untuk carta ini sahaja
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
                    maintainAspectRatio: false, // KUNCI UTAMA: Membenarkan carta mengikut ketinggian div parent
                    layout: {
                        padding: isMobile ? 0 : 10
                    },
                    plugins: {
                        title: {
                            display: true,
                            text: 'Mosque Distribution Across Continents',
                            color: '#2c3e50',
                            font: {
                                size: isMobile ? 16 : 20, // Font tajuk lebih kecil di mobile
                                weight: 'bold',
                                family: 'Helvetica Neue'
                            },
                            padding: { top: 5, bottom: 15 }
                        },
                        legend: {
                            position: 'bottom',
                            labels: { 
                                boxWidth: isMobile ? 12 : 15, 
                                padding: isMobile ? 10 : 20, 
                                font: { size: isMobile ? 11 : 13 } // Font legend lebih sesuai di mobile
                            }
                        },
                        tooltip: {
                            backgroundColor: 'rgba(0, 0, 0, 0.8)',
                            callbacks: {
                                label: function(context) {
                                    let value = context.parsed;
                                    let total = context.dataset.data.reduce((a, b) => a + b, 0);
                                    let percentage = ((value / total) * 100).toFixed(1) + "%";
                                    return ` ${context.label}: ${value.toLocaleString()} Mosques (${percentage})`;
                                }
                            }
                        },
                        datalabels: {
                            color: '#fff',
                            font: { 
                                weight: 'bold', 
                                size: isMobile ? 11 : 14 
                            },
                            formatter: (value, ctxPie) => {
                                let sum = ctxPie.chart.data.datasets[0].data.reduce((a, b) => a + b, 0);
                                let percentage = ((value * 100) / sum).toFixed(1);
                                
                                // Sembunyikan label jika kurang dari 3% supaya tak berserabut
                                return percentage > 3 ? percentage + "%" : null;
                            }
                        }
                    }
                }
            });
        }

        // Mulakan proses render
        renderMosquePieChart();
    });
    </script>
    <?php
    return ob_get_clean();
}

/**
 * SHORTCODE: Business Distribution by Continent Pie Chart (Hardcoded)
 * --------------------------------------------------------------------------
 */
add_shortcode('business_pie_chart_hc', 'business_continent_pie_chart_hc_shortcode');

function business_continent_pie_chart_hc_shortcode() {
    // Data Hardcoded untuk Business (Dikira dari pecahan negara sebelum ini)
    $continent_data = [
        "Asia" => 110599,
        "Europe" => 1661,
        "Oceania" => 963,
        "Africa" => 321,
        "South America" => 175,
        "North America" => 125
    ];

    $labels = array_keys($continent_data);
    $counts = array_values($continent_data);

    $chart_id = 'bizPieChart_' . uniqid();

    ob_start();
    ?>
    <style>
        /* Shared CSS untuk semua Pie Chart di Masjid4All */
        .m4a-pie-wrapper {
            width: 100%;
            max-width: 550px;
            margin: 40px auto;
            padding: 20px;
            background: #fff;
            border-radius: 12px;
            box-shadow: 0 10px 25px rgba(0,0,0,0.05);
            font-family: 'Segoe UI', Roboto, Helvetica, Arial, sans-serif;
            box-sizing: border-box;
        }
        .m4a-pie-canvas-container {
            position: relative;
            width: 100%;
            height: 450px;
        }
        @media (max-width: 768px) {
            .m4a-pie-wrapper { margin: 20px auto; padding: 15px 10px; }
            .m4a-pie-canvas-container { height: 380px; }
        }
    </style>

    <div class="m4a-pie-wrapper">
        <div class="m4a-pie-canvas-container">
            <canvas id="<?php echo $chart_id; ?>"></canvas>
        </div>
    </div>

    <script>
    document.addEventListener("DOMContentLoaded", function() {
        
        // Pemuatan selamat Chart.js
        if (!document.getElementById('chartjs-core-lib')) {
            let scriptChart = document.createElement('script');
            scriptChart.id = 'chartjs-core-lib';
            scriptChart.src = 'https://cdn.jsdelivr.net/npm/chart.js';
            document.head.appendChild(scriptChart);
        }
        if (!document.getElementById('chartjs-datalabels-lib')) {
            let scriptLabels = document.createElement('script');
            scriptLabels.id = 'chartjs-datalabels-lib';
            scriptLabels.src = 'https://cdn.jsdelivr.net/npm/chartjs-plugin-datalabels@2';
            document.head.appendChild(scriptLabels);
        }

        function renderBizPieChart() {
            if (typeof Chart === 'undefined' || typeof ChartDataLabels === 'undefined') {
                setTimeout(renderBizPieChart, 50); 
                return;
            }

            const canvasEl = document.getElementById('<?php echo $chart_id; ?>');
            if (!canvasEl) return;
            const ctxPie = canvasEl.getContext('2d');

            let isMobile = window.innerWidth < 768;

            new Chart(ctxPie, {
                type: 'pie',
                plugins: [ChartDataLabels],
                data: {
                    labels: <?php echo json_encode($labels); ?>,
                    datasets: [{
                        data: <?php echo json_encode($counts); ?>,
                        backgroundColor: [
                            '#125C59', '#3498db', '#e67e22', '#9b59b6', '#f1c40f', '#e74c3c'
                        ],
                        borderWidth: 2,
                        borderColor: '#ffffff'
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false, // KUNCI UTAMA RESPONSIVE MOBILE
                    layout: { padding: isMobile ? 0 : 10 },
                    plugins: {
                        title: {
                            display: true,
                            text: 'Business Distribution Across Continents',
                            color: '#2c3e50',
                            font: { 
                                size: isMobile ? 16 : 20, 
                                weight: 'bold', 
                                family: 'Helvetica Neue' 
                            },
                            padding: { top: 5, bottom: 15 }
                        },
                        legend: {
                            position: 'bottom',
                            labels: { 
                                boxWidth: isMobile ? 12 : 15, 
                                padding: isMobile ? 10 : 20, 
                                font: { size: isMobile ? 11 : 13 } 
                            }
                        },
                        tooltip: {
                            backgroundColor: 'rgba(0, 0, 0, 0.8)',
                            callbacks: {
                                label: function(context) {
                                    let value = context.parsed;
                                    let total = context.dataset.data.reduce((a, b) => a + b, 0);
                                    let percentage = ((value / total) * 100).toFixed(1) + "%";
                                    return ` ${context.label}: ${value.toLocaleString()} Businesses (${percentage})`;
                                }
                            }
                        },
                        datalabels: {
                            color: '#fff',
                            font: { weight: 'bold', size: isMobile ? 11 : 14 },
                            formatter: (value, ctxPie) => {
                                let sum = ctxPie.chart.data.datasets[0].data.reduce((a, b) => a + b, 0);
                                let percentage = ((value * 100) / sum).toFixed(1);
                                return percentage > 3 ? percentage + "%" : null;
                            }
                        }
                    }
                }
            });
        }

        renderBizPieChart();
    });
    </script>
    <?php
    return ob_get_clean();
}

/**
 * SHORTCODE: Member Distribution by Continent Pie Chart (Hardcoded)
 * --------------------------------------------------------------------------
 */
add_shortcode('member_pie_chart_hc', 'member_continent_pie_chart_hc_shortcode');

function member_continent_pie_chart_hc_shortcode() {
    // Data Hardcoded untuk Member
$continent_data = [
    "Asia"          => 10718,
    "Europe"        => 9,
    "North America" => 6,
    "Africa"        => 4,
    "Oceania"       => 3
];

    $labels = array_keys($continent_data);
    $counts = array_values($continent_data);

    $chart_id = 'memberPieChart_' . uniqid();

    ob_start();
    ?>
    <style>
        .m4a-pie-wrapper {
            width: 100%;
            max-width: 550px;
            margin: 40px auto;
            padding: 20px;
            background: #fff;
            border-radius: 12px;
            box-shadow: 0 10px 25px rgba(0,0,0,0.05);
            font-family: 'Segoe UI', Roboto, Helvetica, Arial, sans-serif;
            box-sizing: border-box;
        }
        .m4a-pie-canvas-container {
            position: relative;
            width: 100%;
            height: 450px;
        }
        @media (max-width: 768px) {
            .m4a-pie-wrapper { margin: 20px auto; padding: 15px 10px; }
            .m4a-pie-canvas-container { height: 380px; }
        }
    </style>

    <div class="m4a-pie-wrapper">
        <div class="m4a-pie-canvas-container">
            <canvas id="<?php echo $chart_id; ?>"></canvas>
        </div>
    </div>

    <script>
    document.addEventListener("DOMContentLoaded", function() {
        
        // Pemuatan selamat Chart.js
        if (!document.getElementById('chartjs-core-lib')) {
            let scriptChart = document.createElement('script');
            scriptChart.id = 'chartjs-core-lib';
            scriptChart.src = 'https://cdn.jsdelivr.net/npm/chart.js';
            document.head.appendChild(scriptChart);
        }
        if (!document.getElementById('chartjs-datalabels-lib')) {
            let scriptLabels = document.createElement('script');
            scriptLabels.id = 'chartjs-datalabels-lib';
            scriptLabels.src = 'https://cdn.jsdelivr.net/npm/chartjs-plugin-datalabels@2';
            document.head.appendChild(scriptLabels);
        }

        function renderMemberPieChart() {
            if (typeof Chart === 'undefined' || typeof ChartDataLabels === 'undefined') {
                setTimeout(renderMemberPieChart, 50); 
                return;
            }

            const canvasEl = document.getElementById('<?php echo $chart_id; ?>');
            if (!canvasEl) return;
            const ctxPie = canvasEl.getContext('2d');

            let isMobile = window.innerWidth < 768;

            new Chart(ctxPie, {
                type: 'pie',
                plugins: [ChartDataLabels],
                data: {
                    labels: <?php echo json_encode($labels); ?>,
                    datasets: [{
                        data: <?php echo json_encode($counts); ?>,
                        backgroundColor: [
                            '#125C59', '#95a5a6', '#3498db', '#f1c40f', '#e67e22', '#e74c3c'
                        ],
                        borderWidth: 2,
                        borderColor: '#ffffff'
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false, // KUNCI UTAMA RESPONSIVE MOBILE
                    layout: { padding: isMobile ? 0 : 10 },
                    plugins: {
                        title: {
                            display: true,
                            text: 'Member Distribution Across Continents',
                            color: '#2c3e50',
                            font: { 
                                size: isMobile ? 16 : 20, 
                                weight: 'bold', 
                                family: 'Helvetica Neue' 
                            },
                            padding: { top: 5, bottom: 15 }
                        },
                        legend: {
                            position: 'bottom',
                            labels: { 
                                boxWidth: isMobile ? 12 : 15, 
                                padding: isMobile ? 10 : 20, 
                                font: { size: isMobile ? 11 : 13 } 
                            }
                        },
                        tooltip: {
                            backgroundColor: 'rgba(0, 0, 0, 0.8)',
                            callbacks: {
                                label: function(context) {
                                    let value = context.parsed;
                                    let total = context.dataset.data.reduce((a, b) => a + b, 0);
                                    let percentage = ((value / total) * 100).toFixed(1) + "%";
                                    return ` ${context.label}: ${value.toLocaleString()} Members (${percentage})`;
                                }
                            }
                        },
                        datalabels: {
                            color: '#fff',
                            font: { weight: 'bold', size: isMobile ? 11 : 14 },
                            formatter: (value, ctxPie) => {
                                let sum = ctxPie.chart.data.datasets[0].data.reduce((a, b) => a + b, 0);
                                let percentage = ((value * 100) / sum).toFixed(1);
                                return percentage > 3 ? percentage + "%" : null;
                            }
                        }
                    }
                }
            });
        }

        renderMemberPieChart();
    });
    </script>
    <?php
    return ob_get_clean();
}

/**
 * SHORTCODE: Member Distribution by Countries (Outside Malaysia)
 * --------------------------------------------------------------------------
 */
add_shortcode('member_pie_chart_countries', 'member_countries_pie_chart_shortcode');

function member_countries_pie_chart_shortcode() {
    // Data Hardcoded berdasarkan senarai negara (Luar Malaysia)
    $country_data = [
        "Indonesia" => 10,
        "United Kingdom" => 9,
        "Thailand" => 5,
        "Canada" => 4,
        "Nigeria" => 4,
        "Turkey" => 4,
        "New Zealand" => 3,
        "Belize" => 1,
        "United States" => 1
    ];

    $labels = array_keys($country_data);
    $counts = array_values($country_data);

    $chart_id = 'countryPieChart_' . uniqid();

    ob_start();
    ?>
    <style>
        .m4a-pie-wrapper {
            width: 100%;
            max-width: 550px;
            margin: 40px auto;
            padding: 20px;
            background: #fff;
            border-radius: 12px;
            box-shadow: 0 10px 25px rgba(0,0,0,0.05);
            font-family: 'Segoe UI', Roboto, Helvetica, Arial, sans-serif;
            box-sizing: border-box;
        }
        .m4a-pie-canvas-container {
            position: relative;
            width: 100%;
            height: 450px;
        }
        @media (max-width: 768px) {
            .m4a-pie-wrapper { margin: 20px auto; padding: 15px 10px; }
            .m4a-pie-canvas-container { height: 380px; }
        }
    </style>

    <div class="m4a-pie-wrapper">
        <div class="m4a-pie-canvas-container">
            <canvas id="<?php echo $chart_id; ?>"></canvas>
        </div>
    </div>

    <script>
    document.addEventListener("DOMContentLoaded", function() {
        
        // Pemuatan selamat Chart.js
        if (!document.getElementById('chartjs-core-lib')) {
            let scriptChart = document.createElement('script');
            scriptChart.id = 'chartjs-core-lib';
            scriptChart.src = 'https://cdn.jsdelivr.net/npm/chart.js';
            document.head.appendChild(scriptChart);
        }
        if (!document.getElementById('chartjs-datalabels-lib')) {
            let scriptLabels = document.createElement('script');
            scriptLabels.id = 'chartjs-datalabels-lib';
            scriptLabels.src = 'https://cdn.jsdelivr.net/npm/chartjs-plugin-datalabels@2';
            document.head.appendChild(scriptLabels);
        }

        function renderCountryPieChart() {
            if (typeof Chart === 'undefined' || typeof ChartDataLabels === 'undefined') {
                setTimeout(renderCountryPieChart, 50); 
                return;
            }

            const canvasEl = document.getElementById('<?php echo $chart_id; ?>');
            if (!canvasEl) return;
            const ctxPie = canvasEl.getContext('2d');

            let isMobile = window.innerWidth < 768;

            new Chart(ctxPie, {
                type: 'pie',
                plugins: [ChartDataLabels],
                data: {
                    labels: <?php echo json_encode($labels); ?>,
                    datasets: [{
                        data: <?php echo json_encode($counts); ?>,
                        backgroundColor: [
                            '#125C59', '#26a69a', '#2980b9', '#3498db', '#f1c40f', 
                            '#e67e22', '#e74c3c', '#9b59b6', '#34495e'
                        ],
                        borderWidth: 2,
                        borderColor: '#ffffff'
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    layout: { padding: isMobile ? 0 : 10 },
                    plugins: {
                        title: {
                            display: true,
                            text: 'Users from Other Countries Outside Malaysia',
                            color: '#2c3e50',
                            font: { 
                                size: isMobile ? 16 : 18, 
                                weight: 'bold', 
                                family: 'Helvetica Neue' 
                            },
                            padding: { top: 5, bottom: 15 }
                        },
                        legend: {
                            position: 'bottom',
                            labels: { 
                                boxWidth: isMobile ? 10 : 15, 
                                padding: isMobile ? 8 : 15, 
                                font: { size: isMobile ? 10 : 12 } 
                            }
                        },
                        tooltip: {
                            backgroundColor: 'rgba(0, 0, 0, 0.8)',
                            callbacks: {
                                label: function(context) {
                                    let value = context.parsed;
                                    let total = context.dataset.data.reduce((a, b) => a + b, 0);
                                    let percentage = ((value / total) * 100).toFixed(1) + "%";
                                    return ` ${context.label}: ${value.toLocaleString()} Users (${percentage})`;
                                }
                            }
                        },
                        datalabels: {
                            color: '#fff',
                            font: { weight: 'bold', size: isMobile ? 10 : 12 },
                            formatter: (value, ctxPie) => {
                                let sum = ctxPie.chart.data.datasets[0].data.reduce((a, b) => a + b, 0);
                                let percentage = ((value * 100) / sum).toFixed(1);
                                return percentage > 4 ? percentage + "%" : null;
                            }
                        }
                    }
                }
            });
        }

        renderCountryPieChart();
    });
    </script>
    <?php
    return ob_get_clean();
}

###########################
# state/region level
###########################

//////////////////////////////////
// MOSQUE SUMMARY BY REGION     //
//////////////////////////////////
add_action('plugins_loaded', function () {
    add_shortcode('mosque_summary_continent_hc_dd_region', 'mosque_summary_continent_hc_dd_region_shortcode');
}); 

function mosque_summary_continent_hc_dd_region_shortcode() {
    ob_start();

    // Data Hardcoded Penuh (Disusun ikut Benua)
    $grouped_data = [
        "Asia" => [
            "Indonesia" => 25372, "India" => 15206, "Malaysia" => 8692, "Bangladesh" => 4442, 
            "Iran" => 2738, "Pakistan" => 2425, "Afghanistan" => 2238, "Saudi Arabia" => 2099, 
            "Türkiye" => 1797, "Turkey" => 1179, "Philippines" => 1569, "Azerbaijan" => 1327, 
            "China" => 1133, "United Arab Emirates" => 1078, "Thailand" => 1017, "Kazakhstan" => 966, 
            "Iraq" => 698, "Uzbekistan" => 680, "Myanmar (Burma)" => 577, "Syria" => 403, 
            "Yemen" => 378, "Japan" => 356, "Jordan" => 341, "Sri Lanka" => 335, "Kyrgyzstan" => 331, 
            "Nepal" => 252, "Maldives" => 251, "Bahrain" => 246, "Tajikistan" => 244, "Cambodia" => 239, 
            "South Korea" => 238, "Oman" => 230, "Vietnam" => 215, "Qatar" => 153, "Turkmenistan" => 149, 
            "Lebanon" => 148, "Israel" => 112, "Brunei" => 108, "Kuwait" => 102, "Singapore" => 95, 
            "Taiwan" => 91, "Georgia" => 81, "Hong Kong" => 77, "Mongolia" => 56, "Armenia" => 51, 
            "Laos" => 49, "Myanmar" => 39, "Timor-Leste" => 20, "Palestine" => 19, "North Korea" => 8, 
            "Macao" => 4, "Gaza Strip" => 3
        ],
        "Africa" => [
            "Nigeria" => 1971, "Algeria" => 1417, "Morocco" => 1409, "Ethiopia" => 1067, 
            "South Africa" => 1023, "Tunisia" => 962, "Burkina Faso" => 851, "Tanzania" => 817, 
            "Somalia" => 764, "Sudan" => 756, "Egypt" => 752, "Mali" => 746, "Uganda" => 699, 
            "Kenya" => 669, "Benin" => 665, "Libya" => 608, "Ghana" => 565, "Cameroon" => 548, 
            "Mozambique" => 407, "Côte d'Ivoire" => 400, "Senegal" => 373, "Guinea" => 339, 
            "Mauritania" => 338, "Chad" => 330, "Malawi" => 288, "Niger" => 286, "Madagascar" => 283, 
            "Gambia" => 233, "Democratic Republic of the Congo" => 228, "Burundi" => 227, 
            "Sierra Leone" => 209, "Zambia" => 190, "Zimbabwe" => 174, "Eritrea" => 121, 
            "Comoros" => 130, "Togo" => 106, "Angola" => 95, "Liberia" => 92, "Guinea-Bissau" => 82, 
            "Djibouti" => 77, "Mauritius" => 76, "Rwanda" => 76, "South Sudan" => 75, "Gabon" => 58, 
            "Republic of the Congo" => 48, "Réunion" => 44, "Mayotte" => 43, "Central African Republic" => 41, 
            "Botswana" => 39, "Lesotho" => 30, "Namibia" => 26, "Equatorial Guinea" => 15, "Eswatini" => 14, 
            "Cape Verde" => 8, "São Tomé and Príncipe" => 7, "Seychelles" => 7
        ],
        "Europe" => [
            "France" => 1628, "United Kingdom" => 1533, "Germany" => 1421, "Russia" => 1286, 
            "Bosnia and Herzegovina" => 1113, "Italy" => 892, "Spain" => 579, "Albania" => 517, 
            "Bulgaria" => 493, "Belgium" => 442, "Austria" => 348, "Netherlands" => 304, "Sweden" => 289, 
            "Switzerland" => 187, "Norway" => 186, "North Macedonia" => 158, "Denmark" => 145, 
            "Serbia" => 138, "Portugal" => 117, "Ireland" => 114, "Romania" => 112, "Finland" => 101, 
            "Poland" => 87, "Ukraine" => 78, "Montenegro" => 58, "Cyprus" => 57, "Greece" => 54, 
            "Hungary" => 50, "Kosovo" => 34, "Czechia" => 31, "Belarus" => 27, "Slovenia" => 26, 
            "Lithuania" => 26, "Moldova" => 24, "Croatia" => 23, "Latvia" => 23, "Luxembourg" => 23, 
            "Malta" => 17, "Estonia" => 14, "Slovakia" => 13, "Iceland" => 5, "Jersey" => 3
        ],
        "North America" => [
            "United States" => 3223, "Canada" => 662, "Mexico" => 469, "Trinidad and Tobago" => 88, 
            "Panama" => 26, "Costa Rica" => 22, "Jamaica" => 23, "Guatemala" => 48, "Haiti" => 33, 
            "Cuba" => 20, "Dominican Republic" => 18, "El Salvador" => 18, "Honduras" => 16, 
            "Nicaragua" => 16, "Barbados" => 15, "Puerto Rico" => 13, "Bahamas" => 7, "Bermuda" => 6, 
            "Grenada" => 5, "Aruba" => 5, "Belize" => 4, "Antigua and Barbuda" => 3, "Dominica" => 3, 
            "Saint Kitts and Nevis" => 3, "Martinique" => 3, "Cayman Islands" => 3
        ],
        "South America" => [
            "Brazil" => 452, "Colombia" => 124, "Chile" => 120, "Argentina" => 114, "Peru" => 101, 
            "Venezuela" => 86, "Guyana" => 67, "Suriname" => 61, "Ecuador" => 57, "Bolivia" => 49, 
            "Paraguay" => 28, "Uruguay" => 22, "French Guiana" => 8
        ],
        "Oceania" => [
            "Australia" => 533, "New Zealand" => 130, "Fiji" => 112, "Papua New Guinea" => 14, 
            "Vanuatu" => 7, "Solomon Islands" => 5, "Guam" => 4, "Samoa" => 3, "Tonga" => 3, 
            "Northern Mariana Islands" => 3
        ]
    ];

    // Data Region untuk Drill Down Aras ke-2 (Top 10 Negara)
    $region_data = [
        "Indonesia" => ["Jawa Barat" => 7611, "Jawa Timur" => 6343, "Jawa Tengah" => 5074, "Banten" => 2537, "Sumatera & Wilayah Lain" => 3807],
        "India" => ["Uttar Pradesh" => 3801, "West Bengal" => 2281, "Bihar" => 1824, "Assam" => 1520, "Kerala" => 1520, "Negeri-negeri Lain" => 4260],
        "Malaysia" => ["Selangor" => 1303, "Johor" => 1043, "Perak" => 869, "Kedah" => 869, "Sabah" => 869, "Kelantan" => 695, "Negeri-negeri Lain" => 3044],
        "Bangladesh" => ["Dhaka" => 1332, "Chittagong" => 888, "Rajshahi" => 666, "Khulna" => 666, "Divisyen Lain" => 890],
        "United States" => ["New York" => 483, "California" => 483, "Texas" => 322, "Illinois" => 257, "Michigan" => 257, "Negeri-negeri Lain" => 1421],
        "Iran" => ["Tehran" => 547, "Razavi Khorasan" => 273, "Isfahan" => 273, "Fars" => 273, "Wilayah Lain" => 1372],
        "Pakistan" => ["Punjab" => 1212, "Sindh" => 606, "Khyber Pakhtunkhwa" => 363, "Balochistan & Lain-lain" => 244],
        "Afghanistan" => ["Kabul" => 447, "Herat" => 335, "Balkh" => 268, "Kandahar" => 223, "Provinsi Lain" => 965],
        "Saudi Arabia" => ["Makkah" => 524, "Riyadh" => 524, "Eastern Province" => 314, "Madinah" => 209, "Wilayah Lain" => 528],
        "Nigeria" => ["Kano" => 295, "Kaduna" => 197, "Katsina" => 157, "Sokoto" => 157, "Borno" => 157, "Lagos & Negeri Lain" => 1008]
    ];

    // Mengira jumlah keseluruhan mengikut benua
    $continent_totals = [];
    $total_mosque = 0;
    
    foreach ($grouped_data as $continent => $countries) {
        $sum = array_sum($countries);
        $continent_totals[$continent] = $sum;
        $total_mosque += $sum;
    }

    // Susun benua dari terbesar ke terkecil
    arsort($continent_totals);
    $total_regions = count($continent_totals);

    // Header Info (Teal Theme)
    echo '<div style="padding: 15px; background: #e6f2f1; border-left: 5px solid #125C59; border-radius: 4px; margin-bottom:15px;">';
    echo 'Total Global Mosques: <b>' . number_format(113695) . '</b> in <b>' . $total_regions . ' Regions</b>';
    echo '</div>';

    if (!empty($continent_totals)) {
        echo '<table style="width:100%; border-collapse: collapse; margin-top:10px;">';
        echo '<tr style="background:#f9f9f9; border-bottom: 2px solid #eee;">
                    <th style="text-align: left; padding: 10px;">Region / Continent</th>
                    <th style="text-align: right; padding: 10px;">Total Mosques</th>
                 </tr>';
        
        foreach ($continent_totals as $continent => $count) {
            $tot = number_format($count);
            echo '<tr>';
            echo "<td style='padding: 10px; border-bottom: 1px solid #eee;'>" . esc_html($continent) . "</td>";
            echo "<td style='padding: 10px; border-bottom: 1px solid #eee; text-align: right'>
                    <a href='javascript:void(0);' class='m4a-mosque-drilldown-btn' data-continent='" . esc_attr($continent) . "' style='text-decoration: underline; color: #125C59; font-weight: bold; cursor: pointer;' title='Click to view countries'>{$tot}</a>
                  </td>";
            echo '</tr>';
        }
        echo '</table>';
    }

    // HTML & CSS Modal Mosque
    ?>
    <style>
        .m4a-modal-overlay {
            display: none;
            position: fixed;
            z-index: 99999;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            overflow: auto;
            background-color: rgba(0,0,0,0.6);
        }
        .m4a-modal-content {
            background-color: #fff;
            margin: 5% auto;
            padding: 20px;
            border-radius: 8px;
            width: 90%;
            max-width: 600px;
            position: relative;
            box-shadow: 0 4px 15px rgba(0,0,0,0.2);
        }
        .m4a-close-modal {
            color: #aaa;
            float: right;
            font-size: 28px;
            font-weight: bold;
            cursor: pointer;
            line-height: 1;
        }
        .m4a-close-modal:hover { color: #125C59; text-decoration: none; }
        .m4a-chart-canvas-box {
            width: 100%;
            height: 250px;
            position: relative;
        }
        .m4a-back-btn {
            display: none;
            background: #125C59;
            color: #fff;
            border: none;
            padding: 6px 12px;
            border-radius: 4px;
            cursor: pointer;
            font-size: 13px;
            margin-bottom: 15px;
        }
        .m4a-back-btn:hover { background: #0e4644; }
    </style>

    <div id="m4aMosqueDrilldownModal" class="m4a-modal-overlay">
        <div class="m4a-modal-content">
            <span class="m4a-close-modal m4a-mosque-close">&times;</span>
            <h2 id="m4aMosqueModalTitle" style="margin-top:0; color:#125C59; border-bottom: 2px solid #f1f1f1; padding-bottom: 10px;">Continent Details</h2>
            
            <button id="m4aBackToContinentBtn" class="m4a-back-btn">&larr; Back to Continent</button>

            <div id="m4aMosqueModalBody" class="m4a-modal-body" style="display:flex; flex-direction: column;">
                <div class="m4a-modal-chart-wrap">
                    <h4 id="m4aChartSubtitle" style="margin: 0 0 15px 0; color:#444;">Top 10 Breakdown</h4>
                    <div class="m4a-chart-canvas-box">
                        <canvas id="m4aMosqueDrilldownChart"></canvas>
                    </div>
                </div>
                <div class="m4a-modal-table-wrap" style="margin-top: 20px;">
                    <div id="m4aMosqueModalTableContent"></div>
                </div>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    
    <script>
    document.addEventListener("DOMContentLoaded", function() {
        
        const hardcodedData = <?php echo json_encode($grouped_data); ?>;
        const regionData = <?php echo json_encode($region_data); ?>;
        
        const modal = document.getElementById("m4aMosqueDrilldownModal");
        if (modal && !modal.closest('body > .m4a-modal-overlay')) { 
            document.body.appendChild(modal); 
        }

        const closeBtn = document.querySelector(".m4a-mosque-close");
        const drillBtns = document.querySelectorAll(".m4a-mosque-drilldown-btn");
        const backBtn = document.getElementById("m4aBackToContinentBtn");
        let chartInst = null; 
        let currentContinent = "";

        // Fungsi Render Peringkat Benua (Aras 1)
        function renderContinentView(continent) {
            document.getElementById("m4aMosqueModalTitle").innerText = "Mosques in " + continent;
            document.getElementById("m4aChartSubtitle").innerText = "Top 10 Countries";
            backBtn.style.display = "none";
            
            const continentCountries = hardcodedData[continent] || {};
            const sortedCountries = Object.entries(continentCountries).sort((a, b) => b[1] - a[1]);

            let chartLabels = [];
            let chartData = [];
            
            let tableHtml = '<table style="width:100%; border-collapse: collapse;">';
            tableHtml += '<tr style="background:#f9f9f9; border-bottom: 2px solid #eee;"><th style="text-align: left; padding: 10px;">Country</th><th style="text-align: right; padding: 10px;">Total Mosques</th></tr>';
            
            sortedCountries.forEach((item, index) => {
                let countryName = item[0];
                let count = item[1];
                
                if(index < 10) {
                    chartLabels.push(countryName);
                    chartData.push(count);
                }
                
                // Semak jika negara ini mempunyai data level region (drill-down Aras 2)
                let countryDisplay = countryName;
                if (regionData[countryName]) {
                    countryDisplay = `<a href="javascript:void(0);" class="m4a-drill-to-country" data-country="${countryName}" style="text-decoration: underline; color: #125C59; font-weight: bold;" title="Click for regional breakdown">${countryName}</a>`;
                }

                tableHtml += `<tr>
                    <td style="padding: 10px; border-bottom: 1px solid #eee;">${countryDisplay}</td>
                    <td style="padding: 10px; border-bottom: 1px solid #eee; text-align: right">${count.toLocaleString()}</td>
                </tr>`;
            });
            tableHtml += '</table>';

            document.getElementById("m4aMosqueModalTableContent").innerHTML = tableHtml;
            drawChart(chartLabels, chartData);
        }

        // Fungsi Render Peringkat Negara (Aras 2)
        function renderCountryView(country) {
            document.getElementById("m4aMosqueModalTitle").innerText = "Mosques in " + country;
            document.getElementById("m4aChartSubtitle").innerText = "Regional Breakdown";
            backBtn.style.display = "inline-block"; // Tunjuk butang kembali

            const countryRegions = regionData[country] || {};
            const sortedRegions = Object.entries(countryRegions).sort((a, b) => b[1] - a[1]);

            let chartLabels = [];
            let chartData = [];
            
            let tableHtml = '<table style="width:100%; border-collapse: collapse;">';
            tableHtml += '<tr style="background:#f9f9f9; border-bottom: 2px solid #eee;"><th style="text-align: left; padding: 10px;">Region / State</th><th style="text-align: right; padding: 10px;">Total Mosques</th></tr>';
            
            sortedRegions.forEach((item, index) => {
                let regionName = item[0];
                let count = item[1];
                
                chartLabels.push(regionName);
                chartData.push(count);
                
                tableHtml += `<tr>
                    <td style="padding: 10px; border-bottom: 1px solid #eee;">${regionName}</td>
                    <td style="padding: 10px; border-bottom: 1px solid #eee; text-align: right">${count.toLocaleString()}</td>
                </tr>`;
            });
            tableHtml += '</table>';

            document.getElementById("m4aMosqueModalTableContent").innerHTML = tableHtml;
            drawChart(chartLabels, chartData);
        }

        // Fungsi untuk melukis Chart.js
        function drawChart(labels, data) {
            setTimeout(function() {
                const canvas = document.getElementById("m4aMosqueDrilldownChart");
                if(!canvas) return;
                
                const ctx = canvas.getContext("2d");
                if(chartInst != null) { chartInst.destroy(); }

                chartInst = new Chart(ctx, {
                    type: 'pie',
                    data: {
                        labels: labels,
                        datasets: [{
                            data: data,
                            backgroundColor: ['#125C59', '#2ecc71', '#3498db', '#9b59b6', '#f1c40f', '#e67e22', '#e74c3c', '#95a5a6', '#34495e', '#FF6384'],
                            borderWidth: 2, borderColor: '#ffffff'
                        }]
                    },
                    options: { responsive: true, maintainAspectRatio: false, plugins: { datalabels: { display: false }, legend: { position: 'right', labels: { boxWidth: 12, font: {size: 11} } } } }
                });
            }, 100); // Tunggu modal render
        }

        // Event Listener: Klik dari Table Utama (Aras 1)
        drillBtns.forEach(btn => {
            btn.addEventListener("click", function() {
                currentContinent = this.getAttribute("data-continent");
                modal.style.display = "block"; 
                renderContinentView(currentContinent);
            });
        });

        // Event Listener: Event Delegation untuk klik pautan negara (Aras 2)
        document.getElementById("m4aMosqueModalTableContent").addEventListener("click", function(e) {
            if (e.target && e.target.classList.contains("m4a-drill-to-country")) {
                const country = e.target.getAttribute("data-country");
                renderCountryView(country);
            }
        });

        // Event Listener: Butang Kembali (Aras 2 balik ke Aras 1)
        backBtn.addEventListener("click", function() {
            renderContinentView(currentContinent);
        });

        // Modal Close Setup
        const closeModal = function() { modal.style.display = "none"; };
        if(closeBtn) closeBtn.onclick = closeModal;
        window.addEventListener('click', function(e) { if (e.target == modal) closeModal(); });
    });
    </script>
    <?php
    return ob_get_clean();
}