<?php
/**
 * SSP AJAX Product Trait - Product generator, spin, batch, short URL, proxy, DoH settings AJAX
 */
trait SSP_AjaxProduct {

    private function fix_ai_newlines($text) {
        if (empty($text)) return $text;
        $text = str_replace(["\\n\\n", "\\n"], ["\n\n", "\n"], $text);
        $text = str_replace("nn", "\n\n", $text);
        $text = preg_replace('/([\x{1F300}-\x{1F9FF}\x{2600}-\x{26FF}\x{2700}-\x{27BF}\x{FE00}-\x{FE0F}\x{1F000}-\x{1FFFF}\x{200D}\x{20E3}\x{E0020}-\x{E007F}])n/', "$1\n", $text);
        $text = preg_replace('/([.!?:;،؟!؟…\-\*])n/', "$1\n", $text);
        $text = preg_replace('/\]n/', "]\n", $text);
        $text = preg_replace('/\nn/', "\n\n", $text);
        $text = preg_replace('/\n{3,}/', "\n\n", $text);
        return $text;
    }

    public function handle_generate_product_ai() {
        @set_time_limit(300);
        @ignore_user_abort(true);
        @ini_set('memory_limit', '256M');
        check_ajax_referer('ssp_secure_nonce', 'security');
        $user_id = $this->ajax_require_auth();
        if ($this->get_user_plan($user_id) !== 'pro') wp_send_json_error(['message' => 'تولید محصول با AI فقط در پلن Pro موجود است']);
        $product_name = sanitize_text_field($_POST['product_name'] ?? '');
        $product_brief = sanitize_textarea_field($_POST['product_brief'] ?? '');
        $content_type = sanitize_text_field($_POST['content_type'] ?? 'product');
        $prompt_mode = sanitize_text_field($_POST['prompt_mode'] ?? 'default');
        $custom_prompt = sanitize_textarea_field($_POST['custom_prompt'] ?? '');
        $content_length = intval($_POST['content_length'] ?? 2000);
        if (empty($product_name)) wp_send_json_error(['message' => 'نام محصول الزامی است']);
        $ai_api_key = get_user_meta($user_id, 'ssp_ai_api_key', true);
        if (empty($ai_api_key)) wp_send_json_error(['message' => 'API Key هوش مصنوعی تنظیم نشده']);
        $prompt = $this->get_product_ai_prompt($content_type, $product_name, $product_brief, $prompt_mode, $custom_prompt);
        if ($content_type === 'post') {
            $length_ranges = [
                400 => 'بین ۳۰۰ تا ۵۰۰ کاراکتر، بسیار کوتاه و گیرا',
                700 => 'بین ۶۰۰ تا ۸۰۰ کاراکتر، کوتاه و مؤثر',
                1000 => 'بین ۹۰۰ تا ۱۱۰۰ کاراکتر، متوسط کوتاه',
                2000 => 'بین ۱۸۰۰ تا ۲۲۰۰ کاراکتر، متعادل',
                3000 => 'بین ۲۷۰۰ تا ۳۳۰۰ کاراکتر، بلند و جامع',
                4000 => 'بین ۳۶۰۰ تا ۴۰۰۰ کاراکتر، بسیار کامل',
            ];
            $desc = $length_ranges[$content_length] ?? 'حدود ' . $content_length . ' کاراکتر';
            $prompt .= "\n\nمهم: حجم محتوا باید {$desc} باشد. متن را کوتاه و مفید بنویس.";
        }
        try {
            $result = $this->call_ai_api(get_user_meta($user_id, 'ssp_ai_provider', true) ?: 'openai', $ai_api_key, get_user_meta($user_id, 'ssp_ai_model', true) ?: 'gpt-4o-mini', $prompt, true);
            $decoded = $this->parse_ai_json($result['content']);
            if ($decoded) {
                foreach (['message', 'content'] as $key) {
                    if (!empty($decoded[$key])) {
                        $decoded[$key] = $this->fix_ai_newlines($decoded[$key]);
                        $decoded[$key] = $this->clean_messenger_text($decoded[$key]);
                        // Decode HTML entities that AI might have encoded
                        $decoded[$key] = html_entity_decode($decoded[$key], ENT_QUOTES | ENT_HTML5, 'UTF-8');
                    }
                }
                // Process product fields separately - preserve HTML tags
                foreach (['short_description', 'description'] as $key) {
                    if (!empty($decoded[$key])) {
                        $decoded[$key] = $this->fix_ai_newlines($decoded[$key]);
                        // Only decode HTML entities, do NOT strip tags for product content
                        $decoded[$key] = html_entity_decode($decoded[$key], ENT_QUOTES | ENT_HTML5, 'UTF-8');
                    }
                }
                if (!empty($decoded['title'])) {
                    $decoded['title'] = $this->clean_messenger_text($decoded['title']);
                    $decoded['title'] = html_entity_decode($decoded['title'], ENT_QUOTES | ENT_HTML5, 'UTF-8');
                }
                if (!empty($decoded['name'])) {
                    $decoded['name'] = html_entity_decode($decoded['name'], ENT_QUOTES | ENT_HTML5, 'UTF-8');
                }
                wp_send_json_success([
                    'data' => $decoded,
                    'tokens_used' => $result['tokens_used'] ?? 0,
                    'cost' => $result['cost'] ?? null,
                    'provider' => get_user_meta($user_id, 'ssp_ai_provider', true) ?: 'openai',
                ]);
            }
            else wp_send_json_error(['message' => 'پاسخ AI قابل تفسیر نیست: ' . mb_substr($result['content'], 0, 200)]);
        } catch (Exception $e) { wp_send_json_error(['message' => $e->getMessage()]); }
    }

    public function handle_brainstorm_ideas() {
        @set_time_limit(300);
        @ignore_user_abort(true);
        @ini_set('memory_limit', '256M');
        check_ajax_referer('ssp_secure_nonce', 'security');
        $user_id = $this->ajax_require_auth();
        if ($this->get_user_plan($user_id) !== 'pro') wp_send_json_error(['message' => 'ایده‌یابی فقط در پلن Pro موجود است']);

        $prompt = sanitize_textarea_field($_POST['prompt'] ?? '');
        if (empty($prompt)) wp_send_json_error(['message' => 'موضوع را وارد کنید']);

        $ai_api_key = get_user_meta($user_id, 'ssp_ai_api_key', true);
        if (empty($ai_api_key)) wp_send_json_error(['message' => 'API Key هوش مصنوعی تنظیم نشده']);

        try {
            $result = $this->call_ai_api(
                get_user_meta($user_id, 'ssp_ai_provider', true) ?: 'openai',
                $ai_api_key,
                get_user_meta($user_id, 'ssp_ai_model', true) ?: 'gpt-4o-mini',
                $prompt, true
            );
            $decoded = $this->parse_ai_json($result['content']);
            if ($decoded && is_array($decoded)) {
                wp_send_json_success(['ideas' => $decoded, 'tokens_used' => $result['tokens_used'] ?? 0]);
            } else {
                wp_send_json_error(['message' => 'پاسخ AI قابل تفسیر نیست']);
            }
        } catch (Exception $e) {
            wp_send_json_error(['message' => $e->getMessage()]);
        }
    }

