<?php

trait SSP_AjaxRss {

    public function handle_add_rss_feed() {
        $user_id = $this->ajax_require_auth();
        $profile_id = $this->get_active_profile_id($user_id);
        $feeds = $this->get_profile_rss_feeds($user_id, $profile_id);
        if (count($feeds) >= 10) wp_send_json_error(['message' => 'حداکثر ۱۰ RSS Feed می‌توانید اضافه کنید']);
        $all_feeds = $this->get_user_items($user_id, 'rss_feeds');
        $id = $this->next_id($all_feeds);
        $all_feeds[] = [
            'id' => $id, 'profile_id' => $profile_id,
            'feed_name' => sanitize_text_field($_POST['feed_name'] ?? 'فید جدید'),
            'feed_url' => esc_url_raw($_POST['feed_url'] ?? ''),
            'is_active' => intval($_POST['is_active'] ?? 1),
            'auto_fetch' => intval($_POST['auto_fetch'] ?? 1),
            'extract_content' => intval($_POST['extract_content'] ?? 0),
            'clean_ads' => intval($_POST['clean_ads'] ?? 1),
            'clean_urls' => intval($_POST['clean_urls'] ?? 1),
            'max_length' => intval($_POST['max_length'] ?? 500),
            'content_mode' => sanitize_text_field($_POST['content_mode'] ?? 'summary'),
            'message_template' => sanitize_textarea_field($_POST['message_template'] ?? ''),
            'target_mode' => sanitize_text_field($_POST['target_mode'] ?? 'messengers'),
            'target_messengers' => sanitize_text_field($_POST['target_messengers'] ?? ''),
            'target_wp_site' => intval($_POST['target_wp_site'] ?? 0),
            'created_at' => current_time('mysql'),
        ];
        $this->set_user_items($user_id, 'rss_feeds', $all_feeds);
        wp_send_json_success(['message' => 'RSS Feed اضافه شد!', 'id' => $id]);
    }

    public function handle_delete_rss_feed() {
        $user_id = $this->ajax_require_auth();
        $feeds = $this->get_user_rss_feeds($user_id);
        $id = intval($_POST['feed_id'] ?? $_POST['id'] ?? 0);
        $feeds = array_values(array_filter($feeds, function($f) use ($id) { return (int)$f['id'] !== $id; }));
        $this->set_user_items($user_id, 'rss_feeds', $feeds);
        wp_send_json_success(['message' => 'RSS Feed حذف شد']);
    }

    public function handle_update_rss_feed() {
        $user_id = $this->ajax_require_auth();
        $feeds = $this->get_user_rss_feeds($user_id);
        $id = intval($_POST['feed_id'] ?? $_POST['id'] ?? 0);
        foreach ($feeds as &$f) {
            if ((int)$f['id'] === $id) {
                $f['feed_name'] = sanitize_text_field($_POST['feed_name'] ?? '');
                $f['feed_url'] = esc_url_raw($_POST['feed_url'] ?? '');
                $f['is_active'] = intval($_POST['is_active'] ?? 1);
                $f['auto_fetch'] = intval($_POST['auto_fetch'] ?? 1);
                $f['extract_content'] = intval($_POST['extract_content'] ?? 0);
                $f['clean_ads'] = intval($_POST['clean_ads'] ?? 1);
                $f['clean_urls'] = intval($_POST['clean_urls'] ?? 1);
                $f['max_length'] = intval($_POST['max_length'] ?? 500);
                $f['content_mode'] = sanitize_text_field($_POST['content_mode'] ?? 'summary');
                if (isset($_POST['message_template'])) $f['message_template'] = sanitize_textarea_field($_POST['message_template']);
                $f['target_mode'] = sanitize_text_field($_POST['target_mode'] ?? 'messengers');
                $f['target_messengers'] = sanitize_text_field($_POST['target_messengers'] ?? '');
                $f['target_wp_site'] = intval($_POST['target_wp_site'] ?? 0);
                break;
            }
        }
        unset($f);
        $this->set_user_items($user_id, 'rss_feeds', $feeds);
        wp_send_json_success(['message' => 'تغییرات ذخیره شد']);
    }

