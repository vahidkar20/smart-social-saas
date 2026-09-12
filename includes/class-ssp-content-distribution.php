<?php
/**
 * SSP Content Distribution Trait - Automated content distribution to messengers
 */
trait SSP_ContentDistribution {

    public function handle_get_distributions() {
        $user_id = $this->ajax_require_auth();
        $profile_id = $this->get_active_profile_id($user_id);
        $distributions = $this->get_profile_items($user_id, 'distributions', $profile_id);
        wp_send_json_success(['distributions' => $distributions]);
    }

    public function handle_save_distribution() {
        $user_id = $this->ajax_require_auth();
        if ($this->get_user_plan($user_id) !== 'pro') wp_send_json_error(['message' => 'توزیع خودکار فقط در پلن Pro موجود است']);
        $profile_id = $this->get_active_profile_id($user_id);
        $distributions = $this->get_profile_items($user_id, 'distributions', $profile_id);
        $id = isset($_POST['distribution_id']) ? intval($_POST['distribution_id']) : 0;

        // Build schedule_times from multiple time inputs or interval
        $schedule_type = sanitize_text_field($_POST['schedule_type'] ?? 'daily');
        $schedule_times = sanitize_text_field($_POST['schedule_times'] ?? '10:00');
        $schedule_interval = intval($_POST['schedule_interval'] ?? 0);

        // If interval mode, generate time slots automatically
        if ($schedule_interval > 0 && $schedule_type !== 'once') {
            $start_hour = intval($_POST['interval_start'] ?? 8);
            $end_hour = intval($_POST['interval_end'] ?? 22);
            $times = [];
            for ($h = $start_hour; $h <= $end_hour; $h += $schedule_interval) {
                $times[] = sprintf('%02d:00', $h);
            }
            $schedule_times = implode(',', $times);
        }

        $data = [
            'name' => sanitize_text_field($_POST['name'] ?? 'توزیع جدید'),
            'only_new' => intval($_POST['only_new'] ?? 0),
            'source_site_id' => intval($_POST['source_site_id'] ?? 0),
            'source_type' => sanitize_text_field($_POST['source_type'] ?? 'product'),
            'filter_categories' => sanitize_text_field($_POST['filter_categories'] ?? ''),
            'filter_tags' => sanitize_text_field($_POST['filter_tags'] ?? ''),
            'filter_min_price' => intval($_POST['filter_min_price'] ?? 0),
            'filter_max_price' => intval($_POST['filter_max_price'] ?? 0),
            'filter_stock' => sanitize_text_field($_POST['filter_stock'] ?? ''),
            'sort_order' => sanitize_text_field($_POST['sort_order'] ?? 'newest'),
            'daily_limit' => intval($_POST['daily_limit'] ?? 1),
            'message_template' => sanitize_textarea_field($_POST['message_template'] ?? "{title}\n{excerpt}\n{url}"),
            'target_messengers' => sanitize_text_field($_POST['target_messengers'] ?? ''),
            'include_image' => intval($_POST['include_image'] ?? 0),
            'schedule_type' => $schedule_type,
            'schedule_times' => $schedule_times,
            'schedule_interval' => $schedule_interval,
            'schedule_days' => sanitize_text_field($_POST['schedule_days'] ?? ''),
            'is_active' => intval($_POST['is_active'] ?? 1),
            // New content delay settings
            'new_delay_value' => intval($_POST['new_delay_value'] ?? 5),
            'new_delay_unit' => sanitize_text_field($_POST['new_delay_unit'] ?? 'minutes'),
        ];
        if ($id > 0) {
            $found = false;
            foreach ($distributions as &$d) {
                if ((int)$d['id'] === $id) {
                    $data['id'] = $id;
                    $data['created_at'] = $d['created_at'] ?? current_time('mysql');
                    $data['last_run'] = $d['last_run'] ?? null;
                    $data['last_run_time'] = $d['last_run_time'] ?? null;
                    $d = $data;
                    $found = true;
                    break;
                }
            }
            unset($d);
            if (!$found) wp_send_json_error(['message' => 'توزیع یافت نشد']);
        } else {
            $data['id'] = $this->next_id($distributions);
            $data['created_at'] = current_time('mysql');
            $data['last_run'] = null;
            $data['last_run_time'] = null;
            $distributions[] = $data;
        }
        $this->set_profile_items($user_id, 'distributions', $distributions, $profile_id);
        wp_send_json_success(['message' => 'توزیع ذخیره شد!', 'id' => $data['id']]);
    }

