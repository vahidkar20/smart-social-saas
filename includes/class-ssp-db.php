<?php
if (!defined('ABSPATH')) exit;

class SSP_DB {
    
    public static function create_tables() {
        global $wpdb;
        $charset_collate = $wpdb->get_charset_collate();
        
        $table_queue = $wpdb->prefix . 'ssp_queue';
        $table_logs = $wpdb->prefix . 'ssp_logs';
        
        $sql = "CREATE TABLE $table_queue (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            user_id bigint(20) NOT NULL,
            action_type varchar(50) NOT NULL,
            priority int(11) DEFAULT 5,
            payload longtext NOT NULL,
            status varchar(20) DEFAULT 'pending',
            attempts int(11) DEFAULT 0,
            max_attempts int(11) DEFAULT 3,
            error_message text,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            processed_at datetime,
            PRIMARY KEY  (id),
            KEY status_priority (status, priority),
            KEY user_id (user_id)
        ) $charset_collate;
        
        CREATE TABLE $table_logs (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            user_id bigint(20) NOT NULL,
            platform varchar(50),
            title varchar(255),
            message longtext,
            status varchar(20),
            response longtext,
            ai_provider varchar(50),
            ai_tokens int(11) DEFAULT 0,
            image_url text,
            tool_type varchar(50),
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            KEY user_id (user_id),
            KEY created_at (created_at)
        ) $charset_collate;";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }

    public static function insert_queue($data) {
        global $wpdb;
        $table = $wpdb->prefix . 'ssp_queue';
        if (isset($data['payload']) && is_array($data['payload'])) {
            $data['payload'] = wp_json_encode($data['payload']);
        }
        $wpdb->insert($table, $data);
        return $wpdb->insert_id;
    }

    public static function get_queue_items($status = 'pending', $limit = 50, $user_id = null) {
        global $wpdb;
        $table = $wpdb->prefix . 'ssp_queue';
        $sql = "SELECT * FROM $table";
        if ($status) {
            $sql .= $wpdb->prepare(" WHERE status = %s", $status);
        if ($user_id !== null) {
            $sql .= $wpdb->prepare(" AND user_id = %d", $user_id);
        }
        }
        $sql .= " ORDER BY priority DESC, created_at ASC";
        if ($limit > 0) {
            $sql .= $wpdb->prepare(" LIMIT %d", $limit);
        }
        $results = $wpdb->get_results($sql, ARRAY_A);
        if ($results) {
            foreach ($results as &$row) {
                $row['payload'] = json_decode($row['payload'], true) ?: [];
            }
        }
        return $results ?: [];
    }

    public static function update_queue($id, $data) {
        global $wpdb;
        $table = $wpdb->prefix . 'ssp_queue';
        if (isset($data['payload']) && is_array($data['payload'])) {
            $data['payload'] = wp_json_encode($data['payload']);
        }
        return $wpdb->update($table, $data, ['id' => $id]);
    }

    public static function delete_queue($id) {
        global $wpdb;
        $table = $wpdb->prefix . 'ssp_queue';
        return $wpdb->delete($table, ['id' => $id]);
    }

    public static function insert_log($data) {
        global $wpdb;
        $table = $wpdb->prefix . 'ssp_logs';
        $wpdb->insert($table, $data);
        return $wpdb->insert_id;
    }

    public static function get_logs($user_id = null, $limit = 100) {
        global $wpdb;
        $table = $wpdb->prefix . 'ssp_logs';
        $sql = "SELECT * FROM $table";
        if ($user_id !== null) {
            $sql .= $wpdb->prepare(" WHERE user_id = %d", $user_id);
        }
        $sql .= " ORDER BY created_at DESC";
        if ($limit > 0) {
            $sql .= $wpdb->prepare(" LIMIT %d", $limit);
        }
        return $wpdb->get_results($sql, ARRAY_A) ?: [];
    }
    
    public static function get_all_logs($limit = 100) {
        return self::get_logs(null, $limit);
    }

    public static function delete_log($id) {
        global $wpdb;
        $table = $wpdb->prefix . 'ssp_logs';
        return $wpdb->delete($table, ['id' => $id]);
    }
    
    public static function clear_user_logs($user_id) {
        global $wpdb;
        $table = $wpdb->prefix . 'ssp_logs';
        return $wpdb->delete($table, ['user_id' => $user_id]);
    }
    
    public static function clear_all_logs() {
        global $wpdb;
        $table = $wpdb->prefix . 'ssp_logs';
        return $wpdb->query("TRUNCATE TABLE $table");
    }

    public static function get_today_queue_count($user_id) {
        global $wpdb;
        $table = $wpdb->prefix . 'ssp_queue';
        $today = current_time('Y-m-d');
        return (int)$wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM $table WHERE user_id = %d AND DATE(created_at) = %s", $user_id, $today));
    }
    
    public static function get_today_logs_count($user_id) {
        global $wpdb;
        $table = $wpdb->prefix . 'ssp_logs';
        $today = current_time('Y-m-d');
        return (int)$wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM $table WHERE user_id = %d AND DATE(created_at) = %s", $user_id, $today));
    }

    public static function cleanup_old_data() {
        global $wpdb;
        $table_queue = $wpdb->prefix . 'ssp_queue';
        $table_logs = $wpdb->prefix . 'ssp_logs';
        
        // Delete processed queue older than 3 days
        $wpdb->query("DELETE FROM $table_queue WHERE status = 'success' AND created_at < DATE_SUB(NOW(), INTERVAL 3 DAY)");
        
        // Delete logs older than 30 days
        $wpdb->query("DELETE FROM $table_logs WHERE created_at < DATE_SUB(NOW(), INTERVAL 30 DAY)");
    }
}
