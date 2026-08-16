<?php
/**
 * Shortcode to delete duplicate records in wp_jet_cct_web table.
 * Primary key column: _ID
 * Keeps the row with the smallest _ID for each unique URL.
 * Usage: [delete_duplicate_websites]
 */
function cct_delete_duplicate_records_shortcode() {
    // Admin only
    if ( ! current_user_can( 'manage_options' ) ) {
        return '<p style="color:red;">You do not have permission to run this action.</p>';
    }

    global $wpdb;
    $table_name = $wpdb->prefix . 'jet_cct_web';
    $primary_key = '_ID'; // Explicitly set to your column name

    // Check if table exists
    if ( $wpdb->get_var( $wpdb->prepare( "SHOW TABLES LIKE %s", $table_name ) ) !== $table_name ) {
        return '<p style="color:red;">Table ' . esc_html( $table_name ) . ' does not exist.</p>';
    }

    // Verify the primary key column exists
    $column_exists = $wpdb->get_var( $wpdb->prepare( "SHOW COLUMNS FROM $table_name LIKE %s", $primary_key ) );
    if ( ! $column_exists ) {
        return '<p style="color:red;">Primary key column `_ID` not found in ' . esc_html( $table_name ) . '.</p>';
    }

    // Escape the column name (backticks for underscore)
    $pk_escaped = "`_ID`";

    // Delete duplicates: keep the row with smallest _ID per unique URL
    // Derived table workaround for MySQL restriction
    $sql = "
        DELETE FROM {$table_name}
        WHERE {$pk_escaped} NOT IN (
            SELECT min_id FROM (
                SELECT MIN({$pk_escaped}) as min_id
                FROM {$table_name}
                GROUP BY name
            ) AS temp
        )
    ";

    $result = $wpdb->query( $sql );

    if ( $result === false ) {
        return '<p style="color:red;">Database error: ' . esc_html( $wpdb->last_error ) . '</p>';
    }

    if ( $result > 0 ) {
        return '<p style="color:green;">✓ Successfully deleted ' . intval( $result ) . ' duplicate record(s) from ' . esc_html( $table_name ) . '. Kept one row per unique URL (based on smallest `_ID`).</p>';
    } else {
        return '<p style="color:blue;">ℹ No duplicate records found (based on <strong>url</strong> column).</p>';
    }
}
add_shortcode( 'delete_duplicate_websites', 'cct_delete_duplicate_records_shortcode' );

add_shortcode('sync_cct_url_to_cpt_meta', 'sync_cct_url_to_web_postmeta');

function sync_cct_url_to_web_postmeta() {
    global $wpdb;

    // 1. Define the CCT table name
    $table_name = $wpdb->prefix . 'jet_cct_web';

    // Safety Check: Ensure the CCT table exists
    if ( $wpdb->get_var( $wpdb->prepare( "SHOW TABLES LIKE %s", $table_name ) ) !== $table_name ) {
        return "Error: Table '{$table_name}' does not exist.";
    }

    // 2. Fetch the required columns from the CCT table
    // Fetching _ID, url, and cct_single_post_id
    $results = $wpdb->get_results( "SELECT _ID, url, cct_single_post_id FROM {$table_name}", ARRAY_A );

    if ( empty( $results ) ) {
        return "No records found in table '{$table_name}'.";
    }

    $updated_count = 0;

    // 3. Loop through the CCT items
    foreach ( $results as $row ) {
        $url     = trim( $row['url'] );
        $post_id = isset( $row['cct_single_post_id'] ) ? intval( $row['cct_single_post_id'] ) : 0;

        // Skip if there's no URL or if no post ID is linked to this row
        if ( empty( $url ) || $post_id <= 0 ) {
            continue;
        }

        // 4. Update the Post Meta for the custom post
        // This will update the meta key 'url' for the given $post_id.
        // If the meta key 'url' doesn't exist yet, it will create it automatically.
        $meta_updated = update_post_meta( $post_id, 'url', esc_url_raw( $url ) );

        // update_post_meta returns true on success, or false if the value was identical to the old one
        if ( $meta_updated !== false ) {
            $updated_count++;
        }
    }

    return "Process complete. Updated 'url' postmeta for {$updated_count} posts.";
}

add_shortcode('sync_cct_to_cpt_web', 'migrate_cct_to_cpt_web_direct');

function migrate_cct_to_cpt_web_direct() {
    global $wpdb;

    // 1. Define the CCT table name
    $table_name = $wpdb->prefix . 'jet_cct_web';

    // Safety Check: Ensure the CCT table exists
    if ( $wpdb->get_var( $wpdb->prepare( "SHOW TABLES LIKE %s", $table_name ) ) !== $table_name ) {
        return "Error: Table '{$table_name}' does not exist.";
    }

    // 2. Fetch the required data from the CCT table
    // Fetching post_id as well to prevent duplicating posts that are already synced
    $results = $wpdb->get_results( "SELECT _ID, name, introduction FROM {$table_name}", ARRAY_A );

    if ( empty( $results ) ) {
        return "No records found in table '{$table_name}'.";
    }

    $created_count = 0;

    // 3. Loop through the CCT items
    foreach ( $results as $row ) {
        $cct_id       = $row['_ID'];
        $name         = trim( $row['name'] );
        $introduction = trim( $row['introduction'] );
        $existing_pid = 0; //isset( $row['post_id'] ) ? intval( $row['post_id'] ) : 0;

        // Skip if the name is empty OR if this row already has a post assigned to it
        if ( empty( $name ) || $existing_pid > 0 ) {
            continue;
        }

        // 4. Set up the Custom Post Type arguments
        $post_args = array(
            'post_title'   => $name,
            'post_content' => $introduction,
            'post_status'  => 'publish',
            'post_type'    => 'web', // Your Custom Post Type slug
            'meta_input'   => array(
                'item_id' => $cct_id // Save CCT _ID as post meta
            )
        );

        // Insert the post into the WordPress database
        $new_post_id = wp_insert_post( $post_args );

        // 5. If post creation is successful, update the CCT row with the post_id
        if ( ! is_wp_error( $new_post_id ) && $new_post_id > 0 ) {
            
            $updated = $wpdb->update(
                $table_name,
                array( 'cct_single_post_id' => $new_post_id ), // Data to update
                array( '_ID'     => $cct_id ),       // WHERE clause
                array( '%d' ),                      // Data format
                array( '%d' )                       // WHERE format
            );

            if ( $updated !== false ) {
                $created_count++;
            }
        }
    }

    return "Process completed. Successfully created {$created_count} posts in CPT 'web' and linked them back to CCT.";
}

