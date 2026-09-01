<?php
/**
 * SSP AJAX General Trait - Settings, connection test, AI test, generate, manual send
 */
trait SSP_AjaxGeneral {

    public function handle_save_settings() {
        $user_id = $this->ajax_require_auth();
        $section = sanitize_text_field($_POST['section'] ?? '');

        // Check plan directly (without get_user_plan which sends emails)
        $plan = get_user_meta($user_id, 'ssp_plan', true) ?: 'free';

        if ($section === 'ai') {
            if ($plan === 'pro') {
                update_user_meta($user_id, 'ssp_ai_provider', sanitize_text_field($_POST['ai_provider'] ?? 'openai'));
                $new_key = sanitize_text_field($_POST['ai_api_key'] ?? '');
                if (!empty($new_key)) update_user_meta($user_id, 'ssp_ai_api_key', $new_key);
                update_user_meta($user_id, 'ssp_ai_model', sanitize_text_field($_POST['ai_model'] ?? 'gpt-4o-mini'));
                update_user_meta($user_id, 'ssp_ai_rewrite', intval($_POST['ai_rewrite'] ?? 0));
                update_user_meta($user_id, 'ssp_ai_hashtags', intval($_POST['ai_hashtags'] ?? 0));
                update_user_meta($user_id, 'ssp_ai_prompt_mode', sanitize_text_field($_POST['ai_prompt_mode'] ?? 'simple'));
                update_user_meta($user_id, 'ssp_ai_custom_prompt', sanitize_textarea_field($_POST['ai_custom_prompt'] ?? ''));
                update_user_meta($user_id, 'ssp_ai_mode', sanitize_text_field($_POST['ssp_ai_mode'] ?? 'api'));
                update_user_meta($user_id, 'ssp_ai_chatbot', sanitize_text_field($_POST['ssp_ai_chatbot'] ?? 'deepseek'));
            }
        } elseif ($section === 'template') {
            update_user_meta($user_id, 'ssp_msg_template', sanitize_textarea_field($_POST['msg_template'] ?? ''));
            update_user_meta($user_id, 'ssp_signature', sanitize_textarea_field($_POST['signature'] ?? ''));
            update_user_meta($user_id, 'ssp_global_hashtags', sanitize_text_field($_POST['hashtags'] ?? ''));
        } elseif ($section === 'sources') {
            update_user_meta($user_id, 'ssp_auto_wp_posts', intval($_POST['auto_wp_posts'] ?? 0));
        }
        wp_send_json_success(['message' => 'تنظیمات ذخیره شد!']);
    }

