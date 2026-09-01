<?php
/**
 * SSP AJAX Admin Trait - License, cron, logs, admin operations
 */
trait SSP_AjaxAdmin {

    private function get_license_secret() {
        $secret = get_option('ssp_license_secret', '');
        if (empty($secret)) {
            $secret = wp_generate_password(64, true, true);
            update_option('ssp_license_secret', $secret);
        }
        return $secret;
    }

    private function sign_license($key) {
        $secret = $this->get_license_secret();
        return hash_hmac('sha256', $key, $secret);
    }

    private function verify_license_signature($key, $signature) {
        $expected = $this->sign_license($key);
        return hash_equals($expected, $signature);
    }

    public function handle_activate_license() {
        check_ajax_referer('ssp_secure_nonce', 'security');

        // Rate limiting: max 5 attempts per minute
        $user_id = get_current_user_id();
        $rate_key = 'ssp_license_rate_' . $user_id;
        $attempts = get_transient($rate_key) ?: 0;
        if ($attempts >= 5) {
            wp_send_json_error(['message' => 'تعداد تلاش‌ها بیش از حد مجاز است. لطفاً یک دقیقه صبر کنید']);
        }
        set_transient($rate_key, $attempts + 1, 60);

        $key = sanitize_text_field($_POST['license_key'] ?? '');
        $signature = sanitize_text_field($_POST['license_signature'] ?? '');

        // Validate key format
        if (!preg_match('/^PRO-[A-Z0-9]{4}-[A-Z0-9]{4}-[A-Z0-9]{4}$/', $key)) {
            wp_send_json_error(['message' => 'فرمت لایسنس نامعتبر است']);
        }

        // Verify HMAC signature if provided (for new licenses)
        if (!empty($signature)) {
            if (!$this->verify_license_signature($key, $signature)) {
                wp_send_json_error(['message' => 'لایسنس جعلی شناسایی شد']);
            }
        }

        $licenses = $this->get_global_items('licenses');
        $found = null;
        foreach ($licenses as &$lic) {
            if ($lic['license_key'] === $key && empty($lic['used_by'])) { $found = &$lic; break; }
        }
        unset($lic);

        $redirect_url = admin_url('admin.php?page=smart-automation&tab=subscription');
        if ($found) {
            $user_id = get_current_user_id();
            $found['used_by'] = $user_id;
            $found['used_at'] = current_time('mysql');
            $this->set_global_items('licenses', $licenses);
            update_user_meta($user_id, 'ssp_plan', 'pro');
            update_user_meta($user_id, 'ssp_plan_expiry', strtotime('+' . $found['duration_days'] . ' days'));
            update_user_meta($user_id, 'ssp_license_key', $key);
            delete_transient($rate_key); // Reset rate limit on success
            if (wp_doing_ajax()) wp_send_json_success(['message' => 'اشتراک Pro فعال شد!']);
            else { wp_redirect(add_query_arg(['license_activated' => '1'], $redirect_url)); exit; }
        }
        if (wp_doing_ajax()) wp_send_json_error(['message' => 'لایسنس نامعتبر یا قبلاً استفاده شده']);
        else { wp_redirect(add_query_arg(['license_error' => '1'], $redirect_url)); exit; }
    }

    public function handle_clear_logs() {
        check_ajax_referer('ssp_secure_nonce', 'security');
        $user_id = get_current_user_id();
        $logs = $this->get_global_items('logs');
        $logs = array_values(array_filter($logs, function($l) use ($user_id) { return (int)$l['user_id'] !== (int)$user_id; }));
        $this->set_global_items('logs', $logs);
        wp_send_json_success(['message' => 'همه لاگ‌ها پاک شدند']);
    }

    public function handle_manual_process() {
        check_ajax_referer('ssp_secure_nonce', 'security');
        if (!current_user_can('manage_options')) wp_send_json_error(['message' => 'غیرمجاز']);
        $this->process_queue();
        wp_send_json_success(['message' => 'پردازش انجام شد']);
    }

    public function handle_generate_license() {
        check_ajax_referer('ssp_secure_nonce', 'security');
        if (!current_user_can('manage_options')) wp_send_json_error(['message' => 'غیرمجاز']);
        $licenses = $this->get_global_items('licenses');
        $count = min(50, max(1, intval($_POST['count'] ?? 1)));
        $duration = intval($_POST['duration'] ?? 365);
        $keys = [];
        for ($i = 0; $i < $count; $i++) {
            $key = 'PRO-' . strtoupper(wp_generate_password(4, false)) . '-' . strtoupper(wp_generate_password(4, false)) . '-' . strtoupper(wp_generate_password(4, false));
            $signature = $this->sign_license($key);
            $id = $this->next_id($licenses);
            $licenses[] = ['id' => $id, 'license_key' => $key, 'signature' => $signature, 'plan' => 'pro', 'duration_days' => $duration, 'used_by' => null, 'used_at' => null, 'created_at' => current_time('mysql')];
            $keys[] = $key;
        }
        $this->set_global_items('licenses', $licenses);
        wp_send_json_success(['keys' => $keys]);
    }