    public function handle_generate_product() {
        check_ajax_referer('ssp_secure_nonce', 'security');
        $user_id = $this->ajax_require_auth();
        if ($this->get_user_plan($user_id) !== 'pro') wp_send_json_error(['message' => 'تولید محصول فقط در پلن Pro موجود است']);
        $site_id = intval($_POST['site_id'] ?? 0);
        $content_type = sanitize_text_field($_POST['content_type'] ?? 'product');
        $sites = $this->get_user_wp_sites($user_id);
        $target_site = null;
        foreach ($sites as $site) { if ((int)$site['id'] === $site_id) { $target_site = $site; break; } }
        if (!$target_site) wp_send_json_error(['message' => 'سایت مقصد یافت نشد']);
        if (empty($target_site['username']) || empty($target_site['app_password'])) wp_send_json_error(['message' => 'نام کاربری و رمز Application Password سایت تنظیم نشده']);

        // Sanitize $_POST at boundary before passing to business logic
        $sanitized_post = array_map(function($v) {
            if (is_string($v)) return sanitize_text_field($v);
            return $v;
        }, $_POST);
        $sanitized_post['description'] = sanitize_textarea_field($_POST['description'] ?? '');
        $sanitized_post['short_description'] = sanitize_textarea_field($_POST['short_description'] ?? '');

        $payload = $content_type === 'product' ? $this->build_product_payload($sanitized_post) : $this->build_post_payload($sanitized_post, $content_type);
        $image_ids = $this->process_product_images($target_site, $sanitized_post, $_FILES);
        if ($content_type === 'product') {
            if (!empty($image_ids['thumbnail'])) $payload['images'] = array_map(function($id) { return ['id' => $id]; }, $image_ids['all'] ?? [$image_ids['thumbnail']]);
            $result = $this->publish_woo_product($target_site, $payload);
        } else {
            if (!empty($image_ids['thumbnail'])) $payload['featured_media'] = $image_ids['thumbnail'];
            $result = $this->publish_wp_content($target_site, $payload, $content_type);
        }
        $history_entry = ['type' => $content_type, 'name' => sanitize_text_field($_POST['product_name'] ?? $_POST['post_title'] ?? ''), 'site' => $target_site['site_name'], 'site_url' => $target_site['site_url'], 'result_url' => $result['success'] ? ($result['url'] ?? '') : '', 'status' => $result['success'] ? 'success' : 'error', 'error' => $result['error'] ?? '', 'created_at' => current_time('mysql')];
        $this->add_publish_history($user_id, $history_entry);
        if ($result['success']) wp_send_json_success(['message' => $content_type === 'product' ? 'محصول با موفقیت منتشر شد!' : 'محتوا با موفقیت منتشر شد!', 'url' => $result['url'] ?? '', 'id' => $result['id'] ?? 0]);
        else wp_send_json_error(['message' => $result['error'] ?? 'خطا در انتشار']);
    }

    public function handle_fetch_woo_categories() {
        $user_id = $this->ajax_require_auth();
        $site_id = intval($_POST['site_id'] ?? 0);
        $content_type = sanitize_text_field($_POST['content_type'] ?? 'product');
        $sites = $this->get_user_wp_sites($user_id);
        $target_site = null;
        foreach ($sites as $site) { if ((int)$site['id'] === $site_id) { $target_site = $site; break; } }
        if (!$target_site) wp_send_json_error(['message' => 'سایت یافت نشد']);
        $auth = base64_encode($target_site['username'] . ':' . $target_site['app_password']);
        $endpoint = $content_type === 'product'
            ? rtrim($target_site['site_url'], '/') . '/wp-json/wc/v3/products/categories?per_page=100'
            : rtrim($target_site['site_url'], '/') . '/wp-json/wp/v2/categories?per_page=100';
        $args = array_merge(['timeout' => 15, 'headers' => ['Authorization' => 'Basic ' . $auth]], $this->get_proxy_args());
        $response = wp_remote_get($endpoint, $args);
        if (is_wp_error($response)) wp_send_json_error(['message' => $response->get_error_message()]);
        $code = wp_remote_retrieve_response_code($response);
        $body = json_decode(wp_remote_retrieve_body($response), true);
        if ($code === 200 && is_array($body)) { wp_send_json_success(['categories' => array_map(function($c) { return ['id' => $c['id'], 'name' => $c['name']]; }, $body)]); }
        wp_send_json_error(['message' => 'خطای ' . $code]);
    }

    public function handle_upload_product_image() {
        $user_id = $this->ajax_require_auth();
        if ($this->get_user_plan($user_id) !== 'pro') wp_send_json_error(['message' => 'آپلود تصویر فقط در پلن Pro موجود است']);
        $site_id = intval($_POST['site_id'] ?? 0);
        $resize = sanitize_text_field($_POST['resize'] ?? '');
        $sites = $this->get_user_wp_sites($user_id);
        $target_site = null;
        foreach ($sites as $site) { if ((int)$site['id'] === $site_id) { $target_site = $site; break; } }
        if (!$target_site) wp_send_json_error(['message' => 'سایت مقصد یافت نشد']);
        if (!empty($_FILES['image_file']) && $_FILES['image_file']['error'] === UPLOAD_ERR_OK) {
            $media_id = $this->upload_file_to_remote($target_site, $_FILES['image_file'], $resize);
            if ($media_id) wp_send_json_success(['message' => 'تصویر آپلود شد!', 'media_id' => $media_id]);
            else wp_send_json_error(['message' => 'خطا در آپلود تصویر']);
        }
        if (!empty($_POST['image_url'])) {
            $media_id = $this->upload_image_to_remote($target_site, esc_url_raw($_POST['image_url']), $resize);
            if ($media_id) wp_send_json_success(['message' => 'تصویر آپلود شد!', 'media_id' => $media_id]);
            else wp_send_json_error(['message' => 'خطا در آپلود تصویر از URL']);
        }
        wp_send_json_error(['message' => 'فایل تصویر ارسال نشد']);
    }

