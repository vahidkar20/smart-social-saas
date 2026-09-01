<?php

trait SSP_Queue {

    public function auto_generate_content() {
        $users = get_users(['meta_key' => 'ssp_ai_auto_generate', 'meta_value' => '1', 'fields' => 'ID']);
        foreach ($users as $user_id) {
            if ($this->get_user_plan($user_id) !== 'pro') continue;

            $ai_api_key = get_user_meta($user_id, 'ssp_ai_api_key', true);
            $ai_topic = get_user_meta($user_id, 'ssp_ai_topic', true);

            if (empty($ai_api_key) || empty($ai_topic)) continue;

            try {
                $content = $this->generate_ai_content($user_id, $ai_topic);
                if ($content) {
                    $this->add_to_queue($user_id, 'ai_generated', [
                        'title' => $content['title'],
                        'message' => $content['message'],
                        'hashtags' => $content['hashtags'] ?? '',
                    ], 5);
                }
            } catch (Exception $e) {
                $this->log_activity($user_id, 'system', 'خطا در تولید خودکار', $e->getMessage(), 'error');
            }
        }
    }

    private function generate_ai_content($user_id, $topic) {
        $ai_provider = get_user_meta($user_id, 'ssp_ai_provider', true) ?: 'openai';
        $ai_api_key = get_user_meta($user_id, 'ssp_ai_api_key', true);
        $ai_model = get_user_meta($user_id, 'ssp_ai_model', true) ?: 'gpt-4o-mini';
        $ai_prompt_mode = get_user_meta($user_id, 'ssp_ai_prompt_mode', true) ?: 'simple';

        if ($ai_prompt_mode === 'advanced') {
            $custom_prompt = get_user_meta($user_id, 'ssp_ai_custom_prompt', true);
            if (!empty($custom_prompt)) {
                $prompt = str_replace(
                    ['{topic}', '{title}', '{content}'],
                    [$topic, '', ''],
                    $custom_prompt
                );
            } else {
                $prompt = $this->get_default_ai_prompt($topic);
            }
        } else {
            $prompt = $this->get_default_ai_prompt($topic);
        }

        $result = $this->call_ai_api($ai_provider, $ai_api_key, $ai_model, $prompt, true);
        $decoded = $this->parse_ai_json($result['content']);

        if (!$decoded) throw new Exception('AI response not valid JSON');

        return [
            'title' => $this->clean_messenger_text($decoded['title']),
            'message' => $this->clean_messenger_text($decoded['message']),
            'hashtags' => $decoded['hashtags'] ?? '',
            'tokens_used' => $result['tokens_used'] ?? 0,
            'provider' => $ai_provider,
        ];
    }

    private function clean_messenger_text($text) {
        if (empty($text)) return $text;
        // Remove HTML tags
        $text = wp_strip_all_tags($text);
        $text = preg_replace('/<[^>]+>/', '', $text);
        // Fix HTML entities
        $text = str_replace(['&nbsp;', '&amp;', '&lt;', '&gt;', '&quot;', '&#8211;', '&#8212;'], [' ', '&', '<', '>', '"', '-', '-'], $text);
        // Clean multiple newlines
        $text = preg_replace('/\n{3,}/', "\n\n", $text);
        return trim($text);
    }

    private function get_default_ai_prompt($topic) {
        return "پست تلگرامی فارسی درباره «{$topic}» بنویس.\n" .
            "مهم: این پست برای تلگرام است. فقط متن ساده بنویس.\n\n" .
            "قوانین:\n" .
            "- لحن طبیعی و مثل یک انسان واقعی بنویس\n" .
            "- عنوان جذاب ≤80 کاراکتر\n" .
            "- متن 150-250 کلمه، پاراگراف کوتاه\n" .
            "- مطلقاً از هیچ کد HTML استفاده نکن\n" .
            "- هیچ تگی مثل p br b strong h2 ul li ننویس\n" .
            "- بولد فقط با **\n" .
            "- لیست فقط با - یا شماره\n" .
            "- هیچ هشتگی (#) در متن اضافه نکن\n\n" .
            'خروجی: {"title":"","message":""}';
    }

