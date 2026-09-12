<?php

trait SSP_BotBuilder {

    private function get_user_bot_configs($user_id) {
        return is_array($__tmp = get_user_meta($user_id, 'ssp_bot_configs', true)) ? $__tmp : [];
    }

    private function set_user_bot_configs($user_id, $configs) {
        update_user_meta($user_id, 'ssp_bot_configs', $configs);
    }

    private function get_bot_config($user_id, $bot_id) {
        $configs = $this->get_user_bot_configs($user_id);
        foreach ($configs as $config) {
            if ((int)$config['id'] === (int)$bot_id) {
                return $config;
            }
        }
        return null;
    }

    private function bot_api_request($platform, $token, $method, $data = [], $proxy_args = []) {
        $endpoints = [
            'telegram' => "https://api.telegram.org/bot$token/$method",
            'bale' => "https://tapi.bale.ai/bot$token/$method",
            'eitaa' => "https://eitaayar.ir/api/$token/$method",
            'rubika' => "https://botapi.rubika.ir/v3/$token/$method",
        ];

        if (!isset($endpoints[$platform])) {
            return ['success' => false, 'error' => 'پلتفرم پشتیبانی نمی‌شود'];
        }

        $url = $endpoints[$platform];
        $args = [
            'timeout' => 30,
            'headers' => ['Content-Type' => 'application/json'],
            'body' => json_encode($data),
        ];

        // For Telegram: try multiple methods to bypass filtering
        if ($platform === 'telegram') {
            $relay_enabled = $this->is_telegram_relay_enabled();
            $doh = $this->get_doh_settings();
            $doh_enabled = $doh['enabled'];
            $doh_server = $doh['server'];
            $proxy_enabled = !empty($proxy_args['proxy']);
            $response = null;

            // 1. Try Telegram Relay first
            if ($relay_enabled) {
                $relay_result = $this->send_via_relay($method, $data);
                if ($relay_result['success']) {
                    return $relay_result;
                }
                // Relay failed, continue to other methods
            }

            // 2. Try DoH if enabled and proxy not configured
            if ($doh_enabled && !$proxy_enabled) {
                $response = $this->wp_remote_with_doh($url, $args, $doh_server, 'POST');
            }

            // 3. Fallback to direct/proxy
            if (!$response || is_wp_error($response)) {
                $response = wp_remote_post($url, array_merge($args, $proxy_args));
            }
        } else {
            $response = wp_remote_post($url, array_merge($args, $proxy_args));
        }

        if (is_wp_error($response)) {
            error_log('[SSP Bot] API request failed: ' . $response->get_error_message() . ' URL: ' . $url);
            return ['success' => false, 'error' => $response->get_error_message()];
        }

        $body = json_decode(wp_remote_retrieve_body($response), true);
        $code = wp_remote_retrieve_response_code($response);

        if ($code === 200 && (($body['ok'] ?? false) || ($body['status'] ?? '') === 'OK')) {
            return ['success' => true, 'result' => $body['result'] ?? null];
        }

        error_log('[SSP Bot] API error: ' . ($body['description'] ?? 'unknown') . ' Code: ' . $code . ' URL: ' . $url);
        return ['success' => false, 'error' => $body['description'] ?? ($body['status'] ?? 'خطای ناشناخته')];
    }

    private function bot_api_request_with_retry($platform, $token, $method, $data = [], $max_retries = SSP_BOT_RETRY_MAX_ATTEMPTS) {
        $proxy_args = $this->get_proxy_args();
        $last_error = '';

        for ($attempt = 0; $attempt < $max_retries; $attempt++) {
            if ($attempt > 0) {
                $delay = SSP_BOT_RETRY_BASE_DELAY * pow(2, $attempt - 1);
                sleep($delay);
            }

            $result = $this->bot_api_request($platform, $token, $method, $data, $proxy_args);

            if ($result['success']) {
                return $result;
            }

            $last_error = $result['error'];

            // Don't retry on auth errors
            if (strpos($last_error, 'Unauthorized') !== false || strpos($last_error, 'token') !== false) {
                return $result;
            }

            // Don't retry on 429 (rate limit) - wait longer
            if (strpos($last_error, '429') !== false || strpos($last_error, 'Too Many') !== false) {
                sleep(10);
                continue;
            }
        }

        return ['success' => false, 'error' => $last_error];
    }

    public function handle_save_bot_config() {
        $user_id = $this->ajax_require_auth();
        $configs = $this->get_user_bot_configs($user_id);
        $bot_id = intval($_POST['bot_id'] ?? 0);

        $config_data = [
            'name' => sanitize_text_field($_POST['name'] ?? 'بات جدید'),
            'platform' => sanitize_text_field($_POST['platform'] ?? 'telegram'),
            'token' => sanitize_text_field($_POST['token'] ?? ''),
            'is_active' => intval($_POST['is_active'] ?? 1),
            'welcome_message' => sanitize_textarea_field($_POST['welcome_message'] ?? ''),
            'webhook_url' => esc_url_raw($_POST['webhook_url'] ?? ''),
        ];

        if ($bot_id > 0) {
            // Update existing
            foreach ($configs as &$config) {
                if ((int)$config['id'] === $bot_id) {
                    $config = array_merge($config, $config_data);
                    break;
                }
            }
            unset($config);
        } else {
            // Create new
            $config_data['id'] = $this->next_id($configs);
            $config_data['commands'] = [];
            $config_data['buttons'] = [];
            $config_data['auto_replies'] = [];
            $config_data['created_at'] = current_time('mysql');
            $configs[] = $config_data;
        }

        $this->set_user_bot_configs($user_id, $configs);
        $this->clear_bot_config_cache($user_id, $config_data['id'] ?? $bot_id);
        wp_send_json_success(['message' => 'تنظیمات بات ذخیره شد!', 'bot_id' => $config_data['id'] ?? $bot_id]);
    }

    public function handle_delete_bot() {
        $user_id = $this->ajax_require_auth();
        $bot_id = intval($_POST['bot_id'] ?? 0);
        if ($bot_id <= 0) wp_send_json_error(['message' => 'شناسه بات نامعتبر']);

        // Clear cache before deleting
        $this->clear_bot_config_cache($user_id, $bot_id);

        $configs = $this->get_user_bot_configs($user_id);
        $configs = array_values(array_filter($configs, function($c) use ($bot_id) {
            return (int)$c['id'] !== $bot_id;
        }));
        $this->set_user_bot_configs($user_id, $configs);

        wp_send_json_success(['message' => 'بات حذف شد']);
    }

    public function handle_get_bot_config() {
        $user_id = $this->ajax_require_auth();
        $bot_id = intval($_POST['bot_id'] ?? 0);
        $config = $this->get_bot_config($user_id, $bot_id);

        if (!$config) {
            wp_send_json_error(['message' => 'بات یافت نشد']);
        }

        // Clean up old style data from buttons
        $needs_save = false;
        if (!empty($config['buttons'])) {
            foreach ($config['buttons'] as &$btn) {
                if (isset($btn['style'])) {
                    unset($btn['style']);
                    $needs_save = true;
                }
            }
            unset($btn);
            if ($needs_save) {
                $this->update_bot_config($user_id, $bot_id, $config);
            }
        }

        wp_send_json_success(['config' => $config]);
    }

    public function handle_add_bot_command() {
        $user_id = $this->ajax_require_auth();
        $bot_id = intval($_POST['bot_id'] ?? 0);
        $config = $this->get_bot_config($user_id, $bot_id);

        if (!$config) {
            wp_send_json_error(['message' => 'بات یافت نشد']);
        }

        if (count($config['commands'] ?? []) >= SSP_BOT_BUILDER_MAX_COMMANDS) {
            wp_send_json_error(['message' => 'حداکثر ' . SSP_BOT_BUILDER_MAX_COMMANDS . ' دستور می‌توانید اضافه کنید']);
        }

        $command = [
            'id' => $this->next_id($config['commands'] ?? []),
            'command' => sanitize_text_field($_POST['command'] ?? ''),
            'description' => sanitize_text_field($_POST['description'] ?? ''),
            'response' => sanitize_textarea_field($_POST['response'] ?? ''),
            'is_active' => 1,
        ];

        $config['commands'][] = $command;
        $this->update_bot_config($user_id, $bot_id, $config);
        $this->sync_bot_commands($config);

        wp_send_json_success(['message' => 'دستور اضافه شد و با تلگرام هماهنگ شد!', 'command' => $command]);
    }

    public function handle_delete_bot_command() {
        $user_id = $this->ajax_require_auth();
        $bot_id = intval($_POST['bot_id'] ?? 0);
        $command_id = intval($_POST['command_id'] ?? 0);
        $config = $this->get_bot_config($user_id, $bot_id);

        if (!$config) {
            wp_send_json_error(['message' => 'بات یافت نشد']);
        }

        $config['commands'] = array_values(array_filter($config['commands'] ?? [], function($c) use ($command_id) {
            return (int)$c['id'] !== $command_id;
        }));

        $this->update_bot_config($user_id, $bot_id, $config);
        $this->sync_bot_commands($config);
        wp_send_json_success(['message' => 'دستور حذف شد و با تلگرام هماهنگ شد']);
    }

    public function handle_update_bot_command() {
        $user_id = $this->ajax_require_auth();
        $bot_id = intval($_POST['bot_id'] ?? 0);
        $command_id = intval($_POST['command_id'] ?? 0);
        $config = $this->get_bot_config($user_id, $bot_id);

        if (!$config) {
            wp_send_json_error(['message' => 'بات یافت نشد']);
        }

        foreach ($config['commands'] as &$cmd) {
            if ((int)$cmd['id'] === $command_id) {
                $cmd['command'] = sanitize_text_field($_POST['command'] ?? $cmd['command']);
                $cmd['description'] = sanitize_text_field($_POST['description'] ?? $cmd['description']);
                $cmd['response'] = sanitize_textarea_field($_POST['response'] ?? $cmd['response']);
                $cmd['is_active'] = intval($_POST['is_active'] ?? $cmd['is_active']);
                break;
            }
        }
        unset($cmd);

        $this->update_bot_config($user_id, $bot_id, $config);
        $this->sync_bot_commands($config);
        wp_send_json_success(['message' => 'دستور به‌روزرسانی شد و با تلگرام هماهنگ شد']);
    }