add_shortcode('update_cct_barakah_domains', 'sync_barakah_cct_domains_direct');

function sync_barakah_cct_domains_direct() {
    global $wpdb;
    
    // 1. Define the custom content type table name dynamically
    $table_name = $wpdb->prefix . 'jet_cct_web';

    // Check if the table actually exists to prevent fatal errors
    if ( $wpdb->get_var( $wpdb->prepare( "SHOW TABLES LIKE %s", $table_name ) ) !== $table_name ) {
        return "Error: Table '{$table_name}' does not exist in this database.";
    }

    // 2. Fetch only the necessary columns (_ID, url, name) from the table
    // Change 'url' or 'name' if your CCT column slugs are named differently
    $results = $wpdb->get_results( "SELECT _ID, url, name FROM {$table_name}", ARRAY_A );

    if ( empty( $results ) ) {
        return "No records found in table '{$table_name}'.";
    }

    $updated_count = 0;

    // 3. Loop through the rows
    foreach ( $results as $row ) {
        $id       = $row['_ID'];
        $url      = trim( $row['url'] );
        $old_name = trim( $row['name'] );

        if ( empty( $url ) ) {
            continue;
        }

        // 4. Clean and parse the URL to extract the core domain
        // Ensure the URL has a scheme so parse_url treats it correctly
        if ( ! preg_match( "~^https?://~i", $url ) ) {
            $url = "http://" . $url;
        }

        $parsed_url = parse_url( $url );
        $domain     = isset( $parsed_url['host'] ) ? strtolower( $parsed_url['host'] ) : '';

        // Strip "www." if it exists at the front of the domain
        if ( strpos( $domain, 'www.' ) === 0 ) {
            $domain = substr( $domain, 4 );
        }

        // 5. If a domain was successfully parsed and it differs from the current name, update it
        if ( ! empty( $domain ) && $old_name !== $domain ) {
            
            $updated = $wpdb->update(
                $table_name,
                array( 'name' => $domain ),  // Data to update
                array( '_ID'  => $id ),      // WHERE clause
                array( '%s' ),               // Data format (string)
                array( '%d' )                // WHERE format (integer)
            );

            if ( $updated !== false ) {
                $updated_count++;
            }
        }
    }

    return "Database update complete. Successfully updated {$updated_count} rows in '{$table_name}'.";
}

add_shortcode('jet_csv_importer', 'jet_cct_one_time_importer');
function jet_cct_one_time_importer() {
    if (!current_user_can('manage_options')) {
        return '<p>Permission denied.</p>';
    }

    global $wpdb;
    $table_name = 'wp_jet_cct_web';
    $message = '';

    // Check if form was submitted
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        // Verify nonce
        if (!isset($_POST['_wpnonce']) || !wp_verify_nonce($_POST['_wpnonce'], 'jet_one_time_import')) {
            $message = '<div style="color:red;">Security check failed. Please refresh the page and try again.</div>';
        } 
        // Check if file was uploaded
        elseif (!isset($_FILES['csv_file']) || $_FILES['csv_file']['error'] !== UPLOAD_ERR_OK) {
            $upload_error = isset($_FILES['csv_file']['error']) ? $_FILES['csv_file']['error'] : 'No file';
            $message = '<div style="color:red;">File upload error. Code: ' . $upload_error . '. Please ensure the file is under ' . ini_get('upload_max_filesize') . '.</div>';
        }
        else {
            $file = $_FILES['csv_file']['tmp_name'];
            if (($handle = fopen($file, 'r')) !== false) {
                $skip_header = isset($_POST['has_header']);
                if ($skip_header) fgetcsv($handle);

                $inserted = 0;
                $errors = 0;

                while (($data = fgetcsv($handle, 1000, ',')) !== false) {
                    // Expecting columns: Url, Category, country, introduction
                    if (count($data) < 4) {
                        $errors++;
                        continue;
                    }

                    $url = esc_url_raw($data[0]);
                    $category = sanitize_text_field($data[1]);
                    $country = sanitize_text_field($data[2]);
                    $introduction = sanitize_textarea_field($data[3]);

                    if (empty($url)) {
                        $errors++;
                        continue;
                    }

                    $result = $wpdb->insert(
                        $table_name,
                        [
                            'Url' => $url,
                            'Category' => $category,
                            'country' => $country,
                            'introduction' => $introduction
                        ],
                        ['%s', '%s', '%s', '%s']
                    );

                    if ($result) {
                        $inserted++;
                    } else {
                        $errors++;
                    }
                }
                fclose($handle);
                $message = "<div style='color:green;'>✅ Import complete: $inserted inserted, $errors skipped/errors.</div>";
            } else {
                $message = '<div style="color:red;">Could not open the uploaded file.</div>';
            }
        }
    }

    ob_start();
    ?>
    <div style="max-width:600px; margin:20px 0; padding:20px; border:1px solid #ccc; background:#f9f9f9;">
        <h3>One‑Time CSV Importer → wp_jet_cct_web</h3>
        <?php echo $message; ?>
        <form method="post" enctype="multipart/form-data">
            <?php wp_nonce_field('jet_one_time_import', '_wpnonce'); ?>
            <p>
                <label><strong>CSV file (columns: Url, Category, country, introduction)</strong></label><br>
                <input type="file" name="csv_file" accept=".csv" required>
            </p>
            <p>
                <label>
                    <input type="checkbox" name="has_header" value="1"> My CSV has a header row (skip first line)
                </label>
            </p>
            <p>
                <button type="submit" style="background:#2271b1; color:white; padding:8px 16px; border:none; cursor:pointer;">Upload & Import</button>
            </p>
        </form>
        <p style="font-size:13px; color:#555;">Example CSV row (order: Url, Category, country, introduction):<br>
        <code>https://example.com/masjid, Mosques, Malaysia, "Beautiful mosque in the city"</code></p>
        <p style="color:red;"><strong>After import, remove this shortcode and the page.</strong></p>
    </div>
    <?php
    return ob_get_clean();
}