    public function handle_delete_distribution() {
        $user_id = $this->ajax_require_auth();
        $profile_id = $this->get_active_profile_id($user_id);
        $this->delete_profile_item($user_id, 'distributions', intval($_POST['distribution_id']), $profile_id);
        wp_send_json_success(['message' => 'توزیع حذف شد']);
    }

    public function handle_toggle_distribution() {
        $user_id = $this->ajax_require_auth();
        $profile_id = $this->get_active_profile_id($user_id);
        $distributions = $this->get_profile_items($user_id, 'distributions', $profile_id);
        $id = intval($_POST['distribution_id']);
        foreach ($distributions as &$d) {
            if ((int)$d['id'] === $id) {
                $d['is_active'] = empty($d['is_active']) ? 1 : 0;
                break;
            }
        }
        unset($d);
        $this->set_profile_items($user_id, 'distributions', $distributions, $profile_id);
        wp_send_json_success(['message' => 'وضعیت تغییر کرد']);
    }

    public function handle_preview_distribution() {
        $user_id = $this->ajax_require_auth();
        $profile_id = $this->get_active_profile_id($user_id);
        $distributions = $this->get_profile_items($user_id, 'distributions', $profile_id);
        $id = intval($_POST['distribution_id']);
        $dist = null;
        foreach ($distributions as $d) {
            if ((int)$d['id'] === $id) { $dist = $d; break; }
        }
        if (!$dist) wp_send_json_error(['message' => 'توزیع یافت نشد']);
        $items = $this->get_distribution_items($user_id, $dist, true);
        $previews = [];
        $template = $dist['message_template'] ?? "{title}\n{excerpt}\n{url}";
        foreach (array_slice($items, 0, 5) as $item) {
            $previews[] = $this->format_distribution_message($template, $item);
        }
        wp_send_json_success(['previews' => $previews, 'total' => count($items)]);
    }

    public function handle_run_distribution_now() {
        $user_id = $this->ajax_require_auth();
        if ($this->get_user_plan($user_id) !== 'pro') wp_send_json_error(['message' => 'فقط در پلن Pro']);
        $profile_id = $this->get_active_profile_id($user_id);
        $distributions = $this->get_profile_items($user_id, 'distributions', $profile_id);
        $id = intval($_POST['distribution_id']);
        $dist = null;
        foreach ($distributions as $d) {
            if ((int)$d['id'] === $id) { $dist = $d; break; }
        }
        if (!$dist) wp_send_json_error(['message' => 'توزیع یافت نشد']);
        $result = $this->execute_single_distribution($user_id, $dist);
        wp_send_json_success($result);
    }

    public function process_distributions() {
        $user_ids = $this->get_users_with_active_distributions();
        foreach ($user_ids as $user_id) {
            $profiles = is_array($__tmp = get_user_meta($user_id, 'ssp_profiles', true)) ? $__tmp : [];
            if (empty($profiles)) {
                $distributions = $this->get_profile_items($user_id, 'distributions', 1);
                foreach ($distributions as $dist) {
                    if (empty($dist['is_active'])) continue;
                    if (!$this->should_run_distribution($dist)) continue;
                    $this->execute_single_distribution($user_id, $dist);
                }
            } else {
                foreach ($profiles as $p) {
                    $profile_id = (int)($p['id'] ?? 1);
                    $distributions = $this->get_profile_items($user_id, 'distributions', $profile_id);
                    foreach ($distributions as $dist) {
                        if (empty($dist['is_active'])) continue;
                        if (!$this->should_run_distribution($dist)) continue;
                        $this->execute_single_distribution($user_id, $dist);
                    }
                }
            }
        }
    }