    public function handle_add_bot_button() {
        $user_id = $this->ajax_require_auth();
        $bot_id = intval($_POST['bot_id'] ?? 0);
        $config = $this->get_bot_config($user_id, $bot_id);

        if (!$config) {
            wp_send_json_error(['message' => 'بات یافت نشد']);
        }

        if (count($config['buttons'] ?? []) >= SSP_BOT_BUILDER_MAX_BUTTONS) {
            wp_send_json_error(['message' => 'حداکثر ' . SSP_BOT_BUILDER_MAX_BUTTONS . ' دکمه می‌توانید اضافه کنید']);
        }

        $keyboard_type = sanitize_text_field($_POST['keyboard_type'] ?? 'inline'); // inline, reply

        $button = [
            'id' => $this->next_id($config['buttons'] ?? []),
            'text' => sanitize_text_field($_POST['text'] ?? ''),
            'type' => sanitize_text_field($_POST['type'] ?? 'simple'),
            'value' => sanitize_text_field($_POST['value'] ?? ''),
            'row' => intval($_POST['row'] ?? 0),
            'col' => intval($_POST['col'] ?? 0),
            'keyboard_type' => $keyboard_type,
            'is_active' => 1,
        ];

        // Note: Style options removed due to Bot API incompatibility
        // The style field was causing "Field style must be of type String" errors

        // Type-specific options
        switch ($button['type']) {
            case 'url':
                $button['value'] = esc_url_raw($_POST['value'] ?? '');
                break;

            case 'callback':
                $button['value'] = sanitize_text_field($_POST['value'] ?? '');
                $button['requires_password'] = !empty($_POST['requires_password']) ? 1 : 0;
                break;

            case 'web_app':
                $button['value'] = esc_url_raw($_POST['value'] ?? ''); // Web App URL
                break;

            case 'login_url':
                $button['value'] = esc_url_raw($_POST['value'] ?? ''); // Login URL
                $button['bot_username'] = sanitize_text_field($_POST['bot_username'] ?? '');
                $button['forward_text'] = sanitize_text_field($_POST['forward_text'] ?? '');
                break;

            case 'switch_inline':
                $button['value'] = sanitize_text_field($_POST['value'] ?? ''); // Query to insert
                $button['switch_to_chat'] = !empty($_POST['switch_to_chat']) ? 1 : 0;
                break;

            case 'request_contact':
            case 'request_location':
            case 'request_poll':
                // These don't need a value
                break;

            case 'request_peer':
                $button['peer_type'] = sanitize_text_field($_POST['peer_type'] ?? 'user'); // user, chat, channel
                $button['max_quantity'] = intval($_POST['max_quantity'] ?? 1);
                $button['name_requested'] = !empty($_POST['name_requested']) ? 1 : 0;
                $button['username_requested'] = !empty($_POST['username_requested']) ? 1 : 0;
                $button['photo_requested'] = !empty($_POST['photo_requested']) ? 1 : 0;
                break;

            case 'pay':
                // Payment button - no additional data needed
                break;

            default: // simple
                $button['value'] = sanitize_text_field($_POST['value'] ?? '');
                break;
        }

        // Handle child buttons (button tree) with limit
        $next_buttons_json = sanitize_textarea_field($_POST['next_buttons'] ?? '');
        if (!empty($next_buttons_json)) {
            $next_buttons = json_decode($next_buttons_json, true);
            if (is_array($next_buttons)) {
                // Limit child buttons
                $next_buttons = array_slice($next_buttons, 0, SSP_BOT_BUILDER_MAX_CHILD_BUTTONS);
                foreach ($next_buttons as &$nb) {
                    $nb['text'] = sanitize_text_field($nb['text'] ?? '');
                    $nb['value'] = sanitize_text_field($nb['value'] ?? '');
                }
                unset($nb);
                $button['next_buttons'] = $next_buttons;
            }
        }

        $config['buttons'][] = $button;
        $this->update_bot_config($user_id, $bot_id, $config);

        wp_send_json_success(['message' => 'دکمه اضافه شد!', 'button' => $button]);
    }

    public function handle_delete_bot_button() {
        $user_id = $this->ajax_require_auth();
        $bot_id = intval($_POST['bot_id'] ?? 0);
        $button_id = intval($_POST['button_id'] ?? 0);
        $config = $this->get_bot_config($user_id, $bot_id);

        if (!$config) {
            wp_send_json_error(['message' => 'بات یافت نشد']);
        }

        $config['buttons'] = array_values(array_filter($config['buttons'] ?? [], function($b) use ($button_id) {
            return (int)$b['id'] !== $button_id;
        }));

        $this->update_bot_config($user_id, $bot_id, $config);
        wp_send_json_success(['message' => 'دکمه حذف شد']);
    }

    public function handle_update_bot_button() {
        $user_id = $this->ajax_require_auth();
        $bot_id = intval($_POST['bot_id'] ?? 0);
        $button_id = intval($_POST['button_id'] ?? 0);
        $config = $this->get_bot_config($user_id, $bot_id);

        if (!$config) {
            wp_send_json_error(['message' => 'بات یافت نشد']);
        }

        foreach ($config['buttons'] as &$btn) {
            if ((int)$btn['id'] === $button_id) {
                $btn['text'] = sanitize_text_field($_POST['text'] ?? $btn['text']);
                $btn['type'] = sanitize_text_field($_POST['type'] ?? $btn['type']);
                $btn['value'] = sanitize_text_field($_POST['value'] ?? $btn['value']);
                $btn['row'] = intval($_POST['row'] ?? $btn['row']);
                $btn['col'] = intval($_POST['col'] ?? $btn['col'] ?? 0);
                $btn['keyboard_type'] = sanitize_text_field($_POST['keyboard_type'] ?? $btn['keyboard_type'] ?? 'inline');
                $btn['is_active'] = intval($_POST['is_active'] ?? $btn['is_active']);

                // Note: Style options removed due to Bot API incompatibility

                // Type-specific updates
                switch ($btn['type']) {
                    case 'callback':
                        $btn['requires_password'] = !empty($_POST['requires_password']) ? 1 : 0;
                        break;
                    case 'login_url':
                        $btn['bot_username'] = sanitize_text_field($_POST['bot_username'] ?? '');
                        $btn['forward_text'] = sanitize_text_field($_POST['forward_text'] ?? '');
                        break;
                    case 'switch_inline':
                        $btn['switch_to_chat'] = !empty($_POST['switch_to_chat']) ? 1 : 0;
                        break;
                    case 'request_peer':
                        $btn['peer_type'] = sanitize_text_field($_POST['peer_type'] ?? 'user');
                        $btn['max_quantity'] = intval($_POST['max_quantity'] ?? 1);
                        $btn['name_requested'] = !empty($_POST['name_requested']) ? 1 : 0;
                        $btn['username_requested'] = !empty($_POST['username_requested']) ? 1 : 0;
                        $btn['photo_requested'] = !empty($_POST['photo_requested']) ? 1 : 0;
                        break;
                }

                // Handle child buttons (button tree)
                if (isset($_POST['next_buttons'])) {
                    $next_buttons_json = sanitize_textarea_field($_POST['next_buttons']);
                    if (!empty($next_buttons_json)) {
                        $next_buttons = json_decode($next_buttons_json, true);
                        if (is_array($next_buttons)) {
                            foreach ($next_buttons as &$nb) {
                                $nb['text'] = sanitize_text_field($nb['text'] ?? '');
                                $nb['value'] = sanitize_text_field($nb['value'] ?? '');
                            }
                            unset($nb);
                            $btn['next_buttons'] = $next_buttons;
                        }
                    } else {
                        unset($btn['next_buttons']);
                    }
                }

                break;
            }
        }
        unset($btn);

        $this->update_bot_config($user_id, $bot_id, $config);
        wp_send_json_success(['message' => 'دکمه به‌روزرسانی شد']);
    }

    public function handle_add_bot_auto_reply() {
        $user_id = $this->ajax_require_auth();
        $bot_id = intval($_POST['bot_id'] ?? 0);
        $config = $this->get_bot_config($user_id, $bot_id);

        if (!$config) {
            wp_send_json_error(['message' => 'بات یافت نشد']);
        }

        if (count($config['auto_replies'] ?? []) >= SSP_BOT_BUILDER_MAX_AUTO_REPLIES) {
            wp_send_json_error(['message' => 'حداکثر ' . SSP_BOT_BUILDER_MAX_AUTO_REPLIES . ' پاسخ خودکار می‌توانید اضافه کنید']);
        }

        $reply = [
            'id' => $this->next_id($config['auto_replies'] ?? []),
            'trigger' => sanitize_text_field($_POST['trigger'] ?? ''),
            'match_type' => sanitize_text_field($_POST['match_type'] ?? 'exact'), // exact, contains, starts_with
            'response' => sanitize_textarea_field($_POST['response'] ?? ''),
            'is_active' => 1,
        ];

        $config['auto_replies'][] = $reply;
        $this->update_bot_config($user_id, $bot_id, $config);

        wp_send_json_success(['message' => 'پاسخ خودکار اضافه شد!', 'reply' => $reply]);
    }

    public function handle_delete_bot_auto_reply() {
        $user_id = $this->ajax_require_auth();
        $bot_id = intval($_POST['bot_id'] ?? 0);
        $reply_id = intval($_POST['reply_id'] ?? 0);
        $config = $this->get_bot_config($user_id, $bot_id);

        if (!$config) {
            wp_send_json_error(['message' => 'بات یافت نشد']);
        }

        $config['auto_replies'] = array_values(array_filter($config['auto_replies'] ?? [], function($r) use ($reply_id) {
            return (int)$r['id'] !== $reply_id;
        }));

        $this->update_bot_config($user_id, $bot_id, $config);
        wp_send_json_success(['message' => 'پاسخ خودکار حذف شد']);
    }

    public function handle_update_bot_auto_reply() {
        $user_id = $this->ajax_require_auth();
        $bot_id = intval($_POST['bot_id'] ?? 0);
        $reply_id = intval($_POST['reply_id'] ?? 0);
        $config = $this->get_bot_config($user_id, $bot_id);

        if (!$config) {
            wp_send_json_error(['message' => 'بات یافت نشد']);
        }

        foreach ($config['auto_replies'] as &$reply) {
            if ((int)$reply['id'] === $reply_id) {
                $reply['trigger'] = sanitize_text_field($_POST['trigger'] ?? $reply['trigger']);
                $reply['match_type'] = sanitize_text_field($_POST['match_type'] ?? $reply['match_type']);
                $reply['response'] = sanitize_textarea_field($_POST['response'] ?? $reply['response']);
                $reply['is_active'] = intval($_POST['is_active'] ?? $reply['is_active']);
                break;
            }
        }
        unset($reply);

        $this->update_bot_config($user_id, $bot_id, $config);
        wp_send_json_success(['message' => 'پاسخ خودکار به‌روزرسانی شد']);
    }

