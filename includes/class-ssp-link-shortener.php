<?php

trait SSP_LinkShortener {

    private function shorten_url($url, $settings) {
        if (empty($settings['enabled'])) {
            return $url;
        }

        // Self-hosted shortener
        if ($settings['provider'] === 'self') {
            return $this->create_short_link($url);
        }

        // External providers require API key
        if (empty($settings['api_key'])) {
            return $url;
        }

        // Get proxy settings
        $proxy_settings = get_option('ssp_proxy_settings', []);
        $use_proxy = !empty($proxy_settings['enabled']) && !empty($proxy_settings['host']) && !empty($proxy_settings['port']);
        $proxy_args = [];
        if ($use_proxy) {
            $proxy_args = [
                'proxy' => $proxy_settings['type'] . '://' . $proxy_settings['host'] . ':' . $proxy_settings['port'],
                'sslverify' => false,
            ];
        }

        if ($settings['provider'] === 'bitly') {
            $response = wp_remote_post('https://api-ssl.bitly.com/v4/shorten', array_merge([
                'timeout' => 10,
                'headers' => [
                    'Authorization' => 'Bearer ' . $settings['api_key'],
                    'Content-Type' => 'application/json',
                ],
                'body' => json_encode(['long_url' => $url]),
            ], $proxy_args));

            if (!is_wp_error($response) && wp_remote_retrieve_response_code($response) === 200) {
                $body = json_decode(wp_remote_retrieve_body($response), true);
                return $body['link'] ?? $url;
            }
        } elseif ($settings['provider'] === 'tinyurl') {
            $response = wp_remote_get('https://tinyurl.com/api-create.php?url=' . urlencode($url), array_merge(['timeout' => 10], $proxy_args));
            if (!is_wp_error($response)) {
                return wp_remote_retrieve_body($response);
            }
        }

        return $url;
    }

    private function create_short_link($long_url) {
        $short_links = get_option('ssp_short_links', []);

        // Check if URL already has a short link
        foreach ($short_links as $code => $link) {
            if ($link['long_url'] === $long_url) {
                return $this->get_short_url($code);
            }
        }

        // Generate unique code
        $code = $this->generate_short_code();
        $short_links[$code] = [
            'long_url' => $long_url,
            'created_at' => current_time('mysql'),
            'clicks' => 0,
            'user_id' => get_current_user_id(),
        ];

        update_option('ssp_short_links', $short_links);

        return $this->get_short_url($code, get_current_user_id());
    }

    private function get_short_url($code, $user_id = null) {
        return home_url('/go/' . $code);
    }

    private function generate_short_code($length = 6) {
        $chars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
        $short_links = get_option('ssp_short_links', []);

        do {
            $code = '';
            for ($i = 0; $i < $length; $i++) {
                $code .= $chars[random_int(0, strlen($chars) - 1)];
            }
        } while (isset($short_links[$code]));

        return $code;
    }

    public function handle_short_link_redirect() {
        if (!preg_match('#^/go/([a-zA-Z0-9]+)$#', $_SERVER['REQUEST_URI'] ?? '', $m)) {
            return;
        }

        $code = $m[1];
        $short_links = get_option('ssp_short_links', []);

        if (!isset($short_links[$code])) {
            status_header(404);
            nocache_headers();
            echo '404 - Link not found';
            exit;
        }

        // Track click
        $short_links[$code]['clicks'] = ($short_links[$code]['clicks'] ?? 0) + 1;
        $short_links[$code]['last_clicked'] = current_time('mysql');
        update_option('ssp_short_links', $short_links);

        // 301 redirect
        wp_redirect($short_links[$code]['long_url'], 301);
        exit;
    }

    public function get_short_link_stats($user_id = null) {
        $short_links = get_option('ssp_short_links', []);

        if ($user_id) {
            $short_links = array_filter($short_links, function($link) use ($user_id) {
                return (int)$link['user_id'] === (int)$user_id;
            });
        }

        $total_clicks = array_sum(array_column($short_links, 'clicks'));
        $total_links = count($short_links);

        return [
            'total_links' => $total_links,
            'total_clicks' => $total_clicks,
            'links' => $short_links,
        ];
    }
}