    public function handle_upload_post_image() {
        check_ajax_referer('ssp_secure_nonce', 'security');
        $user_id = $this->ajax_require_auth();
        if ($this->get_user_plan($user_id) !== 'pro') wp_send_json_error(['message' => 'آپلود تصویر فقط در پلن Pro موجود است']);

        if (empty($_FILES['image_file']) || $_FILES['image_file']['error'] !== UPLOAD_ERR_OK) {
            wp_send_json_error(['message' => 'فایل تصویر ارسال نشد']);
        }

        $file = $_FILES['image_file'];
        $allowed = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime = finfo_file($finfo, $file['tmp_name']);
        finfo_close($finfo);

        if (!in_array($mime, $allowed)) wp_send_json_error(['message' => 'فرمت تصویر مجاز نیست']);
        if ($file['size'] > 5 * 1024 * 1024) wp_send_json_error(['message' => 'حجم تصویر بیش از 5 مگابایت است']);

        require_once(ABSPATH . 'wp-admin/includes/image.php');
        require_once(ABSPATH . 'wp-admin/includes/file.php');
        require_once(ABSPATH . 'wp-admin/includes/media.php');

        $attach_id = media_handle_upload('image_file', 0);
        if (is_wp_error($attach_id)) wp_send_json_error(['message' => $attach_id->get_error_message()]);

        $url = wp_get_attachment_url($attach_id);
        wp_send_json_success(['url' => $url, 'attach_id' => $attach_id]);
    }

    public function handle_save_product_prompt() {
        $user_id = $this->ajax_require_auth();
        if ($this->get_user_plan($user_id) !== 'pro') wp_send_json_error(['message' => 'ذخیره پرامپت فقط در پلن Pro موجود است']);
        $prompts = [
            'default_product' => sanitize_textarea_field($_POST['prompt_default_product'] ?? ''),
            'default_post' => sanitize_textarea_field($_POST['prompt_default_post'] ?? ''),
            'fashion' => sanitize_textarea_field($_POST['prompt_fashion'] ?? ''),
            'electronics' => sanitize_textarea_field($_POST['prompt_electronics'] ?? ''),
            'food' => sanitize_textarea_field($_POST['prompt_food'] ?? ''),
        ];
        update_user_meta($user_id, 'ssp_product_prompts', $prompts);
        wp_send_json_success(['message' => 'پرامپت‌ها ذخیره شدند']);
    }

    public function handle_save_product_template() {
        $user_id = $this->ajax_require_auth();
        if ($this->get_user_plan($user_id) !== 'pro') wp_send_json_error(['message' => 'ذخیره قالب فقط در پلن Pro موجود است']);
        $templates = $this->get_user_product_templates($user_id);
        if (count($templates) >= 20) wp_send_json_error(['message' => 'حداکثر ۲۰ قالب می‌توانید ذخیره کنید']);
        
        // Check if updating existing template
        $template_id = isset($_POST['template_id']) ? intval($_POST['template_id']) : 0;
        $id = null;
        
        if ($template_id > 0) {
            // Update existing template
            foreach ($templates as &$t) {
                if ((int)$t['id'] === $template_id) {
                    $id = $template_id;
                    $t['name'] = sanitize_text_field($_POST['template_name'] ?? $t['name']);
                    $t['content_type'] = sanitize_text_field($_POST['content_type'] ?? $t['content_type']);
                    $t['data'] = [
                        'product_name' => sanitize_text_field($_POST['product_name'] ?? ''),
                        'short_description' => sanitize_textarea_field($_POST['short_description'] ?? ''),
                        'description' => wp_kses_post($_POST['description'] ?? ''),
                        'regular_price' => sanitize_text_field($_POST['regular_price'] ?? ''),
                        'sale_price' => sanitize_text_field($_POST['sale_price'] ?? ''),
                        'sku' => sanitize_text_field($_POST['sku'] ?? ''),
                        'weight' => sanitize_text_field($_POST['weight'] ?? ''),
                        'stock_status' => sanitize_text_field($_POST['stock_status'] ?? 'instock'),
                        'product_status' => sanitize_text_field($_POST['product_status'] ?? 'draft'),
                        'post_title' => sanitize_text_field($_POST['post_title'] ?? ''),
                        'post_content' => wp_kses_post($_POST['post_content'] ?? ''),
                        'post_tags' => sanitize_text_field($_POST['post_tags'] ?? ''),
                    ];
                    break;
                }
            }
            unset($t);
            if (!$id) wp_send_json_error(['message' => 'قالب یافت نشد']);
        } else {
            // Create new template
            $id = $this->next_id($templates);
            $templates[] = [
                'id' => $id, 'name' => sanitize_text_field($_POST['template_name'] ?? 'قالب جدید'),
                'content_type' => sanitize_text_field($_POST['content_type'] ?? 'product'),
                'data' => [
                    'product_name' => sanitize_text_field($_POST['product_name'] ?? ''),
                    'short_description' => sanitize_textarea_field($_POST['short_description'] ?? ''),
                    'description' => wp_kses_post($_POST['description'] ?? ''),
                    'regular_price' => sanitize_text_field($_POST['regular_price'] ?? ''),
                    'sale_price' => sanitize_text_field($_POST['sale_price'] ?? ''),
                    'sku' => sanitize_text_field($_POST['sku'] ?? ''),
                    'weight' => sanitize_text_field($_POST['weight'] ?? ''),
                    'stock_status' => sanitize_text_field($_POST['stock_status'] ?? 'instock'),
                    'product_status' => sanitize_text_field($_POST['product_status'] ?? 'draft'),
                    'post_title' => sanitize_text_field($_POST['post_title'] ?? ''),
                    'post_content' => wp_kses_post($_POST['post_content'] ?? ''),
                    'post_tags' => sanitize_text_field($_POST['post_tags'] ?? ''),
                ],
                'created_at' => current_time('mysql'),
            ];
        }
        $this->set_user_product_templates($user_id, $templates);
        wp_send_json_success(['message' => 'قالب ذخیره شد!', 'id' => $id]);
    }

    public function handle_get_product_templates() {
        $user_id = $this->ajax_require_auth();
        wp_send_json_success(['templates' => $this->get_user_product_templates($user_id)]);
    }

    public function handle_delete_product_template() {
        $user_id = $this->ajax_require_auth();
        $templates = $this->get_user_product_templates($user_id);
        $id = intval($_POST['template_id']);
        $templates = array_values(array_filter($templates, function($t) use ($id) { return (int)$t['id'] !== $id; }));
        $this->set_user_product_templates($user_id, $templates);
        wp_send_json_success(['message' => 'قالب حذف شد']);
    }

    public function handle_get_publish_history() {
        $user_id = $this->ajax_require_auth();
        wp_send_json_success(['history' => $this->get_user_publish_history($user_id)]);
    }

