<?php
/**
 * SSP Messenger Trait - Platform message sending
 */
trait SSP_Messenger {

    private function format_message($user_id, $title, $message, $url, $hashtags) {
        $template = get_user_meta($user_id, 'ssp_msg_template', true);
        $signature = get_user_meta($user_id, 'ssp_signature', true);
        $fixed_hashtags = get_user_meta($user_id, 'ssp_global_hashtags', true);

        if (empty($template)) {
            $template = "{title}\n\n{message}\n\n{hashtags}\n\n{link}\n\n{signature}";
        }

        $final_url = $this->append_utm_params($url, $user_id);

        return trim(str_replace(
            ['{title}', '{message}', '{link}', '{hashtags}', '{signature}'],
            [$title, $message, $final_url, trim($hashtags . ' ' . $fixed_hashtags), $signature],
            $template
        ));
    }

    private function send_to_messenger($platform, $token, $message, $channel_id = '', $image_url = '') {
        // Handle JSON array (album) — extract first URL
        if (!empty($image_url) && $image_url[0] === '[') {
            $decoded = json_decode($image_url, true);
            if (is_array($decoded) && count($decoded) > 0) {
                $first = $decoded[0];
                $image_url = is_array($first) ? ($first['url'] ?? '') : (string)$first;
            } else {
                $image_url = '';
            }
        }
        $has_media = !empty($image_url);
        $is_video = $has_media ? $this->is_video_url($image_url) : false;

        if ($platform === 'whatsapp') return $this->send_whatsapp($token, $message, $channel_id, $image_url, $has_media, $is_video);
        if ($platform === 'instagram') return $this->send_instagram($token, $message, $channel_id, $image_url, $has_media);
        if ($platform === 'rubika') return $this->send_rubika($token, $message, $channel_id, $image_url, $has_media, $is_video);

        return $this->send_bot_platform($platform, $token, $message, $channel_id, $image_url, $has_media, $is_video);
    }

    private function is_video_url($url) {
        $ext = strtolower(pathinfo($url, PATHINFO_EXTENSION));
        return in_array($ext, ['mp4', 'mpeg', 'mpg', 'mov', 'avi', 'webm']);
    }

    private function send_whatsapp($token, $message, $channel_id, $image_url, $has_media, $is_video = false) {
        $phone_number_id = $channel_id;
        if (empty($phone_number_id)) return ['success' => false, 'response' => 'Phone Number ID الزامی است'];

        $endpoint = "https://graph.facebook.com/" . SSP_WHATSAPP_API_VERSION . "/$phone_number_id/messages";
        if ($has_media && $is_video) {
            $body = json_encode(['messaging_product' => 'whatsapp', 'to' => $phone_number_id, 'type' => 'video', 'video' => ['link' => $image_url, 'caption' => $message]]);
        } elseif ($has_media) {
            $body = json_encode(['messaging_product' => 'whatsapp', 'to' => $phone_number_id, 'type' => 'image', 'image' => ['link' => $image_url, 'caption' => $message]]);
        } else {
            $body = json_encode(['messaging_product' => 'whatsapp', 'to' => $phone_number_id, 'type' => 'text', 'text' => ['body' => $message]]);
        }

        $response = wp_remote_post($endpoint, array_merge([
            'timeout' => 30,
            'headers' => ['Authorization' => 'Bearer ' . $token, 'Content-Type' => 'application/json'],
            'body' => $body,
        ], $this->get_proxy_args()));

        if (is_wp_error($response)) return ['success' => false, 'response' => $response->get_error_message()];
        $code = wp_remote_retrieve_response_code($response);
        $resp_body = json_decode(wp_remote_retrieve_body($response), true);

        return [
            'success' => $code === 200 && !empty($resp_body['messages']),
            'response' => $resp_body['error']['message'] ?? ($resp_body['messages'][0]['id'] ?? ''),
        ];
    }