    public function handle_test_connection() {
        $user_id = $this->ajax_require_auth();
        $platform = sanitize_text_field($_POST['platform']);

        // Get token from messenger_id (from DB) or directly from POST
        $token = sanitize_text_field($_POST['token'] ?? '');
        $messenger_id = intval($_POST['messenger_id'] ?? 0);
        if (empty($token) && $messenger_id > 0) {
            $messengers = $this->get_user_messengers($user_id);
            foreach ($messengers as $m) {
                if ((int)$m['id'] === $messenger_id) {
                    $token = $m['token'] ?? '';
                    break;
                }
            }
        }

        if ($platform === 'whatsapp') {
            $phone_number_id = sanitize_text_field($_POST['channel_id'] ?? '');
            if (empty($phone_number_id)) wp_send_json_error(['message' => 'Phone Number ID الزامی است']);
            $endpoint = "https://graph.facebook.com/" . SSP_WHATSAPP_API_VERSION . "/$phone_number_id";
            $args = array_merge(['timeout' => 15, 'headers' => ['Authorization' => 'Bearer ' . $token]], $this->get_proxy_args());
            $response = wp_remote_get($endpoint, $args);
            if (is_wp_error($response)) wp_send_json_error(['message' => $response->get_error_message()]);
            $code = wp_remote_retrieve_response_code($response);
            $body = json_decode(wp_remote_retrieve_body($response), true);
            if ($code === 200 && !empty($body['display_phone_number'])) wp_send_json_success(['message' => 'اتصال موفق!', 'bot_name' => $body['display_phone_number']]);
            else wp_send_json_error(['message' => $body['error']['message'] ?? 'خطای ' . $code]);
        }

        if ($platform === 'instagram') {
            $ig_user_id = sanitize_text_field($_POST['channel_id'] ?? '');
            if (empty($ig_user_id)) wp_send_json_error(['message' => 'Instagram User ID الزامی است']);
            $endpoint = "https://graph.facebook.com/" . SSP_WHATSAPP_API_VERSION . "/$ig_user_id?fields=id,username&access_token=$token";
            $args = array_merge(['timeout' => 15], $this->get_proxy_args());
            $response = wp_remote_get($endpoint, $args);
            if (is_wp_error($response)) wp_send_json_error(['message' => $response->get_error_message()]);
            $code = wp_remote_retrieve_response_code($response);
            $body = json_decode(wp_remote_retrieve_body($response), true);
            if ($code === 200 && !empty($body['id'])) wp_send_json_success(['message' => 'اتصال موفق!', 'bot_name' => $body['username'] ?? $body['id']]);
            else wp_send_json_error(['message' => $body['error']['message'] ?? 'خطای ' . $code]);
        }

        $get_apis = [
            'telegram' => 'https://api.telegram.org/bot%s/getMe',
            'bale' => 'https://tapi.bale.ai/bot%s/getMe',
            'eitaa' => 'https://eitaayar.ir/api/%s/getMe',
        ];
        if (isset($get_apis[$platform])) {
            $doh = $this->get_doh_settings();
            $doh_enabled = $doh['enabled'];
            $doh_server = $doh['server'];
            $proxy_enabled = !empty($this->get_cached_option('proxy_settings', [])['enabled']);
            $relay_enabled = $this->is_telegram_relay_enabled();
            $test_url = sprintf($get_apis[$platform], $token);

            $post_args = [
                'timeout' => 15,
                'headers' => ['Content-Type' => 'application/json'],
                'body' => '{}',
            ];

            if ($platform === 'telegram') {
                $response = null;

                // 1. Try Telegram Relay first
                if ($relay_enabled) {
                    $relay_result = $this->send_via_relay('getMe', []);
                    if ($relay_result['success']) {
                        $bot_name = $relay_result['result']['first_name'] ?? 'بات';
                        error_log('[SSP Telegram Test] Relay success: ' . json_encode($relay_result['result']));
                        wp_send_json_success(['message' => 'اتصال موفق! (از طریق رله)', 'bot_name' => $bot_name . ' (Relay)']);
                        return;
                    }
                    error_log('[SSP Telegram Test] Relay failed: ' . ($relay_result['error'] ?? 'unknown'));
                }

                // 2. Try DoH if enabled and proxy not configured
                if ($doh_enabled && !$proxy_enabled) {
                    $response = $this->wp_remote_with_doh($test_url, $post_args, $doh_server, 'POST');
                }

                // 3. Fallback to direct/proxy
                if (!$response || is_wp_error($response)) {
                    $response = wp_remote_post($test_url, array_merge($post_args, $this->get_proxy_args()));
                }
            } elseif ($platform === 'bale') {
                // Bale: getMe with GET
                $response = wp_remote_get($test_url, array_merge(['timeout' => 15], $this->get_proxy_args()));
            } else {
                // Eitaa - POST with JSON body
                $response = wp_remote_post($test_url, array_merge($post_args, $this->get_proxy_args()));
            }

            if (is_wp_error($response)) {
                error_log('[SSP ' . $platform . ' Test] WP Error: ' . $response->get_error_message());
                $hint = !empty(get_option('ssp_proxy_settings', [])['enabled']) ? ' پروکسی فعال است ولی مشکلی دارد.' : '';
                wp_send_json_error(['message' => $response->get_error_message() . $hint]);
            }
            $raw = wp_remote_retrieve_body($response);
            $code = wp_remote_retrieve_response_code($response);
            $body = json_decode($raw, true);
            error_log('[SSP ' . $platform . ' Test] HTTP ' . $code . ' | Response: ' . $raw);

            // Telegram/Bale/Eitaa all use "ok": true format
            if ($code === 200 && ($body['ok'] ?? false)) {
                $bot_name = $body['result']['first_name'] ?? ($body['result']['bot']['name'] ?? 'بات');
                wp_send_json_success(['message' => 'اتصال موفق!', 'bot_name' => $bot_name]);
            }

            $error_msg = $body['description'] ?? $body['status'] ?? 'توکن نامعتبر';
            wp_send_json_error(['message' => $error_msg . ' (HTTP ' . $code . ')']);
        }

        if ($platform === 'rubika') {
            // Rubika: getMe with POST
            $endpoint = "https://botapi.rubika.ir/v3/$token/getMe";
            $response = wp_remote_post($endpoint, array_merge([
                'timeout' => 15,
                'headers' => ['Content-Type' => 'application/json'],
            ], $this->get_proxy_args()));
            if (is_wp_error($response)) {
                $hint = !empty(get_option('ssp_proxy_settings', [])['enabled']) ? ' پروکسی فعال است ولی مشکلی دارد.' : '';
                wp_send_json_error(['message' => $response->get_error_message() . $hint]);
            }
            $raw = wp_remote_retrieve_body($response);
            $code = wp_remote_retrieve_response_code($response);
            $body = json_decode($raw, true);
            error_log('[SSP Rubika Test] HTTP ' . $code . ' | Response: ' . $raw);
            if ($code === 200 && ($body['status'] ?? '') === 'OK') wp_send_json_success(['message' => 'اتصال موفق!', 'bot_name' => 'بات روبیکا']);
            wp_send_json_error(['message' => ($body['status'] ?? 'توکن نامعتبر') . ' (HTTP ' . $code . ')']);
        }

        wp_send_json_error(['message' => 'پلتفرم نامعتبر']);
    }