    public function process_rss_feeds() {
        $users = get_users(['meta_key' => 'ssp_rss_feeds', 'meta_compare' => 'EXISTS', 'fields' => 'ID']);
        foreach ($users as $user_id) {
            $feeds = $this->get_user_rss_feeds($user_id);
            if (empty($feeds)) continue;

            foreach ($feeds as &$feed) {
                if (empty($feed['is_active']) || empty($feed['auto_fetch']) || empty($feed['feed_url'])) continue;
                $last_fetched = $feed['last_fetched'] ?? '';
                if ($last_fetched && (time() - strtotime($last_fetched)) < 600) continue;

                $last_items = $feed['last_items'] ?? [];
                $max_new = 5;
                $fetched_count = 0;
                $response = wp_remote_get($feed['feed_url'], ['timeout' => 10]);
                if (is_wp_error($response)) continue;
                $xml = @simplexml_load_string(wp_remote_retrieve_body($response));
                if (!$xml || !isset($xml->channel)) continue;

                $should_extract = !empty($feed['extract_content']);

                foreach ($xml->channel->item as $item) {
                    if ($fetched_count >= $max_new) break;
                    $guid = (string)($item->guid ?? $item->link ?? '');
                    if (empty($guid)) $guid = md5((string)$item->link);
                    if (in_array($guid, $last_items)) continue;

                    $title = (string)($item->title ?? '');
                    $raw_content = (string)($item->description ?? $item->content ?? '');
                    $url = (string)($item->link ?? '');

                    $extracted = '';
                    if ($should_extract && !empty($url)) {
                        $extracted = $this->extract_article_content($url);
                    }

                    $source_content = !empty($extracted) ? $extracted : $raw_content;
                    $clean_content = $this->clean_rss_content($source_content, $feed);
                    $message = $this->build_rss_message($title, $clean_content, $url, $feed);

                    $this->add_to_queue($user_id, 'rss_post', [
                        'title' => $title,
                        'message' => $message,
                        'url' => $url,
                        'selected_messengers' => $this->parse_rss_targets($feed, $user_id),
                    ], 5);

                    $last_items[] = $guid;
                    $fetched_count++;
                }
                $feed['last_items'] = array_slice($last_items, -100);
                $feed['last_fetched'] = current_time('mysql');
                $feed['fetched_count'] = ($feed['fetched_count'] ?? 0) + $fetched_count;
            }
            unset($feed);
            $this->set_user_items($user_id, 'rss_feeds', $feeds);
        }
    }

    public function handle_fetch_rss_now() {
        $user_id = $this->ajax_require_auth();
        $feed_id = intval($_POST['feed_id'] ?? 0);
        $extract_now = intval($_POST['extract_now'] ?? 0);
        $feeds = $this->get_user_rss_feeds($user_id);
        $found = false;
        foreach ($feeds as &$feed) {
            if ((int)$feed['id'] === $feed_id) {
                $found = true;
                $response = wp_remote_get($feed['feed_url'], ['timeout' => 15]);
                if (is_wp_error($response)) wp_send_json_error(['message' => $response->get_error_message()]);
                $xml = @simplexml_load_string(wp_remote_retrieve_body($response));
                if (!$xml || !isset($xml->channel)) wp_send_json_error(['message' => 'فید نامعتبر است']);

                $last_items = $feed['last_items'] ?? [];
                $items = [];
                $extract_count = 0;
                foreach ($xml->channel->item as $item) {
                    $guid = (string)($item->guid ?? $item->link ?? '');
                    if (empty($guid)) $guid = md5((string)$item->link);

                    $title = (string)($item->title ?? '');
                    $raw_content = (string)($item->description ?? $item->content ?? '');
                    $clean_content = $this->clean_rss_content($raw_content, $feed);
                    $url = (string)($item->link ?? '');
                    $message = $this->build_rss_message($title, $clean_content, $url, $feed);
                    $pub_date = (string)($item->pubDate ?? '');
                    $is_new = !in_array($guid, $last_items);

                    $extracted = '';
                    if ($extract_now && !empty($url) && $extract_count < 3) {
                        $extracted = $this->extract_article_content($url);
                        if (!empty($extracted)) $extract_count++;
                    }

                    $items[] = [
                        'id' => count($items) + 1,
                        'guid' => $guid,
                        'title' => $title,
                        'content' => $message,
                        'snippet' => $clean_content,
                        'extracted' => $extracted,
                        'url' => $url,
                        'pub_date' => $pub_date,
                        'is_new' => $is_new,
                        'selected' => $is_new,
                    ];
                }
                break;
            }
        }
        unset($feed);
        if (!$found) wp_send_json_error(['message' => 'فید یافت نشد']);
        wp_send_json_success(['items' => $items, 'count' => count($items), 'feed_name' => $feed['feed_name'] ?? '']);
    }