add_shortcode('empty_meta_users', 'wp_display_empty_meta_users');

function wp_display_empty_meta_users() {
    // Query users where item_id OR user_phone is empty or doesn't exist
    $args = array(
        'meta_query' => array(
            'relation' => 'OR',
            // Check if item_id is an empty string
            array(
                'key'     => 'item_id',
                'value'   => '',
                'compare' => '='
            ),
            // Check if item_id does not exist at all
            array(
                'key'     => 'item_id',
                'compare' => 'NOT EXISTS'
            ),
            // Check if user_phone is an empty string
            array(
                'key'     => 'user_phone',
                'value'   => '',
                'compare' => '='
            ),
            // Check if user_phone does not exist at all
            array(
                'key'     => 'user_phone',
                'compare' => 'NOT EXISTS'
            ),
        ),
        'number' => -1, // Retrieve all matching users
    );

    $user_query = new WP_User_Query($args);
    $users = $user_query->get_results();

    // Start building the HTML output safely using an output buffer
    ob_start();

    if (!empty($users)) {
        echo '<table class="wp-empty-meta-users-table" style="width:100%; border-collapse: collapse; margin: 20px 0;">';
        echo '<thead>';
        echo '<tr style="background-color: #f7f7f7; text-align: left;">';
        echo '<th style="padding: 10px; border: 1px solid #ddd;">User ID</th>';
        echo '<th style="padding: 10px; border: 1px solid #ddd;">First Name</th>';
        echo '<th style="padding: 10px; border: 1px solid #ddd;">Email</th>';
        echo '</tr>';
        echo '</thead>';
        echo '<tbody>';

        foreach ($users as $user) {
            echo '<tr>';
            echo '<td style="padding: 10px; border: 1px solid #ddd;">' . esc_html($user->ID) . '</td>';
            echo '<td style="padding: 10px; border: 1px solid #ddd;">' . esc_html($user->first_name ? $user->first_name : 'N/A') . '</td>';
            echo '<td style="padding: 10px; border: 1px solid #ddd;">' . esc_html($user->user_email) . '</td>';
            echo '</tr>';
        }

        echo '</tbody>';
        echo '</table>';
    } else {
        echo '<p>No users found with empty item_id or user_phone fields.</p>';
    }

    return ob_get_clean();
}

/**
 * Download handler
 */
function mask_person_name($name) {
    $name = html_entity_decode($name, ENT_QUOTES | ENT_HTML5, 'UTF-8');

    // keep letters/numbers/spaces only
    $name = preg_replace('/[^\p{L}\p{N}\s]/u', '', $name);

    $len = mb_strlen($name);

    if ($len <= 3) {
        return $name;
    }

    $result = mb_substr($name, 0, 3);
    $pos = 3;
    $mask = true;

    while ($pos < $len) {
        if ($mask) {
            $chunk = min(3, $len - $pos);
            $result .= str_repeat('*', $chunk);
        } else {
            $chunk = min(2, $len - $pos);
            $result .= mb_substr($name, $pos, $chunk);
        }

        $pos += $chunk;
        $mask = !$mask;
    }

    return $result;
}


function xlsx_cell($value) {
    $value = (string) $value;

    // remove invalid XML chars
    $value = preg_replace('/[^\P{C}\t\n\r]/u', '', $value);

    // XML escape
    $value = str_replace(
        ['&', '<', '>', '"', "'"],
        ['&amp;', '&lt;', '&gt;', '&quot;', '&apos;'],
        $value
    );

    return '<c t="inlineStr"><is><t xml:space="preserve">' . $value . '</t></is></c>';
}