    public function handle_test_ai() {
        $user_id = $this->ajax_require_auth();
        if ($this->get_user_plan($user_id) === 'free') {
            wp_send_json_error(['message' => 'این قابلیت فقط در پلن Pro موجود است']);
        }

        $provider = sanitize_text_field($_POST['provider']);
        $api_key = sanitize_text_field($_POST['api_key']);
        $model = sanitize_text_field($_POST['model']);

        $proxy_settings = get_option('ssp_proxy_settings', []);
        $use_proxy = !empty($proxy_settings['enabled']);
        $proxy_host = $proxy_settings['host'] ?? '';
        $proxy_port = $proxy_settings['port'] ?? '';
        $proxy_type = $proxy_settings['type'] ?? 'http';

        $doh_enabled = get_option('ssp_doh_enabled', 1);
        $doh_server = get_option('ssp_doh_server', 'cloudflare');

        $endpoints = [
            'openai' => 'https://api.openai.com',
            'openrouter' => 'https://openrouter.ai',
            'anthropic' => 'https://api.anthropic.com',
            'gemini' => 'https://generativelanguage.googleapis.com',
            'groq' => 'https://api.groq.com',
            'deepseek' => 'https://api.deepseek.com',
        ];

        if (isset($endpoints[$provider])) {
            $test_url = $endpoints[$provider];
            $test_args = ['timeout' => 15];

            if ($use_proxy && !empty($proxy_host) && !empty($proxy_port)) {
                $proxy_url = $proxy_type . '://' . $proxy_host . ':' . $proxy_port;
                $test_args['proxy'] = $proxy_url;
                $test_args['sslverify'] = false;
            }

            if ($doh_enabled && !$use_proxy) {
                $test_response = $this->wp_remote_with_doh($test_url, $test_args, $doh_server);
            } else {
                $test_response = wp_remote_get($test_url, $test_args);
            }

            if (is_wp_error($test_response)) {
                $hint = 'احتمالاً فایروال سرور شما دسترسی به ' . $test_url . ' را مسدود کرده.';
                if ($use_proxy) {
                    $hint .= ' پروکسی فعال است ولی مشکلی دارد. تنظیمات پروکسی را بررسی کنید.';
                } elseif ($doh_enabled) {
                    $hint .= ' DNS over HTTPS فعال است ولی مشکلی دارد. سرور DNS دیگری را امتحان کنید.';
                } else {
                    $hint .= ' DNS over HTTPS را فعال کنید (تنظیمات DNS) یا از پروکسی استفاده کنید.';
                }
                wp_send_json_error([
                    'message' => 'خطا در اتصال به سرور: ' . $test_response->get_error_message(),
                    'hint' => $hint
                ]);
            }
        }

        try {
            $result = $this->call_ai_api($provider, $api_key, $model, 'Say "سلام" in Persian, one word only.');
            wp_send_json_success([
                'message' => 'اتصال موفق!',
                'response' => $result['content'],
                'tokens_used' => $result['tokens_used']
            ]);
        } catch (Exception $e) {
            wp_send_json_error(['message' => $e->getMessage()]);
        }
    }

