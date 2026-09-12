<?php

trait SSP_Seo {

    // ════════════════════════════════════════════════════════════════
    // AI Prompt Engineering Modes
    // ════════════════════════════════════════════════════════════════

    private $prompt_modes = [
        'human' => [
            'name' => '🧠 Human Writer',
            'description' => 'متن را انسانی‌تر و طبیعی‌تر بنویسید',
            'prefix' => "Apply /human mode: Rewrite and analyze as if written by an experienced human writer. Avoid AI clichés, repetitive sentence patterns, and robotic phrasing. Use natural transitions, varied sentence lengths, opinions, and conversational language.",
        ],
        'redteam' => [
            'name' => '🔴 Red Team',
            'description' => 'تحلیل انتقادی نقاط ضعف و ریسک‌ها',
            'prefix' => "Apply /redteam mode: Act as a critical reviewer. Challenge assumptions, identify weaknesses, hidden risks, logical flaws, SEO problems, and alternative viewpoints. Be harsh but constructive.",
        ],
        'x10think' => [
            'name' => '🧠 x10 Think',
            'description' => 'تفکر عمیق‌تر و تحلیل لایه‌ای',
            'prefix' => "Apply /x10think mode: Think 10 times deeper than normal. Consider edge cases, second-order effects, long-term consequences, and hidden assumptions. Analyze from multiple angles.",
        ],
        'socrates' => [
            'name' => '🏛️ Socrates',
            'description' => 'پرسش‌گری هوشمند قبل از تحلیل',
            'prefix' => "Apply /socrates mode: Do not answer immediately. Ask one question at a time until enough information is gathered. Guide toward discovering the answer through questioning.",
        ],
        'truth' => [
            'name' => '⚖️ Truth',
            'description' => 'بیان واقعیت‌ها با سطح اطمینان',
            'prefix' => "Apply /truth mode: Present facts, uncertainty levels, counterarguments and confidence estimates. State what is known, unknown, and disputed. Be transparent about limitations.",
        ],
        'meta' => [
            'name' => '🔍 Meta',
            'description' => 'تحلیل فرآیند فکر و استدلال',
            'prefix' => "Apply /meta mode: Explain your assumptions, reasoning strategy, limitations, and possible failure modes. Show your thinking process.",
        ],
        'predict' => [
            'name' => '🔮 Predict',
            'description' => 'پیش‌بینی سناریوهای آینده',
            'prefix' => "Apply /predict mode: Generate multiple future scenarios. Include probabilities, assumptions, and possible disruptions. Predict trends and risks.",
        ],
        'ooda' => [
            'name' => '🔄 OODA Loop',
            'description' => 'چرخه مشاهده-جهت‌گیری-تصمیم-عمل',
            'prefix' => "Apply /ooda mode: Use the Observe-Orient-Decide-Act framework. First observe all facts, then orient by analyzing context, decide on possible actions, and recommend execution steps.",
        ],
        'eli10' => [
            'name' => '👶 ELI10',
            'description' => 'توضیح ساده برای کودک ۱۰ ساله',
            'prefix' => "Apply /eli10 mode: Explain this topic as if speaking to a smart 10-year-old child. Use examples and analogies. Avoid technical jargon. Make it simple and clear.",
        ],
        'alt3' => [
            'name' => '🔀 Alt3',
            'description' => '۳ دیدگاه و استراتژی مختلف',
            'prefix' => "Apply /alt3 mode: Generate 3 completely different approaches, strategies, or perspectives. Compare pros and cons of each. Provide diverse viewpoints.",
        ],
    ];

    public function handle_analyze_seo() {
        check_ajax_referer('ssp_secure_nonce', 'security');
        if (!is_user_logged_in()) wp_send_json_error(['message' => 'غیرمجاز']);

        $title = sanitize_text_field($_POST['title'] ?? '');
        $content = sanitize_textarea_field($_POST['content'] ?? '');
        $hashtags = sanitize_text_field($_POST['hashtags'] ?? '');
        $platform = sanitize_text_field($_POST['platform'] ?? 'general');

        $result = $this->analyze_content_seo($title, $content, $hashtags, $platform);
        wp_send_json_success($result);
    }

    public function handle_generate_meta_desc() {
        check_ajax_referer('ssp_secure_nonce', 'security');
        if (!is_user_logged_in()) wp_send_json_error(['message' => 'غیرمجاز']);

        $title = sanitize_text_field($_POST['title'] ?? '');
        $content = sanitize_textarea_field($_POST['content'] ?? '');

        $meta = $this->generate_meta_description($title, $content);
        wp_send_json_success(['meta_description' => $meta]);
    }

    public function handle_seo_suggest_title() {
        check_ajax_referer('ssp_secure_nonce', 'security');
        if (!is_user_logged_in()) wp_send_json_error(['message' => 'غیرمجاز']);

        $title = sanitize_text_field($_POST['title'] ?? '');
        $content = sanitize_textarea_field($_POST['content'] ?? '');
        $platform = sanitize_text_field($_POST['platform'] ?? 'general');

        $suggestions = $this->generate_seo_title_suggestions($title, $content, $platform);
        wp_send_json_success(['suggestions' => $suggestions]);
    }

    public function handle_analyze_seo_ai() {
        $user_id = $this->ajax_require_auth();
        if ($this->get_user_plan($user_id) === 'free') {
            wp_send_json_error(['message' => 'تحلیل AI فقط در پلن Pro موجود است']);
        }

        $title = sanitize_text_field($_POST['title'] ?? '');
        $content = sanitize_textarea_field($_POST['content'] ?? '');
        $hashtags = sanitize_text_field($_POST['hashtags'] ?? '');
        $platform = sanitize_text_field($_POST['platform'] ?? 'general');

        if (empty($title) && empty($content)) {
            wp_send_json_error(['message' => 'عنوان یا محتوا را وارد کنید']);
        }

        $basic = $this->analyze_content_seo($title, $content, $hashtags, $platform);

        $platform_tips = [
            'telegram' => 'متن روان، خوانا با فاصله‌گذاری خطوط، بولد کردن نکات کلیدی و ایموجی‌های متناسب',
            'bale' => 'متن خوانا با بولد و نکات برجسته',
            'eitaa' => 'متن شفاف، بدون پیچیدگی و منظم',
            'rubika' => 'متن تعاملی و پرانرژی',
            'instagram' => 'کپشن جذاب، قلاب ثانیه اول، فراخوان تعامل و هشتگ‌های هدفمند',
            'whatsapp' => 'مختصر، هدفمند و شخصی‌سازی شده',
            'website' => 'عنوان استاندارد ۳۰-۶۰ کاراکتر، متای سئو بهینه، ساختار هدینگ‌های H2/H3 و رعایت کلمات کلیدی LSI',
            'general' => 'متعادل، حرفه‌ای و پرکشش',
        ];

        $tip = $platform_tips[$platform] ?? $platform_tips['general'];
        $content_preview = mb_substr($content, 0, 1500);
        $prompt = "تحلیل جامع، تخصصی و سختگیرانه سئو و کیفیت محتوای فارسی برای پلتفرم {$platform} ({$tip}).\n\n" .
            "عنوان فعلی: {$title}\n" .
            "محتوای فعلی:\n{$content_preview}\n\n" .
            "هشتگ‌های فعلی: {$hashtags}\n\n" .
            "الزامات تحلیل:\n" .
            "- ارزیابی نمره دقیق سئو و جذابیت بین ۰ تا ۱۰۰\n" .
            "- ارائه خلاصه ارزیابی و تحلیل نقاط قوت و ضعف\n" .
            "- ارائه راهکارهای عملی و مشخص برای افزایش نرخ کلیک و تعامل\n" .
            "- تولید نسخه بهینه‌سازی شده عنوان و محتوای بازنویسی شده و آماده انتشار\n\n" .
            "دستور اکید: پاسخ شما ۱۰۰٪ منحصراً یک آبجکت معتبر JSON بدون هیچ کاراکتر، توضیح یا پیش‌گفتاری باشد با فیلدهای زیر:\n" .
            "{\n" .
            "  \"score\": 85,\n" .
            "  \"summary\": \"خلاصه ارزیابی و وضعیت کیفیت محتوا\",\n" .
            "  \"strengths\": [\"نقطه قوت ۱\", \"نقطه قوت ۲\"],\n" .
            "  \"weaknesses\": [\"نقطه ضعف ۱\", \"نقطه ضعف ۲\"],\n" .
            "  \"improvements\": [\"راهکار بهبود ۱\", \"راهکار بهبود ۲\"],\n" .
            "  \"optimized_title\": \"عنوان بهینه‌سازی شده و به شدت جذاب\",\n" .
            "  \"optimized_content\": \"متن بازنویسی شده و کامل محتوا آماده انتشار\"\n" .
            "}";

        // Apply prompt mode if selected
        $prompt_mode = sanitize_text_field($_POST['prompt_mode'] ?? '');
        if (!empty($prompt_mode) && isset($this->prompt_modes[$prompt_mode])) {
            $prompt = $this->prompt_modes[$prompt_mode]['prefix'] . "\n\n" . $prompt;
        }

        try {
            $result = $this->call_ai_api(
                get_user_meta($user_id, 'ssp_ai_provider', true) ?: 'openai',
                get_user_meta($user_id, 'ssp_ai_api_key', true),
                get_user_meta($user_id, 'ssp_ai_model', true) ?: 'gpt-4o-mini',
                $prompt,
                true
            );

            $decoded = $this->parse_ai_json($result['content']);

            if ($decoded) {
                $decoded['basic_analysis'] = $basic;
                $decoded['tokens_used'] = $result['tokens_used'] ?? 0;
                $decoded['cost'] = $result['cost'] ?? null;
                wp_send_json_success($decoded);
            } else {
                wp_send_json_error(['message' => 'پاسخ AI قابل تفسیر نیست. محتوا را کوتاه‌تر کنید.']);
            }
        } catch (\Throwable $e) {
            $error_msg = $e->getMessage();
            if (empty($error_msg)) $error_msg = 'خطای ناشناخته در ارتباط با AI';
            wp_send_json_error(['message' => 'خطا در تحلیل AI: ' . $error_msg]);
        }
    }

    // ==================== Keyword Analyzer ====================
    public function handle_keyword_analyzer() {
        check_ajax_referer('ssp_secure_nonce', 'security');
        if (!is_user_logged_in()) wp_send_json_error(['message' => 'غیرمجاز']);

        $content = sanitize_textarea_field($_POST['content'] ?? '');
        $focus_keyword = sanitize_text_field($_POST['focus_keyword'] ?? '');
        $title = sanitize_text_field($_POST['title'] ?? '');

        if (empty($content)) {
            wp_send_json_error(['message' => 'محتوا را وارد کنید']);
        }

        $result = $this->analyze_keywords($content, $focus_keyword, $title);
        wp_send_json_success($result);
    }