    public function handle_rss_content_batch() {
        $user_id = $this->ajax_require_auth();
        $urls = json_decode(stripslashes($_POST['urls'] ?? '[]'), true);
        if (empty($urls) || !is_array($urls)) wp_send_json_error(['message' => 'آدرسی ارسال نشده']);

        $max = min(count($urls), 5);
        $results = [];
        for ($i = 0; $i < $max; $i++) {
            $content = $this->extract_article_content(esc_url_raw($urls[$i]));
            if (!empty($content)) {
                $results[] = ['url' => $urls[$i], 'content' => $content];
            }
            if ($i < $max - 1) usleep(300000);
        }

        wp_send_json_success(['extracted' => $results, 'count' => count($results)]);
    }

    public function handle_rss_ai_process() {
        $user_id = $this->ajax_require_auth();
        if ($this->get_user_plan($user_id) !== 'pro') {
            wp_send_json_error(['message' => 'AI فقط در پلن Pro']);
        }

        $items_json = stripslashes($_POST['items'] ?? '');
        $items = json_decode($items_json, true);
        $mode = sanitize_text_field($_POST['ai_mode'] ?? 'summarize');
        $ai_mode = get_user_meta($user_id, 'ssp_ai_mode', true) ?: 'api';
        
        $text = '';
        if (is_array($items)) {
            foreach ($items as $item) {
                $text .= "عنوان: " . ($item['title'] ?? '') . "\n";
                $text .= "محتوا: " . ($item['content'] ?? '') . "\n\n";
            }
        } else {
            $text = sanitize_textarea_field($_POST['text'] ?? '');
        }

        if (empty(trim($text))) wp_send_json_error(['message' => 'متنی برای پردازش وجود ندارد']);

        $prompt = $this->build_rss_ai_prompt($text, $mode);

        if ($ai_mode === 'browser') {
            $bridge_token = $this->get_or_create_bridge_token($user_id);
            $context_type = 'rss';
            $task = $this->create_bridge_task($user_id, $prompt, ['type' => $context_type]);
            wp_send_json_success([
                'mode' => 'browser',
                'task_id' => $task['task_id'],
                'prompt' => $prompt,
                'message' => 'لطفاً در چت‌بات منتظر پاسخ بمانید...',
            ]);
        } else {
            $ai_provider = get_user_meta($user_id, 'ssp_ai_provider', true) ?: 'openai';
            $ai_api_key = get_user_meta($user_id, 'ssp_ai_api_key', true);
            $ai_model = get_user_meta($user_id, 'ssp_ai_model', true) ?: 'gpt-4o-mini';

            if (empty($ai_api_key)) {
                wp_send_json_error(['message' => 'کلید API تنظیم نشده. لطفاً ابتدا در تنظیمات AI کلید را وارد کنید یا از حالت مرورگر استفاده کنید.']);
            }

            try {
                $result = $this->call_ai_api($ai_provider, $ai_api_key, $ai_model, $prompt, false);
                $content = $result['content'] ?? '';
                wp_send_json_success(['mode' => 'api', 'result' => $content]);
            } catch (Exception $e) {
                wp_send_json_error(['message' => 'خطای AI: ' . $e->getMessage()]);
            }
        }
    }