    public function handle_clear_publish_history() {
        $user_id = $this->ajax_require_auth();
        update_user_meta($user_id, 'ssp_publish_history', []);
        wp_send_json_success(['message' => 'تاریخچه پاک شد']);
    }

    public function handle_bulk_generate_products() {
        check_ajax_referer('ssp_secure_nonce', 'security');
        $user_id = $this->ajax_require_auth();
        if ($this->get_user_plan($user_id) !== 'pro') wp_send_json_error(['message' => 'تولید انبوه فقط در پلن Pro موجود است']);

        $site_id = intval($_POST['site_id'] ?? 0);
        $content_type = sanitize_text_field($_POST['content_type'] ?? 'product');
        $items = json_decode(stripslashes($_POST['items'] ?? '[]'), true);
        if (empty($items) || !is_array($items)) wp_send_json_error(['message' => 'آیتمی برای تولید وجود ندارد']);

        // Sanitize decoded items
        $items = array_map(function($item) {
            return [
                'product_name' => sanitize_text_field($item['product_name'] ?? ''),
                'post_title' => sanitize_text_field($item['post_title'] ?? ''),
                'product_brief' => sanitize_textarea_field($item['product_brief'] ?? ''),
                'content_type' => sanitize_text_field($item['content_type'] ?? 'product'),
            ];
        }, $items);

        $sites = $this->get_user_wp_sites($user_id);
        $target_site = null;
        foreach ($sites as $site) { if ((int)$site['id'] === $site_id) { $target_site = $site; break; } }
        if (!$target_site) wp_send_json_error(['message' => 'سایت مقصد یافت نشد']);

        $ai_api_key = get_user_meta($user_id, 'ssp_ai_api_key', true);
        if (empty($ai_api_key)) wp_send_json_error(['message' => 'API Key تنظیم نشده']);

        $names = array_map(function($item) use ($content_type) {
            return $content_type === 'product' ? ($item['product_name'] ?? '') : ($item['post_title'] ?? '');
        }, $items);
        $names = array_filter($names);
        $names_str = implode('، ', $names);

        if ($content_type === 'product') {
            $prompt = count($items) . " محصول فارسی تولید کن.\n" .
                "نام‌ها: {$names_str}\n\n" .
                "توضیحات هر محصول باید واقعی و تخصصی باشه، نه مصنوعی.\n" .
                "فقط اطلاعات واقعی و تایید شده بنویس.\n" .
                "برای هر محصول: name, short_description (2-3 جمله جذاب), description (HTML حرفه‌ای), regular_price (تومان منطقی), categories, tags.\n" .
                "فقط JSON array: [{\"name\":\"\",\"short_description\":\"\",\"description\":\"\",\"regular_price\":\"\",\"categories\":[\"\"],\"tags\":[\"\"]}]";
        } else {
            $prompt = count($items) . " پست وردپرس فارسی تولید کن.\n" .
                "موضوعات: {$names_str}\n\n" .
                "محتوا باید آموزشی، کاربردی و واقعی باشه.\n" .
                "فقط اطلاعات واقعی و تایید شده بنویس.\n" .
                "برای هر پست: title (≤70 کاراکتر), content (HTML حرفه‌ای), excerpt (2-3 جمله), categories, tags.\n" .
                "فقط JSON array: [{\"title\":\"\",\"content\":\"\",\"excerpt\":\"\",\"categories\":[\"\"],\"tags\":[\"\"]}]";
        }

        try {
            $result = $this->call_ai_api(
                get_user_meta($user_id, 'ssp_ai_provider', true) ?: 'openai',
                $ai_api_key,
                get_user_meta($user_id, 'ssp_ai_model', true) ?: 'gpt-4o-mini',
                $prompt, true
            );
            $generated = $this->parse_ai_json($result['content']);
            if (!$generated || !is_array($generated)) {
                wp_send_json_error(['message' => 'پاسخ AI قابل تفسیر نیست']);
            }
            // Decode HTML entities in generated content for batch products/posts
            foreach ($generated as &$item) {
                foreach (['name', 'title', 'short_description', 'description', 'content', 'excerpt'] as $key) {
                    if (!empty($item[$key])) {
                        $item[$key] = html_entity_decode($item[$key], ENT_QUOTES | ENT_HTML5, 'UTF-8');
                    }
                }
            }
            unset($item);
        } catch (Exception $e) {
            wp_send_json_error(['message' => $e->getMessage()]);
        }

        $success_count = 0;
        $failed_count = 0;
        $results = [];
        foreach ($generated as $idx => $gen) {
            $item_data = $items[$idx] ?? $items[count($items) - 1];
            $merged = array_merge($item_data, $gen);
            try {
                if ($content_type === 'product') {
                    $payload = $this->build_product_payload($merged);
                    $result = $this->publish_woo_product($target_site, $payload);
                } else {
                    $payload = $this->build_post_payload($merged, $content_type);
                    $result = $this->publish_wp_content($target_site, $payload, $content_type);
                }
                if ($result['success']) {
                    $success_count++;
                    $results[] = ['name' => $gen['name'] ?? $gen['title'] ?? 'نامشخص', 'status' => 'success', 'url' => $result['url'] ?? ''];
                    $this->add_publish_history($user_id, ['type' => $content_type, 'name' => $gen['name'] ?? $gen['title'] ?? '', 'site' => $target_site['site_name'], 'site_url' => $target_site['site_url'], 'result_url' => $result['url'] ?? '', 'status' => 'success', 'created_at' => current_time('mysql')]);
                } else {
                    $failed_count++;
                    $results[] = ['name' => $gen['name'] ?? $gen['title'] ?? 'نامشخص', 'status' => 'error', 'error' => $result['error'] ?? 'خطای انتشار'];
                }
                sleep(1);
            } catch (Exception $e) {
                $failed_count++;
                $results[] = ['name' => $gen['name'] ?? $gen['title'] ?? 'نامشخص', 'status' => 'error', 'error' => $e->getMessage()];
            }
        }

        wp_send_json_success([
            'message' => "$success_count موفق / $failed_count ناموفق",
            'success_count' => $success_count,
            'failed_count' => $failed_count,
            'results' => $results,
            'tokens_used' => $result['tokens_used'] ?? 0,
            'cost' => $result['cost'] ?? null,
        ]);
    }