    private function analyze_keywords($content, $focus_keyword = '', $title = '') {
        $clean = strip_tags($content);
        $clean = preg_replace('/\s+/', ' ', trim($clean));
        $total_words = str_word_count($clean);

        // Extract all words
        $stop_words = ['و', 'در', 'از', 'به', 'با', 'که', 'این', 'آن', 'را', 'برای', 'تا', 'هم', 'یا', 'ولی', 'اما', 'اگر', 'می', 'شد', 'است', 'بود', 'شود', 'هستند', 'باید', 'خیلی', 'همه', 'هر', 'یک', 'بعد', 'قبل', 'وقتی', 'جای', 'مثل', 'همچنین', 'بجز', 'غیر', 'درباره', 'درون', 'بیرون', 'روی', 'زیر', 'بالا', 'پایین', 'نیز', 'دارد', 'دارند', 'داشت', 'می‌شود', 'می‌کند', 'شده', 'کنید', 'باشد', 'بودن', 'شدن'];

        $tokens = preg_split('/[\s,\.!?؟:;\(\)\[\]\{\}\"\'<>\/\\|@#\$%^&*\-+=~`]+/', $clean, -1, PREG_SPLIT_NO_EMPTY);
        $word_freq = [];
        $bigram_freq = [];
        $trigram_freq = [];

        foreach ($tokens as $token) {
            $token = trim($token, '،.؟!.:;()[]{}"\'');
            $token_len = mb_strlen($token);
            if ($token_len >= 2 && !in_array($token, $stop_words)) {
                $word_freq[$token] = ($word_freq[$token] ?? 0) + 1;
            }
        }

        // Generate bigrams (2-word phrases)
        $words = array_values(array_filter($tokens, function($t) {
            $t = trim($t, '،.؟!.:;()[]{}"\'');
            return mb_strlen($t) >= 2;
        }));
        for ($i = 0; $i < count($words) - 1; $i++) {
            $bg = trim($words[$i], '،.؟!.:;()[]{}"\'') . ' ' . trim($words[$i + 1], '،.؟!.:;()[]{}"\'');
            if (mb_strlen($bg) > 5) {
                $bigram_freq[$bg] = ($bigram_freq[$bg] ?? 0) + 1;
            }
        }

        // Generate trigrams (3-word phrases)
        for ($i = 0; $i < count($words) - 2; $i++) {
            $tg = trim($words[$i], '،.؟!.:;()[]{}"\'') . ' ' . trim($words[$i + 1], '،.؟!.:;()[]{}"\'') . ' ' . trim($words[$i + 2], '،.؟!.:;()[]{}"\'');
            if (mb_strlen($tg) > 8) {
                $trigram_freq[$tg] = ($trigram_freq[$tg] ?? 0) + 1;
            }
        }

        arsort($word_freq);
        arsort($bigram_freq);
        arsort($trigram_freq);

        $top_words = [];
        foreach (array_slice($word_freq, 0, 15, true) as $word => $count) {
            $top_words[] = [
                'keyword' => $word,
                'count' => $count,
                'density' => $total_words > 0 ? round(($count / $total_words) * 100, 2) : 0,
                'type' => 'single',
            ];
        }

        $top_bigrams = [];
        foreach (array_slice($bigram_freq, 0, 10, true) as $phrase => $count) {
            if ($count >= 2) {
                $top_bigrams[] = [
                    'keyword' => $phrase,
                    'count' => $count,
                    'density' => $total_words > 0 ? round(($count / $total_words) * 100, 2) : 0,
                    'type' => 'phrase',
                ];
            }
        }

        $top_trigrams = [];
        foreach (array_slice($trigram_freq, 0, 5, true) as $phrase => $count) {
            if ($count >= 2) {
                $top_trigrams[] = [
                    'keyword' => $phrase,
                    'count' => $count,
                    'density' => $total_words > 0 ? round(($count / $total_words) * 100, 2) : 0,
                    'type' => 'long-tail',
                ];
            }
        }

        // Focus keyword analysis
        $focus_analysis = null;
        if (!empty($focus_keyword)) {
            $fk_lower = mb_strtolower($focus_keyword);
            $content_lower = mb_strtolower($clean);
            $fk_count = mb_substr_count($content_lower, $fk_lower);
            $title_lower = mb_strtolower($title);
            $title_has = mb_pos($title_lower, $fk_lower) !== false;

            $focus_analysis = [
                'keyword' => $focus_keyword,
                'count' => $fk_count,
                'density' => $total_words > 0 ? round(($fk_count / $total_words) * 100, 2) : 0,
                'in_title' => $title_has,
                'in_first_100' => mb_pos($content_lower, $fk_lower) !== false && mb_pos($content_lower, $fk_lower) < 300,
                'recommendations' => [],
            ];

            if ($fk_count === 0) {
                $focus_analysis['recommendations'][] = 'کلمه کلیدی در محتوا یافت نشد';
            } elseif ($fk_count < 3) {
                $focus_analysis['recommendations'][] = 'تکرار کلمه کلیدی کم است (حداقل 3 بار)';
            } elseif ($fk_count > 10) {
                $focus_analysis['recommendations'][] = 'تکرار کلمه کلیدی زیاد است - احتمال keyword stuffing';
            } else {
                $focus_analysis['recommendations'][] = 'تراکم کلمه کلیدی مناسب است';
            }

            if (!$title_has) {
                $focus_analysis['recommendations'][] = 'کلمه کلیدی در عنوان نیست';
            } else {
                $focus_analysis['recommendations'][] = 'کلمه کلیدی در عنوان وجود دارد';
            }

            if ($focus_analysis['in_first_100']) {
                $focus_analysis['recommendations'][] = 'کلمه کلیدی در 100 کاراکتر اول وجود دارد';
            } else {
                $focus_analysis['recommendations'][] = 'کلمه کلیدی را در 100 کاراکتر اول محتوا قرار دهید';
            }
        }

        // Keyword distribution
        $distribution = [];
        if (!empty($top_words)) {
            $max_count = $top_words[0]['count'];
            foreach ($top_words as $kw) {
                $distribution[] = [
                    'keyword' => $kw['keyword'],
                    'count' => $kw['count'],
                    'bar' => $max_count > 0 ? round(($kw['count'] / $max_count) * 100) : 0,
                ];
            }
        }

        return [
            'total_words' => $total_words,
            'unique_words' => count($word_freq),
            'top_words' => $top_words,
            'top_bigrams' => $top_bigrams,
            'top_trigrams' => $top_trigrams,
            'focus_keyword' => $focus_analysis,
            'distribution' => $distribution,
        ];
    }

    // ==================== SERP Preview ====================
    public function handle_serp_preview() {
        check_ajax_referer('ssp_secure_nonce', 'security');
        if (!is_user_logged_in()) wp_send_json_error(['message' => 'غیرمجاز']);

        $title = sanitize_text_field($_POST['title'] ?? '');
        $url = esc_url_raw($_POST['url'] ?? '');
        $meta_desc = sanitize_textarea_field($_POST['meta_desc'] ?? '');

        $result = $this->generate_serp_preview($title, $url, $meta_desc);
        wp_send_json_success($result);
    }

    private function generate_serp_preview($title, $url, $meta_desc) {
        $issues = [];
        $suggestions = [];

        // Title analysis
        $title_len = mb_strlen($title);
        if ($title_len === 0) {
            $issues[] = 'عنوان خالی است';
            $display_title = 'عنوان صفحه';
        } else {
            $display_title = $title_len > 60 ? mb_substr($title, 0, 57) . '...' : $title;
            if ($title_len > 60) {
                $issues[] = 'عنوان بیشتر از 60 کاراکتر است (' . $title_len . ' کاراکتر) - در Google بریده می‌شود';
            } elseif ($title_len < 30) {
                $suggestions[] = 'عنوان کوتاه است. حداقل 30 کاراکتر بنویسید';
            }
        }

        // URL analysis
        if (empty($url)) {
            $issues[] = 'آدرس URL خالی است';
            $display_url = 'example.com/page';
        } else {
            $display_url = parse_url($url, PHP_URL_HOST) . parse_url($url, PHP_URL_PATH);
            $slug = basename(parse_url($url, PHP_URL_PATH));
            if (empty($slug) || $slug === '/') {
                $suggestions[] = 'URL slug خالی است. از slug توصیفی استفاده کنید';
            } elseif (mb_strlen($slug) > 50) {
                $suggestions[] = 'URL slug خیلی بلند است';
            }
            if (preg_match('/[_\s]/', $slug)) {
                $suggestions[] = 'از خط تیره (-) به جای خط زیر (_) یا فاصله در URL استفاده کنید';
            }
        }

        // Meta description analysis
        $desc_len = mb_strlen($meta_desc);
        if ($desc_len === 0) {
            $issues[] = 'توضیحات meta خالی است';
            $display_desc = 'توضیحات صفحه در اینجا نمایش داده می‌شود...';
        } else {
            $display_desc = $desc_len > 155 ? mb_substr($meta_desc, 0, 152) . '...' : $meta_desc;
            if ($desc_len > 155) {
                $issues[] = 'توضیحات meta بیشتر از 155 کاراکتر است (' . $desc_len . ' کاراکتر)';
            } elseif ($desc_len < 70) {
                $suggestions[] = 'توضیحات meta خیلی کوتاه است. حداقل 70 کاراکتر بنویسید';
            }
        }

        $score = 100;
        $score -= count($issues) * 15;
        $score -= count($suggestions) * 5;

        return [
            'preview' => [
                'title' => $display_title,
                'url' => $display_url,
                'description' => $display_desc,
            ],
            'score' => max(0, $score),
            'issues' => $issues,
            'suggestions' => $suggestions,
            'stats' => [
                'title_length' => $title_len,
                'url_length' => mb_strlen($display_url),
                'desc_length' => $desc_len,
            ],
        ];
    }

    // ==================== URL Auditor ====================
    public function handle_url_auditor() {
        check_ajax_referer('ssp_secure_nonce', 'security');
        if (!is_user_logged_in()) wp_send_json_error(['message' => 'غیرمجاز']);

        $url = esc_url_raw($_POST['url'] ?? '');
        if (empty($url)) {
            wp_send_json_error(['message' => 'آدرس URL را وارد کنید']);
        }

        $result = $this->audit_url($url);
        wp_send_json_success($result);
    }