    public function handle_test_ai_live() {
        @set_time_limit(60);
        @ignore_user_abort(true);
        $user_id = $this->ajax_require_auth();
        if ($this->get_user_plan($user_id) === 'free') {
            wp_send_json_error(['message' => 'این قابلیت فقط در پلن Pro موجود است']);
        }

        $step = sanitize_text_field($_POST['step'] ?? '');
        $provider = sanitize_text_field($_POST['provider'] ?? '');
        $api_key = sanitize_text_field($_POST['api_key'] ?? '');
        $model = sanitize_text_field($_POST['model'] ?? '');

        $proxy_settings = get_option('ssp_proxy_settings', []);
        $use_proxy = !empty($proxy_settings['enabled']);
        $proxy_host = $proxy_settings['host'] ?? '';
        $proxy_port = $proxy_settings['port'] ?? '';
        $proxy_type = $proxy_settings['type'] ?? 'http';

        $doh_enabled = get_option('ssp_doh_enabled', 1);
        $doh_server = get_option('ssp_doh_server', 'cloudflare');

        $endpoints = [
            'openai' => ['base' => 'https://api.openai.com', 'api' => 'https://api.openai.com/v1/chat/completions'],
            'openrouter' => ['base' => 'https://openrouter.ai', 'api' => 'https://openrouter.ai/api/v1/chat/completions'],
            'anthropic' => ['base' => 'https://api.anthropic.com', 'api' => 'https://api.anthropic.com/v1/messages'],
            'gemini' => ['base' => 'https://generativelanguage.googleapis.com', 'api' => 'https://generativelanguage.googleapis.com/v1beta/models'],
            'groq' => ['base' => 'https://api.groq.com', 'api' => 'https://api.groq.com/openai/v1/chat/completions'],
            'deepseek' => ['base' => 'https://api.deepseek.com', 'api' => 'https://api.deepseek.com/chat/completions'],
        ];

        $provider_names = [
            'openai' => 'OpenAI', 'openrouter' => 'OpenRouter', 'anthropic' => 'Anthropic (Claude)',
            'gemini' => 'Google Gemini', 'groq' => 'Groq', 'deepseek' => 'DeepSeek',
        ];

        $build_args = function($url) use ($use_proxy, $proxy_host, $proxy_port, $proxy_type) {
            $args = ['timeout' => 15, 'redirection' => 0];
            if ($use_proxy && !empty($proxy_host) && !empty($proxy_port)) {
                $args['proxy'] = $proxy_type . '://' . $proxy_host . ':' . $proxy_port;
                $args['sslverify'] = false;
            }
            return $args;
        };

        $do_request = function($url, $args) use ($doh_enabled, $use_proxy, $doh_server) {
            if ($doh_enabled && !$use_proxy) {
                return $this->wp_remote_with_doh($url, $args, $doh_server);
            }
            return wp_remote_get($url, $args);
        };

        switch ($step) {
            case 'validate_key':
                if (empty($api_key)) {
                    wp_send_json_error(['message' => 'API Key وارد نشده است', 'step' => 'validate_key']);
                }
                if (empty($provider) || !isset($endpoints[$provider])) {
                    wp_send_json_error(['message' => 'پروایدر نامعتبر است', 'step' => 'validate_key']);
                }
                $key_type = 'نامشخص';
                $key_valid = true;
                if ($provider === 'gemini') {
                    if (strpos($api_key, 'AQ.') === 0) {
                        $key_type = 'Auth Key (جدید)';
                        $key_valid = true;
                    } elseif (strpos($api_key, 'AIza') === 0) {
                        $key_type = 'Standard Key (کلاسیک)';
                        $key_valid = true;
                    } else {
                        $key_valid = false;
                    }
                }
                $msg = $key_valid ? 'فرمت API Key صحیح به نظر می‌رسد' : 'فرمت API Key ممکن است نامعتبر باشد (ادامه تست...)';
                if ($provider === 'gemini' && $key_valid) {
                    $msg .= "\nنوع کلید: " . $key_type;
                }
                wp_send_json_success([
                    'message' => $msg,
                    'step' => 'validate_key',
                    'key_valid' => $key_valid,
                ]);
                break;

            case 'check_dns':
                $domain = preg_replace('#^https?://#', '', $endpoints[$provider]['base']);
                $domain = rtrim($domain, '/');
                $start = microtime(true);
                $args = $build_args($endpoints[$provider]['base']);
                $response = $do_request($endpoints[$provider]['base'], $args);
                $elapsed = round((microtime(true) - $start) * 1000);

                if (is_wp_error($response)) {
                    $error_msg = $response->get_error_message();
                    $hint = '';
                    if (strpos($error_msg, 'cURL error 6') !== false || strpos($error_msg, 'Could not resolve') !== false) {
                        $hint = 'DNS حل نشد. DNS over HTTPS را فعال کنید یا سرور DNS دیگری انتخاب کنید.';
                    } elseif (strpos($error_msg, 'cURL error 28') !== false) {
                        $hint = 'تایم‌اوت اتصال. فایروال سرور احتمالاً دسترسی را مسدود کرده. پروکسی تنظیم کنید.';
                    } elseif (strpos($error_msg, 'cURL error 7') !== false) {
                        $hint = 'اتصال رد شد. سرور مقصد در دسترس نیست یا فایروال مسدود کرده.';
                    }
                    wp_send_json_error([
                        'message' => 'خطا در اتصال به ' . $domain . ': ' . $error_msg,
                        'step' => 'check_dns',
                        'hint' => $hint,
                        'elapsed' => $elapsed . 'ms',
                    ]);
                } else {
                    $code = wp_remote_retrieve_response_code($response);
                    if ($code >= 400) {
                        if ($code === 403 && $provider === 'openrouter') {
                            wp_send_json_success([
                                'message' => 'اتصال برقرار شد (کد: ' . $code . ' - صفحه اصلی مسدود ولی API فعال است)',
                                'step' => 'check_dns',
                                'status_code' => $code,
                                'elapsed' => $elapsed . 'ms',
                            ]);
                        } else {
                            $hint = '';
                            if ($code === 403) {
                                $hint = 'سرور درخواست را رد کرد. ';
                                if ($provider === 'gemini') {
                                    $hint .= "\n→ Generative Language API را فعال کنید:";
                                    $hint .= "\n  https://console.cloud.google.com/apis/library/generativelanguage.googleapis.com";
                                } else {
                                    $hint .= 'API Key یا دسترسی خود را بررسی کنید.';
                                }
                            }
                            wp_send_json_error([
                                'message' => 'سرور ' . $domain . ' کد ' . $code . ' برگرداند (زمان: ' . $elapsed . 'ms)',
                                'step' => 'check_dns',
                                'hint' => $hint,
                                'status_code' => $code,
                                'elapsed' => $elapsed . 'ms',
                            ]);
                        }
                    } else {
                        wp_send_json_success([
                            'message' => 'اتصال به ' . $domain . ' برقرار شد (کد: ' . $code . ', زمان: ' . $elapsed . 'ms)',
                            'step' => 'check_dns',
                            'status_code' => $code,
                            'elapsed' => $elapsed . 'ms',
                        ]);
                    }
                }
                break;

            case 'send_test_request':
                $start = microtime(true);
                try {
                    $result = $this->call_ai_api($provider, $api_key, $model, 'Say "Hello" in one word.');
                    $elapsed = round((microtime(true) - $start) * 1000);
                    wp_send_json_success([
                        'message' => 'پاسخ AI دریافت شد!',
                        'step' => 'send_test_request',
                        'response' => $result['content'],
                        'tokens_used' => $result['tokens_used'] ?? 0,
                        'elapsed' => $elapsed . 'ms',
                    ]);
                } catch (Exception $e) {
                    $elapsed = round((microtime(true) - $start) * 1000);
                    $error_msg = $e->getMessage();
                    $hint = '';
                    if (strpos($error_msg, '401') !== false || strpos($error_msg, 'Unauthorized') !== false) {
                        $hint = 'API Key نامعتبر است. کلید را بررسی کنید.';
                    } elseif (strpos($error_msg, '429') !== false) {
                        $hint = 'محدودیت نرخ (Rate Limit). کمی صبر کنید و دوباره تلاش کنید.';
                    } elseif (strpos($error_msg, '403') !== false) {
                        if ($provider === 'gemini') {
                            $is_auth_key = (strpos($api_key, 'AQ.') === 0);
                            $hint = 'خطای 403 Gemini - راهنما:';
                            $hint .= "\n\n1. فعال‌سازی API:";
                            $hint .= "\n   → https://console.cloud.google.com/apis/library/generativelanguage.googleapis.com";
                            $hint .= "\n   → روی Enable کلیک کنید";
                            if ($is_auth_key) {
                                $hint .= "\n\n2. کلید شما از نوع Auth Key هست (AQ.)";
                                $hint .= "\n   → مطمئن شوید Service Account به Gemini API دسترسی دارد";
                                $hint .= "\n   → در AI Studio بررسی کنید کلید Blocked نباشد";
                            } else {
                                $hint .= "\n\n2. کلید خود را به Gemini API محدود کنید:";
                                $hint .= "\n   → https://aistudio.google.com/apikey";
                                $hint .= "\n   → Edit → Restrict to Gemini API only";
                            }
                        } elseif ($provider === 'openrouter') {
                            $hint = "خطای 403 OpenRouter - راهنما:\n";
                            if (stripos($error_msg, 'security policy') !== false) {
                                $hint .= "\nعلت: سیاست امنیتی OpenRouter درخواست را رد کرده\n";
                                $hint .= "\nراه‌حل‌ها:\n";
                                $hint .= "1. API Key را بررسی کنید: https://openrouter.ai/keys\n";
                                $hint .= "2. مطمئن شوید حساب شما فعال است\n";
                                $hint .= "3. مدل انتخابی را بررسی کنید: https://openrouter.ai/models\n";
                            } else {
                                $hint .= "\nAPI Key ممکن است نامعتبر باشد\n";
                                $hint .= "→ https://openrouter.ai/keys";
                            }
                        } else {
                            $hint = 'دسترسی غیرمجاز. API Key ممکن است دسترسی کافی نداشته باشد.';
                        }
                    } elseif (strpos($error_msg, '404') !== false || strpos($error_msg, 'Not Found') !== false) {
                        $hint = 'مدل یافت نشد. نام مدل را بررسی کنید.';
                        if ($provider === 'gemini') {
                            $hint .= "\nمدل‌های معتبر: gemini-2.0-flash, gemini-2.5-flash, gemini-2.5-pro";
                        } elseif ($provider === 'openrouter') {
                            $hint .= "\n→ لیست مدل‌ها: https://openrouter.ai/models";
                        }
                    } elseif (strpos($error_msg, '402') !== false || strpos($error_msg, 'credit') !== false || strpos($error_msg, 'insufficient') !== false) {
                        if ($provider === 'openrouter') {
                            $hint = 'موجودی حساب OpenRouter تمام شده.';
                            $hint .= "\n→ https://openrouter.ai/settings/credits";
                        } else {
                            $hint = 'موجودی حساب تمام شده. حساب خود را شارژ کنید.';
                        }
                    } elseif (strpos($error_msg, 'quota') !== false) {
                        $hint = 'سقف استفاده رسیده. حساب خود را ارتقا دهید.';
                    }
                    wp_send_json_error([
                        'message' => 'خطا: ' . $error_msg,
                        'step' => 'send_test_request',
                        'hint' => $hint,
                        'elapsed' => $elapsed . 'ms',
                    ]);
                }
                break;

            default:
                wp_send_json_error(['message' => 'مرحله نامعتبر', 'step' => $step]);
        }
    }