    public function handle_set_bot_welcome() {
        $user_id = $this->ajax_require_auth();
        $bot_id = intval($_POST['bot_id'] ?? 0);
        $config = $this->get_bot_config($user_id, $bot_id);

        if (!$config) {
            wp_send_json_error(['message' => 'بات یافت نشد']);
        }

        $config['welcome_message'] = sanitize_textarea_field($_POST['welcome_message'] ?? '');
        $this->update_bot_config($user_id, $bot_id, $config);

        wp_send_json_success(['message' => 'پیام خوش‌آمدگویی ذخیره شد']);
    }

    public function handle_set_bot_menu() {
        $user_id = $this->ajax_require_auth();
        $bot_id = intval($_POST['bot_id'] ?? 0);
        $config = $this->get_bot_config($user_id, $bot_id);

        if (!$config) {
            wp_send_json_error(['message' => 'بات یافت نشد']);
        }

        // Build menu commands from bot commands
        $commands = $config['commands'] ?? [];
        $menu_commands = [];
        foreach ($commands as $cmd) {
            if (!empty($cmd['is_active']) && !empty($cmd['command'])) {
                $menu_commands[] = [
                    'command' => '/' . ltrim($cmd['command'], '/'),
                    'description' => $cmd['description'] ?: $cmd['command'],
                ];
            }
        }

        // Set menu via API
        $result = $this->bot_api_request_with_retry(
            $config['platform'],
            $config['token'],
            'setMyCommands',
            ['commands' => $menu_commands]
        );

        if ($result['success']) {
            wp_send_json_success(['message' => 'منوی بات با موفقیت تنظیم شد!']);
        } else {
            wp_send_json_error(['message' => 'خطا در تنظیم منو: ' . $result['error']]);
        }
    }

    public function handle_test_bot_webhook() {
        $user_id = $this->ajax_require_auth();
        $bot_id = intval($_POST['bot_id'] ?? 0);

        if ($bot_id <= 0) {
            wp_send_json_error(['message' => 'شناسه بات نامعتبر']);
        }

        $this->clear_bot_config_cache($user_id, $bot_id);
        $config = $this->get_bot_config($user_id, $bot_id);

        if (!$config) {
            wp_send_json_error(['message' => 'بات یافت نشد (ID: ' . $bot_id . ')']);
        }

        if (empty($config['token'])) {
            wp_send_json_error(['message' => 'توکن بات تنظیم نشده']);
        }

        $result = $this->bot_api_request_with_retry(
            $config['platform'],
            $config['token'],
            'getWebhookInfo'
        );

        if ($result['success']) {
            $info = $result['result'];
            wp_send_json_success([
                'message' => 'اطلاعات webhook دریافت شد',
                'url' => $info['url'] ?? 'تنظیم نشده',
                'pending_count' => $info['pending_update_count'] ?? 0,
                'has_error' => !empty($info['last_error_date']),
                'last_error' => $info['last_error_message'] ?? '',
            ]);
        } else {
            wp_send_json_error(['message' => 'خطا: ' . $result['error']]);
        }
    }

    public function handle_set_bot_webhook() {
        $user_id = $this->ajax_require_auth();
        $bot_id = intval($_POST['bot_id'] ?? 0);
        $webhook_url = esc_url_raw($_POST['webhook_url'] ?? '');

        if ($bot_id <= 0) {
            wp_send_json_error(['message' => 'شناسه بات نامعتبر']);
        }

        // Clear cache and reload config
        $this->clear_bot_config_cache($user_id, $bot_id);
        $config = $this->get_bot_config($user_id, $bot_id);

        if (!$config) {
            wp_send_json_error(['message' => 'بات یافت نشد (ID: ' . $bot_id . ')']);
        }

        if (empty($config['token'])) {
            wp_send_json_error(['message' => 'توکن بات تنظیم نشده']);
        }

        // Log the webhook URL for debugging
        error_log('[SSP Bot] Setting webhook for bot_id=' . $bot_id . ' URL: ' . $webhook_url);

        if (empty($webhook_url)) {
            // Remove webhook
            $result = $this->bot_api_request_with_retry(
                $config['platform'],
                $config['token'],
                'deleteWebhook'
            );
        } else {
            // Validate webhook URL
            if (strpos($webhook_url, 'https://') !== 0) {
                wp_send_json_error(['message' => 'آدرس وبهوک باید با https:// شروع شود']);
            }

            // Set webhook with all necessary parameters
            $webhook_data = [
                'url' => $webhook_url,
                'allowed_updates' => ['message', 'callback_query', 'inline_query', 'chosen_inline_result'],
                'drop_pending_updates' => false,
            ];

            $result = $this->bot_api_request_with_retry(
                $config['platform'],
                $config['token'],
                'setWebhook',
                $webhook_data
            );
        }

        if ($result['success']) {
            $config['webhook_url'] = $webhook_url;
            $this->update_bot_config($user_id, $bot_id, $config);
            error_log('[SSP Bot] Webhook set successfully for bot_id=' . $bot_id);
            wp_send_json_success(['message' => $webhook_url ? 'Webhook تنظیم شد' : 'Webhook حذف شد']);
        } else {
            error_log('[SSP Bot] Webhook failed for bot_id=' . $bot_id . ' Error: ' . $result['error']);
            $error_message = $result['error'];
            // Provide more helpful error messages
            if (strpos($error_message, 'Unauthorized') !== false) {
                $error_message = 'توکن بات نامعتبر است';
            } elseif (strpos($error_message, 'Bad Request') !== false) {
                $error_message = 'آدرس وبهوک نامعتبر است: ' . $error_message;
            } elseif (strpos($error_message, 'Forbidden') !== false) {
                $error_message = 'دسترسی به API تلگرام مسدود شده است';
            }
            wp_send_json_error(['message' => 'خطا: ' . $error_message]);
        }
    }

    public function handle_get_bot_stats() {
        $user_id = $this->ajax_require_auth();
        $bot_id = intval($_POST['bot_id'] ?? 0);
        $config = $this->get_bot_config($user_id, $bot_id);

        if (!$config) {
            wp_send_json_error(['message' => 'بات یافت نشد']);
        }

        // Get bot info
        $result = $this->bot_api_request_with_retry(
            $config['platform'],
            $config['token'],
            'getMe'
        );

        if ($result['success']) {
            $bot_info = $result['result'];
            wp_send_json_success([
                'bot_info' => [
                    'name' => $bot_info['first_name'] ?? '',
                    'username' => $bot_info['username'] ?? '',
                    'id' => $bot_info['id'] ?? '',
                    'is_bot' => $bot_info['is_bot'] ?? false,
                ],
                'commands_count' => count($config['commands'] ?? []),
                'buttons_count' => count($config['buttons'] ?? []),
                'auto_replies_count' => count($config['auto_replies'] ?? []),
            ]);
        } else {
            wp_send_json_error(['message' => 'خطا در دریافت اطلاعات بات: ' . $result['error']]);
        }
    }

    public function handle_generate_bot_code() {
        $user_id = $this->ajax_require_auth();
        $bot_id = intval($_POST['bot_id'] ?? 0);
        $config = $this->get_bot_config($user_id, $bot_id);

        if (!$config) {
            wp_send_json_error(['message' => 'بات یافت نشد']);
        }

        $code = $this->generate_bot_webhook_code($config);
        wp_send_json_success(['code' => $code]);
    }