    private function audit_url($url) {
        $issues = [];
        $strengths = [];
        $suggestions = [];
        $data = [];

        // SSRF Protection: Block private/internal IPs
        $parsed = parse_url($url);
        if (!$parsed || !isset($parsed['host'])) {
            return ['url' => $url, 'error' => 'URL نامعتبر', 'issues' => ['URL نامعتبر است'], 'strengths' => [], 'suggestions' => [], 'score' => 0];
        }

        $host = $parsed['host'];
        $ip = @gethostbyname($host);
        if ($ip && $ip !== $host) {
            $blocked_ranges = [
                ['127.0.0.0', '127.255.255.255'],   // Loopback
                ['10.0.0.0', '10.255.255.255'],      // Private Class A
                ['172.16.0.0', '172.31.255.255'],    // Private Class B
                ['192.168.0.0', '192.168.255.255'],  // Private Class C
                ['169.254.0.0', '169.254.255.255'],  // Link-local
            ];
            $ip_long = ip2long($ip);
            foreach ($blocked_ranges as [$start, $end]) {
                if ($ip_long >= ip2long($start) && $ip_long <= ip2long($end)) {
                    return [
                        'url' => $url,
                        'error' => 'دسترسی به IP خصوصی مسدود شده',
                        'issues' => ['درخواست به IP خصوصی/داخلی مجاز نیست (SSRF Protection)'],
                        'strengths' => [],
                        'suggestions' => [],
                        'score' => 0,
                    ];
                }
            }
        }

        $proxy_args = $this->get_proxy_args();
        $args = array_merge([
            'timeout' => 15,
            'headers' => [
                'User-Agent' => 'Mozilla/5.0 (compatible; SSP-SEO-Auditor/1.0)',
            ],
        ], $proxy_args);

        $response = wp_remote_get($url, $args);

        if (is_wp_error($response)) {
            return [
                'url' => $url,
                'error' => $response->get_error_message(),
                'issues' => ['دسترسی به URL ممکن نیست'],
                'strengths' => [],
                'suggestions' => ['مطمئن شوید URL صحیح و قابل دسترسی است'],
                'score' => 0,
            ];
        }

        $code = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);

        $data['status_code'] = $code;
        $data['content_length'] = mb_strlen($body);

        if ($code !== 200) {
            $issues[] = 'کد وضعیت HTTP: ' . $code;
        } else {
            $strengths[] = 'صفحه با موفقیت بارگذاری شده (200 OK)';
        }

        // Parse HTML
        $doc = new DOMDocument();
        @$doc->loadHTML('<?xml encoding="UTF-8">' . $body, LIBXML_NOERROR | LIBXML_NOWARNING);

        // Title
        $title_nodes = $doc->getElementsByTagName('title');
        $title = $title_nodes->length > 0 ? trim($title_nodes->item(0)->textContent) : '';
        $data['title'] = $title;

        if (empty($title)) {
            $issues[] = 'تگ title وجود ندارد';
        } else {
            $title_len = mb_strlen($title);
            if ($title_len > 60) {
                $issues[] = 'تگ title بیشتر از 60 کاراکتر است (' . $title_len . ')';
            } elseif ($title_len < 30) {
                $suggestions[] = 'تگ title خیلی کوتاه است';
            } else {
                $strengths[] = 'تگ title مناسب است (' . $title_len . ' کاراکتر)';
            }
        }

        // Meta description
        $meta_desc = '';
        $metas = $doc->getElementsByTagName('meta');
        for ($i = 0; $i < $metas->length; $i++) {
            $meta = $metas->item($i);
            if ($meta->getAttribute('name') === 'description') {
                $meta_desc = trim($meta->getAttribute('content'));
                break;
            }
        }
        $data['meta_description'] = $meta_desc;

        if (empty($meta_desc)) {
            $issues[] = 'تگ meta description وجود ندارد';
        } else {
            $desc_len = mb_strlen($meta_desc);
            if ($desc_len > 155) {
                $issues[] = 'meta description بیشتر از 155 کاراکتر است';
            } elseif ($desc_len < 70) {
                $suggestions[] = 'meta description خیلی کوتاه است';
            } else {
                $strengths[] = 'meta description مناسب است';
            }
        }

        // Headings
        $headings = [];
        foreach (['h1', 'h2', 'h3', 'h4', 'h5', 'h6'] as $tag) {
            $nodes = $doc->getElementsByTagName($tag);
            if ($nodes->length > 0) {
                $headings[$tag] = $nodes->length;
            }
        }
        $data['headings'] = $headings;

        if (empty($headings['h1'])) {
            $issues[] = 'تگ H1 وجود ندارد';
        } elseif ($headings['h1'] > 1) {
            $issues[] = 'بیش از یک تگ H1 وجود دارد (' . $headings['h1'] . ' عدد)';
        } else {
            $strengths[] = 'تک تگ H1 وجود دارد';
        }

        if (empty($headings['h2'])) {
            $suggestions[] = 'از تگ‌های H2 برای ساختار محتوا استفاده کنید';
        } else {
            $strengths[] = $headings['h2'] . ' تگ H2 وجود دارد';
        }

        // Images
        $images = $doc->getElementsByTagName('img');
        $total_images = $images->length;
        $images_with_alt = 0;
        $images_without_alt = 0;
        for ($i = 0; $i < $total_images; $i++) {
            $alt = $images->item($i)->getAttribute('alt');
            if (!empty(trim($alt))) {
                $images_with_alt++;
            } else {
                $images_without_alt++;
            }
        }
        $data['images'] = ['total' => $total_images, 'with_alt' => $images_with_alt, 'without_alt' => $images_without_alt];

        if ($total_images > 0 && $images_without_alt > 0) {
            $issues[] = $images_without_alt . ' تصویر بدون alt text وجود دارد';
        } elseif ($total_images > 0) {
            $strengths[] = 'همه تصاویر دارای alt text هستند';
        }

        // Links
        $links = $doc->getElementsByTagName('a');
        $total_links = $links->length;
        $internal_links = 0;
        $external_links = 0;
        $parsed_host = parse_url($url, PHP_URL_HOST);
        for ($i = 0; $i < $total_links; $i++) {
            $href = $links->item($i)->getAttribute('href');
            if (empty($href) || $href === '#') continue;
            $link_host = parse_url($href, PHP_URL_HOST);
            if ($link_host === $parsed_host || empty($link_host)) {
                $internal_links++;
            } else {
                $external_links++;
            }
        }
        $data['links'] = ['total' => $total_links, 'internal' => $internal_links, 'external' => $external_links];

        if ($total_links === 0) {
            $suggestions[] = 'صفحه لینک داخلی ندارد';
        } else {
            if ($internal_links > 0) $strengths[] = $internal_links . ' لینک داخلی وجود دارد';
            if ($external_links > 0) $strengths[] = $external_links . ' لینک خارجی وجود دارد';
        }

        // Open Graph
        $og_tags = [];
        for ($i = 0; $i < $metas->length; $i++) {
            $meta = $metas->item($i);
            $prop = $meta->getAttribute('property');
            if (strpos($prop, 'og:') === 0) {
                $og_tags[$prop] = $meta->getAttribute('content');
            }
        }
        $data['open_graph'] = $og_tags;

        if (empty($og_tags['og:title']) && empty($og_tags['og:description'])) {
            $suggestions[] = 'تگ‌های Open Graph وجود ندارند - برای اشتراک‌گذاری در شبکه‌های اجتماعی مفید است';
        } else {
            $strengths[] = 'تگ‌های Open Graph تنظیم شده‌اند';
        }

        // Canonical
        $canonical = '';
        for ($i = 0; $i < $metas->length; $i++) {
            $meta = $metas->item($i);
            if ($meta->getAttribute('rel') === 'canonical') {
                $canonical = $meta->getAttribute('href');
                break;
            }
        }
        $data['canonical'] = $canonical;

        if (empty($canonical)) {
            $suggestions[] = 'تگ canonical وجود ندارد - برای جلوگیری از محتوای تکراری مفید است';
        }

        // Schema/Structured Data
        $scripts = $doc->getElementsByTagName('script');
        $has_schema = false;
        for ($i = 0; $i < $scripts->length; $i++) {
            $type = $scripts->item($i)->getAttribute('type');
            if ($type === 'application/ld+json') {
                $has_schema = true;
                break;
            }
        }
        $data['has_schema'] = $has_schema;

        if (!$has_schema) {
            $suggestions[] = 'Schema Markup (JSON-LD) وجود ندارد';
        } else {
            $strengths[] = 'Schema Markup وجود دارد';
        }

        // Mobile viewport
        $has_viewport = false;
        for ($i = 0; $i < $metas->length; $i++) {
            $meta = $metas->item($i);
            if ($meta->getAttribute('name') === 'viewport') {
                $has_viewport = true;
                break;
            }
        }
        $data['has_viewport'] = $has_viewport;

        if (!$has_viewport) {
            $issues[] = 'تگ viewport وجود ندارد - برای موبایل ضروری است';
        } else {
            $strengths[] = 'تگ viewport تنظیم شده';
        }

        // Score
        $score = 100;
        $score -= count($issues) * 8;
        $score -= count($suggestions) * 3;