    private function build_rss_ai_prompt($text, $mode) {
        switch ($mode) {
            case 'summarize':
                return "متن زیر را به صورت فوق‌العاده جذاب، فشرده و کاربردی در ۳ الی ۴ خط خلاصه کن.\n" .
                    "الزامات: فقط و فقط متن خلاصه شده نهایی را بدون هیچ کلمه، مقدمه، سلام یا پانویس برگردان:\n\n---\n$text";
            case 'translate':
            case 'translate_to_fa':
                return "متن زیر را با دقت بالا و به فارسی روان، شیوا و کاملاً طبیعی ترجمه کن.\n" .
                    "الزامات: ساختار پاراگراف‌ها را حفظ کن و فقط ترجمه نهایی را بدون هیچ کلمه اضافی برگردان:\n\n---\n$text";
            case 'rewrite':
                return "متن زیر را برای انتشار حرفه‌ای در شبکه‌های اجتماعی (تلگرام، بله، ایتا) بازنویسی کن.\n" .
                    "الزامات: لحن پرکشش و صمیمی، رعایت دقیق فاصله‌گذاری خطوط و پاراگراف‌ها، استفاده بجا از ایموجی‌ها، و دعوت به اقدام (CTA). فقط متن بازنویسی‌شده را بدون هیچ توضیح اضافی برگردان:\n\n---\n$text";
            case 'hashtags':
                return "برای متن زیر، ۵ تا ۸ هشتگ به شدت پرمخاطب، هدفمند و مرتبط به زبان فارسی استخراج کن.\n" .
                    "الزامات: هشتگ‌ها در یک خط با علامت # و فاصله از هم جدا شوند (مثال: #هشتگ۱ #هشتگ۲). فقط هشتگ‌ها را برگردان بدون هیچ متن اضافی:\n\n---\n$text";
            case 'extract_keywords':
                return "از متن زیر، مهمترین و پرجستجوترین کلمات کلیدی و عبارات کلیدی را استخراج کن.\n" .
                    "الزامات: به صورت فهرست منظم خط‌به‌خط برگردان بدون هیچ متن یا توضیح اضافی:\n\n---\n$text";
            default:
                return "متن زیر را با دقت پردازش کن و فقط نتیجه نهایی را بدون هیچ مقدمه یا پانویسی برگردان:\n\n---\n$text";
        }
    }

    public function handle_rss_send_selected() {
        $user_id = $this->ajax_require_auth();
        $items = json_decode(stripslashes($_POST['items'] ?? '[]'), true);
        $feed_id = intval($_POST['feed_id'] ?? 0);

        if (empty($items) || !is_array($items)) wp_send_json_error(['message' => 'آیتمی انتخاب نشده']);

        $feeds = $this->get_user_rss_feeds($user_id);
        $feed = null;
        foreach ($feeds as $f) {
            if ((int)$f['id'] === $feed_id) { $feed = $f; break; }
        }

        $sent = 0;
        foreach ($items as $item) {
            $title = sanitize_text_field($item['title'] ?? '');
            $content = sanitize_textarea_field($item['content'] ?? '');
            $url = esc_url_raw($item['url'] ?? '');
            $guid = sanitize_text_field($item['guid'] ?? '');

            $this->add_to_queue($user_id, 'rss_post', [
                'title' => $title,
                'message' => $content,
                'url' => $url,
                'selected_messengers' => $this->parse_rss_targets($feed, $user_id),
            ], 5);

            if ($feed && $guid) {
                if (!isset($feed['last_items'])) $feed['last_items'] = [];
                $feed['last_items'][] = $guid;
                $feed['last_items'] = array_slice($feed['last_items'], -100);
                $feed['fetched_count'] = ($feed['fetched_count'] ?? 0) + 1;
            }
            $sent++;
        }

        if ($feed) {
            $feed['last_fetched'] = current_time('mysql');
            $this->set_user_items($user_id, 'rss_feeds', $feeds);
        }

        wp_send_json_success(['message' => "$sent آیتم به صف ارسال اضافه شد", 'sent' => $sent]);
    }