    private function send_instagram($token, $message, $channel_id, $image_url, $has_image) {
        $ig_user_id = $channel_id;
        if (empty($ig_user_id)) return ['success' => false, 'response' => 'Instagram User ID الزامی است'];

        if (!$has_image) return ['success' => false, 'response' => 'اینستاگرام نیاز به تصویر برای ارسال پست دارد'];

        $proxy_args = $this->get_proxy_args();
        $create_url = "https://graph.facebook.com/" . SSP_WHATSAPP_API_VERSION . "/$ig_user_id/media";
        $create_body = json_encode(['image_url' => $image_url, 'caption' => $message, 'access_token' => $token]);

        $create_response = wp_remote_post($create_url, array_merge([
            'timeout' => 30, 'headers' => ['Content-Type' => 'application/json'], 'body' => $create_body,
        ], $proxy_args));

        if (is_wp_error($create_response)) return ['success' => false, 'response' => $create_response->get_error_message()];
        $create_data = json_decode(wp_remote_retrieve_body($create_response), true);
        if (empty($create_data['id'])) return ['success' => false, 'response' => $create_data['error']['message'] ?? 'خطا در ایجاد رسانه'];

        $publish_url = "https://graph.facebook.com/" . SSP_WHATSAPP_API_VERSION . "/$ig_user_id/media_publish";
        $publish_body = json_encode(['creation_id' => $create_data['id'], 'access_token' => $token]);

        $publish_response = wp_remote_post($publish_url, array_merge([
            'timeout' => 30, 'headers' => ['Content-Type' => 'application/json'], 'body' => $publish_body,
        ], $proxy_args));

        if (is_wp_error($publish_response)) return ['success' => false, 'response' => $publish_response->get_error_message()];
        $publish_data = json_decode(wp_remote_retrieve_body($publish_response), true);

        return [
            'success' => !empty($publish_data['id']),
            'response' => $publish_data['id'] ?? ($publish_data['error']['message'] ?? 'خطا در انتشار'),
        ];
    }

    private function send_rubika($token, $message, $channel_id, $image_url, $has_media, $is_video = false) {
        $base_url = "https://botapi.rubika.ir/v3/$token";
        $proxy_args = $this->get_proxy_args();

        // Rubika: 3-step file send: requestSendFile → upload → sendFile
        if ($has_media && !empty($image_url)) {
            $file_type = $is_video ? 'Video' : 'Image';
            error_log('[SSP Rubika] sendFile attempt: url=' . $image_url . ' type=' . $file_type . ' chat=' . $channel_id);

            // Step 1: Request upload URL
            $req_body = json_encode(['type' => $file_type]);
            $req_response = wp_remote_post("$base_url/requestSendFile", array_merge([
                'timeout' => 30,
                'headers' => ['Content-Type' => 'application/json'],
                'body' => $req_body,
            ], $proxy_args));

            if (is_wp_error($req_response)) {
                error_log('[SSP Rubika] requestSendFile WP Error: ' . $req_response->get_error_message());
            } else {
                $req_raw = wp_remote_retrieve_body($req_response);
                $req_resp = json_decode($req_raw, true);
                error_log('[SSP Rubika] requestSendFile Response: ' . $req_raw);

                if (($req_resp['status'] ?? '') === 'OK' && !empty($req_resp['data']['upload_url'])) {
                    $upload_url = $req_resp['data']['upload_url'];

                    // Step 2: Download image and upload to Rubika (raw curl for multipart)
                    $downloaded = $this->download_file_for_upload($image_url, $proxy_args);
                    if ($downloaded) {
                        error_log('[SSP Rubika] upload step: file=' . $downloaded['path'] . ' mime=' . $downloaded['mime'] . ' size=' . filesize($downloaded['path']));
                        $ch = curl_init($upload_url);
                        curl_setopt($ch, CURLOPT_POST, true);
                        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
                        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
                        curl_setopt($ch, CURLOPT_POSTFIELDS, [
                            'file' => new CURLFile($downloaded['path'], $downloaded['mime'], $downloaded['name']),
                        ]);
                        if (!empty($proxy_args['proxy'])) {
                            curl_setopt($ch, CURLOPT_PROXY, $proxy_args['proxy']);
                            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
                        }
                        $upload_raw = curl_exec($ch);
                        $curl_error = curl_error($ch);
                        curl_close($ch);
                        @unlink($downloaded['path']);

                        if ($curl_error) {
                            error_log('[SSP Rubika] upload curl error: ' . $curl_error);
                        } else {
                            $upload_resp = json_decode($upload_raw, true);
                            error_log('[SSP Rubika] upload Response: ' . $upload_raw);
                            $file_id = $upload_resp['data']['file_id'] ?? ($upload_resp['result']['file_id'] ?? ($upload_resp['file_id'] ?? ''));

                            if (!empty($file_id)) {
                                // Step 3: Send file with file_id
                                $send_body = json_encode([
                                    'chat_id' => (string)$channel_id,
                                    'file_id' => $file_id,
                                    'text' => $message,
                                ]);
                                $send_response = wp_remote_post("$base_url/sendFile", array_merge([
                                    'timeout' => 30,
                                    'headers' => ['Content-Type' => 'application/json'],
                                    'body' => $send_body,
                                ], $proxy_args));

                                if (!is_wp_error($send_response)) {
                                    $send_raw = wp_remote_retrieve_body($send_response);
                                    $send_resp = json_decode($send_raw, true);
                                    error_log('[SSP Rubika] sendFile Response: ' . $send_raw);
                                    if (($send_resp['status'] ?? '') === 'OK') {
                                        return ['success' => true, 'response' => $send_resp['data']['message_id'] ?? ($send_resp['result']['message_id'] ?? '')];
                                    }
                                }
                            }
                        }
                    }
                }
            }
            error_log('[SSP Rubika] sendFile pipeline failed, sending text only');
        }

        // Send text message
        $url = "$base_url/sendMessage";
        $json_body = json_encode(['chat_id' => (string)$channel_id, 'text' => $message]);
        $response = wp_remote_post($url, array_merge([
            'timeout' => 30,
            'headers' => ['Content-Type' => 'application/json'],
            'body' => $json_body,
        ], $proxy_args));

        if (is_wp_error($response)) {
            error_log('[SSP Rubika] WP Error: ' . $response->get_error_message() . ' | URL: ' . $url);
            return ['success' => false, 'response' => $response->get_error_message()];
        }

        $raw_response = wp_remote_retrieve_body($response);
        $code = wp_remote_retrieve_response_code($response);
        $body_response = json_decode($raw_response, true);

        error_log('[SSP Rubika] HTTP ' . $code . ' | Request: ' . $json_body . ' | Response: ' . $raw_response);

        // If JSON fails with INVALID_INPUT, try form-encoded
        if ($code === 200 && ($body_response['status'] ?? '') !== 'OK') {
            error_log('[SSP Rubika] JSON failed, trying form-encoded...');
            $form_response = wp_remote_post($url, array_merge([
                'timeout' => 30,
                'body' => ['chat_id' => (string)$channel_id, 'text' => $message],
            ], $proxy_args));

            if (!is_wp_error($form_response)) {
                $raw_response = wp_remote_retrieve_body($form_response);
                $code = wp_remote_retrieve_response_code($form_response);
                $body_response = json_decode($raw_response, true);
                error_log('[SSP Rubika] Form-encoded HTTP ' . $code . ' | Response: ' . $raw_response);
            }
        }

        $is_success = $code === 200 && ($body_response['status'] ?? '') === 'OK';
        return [
            'success' => $is_success,
            'response' => $is_success ? ($body_response['result']['message_id'] ?? '') : ($body_response['status'] ?? ($body_response['description'] ?? 'خطا')),
        ];
    }