add_action('template_redirect', function () {
    if (!isset($_GET['download_cradle_xlsx'])) {
        return;
    }

    global $wpdb;
    $table = $wpdb->prefix . 'jet_cct_cradle_user';

    $rows = $wpdb->get_results("
        SELECT 
            name,
            phone2,
            country,
            DATE_FORMAT(registered, '%d/%m/%Y') as registered
        FROM {$table}
        WHERE
            name IS NOT NULL
            AND name != ''
            AND registered IS NOT NULL
            AND registered != ''
        ORDER BY registered ASC
    ", ARRAY_A);

    if (empty($rows)) {
        wp_die('No records found.');
    }

    if (!class_exists('ZipArchive')) {
        wp_die('ZipArchive is not enabled.');
    }

    $tmp = wp_tempnam('cradle_xlsx');
    $zip = new ZipArchive();

    if ($zip->open($tmp, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
        wp_die('Unable to create XLSX.');
    }

    $zip->addFromString('[Content_Types].xml', '<?xml version="1.0" encoding="UTF-8"?>
<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">
    <Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>
    <Default Extension="xml" ContentType="application/xml"/>
    <Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>
    <Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>
    <Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>
</Types>');

    $zip->addFromString('_rels/.rels', '<?xml version="1.0" encoding="UTF-8"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
    <Relationship Id="rId1"
        Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument"
        Target="xl/workbook.xml"/>
</Relationships>');

    $zip->addFromString('xl/workbook.xml', '<?xml version="1.0" encoding="UTF-8"?>
<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"
 xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">
    <sheets>
        <sheet name="Cradle Users" sheetId="1" r:id="rId1"/>
    </sheets>
</workbook>');

    $zip->addFromString('xl/_rels/workbook.xml.rels', '<?xml version="1.0" encoding="UTF-8"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
    <Relationship Id="rId1"
        Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet"
        Target="worksheets/sheet1.xml"/>
    <Relationship Id="rId2"
        Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles"
        Target="styles.xml"/>
</Relationships>');

    $zip->addFromString('xl/styles.xml', '<?xml version="1.0" encoding="UTF-8"?>
<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">
    <fonts count="1"><font><sz val="11"/><name val="Calibri"/></font></fonts>
    <fills count="1"><fill><patternFill patternType="none"/></fill></fills>
    <borders count="1"><border/></borders>
    <cellStyleXfs count="1"><xf/></cellStyleXfs>
    <cellXfs count="1"><xf numFmtId="0"/></cellXfs>
</styleSheet>');

    $sheet = '<?xml version="1.0" encoding="UTF-8"?>
<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">
<sheetData>';

    $sheet .= '<row r="1">';
    $sheet .= xlsx_cell('Name');
    $sheet .= xlsx_cell('Phone');
    $sheet .= xlsx_cell('Country');
    $sheet .= xlsx_cell('Registered');
    $sheet .= '</row>';

    $rowNum = 2;

    foreach ($rows as $row) {
        $sheet .= '<row r="' . $rowNum . '">';
        $sheet .= xlsx_cell($row['name']);
        $sheet .= xlsx_cell($row['phone2']);
        $sheet .= xlsx_cell($row['country']);
        $sheet .= xlsx_cell($row['registered']);
        $sheet .= '</row>';
        $rowNum++;
    }

    $sheet .= '</sheetData></worksheet>';

    $zip->addFromString('xl/worksheets/sheet1.xml', $sheet);
    $zip->close();

    if (ob_get_length()) {
        ob_end_clean();
    }

    $filename = 'cradle_users_' . date('Ymd_His') . '.xlsx';

    nocache_headers();
    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Content-Length: ' . filesize($tmp));

    readfile($tmp);
    unlink($tmp);
    exit;
});

add_shortcode('export_cradle_excel', function () {
    $url = add_query_arg('download_cradle_xlsx', 1, home_url('/'));
    return '<a href="' . esc_url($url) . '" class="button">Download XLSX Test File</a>';
});

/**
 * Shortcode: [cradle_registered_summary]
 */
add_shortcode('cradle_registered_summary', function() {
    global $wpdb;

    $table = $wpdb->prefix . 'jet_cct_cradle_user';

    $results = $wpdb->get_results("
        SELECT 
            DATE_FORMAT(registered, '%Y-%m') as reg_month,
            COUNT(*) as total
        FROM {$table}
        WHERE registered IS NOT NULL
          AND registered != ''
        GROUP BY reg_month
        ORDER BY reg_month ASC
    ");
 
    if (empty($results)) {
        return '<p>No Malaysia records found.</p>';
    }

    $month_names = [
        '01' => 'Jan',
        '02' => 'Feb',
        '03' => 'Mac',
        '04' => 'Apr',
        '05' => 'Mei',
        '06' => 'Jun',
        '07' => 'Jul',
        '08' => 'Ogos',
        '09' => 'Sep',
        '10' => 'Okt',
        '11' => 'Nov',
        '12' => 'Dis',
    ];

    $html = '<table style="width:100%; border-collapse:collapse;">';
    $html .= '<thead>';
    $html .= '<tr>
                <th style="border:1px solid #ddd; padding:8px; text-align:left;">Registered</th>
                <th style="border:1px solid #ddd; padding:8px; text-align:left;">No of users</th>
              </tr>';
    $html .= '</thead><tbody>';

    foreach ($results as $row) {
        list($year, $month) = explode('-', $row->reg_month);
        $label = $month_names[$month] . ' ' . $year;

        $html .= '<tr>';
        $html .= '<td style="border:1px solid #ddd; padding:8px;">' . esc_html($label) . '</td>';
        $html .= '<td style="border:1px solid #ddd; padding:8px;">' . number_format($row->total) . ' users</td>';
        $html .= '</tr>';
    }

    $html .= '</tbody></table>';

    return $html;
});
 
add_shortcode('update_cradle_registered_dates', function() {
    global $wpdb;

    $table = $wpdb->prefix . 'jet_cct_cradle_user';

    $records = $wpdb->get_results("
        SELECT _ID, registered
        FROM {$table}
        WHERE
            country IS NOT NULL
            AND country != ''
            AND country != 'Malaysia'
            AND name IS NOT NULL
            AND name != ''
        ORDER BY _ID ASC
    ");

    if (empty($records)) {
        return 'No matching records found.';
    }

    $updated = 0;
    $failed = 0;
    $debug = [];

    foreach ($records as $record) {
        $random_date = sprintf('2026-04-%02d', rand(1, 30));

        $result = $wpdb->update(
            $table,
            ['registered' => $random_date],
            ['_ID' => $record->_ID],
            ['%s'],
            ['%d']
        );

        if ($result === false) {
            $failed++;
            $debug[] = "ID {$record->_ID}: ERROR " . $wpdb->last_error;
        } elseif ($result === 0) {
            $debug[] = "ID {$record->_ID}: No change";
        } else {
            $updated++;
            $debug[] = "ID {$record->_ID}: Updated to {$random_date}";
        }
    }

    return '<pre>'
        . "Updated: {$updated}\n"
        . "Failed: {$failed}\n\n"
        . implode("\n", array_slice($debug, 0, 20))
        . '</pre>';
});

add_shortcode('fix_cradle_privacy', function () {
    global $wpdb;

    $table = $wpdb->prefix . 'jet_cct_cradle_user';
    $batch = 1000;
    $offset = isset($_GET['offset']) ? intval($_GET['offset']) : 0;

    $rows = $wpdb->get_results($wpdb->prepare("
        SELECT _ID, phone, name
        FROM {$table}
        WHERE 
            registered IS NOT NULL
            AND registered != ''
        LIMIT %d OFFSET %d
    ", $batch, $offset));

    if (empty($rows)) {
        return "<p>✅ Done. All non-Malaysia records processed.</p>";
    }

    
    foreach ($rows as $row) {
        $update = [];

        // PHONE
        if (!empty($row->phone)) {
            $clean = preg_replace('/\D+/', '', $row->phone);

            if (!empty($clean)) {
                if (strlen($clean) > 7) {
                    $masked = substr($clean, 0, 4)
                            . str_repeat('#', max(1, strlen($clean) - 7))
                            . substr($clean, -3);
                } else {
                    $masked = $clean;
                }

                $update['phone'] = $clean;
                $update['phone2'] = $masked;
            }
        }

        // NAME
        if (!empty($row->name)) {
            $update['name2'] = mask_person_name($row->name);
        }

        if (!empty($update)) {
            $wpdb->update(
                $table,
                $update,
                ['_ID' => $row->_ID]
            );
        }
    }

    $next = $offset + $batch;
    $url = add_query_arg('offset', $next);

    return "
        <p>Processed " . count($rows) . " non-Malaysia records...</p>
        <script>
            setTimeout(function() {
                window.location.href = '{$url}';
            }, 1000);
        </script>
    ";
});

/**
 * Shortcode: [fix_cradle_phones]
 * Batch update jet-cct-cradle_user phone -> phone2
 */
add_shortcode('fix_cradle_phones', function () {
    global $wpdb;

    $table = $wpdb->prefix . 'jet_cct_cradle_user';
    $batch = 1000;

    // get offset
    $offset = isset($_GET['offset']) ? intval($_GET['offset']) : 0;

    // fetch batch
    $rows = $wpdb->get_results($wpdb->prepare("
        SELECT _ID, phone
        FROM {$table}
        WHERE phone IS NOT NULL
        AND phone != ''
        LIMIT %d OFFSET %d
    ", $batch, $offset));

    if (empty($rows)) {
        return "<p>✅ Done. All records processed.</p>";
    }

    foreach ($rows as $row) {
        // 1. remove all non-numeric chars
        $clean = preg_replace('/\D+/', '', $row->phone);

        if (empty($clean)) {
            continue;
        }

        // 2. hide middle digits
        if (strlen($clean) > 7) {
            $masked = substr($clean, 0, 4)
                    . str_repeat('*', max(1, strlen($clean) - 7))
                    . substr($clean, -3);
        } else {
            $masked = $clean;
        }

        // 3. update phone + phone2
        $wpdb->update(
            $table,
            [
                'phone'  => $clean,
                'phone2' => $masked
            ],
            ['_ID' => $row->_ID]
        );
    }

    $next = $offset + $batch;
    $url = add_query_arg('offset', $next);

    return "
        <p>Processed " . count($rows) . " records...</p>
        <script>
            setTimeout(function() {
                window.location.href = '{$url}';
            }, 1000);
        </script>
    ";
});

/**
 * Shortcode: [cradle_user_summary]
 *
 * Show total users grouped by country
 */

add_shortcode('cradle_user_summary', function () {

    if ( ! current_user_can('manage_options') ) {
        return 'Unauthorized';
    }

    global $wpdb;

    $table = $wpdb->prefix . 'jet_cct_cradle_user';

    $results = $wpdb->get_results("
        SELECT 
            country,
            COUNT(*) as total_users
        FROM {$table}
        WHERE country IS NOT NULL
        AND country != ''
        GROUP BY country
        ORDER BY total_users DESC
    ");

    if ( empty($results) ) {
        return 'No records found.';
    }

    $output = '';

    $output .= '<table style="width:100%; border-collapse:collapse;">';
    $output .= '
        <tr>
            <th style="border:1px solid #ccc;padding:8px;text-align:left;">Country</th>
            <th style="border:1px solid #ccc;padding:8px;text-align:right;">Users</th>
        </tr>
    ';

    $grand_total = 0;

    foreach ($results as $row) {

        $country = esc_html($row->country);
        $total   = number_format($row->total_users);

        $grand_total += $row->total_users;

        $output .= "
            <tr>
                <td style='border:1px solid #ccc;padding:8px;'>{$country}</td>
                <td style='border:1px solid #ccc;padding:8px;text-align:right;'>{$total}</td>
            </tr>
        ";
    }

    $output .= "
        <tr style='font-weight:bold;background:#f5f5f5;'>
            <td style='border:1px solid #ccc;padding:8px;'>TOTAL</td>
            <td style='border:1px solid #ccc;padding:8px;text-align:right;'>
                " . number_format($grand_total) . "
            </td>
        </tr>
    ";

    $output .= '</table>';

    return $output;
});

/**
 * Shortcode: [sync_cradle_users]
 * 
 * Loops through jet-cct-business records where:
 * - country IS NOT empty
 * - phone IS NOT empty
 * 
 * Then creates new record in:
 * - jet-cct-cradle_user
 * 
 * Fields:
 * - phone
 * - country
 * 
 * IMPORTANT:
 * - Designed for large dataset (~40K records)
 * - Uses batch processing
 * - Avoids duplicate phone numbers
 */

add_shortcode('sync_cradle_users', function () {

    if ( ! current_user_can('manage_options') ) {
        return 'Unauthorized';
    }

    global $wpdb;

    $business_table = $wpdb->prefix . 'jet_cct_business';
    $cradle_table  = $wpdb->prefix . 'jet_cct_cradle_user';

    $batch_size = 500;
    $offset     = isset($_GET['offset']) ? intval($_GET['offset']) : 0;

    // Get batch
    $records = $wpdb->get_results(
        $wpdb->prepare("
            SELECT phone, country
            FROM {$business_table}
            WHERE country IS NOT NULL
            AND country != ''
            AND phone IS NOT NULL
            AND phone != ''
            LIMIT %d OFFSET %d
        ", $batch_size, $offset)
    );

    if ( empty($records) ) {
        return "Done syncing.";
    }

    $inserted = 0;
    $skipped  = 0;

    foreach ($records as $record) {

        $phone   = trim($record->phone);
        $country = trim($record->country);

        // Optional normalization
        $phone = preg_replace('/\s+/', '', $phone);

        if ( empty($phone) || empty($country) ) {
            continue;
        }

        // Check duplicate by phone
        $exists = $wpdb->get_var(
            $wpdb->prepare("
                SELECT _ID
                FROM {$cradle_table}
                WHERE phone = %s
                LIMIT 1
            ", $phone)
        );

        if ($exists) {
            $skipped++;
            continue;
        }

        // Insert new record
        $result = $wpdb->insert(
            $cradle_table,
            [
                'phone'   => $phone,
                'country' => $country,
            ],
            [
                '%s',
                '%s',
            ]
        );

        if ($result) {
            $inserted++;
        }
    }

    $next_offset = $offset + $batch_size;

    $next_url = add_query_arg([
        'offset' => $next_offset
    ]);

    $output  = "<div style='padding:20px;border:1px solid #ddd'>";
    $output .= "<h3>Batch Processed</h3>";
    $output .= "<p>Offset: {$offset}</p>";
    $output .= "<p>Inserted: {$inserted}</p>";
    $output .= "<p>Skipped: {$skipped}</p>";
    $output .= "<p><a href='{$next_url}'>Process Next Batch</a></p>";
    $output .= "</div>";

    return $output;
});


add_shortcode('update_countries', 'update_countries_shortcode');
 
function update_countries_shortcode() {
    global $wpdb;
    echo 'Update Countries';
    $listing_table   = $wpdb->prefix . 'jet_cct_business';
    $country_table  = $wpdb->prefix . 'jet_cct_countries';

    $limit  = 10000;
    $offset = 30000;
    
    // Get mosques batch
    $rows = $wpdb->get_results(
        $wpdb->prepare(
            "SELECT _ID, address, country 
             FROM {$listing_table}
             LIMIT %d OFFSET %d",
            $limit,
            $offset
        )
    );

    if (empty($rows)) return 'No records found';

    $ret = '';
    $cnt = 0;
    foreach ($rows as $row) {

        // Skip if already has country
        //if (!empty($row->country)) continue;

        if (empty($row->address)) continue;

        // Extract country (last part after comma)
        $parts = explode(',', $row->address);
        $country = trim(end($parts));
        $address = $row->address;
        if (empty($country)) continue;

        // Check if country exists
        $exists = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM {$country_table} WHERE country = %s",
                $country
            )
        );

        if ($exists) {
            // Update country
            $wpdb->update(
                $listing_table,
                ['country' => $country],
                ['_ID' => $row->_ID],
                ['%s'],
                ['%d']
            );
        }
        $ret .= $address . ' - ' . $country . '<br>';
        $cnt = $cnt + 1;
    }

    return 'Batch processed<br>' . $cnt;
}
 
/*
FORM


SHORTCODE
- affiliate_page
- affiliate_qr


FUNCTION



*/

// SHORTCODE /////////////////////////////////////////////

// Display Content
add_shortcode('affiliate_page', 'affiliate_page_shortcode');
function affiliate_page_shortcode() {

    $current_user = wp_get_current_user();  
    $user_id = $current_user->ID;
 
    //$affiliate = get_field_from_userid($user_id, 'affiliate_url');
    if (!$affiliate) {
        echo '<style>.affiliate_buttons { display: none !important; }</style>';
        return;
    }else{
        echo '<style>.btn_create_page { display: none !important; }</style>';
        echo '<style>.member_intro { display: none !important; }</style>';
    }

    $content = affiliate_content($user_id);
    
    return $content;
}

function affiliate_contentx($user_id){
    $name  = get_field_from_userid($user_id, 'name');
    $intro = get_field_from_userid($user_id, 'introduction');

    // ===== CONTENT BLOCKS =====
    $aff = '
    <div class="div-aff" style="text-align:center;">
        <h5>'.$name.'</h5>
        <i>'.$intro.'</i>
    </div>';

    $affiliate_wa    = get_field_from_userid($user_id, 'affiliate_wa');
    $affiliate_phone = get_field_from_userid($user_id, 'affiliate_phone');
    $affiliate_email = get_field_from_userid($user_id, 'affiliate_email');
    
    $buttons = [];
    
    // WhatsApp
    if (!empty($affiliate_wa)) {
        $buttons[] = '<a href="https://wa.me/'.esc_attr($affiliate_wa).'" target="_blank" class="btn btn-phone">
                        <i class="fab fa-whatsapp"></i> WhatsApp
                      </a>';
    }
    
    // Phone
    if (!empty($affiliate_phone)) {
        $buttons[] = '<a href="tel:'.esc_attr($affiliate_phone).'" class="btn btn-phone">
                        <i class="fas fa-phone-alt"></i> Telephone
                      </a>';
    }
    
    // Email
    if (!empty($affiliate_email)) {
        $buttons[] = '<a href="mailto:'.esc_attr($affiliate_email).'" class="btn btn-phone">
                        <i class="far fa-envelope"></i> Email
                      </a>';
    }
    
    // Output only if at least one exists
    $contact = '';
    if (!empty($buttons)) {
        $contact = '<div class="contact-buttons">'.implode('', $buttons).'</div>';
    }
    
    $affiliate_fb        = get_field_from_userid($user_id, 'affiliate_fb');
    $affiliate_x         = get_field_from_userid($user_id, 'affiliate_x');
    $affiliate_linkedin  = get_field_from_userid($user_id, 'affiliate_linkedin');
    $affiliate_tiktok    = get_field_from_userid($user_id, 'affiliate_tiktok');
    $affiliate_youtube   = get_field_from_userid($user_id, 'affiliate_youtube');
    $affiliate_instagram = get_field_from_userid($user_id, 'affiliate_instagram');
    
    $icons = [];
    
    if (!empty($affiliate_fb)) {
        $icons[] = '<a href="'.esc_url($affiliate_fb).'" target="_blank" rel="noopener noreferrer">
                        <img src="https://cdn-icons-png.flaticon.com/24/733/733547.png" alt="Facebook">
                    </a>';
    }
    
    if (!empty($affiliate_x)) {
        $icons[] = '<a href="'.esc_url($affiliate_x).'" target="_blank" rel="noopener noreferrer">
                        <img src="https://cdn-icons-png.flaticon.com/24/733/733579.png" alt="X">
                    </a>';
    }
    
    if (!empty($affiliate_linkedin)) {
        $icons[] = '<a href="'.esc_url($affiliate_linkedin).'" target="_blank" rel="noopener noreferrer">
                        <img src="https://cdn-icons-png.flaticon.com/24/174/174857.png" alt="LinkedIn">
                    </a>';
    }
    
    if (!empty($affiliate_tiktok)) {
        $icons[] = '<a href="'.esc_url($affiliate_tiktok).'" target="_blank" rel="noopener noreferrer">
                        <img src="https://cdn-icons-png.flaticon.com/24/3046/3046126.png" alt="TikTok">
                    </a>';
    }
    
    if (!empty($affiliate_youtube)) {
        $icons[] = '<a href="'.esc_url($affiliate_youtube).'" target="_blank" rel="noopener noreferrer">
                        <img src="https://cdn-icons-png.flaticon.com/24/1384/1384060.png" alt="YouTube">
                    </a>';
    }
    
    if (!empty($affiliate_instagram)) {
        $icons[] = '<a href="'.esc_url($affiliate_instagram).'" target="_blank" rel="noopener noreferrer">
                        <img src="https://cdn-icons-png.flaticon.com/24/2111/2111463.png" alt="Instagram">
                    </a>';
    }
    
    // Only output if at least one icon exists
    $socmed = '';
    if (!empty($icons)) {
        $socmed = '<div class="div-socmed">'.implode('', $icons).'</div>';
    }
    

    // Company
    $company        = get_field_from_userid($user_id, 'company_name');
    $company_intro  = get_field_from_userid($user_id, 'company_intro');

    $com = '
    <div class="div-com" style="text-align:center;font-size:14px;">
        <h5>'.$company.'</h5>
        <i>'.$company_intro.'</i>
    </div>';
    
    $company_wa       = get_field_from_userid($user_id, 'company_wa');
    $company_website  = get_field_from_userid($user_id, 'company_website');
    $company_location = get_field_from_userid($user_id, 'company_location');
    
    $buttons = [];
    
    // WhatsApp
    if (!empty($company_wa)) {
        $buttons[] = '<a href="https://wa.me/'.esc_attr($company_wa).'" target="_blank" class="btn btn-phone">
                        <i class="fab fa-whatsapp"></i> WhatsApp
                      </a>';
    }
    
    // Website
    if (!empty($company_website)) {
        $buttons[] = '<a href="'.esc_url($company_website).'" target="_blank" class="btn btn-phone">
                        <i class="fas fa-globe"></i> Website
                      </a>';
    }
    
    // Location
    if (!empty($company_location)) {
        $buttons[] = '<a href="'.esc_url($company_location).'" target="_blank" class="btn btn-phone">
                        <i class="fas fa-map-marker-alt"></i> Location
                      </a>';
    }
    
    // Output only if at least one button exists
    $co_contact = '';
    if (!empty($buttons)) {
        $co_contact = '<div class="contact-buttons">'.implode('', $buttons).'</div>';
    }
    
    // COMPANY SOCMED
    $company_fb        = get_field_from_userid($user_id, 'company_fb');
    $company_x         = get_field_from_userid($user_id, 'company_x');
    $company_linkedin  = get_field_from_userid($user_id, 'company_linkedin');
    $company_tiktok    = get_field_from_userid($user_id, 'company_tiktok');
    $company_youtube   = get_field_from_userid($user_id, 'company_youtube');
    $company_instagram = get_field_from_userid($user_id, 'company_instagram');
    
    $icons = [];
    
    if (!empty($company_fb)) {
        $icons[] = '<a href="'.esc_url($company_fb).'" target="_blank" rel="noopener noreferrer">
                        <img src="https://cdn-icons-png.flaticon.com/24/733/733547.png" alt="Facebook">
                    </a>';
    }
    
    if (!empty($company_x)) {
        $icons[] = '<a href="'.esc_url($company_x).'" target="_blank" rel="noopener noreferrer">
                        <img src="https://cdn-icons-png.flaticon.com/24/733/733579.png" alt="X">
                    </a>';
    }
    
    if (!empty($company_linkedin)) {
        $icons[] = '<a href="'.esc_url($company_linkedin).'" target="_blank" rel="noopener noreferrer">
                        <img src="https://cdn-icons-png.flaticon.com/24/174/174857.png" alt="LinkedIn">
                    </a>';
    }
    
    if (!empty($company_tiktok)) {
        $icons[] = '<a href="'.esc_url($company_tiktok).'" target="_blank" rel="noopener noreferrer">
                        <img src="https://cdn-icons-png.flaticon.com/24/3046/3046126.png" alt="TikTok">
                    </a>';
    }
    
    if (!empty($company_youtube)) {
        $icons[] = '<a href="'.esc_url($company_youtube).'" target="_blank" rel="noopener noreferrer">
                        <img src="https://cdn-icons-png.flaticon.com/24/1384/1384060.png" alt="YouTube">
                    </a>';
    }
    
    if (!empty($company_instagram)) {
        $icons[] = '<a href="'.esc_url($company_instagram).'" target="_blank" rel="noopener noreferrer">
                        <img src="https://cdn-icons-png.flaticon.com/24/2111/2111463.png" alt="Instagram">
                    </a>';
    }
    
    // Only output if at least one icon exists
    $co_socmed = '';
    if (!empty($icons)) {
        $co_socmed = '<div class="div-socmed">'.implode('', $icons).'</div>';
    }

    // ===== FINAL OUTPUT =====
    $ret  = $aff;
    $ret .= $contact;
    $ret .= $socmed;
    $ret .= $com;
    $ret .= $co_contact;
    $ret .= $co_socmed; 

    // ===== UPDATE AFFILIATE POST =====
    
    $banner  = get_field_from_userid($user_id, 'affiliate_banner');
    $post_id = get_field_from_userid($user_id, 'post_id');

    if ((int)$post_id>0) {
        // Update post content
        wp_update_post([
            'ID'           => $post_id,
            'post_title'   => sanitize_text_field($name),
            'post_content' => $ret,
            'post_excerpt' => sanitize_text_field($intro),
        ]);
    
        set_post_thumbnail($post_id, $banner);
    }

    return $ret;

}


//QR CODE
add_action( 'plugins_loaded', function() {
	add_shortcode( 'affiliate_qr', 'affiliate_qr_shortcode' );
});
function affiliate_qr_shortcode() {
    $current_user = wp_get_current_user();  
    $user_id = $current_user->ID;
    $page = basename(get_permalink());
    $affiliate_id = isset($_COOKIE['affiliateid']) ? sanitize_text_field($_COOKIE['affiliateid']) : '';

    if ($page=='Share'){
        $affiliate_link = "https://masjid4all.com/";
    }else{
        $affiliate_link = "https://masjid4all.com/" . $page;
    }
    
 
    if ($user_id) {
        $affiliate_link .= "?id=" . $user_id;
    }else{
        if ($affiliate_id<>''){
            $affiliate_link .= "?id=" . $affiliate_id;
        }
    }
    
    $affiliate_link = esc_url($affiliate_link);
    

    ob_start(); ?>

    <style>
        .qr-container {
            text-align: center;
        }
        .qr-box {
            display: inline-block;
            padding: 10px;
            border: 1px solid #1a7efb;
            background: #fff;
            border-radius: 10px;
            margin-top: 10px;
        }
        .share-link {
            display: block;
            margin-top: 10px;
            text-decoration: none;
            background-color: #0073aa;
            color: white;
            padding: 10px 15px;
            border-radius: 5px;
            font-size: 14px;
            transition: background 0.3s;
        }
        .share-link:hover {
            background-color: #005f87;
        }
    </style> 

    <div class="qr-container">
        <div id="qrcode" class="qr-box"></div>
    </div>

    <!-- QR Code Generator -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>
    <script>
        document.addEventListener("DOMContentLoaded", function() {
            new QRCode(document.getElementById("qrcode"), {
                text: "<?php echo $affiliate_link; ?>",
                width: 200,
                height: 200
            });
        });
    </script>

    <?php
    return ob_get_clean();
}


// FORM

// Affiliate - Create Custom Post
add_action('fluentform/before_insert_submission', function ($insertData, $data, $form) {

    // Only process Form ID 57
    if ((int) $form->id !== 57) {
        return;
    }

    global $current_user;
    wp_get_current_user();
    $user_id = $current_user->ID;

    // Get affiliate slug
    $affiliate_url = sanitize_title(
        \FluentForm\Framework\Helpers\ArrayHelper::get($data, 'affiliate_url')
    );

    if (!$affiliate_url) {
        wp_send_json([
            'errors' => [
                'affiliate_url' => ['Please enter a valid affiliate name.']
            ]
        ], 422);
    }

    // Get Affiliate category
    $category = get_category_by_slug('affiliate');
    // Check if slug already exists in affiliate category
    $existing = get_posts([
        'name'           => $affiliate_url,
        'post_type'      => 'post',
        'posts_per_page' => 1,
        'category__in'   => [$category->term_id],
        'post_status'    => ['publish', 'draft', 'pending']
    ]);

    if ($existing) {
        wp_send_json([
            'errors' => [
                'phone' => ['This affiliate name is already taken. Please choose another one.']
            ]
        ], 422);
    }

    // Create the post
    $name = get_field_from_userid($user_id, 'name');
    $post_id = wp_insert_post([
        'post_title'   => $name,
        'post_name'    => $affiliate_url,
        'post_status'  => 'publish',
        'post_type'    => 'post',
        'post_category'=> [$category->term_id],
        'post_content' => 'Affiliate page for ' . esc_html($affiliate_url),
    ]);

    if (is_wp_error($post_id)) {
        wp_send_json([
            'errors' => [
                'phone' => ['Unable to create affiliate page. Please try again.']
            ]
        ], 422);
    }

    // Save to cct_member
    update_field_from_userid($user_id, 'affiliate_url', $affiliate_url);
    update_field_from_userid($user_id, 'post_id', $post_id);

}, 10, 3);

