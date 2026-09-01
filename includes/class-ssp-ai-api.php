<?php
/**
 * SSP AI API Trait - All AI provider integrations
 */
trait SSP_AiApi {

    private function call_ai_api($provider, $api_key, $model, $prompt, $json_mode = false) {
        $temperature = 0.7;
        $max_tokens = 2000;
        $retries = 2;

        $proxy_settings = $this->get_cached_option('proxy_settings', []);
        $use_proxy = !empty($proxy_settings['enabled']);
        $proxy_host = $proxy_settings['host'] ?? '';
        $proxy_port = $proxy_settings['port'] ?? '';
        $proxy_type = $proxy_settings['type'] ?? 'http';

        $doh = $this->get_doh_settings();
        $doh_enabled = $doh['enabled'];
        $doh_server = $doh['server'];

        for ($attempt = 0; $attempt <= $retries; $attempt++) {
            if ($attempt > 0) usleep((int)(SSP_AI_RETRY_DELAY * $attempt * 1000000));

            try {
                return $this->send_ai_request($provider, $api_key, $model, $prompt, $json_mode, $temperature, $max_tokens, $use_proxy, $proxy_host, $proxy_port, $proxy_type, $doh_enabled, $doh_server);
            } catch (Exception $e) {
                $msg = $e->getMessage();
                if (strpos($msg, '429') !== false && $attempt < $retries) continue;
                throw $e;
            }
        }

        throw new Exception('پروایدر پشتیبانی نمی‌شود');
    }

    private function send_ai_request($provider, $api_key, $model, $prompt, $json_mode, $temperature, $max_tokens, $use_proxy, $proxy_host, $proxy_port, $proxy_type, $doh_enabled, $doh_server) {
        $timeout = 60;

        switch ($provider) {
            case 'openai':
            case 'openrouter':
                return $this->call_openai_compat($provider, $api_key, $model, $prompt, $temperature, $max_tokens, $timeout, $use_proxy, $proxy_host, $proxy_port, $proxy_type, $doh_enabled, $doh_server);

            case 'anthropic':
                return $this->call_anthropic($api_key, $model, $prompt, $max_tokens, $timeout, $use_proxy, $proxy_host, $proxy_port, $proxy_type, $doh_enabled, $doh_server);

            case 'gemini':
                return $this->call_gemini($api_key, $model, $prompt, $json_mode, $temperature, $max_tokens, $timeout, $use_proxy, $proxy_host, $proxy_port, $proxy_type, $doh_enabled, $doh_server);

            case 'groq':
            case 'deepseek':
                $endpoint = $provider === 'groq'
                    ? 'https://api.groq.com/openai/v1/chat/completions'
                    : 'https://api.deepseek.com/chat/completions';
                return $this->call_openai_compat($provider, $api_key, $model, $prompt, $temperature, $max_tokens, $timeout, $use_proxy, $proxy_host, $proxy_port, $proxy_type, $doh_enabled, $doh_server, $endpoint);
        }

        throw new Exception('پروایدر پشتیبانی نمی‌شود');
    }