        return [
            'url' => $url,
            'data' => $data,
            'score' => max(0, min(100, $score)),
            'issues' => $issues,
            'strengths' => $strengths,
            'suggestions' => $suggestions,
        ];
    }

    // ==================== GEO Analyzer (Generative Engine Optimization) ====================
    public function handle_geo_analyze() {
        check_ajax_referer('ssp_secure_nonce', 'security');
        if (!is_user_logged_in()) wp_send_json_error(['message' => 'غیرمجاز']);

        $title = sanitize_text_field($_POST['title'] ?? '');
        $content = sanitize_textarea_field($_POST['content'] ?? '');
        $meta_desc = sanitize_textarea_field($_POST['meta_desc'] ?? '');

        if (empty($content)) {
            wp_send_json_error(['message' => 'محتوا را وارد کنید']);
        }

        $result = $this->analyze_geo($title, $content, $meta_desc);
        wp_send_json_success($result);
    }

    private function analyze_geo($title, $content, $meta_desc = '') {
        $clean = strip_tags($content);
        $clean = preg_replace('/\s+/', ' ', trim($clean));
        $word_count = str_word_count($clean);
        $char_count = mb_strlen($clean);

        $scores = [];
        $issues = [];
        $strengths = [];
        $suggestions = [];

        // === 1. Definition-First Structure (20 points) ===
        // AI engines prefer content that starts with a clear definition
        $definition_score = 20;
        $first_sentence = '';
        $sentences = preg_split('/[.!?؟]+/', $clean, -1, PREG_SPLIT_NO_EMPTY);
        if (!empty($sentences)) {
            $first_sentence = trim($sentences[0]);
        }

        // Check if content starts with a definition pattern
        $definition_patterns = [
            '/^[\w\s]+ (چیست|چیه|عبارت است از|به (عنوان|معنی)|تعریف|به طور)/i',
            '/^(هوش مصنوعی|blockchain|SEO|GEO)/i',
            '/^[A-Za-z]+ /',  // English term followed by explanation
        ];
        $has_definition = false;
        foreach ($definition_patterns as $pattern) {
            if (preg_match($pattern, $first_sentence)) {
                $has_definition = true;
                break;
            }
        }

        // Check if first sentence is short and clear (ideal for AI extraction)
        if (mb_strlen($first_sentence) > 10 && mb_strlen($first_sentence) < 200) {
            $strengths[] = 'جمله اول مناسب و کوتاه است (' . mb_strlen($first_sentence) . ' کاراکتر)';
            $definition_score += 3;
        } elseif (mb_strlen($first_sentence) >= 200) {
            $issues[] = 'جمله اول خیلی طولانی است - AI معمولاً 100-200 کاراکتر اول را استخراج می‌کند';
            $definition_score -= 5;
        }

        // Check for "is" definition pattern (X is Y)
        if (preg_match('/\b(is|are|was|هست|است|عبارت است)\b/i', mb_substr($clean, 0, 300))) {
            $strengths[] = 'محتوا شامل تعریف واضح است';
            $definition_score += 3;
        } else {
            $suggestions[] = 'محتوا با تعریف واضح شروع نمی‌شود. الگو: "X یک Y است که Z..."';
            $definition_score -= 3;
        }

        $scores['definition'] = max(0, min(20, $definition_score));

        // === 2. Citation-Worthy Content (20 points) ===
        $citation_score = 20;

        // Statistics and numbers
        $has_statistics = preg_match('/\d+[\.\,]?\d*\s*(درصد|٪|٪|بیش از|کمتر از|حدود| Approximately)/i', $clean);
        $has_numbers = preg_match('/\d+/', $clean);
        if ($has_statistics) {
            $strengths[] = 'محتوا شامل آمار و ارقام است - AI علاقه‌مند به استناد به آمار است';
            $citation_score += 5;
        } elseif ($has_numbers) {
            $strengths[] = 'محتوا شامل اعداد است';
            $citation_score += 2;
        } else {
            $suggestions[] = 'محتوا آمار و ارقام ندارد. اضافه کردن آمار استنادپذیری را افزایش می‌دهد';
            $citation_score -= 3;
        }

        // Expert quotes / citations
        $has_quotes = preg_match('/[""«»]|(گفته|اظهار داشت|بر اساس|طبق تحقیقات|研究|مطالعات نشان)/i', $clean);
        if ($has_quotes) {
            $strengths[] = 'محتوا شامل نقل‌قول یا استناد است';
            $citation_score += 4;
        } else {
            $suggestions[] = 'استناد به متخصصان یا تحقیقات استنادپذیری را افزایش می‌دهد';
            $citation_score -= 2;
        }

        // References to external sources
        $has_references = preg_match('/(منبع|مرجع|source|reference|لینک|http)/i', $clean);
        if ($has_references) {
            $strengths[] = 'محتوا شامل مراجع خارجی است';
            $citation_score += 3;
        }

        // Year/date mention (freshness signal)
        $has_year = preg_match('/(۱۴۰[0-9]|۱۴۱[0-9]|202[0-9]|203[0-9])/', $clean);
        if ($has_year) {
            $strengths[] = 'محتوا شامل سال/تاریخ است - نشانه تازگی';
            $citation_score += 2;
        } else {
            $suggestions[] = 'سال یا تاریخ به‌روز اضافه کنید تا محتوا تازه‌تر به نظر برسد';
            $citation_score -= 1;
        }

        $scores['citation'] = max(0, min(20, $citation_score));

        // === 3. Structured for AI Extraction (15 points) ===
        $structure_score = 15;

        // Clear headings
        $heading_count = preg_match_all('/(<h[1-6][^>]*>|^#{1,6}\s)/m', $content);
        if ($heading_count >= 3) {
            $strengths[] = 'محتوا ساختار عنوان‌بندی خوبی دارد (' . $heading_count . ' عنوان)';
            $structure_score += 4;
        } elseif ($heading_count === 0) {
            $issues[] = 'محتوا تگ عنوان ندارد - AI از عنوان‌ها برای درک ساختار استفاده می‌کند';
            $structure_score -= 5;
        } else {
            $suggestions[] = 'تعداد عنوان‌ها کم است. حداقل 3 عنوان H2/H3 اضافه کنید';
            $structure_score -= 2;
        }

        // Bullet points / lists
        $has_lists = preg_match('/(<li|<ul|<ol|^[-*•]\s|^۱|^۲|^۳)/m', $content);
        if ($has_lists) {
            $strengths[] = 'محتوا شامل لیست است - AI لیست‌ها را راحت‌تر استخراج می‌کند';
            $structure_score += 4;
        } else {
            $suggestions[] = 'محتوا لیست ندارد. لیست‌ها توسط AI بهتر استخراج می‌شوند';
            $structure_score -= 3;
        }

        // Short paragraphs
        $paragraphs = preg_split('/\n\s*\n/', $clean);
        $avg_para_len = $word_count / max(count(array_filter($paragraphs)), 1);
        if ($avg_para_len <= 50) {
            $strengths[] = 'پاراگراف‌ها کوتاه و مناسب هستند';
            $structure_score += 3;
        } elseif ($avg_para_len > 100) {
            $suggestions[] = 'پاراگراف‌ها خیلی طولانی هستند. پاراگراف‌های کوتاه‌تر (2-3 جمله) بهتر استخراج می‌شوند';
            $structure_score -= 3;
        }

        $scores['structure'] = max(0, min(15, $structure_score));

        // === 4. E-E-A-T Signals (15 points) ===
        $eeat_score = 15;

        // Experience signals
        $experience_words = ['تجربه', 'تجربه کردم', 'امتحان کردم', 'دیدم', 'متوجه شدم', 'در عمل', 'واقعی', '实战'];
        foreach ($experience_words as $word) {
            if (mb_stripos($clean, $word) !== false) {
                $strengths[] = 'محتوا شامل تجربه عملی است (Experience)';
                $eeat_score += 3;
                break;
            }
        }

        // Expertise signals
        $expertise_words = ['طبق تحقیقات', 'مطالعات نشان', 'تحقیقات علمی', 'آمار رسمی', 'گزارش', 'بر اساس'];
        foreach ($expertise_words as $word) {
            if (mb_stripos($clean, $word) !== false) {
                $strengths[] = 'محتوا شامل استناد تحقیقاتی است (Expertise)';
                $eeat_score += 3;
                break;
            }
        }

        // Authority signals
        $authority_words = ['متخصص', 'کارشناس', 'حرفه‌ای', 'سابقه', 'تجربه سال‌ها', 'تخصص'];
        foreach ($authority_words as $word) {
            if (mb_stripos($clean, $word) !== false) {
                $strengths[] = 'محتوا شامل اشاره به تخصص است (Authoritativeness)';
                $eeat_score += 2;
                break;
            }
        }

        // Trust signals
        $trust_words = ['تضمین', 'гаранти', 'betahit', 'ضمانت', 'قابل اعتماد', 'معتبر', 'رسمی', 'official'];
        foreach ($trust_words as $word) {
            if (mb_stripos($clean, $word) !== false) {
                $strengths[] = 'محتوا شامل سیگنال اعتماد است (Trustworthiness)';
                $eeat_score += 2;
                break;
            }
        }

        // Personal experience (first person)
        if (preg_match('/(من |ما |بنده|اینجانب|این نویسنده)/u', $clean)) {
            $strengths[] = 'محتوا شامل تجربه شخصی است - سیگنال قوی E-E-A-T';
            $eeat_score += 3;
        } else {
            $suggestions[] = 'اضافه کردن تجربه شخصی (اول شخص) سیگنال E-E-A-T را تقویت می‌کند';
        }

        $scores['eeat'] = max(0, min(15, $eeat_score));

        // === 5. Question-Answer Format (15 points) ===
        $qa_score = 15;

        // Questions in content
        $question_count = preg_match_all('/[؟\?]/', $content);
        if ($question_count >= 3) {
            $strengths[] = 'محتوا شامل ' . $question_count . ' سوال است - فرمت محبوب AI';
            $qa_score += 5;
        } elseif ($question_count >= 1) {
            $qa_score += 2;
        } else {
            $suggestions[] = 'محتوا سوال ندارد. فرمت سوال-جواب توسط AI ترجیح داده می‌شود';
            $qa_score -= 3;
        }

        // FAQ-style content
        $faq_patterns = ['سوالات متداول', 'FAQ', 'پرسش و پاسخ', 'Questions'];
        foreach ($faq_patterns as $pat) {
            if (mb_stripos($clean, $pat) !== false) {
                $strengths[] = 'محتوا شامل بخش سوالات متداول است';
                $qa_score += 4;
                break;
            }
        }

        // How-to format
        $howto_patterns = ['مراحل', 'گام به گام', 'قدم', '步骤', 'how to', 'چطور', 'چگونه'];
        foreach ($howto_patterns as $pat) {
            if (mb_stripos($clean, $pat) !== false) {
                $strengths[] = 'محتوا شامل فرمت آموزشی (How-to) است';
                $qa_score += 3;
                break;
            }
        }

        $scores['qa_format'] = max(0, min(15, $qa_score));

        // === 6. Content Completeness (15 points) ===
        $completeness_score = 15;

        // Word count for GEO
        if ($word_count >= 300 && $word_count <= 2000) {
            $strengths[] = 'طول محتوا مناسب است (' . $word_count . ' کلمه)';
            $completeness_score += 3;
        } elseif ($word_count < 300) {
            $issues[] = 'محتوا خیلی کوتاه است (' . $word_count . ' کلمه). محتوای 300-2000 کلمه برای AI بهینه است';
            $completeness_score -= 5;
        } elseif ($word_count > 2000) {
            $suggestions[] = 'محتوا طولانی است (' . $word_count . ' کلمه). بخش‌بندی کنید';
            $completeness_score -= 2;
        }

        // Summary/conclusion
        $has_conclusion = preg_match('/(نتیجه|نتیجه‌گیری|جمع‌بندی|در نهایت|به طور خلاصه|summary|conclusion)/i', $clean);
        if ($has_conclusion) {
            $strengths[] = 'محتوا شامل بخش نتیجه‌گیری است';
            $completeness_score += 3;
        } else {
            $suggestions[] = 'بخش نتیجه‌گیری اضافه کنید';
            $completeness_score -= 2;
        }

        // Internal linking hints
        $has_links = preg_match('/https?:\/\/\S+/', $content);
        if ($has_links) {
            $strengths[] = 'محتوا شامل لینک است';
            $completeness_score += 2;
        }

        // Image/media
        if (preg_match('/<img/i', $content) || preg_match('/<video/i', $content)) {
            $strengths[] = 'محتوا شامل رسانه (تصویر/ویدیو) است';
            $completeness_score += 2;
        } else {
            $suggestions[] = 'اضافه کردن تصویر یا ویدیو تجربه کاربری را بهبود می‌دهد';
        }

        $scores['completeness'] = max(0, min(15, $completeness_score));

        // === Final GEO Score ===
        $geo_score = array_sum($scores);

        // GEO-specific suggestions
        $geo_suggestions = [];
        if ($geo_score < 50) {
            $geo_suggestions[] = 'محتوا برای موتورهای جستجوی AI بهینه نیست';
            $geo_suggestions[] = 'با تعریف واضح شروع کنید: "X یک Y است که Z..."';
            $geo_suggestions[] = 'آمار و ارقام واقعی اضافه کنید';
            $geo_suggestions[] = 'از لیست و bullet point استفاده کنید';
            $geo_suggestions[] = 'بخش سوالات متداول (FAQ) اضافه کنید';
        } elseif ($geo_score < 75) {
            $geo_suggestions[] = 'محتوا نسبتاً خوب است ولی قابل بهبود';
            $geo_suggestions[] = 'استناد به منابع معتبر اضافه کنید';
            $geo_suggestions[] = 'تجربه شخصی بیشتری بنویسید';
        } else {
            $geo_suggestions[] = 'محتوا برای AI بهینه است';
        }

        return [
            'geo_score' => $geo_score,
            'scores' => $scores,
            'issues' => $issues,
            'strengths' => $strengths,
            'suggestions' => array_merge($suggestions, $geo_suggestions),
            'stats' => [
                'word_count' => $word_count,
                'char_count' => $char_count,
                'sentence_count' => count($sentences),
                'question_count' => $question_count,
            ],
        ];
    }

    // ==================== Schema Markup Generator ====================
    public function handle_schema_generator() {
        check_ajax_referer('ssp_secure_nonce', 'security');
        if (!is_user_logged_in()) wp_send_json_error(['message' => 'غیرمجاز']);

        $type = sanitize_text_field($_POST['type'] ?? 'article');
        $data = [];
        foreach ($_POST as $key => $value) {
            if ($key !== 'action' && $key !== 'security') {
                if (is_array($value)) {
                    $data[$key] = array_map('sanitize_text_field', $value);
                } else {
                    $data[$key] = sanitize_text_field($value);
                }
            }
        }

        // Parse JSON-encoded arrays from JavaScript
        foreach (['questions', 'steps', 'items', 'social'] as $key) {
            if (!empty($data[$key]) && is_string($data[$key])) {
                $decoded = json_decode($data[$key], true);
                if (is_array($decoded)) {
                    $data[$key] = $decoded;
                }
            }
        }

        $result = $this->generate_schema($type, $data);
        wp_send_json_success($result);
    }

    private function generate_schema($type, $data) {
        $schema = [];

        switch ($type) {
            case 'article':
                $schema = [
                    '@context' => 'https://schema.org',
                    '@type' => 'Article',
                    'headline' => $data['title'] ?? '',
                    'description' => $data['description'] ?? '',
                    'author' => ['@type' => 'Person', 'name' => $data['author'] ?? ''],
                    'datePublished' => $data['date_published'] ?? date('Y-m-d'),
                    'dateModified' => $data['date_modified'] ?? date('Y-m-d'),
                ];
                if (!empty($data['image'])) {
                    $schema['image'] = $data['image'];
                }
                if (!empty($data['url'])) {
                    $schema['url'] = $data['url'];
                }
                break;

            case 'faq':
                $schema = [
                    '@context' => 'https://schema.org',
                    '@type' => 'FAQPage',
                    'mainEntity' => [],
                ];
                if (!empty($data['questions']) && is_array($data['questions'])) {
                    foreach ($data['questions'] as $qa) {
                        $schema['mainEntity'][] = [
                            '@type' => 'Question',
                            'name' => $qa['question'] ?? '',
                            'acceptedAnswer' => [
                                '@type' => 'Answer',
                                'text' => $qa['answer'] ?? '',
                            ],
                        ];
                    }
                }
                break;

            case 'howto':
                $schema = [
                    '@context' => 'https://schema.org',
                    '@type' => 'HowTo',
                    'name' => $data['title'] ?? '',
                    'description' => $data['description'] ?? '',
                    'step' => [],
                ];
                if (!empty($data['steps']) && is_array($data['steps'])) {
                    foreach ($data['steps'] as $i => $step) {
                        $schema['step'][] = [
                            '@type' => 'HowToStep',
                            'position' => $i + 1,
                            'name' => $step['name'] ?? '',
                            'text' => $step['text'] ?? '',
                        ];
                    }
                }
                break;

            case 'product':
                $schema = [
                    '@context' => 'https://schema.org',
                    '@type' => 'Product',
                    'name' => $data['name'] ?? '',
                    'description' => $data['description'] ?? '',
                ];
                if (!empty($data['price'])) {
                    $schema['offers'] = [
                        '@type' => 'Offer',
                        'price' => $data['price'],
                        'priceCurrency' => $data['currency'] ?? 'IRR',
                        'availability' => 'https://schema.org/InStock',
                    ];
                }
                if (!empty($data['image'])) {
                    $schema['image'] = $data['image'];
                }
                break;

            case 'organization':
                $schema = [
                    '@context' => 'https://schema.org',
                    '@type' => 'Organization',
                    'name' => $data['name'] ?? '',
                    'url' => $data['url'] ?? '',
                ];
                if (!empty($data['logo'])) {
                    $schema['logo'] = $data['logo'];
                }
                if (!empty($data['social'])) {
                    $schema['sameAs'] = array_values($data['social']);
                }
                break;

            case 'localbusiness':
                $schema = [
                    '@context' => 'https://schema.org',
                    '@type' => 'LocalBusiness',
                    'name' => $data['name'] ?? '',
                    'address' => [
                        '@type' => 'PostalAddress',
                        'streetAddress' => $data['street'] ?? '',
                        'addressLocality' => $data['city'] ?? '',
                        'addressCountry' => $data['country'] ?? 'IR',
                    ],
                ];
                if (!empty($data['phone'])) {
                    $schema['telephone'] = $data['phone'];
                }
                break;

            case 'breadcrumb':
                $schema = [
                    '@context' => 'https://schema.org',
                    '@type' => 'BreadcrumbList',
                    'itemListElement' => [],
                ];
                if (!empty($data['items']) && is_array($data['items'])) {
                    foreach ($data['items'] as $i => $item) {
                        $schema['itemListElement'][] = [
                            '@type' => 'ListItem',
                            'position' => $i + 1,
                            'name' => $item['name'] ?? '',
                            'item' => $item['url'] ?? '',
                        ];
                    }
                }
                break;

            case 'video':
                $schema = [
                    '@context' => 'https://schema.org',
                    '@type' => 'VideoObject',
                    'name' => $data['title'] ?? '',
                    'description' => $data['description'] ?? '',
                    'uploadDate' => $data['upload_date'] ?? date('Y-m-d'),
                ];
                if (!empty($data['thumbnail'])) {
                    $schema['thumbnailUrl'] = $data['thumbnail'];
                }
                if (!empty($data['duration'])) {
                    $schema['duration'] = $data['duration'];
                }
                break;

            default:
                $schema = [
                    '@context' => 'https://schema.org',
                    '@type' => 'WebPage',
                    'name' => $data['title'] ?? '',
                    'description' => $data['description'] ?? '',
                ];
        }

        $json_ld = json_encode($schema, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP);

        return [
            'type' => $type,
            'json_ld' => $json_ld,
            'html' => '<script type="application/ld+json">' . "\n" . $json_ld . "\n" . '</script>',
            'preview' => $schema,
        ];
    }

    // ==================== E-E-A-T Analyzer ====================
    public function handle_eeat_analyze() {
        check_ajax_referer('ssp_secure_nonce', 'security');
        if (!is_user_logged_in()) wp_send_json_error(['message' => 'غیرمجاز']);

        $title = sanitize_text_field($_POST['title'] ?? '');
        $content = sanitize_textarea_field($_POST['content'] ?? '');
        $author = sanitize_text_field($_POST['author'] ?? '');

        if (empty($content)) {
            wp_send_json_error(['message' => 'محتوا را وارد کنید']);
        }

        $result = $this->analyze_eeat($title, $content, $author);
        wp_send_json_success($result);
    }

    private function analyze_eeat($title, $content, $author = '') {
        $clean = strip_tags($content);
        $clean = preg_replace('/\s+/', ' ', trim($clean));

        $scores = [
            'experience' => 0,
            'expertise' => 0,
            'authoritativeness' => 0,
            'trustworthiness' => 0,
        ];
        $details = [];

        // === Experience (0-25) ===
        $exp_indicators = [
            'personally' => [' personally', ' personally tested', ' من خودم', ' personally used', ' در تجربه من', ' I found', ' من متوجه شدم'],
            'first_person' => ['/من /u', '/ما /u', '/بنده /u', '/اینجانب /u'],
            'years' => ['/\d+\s*سال\s*تجربه/i', '/\d+\s*years?\s*experience/i'],
            'testing' => ['تست کردم', 'امتحان کردم', 'آزمایش', 'تست شده', 'tested', 'reviewed'],
            'before_after' => ['قبل و بعد', 'before and after', 'نتیجه', 'result'],
        ];

        foreach ($exp_indicators as $type => $patterns) {
            foreach ($patterns as $pattern) {
                if (is_string($pattern) && mb_stripos($clean, $pattern) !== false) {
                    $scores['experience'] += 5;
                    $details['experience'][] = 'سیگنال تجربه: ' . $pattern;
                    break;
                } elseif ($pattern[0] === '/' && preg_match($pattern, $clean)) {
                    $scores['experience'] += 5;
                    $details['experience'][] = 'سیگنال تجربه: regex match';
                    break;
                }
            }
        }
        $scores['experience'] = min(25, $scores['experience']);

        if ($scores['experience'] < 10) {
            $details['experience_tips'] = [
                'از زبان اول شخص استفاده کنید (من، ما)',
                'تجربه عملی خود را شرح دهید',
                'قبل و بعد را نشان دهید',
                'نتایج تست واقعی اضافه کنید',
            ];
        }

        // === Expertise (0-25) ===
        $expert_indicators = [
            'technical_terms' => ['/\b[A-Z]{2,}\b/', '/\b(ROI|CTR|CPC|CVR|LTV|CAC|KPI|API|SDK|HTML|CSS|SEO|GEO)\b/i'],
            'deep_knowledge' => ['طبق تحقیقات', 'مطالعات نشان', ' research shows', ' studies indicate', 'آمار رسمی', 'official data'],
            'citations' => ['/"[^"]{10,}"/', '/«[^»]{10,}»/'],
            'formulas' => ['/\d+\s*[\+\-\*\/\=]\s*\d+/', '/\d+%/'],
            'definitions' => ['عبارت است از', 'تعریف', 'definition', 'به طور ساده', 'به بیان ساده'],
        ];

        foreach ($expert_indicators as $type => $patterns) {
            foreach ($patterns as $pattern) {
                if (preg_match($pattern, $clean)) {
                    $scores['expertise'] += 5;
                    $details['expertise'][] = 'سیگنال تخصص: ' . $type;
                    break;
                }
            }
        }
        $scores['expertise'] = min(25, $scores['expertise']);

        // === Authoritativeness (0-25) ===
        $auth_indicators = [
            'author_name' => !empty($author),
            'brand_mentions' => preg_match_all('/[A-Z][a-z]+(?:\s[A-Z][a-z]+)+/', $clean) > 2,
            'external_links' => preg_match_all('/https?:\/\/\S+/', $content) > 0,
            'statistics' => preg_match('/\d+[\.\,]?\d*\s*(درصد|٪|%|barrier|ratio)/i', $clean),
            'references' => preg_match('/(منبع|مرجع|source|reference|According to)/i', $clean),
        ];

        foreach ($auth_indicators as $type => $check) {
            if ($check) {
                $scores['authoritativeness'] += 5;
                $details['authoritativeness'][] = 'سیگنال اعتبار: ' . $type;
            }
        }
        $scores['authoritativeness'] = min(25, $scores['authoritativeness']);

        // === Trustworthiness (0-25) ===
        $trust_indicators = [
            'https_links' => preg_match('/https:\/\//', $content),
            'citations' => preg_match('/(منبع|مرجع|source|reference)/i', $clean),
            'dates' => preg_match('/(۱۴۰[0-9]|۱۴۱[0-9]|202[0-9])/', $clean),
            'contact_info' => preg_match('/(تماس|contact|email|تلفن|phone)/i', $clean),
            'privacy' => preg_match('/(حریم خصوصی|privacy|-cookie)/i', $clean),
            'disclaimer' => preg_match('/(سلب مسئولیت|disclaimer|warning|هشدار)/i', $clean),
        ];

        foreach ($trust_indicators as $type => $check) {
            if ($check) {
                $scores['trustworthiness'] += 4;
                $details['trustworthiness'][] = 'سیگنال اعتماد: ' . $type;
            }
        }
        $scores['trustworthiness'] = min(25, $scores['trustworthiness']);

        // === Total Score ===
        $total = array_sum($scores);
        $level = '';
        if ($total >= 80) $level = 'عالی';
        elseif ($total >= 60) $level = 'خوب';
        elseif ($total >= 40) $level = 'متوسط';
        else $level = 'نیاز به بهبود';

        return [
            'total_score' => $total,
            'level' => $level,
            'scores' => $scores,
            'details' => $details,
            'tips' => [
                'experience' => $scores['experience'] < 15 ? 'تجربه شخصی بیشتری اضافه کنید' : 'سیگنال تجربه خوب است',
                'expertise' => $scores['expertise'] < 15 ? 'عمق تخصصی محتوا را افزایش دهید' : 'سطح تخصص مناسب است',
                'authoritativeness' => $scores['authoritativeness'] < 15 ? 'استناد به منابع معتبر اضافه کنید' : 'اعتبار محتوا خوب است',
                'trustworthiness' => $scores['trustworthiness'] < 15 ? 'سیگنال‌های اعتماد بیشتری اضافه کنید' : 'سیگنال اعتماد مناسب است',
            ],
        ];
    }

    // ==================== Content Gap Analysis ====================
    public function handle_content_gap() {
        check_ajax_referer('ssp_secure_nonce', 'security');
        if (!is_user_logged_in()) wp_send_json_error(['message' => 'غیرمجاز']);

        $title = sanitize_text_field($_POST['title'] ?? '');
        $content = sanitize_textarea_field($_POST['content'] ?? '');
        $target_keywords = sanitize_text_field($_POST['keywords'] ?? '');

        if (empty($content)) {
            wp_send_json_error(['message' => 'محتوا را وارد کنید']);
        }

        $result = $this->analyze_content_gap($title, $content, $target_keywords);
        wp_send_json_success($result);
    }

    private function analyze_content_gap($title, $content, $target_keywords) {
        $clean = strip_tags($content);
        $clean = preg_replace('/\s+/', ' ', trim($clean));

        $keywords = array_filter(explode(',', $target_keywords), function($k) { return !empty(trim($k)); });
        $found_keywords = [];
        $missing_keywords = [];

        foreach ($keywords as $keyword) {
            $kw = trim($keyword);
            $count = mb_substr_count(mb_strtolower($clean), mb_strtolower($kw));
            $found_keywords[] = [
                'keyword' => $kw,
                'found' => $count > 0,
                'count' => $count,
            ];
            if ($count === 0) {
                $missing_keywords[] = $kw;
            }
        }

        // Coverage percentage
        $coverage = count($keywords) > 0 ? round((count($found_keywords) - count($missing_keywords)) / count($keywords) * 100) : 0;

        // Suggestions for missing keywords
        $suggestions = [];
        foreach ($missing_keywords as $kw) {
            $suggestions[] = 'کلمه "' . $kw . '" در محتوا یافت نشد. آن را در عنوان، پاراگراف اول یا زیرعنوان اضافه کنید';
        }

        // Related topic suggestions
        $top_words = $this->extract_keywords($clean);
        $related_topics = [];
        foreach (array_slice($top_words, 0, 5) as $kw) {
            $related_topics[] = 'محتوا درباره "' . $kw['word'] . '" قوی است (' . $kw['count'] . ' بار)';
        }

        return [
            'coverage' => $coverage,
            'total_keywords' => count($keywords),
            'found' => count($found_keywords) - count($missing_keywords),
            'missing' => count($missing_keywords),
            'keywords' => $found_keywords,
            'missing_keywords' => $missing_keywords,
            'suggestions' => $suggestions,
            'related_topics' => $related_topics,
        ];
    }

    // ==================== Topic Clustering ====================
    public function handle_topic_cluster() {
        check_ajax_referer('ssp_secure_nonce', 'security');
        if (!is_user_logged_in()) wp_send_json_error(['message' => 'غیرمجاز']);

        $content = sanitize_textarea_field($_POST['content'] ?? '');
        if (empty($content)) {
            wp_send_json_error(['message' => 'محتوا را وارد کنید']);
        }

        $result = $this->analyze_topic_cluster($content);
        wp_send_json_success($result);
    }

    private function analyze_topic_cluster($content) {
        $clean = strip_tags($content);
        $clean = preg_replace('/\s+/', ' ', trim($clean));

        // Extract all meaningful words
        $tokens = preg_split('/[\s,\.!?؟:;\(\)\[\]\{\}\"\'<>\/\\|@#\$%^&*\-+=~`]+/', $clean, -1, PREG_SPLIT_NO_EMPTY);
        $word_freq = [];
        foreach ($tokens as $token) {
            $token = trim($token, '،.؟!.:;()[]{}"\'');
            if (mb_strlen($token) >= 3 && preg_match('/^[\x{0600}-\x{06FF}\x{067E}-\x{0686}\x{06A9}-\x{06AF}\x{06CC}\x{200C}]+$/u', $token) && !in_array($token, self::$_seo_stop_words)) {
                $word_freq[$token] = ($word_freq[$token] ?? 0) + 1;
            }
        }
        arsort($word_freq);
        $top_words = array_slice($word_freq, 0, 20, true);

        // Create topic clusters based on co-occurrence
        $clusters = [];
        $sentences = preg_split('/[.!?؟]+/', $clean, -1, PREG_SPLIT_NO_EMPTY);

        foreach ($top_words as $word => $count) {
            $cluster_words = [];
            foreach ($sentences as $sentence) {
                if (mb_stripos($sentence, $word) !== false) {
                    // Find other top words in the same sentence
                    foreach ($top_words as $other_word => $other_count) {
                        if ($other_word !== $word && mb_stripos($sentence, $other_word) !== false) {
                            $cluster_words[$other_word] = ($cluster_words[$other_word] ?? 0) + 1;
                        }
                    }
                }
            }
            arsort($cluster_words);
            $clusters[$word] = [
                'word' => $word,
                'frequency' => $count,
                'related' => array_slice(array_keys($cluster_words), 0, 5),
                'strength' => count($cluster_words),
            ];
        }

        // Calculate topic diversity
        $unique_topics = count(array_filter($clusters, function($c) { return $c['strength'] >= 2; }));

        // Content depth score
        $word_count = str_word_count($clean);
        $depth_score = min(100, $word_count / 10); // 100 words = score 10

        return [
            'clusters' => array_values(array_slice($clusters, 0, 10)),
            'total_topics' => count($clusters),
            'strong_topics' => $unique_topics,
            'depth_score' => $depth_score,
            'top_words' => array_map(function($word, $count) {
                return ['word' => $word, 'count' => $count];
            }, array_keys($top_words), $top_words),
        ];
    }

    // ==================== SEO Checklist Generator ====================
    public function handle_seo_checklist() {
        check_ajax_referer('ssp_secure_nonce', 'security');
        if (!is_user_logged_in()) wp_send_json_error(['message' => 'غیرمجاز']);

        $title = sanitize_text_field($_POST['title'] ?? '');
        $content = sanitize_textarea_field($_POST['content'] ?? '');
        $meta_desc = sanitize_textarea_field($_POST['meta_desc'] ?? '');
        $platform = sanitize_text_field($_POST['platform'] ?? 'general');

        $result = $this->generate_seo_checklist($title, $content, $meta_desc, $platform);
        wp_send_json_success($result);
    }

    private function generate_seo_checklist($title, $content, $meta_desc, $platform) {
        $clean = strip_tags($content);
        $clean = preg_replace('/\s+/', ' ', trim($clean));
        $title_len = mb_strlen($title);
        $content_len = mb_strlen($clean);
        $word_count = str_word_count($clean);

        $checklist = [];

        // Title checks
        $checklist[] = ['category' => 'عنوان', 'item' => 'عنوان خالی نیست', 'passed' => $title_len > 0, 'critical' => true];
        $checklist[] = ['category' => 'عنوان', 'item' => 'طول عنوان 30-60 کاراکتر', 'passed' => $title_len >= 30 && $title_len <= 60, 'critical' => true];
        $checklist[] = ['category' => 'عنوان', 'item' => 'کلمه کلیدی در عنوان وجود دارد', 'passed' => !empty($title), 'critical' => true];
        $checklist[] = ['category' => 'عنوان', 'item' => 'عنوان شامل عدد است', 'passed' => preg_match('/\d+/', $title) > 0, 'critical' => false];
        $checklist[] = ['category' => 'عنوان', 'item' => 'عنوان سوالی است', 'passed' => preg_match('/[؟\?]/', $title) > 0, 'critical' => false];

        // Content checks
        $checklist[] = ['category' => 'محتوا', 'item' => 'محتوا خالی نیست', 'passed' => !empty($clean), 'critical' => true];
        $checklist[] = ['category' => 'محتوا', 'item' => 'طول محتوا مناسب است (حداقل 300 کلمه)', 'passed' => $word_count >= 300, 'critical' => true];
        $checklist[] = ['category' => 'محتوا', 'item' => 'محتوا شامل لیست است', 'passed' => preg_match('/[-*•]\s|^\d+[\.\)]/m', $content) > 0, 'critical' => false];
        $checklist[] = ['category' => 'محتوا', 'item' => 'محتوا شامل سوال است', 'passed' => preg_match('/[؟\?]/', $content) > 0, 'critical' => false];
        $checklist[] = ['category' => 'محتوا', 'item' => 'محتوا شامل لینک خارجی است', 'passed' => preg_match('/https?:\/\/\S+/', $content) > 0, 'critical' => false];

        // Meta description checks
        if ($platform === 'website') {
            $meta_len = mb_strlen($meta_desc);
            $checklist[] = ['category' => 'Meta', 'item' => 'توضیحات meta وجود دارد', 'passed' => !empty($meta_desc), 'critical' => true];
            $checklist[] = ['category' => 'Meta', 'item' => 'طول توضیحات meta 70-155 کاراکتر', 'passed' => $meta_len >= 70 && $meta_len <= 155, 'critical' => true];
        }

        // Structure checks
        if ($platform === 'website') {
            $checklist[] = ['category' => 'ساختار', 'item' => 'تگ H1 وجود دارد', 'passed' => preg_match('/<h1/i', $content) > 0, 'critical' => true];
            $checklist[] = ['category' => 'ساختار', 'item' => 'تگ‌های H2/H3 وجود دارد', 'passed' => preg_match('/<h[23]/i', $content) > 0, 'critical' => true];
            $checklist[] = ['category' => 'ساختار', 'item' => 'تصاویر alt text دارند', 'passed' => true, 'critical' => false];
            $checklist[] = ['category' => 'ساختار', 'item' => 'Schema Markup وجود دارد', 'passed' => false, 'critical' => false];
        }

        // Engagement checks
        $checklist[] = ['category' => 'تعامل', 'item' => 'CTA وجود دارد', 'passed' => preg_match('/(کلیک|بزنید|مشاهده|دانلود|عضو|خرید|تماس|ثبتنام)/i', $clean) > 0, 'critical' => false];
        $checklist[] = ['category' => 'تعامل', 'item' => 'ایموجی مناسب استفاده شده', 'passed' => preg_match_all('/[\x{1F300}-\x{1F9FF}]/u', $content) <= 5, 'critical' => false];

        // Social media checks
        if ($platform !== 'website') {
            // Hashtag checks removed - hashtags are now optional
        }

        // Calculate score
        $total = count($checklist);
        $passed = count(array_filter($checklist, function($c) { return $c['passed']; }));
        $critical_total = count(array_filter($checklist, function($c) { return $c['critical']; }));
        $critical_passed = count(array_filter($checklist, function($c) { return $c['passed'] && $c['critical']; }));

        $score = $total > 0 ? round(($passed / $total) * 100) : 0;

        return [
            'checklist' => $checklist,
            'score' => $score,
            'passed' => $passed,
            'total' => $total,
            'critical_passed' => $critical_passed,
            'critical_total' => $critical_total,
        ];
    }

    // ==================== Featured Snippet Optimizer ====================
    public function handle_featured_snippet() {
        check_ajax_referer('ssp_secure_nonce', 'security');
        if (!is_user_logged_in()) wp_send_json_error(['message' => 'غیرمجاز']);

        $title = sanitize_text_field($_POST['title'] ?? '');
        $content = sanitize_textarea_field($_POST['content'] ?? '');

        if (empty($content)) {
            wp_send_json_error(['message' => 'محتوا را وارد کنید']);
        }

        $result = $this->optimize_featured_snippet($title, $content);
        wp_send_json_success($result);
    }

    private function optimize_featured_snippet($title, $content) {
        $clean = strip_tags($content);
        $clean = preg_replace('/\s+/', ' ', trim($clean));

        $sentences = preg_split('/[.!?؟]+/', $clean, -1, PREG_SPLIT_NO_EMPTY);
        $first_150 = mb_substr($clean, 0, 150);

        // Check for snippet-worthy formats
        $formats = [];

        // Definition format (X is Y)
        if (preg_match('/\b(is|are|هست|است|عبارت است)\b/i', $first_150)) {
            $formats[] = ['type' => 'تعریف', 'passed' => true, 'example' => $first_150];
        } else {
            $formats[] = ['type' => 'تعریف', 'passed' => false, 'suggestion' => 'محتوا با تعریف واضح شروع نکنید. الگو: "X یک Y است که Z..."'];
        }

        // List format
        if (preg_match('/[-*•]\s|^\d+[\.\)]/m', $content)) {
            $formats[] = ['type' => 'لیست', 'passed' => true];
        } else {
            $formats[] = ['type' => 'لیست', 'passed' => false, 'suggestion' => 'محتوا را به صورت لیست مرتب کنید'];
        }

        // Table format
        if (preg_match('/<table|<\/table>/i', $content)) {
            $formats[] = ['type' => 'جدول', 'passed' => true];
        } else {
            $formats[] = ['type' => 'جدول', 'passed' => false, 'suggestion' => 'داده‌ها را در جدول نمایش دهید'];
        }

        // Step-by-step format
        if (preg_match('/مرحله|گام|قدم|step/i', $clean)) {
            $formats[] = ['type' => 'گام‌به‌گام', 'passed' => true];
        } else {
            $formats[] = ['type' => 'گام‌به‌گام', 'passed' => false, 'suggestion' => 'مراحل را شماره‌گذاری کنید'];
        }

        // Question-answer format
        if (preg_match('/[؟\?]/', $content)) {
            $formats[] = ['type' => 'سوال-جواب', 'passed' => true];
        } else {
            $formats[] = ['type' => 'سوال-جواب', 'passed' => false, 'suggestion' => 'سوالات رایج را مطرح کنید'];
        }

        // Snippet length check (150-300 chars ideal)
        $snippet_length = mb_strlen($first_150);
        $snippet_score = 0;
        if ($snippet_length >= 100 && $snippet_length <= 300) {
            $snippet_score = 100;
        } elseif ($snippet_length > 0 && $snippet_length < 100) {
            $snippet_score = 50;
        } elseif ($snippet_length > 300) {
            $snippet_score = 70;
        }

        $passed_formats = count(array_filter($formats, function($f) { return $f['passed']; }));
        $total_formats = count($formats);

        return [
            'formats' => $formats,
            'snippet_length' => $snippet_length,
            'snippet_score' => $snippet_score,
            'first_150' => $first_150,
            'passed_formats' => $passed_formats,
            'total_formats' => $total_formats,
        ];
    }

    // ==================== Voice Search Optimizer ====================
    public function handle_voice_search() {
        check_ajax_referer('ssp_secure_nonce', 'security');
        if (!is_user_logged_in()) wp_send_json_error(['message' => 'غیرمجاز']);

        $content = sanitize_textarea_field($_POST['content'] ?? '');
        if (empty($content)) {
            wp_send_json_error(['message' => 'محتوا را وارد کنید']);
        }

        $result = $this->analyze_voice_search($content);
        wp_send_json_success($result);
    }

    private function analyze_voice_search($content) {
        $clean = strip_tags($content);
        $clean = preg_replace('/\s+/', ' ', trim($clean));

        $checks = [];
        $suggestions = [];

        // Conversational tone
        $has_conversational = preg_match('/(شما|تو|ما|شما می‌توانید|آیا|آیا می‌دانستید)/u', $clean);
        $checks['conversational'] = $has_conversational;
        if (!$has_conversational) {
            $suggestions[] = 'از زبان محاوره‌ای استفاده کنید (شما، ما، آیا)';
        }

        // Question patterns
        $question_count = preg_match_all('/[؟\?]/', $clean);
        $checks['questions'] = $question_count >= 3;
        if ($question_count < 3) {
            $suggestions[] = 'حداقل ۳ سوال در محتوا مطرح کنید';
        }

        // How-to format
        $has_howto = preg_match('/چطور|چگونه|how to|مراحل|گام به گام/i', $clean);
        $checks['howto'] = $has_howto;
        if (!$has_howto) {
            $suggestions[] = 'فرمت آموزشی (چطور/چگونه) اضافه کنید';
        }

        // Short answer sentences (voice search prefers 29 words or less)
        $sentences = preg_split('/[.!?؟]+/', $clean, -1, PREG_SPLIT_NO_EMPTY);
        $short_answers = 0;
        foreach ($sentences as $s) {
            if (str_word_count(trim($s)) <= 29) $short_answers++;
        }
        $short_answer_ratio = count($sentences) > 0 ? round($short_answers / count($sentences) * 100) : 0;
        $checks['short_answers'] = $short_answer_ratio >= 50;

        if ($short_answer_ratio < 50) {
            $suggestions[] = 'جملات کوتاه‌تر بنویسید (حداکثر ۲۹ کلمه)';
        }

        // Local signals
        $has_local = preg_match('/(اینجا|این منطقه|نزدیک|附近|near me|local)/i', $clean);
        $checks['local_signals'] = $has_local;

        // Natural language patterns
        $natural_patterns = ['بهترین', 'چطور', 'چگونه', 'چرا', 'کجاست', 'چیست', '是多少', '怎么', '什么'];
        $natural_count = 0;
        foreach ($natural_patterns as $p) {
            if (mb_stripos($clean, $p) !== false) $natural_count++;
        }
        $checks['natural_language'] = $natural_count >= 2;

        $score = 0;
        foreach ($checks as $check) {
            if ($check) $score += 20;
        }

        return [
            'score' => min(100, $score),
            'checks' => $checks,
            'suggestions' => $suggestions,
            'question_count' => $question_count,
            'short_answer_ratio' => $short_answer_ratio,
        ];
    }

    // ════════════════════════════════════════════════════════════════
    // Site Audit — Full Website Analysis with AI
    // ════════════════════════════════════════════════════════════════

    public function handle_site_audit() {
        check_ajax_referer('ssp_secure_nonce', 'security');
        $user_id = $this->ajax_require_auth();

        if ($this->get_user_plan($user_id) !== 'pro') {
            wp_send_json_error(['message' => 'این قابلیت فقط در پلن Pro موجود است']);
        }

        $url = esc_url_raw($_POST['url'] ?? '');
        if (empty($url) || !filter_var($url, FILTER_VALIDATE_URL)) {
            wp_send_json_error(['message' => 'آدرس URL معتبر نیست']);
        }

        // SSRF protection — block private/internal IPs
        $parsed = parse_url($url);
        $host = $parsed['host'] ?? '';
        $ip = gethostbyname($host);
        if ($ip && filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false) {
            wp_send_json_error(['message' => 'آدرس URL به سرورهای داخلی اشاره می‌کند']);
        }

        // Fetch the page
        $response = wp_remote_get($url, [
            'timeout' => 15,
            'user-agent' => 'Mozilla/5.0 (compatible; SSP-SiteAuditor/1.0)',
            'headers' => ['Accept' => 'text/html,application/xhtml+xml'],
        ]);

        if (is_wp_error($response)) {
            wp_send_json_error(['message' => 'خطا در دریافت صفحه: ' . $response->get_error_message()]);
        }

        $status_code = wp_remote_retrieve_response_code($response);
        if ($status_code < 200 || $status_code >= 400) {
            wp_send_json_error(['message' => "خطای HTTP $status_code: صفحه قابل دسترسی نیست"]);
        }

        $html = wp_remote_retrieve_body($response);

        // Parse HTML
        libxml_use_internal_errors(true);
        $dom = new DOMDocument();
        $dom->loadHTML('<?xml encoding="UTF-8">' . $html, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
        libxml_clear_errors();

        // Extract data
        $data = $this->extract_page_data($dom, $url);

        // Call AI API for analysis
        $provider = get_user_meta($user_id, 'ssp_ai_provider', true) ?: 'openai';
        $api_key = get_user_meta($user_id, 'ssp_ai_api_key', true);
        $model = get_user_meta($user_id, 'ssp_ai_model', true) ?: 'gpt-4o-mini';

        if (empty($api_key)) {
            wp_send_json_error(['message' => 'کلید API تنظیم نشده است. لطفاً ابتدا تنظیمات AI را تکمیل کنید.']);
        }

        $prompt_mode = sanitize_text_field($_POST['prompt_mode'] ?? '');
        $prompt = $this->build_site_audit_prompt($data, $prompt_mode);

        $ai_result = $this->call_ai_api($provider, $api_key, $model, $prompt, true);

        if (is_wp_error($ai_result)) {
            wp_send_json_error(['message' => 'خطا در فراخوانی AI: ' . $ai_result->get_error_message()]);
        }

        $parsed_ai = $this->parse_ai_json($ai_result['content']);

        if (!$parsed_ai) {
            wp_send_json_error(['message' => 'پاسخ AI قابل تفسیر نبود. لطفاً دوباره تلاش کنید.']);
        }

        wp_send_json_success($parsed_ai);
    }

    private function extract_page_data($dom, $url) {
        $data = ['url' => $url];

        // Title
        $title_nodes = $dom->getElementsByTagName('title');
        $data['title'] = $title_nodes->length > 0 ? trim($title_nodes->item(0)->textContent) : '';

        // Meta description
        $data['meta_description'] = '';
        foreach ($dom->getElementsByTagName('meta') as $meta) {
            if (strtolower($meta->getAttribute('name')) === 'description') {
                $data['meta_description'] = $meta->getAttribute('content');
                break;
            }
        }

        // Meta keywords
        $data['meta_keywords'] = '';
        foreach ($dom->getElementsByTagName('meta') as $meta) {
            if (strtolower($meta->getAttribute('name')) === 'keywords') {
                $data['meta_keywords'] = $meta->getAttribute('content');
                break;
            }
        }

        // Headings
        $data['headings'] = [];
        foreach (['h1', 'h2', 'h3', 'h4', 'h5', 'h6'] as $tag) {
            $nodes = $dom->getElementsByTagName($tag);
            foreach ($nodes as $node) {
                $text = trim($node->textContent);
                if (!empty($text)) {
                    $data['headings'][] = ['level' => strtoupper($tag), 'text' => $text];
                }
            }
        }

        // Body text
        $body = $dom->getElementsByTagName('body');
        $data['body_text'] = '';
        if ($body->length > 0) {
            $data['body_text'] = trim(preg_replace('/\s+/', ' ', $body->item(0)->textContent));
            $data['body_text'] = mb_substr($data['body_text'], 0, 3000);
        }

        // Images
        $images = $dom->getElementsByTagName('img');
        $data['images_total'] = $images->length;
        $data['images_without_alt'] = 0;
        foreach ($images as $img) {
            if (empty($img->getAttribute('alt'))) {
                $data['images_without_alt']++;
            }
        }

        // Links
        $links = $dom->getElementsByTagName('a');
        $data['links_internal'] = 0;
        $data['links_external'] = 0;
        $parsed_url = parse_url($url);
        $base_domain = $parsed_url['host'] ?? '';
        foreach ($links as $link) {
            $href = $link->getAttribute('href');
            if (empty($href) || $href[0] === '#') continue;
            $link_host = parse_url($href, PHP_URL_HOST);
            if ($link_host === $base_domain || $link_host === '') {
                $data['links_internal']++;
            } else {
                $data['links_external']++;
            }
        }

        // Open Graph
        $data['og'] = [];
        foreach ($dom->getElementsByTagName('meta') as $meta) {
            $prop = $meta->getAttribute('property');
            if (strpos($prop, 'og:') === 0) {
                $data['og'][$prop] = $meta->getAttribute('content');
            }
        }

        // Canonical
        $data['canonical'] = '';
        foreach ($dom->getElementsByTagName('link') as $link) {
            if (strtolower($link->getAttribute('rel')) === 'canonical') {
                $data['canonical'] = $link->getAttribute('href');
                break;
            }
        }

        // Schema/JSON-LD
        $data['schema'] = '';
        foreach ($dom->getElementsByTagName('script') as $script) {
            if ($script->getAttribute('type') === 'application/ld+json') {
                $data['schema'] = $script->textContent;
                break;
            }
        }

        // Viewport
        $data['viewport'] = '';
        foreach ($dom->getElementsByTagName('meta') as $meta) {
            if (strtolower($meta->getAttribute('name')) === 'viewport') {
                $data['viewport'] = $meta->getAttribute('content');
                break;
            }
        }

        // Robots
        $data['robots'] = '';
        foreach ($dom->getElementsByTagName('meta') as $meta) {
            if (strtolower($meta->getAttribute('name')) === 'robots') {
                $data['robots'] = $meta->getAttribute('content');
                break;
            }
        }

        return $data;
    }

    private function build_site_audit_prompt($data, $prompt_mode = '') {
        $headings_text = '';
        foreach ($data['headings'] as $h) {
            $headings_text .= $h['level'] . ': ' . $h['text'] . "\n";
        }

        $og_text = '';
        foreach ($data['og'] as $k => $v) {
            $og_text .= $k . ': ' . $v . "\n";
        }

        $prompt = "You are an expert SEO auditor and digital marketing strategist.\n\n";
        $prompt .= "TASK: Perform a comprehensive SEO audit of the following webpage.\n\n";
        $prompt .= "PAGE DATA:\n";
        $prompt .= "- URL: " . $data['url'] . "\n";
        $prompt .= "- Title: " . ($data['title'] ?: '(none)') . "\n";
        $prompt .= "- Meta Description: " . ($data['meta_description'] ?: '(none)') . "\n";
        $prompt .= "- Meta Keywords: " . ($data['meta_keywords'] ?: '(none)') . "\n";
        $prompt .= "- Headings:\n" . ($headings_text ?: '(none)') . "\n";
        $prompt .= "- Body Text (first 2000 chars): " . mb_substr($data['body_text'], 0, 2000) . "\n";
        $prompt .= "- Images: " . $data['images_total'] . " total, " . $data['images_without_alt'] . " without alt text\n";
        $prompt .= "- Links: " . $data['links_internal'] . " internal, " . $data['links_external'] . " external\n";
        $prompt .= "- Open Graph:\n" . ($og_text ?: '(none)') . "\n";
        $prompt .= "- Schema/JSON-LD: " . ($data['schema'] ?: '(none)') . "\n";
        $prompt .= "- Mobile Viewport: " . ($data['viewport'] ?: '(none)') . "\n";
        $prompt .= "- Robots Meta: " . ($data['robots'] ?: '(none)') . "\n\n";

        $prompt .= "ANALYZE AND PROVIDE:\n\n";
        $prompt .= "1. SEO_SCORE (0-100): Overall SEO health score\n";
        $prompt .= "2. PAGE_ANALYSIS: Detailed analysis of title, meta, headings, content structure\n";
        $prompt .= "3. STRENGTHS: What the page does well (list 3-5 items)\n";
        $prompt .= "4. WEAKNESSES: What needs improvement (list 3-5 items)\n";
        $prompt .= "5. IMPROVEMENTS: Specific actionable suggestions (list 5-8 items)\n";
        $prompt .= "6. CONTENT_IDEAS: 5 content ideas to improve topical authority and ranking\n";
        $prompt .= "7. RANKING_TRICKS: 5 practical SEO tricks specific to this page's niche\n";
        $prompt .= "8. AI_VISIBILITY: How to optimize for AI search results (SGE, ChatGPT search, Perplexity)\n";
        $prompt .= "9. TECHNICAL_ISSUES: Any technical SEO problems found\n";
        $prompt .= "10. COMPETITOR_INSIGHTS: What competitors might be doing better\n\n";

        $prompt .= "CRITICAL RULES:\n";
        $prompt .= "1. Return ONLY the raw JSON object. No explanations, no markdown, no text before or after.\n";
        $prompt .= "2. Do NOT wrap in code blocks.\n";
        $prompt .= "3. The JSON must be valid and parseable.\n";
        $prompt .= "4. All string values must be in Persian (فارسی).\n\n";

        $prompt .= "Return JSON ONLY:\n";
        $prompt .= '{"seo_score":0,"page_analysis":"","strengths":[""],"weaknesses":[""],"improvements":[""],"content_ideas":[""],"ranking_tricks":[""],"ai_visibility":[""],"technical_issues":[""],"competitor_insights":"","summary":""}';

        // Apply prompt mode if selected
        if (!empty($prompt_mode) && isset($this->prompt_modes[$prompt_mode])) {
            $prompt = $this->prompt_modes[$prompt_mode]['prefix'] . "\n\n" . $prompt;
        }

        return $prompt;
    }
}