    private function should_run_distribution($dist) {
        $now = current_time('timestamp');
        $current_hour = intval(current_time('H'));  // Use WP timezone, not server
        $today = current_time('Y-m-d');
        $schedule_type = $dist['schedule_type'] ?? 'daily';
        $schedule_times = $dist['schedule_times'] ?? ($dist['schedule_time'] ?? '10:00');

        // Parse time slots (comma-separated) and normalize to HH:MM format
        $times = array_filter(array_map('trim', explode(',', $schedule_times)));
        $times = array_map(function($t) {
            $parts = explode(':', $t);
            return sprintf('%02d:%02d', intval($parts[0] ?? 0), intval($parts[1] ?? 0));
        }, $times);

        // Find the next matching time slot
        $last_run_time = $dist['last_run_time'] ?? '';
        $last_run_date = $dist['last_run'] ? date('Y-m-d', strtotime($dist['last_run'])) : '';

        foreach ($times as $time_str) {
            $scheduled_ts = strtotime($today . ' ' . $time_str);
            if ($now < $scheduled_ts) continue; // Not yet this time

            // Check if we already ran at this specific time today
            if ($last_run_date === $today && $last_run_time === $time_str) {
                continue; // Already ran at this time today
            }

            // Check schedule type
            if ($schedule_type === 'once') {
                if (!empty($dist['last_run'])) continue;
                return true;
            }

            if ($schedule_type === 'daily') {
                return true;
            }

            if ($schedule_type === 'weekly') {
                $days_str = trim($dist['schedule_days'] ?? '');
                if (empty($days_str)) continue; // No days selected, skip
                $days = array_map('intval', explode(',', $days_str));
                $current_day = intval(current_time('w'));
                if (in_array($current_day, $days)) {
                    return true;
                }
            }
        }

        return false;
    }

    private function execute_single_distribution($user_id, $dist) {
        $items = $this->get_distribution_items($user_id, $dist);
        if (empty($items)) return ['message' => 'محتوایی برای ارسال یافت نشد', 'sent' => 0];

        // Check daily limit
        $daily_limit = $dist['daily_limit'] ?? 5;
        $sent_today = $this->get_distribution_sent_count($user_id, $dist['id']);
        $remaining = max(0, $daily_limit - $sent_today);
        if ($remaining <= 0) return ['message' => 'سقف روزانه تکمیل شد', 'sent' => 0];

        // Send only 1 item per time slot
        $items = array_slice($items, 0, 1);

        $messengers = $this->get_enabled_messengers($user_id);
        $target_ids = array_filter(array_map('intval', explode(',', $dist['target_messengers'] ?? '')));
        $target_messengers = array_filter($messengers, function($m) use ($target_ids) {
            return empty($target_ids) || in_array((int)$m['id'], $target_ids);
        });

        $sent_count = 0;
        $template = $dist['message_template'] ?? "{title}\n{excerpt}\n{url}";
        $include_image = !empty($dist['include_image']) ? (int)$dist['include_image'] : 0;

        foreach ($items as $item) {
            $message = $this->format_distribution_message($template, $item);
            $image_url = $include_image ? ($item['image'] ?? '') : '';
            
            foreach ($target_messengers as $messenger) {
                $result = $this->send_to_messenger($messenger['platform'], $messenger['token'], $message, $messenger['channel_id'] ?? '', $image_url);
                $sent_count++;
                $this->log_activity($user_id, $messenger['platform'], 'توزیع خودکار', $item['title'] ?? 'محتوا', $result['success'] ? 'success' : 'error', $result['response'] ?? '');
                usleep((int)(SSP_MSG_DELAY * 1000000));
            }
            $this->mark_item_distributed($user_id, $dist, $item);
        }

        // Update last_run and last_run_time
        $this->update_distribution_last_run($user_id, $dist, current_time('H:i'));

        return ['message' => "{$sent_count} پیام ارسال شد", 'sent' => $sent_count];
    }