    public function handle_generate_now() {
        $user_id = $this->ajax_require_auth();
        if ($this->get_user_plan($user_id) !== 'pro') wp_send_json_error(['message' => 'این قابلیت فقط در پلن Pro موجود است']);
        $ai_topic = get_user_meta($user_id, 'ssp_ai_topic', true);
        $ai_api_key = get_user_meta($user_id, 'ssp_ai_api_key', true);
        if (empty($ai_api_key) || empty($ai_topic)) wp_send_json_error(['message' => 'لطفاً ابتدا API Key و موضوع را تنظیم کنید']);
        try {
            $content = $this->generate_ai_content($user_id, $ai_topic);
            $result = $this->add_to_queue($user_id, 'ai_generated', ['title' => $content['title'], 'message' => $content['message'], 'hashtags' => $content['hashtags'] ?? ''], 10);
            if ($result['success']) wp_send_json_success(['message' => 'محتوا تولید و به صف اضافه شد!', 'content' => $content]);
            else wp_send_json_error(['message' => $result['message']]);
        } catch (Exception $e) { wp_send_json_error(['message' => $e->getMessage()]); }
    }

    public function handle_manual_send() {
        $user_id = $this->ajax_require_auth();

        $raw_image = wp_unslash($_POST['image_url'] ?? '');
        // Check if it's a JSON array (album)
        if ($raw_image && $raw_image[0] === '[') {
            $decoded = json_decode($raw_image, true);
            $image_url = is_array($decoded) ? wp_json_encode($decoded) : '';
        } else {
            $image_url = esc_url_raw($raw_image);
        }

        $payload = [
            'title' => sanitize_text_field($_POST['title'] ?? ''),
            'message' => sanitize_textarea_field($_POST['message'] ?? ''),
            'hashtags' => sanitize_text_field($_POST['hashtags'] ?? ''),
            'image_url' => $image_url,
            'skip_ai_rewrite' => !empty($_POST['skip_ai_rewrite']),
        ];

        // Per-messenger selection
        $messenger_ids = sanitize_text_field($_POST['messengers'] ?? '');
        if (!empty($messenger_ids)) {
            $payload['selected_messengers'] = array_map('intval', explode(',', $messenger_ids));
        }

        $result = $this->add_to_queue($user_id, 'manual_send', $payload, 10);
        if ($result['success']) wp_send_json_success(['message' => 'پیام به صف اضافه شد و به زودی ارسال می‌شود!']);
        else wp_send_json_error(['message' => $result['message']]);
    }

