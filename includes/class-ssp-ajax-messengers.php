<?php
/**
 * SSP AJAX Messengers Trait - Messenger CRUD AJAX
 */
trait SSP_AjaxMessengers {

    public function handle_get_messengers() {
        check_ajax_referer('ssp_secure_nonce', 'security');
        $user_id = $this->ajax_require_auth();
        $profile_id = $this->get_active_profile_id($user_id);
        $messengers = $this->get_profile_messengers($user_id, $profile_id);
        wp_send_json_success($messengers);
    }

    public function handle_add_messenger() {
        $user_id = $this->ajax_require_auth();
        $plan = $this->get_user_plan($user_id);
        $profile_id = $this->get_active_profile_id($user_id);
        $messengers = $this->get_profile_messengers($user_id, $profile_id);
        if ($plan === 'free' && count($messengers) >= 1) wp_send_json_error(['message' => 'در پلن رایگان فقط ۱ پیام‌رسان می‌توانید اضافه کنید']);
        elseif (count($messengers) >= 10) wp_send_json_error(['message' => 'حداکثر ۱۰ پیام‌رسان می‌توانید اضافه کنید']);
        $all_messengers = $this->get_user_items($user_id, 'messengers');
        $id = $this->next_id($all_messengers);
        $token = sanitize_text_field($_POST['token'] ?? $_POST['bot_token'] ?? '');
        $all_messengers[] = [
            'id' => $id, 'profile_id' => $profile_id,
            'platform' => sanitize_text_field($_POST['platform'] ?? 'telegram'),
            'name' => sanitize_text_field($_POST['name'] ?? 'پیام‌رسان جدید'), 'token' => $token,
            'channel_id' => sanitize_text_field($_POST['channel_id'] ?? ''),
            'is_active' => intval($_POST['is_active'] ?? 1), 'created_at' => current_time('mysql'),
        ];
        $this->set_user_items($user_id, 'messengers', $all_messengers);
        wp_send_json_success(['message' => 'پیام‌رسان با موفقیت اضافه شد!', 'id' => $id]);
    }

    public function handle_delete_messenger() {
        $user_id = $this->ajax_require_auth();
        $id = intval($_POST['messenger_id'] ?? $_POST['id'] ?? 0);
        $messengers = $this->get_user_messengers($user_id);
        $messengers = array_values(array_filter($messengers, function($m) use ($id) { return (int)$m['id'] !== $id; }));
        $this->set_user_items($user_id, 'messengers', $messengers);
        wp_send_json_success(['message' => 'پیام‌رسان حذف شد']);
    }

    public function handle_update_messenger() {
        $user_id = $this->ajax_require_auth();
        $id = intval($_POST['messenger_id'] ?? $_POST['id'] ?? 0);
        $messengers = $this->get_user_messengers($user_id);
        foreach ($messengers as &$m) {
            if ((int)$m['id'] === $id) {
                if (isset($_POST['name'])) $m['name'] = sanitize_text_field($_POST['name']);
                if (isset($_POST['platform'])) $m['platform'] = sanitize_text_field($_POST['platform']);
                $new_token = sanitize_text_field($_POST['token'] ?? $_POST['bot_token'] ?? '');
                if (!empty($new_token) && strpos($new_token, '****') === false) {
                    $m['token'] = $new_token;
                }
                if (isset($_POST['channel_id'])) $m['channel_id'] = sanitize_text_field($_POST['channel_id']);
                if (isset($_POST['is_active'])) $m['is_active'] = intval($_POST['is_active']);
                break;
            }
        }
        unset($m);
        $this->set_user_items($user_id, 'messengers', $messengers);
        wp_send_json_success(['message' => 'تغییرات ذخیره شد']);
    }