    private function get_distribution_items($user_id, $dist, $skip_history = false) {
        $source_type = $dist['source_type'] ?? 'product';
        $source_site_id = $dist['source_site_id'] ?? 0;
        $only_new = !empty($dist['only_new']);
        
        if ($source_type === 'draft') {
            $drafts = $this->get_user_items($user_id, 'drafts');
            return array_filter($drafts, function($d) { return !empty($d['content']); });
        }
        if (empty($source_site_id)) return [];
        $sites = $this->get_user_items($user_id, 'wp_sites');
        $site = null;
        foreach ($sites as $s) {
            if ((int)$s['id'] === $source_site_id) { $site = $s; break; }
        }
        if (!$site) return [];
        $site_base_url = untrailingslashit($site['site_url']) . '/?p=';
        $endpoint = rtrim($site['site_url'], '/');
        if ($source_type === 'product') {
            $endpoint .= '/wp-json/wc/v3/products';
            // فقط محصولات منتشر شده را دریافت کن
            $params = ['per_page' => 100, 'status' => 'publish', 'orderby' => 'date', 'order' => 'desc'];
        } else {
            $endpoint .= '/wp-json/wp/v2/posts';
            // فقط نوشته‌های منتشر شده را دریافت کن
            $params = ['per_page' => 100, 'status' => 'publish'];
        }
        if (!empty($dist['filter_categories'])) {
            $params['category'] = $dist['filter_categories'];
        }
        if (!empty($dist['filter_tags'])) {
            $params[($source_type === 'product') ? 'tag' : 'tags'] = $dist['filter_tags'];
        }
        if (!empty($dist['filter_min_price'])) {
            $params['min_price'] = $dist['filter_min_price'];
        }
        if (!empty($dist['filter_max_price'])) {
            $params['max_price'] = $dist['filter_max_price'];
        }
        if (!empty($dist['filter_stock'])) {
            $params['stock_status'] = $dist['filter_stock'];
        }
        $endpoint .= '?' . http_build_query($params);
        $response = wp_remote_get($endpoint, array_merge([
            'headers' => ['Authorization' => 'Basic ' . base64_encode($site['username'] . ':' . $site['app_password'])],
            'timeout' => 30,
        ], $this->get_proxy_args()));
        if (is_wp_error($response)) return [];
        $body = json_decode(wp_remote_retrieve_body($response), true);
        if (!is_array($body)) return [];
        
        // دریافت تاریخچه توزیع برای جلوگیری از ارسال تکراری
        $distributed = $this->get_distribution_history($user_id, $dist['id']);
        $distributed_ids = array_column($distributed, 'item_id');
        
        // محاسبه زمان تأخیر برای حالت محتوای جدید
        $delay_value = isset($dist['new_delay_value']) ? intval($dist['new_delay_value']) : 5;
        $delay_unit = isset($dist['new_delay_unit']) ? $dist['new_delay_unit'] : 'minutes';
        $delay_seconds = 0;
        if ($delay_unit === 'minutes') {
            $delay_seconds = $delay_value * 60;
        } elseif ($delay_unit === 'hours') {
            $delay_seconds = $delay_value * 3600;
        } elseif ($delay_unit === 'days') {
            $delay_seconds = $delay_value * 86400;
        }
        $cutoff_time = current_time('timestamp') - $delay_seconds;
        
        $items = [];
        $all_distributed = true;
        
        foreach ($body as $item) {
            $item_id = $item['id'] ?? 0;
            
            // جلوگیری از ارسال تکراری (در حالت preview رد میشه)
            if (!$skip_history && in_array($item_id, $distributed_ids)) {
                continue;
            }
            
            // تأخیر زمانی فقط در حالت only_new و غیر از preview اعمال میشه
            if ($only_new && !$skip_history) {
                $item_date = isset($item['date']) ? strtotime($item['date']) : 0;
                if ($item_date > $cutoff_time) {
                    continue;
                }
            }
            
            $all_distributed = false;
            
            $items[] = [
                'id' => $item_id,
                'title' => $item['name'] ?? $item['title']['rendered'] ?? '',
                'excerpt' => $item['short_description'] ?? $item['excerpt']['rendered'] ?? '',
                'url' => $site_base_url . $item_id,
                'image' => $item['images'][0]['src'] ?? ($item['featured_media_url'] ?? ''),
                'price' => $item['price'] ?? '',
            ];
        }
        
        // یک دور کامل از همه آیتم‌ها گذشت، تاریخچه را پاک می‌کنیم و از اول شروع می‌کنیم
        if (!$skip_history && $all_distributed && !empty($distributed_ids)) {
            $this->clear_distribution_history($user_id, $dist['id']);
            $items = [];
            foreach ($body as $item) {
                $item_id = $item['id'] ?? 0;
                $item_date = isset($item['date']) ? strtotime($item['date']) : 0;
                if ($only_new && $item_date > $cutoff_time) continue;
                $items[] = [
                    'id' => $item_id,
                    'title' => $item['name'] ?? $item['title']['rendered'] ?? '',
                    'excerpt' => $item['short_description'] ?? $item['excerpt']['rendered'] ?? '',
                    'url' => $site_base_url . $item_id,
                    'image' => $item['images'][0]['src'] ?? ($item['featured_media_url'] ?? ''),
                    'price' => $item['price'] ?? '',
                ];
            }
        }
        
        if ($dist['sort_order'] === 'random') shuffle($items);
        elseif ($dist['sort_order'] === 'oldest') $items = array_reverse($items);
        return $items;
    }