    public function handle_upload_media() {
        $user_id = $this->ajax_require_auth();

        if (empty($_FILES['media_file'])) wp_send_json_error(['message' => 'فایلی ارسال نشد']);

        $file = $_FILES['media_file'];
        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

        $image_types = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
        $video_types = ['mp4', 'mpeg', 'mpg', 'mov', 'avi'];
        $is_image = in_array($ext, $image_types);
        $is_video = in_array($ext, $video_types);

        if (!$is_image && !$is_video) {
            wp_send_json_error(['message' => 'فرمت فایل پشتیبانی نمی‌شود']);
        }

        $max_size = $is_video ? 80 * 1024 * 1024 : 10 * 1024 * 1024;
        if ($file['size'] > $max_size) {
            wp_send_json_error(['message' => $is_video ? 'حداکثر حجم ویدیو ۸۰ مگابایت است' : 'حداکثر حجم تصویر ۱۰ مگابایت است']);
        }

        if ($file['error'] !== UPLOAD_ERR_OK) {
            wp_send_json_error(['message' => 'خطا در آپلود فایل']);
        }

        $upload_dir = wp_upload_dir();
        $media_dir = $upload_dir['basedir'] . '/ssp-media/' . $user_id;
        if (!file_exists($media_dir)) wp_mkdir_p($media_dir);

        // Cleanup old files (older than 24 hours)
        $this->cleanup_user_media($media_dir, 86400);

        $filename = 'ssp_' . time() . '_' . wp_generate_password(8, false) . '.' . $ext;
        $filepath = $media_dir . '/' . $filename;

        if (!move_uploaded_file($file['tmp_name'], $filepath)) {
            wp_send_json_error(['message' => 'خطا در ذخیره فایل']);
        }

        $url = $upload_dir['baseurl'] . '/ssp-media/' . $user_id . '/' . $filename;
        wp_send_json_success(['url' => $url, 'filename' => $filename]);
    }