    private function generate_bot_webhook_code($config) {
        $platform = $config['platform'];
        $token = $config['token'];
        $commands = $config['commands'] ?? [];
        $auto_replies = $config['auto_replies'] ?? [];
        $buttons = $config['buttons'] ?? [];
        $welcome = $config['welcome_message'] ?? '';

        $webhook_endpoint = home_url('/wp-json/ssp/v1/bot-webhook/' . $config['id']);

        $code = '<?php';
        $code .= "\n/**";
        $code .= "\n * Webhook Handler for " . ucfirst($platform) . " Bot";
        $code .= "\n * Generated by Smart Automation Pro";
        $code .= "\n * Endpoint: $webhook_endpoint";
        $code .= "\n */";
        $code .= "\n";
        $code .= "\nadd_action('rest_api_init', function() {";
        $code .= "\n    register_rest_route('ssp/v1', '/bot-webhook/" . $config['id'] . "', [";
        $code .= "\n        'methods' => 'POST',";
        $code .= "\n        'callback' => 'ssp_bot_webhook_handler_" . $config['id'] . "',";
        $code .= "\n        'permission_callback' => '__return_true';";
        $code .= "\n    ]);";
        $code .= "\n});";
        $code .= "\n";
        $code .= "\nfunction ssp_bot_webhook_handler_" . $config['id'] . "(\$request) {";
        $code .= "\n    \$body = json_decode(\$request->get_body(), true);";
        $code .= "\n    \$token = '" . $token . "';";
        $code .= "\n    \$api_base = '" . ($platform === 'telegram' ? 'https://api.telegram.org/bot' : 'https://tapi.bale.ai/bot') . "';";
        $code .= "\n";

        // Handle callback queries
        $code .= "\n    // Handle callback queries (inline keyboard)";
        $code .= "\n    if (isset(\$body['callback_query'])) {";
        $code .= "\n        \$callback_query_id = \$body['callback_query']['id'];";
        $code .= "\n        \$callback_data = \$body['callback_query']['data'] ?? '';";
        $code .= "\n        \$chat_id = \$body['callback_query']['message']['chat']['id'];";
        $code .= "\n";
        $code .= "\n        // Answer callback query";
        $code .= "\n        ssp_bot_answer_callback(\$api_base, \$token, \$callback_query_id);";
        $code .= "\n";

        // Add callback button handlers
        foreach ($buttons as $btn) {
            if (!empty($btn['is_active']) && $btn['type'] === 'callback' && $btn['keyboard_type'] === 'inline') {
                $callback_value = addslashes($btn['value']);
                // Check if there's a matching command
                $response = '';
                foreach ($commands as $cmd) {
                    if (strtolower(ltrim($cmd['command'], '/')) === strtolower($btn['value'])) {
                        $response = addslashes($cmd['response']);
                        break;
                    }
                }
                if (empty($response) && !empty($btn['response_message'])) {
                    $response = addslashes($btn['response_message']);
                }
                if (!empty($response)) {
                    $code .= "\n        if (\$callback_data === '$callback_value') {";
                    $code .= "\n            ssp_bot_send_message(\$api_base, \$token, \$chat_id, '$response');";
                    $code .= "\n            return new WP_REST_Response(['ok' => true]);";
                    $code .= "\n        }";
                    $code .= "\n";
                }
            }
        }

        $code .= "\n        return new WP_REST_Response(['ok' => true]);";
        $code .= "\n    }";
        $code .= "\n";

        // Welcome message handler with keyboard
        if (!empty($welcome)) {
            $code .= "\n    // Handle /start command";
            $code .= "\n    if (isset(\$body['message']['text']) && \$body['message']['text'] === '/start') {";
            $code .= "\n        \$chat_id = \$body['message']['chat']['id'];";
            $code .= "\n        \$keyboard = ssp_bot_build_keyboard();";
            $code .= "\n        ssp_bot_send_message(\$api_base, \$token, \$chat_id, '" . addslashes($welcome) . "', \$keyboard);";
            $code .= "\n        return new WP_REST_Response(['ok' => true]);";
            $code .= "\n    }";
            $code .= "\n";
        }

        // Command handlers with keyboard
        foreach ($commands as $cmd) {
            if (!empty($cmd['is_active']) && !empty($cmd['command'])) {
                $cmd_name = ltrim($cmd['command'], '/');
                $response = addslashes($cmd['response']);
                $code .= "\n    // Handle /$cmd_name command";
                $code .= "\n    if (isset(\$body['message']['text']) && \$body['message']['text'] === '/$cmd_name') {";
                $code .= "\n        \$chat_id = \$body['message']['chat']['id'];";
                $code .= "\n        \$keyboard = ssp_bot_build_keyboard();";
                $code .= "\n        ssp_bot_send_message(\$api_base, \$token, \$chat_id, '$response', \$keyboard);";
                $code .= "\n        return new WP_REST_Response(['ok' => true]);";
                $code .= "\n    }";
                $code .= "\n";
            }
        }

        // Auto reply handlers with keyboard
        foreach ($auto_replies as $reply) {
            if (!empty($reply['is_active']) && !empty($reply['trigger'])) {
                $trigger = addslashes($reply['trigger']);
                $response = addslashes($reply['response']);
                $match_type = $reply['match_type'] ?? 'exact';

                $code .= "\n    // Auto reply: $trigger ($match_type)";
                $code .= "\n    if (isset(\$body['message']['text'])) {";
                $code .= "\n        \$text = \$body['message']['text'];";

                if ($match_type === 'exact') {
                    $code .= "\n        if (\$text === '$trigger') {";
                } elseif ($match_type === 'contains') {
                    $code .= "\n        if (strpos(\$text, '$trigger') !== false) {";
                } else { // starts_with
                    $code .= "\n        if (strpos(\$text, '$trigger') === 0) {";
                }

                $code .= "\n            \$chat_id = \$body['message']['chat']['id'];";
                $code .= "\n            \$keyboard = ssp_bot_build_keyboard();";
                $code .= "\n            ssp_bot_send_message(\$api_base, \$token, \$chat_id, '$response', \$keyboard);";
                $code .= "\n            return new WP_REST_Response(['ok' => true]);";
                $code .= "\n        }";
                $code .= "\n    }";
                $code .= "\n";
            }
        }

        // Helper functions
        $code .= "\n    return new WP_REST_Response(['ok' => true]);";
        $code .= "\n}";
        $code .= "\n";

        // Build keyboard function
        $code .= "\nfunction ssp_bot_build_keyboard() {";
        $code .= "\n    \$inline_rows = [];";
        $code .= "\n    \$reply_rows = [];";
        $code .= "\n";

        // Inline keyboard only allows specific button types
        $inline_allowed_types = ['url', 'callback', 'web_app', 'login_url', 'switch_inline', 'pay'];

        foreach ($buttons as $btn) {
            if (empty($btn['is_active'])) continue;
            $keyboard_type = $btn['keyboard_type'] ?? 'inline';
            $row = $btn['row'] ?? 0;
            $btn_text = addslashes($btn['text']);

            // Skip simple buttons for inline keyboard - they're not allowed
            if ($keyboard_type === 'inline' && !in_array($btn['type'] ?? 'simple', $inline_allowed_types)) {
                continue;
            }

            $code .= "\n    // Button: $btn_text";
            $code .= "\n    \$btn_obj = ['text' => '$btn_text'];";

            // Note: style is removed to avoid Bot API errors

            switch ($btn['type']) {
                case 'url':
                    $url = addslashes($btn['value']);
                    $code .= "\n    \$btn_obj['url'] = '$url';";
                    break;
                case 'callback':
                    $data = addslashes($btn['value']);
                    $code .= "\n    \$btn_obj['callback_data'] = '$data';";
                    break;
                case 'web_app':
                    $webapp_url = addslashes($btn['value']);
                    $code .= "\n    \$btn_obj['web_app'] = ['url' => '$webapp_url'];";
                    break;
                case 'login_url':
                    $login_url = addslashes($btn['value']);
                    $code .= "\n    \$btn_obj['login_url'] = ['url' => '$login_url'];";
                    break;
                case 'switch_inline':
                    $query = addslashes($btn['value'] ?? '');
                    $code .= "\n    \$btn_obj['switch_inline_query'] = '$query';";
                    break;
                case 'request_contact':
                    $code .= "\n    \$btn_obj['request_contact'] = true;";
                    break;
                case 'request_location':
                    $code .= "\n    \$btn_obj['request_location'] = true;";
                    break;
                case 'pay':
                    $code .= "\n    \$btn_obj['pay'] = true;";
                    break;
            }

            if ($keyboard_type === 'inline') {
                $code .= "\n    if (!isset(\$inline_rows[$row])) \$inline_rows[$row] = [];";
                $code .= "\n    \$inline_rows[$row][] = \$btn_obj;";
            } else {
                $code .= "\n    if (!isset(\$reply_rows[$row])) \$reply_rows[$row] = [];";
                $code .= "\n    \$reply_rows[$row][] = \$btn_obj;";
            }
            $code .= "\n";
        }

        $code .= "\n    \$markup = null;";
        $code .= "\n    if (!empty(\$inline_rows)) {";
        $code .= "\n        ksort(\$inline_rows);";
        $code .= "\n        \$markup = ['inline_keyboard' => array_values(\$inline_rows)];";
        $code .= "\n    }";
        $code .= "\n    if (!empty(\$reply_rows)) {";
        $code .= "\n        ksort(\$reply_rows);";
        $code .= "\n        \$markup = ['keyboard' => array_values(\$reply_rows), 'resize_keyboard' => true];";
        $code .= "\n    }";
        $code .= "\n    return \$markup;";
        $code .= "\n}";
        $code .= "\n";

        // Send message function with keyboard support
        $code .= "\nfunction ssp_bot_send_message(\$api_base, \$token, \$chat_id, \$text, \$keyboard = null) {";
        $code .= "\n    \$data = ['chat_id' => \$chat_id, 'text' => \$text, 'parse_mode' => 'HTML'];";
        $code .= "\n    if (\$keyboard) {";
        $code .= "\n        \$data['reply_markup'] = json_encode(\$keyboard);";
        $code .= "\n    }";
        $code .= "\n    wp_remote_post(\$api_base . \$token . '/sendMessage', [";
        $code .= "\n        'timeout' => 10,";
        $code .= "\n        'headers' => ['Content-Type' => 'application/json'],";
        $code .= "\n        'body' => json_encode(\$data),";
        $code .= "\n    ]);";
        $code .= "\n}";
        $code .= "\n";

        // Answer callback function
        $code .= "\nfunction ssp_bot_answer_callback(\$api_base, \$token, \$callback_query_id) {";
        $code .= "\n    wp_remote_post(\$api_base . \$token . '/answerCallbackQuery', [";
        $code .= "\n        'timeout' => 10,";
        $code .= "\n        'headers' => ['Content-Type' => 'application/json'],";
        $code .= "\n        'body' => json_encode(['callback_query_id' => \$callback_query_id]),";
        $code .= "\n    ]);";
        $code .= "\n}";

        return $code;
    }

    private function sync_bot_commands($config) {
        if (empty($config['token']) || empty($config['platform'])) return;
        $commands = $config['commands'] ?? [];
        $menu_commands = [];
        foreach ($commands as $cmd) {
            if (!empty($cmd['is_active']) && !empty($cmd['command'])) {
                $menu_commands[] = [
                    'command' => '/' . ltrim($cmd['command'], '/'),
                    'description' => $cmd['description'] ?: $cmd['command'],
                ];
            }
        }
        $this->bot_api_request_with_retry(
            $config['platform'],
            $config['token'],
            'setMyCommands',
            ['commands' => $menu_commands]
        );
    }

    private function update_bot_config($user_id, $bot_id, $config) {
        $configs = $this->get_user_bot_configs($user_id);
        foreach ($configs as &$c) {
            if ((int)$c['id'] === (int)$bot_id) {
                $c = $config;
                break;
            }
        }
        unset($c);
        $this->set_user_bot_configs($user_id, $configs);
        $this->clear_bot_config_cache($user_id, $bot_id);
    }

    private function get_bot_config_cached($user_id, $bot_id) {
        $cache_key = "ssp_bot_{$user_id}_{$bot_id}";
        $cached = wp_cache_get($cache_key, 'ssp');

        if ($cached !== false) {
            return $cached;
        }

        $config = $this->get_bot_config($user_id, $bot_id);
        if ($config) {
            wp_cache_set($cache_key, $config, 'ssp', SSP_BOT_CONFIG_CACHE_TTL);
        }

        return $config;
    }

    private function clear_bot_config_cache($user_id, $bot_id) {
        $cache_key = "ssp_bot_{$user_id}_{$bot_id}";
        wp_cache_delete($cache_key, 'ssp');
    }

    private function check_bot_limits($user_id) {
        $configs = $this->get_user_bot_configs($user_id);
        return count($configs) < SSP_MAX_BOTS_PER_USER;
    }

