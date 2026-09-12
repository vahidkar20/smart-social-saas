<?php
/**
 * SSP Network Trait - DNS over HTTPS, proxy, connectivity
 */
trait SSP_Network {

    /* ============ DNS over HTTPS (DoH) ============ */
    private function resolve_doh($domain, $doh_server = 'cloudflare') {
        $doh_urls = [];
        foreach (SSP_DOH_SERVERS as $name => $url) {
            $doh_urls[$name] = $url . '?name=' . urlencode($domain) . '&type=A';
        }

        $servers_to_try = array_merge([$doh_server], array_diff(array_keys($doh_urls), [$doh_server]));

        foreach ($servers_to_try as $server) {
            if (!isset($doh_urls[$server])) continue;

            $response = wp_remote_get($doh_urls[$server], [
                'timeout' => 8,
                'headers' => ['Accept' => 'application/dns-json'],
            ]);

            if (is_wp_error($response)) continue;

            $body = json_decode(wp_remote_retrieve_body($response), true);
            if (empty($body['Answer'])) continue;

            foreach ($body['Answer'] as $answer) {
                if ($answer['type'] === 1) return $answer['data'];
            }
        }

        return false;
    }

    private function wp_remote_with_doh($url, $args = [], $doh_server = 'cloudflare', $method = 'POST') {
        $parsed = parse_url($url);
        if (!$parsed || !isset($parsed['host'])) {
            return new \WP_Error('invalid_url', 'URL نامعتبر');
        }

        $host = $parsed['host'];
        $ip = $this->resolve_doh($host, $doh_server);

        if ($ip) {
            if (!isset($args['headers'])) $args['headers'] = [];
            $args['headers']['Host'] = $host;
            $args['sslverify'] = false;
            $new_url = str_replace('://' . $host, '://' . $ip, $url);
            if (strtoupper($method) === 'GET') {
                return wp_remote_get($new_url, $args);
            }
            return wp_remote_post($new_url, $args);
        }

        if (strtoupper($method) === 'GET') {
            return wp_remote_get($url, $args);
        }
        return wp_remote_post($url, $args);
    }

    private function test_doh_connectivity($doh_server = 'cloudflare') {
        $server_url = SSP_DOH_SERVERS[$doh_server] ?? SSP_DOH_SERVERS['cloudflare'];
        $url = $server_url . '?name=example.com&type=A';

        $response = wp_remote_get($url, [
            'timeout' => 8,
            'headers' => ['Accept' => 'application/dns-json'],
        ]);

        if (is_wp_error($response)) {
            return ['success' => false, 'error' => $response->get_error_message()];
        }

        $body = json_decode(wp_remote_retrieve_body($response), true);
        if (!empty($body['Answer'])) {
            return ['success' => true, 'server' => $doh_server];
        }

        return ['success' => false, 'error' => 'پاسخ DNS نامعتبر'];
    }

    /* ============ Telegram Relay (Self-hosted PHP) ============ */
    private function get_telegram_relay_settings() {
        static $settings = null;
        if ($settings === null) {
            $settings = is_array($__tmp = get_option('ssp_telegram_relay', [])) ? $__tmp : [];
        }
        return $settings;
    }

    private function is_telegram_relay_enabled() {
        $settings = $this->get_telegram_relay_settings();
        return !empty($settings['enabled']) && !empty($settings['worker_url']) && !empty($settings['secret_key']);
    }