    private function cleanup_user_media($dir, $max_age) {
        if (!is_dir($dir)) return;
        $files = glob($dir . '/*');
        $now = time();
        foreach ($files as $file) {
            if (is_file($file) && ($now - filemtime($file)) > $max_age) {
                @unlink($file);
            }
        }
    }

    public function handle_generate_ai_content() {
        @set_time_limit(300);
        @ignore_user_abort(true);
        @ini_set('memory_limit', '256M');
        check_ajax_referer('ssp_secure_nonce', 'security');
        $user_id = $this->ajax_require_auth();
        $plan = $this->get_user_plan($user_id);
        if ($plan === 'free') wp_send_json_error(['message' => 'تولید محتوا فقط در پلن Pro موجود است']);

        $prompt = sanitize_textarea_field($_POST['prompt'] ?? '');
        if (empty($prompt)) wp_send_json_error(['message' => 'متن ورودی خالی است']);

        $ai_provider = get_user_meta($user_id, 'ssp_ai_provider', true) ?: 'openai';
        $ai_api_key = get_user_meta($user_id, 'ssp_ai_api_key', true);
        $ai_model = get_user_meta($user_id, 'ssp_ai_model', true) ?: 'gpt-4o-mini';

        if (empty($ai_api_key)) wp_send_json_error(['message' => 'API Key تنظیم نشده. ابتدا تنظیمات AI را تکمیل کنید.']);

        $system_prompt = "تو یک نویسنده محتوای فارسی حرفه‌ای هستی. بر اساس موضوع زیر محتوا بنویس.\n\n" .
            "قوانین:\n" .
            "- فقط اطلاعات واقعی و تایید شده بنویس\n" .
            "- اگر از صحت چیزی مطمئن نیستی، اون رو حذف کن\n" .
            "- لحن طبیعی و انسانی داشته باش\n" .
            "- نکات عملی و کاربردی بده\n" .
            "- متن باید کاملاً ساده باشه. هیچ تگ HTML مثل <p> <br> <b> <strong> <ul> <li> استفاده نکن\n" .
            "- بولد رو با ** بنویس\n\n" .
            "موضوع:\n" . $prompt;

        try {
            $result = $this->call_ai_api($ai_provider, $ai_api_key, $ai_model, $system_prompt);
            $content = wp_strip_all_tags($result['content']);
            $content = preg_replace('/<[^>]+>/', '', $content);
            wp_send_json_success([
                'content' => $content,
                'tokens_used' => $result['tokens_used'] ?? 0,
                'cost' => $result['cost'] ?? null,
            ]);
        } catch (Exception $e) {
            wp_send_json_error(['message' => $e->getMessage()]);
        }
    }