    public function handle_fetch_remote_product() {
        $user_id = $this->ajax_require_auth();
        if ($this->get_user_plan($user_id) !== 'pro') wp_send_json_error(['message' => 'کلون محصول فقط در پلن Pro موجود است']);
        $site_id = intval($_POST['site_id'] ?? 0);
        $product_url = esc_url_raw($_POST['product_url'] ?? '');
        if (empty($product_url)) wp_send_json_error(['message' => 'آدرس محصول الزامی است']);
        $sites = $this->get_user_wp_sites($user_id);
        $target_site = null;
        foreach ($sites as $site) { if ((int)$site['id'] === $site_id) { $target_site = $site; break; } }
        if (!$target_site) wp_send_json_error(['message' => 'سایت مقصد یافت نشد']);
        $product_id = 0;
        if (preg_match('/\/product\/(\d+)/', $product_url, $m)) $product_id = intval($m[1]);
        elseif (preg_match('/post=(\d+)/', $product_url, $m)) $product_id = intval($m[1]);
        elseif (is_numeric(basename($product_url))) $product_id = intval(basename($product_url));
        if (!$product_id) {
            $slug = basename(rtrim($product_url, '/'));
            $auth = base64_encode($target_site['username'] . ':' . $target_site['app_password']);
            $endpoint = rtrim($target_site['site_url'], '/') . '/wp-json/wc/v3/products?slug=' . urlencode($slug);
            $args = array_merge(['timeout' => 15, 'headers' => ['Authorization' => 'Basic ' . $auth]], $this->get_proxy_args());
            $response = wp_remote_get($endpoint, $args);
            if (!is_wp_error($response)) { $body = json_decode(wp_remote_retrieve_body($response), true); if (!empty($body[0]['id'])) $product_id = $body[0]['id']; }
        }
        if (!$product_id) wp_send_json_error(['message' => 'محصول یافت نشد']);
        $auth = base64_encode($target_site['username'] . ':' . $target_site['app_password']);
        $endpoint = rtrim($target_site['site_url'], '/') . '/wp-json/wc/v3/products/' . $product_id;
        $args = array_merge(['timeout' => 15, 'headers' => ['Authorization' => 'Basic ' . $auth]], $this->get_proxy_args());
        $response = wp_remote_get($endpoint, $args);
        if (is_wp_error($response)) wp_send_json_error(['message' => $response->get_error_message()]);
        $code = wp_remote_retrieve_response_code($response);
        $body = json_decode(wp_remote_retrieve_body($response), true);
        if ($code !== 200) wp_send_json_error(['message' => $body['message'] ?? 'خطای ' . $code]);
        wp_send_json_success(['data' => [
            'name' => $body['name'] ?? '', 'short_description' => wp_strip_all_tags($body['short_description'] ?? ''),
            'description' => $body['description'] ?? '', 'regular_price' => $body['regular_price'] ?? '',
            'sale_price' => $body['sale_price'] ?? '', 'sku' => $body['sku'] ?? '', 'weight' => $body['weight'] ?? '',
            'stock_status' => $body['stock_status'] ?? 'instock',
            'categories' => array_map(function($c) { return ['id' => $c['id'], 'name' => $c['name']]; }, $body['categories'] ?? []),
            'tags' => array_map(function($t) { return $t['name']; }, $body['tags'] ?? []),
            'attributes' => array_map(function($a) { return ['name' => $a['name'], 'options' => $a['options'] ?? []]; }, $body['attributes'] ?? []),
            'images' => array_map(function($img) { return $img['src']; }, $body['images'] ?? []),
            'dimensions' => $body['dimensions'] ?? [], 'status' => 'draft',
        ]]);
    }

    public function handle_fetch_woo_brands() {
        $user_id = $this->ajax_require_auth();
        $site_id = intval($_POST['site_id'] ?? 0);
        $sites = $this->get_user_wp_sites($user_id);
        $target_site = null;
        foreach ($sites as $site) { if ((int)$site['id'] === $site_id) { $target_site = $site; break; } }
        if (!$target_site) wp_send_json_error(['message' => 'سایت یافت نشد']);
        $auth = base64_encode($target_site['username'] . ':' . $target_site['app_password']);
        $taxonomies = ['product_brand', 'brand', 'pwb-brand', 'pa_brand'];
        $brands = [];
        foreach ($taxonomies as $tax) {
            $endpoint = rtrim($target_site['site_url'], '/') . '/wp-json/wp/v2/' . $tax . '?per_page=100';
            $args = array_merge(['timeout' => 10, 'headers' => ['Authorization' => 'Basic ' . $auth]], $this->get_proxy_args());
            $response = wp_remote_get($endpoint, $args);
            if (!is_wp_error($response)) {
                $code = wp_remote_retrieve_response_code($response);
                if ($code === 200) { $body = json_decode(wp_remote_retrieve_body($response), true); if (is_array($body) && !isset($body['code'])) { $brands = array_map(function($b) { return ['id' => $b['id'], 'name' => $b['name']]; }, $body); break; } }
            }
        }
        wp_send_json_success(['brands' => $brands]);
    }

    public function handle_fetch_woo_shipping() {
        $user_id = $this->ajax_require_auth();
        $site_id = intval($_POST['site_id'] ?? 0);
        $sites = $this->get_user_wp_sites($user_id);
        $target_site = null;
        foreach ($sites as $site) { if ((int)$site['id'] === $site_id) { $target_site = $site; break; } }
        if (!$target_site) wp_send_json_error(['message' => 'سایت یافت نشد']);
        $auth = base64_encode($target_site['username'] . ':' . $target_site['app_password']);
        $endpoint = rtrim($target_site['site_url'], '/') . '/wp-json/wc/v3/products/shipping_classes?per_page=100';
        $args = array_merge(['timeout' => 10, 'headers' => ['Authorization' => 'Basic ' . $auth]], $this->get_proxy_args());
        $response = wp_remote_get($endpoint, $args);
        if (is_wp_error($response)) wp_send_json_error(['message' => $response->get_error_message()]);
        $code = wp_remote_retrieve_response_code($response);
        $body = json_decode(wp_remote_retrieve_body($response), true);
        if ($code === 200 && is_array($body)) { wp_send_json_success(['shipping_classes' => array_map(function($c) { return ['id' => $c['id'], 'name' => $c['name']]; }, $body)]); }
        wp_send_json_error(['message' => 'خطای ' . $code]);
    }

