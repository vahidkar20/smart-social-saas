<?php
/**
 * SSP Cleanup Trait - Log/queue cleanup, rate limiting, encryption, sanitization
 */
trait SSP_Cleanup {

    public function run_cleanup() {
        $this->cleanup_old_logs();
        $this->cleanup_old_queue();
        $this->cleanup_stale_scenario_steps();
        $this->cleanup_expired_bridge_tasks();
        $this->cleanup_old_distribution_history();
        $this->cleanup_expired_rate_limit_transients();
    }

    /**
     * Enhanced cleanup for rate-limit transients
     */
    private function cleanup_expired_rate_limit_transients() {
        global $wpdb;
        $wpdb->query($wpdb->prepare(
            "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s AND option_name NOT LIKE %s",
            '_transient_ssp_rl_%', '_transient_timeout_ssp_rl_%'
        ));
        $wpdb->query($wpdb->prepare(
            "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s AND option_value < %d",
            '_transient_timeout_ssp_rl_%', time()
        ));
    }

    /**
     * Clean up stale scenario steps (orphaned bot scenario data)
     */
    private function cleanup_stale_scenario_steps() {
        $users = get_users(['fields' => 'ID', 'meta_key' => 'ssp_bot_scenario_steps', 'number' => 500]);
        foreach ($users as $user_id) {
            $steps = get_user_meta($user_id, 'ssp_bot_scenario_steps', true);
            if (!is_array($steps) || empty($steps)) continue;

            $configs = $this->get_user_bot_configs($user_id);
            $active_bot_ids = array_column(array_filter($configs, function($c) { return !empty($c['is_active']); }), 'id');
            $active_bot_ids = array_map('intval', $active_bot_ids);

            $cleaned = false;
            foreach ($steps as $bot_id => $step_id) {
                if (!in_array((int)$bot_id, $active_bot_ids)) {
                    unset($steps[$bot_id]);
                    $cleaned = true;
                }
            }

            if ($cleaned) {
                if (empty($steps)) {
                    delete_user_meta($user_id, 'ssp_bot_scenario_steps');
                } else {
                    update_user_meta($user_id, 'ssp_bot_scenario_steps', $steps);
                }
            }
        }
    }

    /**
     * Clean up expired bridge task/result transients
     */
    private function cleanup_expired_bridge_tasks() {
        global $wpdb;
        $wpdb->query($wpdb->prepare(
            "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s AND option_value < %d",
            '_transient_timeout_ssp_bridge_%', time()
        ));
        $wpdb->query($wpdb->prepare(
            "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s AND option_name NOT LIKE %s",
            '_transient_ssp_bridge_%', '_transient_timeout_ssp_bridge_%'
        ));
    }

    /**
     * Clean up orphaned distribution history (for deleted distributions)
     */
    private function cleanup_old_distribution_history() {
        $users = get_users(['fields' => 'ID', 'meta_key' => 'ssp_distributions', 'meta_compare' => 'EXISTS', 'number' => 200]);
        foreach ($users as $user_id) {
            $dists = get_user_meta($user_id, 'ssp_distributions', true) ?: [];
            $active_ids = array_column($dists, 'id');
            // Check for orphaned history keys
            $all_meta = get_user_meta($user_id);
            foreach ($all_meta as $key => $value) {
                if (strpos($key, 'ssp_dist_history_') === 0) {
                    $dist_id = intval(str_replace('ssp_dist_history_', '', $key));
                    if (!in_array($dist_id, $active_ids)) {
                        delete_user_meta($user_id, $key);
                    }
                }
            }
        }
    }

    /**
     * Clean up old logs (keep last 200)
     */
    private function cleanup_old_logs() {
        $last_cleanup = get_option('ssp_last_log_cleanup', 0);
        if (time() - $last_cleanup < SSP_LOG_CLEANUP_INTERVAL) return;
        $logs = $this->get_global_items('logs');
        if (count($logs) > SSP_MAX_LOGS) {
            $logs = array_slice($logs, 0, SSP_MAX_LOGS);
            $this->set_global_items('logs', $logs);
        }
        update_option('ssp_last_log_cleanup', time(), false);
    }

    /**
     * Clean up old queue items (completed/failed older than 24h)
     */
    private function cleanup_old_queue() {
        $queue = $this->get_global_items('queue');
        $changed = false;
        $cutoff = time() - 86400;
        foreach ($queue as $key => $item) {
            if (in_array($item['status'], ['completed', 'failed'])) {
                $item_time = strtotime($item['created_at'] ?? '');
                if ($item_time && $item_time < $cutoff) { unset($queue[$key]); $changed = true; }
            }
        }
        if ($changed) {
            $queue = array_values($queue);
            $this->set_global_items('queue', $queue);
        }
    }

    private function check_webhook_rate_limit($ip) {
        $transient_key = 'ssp_rl_' . md5($ip);
        $requests = get_transient($transient_key);
        if ($requests === false) $requests = 0;
        if ($requests >= SSP_WEBHOOK_RATE_LIMIT) return false;
        set_transient($transient_key, $requests + 1, 60);
        return true;
    }

    private function encrypt_token($token) {
        if (empty($token)) return $token;
        $key = get_option('ssp_encryption_key', '');
        if (empty($key)) {
            $key = wp_generate_password(64, true, true);
            update_option('ssp_encryption_key', $key);
        }
        $iv = substr(md5($key), 0, 16);
        $encrypted = openssl_encrypt($token, 'AES-128-CBC', $key, 0, $iv);
        return base64_encode($encrypted);
    }

    private function decrypt_token($encrypted_token) {
        if (empty($encrypted_token)) return $encrypted_token;
        if (preg_match('/^[0-9]+:[A-Za-z0-9_-]+$/', $encrypted_token)) return $encrypted_token;
        $key = get_option('ssp_encryption_key', '');
        if (empty($key)) return $encrypted_token;
        $iv = substr(md5($key), 0, 16);
        $decrypted = openssl_decrypt(base64_decode($encrypted_token), 'AES-128-CBC', $key, 0, $iv);
        return $decrypted !== false ? $decrypted : $encrypted_token;
    }

    private function sanitize_webhook_input($data) {
        if (!is_array($data)) return null;
        $sanitized = [];
        foreach ($data as $key => $value) {
            if (is_string($value)) $sanitized[$key] = mb_substr($value, 0, 10000);
            elseif (is_array($value)) $sanitized[$key] = $this->sanitize_webhook_input($value);
            elseif (is_numeric($value)) $sanitized[$key] = $value;
        }
        return $sanitized;
    }
}