    public function on_post_publish($new_status, $old_status, $post) {
        if ($new_status !== 'publish' || $old_status === 'publish') return;
        if (!in_array($post->post_type, ['post', 'page', 'product'])) return;

        $users = get_users(['meta_key' => 'ssp_auto_wp_posts', 'meta_value' => '1', 'fields' => 'ID']);
        foreach ($users as $user_id) {
            $wp_sites = $this->get_user_wp_sites($user_id);
            $has_auto = false;
            foreach ($wp_sites as $site) {
                if (!empty($site['auto_publish'])) { $has_auto = true; break; }
            }
            if (!$has_auto) continue;

            $featured_image_url = '';
            $thumbnail_id = get_post_thumbnail_id($post->ID);
            if ($thumbnail_id) {
                $featured_image_url = wp_get_attachment_url($thumbnail_id);
            }

            $this->add_to_queue($user_id, 'publish_post', [
                'post_id' => $post->ID,
                'title' => $post->post_title,
                'excerpt' => wp_trim_words($post->post_content, 30),
                'url' => get_permalink($post->ID),
                'featured_image_url' => $featured_image_url,
            ], 10);
        }
    }

    public function add_to_queue($user_id, $action_type, $payload, $priority = 5) {
        $daily_limit = $this->get_user_plan($user_id) === 'pro' ? 9999 : 5;
        if ($this->get_today_count($user_id) >= $daily_limit) {
            return ['success' => false, 'message' => 'محدودیت روزانه پر شده'];
        }

        $queue = $this->get_global_items('queue');
        $id = $this->next_id($queue);
        $queue[] = [
            'id' => $id,
            'user_id' => (int)$user_id,
            'action_type' => $action_type,
            'priority' => (int)$priority,
            'payload' => $payload,
            'status' => 'pending',
            'attempts' => 0,
            'max_attempts' => 3,
            'error_message' => '',
            'created_at' => current_time('mysql'),
            'processed_at' => '',
        ];
        $this->set_global_items('queue', $queue);
        return ['success' => true, 'queue_id' => $id];
    }

    public function process_queue() {
        $queue = $this->get_global_items('queue');
        if (empty($queue)) return;

        $pending = [];
        foreach ($queue as &$item) {
            if ($item['status'] === 'pending' && $item['attempts'] < $item['max_attempts']) {
                $pending[] = &$item;
            }
        }
        unset($item);

        usort($pending, function($a, $b) {
            return $b['priority'] <=> $a['priority'];
        });
        $pending = array_slice($pending, 0, 5); // Reduced from 10 to 5 per cycle

        $first = true;
        foreach ($pending as &$item) {
            if (!$first) usleep((int)(SSP_MSG_DELAY * 1000000)); // Delay between items to avoid rate limits
            $first = false;

            $item['status'] = 'processing';
            $item['attempts']++;

            try {
                $payload = is_string($item['payload']) ? json_decode($item['payload'], true) : $item['payload'];
                $this->execute_action($item['user_id'], $item['action_type'], $payload);
                $item['status'] = 'completed';
                $item['processed_at'] = current_time('mysql');
            } catch (Exception $e) {
                $item['status'] = $item['attempts'] >= $item['max_attempts'] ? 'failed' : 'pending';
                $item['error_message'] = $e->getMessage();
            }
        }
        unset($item);

        $this->set_global_items('queue', $queue);
    }

    private function execute_action($user_id, $action_type, $payload) {
        switch ($action_type) {
            case 'publish_post':
            case 'manual_send':
            case 'ai_generated':
            case 'rss_post':
                return $this->action_publish_content($user_id, $payload, $action_type);
            default:
                throw new Exception('نوع اکشن نامعتبر');
        }
    }