    public function handle_batch_generate() {
        @set_time_limit(300);
        @ignore_user_abort(true);
        @ini_set('memory_limit', '256M');
        check_ajax_referer('ssp_secure_nonce', 'security');
        $user_id = $this->ajax_require_auth();
        if ($this->get_user_plan($user_id) !== 'pro') wp_send_json_error(['message' => 'تولید دسته‌ای فقط در پلن Pro موجود است']);
        $count = min(10, max(1, intval($_POST['count'] ?? 3)));
        $topic = sanitize_text_field($_POST['topic'] ?? '') ?: (get_user_meta($user_id, 'ssp_ai_topic', true) ?: 'عمومی');
        $style = sanitize_text_field($_POST['style'] ?? 'general');
        $length = intval($_POST['length'] ?? 800);
        $tone = sanitize_text_field($_POST['tone'] ?? 'natural');
        $ai_api_key = get_user_meta($user_id, 'ssp_ai_api_key', true);
        $ai_mode = get_user_meta($user_id, 'ssp_ai_mode', true) ?: 'api';
        if ($ai_mode !== 'browser' && empty($ai_api_key)) wp_send_json_error(['message' => 'API Key تنظیم نشده']);
        $style_guides = ['general' => 'عمومی و متعادل', 'formal' => 'رسمی و حرفه‌ای', 'casual' => 'صمیمی و دوستانه', 'promotional' => 'تبلیغاتی با دعوت به اقدام', 'educational' => 'آموزشی با نکات عملی', 'news' => 'خبری و واقعی', 'story' => 'داستانی و روایتی', 'motivational' => 'انگیزشی و الهام‌بخش', 'humorous' => 'طنز و سرگرمی', 'technical' => 'فنی و تخصصی', 'review' => 'نقد و بررسی', 'comparison' => 'مقایسه‌ای', 'list' => 'لیستی و فهرستی', 'question' => 'سؤالی و تعاملی'];
        $tone_guides = ['natural' => 'طبیعی و روان', 'enthusiastic' => 'پرانرژی و هیجانی', 'calm' => 'آرام و خونسرد', 'authoritative' => 'موثق و مطمئن', 'friendly' => 'صمیمانه و صمیمی', 'persuasive' => 'قانع‌کننده', 'emotional' => 'احساسی و عاطفی', 'analytical' => 'تحلیلی و منطقی'];
        $style_desc = $style_guides[$style] ?? 'متعادل';
        $tone_desc = $tone_guides[$tone] ?? 'طبیعی';
        $prompt = "{$count} پست شبکه اجتماعی فارسی درباره «{$topic}».\n" .
            "سبک محتوا: {$style_desc}\n" .
            "لحن نوشتار: {$tone_desc}\n" .
            "هر پست باید:\n" .
            "- عنوان جذاب ≤80 کاراکتر (شامل عدد یا سوال)\n" .
            "- متن حدود {$length} کاراکتر با نکات عملی و واقعی\n" .
            "- لحن طبیعی، مثل یک آدم واقعی\n" .
            "- زاویه متفاوت نسبت به بقیه\n" .
            "- متن باید کاملاً ساده باشه. هیچ تگ HTML مثل <p> <br> <b> <strong> استفاده نکن\n" .
            "- بولد رو با ** بنویس\n" .
            "- فقط اطلاعات واقعی و تایید شده بنویس. اگر از صحت چیزی مطمئن نیستی، اون رو حذف کن\n" .
            "- هیچ هشتگی (#) در متن اضافه نکن\n" .
            '[{"title":"","message":""}]';
        try {
            $result = $this->call_ai_api(get_user_meta($user_id, 'ssp_ai_provider', true) ?: 'openai', $ai_api_key, get_user_meta($user_id, 'ssp_ai_model', true) ?: 'gpt-4o-mini', $prompt, true);
            $decoded = $this->parse_ai_json($result['content']);
            if ($decoded && is_array($decoded)) {
                foreach ($decoded as $idx => $item) {
                    foreach ($item as $key => $val) {
                        if (is_string($val)) $decoded[$idx][$key] = wp_strip_all_tags($val);
                    }
                }
                wp_send_json_success([
                    'items' => $decoded,
                    'tokens_used' => $result['tokens_used'] ?? 0,
                    'cost' => $result['cost'] ?? null,
                ]);
            } else {
                $content = trim($result['content']);
                if (preg_match('/```(?:json)?\s*\n?(.*?)\n?\s*```/s', $content, $m)) $decoded = json_decode(trim($m[1]), true);
                if (!$decoded) { $start = strpos($content, '['); $end = strrpos($content, ']'); if ($start !== false && $end !== false) $decoded = json_decode(substr($content, $start, $end - $start + 1), true); }
                if ($decoded && is_array($decoded)) wp_send_json_success(['items' => $decoded]);
                else wp_send_json_error(['message' => 'پاسخ AI قابل تفسیر نیست: ' . mb_substr($result['content'], 0, 200)]);
            }
        } catch (Exception $e) { wp_send_json_error(['message' => $e->getMessage()]); }
    }

    public function handle_send_batch() {
        check_ajax_referer('ssp_secure_nonce', 'security');
        $user_id = $this->ajax_require_auth();
        $items = json_decode(stripslashes($_POST['items'] ?? '[]'), true);
        if (empty($items) || !is_array($items)) wp_send_json_error(['message' => 'آیتمی برای ارسال وجود ندارد']);

        // Sanitize batch items
        $items = array_map(function($item) {
            return [
                'title' => sanitize_text_field($item['title'] ?? ''),
                'message' => sanitize_textarea_field($item['message'] ?? ''),
                'hashtags' => sanitize_text_field($item['hashtags'] ?? ''),
            ];
        }, $items);

        $queued = 0;
        foreach ($items as $item) {
            $result = $this->add_to_queue($user_id, 'ai_generated', $item, 10);
            if ($result['success']) $queued++;
        }
        wp_send_json_success(['message' => "$queued محتوا به صف ارسال اضافه شد"]);
    }

    public function handle_shorten_url() {
        $user_id = $this->ajax_require_auth();
        $url = esc_url_raw($_POST['url'] ?? '');
        $settings = $this->get_link_settings($user_id);
        $shortened = $this->shorten_url($url, $settings);
        wp_send_json_success(['short_url' => $shortened]);
    }

    public function handle_save_link_settings() {
        $user_id = $this->ajax_require_auth();
        $settings = ['enabled' => intval($_POST['enabled'] ?? 0), 'provider' => sanitize_text_field($_POST['provider'] ?? 'bitly'), 'api_key' => sanitize_text_field($_POST['api_key'] ?? '')];
        $this->set_link_settings($user_id, $settings);
        wp_send_json_success(['message' => 'تنظیمات ذخیره شد']);
    }

    public function handle_get_short_links() {
        $user_id = $this->ajax_require_auth();
        wp_send_json_success($this->get_short_link_stats($user_id));
    }

    public function handle_delete_short_link() {
        $user_id = $this->ajax_require_auth();
        $code = sanitize_text_field($_POST['code'] ?? '');
        if (empty($code)) wp_send_json_error(['message' => 'کد لینک الزامی است']);
        $short_links = get_option('ssp_short_links', []);
        if (isset($short_links[$code])) { unset($short_links[$code]); update_option('ssp_short_links', $short_links); wp_send_json_success(['message' => 'لینک حذف شد']); }
        else wp_send_json_error(['message' => 'لینک یافت نشد']);
    }