    public function handle_save_telegram_relay() {
        $this->ajax_require_admin();
        $settings = [
            'enabled'    => intval($_POST['relay_enabled'] ?? 0),
            'worker_url' => esc_url_raw($_POST['worker_url'] ?? ''),
            'secret_key' => sanitize_text_field($_POST['secret_key'] ?? ''),
        ];
        update_option('ssp_telegram_relay', $settings);
        wp_send_json_success(['message' => 'تنظیمات رله تلگرام ذخیره شد']);
    }

    public function handle_test_telegram_relay() {
        $this->ajax_require_admin();
        $settings = get_option('ssp_telegram_relay', []);
        if (empty($settings['worker_url']) || empty($settings['secret_key'])) {
            wp_send_json_error(['message' => 'آدرس Worker و Secret Key الزامی است']);
        }

        $result = $this->test_telegram_relay();
        if ($result['success']) {
            $bot_name = $result['bot_name'] ?? 'بات';
            $bot_username = $result['bot_username'] ?? '';
            $msg = 'تست موفق! اتصال به رله تلگرام برقرار شد.';
            if ($bot_username) $msg .= "\n\nنام ربات: $bot_name\nیوزرنیم: @$bot_username";
            wp_send_json_success(['message' => $msg]);
        } else {
            wp_send_json_error(['message' => 'تست ناموفق: ' . ($result['error'] ?? 'خطای ناشناخته')]);
        }
    }
}