    private function action_publish_content($user_id, $payload, $action_type) {
        $title = $payload['title'] ?? '';
        $message = $payload['message'] ?? ($payload['excerpt'] ?? '');
        $url = $payload['url'] ?? '';
        $hashtags = $payload['hashtags'] ?? '';
        $image_url = $payload['image_url'] ?? '';

        $ai_api_key = get_user_meta($user_id, 'ssp_ai_api_key', true);
        $ai_result = ['title' => $title, 'message' => $message, 'hashtags' => $hashtags, 'provider' => '', 'tokens_used' => 0];

        if (!empty($ai_api_key) && $action_type !== 'ai_generated' && empty($payload['skip_ai_rewrite'])) {
            $ai_rewrite = get_user_meta($user_id, 'ssp_ai_rewrite', true);
            $ai_hashtags = get_user_meta($user_id, 'ssp_ai_hashtags', true);
            if ($ai_rewrite || $ai_hashtags) {
                $ai_result = $this->process_with_ai($user_id, $title, $message);
            }
        }

        $final_title = $ai_result['title'] ?? $title;
        $final_message = $ai_result['message'] ?? $message;
        $final_hashtags = $ai_result['hashtags'] ?? $hashtags;

        // Auto-generate image for AI content if enabled
        if (empty($image_url) && $action_type === 'ai_generated' && $this->get_user_plan($user_id) === 'pro') {
            $image_settings = get_user_meta($user_id, 'ssp_image_settings', true) ?: [];
            if (!empty($image_settings['auto_image']) && !empty($ai_api_key)) {
                try {
                    $img_result = $this->generate_ai_image($user_id, $final_title . ' - ' . $final_message, $image_settings['default_size'] ?? '1024x1024', $image_settings['default_quality'] ?? 'standard');
                    $image_url = $img_result['url'] ?? '';
                } catch (Exception $e) {
                    // Image generation failed, continue without image
                }
            }
        }

        // Send to messengers
        $all_messengers = $this->get_enabled_messengers($user_id);
        $selected_ids = $payload['selected_messengers'] ?? [];
        if (!empty($selected_ids)) {
            $messengers = array_filter($all_messengers, function($m) use ($selected_ids) {
                return in_array((int)$m['id'], $selected_ids);
            });
        } else {
            $messengers = $all_messengers;
        }
        $first = true;
        $failed_count = 0;
        $last_error = '';
        $is_album = false;
        $media_urls = [];

        // Detect album (JSON array of media URLs)
        if (!empty($image_url) && $image_url[0] === '[') {
            $decoded = json_decode($image_url, true);
            if (is_array($decoded) && count($decoded) > 0) {
                $is_album = true;
                $media_urls = array_column($decoded, 'url');
            }
        }

        foreach ($messengers as $messenger) {
            if (!$first) usleep((int)(SSP_MSG_DELAY * 1000000));
            $first = false;

            $msg = $this->format_message($user_id, $final_title, $final_message, $url, $final_hashtags);
            $result = ['success' => false, 'response' => ''];

            try {
                if ($is_album && count($media_urls) > 1 && in_array($messenger['platform'], ['telegram', 'bale'])) {
                    $result = $this->send_media_group($messenger['platform'], $messenger['token'], $messenger['channel_id'], $media_urls, $msg);
                } else {
                    $single_url = $is_album ? ($media_urls[0] ?? '') : $image_url;
                    $result = $this->send_to_messenger($messenger['platform'], $messenger['token'], $msg, $messenger['channel_id'], $single_url);
                }
            } catch (\Error $e) {
                $result = ['success' => false, 'response' => 'PHP Error: ' . $e->getMessage()];
                error_log('[SSP ' . $messenger['platform'] . '] Send error: ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
            } catch (\Exception $e) {
                $result = ['success' => false, 'response' => 'Exception: ' . $e->getMessage()];
                error_log('[SSP ' . $messenger['platform'] . '] Send exception: ' . $e->getMessage());
            }

            $this->log_activity($user_id, $messenger['platform'], $final_title, $msg,
                $result['success'] ? 'success' : 'error', $result['response'] ?? '',
                $ai_result['provider'], $ai_result['tokens_used'], $image_url);

            if (!$result['success']) {
                $failed_count++;
                $last_error = $result['response'] ?? '';
            }
        }

        // Send email notification on failure
        if ($failed_count > 0) {
            $this->send_email_notification($user_id, 'queue_failed', [
                'count' => $failed_count,
                'error' => $last_error,
            ]);
        }

        // Cleanup uploaded media files after sending
        $cleanup_urls = $is_album ? $media_urls : ($image_url ? [$image_url] : []);
        foreach ($cleanup_urls as $curl) {
            if (!empty($curl) && strpos($curl, '/ssp-media/') !== false) {
                $upload_dir = wp_upload_dir();
                $media_path = str_replace($upload_dir['baseurl'], $upload_dir['basedir'], $curl);
                if (file_exists($media_path)) @unlink($media_path);
            }
        }
    }

    private function process_with_ai($user_id, $title, $message) {
        $ai_provider = get_user_meta($user_id, 'ssp_ai_provider', true) ?: 'openai';
        $ai_api_key = get_user_meta($user_id, 'ssp_ai_api_key', true);
        $ai_model = get_user_meta($user_id, 'ssp_ai_model', true) ?: 'gpt-4o-mini';

        $ai_rewrite = get_user_meta($user_id, 'ssp_ai_rewrite', true);

        $tasks = [];
        if ($ai_rewrite) $tasks[] = 'بازنویسی منحصر به فرد با لحن طبیعی';

        $prompt = "وظایف: " . implode('; ', $tasks) . "\n" .
            "عنوان: {$title}\nمحتوا: {$message}\n\n" .
            "قوانین:\n" .
            "- محتوا را طوری بازنویسی کن که طبیعی و انسانی به نظر برسه\n" .
            "- نکات عملی و واقعی حفظ بشه\n" .
            "- ساختار و معنی حفظ بشه\n" .
            "- متن باید کاملاً ساده باشه. هیچ تگ HTML مثل <p> <br> <b> <strong> استفاده نکن\n" .
            "- بولد رو با ** بنویس\n" .
            "- فقط اطلاعات محتوای اصلی رو حفظ کن. چیز نامطمئن اضافه نکن\n" .
            "- هیچ هشتگی (#) در متن اضافه نکن. نه در انتها و نه در وسط متن\n" .
            '{"title":"","message":""}';

        try {
            $response = $this->call_ai_api($ai_provider, $ai_api_key, $ai_model, $prompt, true);
            $decoded = $this->parse_ai_json($response['content']);
            return [
                'title' => $this->clean_messenger_text($decoded['title'] ?? $title),
                'message' => $this->clean_messenger_text($decoded['message'] ?? $message),
                'hashtags' => $decoded['hashtags'] ?? '',
                'provider' => $ai_provider,
                'tokens_used' => $response['tokens_used'] ?? 0,
            ];
        } catch (Exception $e) {
            return ['title' => $title, 'message' => $message, 'hashtags' => '', 'provider' => '', 'tokens_used' => 0];
        }
    }

    public function process_schedules() {
        $now = current_time('mysql');
        // Only query users who actually have schedules (avoids loading ALL users)
        $users = get_users(['meta_key' => 'ssp_schedules', 'meta_compare' => 'EXISTS', 'fields' => 'ID']);
        foreach ($users as $user_id) {
            $schedules = $this->get_user_items($user_id, 'schedules');
            if (empty($schedules)) continue;

            $changed = false;
            foreach ($schedules as &$sch) {
                if ($sch['status'] === 'pending' && $sch['scheduled_at'] <= $now) {
                    $this->add_to_queue($user_id, 'manual_send', [
                        'title' => $sch['title'],
                        'message' => $sch['message'],
                    ], 8);

                    if (!empty($sch['recurring'])) {
                        $sch['scheduled_at'] = $this->get_next_recurring($sch['scheduled_at'], $sch['recurring']);
                    } else {
                        $sch['status'] = 'completed';
                    }
                    $changed = true;
                }
            }
            unset($sch);
            if ($changed) {
                $this->set_user_items($user_id, 'schedules', $schedules);
            }
        }
    }

    private function get_next_recurring($current, $type) {
        $dt = new DateTime($current);
        switch ($type) {
            case 'daily': $dt->modify('+1 day'); break;
            case 'weekly': $dt->modify('+1 week'); break;
            case 'monthly': $dt->modify('+1 month'); break;
        }
        return $dt->format('Y-m-d H:i:s');
    }
}