    public function handle_save_short_url_format() {
        $user_id = $this->ajax_require_admin();
        $format = sanitize_text_field($_POST['format'] ?? 'subdomain');
        $custom_url = esc_url_raw($_POST['custom_url'] ?? '');
        update_option('ssp_short_url_format', $format);
        if ($format === 'custom' && !empty($custom_url)) update_option('ssp_short_url_custom', $custom_url);
        wp_send_json_success(['message' => 'فرمت ذخیره شد']);
    }

    public function handle_save_custom_domain() {
        $user_id = $this->ajax_require_auth();
        $custom_domain = esc_url_raw($_POST['custom_domain'] ?? '');
        if (!empty($custom_domain)) {
            $parsed = parse_url($custom_domain);
            if (!$parsed || !isset($parsed['scheme']) || !isset($parsed['host'])) wp_send_json_error(['message' => 'آدرس دامنه نامعتبر است']);
            if ($parsed['scheme'] !== 'https') $custom_domain = 'https://' . $parsed['host'];
        }
        update_user_meta($user_id, 'ssp_custom_domain', $custom_domain);
        wp_send_json_success(['message' => 'دامنه سفارشی ذخیره شد', 'example' => !empty($custom_domain) ? rtrim($custom_domain, '/') . '/abc123' : '']);
    }

    public function handle_save_proxy_settings() {
        $this->ajax_require_admin();
        $settings = ['enabled' => intval($_POST['proxy_enabled'] ?? 0), 'host' => sanitize_text_field($_POST['proxy_host'] ?? ''), 'port' => intval($_POST['proxy_port'] ?? 0), 'type' => sanitize_text_field($_POST['proxy_type'] ?? 'http'), 'username' => sanitize_text_field($_POST['proxy_username'] ?? ''), 'password' => sanitize_text_field($_POST['proxy_password'] ?? '')];
        update_option('ssp_proxy_settings', $settings);
        wp_send_json_success(['message' => 'تنظیمات پروکسی ذخیره شد']);
    }

    public function handle_test_proxy() {
        $this->ajax_require_admin();
        $proxy_settings = get_option('ssp_proxy_settings', []);
        if (empty($proxy_settings['enabled']) || empty($proxy_settings['host']) || empty($proxy_settings['port'])) wp_send_json_error(['message' => 'تنظیمات پروکسی ناقص است']);
        $proxy_url = $proxy_settings['type'] . '://' . $proxy_settings['host'] . ':' . $proxy_settings['port'];
        $response = wp_remote_get('https://httpbin.org/ip', ['timeout' => 15, 'proxy' => $proxy_url, 'sslverify' => false]);
        if (is_wp_error($response)) wp_send_json_error(['message' => 'خطا در اتصال از طریق پروکسی: ' . $response->get_error_message()]);
        $code = wp_remote_retrieve_response_code($response);
        $body = json_decode(wp_remote_retrieve_body($response), true);
        if ($code === 200) wp_send_json_success(['message' => 'پروکسی کار می‌کند!', 'ip' => $body['origin'] ?? 'نامشخص']);
        else wp_send_json_error(['message' => 'خطای ' . $code]);
    }

    public function handle_network_diagnostics() {
        $this->ajax_require_admin();
        $results = [];
        $basic_test = $this->test_basic_connectivity();
        $results['اتصال پایه'] = ['status' => $basic_test['success'] ? 'success' : 'error', 'message' => $basic_test['success'] ? 'اتصال به ' . $basic_test['service'] . ' موفق' : $basic_test['error'], 'time' => ''];
        $dns_servers = ['Cloudflare DNS' => 'https://1.1.1.1/dns-query?name=example.com&type=A', 'Google DNS' => 'https://dns.google/resolve?name=example.com&type=A'];
        foreach ($dns_servers as $name => $url) {
            $start = microtime(true);
            $response = wp_remote_get($url, ['timeout' => 10, 'headers' => ['Accept' => 'application/dns-json']]);
            $time = round((microtime(true) - $start) * 1000);
            if (is_wp_error($response)) $results[$name] = ['status' => 'error', 'message' => $response->get_error_message(), 'time' => $time . 'ms'];
            else { $body = json_decode(wp_remote_retrieve_body($response), true); $results[$name] = ['status' => !empty($body['Answer']) ? 'success' : 'warning', 'message' => !empty($body['Answer']) ? 'حل DNS موفق' : 'پاسخ نامعتبر', 'time' => $time . 'ms']; }
        }
        $endpoints = ['OpenRouter API' => 'https://openrouter.ai', 'OpenAI API' => 'https://api.openai.com', 'Anthropic API' => 'https://api.anthropic.com', 'Google Gemini' => 'https://generativelanguage.googleapis.com', 'Groq API' => 'https://api.groq.com', 'DeepSeek API' => 'https://api.deepseek.com', 'Telegram API' => 'https://api.telegram.org', 'Bale API' => 'https://tapi.bale.ai', 'Eitaa API' => 'https://api.eitaa.com'];
        $proxy = $this->get_proxy_settings();
        foreach ($endpoints as $name => $url) {
            $args = array_merge(['timeout' => 10], $this->get_proxy_args());
            $start = microtime(true);
            $response = wp_remote_get($url, $args);
            $time = round((microtime(true) - $start) * 1000);
            if (is_wp_error($response)) $results[$name] = ['status' => 'error', 'message' => $response->get_error_message(), 'time' => $time . 'ms'];
            else { $code = wp_remote_retrieve_response_code($response); $results[$name] = ['status' => $code < 400 ? 'success' : 'warning', 'code' => $code, 'time' => $time . 'ms']; }
        }
        wp_send_json_success(['results' => $results]);
    }

    public function handle_save_doh_settings() {
        $this->ajax_require_admin();
        update_option('ssp_doh_enabled', intval($_POST['doh_enabled'] ?? 0));
        update_option('ssp_doh_server', sanitize_text_field($_POST['doh_server'] ?? 'cloudflare'));
        wp_send_json_success(['message' => 'تنظیمات DNS ذخیره شد']);
    }