    private function send_bot_platform($platform, $token, $message, $channel_id, $image_url, $has_media, $is_video = false) {
        $endpoints = [
            'telegram' => ['base' => 'https://api.telegram.org/bot' . $token, 'parse_mode' => true],
            'bale' => ['base' => 'https://tapi.bale.ai/bot' . $token, 'parse_mode' => true],
            'eitaa' => ['base' => 'https://eitaayar.ir/api/' . $token, 'parse_mode' => false],
        ];

        if (!isset($endpoints[$platform])) return ['success' => false, 'response' => 'پلتفرم نامعتبر'];

        $config = $endpoints[$platform];
        $proxy_args = $this->get_proxy_args();
        $response = null;

        // Eitaa: use sendFile with curl for media
        if ($platform === 'eitaa') {
            if ($has_media && !empty($image_url)) {
                error_log('[SSP Eitaa] sendFile attempt: url=' . $image_url . ' chat=' . $channel_id);
                $downloaded = $this->download_file_for_upload($image_url, $proxy_args);
                if ($downloaded) {
                    $api_url = $config['base'] . '/sendFile';
                    $ch = curl_init($api_url);
                    curl_setopt($ch, CURLOPT_POST, true);
                    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
                    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
                    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                    curl_setopt($ch, CURLOPT_POSTFIELDS, [
                        'file' => new CURLFile($downloaded['path'], $downloaded['mime'], $downloaded['name']),
                        'chat_id' => $channel_id,
                        'caption' => $message,
                        'title' => '',
                    ]);
                    $raw_response = curl_exec($ch);
                    $curl_error = curl_error($ch);
                    curl_close($ch);
                    @unlink($downloaded['path']);

                    if ($curl_error) {
                        error_log('[SSP Eitaa] sendFile curl error: ' . $curl_error);
                        return ['success' => false, 'response' => $curl_error];
                    }
                    $body_response = json_decode($raw_response, true);
                    $ok = $body_response['ok'] ?? false;
                    error_log('[SSP Eitaa] sendFile Response: ' . $raw_response);

                    if ($ok) {
                        return ['success' => true, 'response' => $body_response['result']['message_id'] ?? ''];
                    }
                    // sendFile failed, fall through to text
                    error_log('[SSP Eitaa] sendFile failed, sending text only');
                } else {
                    error_log('[SSP Eitaa] Failed to download image, sending text only');
                }
            }
            // Text-only fallback for Eitaa
            $url = $config['base'] . '/sendMessage';
            $body = ['chat_id' => $channel_id, 'text' => $message];
            $response = wp_remote_post($url, array_merge(['timeout' => 30, 'body' => $body], $proxy_args));

            if (is_wp_error($response)) return ['success' => false, 'response' => $response->get_error_message()];
            $raw_response = wp_remote_retrieve_body($response);
            $code = wp_remote_retrieve_response_code($response);
            $body_response = json_decode($raw_response, true);
            error_log('[SSP Eitaa] sendMessage HTTP ' . $code . ' | Response: ' . $raw_response);

            return [
                'success' => $code === 200 && ($body_response['ok'] ?? false),
                'response' => $body_response['description'] ?? '',
            ];
        }

        // Telegram and Bale: standard sendPhoto/sendVideo/sendMessage
        // Bale cannot fetch external URLs — must download and upload like Eitaa
        if ($platform === 'bale' && $has_media && !empty($image_url)) {
            $endpoint = $is_video ? '/sendVideo' : '/sendPhoto';
            $field_name = $is_video ? 'video' : 'photo';
            $downloaded = $this->download_file_for_upload($image_url, $proxy_args);
            if ($downloaded) {
                $api_url = $config['base'] . $endpoint;
                $ch = curl_init($api_url);
                curl_setopt($ch, CURLOPT_POST, true);
                curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
                curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                $post_fields = [
                    $field_name => new CURLFile($downloaded['path'], $downloaded['mime'], $downloaded['name']),
                    'chat_id' => $channel_id,
                    'caption' => $message,
                ];
                if ($config['parse_mode']) $post_fields['parse_mode'] = 'HTML';
                curl_setopt($ch, CURLOPT_POSTFIELDS, $post_fields);
                $raw_response = curl_exec($ch);
                $curl_error = curl_error($ch);
                curl_close($ch);
                @unlink($downloaded['path']);
                if ($curl_error) return ['success' => false, 'response' => $curl_error];
                $body_response = json_decode($raw_response, true);
                return [
                    'success' => ($body_response['ok'] ?? false),
                    'response' => $body_response['description'] ?? ($body_response['result']['message_id'] ?? ''),
                ];
            }
            // Download failed — Bale can't fetch URLs, force text-only
            error_log('[SSP Bale] Download failed, sending text only');
            $has_media = false;
        }

        if ($has_media && $is_video) {
            $endpoint = '/sendVideo';
            $body = ['video' => $image_url, 'caption' => $message];
        } elseif ($has_media) {
            $endpoint = '/sendPhoto';
            $body = ['photo' => $image_url, 'caption' => $message];
        } else {
            $endpoint = '/sendMessage';
            $body = ['text' => $message];
        }
        $url = $config['base'] . $endpoint;

        if ($config['parse_mode']) $body['parse_mode'] = 'HTML';
        if (!empty($channel_id)) $body['chat_id'] = $channel_id;

        $doh = $this->get_doh_settings();
        $doh_enabled = $doh['enabled'];
        $doh_server = $doh['server'];
        $proxy_enabled = !empty($this->get_proxy_settings()['enabled']);
        $relay_enabled = $this->is_telegram_relay_enabled();

        // For Telegram: try multiple methods to bypass filtering
        if ($platform === 'telegram') {
            // 1. Try Telegram Relay first (self-hosted PHP on foreign hosting)
            if ($relay_enabled) {
                $relay_result = $this->send_via_relay($has_media ? ltrim($endpoint, '/') : 'sendMessage', $body);
                if ($relay_result['success']) {
                    return ['success' => true, 'response' => ''];
                }
                // Relay failed, continue to other methods
            }

            // 2. Try DoH if enabled and proxy not configured
            if ($doh_enabled && !$proxy_enabled) {
                $response = $this->wp_remote_with_doh($url, ['timeout' => 30, 'body' => $body], $doh_server, 'POST');
            }

            // 3. Fallback to direct/proxy if DoH fails or not enabled
            if (!$response || is_wp_error($response)) {
                $response = wp_remote_post($url, array_merge(['timeout' => 30, 'body' => $body], $proxy_args));
            }
        } else {
            $response = wp_remote_post($url, array_merge(['timeout' => 30, 'body' => $body], $proxy_args));
        }

        if (is_wp_error($response)) return ['success' => false, 'response' => $response->get_error_message()];
        $body_response = json_decode(wp_remote_retrieve_body($response), true);
        $code = wp_remote_retrieve_response_code($response);

        return [
            'success' => $code === 200 && ($body_response['ok'] ?? false),
            'response' => $body_response['description'] ?? ''
        ];
    }