    public function handle_revoke_license() {
        check_ajax_referer('ssp_secure_nonce', 'security');
        if (!current_user_can('manage_options')) wp_send_json_error(['message' => 'غیرمجاز']);
        $licenses = $this->get_global_items('licenses');
        $id = intval($_POST['license_id']);
        // If license is used, also reset the user's plan
        foreach ($licenses as $lic) {
            if ((int)$lic['id'] === $id && !empty($lic['used_by'])) {
                update_user_meta($lic['used_by'], 'ssp_plan', 'free');
                delete_user_meta($lic['used_by'], 'ssp_plan_expiry');
                delete_user_meta($lic['used_by'], 'ssp_license_key');
                break;
            }
        }
        $licenses = array_values(array_filter($licenses, function($l) use ($id) { return (int)$l['id'] !== $id; }));
        $this->set_global_items('licenses', $licenses);
        wp_send_json_success(['message' => 'لایسنس حذف شد']);
    }

    public function handle_update_license_expiry() {
        check_ajax_referer('ssp_secure_nonce', 'security');
        if (!current_user_can('manage_options')) wp_send_json_error(['message' => 'غیرمجاز']);
        $licenses = $this->get_global_items('licenses');
        $id = intval($_POST['license_id']);
        $new_duration = intval($_POST['duration_days']);
        if ($new_duration < 1) wp_send_json_error(['message' => 'مدت نامعتبر است']);
        $found = false;
        foreach ($licenses as &$lic) {
            if ((int)$lic['id'] === $id) {
                $lic['duration_days'] = $new_duration;
                // If used, also update the user's plan expiry
                if (!empty($lic['used_by'])) {
                    update_user_meta($lic['used_by'], 'ssp_plan_expiry', strtotime('+' . $new_duration . ' days'));
                }
                $found = true;
                break;
            }
        }
        unset($lic);
        if (!$found) wp_send_json_error(['message' => 'لایسنس یافت نشد']);
        $this->set_global_items('licenses', $licenses);
        wp_send_json_success(['message' => 'تاریخ لایسنس بروزرسانی شد']);
    }

    public function handle_activate_cron() {
        check_ajax_referer('ssp_secure_nonce', 'security');
        if (!current_user_can('manage_options')) wp_send_json_error(['message' => 'غیرمجاز']);
        $this->activate();
        if (wp_next_scheduled('ssp_process_queue_hook')) wp_send_json_success(['message' => 'Cron jobs با موفقیت فعال شدند!']);
        else wp_send_json_error(['message' => 'خطا در فعال‌سازی Cron jobs']);
    }

    /**
     * Get list of all users for admin browser
     */
    public function handle_get_user_list() {
        check_ajax_referer('ssp_secure_nonce', 'security');
        if (!current_user_can('manage_options')) wp_send_json_error(['message' => 'غیرمجاز']);

        $search = sanitize_text_field($_POST['search'] ?? '');
        $args = [
            'fields' => ['ID', 'display_name', 'user_email', 'user_login'],
            'number' => 50,
            'orderby' => 'display_name',
            'order' => 'ASC',
        ];
        if (!empty($search)) {
            $args['search'] = $search;
            $args['search_columns'] = ['display_name', 'user_email', 'user_login'];
        }

        $users = get_users($args);
        $result = [];
        foreach ($users as $user) {
            $plan = get_user_meta($user->ID, 'ssp_plan', true) ?: 'free';
            $messengers = get_user_meta($user->ID, 'ssp_messengers', true) ?: [];
            $bots = get_user_meta($user->ID, 'ssp_bot_configs', true) ?: [];
            $wp_sites = get_user_meta($user->ID, 'ssp_wp_sites', true) ?: [];
            $profiles = get_user_meta($user->ID, 'ssp_profiles', true) ?: [];

            $result[] = [
                'id' => $user->ID,
                'name' => $user->display_name,
                'email' => $user->user_email,
                'login' => $user->user_login,
                'plan' => $plan,
                'messenger_count' => count($messengers),
                'bot_count' => count($bots),
                'wp_site_count' => count($wp_sites),
                'profile_count' => count($profiles),
            ];
        }

        wp_send_json_success(['users' => $result]);
    }

    /**
     * Start impersonating a user
     */
    public function handle_impersonate_user() {
        check_ajax_referer('ssp_secure_nonce', 'security');
        if (!current_user_can('manage_options')) wp_send_json_error(['message' => 'غیرمجاز']);

        $admin_id = get_current_user_id();
        $target_id = intval($_POST['target_user_id'] ?? 0);

        if ($target_id === $admin_id) wp_send_json_error(['message' => 'نمی‌توانید خودتان را مشاهده کنید']);
        if (!$target_id || !get_userdata($target_id)) wp_send_json_error(['message' => 'کاربر یافت نشد']);

        set_transient('ssp_impersonate_' . $admin_id, $target_id, 600); // 10 minutes

        $target_user = get_userdata($target_id);
        error_log('[SSP Admin] Impersonation started: admin=' . $admin_id . ' target=' . $target_id . ' (' . $target_user->display_name . ')');

        wp_send_json_success([
            'message' => 'پورتال کاربر «' . $target_user->display_name . '» باز شد',
            'redirect' => admin_url('admin.php?page=smart-automation&impersonate=1')
        ]);
    }