    private function call_openai_compat($provider, $api_key, $model, $prompt, $temperature, $max_tokens, $timeout, $use_proxy, $proxy_host, $proxy_port, $proxy_type, $doh_enabled, $doh_server, $endpoint = '') {
        if (empty($endpoint)) {
            $endpoint = $provider === 'openrouter'
                ? 'https://openrouter.ai/api/v1/chat/completions'
                : 'https://api.openai.com/v1/chat/completions';
        }

        $args = [
            'timeout' => $timeout,
            'headers' => [
                'Content-Type' => 'application/json',
                'Authorization' => 'Bearer ' . $api_key,
            ],
            'body' => json_encode([
                'model' => $model,
                'messages' => [['role' => 'user', 'content' => $prompt]],
                'temperature' => $temperature,
                'max_tokens' => $max_tokens,
            ]),
        ];

        if ($use_proxy && !empty($proxy_host) && !empty($proxy_port)) {
            $args['proxy'] = $proxy_type . '://' . $proxy_host . ':' . $proxy_port;
            $args['sslverify'] = false;
        }

        if ($doh_enabled && !$use_proxy) {
            $response = $this->wp_remote_with_doh($endpoint, $args, $doh_server);
        } else {
            $response = wp_remote_post($endpoint, $args);
        }

        if (is_wp_error($response)) throw new Exception($response->get_error_message());
        $code = wp_remote_retrieve_response_code($response);
        $raw_body = wp_remote_retrieve_body($response);
        $body = json_decode($raw_body, true);

        if ($code !== 200) {
            $error_msg = '';
            if (!empty($body['error']['message'])) {
                $error_msg = $body['error']['message'];
            } elseif (!empty($body['error']['reason'])) {
                $error_msg = $body['error']['reason'];
            } elseif (!empty($body['error'])) {
                $error_msg = is_string($body['error']) ? $body['error'] : json_encode($body['error']);
            } else {
                $error_msg = !empty($body['message']) ? $body['message'] : mb_substr($raw_body, 0, 500);
            }

            $debug_info = "\n\n--- اطلاعات عیب‌یابی ---\nکد HTTP: $code\n";

            if ($code === 402) {
                throw new Exception("خطای 402 - اتمام اعتبار: موجودی حساب OpenRouter تمام شده.\n→ https://openrouter.ai/settings/credits");
            } elseif ($code === 429) {
                $retry_after = $body['error']['metadata']['headers']['retry-after'] ?? '';
                $msg = 'خطای 429 - محدودیت نرخ: درخواست‌ها بیش از حد مجاز است.';
                if ($retry_after) $msg .= "\nزمان انتظار: " . $retry_after . " ثانیه";
                throw new Exception($msg);
            } elseif ($code === 403) {
                $hint = "\n\nعلت احتمالی:\n";
                if (stripos($error_msg, 'security policy') !== false) {
                    $hint .= "1. سیاست امنیتی OpenRouter درخواست را رد کرده\n";
                    $hint .= "2. مدل انتخاب شده ممکن است مجاز نباشد\n";
                    $hint .= "3. حساب شما ممکن است نیاز به احراز هویت داشته باشد\n";
                    $hint .= "\n→ https://openrouter.ai/keys را بررسی کنید";
                } elseif (stripos($error_msg, 'invalid') !== false || stripos($error_msg, 'key') !== false) {
                    $hint .= "API Key نامعتبر است";
                } else {
                    $hint .= "دلیل: " . $error_msg;
                }
                throw new Exception("خطای 403 - دسترسی غیرمجاز: $error_msg$hint$debug_info");
            } elseif ($code >= 500) {
                throw new Exception("خطای سرور OpenRouter ($code): $error_msg\nلطفاً کمی بعد دوباره تلاش کنید.$debug_info");
            } else {
                throw new Exception("خطای OpenRouter ($code): $error_msg$debug_info");
            }
        }

        if (!isset($body['choices'][0]['message']['content'])) {
            throw new Exception('پاسخ معتبری از OpenRouter دریافت نشد');
        }

        $tokens_used = $body['usage']['total_tokens'] ?? 0;
        $cost = $body['usage']['cost'] ?? null;

        return [
            'content' => $body['choices'][0]['message']['content'],
            'tokens_used' => $tokens_used,
            'cost' => $cost,
        ];
    }

    private function call_anthropic($api_key, $model, $prompt, $max_tokens, $timeout, $use_proxy, $proxy_host, $proxy_port, $proxy_type, $doh_enabled, $doh_server) {
        $endpoint = 'https://api.anthropic.com/v1/messages';
        $args = [
            'timeout' => $timeout,
            'headers' => [
                'Content-Type' => 'application/json',
                'x-api-key' => $api_key,
                'anthropic-version' => '2023-06-01',
            ],
            'body' => json_encode([
                'model' => $model,
                'max_tokens' => $max_tokens,
                'messages' => [['role' => 'user', 'content' => $prompt]],
            ]),
        ];

        if ($use_proxy && !empty($proxy_host) && !empty($proxy_port)) {
            $args['proxy'] = $proxy_type . '://' . $proxy_host . ':' . $proxy_port;
            $args['sslverify'] = false;
        }

        if ($doh_enabled && !$use_proxy) {
            $response = $this->wp_remote_with_doh($endpoint, $args, $doh_server);
        } else {
            $response = wp_remote_post($endpoint, $args);
        }

        if (is_wp_error($response)) throw new Exception($response->get_error_message());
        $code = wp_remote_retrieve_response_code($response);
        if ($code === 429) throw new Exception('429 rate limit');
        $body = json_decode(wp_remote_retrieve_body($response), true);

        return [
            'content' => $body['content'][0]['text'] ?? '',
            'tokens_used' => ($body['usage']['input_tokens'] ?? 0) + ($body['usage']['output_tokens'] ?? 0),
        ];
    }