    public function handle_rss_save_drafts() {
        $user_id = $this->ajax_require_auth();
        $items = json_decode(stripslashes($_POST['items'] ?? '[]'), true);

        if (empty($items) || !is_array($items)) wp_send_json_error(['message' => 'آیتمی انتخاب نشده']);

        $drafts = $this->get_user_drafts($user_id);
        $saved = 0;

        foreach ($items as $item) {
            $id = $this->next_id($drafts);
            $drafts[] = [
                'id' => $id,
                'title' => sanitize_text_field($item['title'] ?? ''),
                'content' => sanitize_textarea_field($item['content'] ?? ''),
                'hashtags' => '',
                'meta_description' => '',
                'draft_type' => 'rss',
                'status' => 'pending',
                'created_at' => current_time('mysql'),
                'updated_at' => current_time('mysql'),
            ];
            $saved++;
        }

        $this->set_user_drafts($user_id, $drafts);
        wp_send_json_success(['message' => "$saved آیتم به پیش‌نویس اضافه شد", 'saved' => $saved]);
    }

    public function handle_rss_schedule() {
        $user_id = $this->ajax_require_auth();
        if ($this->get_user_plan($user_id) !== 'pro') wp_send_json_error(['message' => 'زمان‌بندی فقط در پلن Pro موجود است']);

        $items = json_decode(stripslashes($_POST['items'] ?? '[]'), true);
        $start_datetime = sanitize_text_field($_POST['start_datetime'] ?? '');
        $interval = max(5, intval($_POST['interval'] ?? 60));

        if (empty($items) || !is_array($items)) wp_send_json_error(['message' => 'آیتمی انتخاب نشده']);
        if (empty($start_datetime)) wp_send_json_error(['message' => 'تاریخ و ساعت شروع الزامی است']);

        $schedules = $this->get_user_items($user_id, 'schedules');
        $base_ts = strtotime($start_datetime);
        $added = 0;

        foreach ($items as $i => $item) {
            $sch_time = date('Y-m-d H:i:s', $base_ts + ($i * $interval * 60));
            $id = $this->next_id($schedules);
            $schedules[] = [
                'id' => $id,
                'title' => sanitize_text_field($item['title'] ?? ''),
                'message' => sanitize_textarea_field($item['content'] ?? ''),
                'hashtags' => '',
                'scheduled_at' => $sch_time,
                'status' => 'pending',
                'recurring' => '',
                'created_at' => current_time('mysql'),
            ];
            $added++;
        }

        $this->set_user_items($user_id, 'schedules', $schedules);
        wp_send_json_success(['message' => "$added آیتم زمان‌بندی شد", 'added' => $added]);
    }

    private function extract_article_content($url) {
        if (empty($url)) return '';

        $cache_key = 'ssp_article_' . md5($url);
        $cached = get_transient($cache_key);
        if ($cached !== false) return $cached;

        $response = wp_remote_get($url, [
            'timeout' => 15,
            'user_agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
        ]);

        if (is_wp_error($response)) return '';

        $body = wp_remote_retrieve_body($response);
        if (empty($body)) return '';

        $content = $this->extract_main_content($body);
        $content = wp_strip_all_tags($content);
        $content = preg_replace('/\s+/', ' ', $content);
        $content = preg_replace('/\n\s*\n/', "\n", $content);
        $content = trim($content);

        if (mb_strlen($content) > 100) {
            set_transient($cache_key, $content, HOUR_IN_SECONDS);
        }

        return $content;
    }