    /**
     * Stop impersonating a user
     */
    public function handle_stop_impersonation() {
        check_ajax_referer('ssp_secure_nonce', 'security');
        if (!current_user_can('manage_options')) wp_send_json_error(['message' => 'غیرمجاز']);

        $admin_id = get_current_user_id();
        delete_transient('ssp_impersonate_' . $admin_id);

        wp_send_json_success(['message' => 'مشاهده کاربر متوقف شد']);
    }

    /**
     * Retry a failed message
     */
    public function handle_retry_message() {
        check_ajax_referer('ssp_secure_nonce', 'security');
        $user_id = $this->ajax_require_auth();

        $log_id = intval($_POST['log_id'] ?? 0);
        if ($log_id <= 0) wp_send_json_error(['message' => 'شناسه پیام نامعتبر']);

        // Find the log entry
        $logs = $this->get_global_items('logs');
        $log_entry = null;
        foreach ($logs as $log) {
            if ((int)$log['id'] === $log_id && (int)$log['user_id'] === (int)$user_id) {
                $log_entry = $log;
                break;
            }
        }

        if (!$log_entry) wp_send_json_error(['message' => 'پیام یافت نشد']);
        if ($log_entry['status'] === 'success') wp_send_json_error(['message' => 'این پیام قبلاً با موفقیت ارسال شده']);

        // Re-create queue item with same payload
        $payload = [
            'title' => $log_entry['title'] ?? '',
            'message' => $log_entry['message'] ?? '',
            'hashtags' => '',
            'image_url' => $log_entry['image_url'] ?? '',
        ];

        // Get enabled messengers for this platform
        $messengers = $this->get_enabled_messengers($user_id);
        $platform_messengers = array_filter($messengers, function($m) use ($log_entry) {
            return $m['platform'] === $log_entry['platform'];
        });

        if (!empty($platform_messengers)) {
            $payload['selected_messengers'] = array_column($platform_messengers, 'id');
        }

        $result = $this->add_to_queue($user_id, 'manual_send', $payload, 10);

        if ($result['success']) {
            wp_send_json_success(['message' => 'پیام مجدداً به صف ارسال اضافه شد']);
        } else {
            wp_send_json_error(['message' => $result['message'] ?? 'خطا در اضافه کردن به صف']);
        }
    }

    /**
     * Cancel a queue item
     */
    public function handle_cancel_queue_item() {
        check_ajax_referer('ssp_secure_nonce', 'security');
        $user_id = $this->ajax_require_auth();

        $item_id = intval($_POST['item_id'] ?? 0);
        if ($item_id <= 0) wp_send_json_error(['message' => 'شناسه نامعتبر']);

        $queue = $this->get_global_items('queue');
        $found = false;
        foreach ($queue as $key => $item) {
            if ((int)$item['id'] === $item_id && (int)$item['user_id'] === (int)$user_id && $item['status'] === 'pending') {
                unset($queue[$key]);
                $found = true;
                break;
            }
        }

        if (!$found) wp_send_json_error(['message' => 'پیام یافت نشد یا قبلاً پردازش شده']);

        $queue = array_values($queue);
        $this->set_global_items('queue', $queue);

        wp_send_json_success(['message' => 'پیام از صف حذف شد']);
    }

    /**
     * Cancel all pending queue items for a user
     */
    public function handle_cancel_all_queue() {
        check_ajax_referer('ssp_secure_nonce', 'security');
        $user_id = $this->ajax_require_auth();

        $queue = $this->get_global_items('queue');
        $count = 0;
        $new_queue = [];
        foreach ($queue as $item) {
            if ((int)$item['user_id'] === (int)$user_id && $item['status'] === 'pending') {
                $count++;
            } else {
                $new_queue[] = $item;
            }
        }

        $this->set_global_items('queue', $new_queue);

        wp_send_json_success(['message' => "$count پیام از صف حذف شد"]);
    }

    /**
     * Cancel a scheduled post
     */
    public function handle_cancel_schedule() {
        check_ajax_referer('ssp_secure_nonce', 'security');
        $user_id = $this->ajax_require_auth();

        $schedule_id = intval($_POST['schedule_id'] ?? 0);
        if ($schedule_id <= 0) wp_send_json_error(['message' => 'شناسه نامعتبر']);

        $schedules = $this->get_user_items($user_id, 'schedules');
        $found = false;
        foreach ($schedules as $key => $sch) {
            if ((int)$sch['id'] === $schedule_id && $sch['status'] === 'pending') {
                unset($schedules[$key]);
                $found = true;
                break;
            }
        }

        if (!$found) wp_send_json_error(['message' => 'زمان‌بندی یافت نشد']);

        $schedules = array_values($schedules);
        $this->set_user_items($user_id, 'schedules', $schedules);

        wp_send_json_success(['message' => 'زمان‌بندی لغو شد']);
    }
}