    private function format_distribution_message($template, $item) {
        $replacements = [
            '{title}' => $item['title'] ?? '',
            '{excerpt}' => strip_tags($item['excerpt'] ?? ''),
            '{url}' => $item['url'] ?? '',
            '{image}' => $item['image'] ?? '',
            '{price}' => $item['price'] ?? '',
        ];
        return str_replace(array_keys($replacements), array_values($replacements), $template);
    }

    private function get_distribution_history($user_id, $dist_id) {
        $history = is_array($__tmp = get_user_meta($user_id, 'ssp_dist_history_' . $dist_id, true)) ? $__tmp : [];
        $cutoff = strtotime('-90 days');
        return array_filter($history, function($h) use ($cutoff) {
            return strtotime($h['date'] ?? '') > $cutoff;
        });
    }

    private function clear_distribution_history($user_id, $dist_id) {
        delete_user_meta($user_id, 'ssp_dist_history_' . $dist_id);
    }

    private function mark_item_distributed($user_id, $dist, $item) {
        $key = 'ssp_dist_history_' . $dist['id'];
        $history = is_array($__tmp = get_user_meta($user_id, $key, true)) ? $__tmp : [];
        $history[] = ['item_id' => $item['id'] ?? 0, 'date' => current_time('mysql')];
        if (count($history) > 2000) $history = array_slice($history, -2000);
        update_user_meta($user_id, $key, $history);
    }

    private function get_distribution_sent_count($user_id, $dist_id) {
        $history = is_array($__tmp = get_user_meta($user_id, 'ssp_dist_history_' . $dist_id, true)) ? $__tmp : [];
        $today = current_time('Y-m-d');
        return count(array_filter($history, function($h) use ($today) {
            return substr($h['date'] ?? '', 0, 10) === $today;
        }));
    }

    private function update_distribution_last_run($user_id, $dist, $time_str = '') {
        $profile_id = $this->get_active_profile_id($user_id);
        $distributions = $this->get_profile_items($user_id, 'distributions', $profile_id);
        foreach ($distributions as &$d) {
            if ((int)$d['id'] === $dist['id']) {
                $d['last_run'] = current_time('mysql');
                $d['last_run_time'] = $time_str;
                break;
            }
        }
        unset($d);
        $this->set_profile_items($user_id, 'distributions', $distributions, $profile_id);
    }

    private function get_users_with_active_distributions() {
        global $wpdb;
        $results = $wpdb->get_col($wpdb->prepare(
            "SELECT DISTINCT user_id FROM {$wpdb->usermeta} WHERE meta_key = %s AND meta_value NOT IN ('', 'a:0:{}', 'b:0;')",
            'ssp_distributions'
        ));
        return array_map('intval', $results);
    }

    private function set_profile_items($user_id, $key, $items, $profile_id = null) {
        $all_items = $this->get_user_items($user_id, $key);
        if ($profile_id === null) $profile_id = $this->get_active_profile_id($user_id);
        $all_items = array_filter($all_items, function($item) use ($profile_id) {
            return (int)($item['profile_id'] ?? 1) !== $profile_id;
        });
        foreach ($items as $item) {
            $item['profile_id'] = $profile_id;
            $all_items[] = $item;
        }
        $this->set_user_items($user_id, $key, array_values($all_items));
    }
}