    private function extract_main_content($html) {
        $dom = new DOMDocument();
        libxml_use_internal_errors(true);
        $dom->loadHTML('<?xml encoding="UTF-8">' . $html);
        libxml_clear_errors();

        $xpath = new DOMXPath($dom);

        $selectors = [
            '//article',
            '//main',
            '//div[contains(@class,"post-content")]',
            '//div[contains(@class,"entry-content")]',
            '//div[contains(@class,"article-body")]',
            '//div[contains(@class,"article-content")]',
            '//div[contains(@class,"story-body")]',
            '//div[contains(@class,"content-body")]',
            '//div[@itemprop="articleBody"]',
            '//div[@role="main"]',
        ];

        foreach ($selectors as $selector) {
            $nodes = @$xpath->query($selector);
            if ($nodes && $nodes->length > 0) {
                $text = $nodes->item(0)->textContent;
                if (mb_strlen(trim($text)) > 200) {
                    return trim($text);
                }
            }
        }

        $body = @$xpath->query('//body');
        if ($body && $body->length > 0) {
            $text = $body->item(0)->textContent;
            return trim($text);
        }

        return '';
    }

    private function clean_rss_content($raw_content, $feed) {
        $content = $raw_content;

        $content = wp_strip_all_tags($content);

        if (!empty($feed['clean_urls'])) {
            $content = preg_replace('/https?:\/\/[^\s]+/', '', $content);
            $content = preg_replace('/www\.[^\s]+/', '', $content);
        }

        if (!empty($feed['clean_ads'])) {
            $ad_patterns = [
                '/تبلیغات/i',
                '/advertisment/i',
                '/sponsored/i',
                '/Advertisement/i',
                '/إعلانات/i',
                '/ادامه مطلب/i',
                '/منبع:/i',
                '/source:/i',
                '/کلیک کنید/i',
                '/click here/i',
                '/read more/i',
                '/ادامه در سایت/i',
                '/بیشتر بخوانید/i',
            ];
            $content = preg_replace($ad_patterns, '', $content);
        }

        $content = preg_replace('/\s+/', ' ', trim($content));
        $content = preg_replace('/\n\s*\n/', "\n", $content);

        $max_length = $feed['max_length'] ?? 500;
        if (mb_strlen($content) > $max_length) {
            $content = mb_substr($content, 0, $max_length) . '...';
        }

        return trim($content);
    }

    private function build_rss_message($title, $content, $url, $feed) {
        if (!empty($feed['message_template'])) {
            $excerpt = wp_trim_words($content, 30, '...');
            $tpl = $feed['message_template'];
            $tpl = str_replace('{title}', $title, $tpl);
            $tpl = str_replace('{content}', $content, $tpl);
            $tpl = str_replace('{url}', $url, $tpl);
            $tpl = str_replace('{excerpt}', $excerpt, $tpl);
            return trim($tpl);
        }
        
        $mode = $feed['content_mode'] ?? 'summary';

        switch ($mode) {
            case 'title_only':
                return $title;

            case 'title_link':
                return $title . "\n\n" . $url;

            case 'full':
                return $content;

            case 'summary':
            default:
                $message = '';
                if (!empty($title)) $message .= $title . "\n\n";
                if (!empty($content)) $message .= $content;
                if (!empty($url)) $message .= "\n\n🔗 " . $url;
                return trim($message);
        }
    }

    private function parse_rss_targets($feed, $user_id) {
        $target_mode = $feed['target_mode'] ?? 'all';

        if ($target_mode === 'messengers') {
            $target_ids = array_map('intval', explode(',', $feed['target_messengers'] ?? ''));
            return array_filter($target_ids);
        }

        return [];
    }
}