    private function send_via_relay($telegram_method, $data) {
        $settings = $this->get_telegram_relay_settings();
        if (empty($settings['worker_url']) || empty($settings['secret_key'])) {
            return ['success' => false, 'error' => 'تنظیمات رله تلگرام پیکربندی نشده'];
        }

        $worker_url = rtrim($settings['worker_url'], '/');
        $body = [
            'secret_key' => $settings['secret_key'],
            'method'     => $telegram_method,
        ];

        // Pass through chat_id (required for most methods)
        if (!empty($data['chat_id'])) $body['chat_id'] = $data['chat_id'];

        // Pass through all supported Telegram parameters
        $pass_through = ['text', 'photo', 'caption', 'parse_mode', 'reply_markup',
            'disable_web_page_preview', 'disable_notification', 'reply_to_message_id',
            'document', 'video', 'audio', 'latitude', 'longitude', 'phone_number',
            'first_name', 'last_name', 'duration', 'performer', 'title', 'message_id'];
        foreach ($pass_through as $key) {
            if (!empty($data[$key])) {
                $body[$key] = is_array($data[$key]) ? json_encode($data[$key]) : $data[$key];
            }
        }

        $args = [
            'timeout' => SSP_RELAY_DEFAULT_TIMEOUT,
            'headers' => ['Content-Type' => 'application/json'],
            'body'    => json_encode($body),
        ];

        $proxy_args = $this->get_proxy_args();
        $response = null;
        $final_url = $worker_url;

        $parsed = parse_url($worker_url);
        $host = $parsed['host'] ?? '';
        $doh = $this->get_doh_settings();
        $doh_enabled = $doh['enabled'];
        $proxy_enabled = !empty($proxy_args['proxy']);

        error_log('[SSP Relay] === START ===');
        error_log('[SSP Relay] URL: ' . $worker_url);
        error_log('[SSP Relay] Host: ' . $host);
        error_log('[SSP Relay] DoH: ' . ($doh_enabled ? 'ON' : 'OFF'));
        error_log('[SSP Relay] Proxy: ' . ($proxy_enabled ? 'ON' : 'OFF'));
        error_log('[SSP Relay] Method: ' . $telegram_method);

        // Step 1: DNS resolution
        if ($host) {
            $ip = gethostbyname($host);
            $dns_ok = ($ip !== $host);
            error_log('[SSP Relay] DNS direct: ' . $ip . ' (' . ($dns_ok ? 'OK' : 'FAILED') . ')');

            if (!$dns_ok && $doh_enabled) {
                $doh_server = $doh['server'];
                error_log('[SSP Relay] DNS DoH trying: ' . $doh_server);
                $doh_ip = $this->resolve_doh($host, $doh_server);
                error_log('[SSP Relay] DNS DoH result: ' . ($doh_ip ?: 'FAILED'));
                if ($doh_ip) {
                    $final_url = str_replace('://' . $host, '://' . $doh_ip, $worker_url);
                    $args['headers']['Host'] = $host;
                    error_log('[SSP Relay] URL rewritten: ' . $final_url);
                }
            } elseif (!$dns_ok && !$doh_enabled) {
                error_log('[SSP Relay] DNS FAILED + DoH OFF!');
            }
        }

        // Step 2: Try proxy
        if ($proxy_enabled) {
            error_log('[SSP Relay] Trying proxy...');
            $proxy_args['sslverify'] = false;
            $response = wp_remote_post($final_url, array_merge($args, $proxy_args));
            if (is_wp_error($response)) {
                error_log('[SSP Relay] Proxy FAIL: ' . $response->get_error_message());
            } else {
                error_log('[SSP Relay] Proxy OK: HTTP ' . wp_remote_retrieve_response_code($response));
            }
        }

        // Step 3: Try direct
        if (!$response || is_wp_error($response)) {
            error_log('[SSP Relay] Trying direct...');
            $response = wp_remote_post($final_url, $args);
            if (is_wp_error($response)) {
                error_log('[SSP Relay] Direct FAIL: ' . $response->get_error_message());
            } else {
                error_log('[SSP Relay] Direct OK: HTTP ' . wp_remote_retrieve_response_code($response));
            }
        }

        if (is_wp_error($response)) {
            error_log('[SSP Relay] === ALL FAILED ===');
            return ['success' => false, 'error' => $response->get_error_message()];
        }

        $code = wp_remote_retrieve_response_code($response);
        $raw = wp_remote_retrieve_body($response);
        error_log('[SSP Relay] === RESPONSE: HTTP ' . $code . ' ===');
        error_log('[SSP Relay] Body: ' . $raw);

        $decoded = json_decode($raw, true);
        if ($code === 200 && !empty($decoded['ok'])) {
            return ['success' => true, 'result' => $decoded['result'] ?? null];
        }

        return ['success' => false, 'error' => $decoded['description'] ?? ('HTTP ' . $code)];
    }

    private function test_telegram_relay($test_chat_id = '') {
        $settings = $this->get_telegram_relay_settings();
        if (empty($settings['worker_url']) || empty($settings['secret_key'])) {
            return ['success' => false, 'error' => 'تنظیمات رله پیکربندی نشده'];
        }

        error_log('[SSP Relay Test] URL: ' . $settings['worker_url']);

        $result = $this->send_via_relay('getMe', []);

        error_log('[SSP Relay Test] Result: ' . json_encode($result));

        if ($result['success'] && !empty($result['result']['username'])) {
            $result['bot_name'] = $result['result']['first_name'] ?? '';
            $result['bot_username'] = $result['result']['username'] ?? '';
        }

        return $result;
    }

    private function test_basic_connectivity() {
        $test_urls = [
            'Google' => 'https://www.google.com',
            'Cloudflare' => 'https://1.1.1.1',
            'GitHub' => 'https://github.com',
        ];

        foreach ($test_urls as $name => $url) {
            $response = wp_remote_get($url, ['timeout' => 10]);
            if (!is_wp_error($response)) {
                return ['success' => true, 'service' => $name];
            }
        }

        return ['success' => false, 'error' => 'سرور به هیچ سرویس خارجی متصل نمی‌شود'];
    }
}