    private function call_gemini($api_key, $model, $prompt, $json_mode, $temperature, $max_tokens, $timeout, $use_proxy, $proxy_host, $proxy_port, $proxy_type, $doh_enabled, $doh_server) {
        $endpoint = "https://generativelanguage.googleapis.com/v1beta/models/$model:generateContent";
        $gen_config = ['temperature' => $temperature, 'maxOutputTokens' => $max_tokens];
        if ($json_mode) $gen_config['responseMimeType'] = 'application/json';

        $args = [
            'timeout' => $timeout,
            'headers' => [
                'Content-Type' => 'application/json',
                'x-goog-api-key' => $api_key,
            ],
            'body' => json_encode([
                'contents' => [['parts' => [['text' => $prompt]]]],
                'generationConfig' => $gen_config,
            ]),
        ];

        if ($use_proxy && !empty($proxy_host) && !empty($proxy_port)) {
            $args['proxy'] = $proxy_type . '://' . $proxy_host . ':' . $proxy_port;
            $args['sslverify'] = false;
        }

        if ($doh_enabled && !$use_proxy) {
            $response = $this->wp_remote_with_doh($endpoint, $args, $doh_server);
        } else {
            $response = wp_remote_post($endpoint, $args);
        }

        if (is_wp_error($response)) throw new Exception($response->get_error_message());
        $code = wp_remote_retrieve_response_code($response);
        if ($code === 429) throw new Exception('429 rate limit');
        $body = json_decode(wp_remote_retrieve_body($response), true);

        if ($code !== 200) {
            $error_msg = $body['error']['message'] ?? ('خطای Gemini: ' . $code);
            $error_code = $body['error']['code'] ?? $code;
            if ($code === 403) {
                $error_msg = 'خطای 403 - دسترسی غیرمجاز: ' . $error_msg;
                $error_msg .= "\n\nراهنمای رفع مشکل:";
                $error_msg .= "\n1. مطمئن شوید Generative Language API در پروژه Google Cloud فعال است";
                $error_msg .= "\n   → https://console.cloud.google.com/apis/library/generativelanguage.googleapis.com";
                $error_msg .= "\n2. اگر کلید Auth هست (شروع با AQ.)، مطمئن شوید Service Account دسترسی دارد";
                $error_msg .= "\n3. اگر کلید Standard هست (شروع با AIza)، آن را به Gemini API محدود کنید";
                $error_msg .= "\n   → https://aistudio.google.com/apikey → Edit → Restrict to Gemini API only";
            } elseif ($code === 400) {
                $error_msg = 'خطای 400 - درخواست نامعتبر: ' . $error_msg;
                $error_msg .= "\n\nنکته: مدل '$model' ممکن است وجود نداشته باشد.";
                $error_msg .= "\nمدل‌های معتبر: gemini-2.0-flash, gemini-2.5-flash, gemini-2.5-pro";
            }
            throw new Exception($error_msg);
        }
        if (!empty($body['promptFeedback']['blockReason'])) {
            throw new Exception('پاسخ Gemini مسدود شد: ' . $body['promptFeedback']['blockReason']);
        }

        $text = $body['candidates'][0]['content']['parts'][0]['text'] ?? '';
        if (empty($text)) {
            $reason = $body['candidates'][0]['finishReason'] ?? 'نامشخص';
            throw new Exception('Gemini پاسخ خالی برگرداند (دلیل: ' . $reason . ')');
        }

        return [
            'content' => $text,
            'tokens_used' => $body['usageMetadata']['totalTokenCount'] ?? 0,
        ];
    }
}