    public function handle_test_messenger() {
        $user_id = $this->ajax_require_auth();
        $id = intval($_POST['messenger_id'] ?? $_POST['id'] ?? 0);
        $messengers = $this->get_user_messengers($user_id);
        $target = null;
        foreach ($messengers as $m) {
            if ((int)$m['id'] === $id) {
                $target = $m;
                break;
            }
        }
        if (!$target) {
            wp_send_json_error(['message' => 'پیام‌رسان یافت نشد']);
        }
        $platform = $target['platform'];
        $token = $target['token'];
        $test_apis = ['telegram' => 'getMe', 'bale' => 'getMe', 'eitaa' => 'getMe', 'rubika' => 'getMe'];
        if (!isset($test_apis[$platform])) {
            wp_send_json_error(['message' => 'پلتفرم برای تست مستقیم پشتیبانی نمی‌شود']);
        }
        $result = $this->bot_api_request_with_retry($platform, $token, $test_apis[$platform]);
        
        $is_success = $result['success'];
        $err = strtolower($result['error'] ?? '');
        if ($platform === 'rubika' || $platform === 'eitaa') {
            if (!$is_success && strpos($err, 'unauthorized') === false && strpos($err, 'invalid token') === false) {
                $is_success = true;
            }
        }

        if ($is_success) {
            $bot_name = $result['result']['first_name'] ?? $result['result']['bot']['name'] ?? $result['result']['username'] ?? 'بات';
            wp_send_json_success(['message' => 'اتصال موفق! نام بات: ' . $bot_name, 'bot_name' => $bot_name]);
        } else {
            wp_send_json_error(['message' => 'خطا در اتصال: ' . ($result['error'] ?? 'پاسخی دریافت نشد')]);
        }
    }

    public function handle_check_messenger_health() {
        $user_id = $this->ajax_require_auth();
        $messengers = $this->get_enabled_messengers($user_id);
        $results = [];
        foreach ($messengers as $messenger) {
            $platform = $messenger['platform'];
            $token = $messenger['token'];
            $test_apis = ['telegram' => 'getMe', 'bale' => 'getMe', 'eitaa' => 'getMe', 'rubika' => 'getMe'];
            if (isset($test_apis[$platform])) {
                $result = $this->bot_api_request_with_retry($platform, $token, $test_apis[$platform]);
                $is_success = $result['success'];
                $err = strtolower($result['error'] ?? '');
                if ($platform === 'rubika' || $platform === 'eitaa') {
                    if (!$is_success && strpos($err, 'unauthorized') === false && strpos($err, 'invalid token') === false) {
                        $is_success = true;
                    }
                }
                $results[] = [
                    'id' => $messenger['id'], 'name' => $messenger['name'], 'platform' => $platform,
                    'healthy' => $is_success, 'error' => $is_success ? '' : $result['error'],
                    'bot_name' => $is_success ? ($result['result']['first_name'] ?? $result['result']['bot']['name'] ?? '') : '',
                ];
            }
        }
        wp_send_json_success(['results' => $results]);
    }

    public function handle_validate_messenger_token() {
        check_ajax_referer('ssp_secure_nonce', 'security');
        if (!is_user_logged_in()) wp_send_json_error(['message' => 'لطفاً وارد شوید']);
        $platform = sanitize_text_field($_POST['platform'] ?? '');
        $token = sanitize_text_field($_POST['token'] ?? '');
        if (empty($platform) || empty($token)) wp_send_json_error(['message' => 'پلتفرم و توکن الزامی است']);
        $test_apis = ['telegram' => 'getMe', 'bale' => 'getMe', 'eitaa' => 'getMe', 'rubika' => 'getMe'];
        if (!isset($test_apis[$platform])) wp_send_json_error(['message' => 'پلتفرم پشتیبانی نمی‌شود']);
        $result = $this->bot_api_request_with_retry($platform, $token, $test_apis[$platform]);
        $is_success = $result['success'];
        $err = strtolower($result['error'] ?? '');
        if ($platform === 'rubika' || $platform === 'eitaa') {
            if (!$is_success && strpos($err, 'unauthorized') === false && strpos($err, 'invalid token') === false) {
                $is_success = true;
            }
        }
        if ($is_success) wp_send_json_success(['valid' => true, 'bot_name' => $result['result']['first_name'] ?? $result['result']['bot']['name'] ?? '', 'bot_username' => $result['result']['username'] ?? '']);
        else wp_send_json_success(['valid' => false, 'error' => $result['error']]);
    }
}
