<?php

class Niz_WA_Session {

    private static function table() {
        global $wpdb;
        return $wpdb->prefix . 'niz_wa_sessions';
    }

    public static function get($phone) {
        global $wpdb;

        $row = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM " . self::table() . " WHERE phone = %s",
                $phone
            ),
            ARRAY_A
        );

        if (!$row) {
            return null;
        }

        $context = [];

        if (!empty($row['context_json'])) {
            $context = json_decode($row['context_json'], true);
        }

        return [
            'state' => $row['state'],
            'context' => is_array($context) ? $context : []
        ];
    }

    public static function set($phone, $data) {
        global $wpdb;

        $state = $data['state'] ?? '';
        $context = $data;

        unset($context['state']);

        $wpdb->replace(
            self::table(),
            [
                'phone' => $phone,
                'state' => $state,
                'context_json' => wp_json_encode($context),
                'updated_at' => current_time('mysql')
            ]
        );
    }

    public static function clear($phone) {
        global $wpdb;

        $wpdb->delete(
            self::table(),
            [
                'phone' => $phone
            ]
        );
    }
}