    public function handle_test_doh() {
        $this->ajax_require_admin();
        $doh_server = sanitize_text_field($_POST['doh_server'] ?? 'cloudflare');
        $test_domain = 'openrouter.ai';
        $connectivity = $this->test_doh_connectivity($doh_server);
        if (!$connectivity['success']) wp_send_json_error(['message' => 'سرور DNS قابل دسترسی نیست!', 'hint' => 'فایروال سرور شما دسترسی به سرورهای DNS را هم مسدود کرده.']);
        $start = microtime(true);
        $ip = $this->resolve_doh($test_domain, $doh_server);
        $time = round((microtime(true) - $start) * 1000);
        if ($ip) wp_send_json_success(['message' => 'DNS با موفقیت حل شد!', 'domain' => $test_domain, 'ip' => $ip, 'time' => $time . 'ms']);
        else {
            $test_ip = $this->resolve_doh('example.com', $doh_server);
            if ($test_ip) wp_send_json_error(['message' => 'DNS کار می‌کند ولی ' . $test_domain . ' حل نشد!', 'hint' => 'ممکن است دامنه موقتاً در دسترس نباشد.']);
            else wp_send_json_error(['message' => 'DNS over HTTPS کار نمی‌کند!', 'hint' => 'فایروال سرور شما دسترسی به سرورهای DNS را مسدود کرده.']);
        }
    }

    public function handle_test_telegram_dns() {
        $this->ajax_require_admin();
        $single_server = sanitize_text_field($_POST['server'] ?? '');
        $doh_servers = SSP_DOH_SERVERS;
        $test_domains = ['api.telegram.org', 'tapi.bale.ai', 'docs.bale.ai', 'api.openai.com', 'api.anthropic.com'];
        if (!empty($single_server) && isset($doh_servers[$single_server])) {
            $server_url = $doh_servers[$single_server];
            $server_results = [];
            foreach ($test_domains as $domain) {
                $start = microtime(true);
                $response = wp_remote_get($server_url . '?name=' . urlencode($domain) . '&type=A', ['timeout' => 6, 'headers' => ['Accept' => 'application/dns-json']]);
                $time = round((microtime(true) - $start) * 1000);
                if (is_wp_error($response)) { $server_results[$domain] = ['status' => 'error', 'message' => $response->get_error_message(), 'time' => $time]; continue; }
                $body = json_decode(wp_remote_retrieve_body($response), true);
                $found = false;
                if (!empty($body['Answer'])) { foreach ($body['Answer'] as $answer) { if ($answer['type'] === 1) { $server_results[$domain] = ['status' => 'success', 'ip' => $answer['data'], 'time' => $time]; $found = true; break; } } }
                if (!$found) $server_results[$domain] = ['status' => 'failed', 'message' => 'IP یافت نشد', 'time' => $time];
            }
            $all_success = true;
            foreach ($server_results as $r) { if ($r['status'] !== 'success') { $all_success = false; break; } }
            wp_send_json_success(['server' => $single_server, 'results' => $server_results, 'overall' => $all_success ? 'success' : 'partial']);
            return;
        }
        $server_list = [];
        foreach ($doh_servers as $name => $url) $server_list[$name] = $url;
        wp_send_json_success(['servers' => $server_list, 'test_domains' => $test_domains]);
    }

    public function handle_save_utm_settings() {
        $user_id = $this->ajax_require_auth();
        $settings = ['enabled' => intval($_POST['utm_enabled'] ?? 0), 'source' => sanitize_text_field($_POST['utm_source'] ?? 'smart-automation'), 'medium' => sanitize_text_field($_POST['utm_medium'] ?? 'social'), 'campaign' => sanitize_text_field($_POST['utm_campaign'] ?? ''), 'auto_source' => intval($_POST['utm_auto_source'] ?? 1)];
        update_user_meta($user_id, 'ssp_utm_settings', $settings);
        wp_send_json_success(['message' => 'تنظیمات UTM ذخیره شد']);
    }

    public function handle_save_email_settings() {
        $user_id = $this->ajax_require_auth();
        $settings = ['enabled' => intval($_POST['email_enabled'] ?? 0), 'email' => sanitize_email($_POST['email_address'] ?? ''), 'on_failure' => intval($_POST['email_on_failure'] ?? 1), 'on_license_expiry' => intval($_POST['email_on_license_expiry'] ?? 1), 'daily_summary' => intval($_POST['email_daily_summary'] ?? 0)];
        update_user_meta($user_id, 'ssp_email_settings', $settings);
        wp_send_json_success(['message' => 'تنظیمات ایمیل ذخیره شد']);
    }

    public function handle_test_email() {
        $user_id = $this->ajax_require_auth();
        $email = sanitize_email($_POST['email_address'] ?? '');
        if (empty($email)) { $user = get_userdata($user_id); $email = $user ? $user->user_email : ''; }
        if (empty($email)) wp_send_json_error(['message' => 'آدرس ایمیل وارد نشده']);
        $headers = ['Content-Type: text/html; charset=UTF-8'];
        $body = '<div style="font-family:Tahoma,sans-serif; direction:rtl; padding:20px;"><h2 style="color:#10B981;">تست ایمیل موفق!</h2><p>این یک ایمیل تستی از Smart Automation است.</p></div>';
        $sent = wp_mail($email, 'تست Smart Automation', $body, $headers);
        if ($sent) wp_send_json_success(['message' => 'ایمیل تست ارسال شد!']);
        else wp_send_json_error(['message' => 'خطا در ارسال ایمیل']);
    }

    public function handle_generate_image() {
        $user_id = $this->ajax_require_auth();
        if ($this->get_user_plan($user_id) === 'free') wp_send_json_error(['message' => 'این قابلیت فقط در پلن Pro موجود است']);
        $prompt = sanitize_textarea_field($_POST['prompt'] ?? '');
        $size = sanitize_text_field($_POST['size'] ?? '1024x1024');
        $quality = sanitize_text_field($_POST['quality'] ?? 'standard');
        if (empty($prompt)) wp_send_json_error(['message' => 'پرامپت الزامی است']);
        try {
            $result = $this->generate_ai_image($user_id, $prompt, $size, $quality);
            $images = get_user_meta($user_id, 'ssp_generated_images', true) ?: [];
            array_unshift($images, ['url' => $result['url'], 'prompt' => $prompt, 'created_at' => current_time('mysql')]);
            $images = array_slice($images, 0, 20);
            update_user_meta($user_id, 'ssp_generated_images', $images);
            wp_send_json_success(['message' => 'تصویر تولید شد!', 'url' => $result['url']]);
        } catch (Exception $e) { wp_send_json_error(['message' => $e->getMessage()]); }
    }

    public function handle_save_image_settings() {
        $user_id = $this->ajax_require_auth();
        $settings = ['auto_image' => intval($_POST['auto_image'] ?? 0), 'default_size' => sanitize_text_field($_POST['default_size'] ?? '1024x1024'), 'default_quality' => sanitize_text_field($_POST['default_quality'] ?? 'standard')];
        update_user_meta($user_id, 'ssp_image_settings', $settings);
        wp_send_json_success(['message' => 'تنظیمات تصویر ذخیره شد']);
    }
}