    private function download_file_for_upload($url, $proxy_args = []) {
        $response = wp_remote_get($url, array_merge([
            'timeout' => 30,
        ], $proxy_args));

        if (is_wp_error($response)) {
            error_log('[SSP Upload] Download failed: ' . $response->get_error_message() . ' | URL: ' . $url);
            return false;
        }

        $code = wp_remote_retrieve_response_code($response);
        if ($code !== 200) {
            error_log('[SSP Upload] Download HTTP ' . $code . ' | URL: ' . $url);
            return false;
        }

        // Determine extension from URL or content-type
        $url_ext = strtolower(pathinfo($url, PATHINFO_EXTENSION));
        $mime = wp_remote_retrieve_header($response, 'content-type');
        if (empty($mime)) {
            $mime_map = ['jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'png' => 'image/png', 'gif' => 'image/gif', 'webp' => 'image/webp', 'mp4' => 'video/mp4'];
            $mime = $mime_map[$url_ext] ?? 'application/octet-stream';
        }
        $ext_map = ['image/jpeg' => '.jpg', 'image/png' => '.png', 'image/gif' => '.gif', 'image/webp' => '.webp', 'video/mp4' => '.mp4'];
        $ext = $ext_map[$mime] ?? ('.' . ($url_ext ?: 'bin'));

        $name = basename(wp_parse_url($url, PHP_URL_PATH)) ?: ('upload_' . time() . $ext);
        if (strpos($name, '.') === false) $name .= $ext;

        $tmp = sys_get_temp_dir() . '/ssp_' . wp_generate_password(8, false) . $ext;
        $body = wp_remote_retrieve_body($response);
        file_put_contents($tmp, $body);
        $size = filesize($tmp);
        error_log('[SSP Upload] Downloaded: ' . $size . ' bytes | ' . $name . ' | ' . $tmp);

        if ($size === 0) {
            error_log('[SSP Upload] WARNING: Downloaded file is empty! URL: ' . $url);
            @unlink($tmp);
            return false;
        }

        return ['path' => $tmp, 'mime' => $mime, 'name' => $name];
    }

    private function send_media_group($platform, $token, $channel_id, $media_urls, $caption = '') {
        if ($platform === 'telegram') {
            $base = 'https://api.telegram.org/bot' . $token;
        } elseif ($platform === 'bale') {
            $base = 'https://tapi.bale.ai/bot' . $token;
        } else {
            // Fallback: send first media only
            return $this->send_to_messenger($platform, $token, $caption, $channel_id, $media_urls[0] ?? '');
        }

        $media = [];
        foreach ($media_urls as $idx => $murl) {
            $is_video = $this->is_video_url($murl);
            $item = ['type' => $is_video ? 'video' : 'photo', 'media' => $murl];
            if ($idx === 0 && !empty($caption)) $item['caption'] = $caption;
            $media[] = $item;
        }

        $body = ['chat_id' => (string)$channel_id, 'media' => $media];
        $url = "$base/sendMediaGroup";

        $proxy_args = $this->get_proxy_args();
        $response = wp_remote_post($url, array_merge([
            'timeout' => 60,
            'body' => json_encode($body),
            'headers' => ['Content-Type' => 'application/json'],
        ], $proxy_args));

        if (is_wp_error($response)) return ['success' => false, 'response' => $response->get_error_message()];
        $raw = wp_remote_retrieve_body($response);
        $code = wp_remote_retrieve_response_code($response);
        $resp = json_decode($raw, true);
        $ok = ($platform === 'telegram' || $platform === 'bale') ? ($resp['ok'] ?? false) : false;

        return [
            'success' => $code === 200 && $ok,
            'response' => $resp['description'] ?? ($resp['result'][0]['message_id'] ?? ''),
        ];
    }
}
