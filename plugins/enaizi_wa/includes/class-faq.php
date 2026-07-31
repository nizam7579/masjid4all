<?php

class Niz_WA_FAQ {

    public static function search($message) {
        global $wpdb;

        $table = $wpdb->prefix . 'niz_wa_faq';
        $msg = strtolower(trim($message));

        $rows = $wpdb->get_results(
            "SELECT * FROM $table WHERE status = 1",
            ARRAY_A
        );

        if (!$rows) {
            return null;
        }

        foreach ($rows as $row) {
            $keywords = explode(',', $row['keywords'] ?? '');

            foreach ($keywords as $keyword) {
                $keyword = strtolower(trim($keyword));

                if (!$keyword) {
                    continue;
                }

                if (strpos($msg, $keyword) !== false) {
                    return $row['answer'];
                }
            }
        }

        return null;
    }
}