    public function handle_bot_webhook($request) {
        $client_ip = $request->get_header('REMOTE_ADDR') ?? $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
        if (!$this->check_webhook_rate_limit($client_ip)) {
            error_log('[SSP Bot] Rate limit exceeded for IP: ' . $client_ip);
            return new \WP_REST_Response(['ok' => false, 'error' => 'Rate limit exceeded'], 429);
        }

        $bot_id = intval($request->get_param('bot_id'));
        $body = json_decode($request->get_body(), true);

        if (!$body || !$bot_id) {
            return new \WP_REST_Response(['ok' => false, 'error' => 'Invalid request'], 400);
        }

        $body = $this->sanitize_webhook_input($body);

        // Log incoming webhook for debugging
        error_log('[SSP Bot] Webhook received for bot_id=' . $bot_id . ' from IP: ' . $client_ip);

        // Find bot owner via direct DB query (avoids scanning all users)
        global $wpdb;
        $owner_id = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT user_id FROM {$wpdb->usermeta} 
             WHERE meta_key = 'ssp_bot_configs' 
             AND meta_value LIKE %s LIMIT 1", '%"id";i:' . intval($bot_id) . ';%'
        ));

        if (!$owner_id) {
            error_log('[SSP Bot] Bot owner not found. bot_id=' . $bot_id);
            return new \WP_REST_Response(['ok' => true]);
        }

        // Get bot config from owner (with caching)
        $found_config = $this->get_bot_config_cached($owner_id, $bot_id);

        if (!$found_config || empty($found_config['is_active'])) {
            error_log('[SSP Bot] Bot not found or inactive. bot_id=' . $bot_id . ' owner_id=' . $owner_id);
            return new \WP_REST_Response(['ok' => true]);
        }

        error_log('[SSP Bot] Bot found: user_id=' . $owner_id . ' bot_id=' . $bot_id . ' platform=' . $found_config['platform']);

        $platform = $found_config['platform'];
        $token = $found_config['token'];

        // Clean up old style data from buttons to prevent API errors
        if (!empty($found_config['buttons'])) {
            foreach ($found_config['buttons'] as &$btn) {
                if (isset($btn['style'])) {
                    unset($btn['style']);
                }
            }
            unset($btn);
        }

        $message = $body['message'] ?? $body['callback_query']['message'] ?? null;
        $chat_id = isset($message['chat']['id']) ? intval($message['chat']['id']) : null;
        $text = isset($message['text']) ? sanitize_text_field($message['text']) : (isset($body['callback_query']['data']) ? sanitize_text_field($body['callback_query']['data']) : null);
        $from_id = isset($body['callback_query']['from']['id']) ? intval($body['callback_query']['from']['id']) : (isset($message['from']['id']) ? intval($message['from']['id']) : null);

        // Extract Telegram user info for placeholders
        $from_data = $body['callback_query']['from'] ?? $message['from'] ?? [];
        $telegram_user = [
            'first_name' => sanitize_text_field($from_data['first_name'] ?? ''),
            'last_name' => sanitize_text_field($from_data['last_name'] ?? ''),
            'username' => sanitize_text_field($from_data['username'] ?? ''),
        ];

        if (!$chat_id) {
            return new \WP_REST_Response(['ok' => true]);
        }

        // Handle callback query (inline keyboard buttons)
        if (isset($body['callback_query'])) {
            $callback_query_id = $body['callback_query']['id'] ?? '';
            $callback_data = $body['callback_query']['data'] ?? '';

            // Answer callback query to remove loading indicator
            if (!empty($callback_query_id)) {
                $this->bot_api_request($platform, $token, 'answerCallbackQuery', [
                    'callback_query_id' => $callback_query_id,
                ]);
            }

            // Handle back button (format: back:parent_value)
            if (strpos($callback_data, 'back:') === 0) {
                $parent_value = substr($callback_data, 5);
                $all_buttons = $found_config['buttons'] ?? [];

                // Find the parent button and show its siblings
                $parent_btn = $this->find_parent_button($all_buttons, $parent_value);
                if ($parent_btn && !empty($parent_btn['next_buttons'])) {
                    // Find the grandparent to enable going further back
                    $grandparent = $this->find_parent_button($all_buttons, $parent_btn['value']);
                    $back_value = $grandparent ? $grandparent['value'] : '';

                    $response_text = $this->replace_bot_placeholders($parent_btn['response_message'] ?? $parent_btn['text'], $owner_id, $found_config, $telegram_user);
                    $keyboard = $this->build_child_keyboard($parent_btn['next_buttons'], $back_value, $found_config);
                    $send_data = [
                        'chat_id' => $chat_id,
                        'text' => $response_text,
                        'parse_mode' => 'HTML',
                    ];
                    if ($keyboard) {
                        $send_data['reply_markup'] = $keyboard;
                    }
                    $this->bot_api_request($platform, $token, 'sendMessage', $send_data);
                } else {
                    // Fallback to main menu
                    $this->send_bot_message($platform, $token, $chat_id, $found_config['welcome_message'] ?? 'منوی اصلی:', $found_config);
                }
                return new \WP_REST_Response(['ok' => true]);
            }

            // Handle scenario callbacks (format: sc:step_id:action)
            if (strpos($callback_data, 'sc:') === 0) {
                $parts = explode(':', $callback_data, 3);
                $next_step_id = $parts[1] ?? 'end';
                $action = $parts[2] ?? '';

                // Execute action if specified
                if (!empty($action) && $action !== 'end') {
                    $this->execute_scenario_action($action, $found_config, $owner_id, $telegram_user, $chat_id);
                }

                // End scenario if next_step is 'end'
                if ($next_step_id === 'end') {
                    $this->set_user_scenario_step($owner_id, $bot_id, null);
                    $this->send_bot_message($platform, $token, $chat_id, "✅ با تشکر از شما!\n\nبرای شروع مجدد، دکمه زیر را بزنید.", $found_config);
                    return new \WP_REST_Response(['ok' => true]);
                }

                // Find and send next step
                $scenarios = $found_config['scenarios'] ?? [];
                $next_step = $this->find_scenario_step($scenarios, $next_step_id);
                if ($next_step) {
                    $this->set_user_scenario_step($owner_id, $bot_id, $next_step_id);
                    $keyboard = $this->build_scenario_keyboard($next_step);
                    $message_text = $this->replace_bot_placeholders($next_step['message'], $owner_id, $found_config, $telegram_user);
                    $send_data = [
                        'chat_id' => $chat_id,
                        'text' => $message_text,
                        'parse_mode' => 'HTML',
                    ];
                    if ($keyboard) {
                        $send_data['reply_markup'] = $keyboard;
                    }
                    $this->bot_api_request($platform, $token, 'sendMessage', $send_data);
                }

                return new \WP_REST_Response(['ok' => true]);
            }

            // Find matching button and execute action
            foreach (($found_config['buttons'] ?? []) as $btn) {
                if (empty($btn['is_active']) || $btn['type'] !== 'callback' || $btn['keyboard_type'] !== 'inline') {
                    continue;
                }

                if ($btn['value'] === $callback_data) {
                    // Check if button has child buttons (button tree)
                    if (!empty($btn['next_buttons']) && is_array($btn['next_buttons'])) {
                        $response_text = $this->replace_bot_placeholders($btn['response_message'] ?? $btn['text'], $owner_id, $found_config, $telegram_user);
                        $child_keyboard = $this->build_child_keyboard($btn['next_buttons'], $btn['value'], $found_config);
                        $send_data = [
                            'chat_id' => $chat_id,
                            'text' => $response_text,
                            'parse_mode' => 'HTML',
                        ];
                        if ($child_keyboard) {
                            $send_data['reply_markup'] = $child_keyboard;
                        }
                        $this->bot_api_request($platform, $token, 'sendMessage', $send_data);
                        return new \WP_REST_Response(['ok' => true]);
                    }

                    // Check commands first
                    foreach (($found_config['commands'] ?? []) as $cmd) {
                        if (strtolower(ltrim($cmd['command'], '/')) === strtolower($callback_data)) {
                            $this->send_bot_message($platform, $token, $chat_id, $cmd['response'], $found_config);
                            return new \WP_REST_Response(['ok' => true]);
                        }
                    }

                    // Check auto-replies
                    if ($this->execute_auto_reply($callback_data, $found_config, $owner_id, $telegram_user, $platform, $token, $chat_id)) {
                        return new \WP_REST_Response(['ok' => true]);
                    }

                    // Fallback to button response_message
                    if (!empty($btn['response_message'])) {
                        $this->send_bot_message($platform, $token, $chat_id, $btn['response_message'], $found_config);
                    }

                    return new \WP_REST_Response(['ok' => true]);
                }
            }

            // If no button match, check auto-replies
            $this->execute_auto_reply($callback_data, $found_config, $owner_id, $telegram_user, $platform, $token, $chat_id);
            return new \WP_REST_Response(['ok' => true]);
        }

        // Handle message with keyboard
        if ($message && isset($message['text'])) {
            $text = sanitize_text_field($message['text']);

            // Welcome message for /start
            if ($text === '/start') {
                // Check if there's a start scenario
                $scenarios = $found_config['scenarios'] ?? [];
                $start_scenario = $this->find_scenario_by_trigger($scenarios, '/start');
                if ($start_scenario && !empty($start_scenario['steps'])) {
                    $first_step = $start_scenario['steps'][0];
                    $this->set_user_scenario_step($owner_id, $bot_id, $first_step['id']);
                    $keyboard = $this->build_scenario_keyboard($first_step);
                    $message_text = $this->replace_bot_placeholders($first_step['message'], $owner_id, $found_config, $telegram_user);
                    $send_data = [
                        'chat_id' => $chat_id,
                        'text' => $message_text,
                        'parse_mode' => 'HTML',
                    ];
                    if ($keyboard) {
                        $send_data['reply_markup'] = $keyboard;
                    }
                    $this->bot_api_request($platform, $token, 'sendMessage', $send_data);
                    return new \WP_REST_Response(['ok' => true]);
                }

                // Fallback to welcome message
                if (!empty($found_config['welcome_message'])) {
                    $welcome_text = $this->replace_bot_placeholders($found_config['welcome_message'], $owner_id, $found_config, $telegram_user);
                    $this->send_bot_message($platform, $token, $chat_id, $welcome_text, $found_config);
                    return new \WP_REST_Response(['ok' => true]);
                }
            }

            // Command matching
            if (strpos($text, '/') === 0) {
                $cmd_name = ltrim($text, '/');
                foreach (($found_config['commands'] ?? []) as $cmd) {
                    if (!empty($cmd['is_active']) && strtolower(ltrim($cmd['command'], '/')) === strtolower($cmd_name)) {
                        $cmd_response = $this->replace_bot_placeholders($cmd['response'], $owner_id, $found_config, $telegram_user);
                        $this->send_bot_message($platform, $token, $chat_id, $cmd_response, $found_config);
                        return new \WP_REST_Response(['ok' => true]);
                    }
                }
            }

            // Text auto-reply matching
            foreach (($found_config['auto_replies'] ?? []) as $reply) {
                if (empty($reply['is_active'])) continue;
                $match_type = $reply['match_type'] ?? 'exact';
                $match = false;

                if ($match_type === 'exact') {
                    $match = strtolower($text) === strtolower($reply['trigger']);
                } elseif ($match_type === 'contains') {
                    $match = strpos(strtolower($text), strtolower($reply['trigger'])) !== false;
                } elseif ($match_type === 'starts_with') {
                    $match = strpos(strtolower($text), strtolower($reply['trigger'])) === 0;
                }

                if ($match) {
                    $response_text = $this->replace_bot_placeholders($reply['response'], $owner_id, $found_config, $telegram_user);
                    $this->send_bot_message($platform, $token, $chat_id, $response_text, $found_config);
                    return new \WP_REST_Response(['ok' => true]);
                }
            }
        }

        return new \WP_REST_Response(['ok' => true]);
    }

    /**
     * Build keyboard markup from bot buttons
     */
    private function build_keyboard_markup($buttons, $commands = []) {
        if (empty($buttons)) return null;

        $inline_rows = [];
        $reply_rows = [];

        // Inline keyboard only allows specific button types
        $inline_allowed_types = ['url', 'callback', 'web_app', 'login_url', 'switch_inline', 'pay'];

        // Group buttons by keyboard type and row
        foreach ($buttons as $btn) {
            if (empty($btn['is_active'])) continue;

            $keyboard_type = $btn['keyboard_type'] ?? 'inline';
            $row = $btn['row'] ?? 0;
            $btn_type = $btn['type'] ?? 'simple';

            if ($keyboard_type === 'inline') {
                // Skip simple buttons for inline keyboard - they're not allowed
                if (!in_array($btn_type, $inline_allowed_types)) {
                    continue;
                }
                if (!isset($inline_rows[$row])) $inline_rows[$row] = [];
                $inline_rows[$row][] = $this->build_button_object($btn, $commands);
            } else {
                if (!isset($reply_rows[$row])) $reply_rows[$row] = [];
                $reply_rows[$row][] = $this->build_button_object($btn, $commands);
            }
        }

        $markup = null;

        // Build inline keyboard
        if (!empty($inline_rows)) {
            ksort($inline_rows);
            $inline_keyboard = [];
            foreach ($inline_rows as $row_buttons) {
                $inline_keyboard[] = $row_buttons;
            }
            $markup = ['inline_keyboard' => $inline_keyboard];
        }

        // Build reply keyboard
        if (!empty($reply_rows)) {
            ksort($reply_rows);
            $reply_keyboard = [];
            foreach ($reply_rows as $row_buttons) {
                $reply_keyboard[] = $row_buttons;
            }
            $markup = [
                'keyboard' => $reply_keyboard,
                'resize_keyboard' => true,
                'one_time_keyboard' => false,
            ];
        }

        return $markup;
    }

    /**
     * Build individual button object for Telegram API
     */
    private function build_button_object($btn, $commands = []) {
        $button = ['text' => $btn['text']];

        // Note: style/keyboardButtonStyle is not reliably supported in Bot API
        // Removing it to avoid "Field style must be of type String" errors

        switch ($btn['type']) {
            case 'url':
                $button['url'] = $btn['value'];
                break;

            case 'callback':
                $button['callback_data'] = $btn['value'];
                if (!empty($btn['requires_password'])) {
                    $button['requires_password'] = true;
                }
                break;

            case 'web_app':
                $button['web_app'] = ['url' => $btn['value']];
                break;

            case 'login_url':
                $login_url = ['url' => $btn['value']];
                if (!empty($btn['bot_username'])) {
                    $login_url['bot_username'] = $btn['bot_username'];
                }
                if (!empty($btn['forward_text'])) {
                    $login_url['forward_text'] = $btn['forward_text'];
                }
                $button['login_url'] = $login_url;
                break;

            case 'switch_inline':
                if (!empty($btn['switch_to_chat'])) {
                    $button['switch_inline_query_current_chat'] = $btn['value'] ?? '';
                } else {
                    $button['switch_inline_query'] = $btn['value'] ?? '';
                }
                break;

            case 'request_contact':
                $button['request_contact'] = true;
                break;

            case 'request_location':
                $button['request_location'] = true;
                break;

            case 'request_poll':
                $button['request_poll'] = ['type' => 'quiz']; // or 'regular'
                break;

            case 'request_peer':
                $peer_type = $btn['peer_type'] ?? 'user';
                $request_peer = [];
                if ($peer_type === 'user') {
                    $request_peer['user_is_bot'] = false;
                } elseif ($peer_type === 'chat') {
                    $request_peer['chat_is_channel'] = false;
                } elseif ($peer_type === 'channel') {
                    $request_peer['chat_is_channel'] = true;
                }
                if (!empty($btn['max_quantity'])) {
                    $request_peer['max_quantity'] = $btn['max_quantity'];
                }
                $button['request_peer'] = $request_peer;
                break;

            case 'pay':
                $button['pay'] = true;
                break;

            default: // simple
                // Simple text button in reply keyboard
                break;
        }

        return $button;
    }

    /**
     * Debug function to list all bots and their IDs
     */
    public function handle_debug_bot_list() {
        check_ajax_referer('ssp_secure_nonce', 'security');
        if (!is_user_logged_in()) wp_send_json_error(['message' => 'لطفاً وارد شوید']);

        $current_user_id = get_current_user_id();
        $all_users = get_users(['fields' => 'ID', 'number' => 1000]);
        $all_bots = [];
        $current_user_bots = [];

        foreach ($all_users as $uid) {
            $user_configs = is_array($__tmp = get_user_meta($uid, 'ssp_bot_configs', true)) ? $__tmp : [];
            foreach ($user_configs as $config) {
                $is_current = ((int)$uid === (int)$current_user_id);
                $bot_info = [
                    'user_id' => (int)$uid,
                    'bot_id' => (int)($config['id'] ?? 0),
                    'name' => $config['name'] ?? '',
                    'platform' => $config['platform'] ?? '',
                    'is_active' => $config['is_active'] ?? 0,
                    'has_token' => !empty($config['token']),
                    'token_preview' => !empty($config['token']) ? substr($config['token'], 0, 10) . '...' : 'NO TOKEN',
                    'webhook_url' => $config['webhook_url'] ?? '',
                    'is_current_user' => $is_current,
                    'commands_count' => count($config['commands'] ?? []),
                    'buttons_count' => count($config['buttons'] ?? []),
                ];
                $all_bots[] = $bot_info;

                if ($is_current) {
                    $current_user_bots[] = $bot_info;
                }
            }
        }

        // Clear all bot config cache for current user
        $this->clear_all_user_bot_cache($current_user_id);

        wp_send_json_success([
            'current_user_id' => (int)$current_user_id,
            'total_bots' => count($all_bots),
            'total_users' => count($all_users),
            'bots' => $all_bots,
            'current_user_bots' => $current_user_bots,
        ]);
    }

    /**
     * Clear all bot config cache for a user
     */
    private function clear_all_user_bot_cache($user_id) {
        $configs = $this->get_user_bot_configs($user_id);
        foreach ($configs as $config) {
            if (isset($config['id'])) {
                $this->clear_bot_config_cache($user_id, $config['id']);
            }
        }
    }

    /**
     * Clean up old style data from all bots' buttons
     * This is called once to fix the style format issue
     */
    public function cleanup_all_bot_buttons_style() {
        $all_users = get_users(['fields' => 'ID', 'number' => 1000]);
        $cleaned = 0;

        foreach ($all_users as $uid) {
            $user_configs = is_array($__tmp = get_user_meta($uid, 'ssp_bot_configs', true)) ? $__tmp : [];
            $needs_save = false;

            foreach ($user_configs as &$config) {
                if (!empty($config['buttons'])) {
                    foreach ($config['buttons'] as &$btn) {
                        if (isset($btn['style'])) {
                            unset($btn['style']);
                            $needs_save = true;
                        }
                    }
                    unset($btn);
                }
            }
            unset($config);

            if ($needs_save) {
                update_user_meta($uid, 'ssp_bot_configs', $user_configs);
                $cleaned++;
            }
        }

        error_log('[SSP Bot] Cleaned style data from ' . $cleaned . ' users');
        return $cleaned;
    }

    /**
     * Debug: Show buttons data for a bot to check style format
     */
    public function handle_debug_bot_buttons() {
        check_ajax_referer('ssp_secure_nonce', 'security');
        if (!is_user_logged_in()) wp_send_json_error(['message' => 'لطفاً وارد شوید']);

        $current_user_id = get_current_user_id();
        $bot_id = intval($_POST['bot_id'] ?? 0);

        if ($bot_id <= 0) {
            wp_send_json_error(['message' => 'شناسه بات نامعتبر']);
        }

        $config = $this->get_bot_config($current_user_id, $bot_id);
        if (!$config) {
            wp_send_json_error(['message' => 'بات یافت نشد']);
        }

        $buttons = $config['buttons'] ?? [];

        // Show raw button data for debugging
        wp_send_json_success([
            'bot_id' => $bot_id,
            'buttons' => $buttons,
            'buttons_json' => json_encode($buttons, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE),
        ]);
    }

    // ========================================
    // HELPER: SEND BOT MESSAGE
    // ========================================

    /**
     * Send a message to a chat with keyboard
     */
    private function send_bot_message($platform, $token, $chat_id, $text, $config) {
        $send_data = [
            'chat_id' => $chat_id,
            'text' => $text,
            'parse_mode' => 'HTML',
        ];
        $keyboard = $this->build_keyboard_markup($config['buttons'] ?? [], $config['commands'] ?? []);
        if ($keyboard) {
            $send_data['reply_markup'] = $keyboard;
        }
        return $this->bot_api_request($platform, $token, 'sendMessage', $send_data);
    }

    /**
     * Find and execute auto-reply by trigger
     */
    private function execute_auto_reply($trigger, $config, $user_id, $telegram_user, $platform, $token, $chat_id) {
        foreach (($config['auto_replies'] ?? []) as $reply) {
            if (empty($reply['is_active'])) continue;
            if (strtolower($reply['trigger']) === strtolower($trigger)) {
                $text = $this->replace_bot_placeholders($reply['response'], $user_id, $config, $telegram_user);
                $this->send_bot_message($platform, $token, $chat_id, $text, $config);
                return true;
            }
        }
        return false;
    }

    /**
     * Build keyboard from child buttons with automatic back button
     */
    private function build_child_keyboard($child_buttons, $parent_value, $config) {
        $buttons = [];
        $row = 0;
        $col = 0;

        foreach ($child_buttons as $child) {
            $buttons[] = [
                'text' => $child['text'] ?? '',
                'type' => 'callback',
                'value' => $child['value'] ?? '',
                'keyboard_type' => 'inline',
                'row' => $row,
                'col' => $col,
                'is_active' => 1,
            ];
            $col++;
            if ($col >= 2) {
                $col = 0;
                $row++;
            }
        }

        // Add back button if not at root level
        if (!empty($parent_value)) {
            $buttons[] = [
                'text' => '🔙 بازگشت',
                'type' => 'callback',
                'value' => 'back:' . $parent_value,
                'keyboard_type' => 'inline',
                'row' => $row,
                'col' => $col,
                'is_active' => 1,
            ];
        }

        return $this->build_keyboard_markup($buttons);
    }

    /**
     * Find button by value recursively in button tree (with depth limit)
     */
    private function find_button_by_value($buttons, $value, $depth = 0) {
        if ($depth > 5) return null; // Max depth limit
        foreach ($buttons as $btn) {
            if (($btn['value'] ?? '') === $value) {
                return $btn;
            }
            if (!empty($btn['next_buttons'])) {
                $found = $this->find_button_by_value($btn['next_buttons'], $value, $depth + 1);
                if ($found) return $found;
            }
        }
        return null;
    }

    /**
     * Find parent button that contains this child (with depth limit)
     */
    private function find_parent_button($buttons, $child_value, $depth = 0) {
        if ($depth > 5) return null; // Max depth limit
        foreach ($buttons as $btn) {
            if (!empty($btn['next_buttons'])) {
                foreach ($btn['next_buttons'] as $child) {
                    if (($child['value'] ?? '') === $child_value) {
                        return $btn;
                    }
                }
                $found = $this->find_parent_button($btn['next_buttons'], $child_value, $depth + 1);
                if ($found) return $found;
            }
        }
        return null;
    }

    /**
     * Execute a scenario action
     */
    private function execute_scenario_action($action, $config, $user_id, $telegram_user, $chat_id) {
        switch ($action) {
            case 'notify_admin':
                // Log the interaction
                $this->log_activity($user_id, 'bot', 'Scenario Action', 'Action: ' . $action . ', Chat: ' . $chat_id, 'info');
                break;
            case 'add_to_queue':
                // Could add content to queue
                break;
            case 'send_form':
                // Could trigger a form flow
                break;
        }
    }

    // ========================================
    // PLACEHOLDER REPLACEMENT
    // ========================================

    /**
     * Replace placeholders in bot response text
     */
    private function replace_bot_placeholders($text, $user_id, $bot_config, $telegram_user = []) {
        // Portal URL
        $portal_url = '';
        if (function_exists('admin_url')) {
            $portal_url = admin_url('admin.php?page=smart-automation');
        }
        $text = str_replace('{portal_url}', $portal_url, $text);

        // User info from Telegram webhook
        $text = str_replace('{first_name}', $telegram_user['first_name'] ?? '', $text);
        $text = str_replace('{last_name}', $telegram_user['last_name'] ?? '', $text);
        $text = str_replace('{username}', $telegram_user['username'] ?? '', $text);

        // Stats
        if (strpos($text, '{posts_count}') !== false || strpos($text, '{pending_count}') !== false || strpos($text, '{last_activity}') !== false) {
            $user_logs = SSP_DB::get_logs($user_id, 200);
            $posts_count = count(array_filter($user_logs, function($log) {
                return ($log['status'] ?? '') === 'success';
            }));
            // Count pending queue items for this user
            $pending_count = count(SSP_DB::get_queue_items('pending', 0, $user_id));
            // Get most recent activity
            $last_activity = 'ندارد';
            if (!empty($user_logs)) {
                usort($user_logs, function($a, $b) {
                    return strtotime($b['created_at'] ?? '0') - strtotime($a['created_at'] ?? '0');
                });
                $last_activity = reset($user_logs)['created_at'] ?? 'ندارد';
            }

            $text = str_replace('{posts_count}', (string)$posts_count, $text);
            $text = str_replace('{pending_count}', (string)$pending_count, $text);
            $text = str_replace('{last_activity}', $last_activity, $text);
        }

        // Date/Time
        $text = str_replace('{date}', wp_date('Y-m-d'), $text);
        $text = str_replace('{time}', wp_date('H:i'), $text);

        return $text;
    }

    // ========================================
    // BOT TEMPLATES
    // ========================================

    /**
     * Build a template button
     */
    private function tpl_btn($text, $value, $row, $col = 0) {
        return ['text' => $text, 'type' => 'callback', 'value' => $value, 'keyboard_type' => 'inline', 'row' => $row, 'col' => $col, 'is_active' => 1];
    }

    private function tpl_reply($trigger, $response) {
        return ['trigger' => $trigger, 'match_type' => 'exact', 'response' => $response, 'is_active' => 1];
    }

    // ========================================
    // SCENARIO SYSTEM
    // ========================================

    /**
     * Get user's current scenario step
     */
    private function get_user_scenario_step($user_id, $bot_id) {
        $steps = is_array($__tmp = get_user_meta($user_id, 'ssp_bot_scenario_steps', true)) ? $__tmp : [];
        return $steps[$bot_id] ?? null;
    }

    /**
     * Set user's current scenario step
     */
    private function set_user_scenario_step($user_id, $bot_id, $step_id) {
        $steps = is_array($__tmp = get_user_meta($user_id, 'ssp_bot_scenario_steps', true)) ? $__tmp : [];
        if ($step_id === null) {
            unset($steps[$bot_id]);
        } else {
            $steps[$bot_id] = $step_id;
        }
        update_user_meta($user_id, 'ssp_bot_scenario_steps', $steps);
    }

    /**
     * Find scenario step by ID
     */
    private function find_scenario_step($scenarios, $step_id) {
        foreach ($scenarios as $scenario) {
            foreach (($scenario['steps'] ?? []) as $step) {
                if (($step['id'] ?? '') === $step_id) {
                    return $step;
                }
            }
        }
        return null;
    }

    /**
     * Find scenario that starts with a trigger
     */
    private function find_scenario_by_trigger($scenarios, $trigger) {
        foreach ($scenarios as $scenario) {
            if (($scenario['trigger'] ?? '') === $trigger) {
                return $scenario;
            }
        }
        return null;
    }

    /**
     * Build scenario step keyboard
     */
    private function build_scenario_keyboard($step) {
        $buttons = [];
        foreach (($step['buttons'] ?? []) as $i => $btn) {
            $buttons[] = [
                'text' => $btn['text'],
                'type' => 'callback',
                'value' => 'sc:' . ($btn['next'] ?? 'end') . ':' . ($btn['action'] ?? ''),
                'keyboard_type' => 'inline',
                'row' => floor($i / 2),
                'col' => $i % 2,
            ];
        }
        return $this->build_keyboard_markup($buttons);
    }

    /**
     * Get all available bot templates
     */
    private function get_bot_templates() {
        return [
            'content_publisher' => [
                'name' => 'بات انتشار محتوا',
                'description' => 'ارسال و زمان‌بندی پست‌ها از طریق تلگرام',
                'platform' => 'telegram',
                'welcome_message' => "سلام {first_name}! 👋\nبه بات انتشار محتوا خوش آمدید.\n\nاز منوی زیر یکی از عملیات را انتخاب کنید:",
                'commands' => [],
                'buttons' => [
                    $this->tpl_btn('📝 پست جدید', 'new_post', 0, 0),
                    $this->tpl_btn('📅 زمان‌بندی', 'schedule', 0, 1),
                    $this->tpl_btn('📊 آمار', 'stats', 1, 0),
                    $this->tpl_btn('❓ راهنما', 'help', 1, 1),
                ],
                'auto_replies' => [
                    $this->tpl_reply('new_post', "📝 ایجاد پست جدید\n\nبرای ایجاد پست جدید از پنل مدیریت استفاده کنید:\n{portal_url}\n\nیا متن پست خود را اینجا ارسال کنید:"),
                    $this->tpl_reply('schedule', "📅 زمان‌بندی پست\n\nبرای زمان‌بندی پست‌ها از پنل مدیریت استفاده کنید:\n{portal_url}"),
                    $this->tpl_reply('stats', "📊 آمار انتشار\n\nتعداد پست‌های ارسال شده: {posts_count}\nپست‌های در انتظار: {pending_count}\nآخرین فعالیت: {last_activity}"),
                    $this->tpl_reply('help', "❓ راهنمای بات\n\n🔹 پست جدید: ایجاد و ارسال پست\n🔹 زمان‌بندی: مدیریت زمان ارسال\n🔹 آمار: مشاهده آمار انتشار\n🔹 راهنما: نمایش این پیام\n\nبرای بازگشت به منو، /start را ارسال کنید."),
                ],
                'scenarios' => [],
            ],

            'faq_bot' => [
                'name' => 'بات سوالات متداول',
                'description' => 'پاسخ خودکار به سوالات رایج مشتریان',
                'platform' => 'telegram',
                'welcome_message' => "سلام {first_name}! 👋\nبه بات پشتیبانی خوش آمدید.\n\nچطور می‌توانیم کمکتان کنی؟\n\nاز منوی زیر سوال خود را انتخاب کنید:",
                'commands' => [],
                'buttons' => [
                    $this->tpl_btn('📦 ارسال و تحویل', 'shipping', 0, 0),
                    $this->tpl_btn('🔄 مرجوعی', 'returns', 0, 1),
                    $this->tpl_btn('💳 پرداخت', 'payment', 1, 0),
                    $this->tpl_btn('💬 پشتیبانی', 'support', 1, 1),
                ],
                'auto_replies' => [
                    $this->tpl_reply('shipping', "📦 ارسال و تحویل\n\n🔹 ارسال رایگان برای خریدهای بالای ۵۰۰,۰۰۰ تومان\n🔹 ارسال استاندارد: ۳-۵ روز کاری\n🔹 ارسال سریع: ۱-۲ روز کاری\n🔹 پیگیری سفارش: شماره سفارش را ارسال کنید\n\n❓ سوال دیگری دارید؟"),
                    $this->tpl_reply('returns', "🔄 سیاست مرجوعی\n\n🔹 مرجوعی تا ۷ روز پس از تحویل\n🔹 کالا باید بدون استفاده و در بسته‌بندی اصلی باشد\n🔹 هزینه مرجوعی بر عهده مشتری\n🔹 مبلغ ظرف ۳ روز کاری بازگشت داده می‌شود\n\n❓ سوال دیگری دارید؟"),
                    $this->tpl_reply('payment', "💳 روش‌های پرداخت\n\n🔹 کارت به کارت\n🔹 درگاه پرداخت آنلاین\n🔹 پرداخت در محل (فقط تهران)\n🔹 اقساط تا ۱۲ ماه\n\nکد پیگیری پرداخت خود را ذخیره کنید.\n\n❓ سوال دیگری دارید؟"),
                    $this->tpl_reply('support', "💬 پشتیبانی\n\nتیم پشتیبانی ما آماده کمک است.\n\n⏰ ساعات کاری: شنبه تا پنجشنبه ۹ الی ۱۸\n📧 ایمیل: support@example.com\n📞 تلفن: ۰۲۱-۱۲۳۴۵۶۷۸\n\nپیام خود را ارسال کنید تا در اسرع وقت پاسخ دهیم."),
                ],
                'scenarios' => [],
            ],

            'appointment' => [
                'name' => 'بات نوبت‌دهی',
                'description' => 'رزرو نوبت و یادآوری وقت ملاقات',
                'platform' => 'telegram',
                'welcome_message' => '',
                'commands' => [],
                'buttons' => [],
                'auto_replies' => [],
                'scenarios' => [
                    [
                        'id' => 1,
                        'name' => 'رزرو نوبت',
                        'trigger' => '/start',
                        'steps' => [
                            [
                                'id' => 'welcome',
                                'message' => "سلام {first_name}! 👋\nبه سامانه نوبت‌دهی خوش آمدید.\n\nلطفاً نوع خدمت را انتخاب کنید:",
                                'buttons' => [
                                    ['text' => '🩺 ویزیت عمومی', 'next' => 'select_date'],
                                    ['text' => '🦷 دندانپزشکی', 'next' => 'select_date'],
                                    ['text' => '👁️ چشم پزشکی', 'next' => 'select_date'],
                                ],
                            ],
                            [
                                'id' => 'select_date',
                                'message' => "📅 تاریخ مورد نظر را انتخاب کنید:",
                                'buttons' => [
                                    ['text' => 'شنبه ۳ مرداد', 'next' => 'select_time'],
                                    ['text' => 'یکشنبه ۴ مرداد', 'next' => 'select_time'],
                                    ['text' => 'دوشنبه ۵ مرداد', 'next' => 'select_time'],
                                ],
                            ],
                            [
                                'id' => 'select_time',
                                'message' => "🕐 ساعت مورد نظر را انتخاب کنید:",
                                'buttons' => [
                                    ['text' => '۰۹:۰۰', 'next' => 'confirm', 'action' => 'notify_admin'],
                                    ['text' => '۱۰:۰۰', 'next' => 'confirm', 'action' => 'notify_admin'],
                                    ['text' => '۱۱:۰۰', 'next' => 'confirm', 'action' => 'notify_admin'],
                                    ['text' => '۱۴:۰۰', 'next' => 'confirm', 'action' => 'notify_admin'],
                                ],
                            ],
                            [
                                'id' => 'confirm',
                                'message' => "✅ نوبت شما با موفقیت ثبت شد!\n\n📌 جزئیات نوبت:\n- خدمت: انتخاب شده\n- تاریخ: انتخاب شده\n- ساعت: انتخاب شده\n\n⏰ یادآوری ۲۴ ساعت قبل از وقت ارسال می‌شود.\n\n❓ سوالی دارید؟",
                                'buttons' => [
                                    ['text' => '🔙 بازگشت به منو', 'next' => 'welcome'],
                                    ['text' => '❌ لغو نوبت', 'next' => 'cancel'],
                                ],
                            ],
                            [
                                'id' => 'cancel',
                                'message' => "❌ نوبت شما لغو شد.\n\nبرای رزرو مجدد، /start را ارسال کنید.",
                                'buttons' => [],
                            ],
                        ],
                    ],
                ],
            ],
        ];
    }

    /**
     * AJAX: Load bot template
     */
    public function handle_load_bot_template() {
        check_ajax_referer('ssp_secure_nonce', 'security');
        if (!is_user_logged_in()) wp_send_json_error(['message' => 'لطفاً وارد شوید']);

        $template_key = sanitize_text_field($_POST['template_key'] ?? '');
        $templates = $this->get_bot_templates();

        if (!isset($templates[$template_key])) {
            wp_send_json_error(['message' => 'قالب یافت نشد']);
        }

        wp_send_json_success(['template' => $templates[$template_key]]);
    }

    /**
     * AJAX: Apply bot template (creates bot + loads all template data)
     */
    public function handle_apply_bot_template() {
        $user_id = $this->ajax_require_auth();
        $template_key = sanitize_text_field($_POST['template_key'] ?? '');
        $templates = $this->get_bot_templates();

        if (!isset($templates[$template_key])) {
            wp_send_json_error(['message' => 'قالب یافت نشد']);
        }

        $tpl = $templates[$template_key];
        $configs = $this->get_user_bot_configs($user_id);

        // Assign IDs to buttons, commands, and auto_replies
        $buttons = $tpl['buttons'] ?? [];
        $btn_id = 1;
        foreach ($buttons as &$btn) {
            $btn['id'] = $btn_id++;
        }
        unset($btn);

        $commands = $tpl['commands'] ?? [];
        $cmd_id = 1;
        foreach ($commands as &$cmd) {
            $cmd['id'] = $cmd_id++;
        }
        unset($cmd);

        $auto_replies = $tpl['auto_replies'] ?? [];
        $reply_id = 1;
        foreach ($auto_replies as &$reply) {
            $reply['id'] = $reply_id++;
        }
        unset($reply);

        // Create new bot with template data
        $config_data = [
            'id' => $this->next_id($configs),
            'name' => $tpl['name'],
            'platform' => $tpl['platform'],
            'token' => '',
            'is_active' => 0,
            'welcome_message' => $tpl['welcome_message'],
            'webhook_url' => '',
            'commands' => $commands,
            'buttons' => $buttons,
            'auto_replies' => $auto_replies,
            'scenarios' => $tpl['scenarios'] ?? [],
            'created_at' => current_time('mysql'),
        ];

        $configs[] = $config_data;
        $this->set_user_bot_configs($user_id, $configs);

        wp_send_json_success([
            'message' => 'قالب با موفقیت بارگذاری شد! توکن ربات را وارد کنید.',
            'bot_id' => $config_data['id'],
            'config' => $config_data,
        ]);
    }

    // ========================================
    // SCENARIO CRUD
    // ========================================

    /**
     * AJAX: Save scenario (create or update)
     */
    public function handle_save_bot_scenario() {
        $user_id = $this->ajax_require_auth();
        $bot_id = intval($_POST['bot_id'] ?? 0);
        $scenario_id = intval($_POST['scenario_id'] ?? 0);
        $name = sanitize_text_field($_POST['name'] ?? '');
        $trigger = sanitize_text_field($_POST['trigger'] ?? '');
        $steps_json = sanitize_textarea_field($_POST['steps'] ?? '[]');

        if (empty($name)) {
            wp_send_json_error(['message' => 'نام سناریو الزامی است']);
        }

        $steps = json_decode($steps_json, true);
        if (!is_array($steps)) {
            wp_send_json_error(['message' => 'فرمت مراحل نامعتبر است']);
        }

        // Limit scenario steps
        $steps = array_slice($steps, 0, SSP_BOT_BUILDER_MAX_SCENARIO_STEPS);

        // Sanitize steps
        foreach ($steps as &$step) {
            $step['id'] = sanitize_text_field($step['id'] ?? '');
            $step['message'] = sanitize_textarea_field($step['message'] ?? '');
            // Limit buttons per step
            $step['buttons'] = array_slice($step['buttons'] ?? [], 0, SSP_BOT_BUILDER_MAX_SCENARIO_BUTTONS);
            foreach ($step['buttons'] as &$btn) {
                $btn['text'] = sanitize_text_field($btn['text'] ?? '');
                $btn['next'] = sanitize_text_field($btn['next'] ?? 'end');
                $btn['action'] = sanitize_text_field($btn['action'] ?? '');
            }
            unset($btn);
        }
        unset($step);

        // Get bot config
        $configs = $this->get_user_bot_configs($user_id);
        $found = false;
        foreach ($configs as &$config) {
            if ((int)$config['id'] === $bot_id) {
                if (!isset($config['scenarios'])) $config['scenarios'] = [];

                $scenario_data = [
                    'id' => $scenario_id ?: $this->next_id($config['scenarios']),
                    'name' => $name,
                    'trigger' => $trigger,
                    'steps' => $steps,
                ];

                if ($scenario_id) {
                    // Update existing
                    foreach ($config['scenarios'] as &$s) {
                        if ((int)$s['id'] === $scenario_id) {
                            $s = $scenario_data;
                            break;
                        }
                    }
                    unset($s);
                } else {
                    // Create new
                    $config['scenarios'][] = $scenario_data;
                }

                $found = true;
                break;
            }
        }
        unset($config);

        if (!$found) {
            wp_send_json_error(['message' => 'بات یافت نشد']);
        }

        $this->set_user_bot_configs($user_id, $configs);
        $this->clear_bot_config_cache($user_id, $bot_id);

        wp_send_json_success([
            'message' => 'سناریو ذخیره شد!',
            'scenario' => $scenario_data,
        ]);
    }

    /**
     * AJAX: Delete scenario
     */
    public function handle_delete_bot_scenario() {
        $user_id = $this->ajax_require_auth();
        $bot_id = intval($_POST['bot_id'] ?? 0);
        $scenario_id = intval($_POST['scenario_id'] ?? 0);

        $configs = $this->get_user_bot_configs($user_id);
        $found = false;
        foreach ($configs as &$config) {
            if ((int)$config['id'] === $bot_id) {
                $config['scenarios'] = array_values(array_filter($config['scenarios'] ?? [], function($s) use ($scenario_id) {
                    return (int)$s['id'] !== $scenario_id;
                }));
                $found = true;
                break;
            }
        }
        unset($config);

        if (!$found) {
            wp_send_json_error(['message' => 'بات یافت نشد']);
        }

        $this->set_user_bot_configs($user_id, $configs);
        $this->clear_bot_config_cache($user_id, $bot_id);

        wp_send_json_success(['message' => 'سناریو حذف شد']);
    }
}
