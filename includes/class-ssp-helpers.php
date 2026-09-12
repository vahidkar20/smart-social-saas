<?php
/**
 * SSP Helpers Trait - Storage, utility, and common operations
 */
trait SSP_Helpers {

    /* ============ Jalali Date Helpers (PHP) ============ */
    private function gregorian_to_jalali($gy, $gm, $gd) {
        $g_d_m = [0, 31, 59, 90, 120, 151, 181, 212, 243, 273, 304, 334];
        $gy2 = ($gm > 2) ? ($gy + 1) : $gy;
        $days = 355666 + (365 * $gy) + (int)(($gy2 + 3) / 4) - (int)(($gy2 + 99) / 100) + (int)(($gy2 + 399) / 400) + $gd + $g_d_m[$gm - 1];
        $jy = -1595 + (33 * (int)($days / 12053));
        $days %= 12053;
        $jy += 4 * (int)($days / 1461);
        $days %= 1461;
        if ($days > 365) { $jy += (int)(($days - 1) / 365); $days = ($days - 1) % 365; }
        if ($days < 186) { $jm = 1 + (int)($days / 31); $jd = 1 + ($days % 31); }
        else { $jm = 7 + (int)(($days - 186) / 30); $jd = 1 + (($days - 186) % 30); }
        return [$jy, $jm, $jd];
    }

    private function gregorian_to_jalali_str($date_str, $format = 'Y/m/d H:i') {
        if (empty($date_str)) return '';
        $ts = strtotime($date_str);
        if (!$ts) return $date_str;
        $gy = (int)date('Y', $ts);
        $gm = (int)date('n', $ts);
        $gd = (int)date('j', $ts);
        $j = $this->gregorian_to_jalali($gy, $gm, $gd);
        $months = ['01'=>'فروردین','02'=>'اردیبهشت','03'=>'خرداد','04'=>'تیر','05'=>'مرداد','06'=>'شهریور','07'=>'مهر','08'=>'آبان','09'=>'آذر','10'=>'دی','11'=>'بهمن','12'=>'اسفند'];
        $jm_str = sprintf('%02d', $j[1]);
        $jdate = $j[0] . '/' . sprintf('%02d', $j[1]) . '/' . sprintf('%02d', $j[2]);
        $jtime = date('H:i', $ts);
        return $jdate . ' ' . ($months[$jm_str] ?? '') . ' - ' . $jtime;
    }

    private function jalali_date_short($date_str) {
        if (empty($date_str)) return '';
        $ts = strtotime($date_str);
        if (!$ts) return $date_str;
        $j = $this->gregorian_to_jalali((int)date('Y', $ts), (int)date('n', $ts), (int)date('j', $ts));
        return $j[0] . '/' . sprintf('%02d', $j[1]) . '/' . sprintf('%02d', $j[2]);
    }

    /* ============ AJAX Auth Helpers ============ */
    private function ajax_require_auth() {
        check_ajax_referer('ssp_secure_nonce', 'security');
        if (!is_user_logged_in()) wp_send_json_error(['message' => 'غیرمجاز']);

        // Check for admin impersonation (transient acts as session for 10 minutes)
        $admin_id = get_current_user_id();
        if (current_user_can('manage_options')) {
            $impersonate_target = get_transient('ssp_impersonate_' . $admin_id);
            if (!empty($impersonate_target) && (int)$impersonate_target !== $admin_id) {
                return (int)$impersonate_target;
            }
        }

        return $admin_id;
    }

    private function ajax_require_admin() {
        check_ajax_referer('ssp_secure_nonce', 'security');
        if (!current_user_can('manage_options')) wp_send_json_error(['message' => 'غیرمجاز']);
        return get_current_user_id();
    }

    /* ============ Proxy Helpers ============ */
    private static $_proxy_settings_cache = null;
    private static $_settings_cache = null;

    /**
     * Centralized settings cache — reduces repeated get_option() calls.
     * Caches the most frequently accessed plugin settings in a single array.
     */
    private function get_settings_cache() {
        if (self::$_settings_cache !== null) return self::$_settings_cache;
        self::$_settings_cache = [
            'doh_enabled'   => get_option('ssp_doh_enabled', 1),
            'doh_server'    => get_option('ssp_doh_server', 'cloudflare'),
            'proxy_settings' => is_array($__tmp = get_option('ssp_proxy_settings', [])) ? $__tmp : [],
            'telegram_relay' => is_array($__tmp = get_option('ssp_telegram_relay', [])) ? $__tmp : [],
            'encryption_key' => get_option('ssp_encryption_key', ''),
        ];
        return self::$_settings_cache;
    }

    private function get_cached_option($key, $default = '') {
        $cache = $this->get_settings_cache();
        return $cache[$key] ?? $default;
    }

    private function get_proxy_settings() {
        if (self::$_proxy_settings_cache !== null) return self::$_proxy_settings_cache;
        $s = $this->get_cached_option('proxy_settings', []);
        self::$_proxy_settings_cache = [
            'enabled' => !empty($s['enabled']) && !empty($s['host']) && !empty($s['port']),
            'url' => (!empty($s['enabled']) && !empty($s['host']) && !empty($s['port']))
                ? $s['type'] . '://' . $s['host'] . ':' . $s['port'] : '',
            'host' => $s['host'] ?? '',
            'port' => $s['port'] ?? '',
            'type' => $s['type'] ?? 'http',
        ];
        return self::$_proxy_settings_cache;
    }

    private function get_proxy_args() {
        $s = $this->get_proxy_settings();
        if (empty($s['enabled']) || empty($s['host']) || empty($s['port'])) return [];
        return [
            'proxy' => $s['url'],
            'sslverify' => false,
        ];
    }

    /**
     * Get DoH settings from centralized cache.
     */
    private function get_doh_settings() {
        return [
            'enabled' => (bool) $this->get_cached_option('doh_enabled', 1),
            'server'  => $this->get_cached_option('doh_server', 'cloudflare'),
        ];
    }

    /**
     * Get telegram relay settings from centralized cache.
     */
    private function get_telegram_relay_cached() {
        return $this->get_cached_option('telegram_relay', []);
    }

    /* ============ Storage Helpers (wp_options + usermeta) ============ */
    private function get_user_items($user_id, $key) {
        {$data = get_user_meta($user_id, 'ssp_' . $key, true); return is_array($data) ? $data : [];}
    }

    private function set_user_items($user_id, $key, $items) {
        update_user_meta($user_id, 'ssp_' . $key, $items);
    }

    private function next_id($items) {
        return empty($items) ? 1 : max(array_column($items, 'id')) + 1;
    }

    private function get_global_items($key) {
        $cache_key = 'ssp_global_' . $key;
        $cached = wp_cache_get($cache_key, 'ssp');
        if ($cached !== false) return $cached;
        $items = get_option('ssp_' . $key, []); if (!is_array($items)) $items = [];
        wp_cache_set($cache_key, $items, 'ssp', 300);
        return $items;
    }

    private function set_global_items($key, $items) {
        update_option('ssp_' . $key, $items, false);
        wp_cache_delete('ssp_global_' . $key, 'ssp');
    }

    private function get_user_messengers($user_id) {
        return $this->get_user_items($user_id, 'messengers');
    }

    private function get_user_wp_sites($user_id) {
        return $this->get_user_items($user_id, 'wp_sites');
    }

    private function get_user_rss_feeds($user_id) {
        return $this->get_user_items($user_id, 'rss_feeds');
    }

    private function get_profile_messengers($user_id, $profile_id = null) {
        $items = $this->get_user_items($user_id, 'messengers');
        if ($profile_id === null) $profile_id = $this->get_active_profile_id($user_id);
        return array_values(array_filter($items, function($m) use ($profile_id) {
            return (int)($m['profile_id'] ?? 1) === $profile_id;
        }));
    }

    private function get_profile_wp_sites($user_id, $profile_id = null) {
        $items = $this->get_user_items($user_id, 'wp_sites');
        if ($profile_id === null) $profile_id = $this->get_active_profile_id($user_id);
        return array_values(array_filter($items, function($s) use ($profile_id) {
            return (int)($s['profile_id'] ?? 1) === $profile_id;
        }));
    }

    private function get_profile_rss_feeds($user_id, $profile_id = null) {
        $items = $this->get_user_items($user_id, 'rss_feeds');
        if ($profile_id === null) $profile_id = $this->get_active_profile_id($user_id);
        return array_values(array_filter($items, function($f) use ($profile_id) {
            return (int)($f['profile_id'] ?? 1) === $profile_id;
        }));
    }

    private function get_enabled_messengers($user_id) {
        return array_values(array_filter($this->get_user_messengers($user_id), function($m) {
            return !empty($m['is_active']);
        }));
    }

    private function get_today_count($user_id, $logs = null) {
        $today = current_time('Y-m-d');
        $cache_key = 'ssp_daily_count_' . $user_id . '_' . $today;
        $count = get_transient($cache_key);
        if ($count !== false) return (int)$count;

        $count = SSP_DB::get_today_logs_count($user_id);

        set_transient($cache_key, $count, 300);
        return $count;
    }

    /* ============ Storage Helpers - Drafts ============ */
    private function get_user_drafts($user_id) {
        {$data = get_user_meta($user_id, 'ssp_drafts', true); return is_array($data) ? $data : [];}
    }

    private function set_user_drafts($user_id, $drafts) {
        update_user_meta($user_id, 'ssp_drafts', $drafts);
    }

    /* ============ Storage Helpers - Templates ============ */
    private function get_user_template_items($user_id) {
        {$data = get_user_meta($user_id, 'ssp_template_items', true); return is_array($data) ? $data : [];}
    }

    private function set_user_template_items($user_id, $templates) {
        update_user_meta($user_id, 'ssp_template_items', $templates);
    }

    /* ============ Storage Helpers - Link Shortener ============ */
    private function get_link_settings($user_id) {
        return get_user_meta($user_id, 'ssp_link_settings', true) ?: ['enabled' => false, 'provider' => 'self', 'api_key' => ''];
    }

    private function set_link_settings($user_id, $settings) {
        update_user_meta($user_id, 'ssp_link_settings', $settings);
    }

    /* ============ User Plan ============ */
    private function get_user_plan($user_id) {
        $plan = get_user_meta($user_id, 'ssp_plan', true) ?: 'free';
        if ($plan === 'pro') {
            $expiry = get_user_meta($user_id, 'ssp_plan_expiry', true);
            if ($expiry && time() > $expiry) {
                update_user_meta($user_id, 'ssp_plan', 'free');
                $this->send_email_notification($user_id, 'license_expired');
                return 'free';
            }
            if ($expiry && ($expiry - time()) < 3 * DAY_IN_SECONDS && ($expiry - time()) > 0) {
                $last_warn = get_user_meta($user_id, 'ssp_license_warn_sent', true) ?: 0;
                if (time() - $last_warn > DAY_IN_SECONDS) {
                    $this->send_email_notification($user_id, 'license_expiring', [
                        'days_left' => ceil(($expiry - time()) / DAY_IN_SECONDS),
                    ]);
                    update_user_meta($user_id, 'ssp_license_warn_sent', time());
                }
            }
        }
        return $plan;
    }

    /* ============ SEO Helpers ============ */
    private function analyze_content_seo($title, $content, $hashtags, $platform = 'general') {
        $issues = [];
        $suggestions = [];
        $strengths = [];
        $score_breakdown = [];

        // === Title Analysis (25 points) ===
        $title_score = 25;
        $title_len = mb_strlen($title);
        if ($title_len === 0) {
            $issues[] = 'عنوان خالی است';
            $title_score -= 25;
        } else {
            if ($title_len < 10) {
                $issues[] = 'عنوان خیلی کوتاه است (حداقل 10 کاراکتر)';
                $title_score -= 10;
            } elseif ($title_len <= 60) {
                $strengths[] = 'طول عنوان مناسب است (' . $title_len . ' کاراکتر)';
            } else {
                $issues[] = 'عنوان خیلی بلند است (حداکثر 60 کاراکتر)';
                $title_score -= 8;
            }

            // Title question
            if (preg_match('/[؟\?]/', $title)) {
                $strengths[] = 'عنوان سوالی است - توجه بیشتری جلب می‌کند';
                $title_score += 2;
            }

            // Title power words
            $power_words = ['راهنما', 'آموزش', 'نکات', 'ترفند', 'بهترین', 'جدیدترین', 'رایگان', 'فوری', 'حتماً', 'چطور', 'چگونه', 'چرا', 'چیست', 'مقایسه', 'بررسی', 'کامل', 'جامع', 'حرفه‌ای'];
            foreach ($power_words as $pw) {
                if (mb_strpos($title, $pw) !== false) {
                    $strengths[] = 'عنوان شامل کلمه جذاب "' . $pw . '"';
                    $title_score += 2;
                    break;
                }
            }

            // Title number
            if (preg_match('/^\d/', $title)) {
                $strengths[] = 'شروع عنوان با عدد توجه را جلب می‌کند';
                $title_score += 1;
            }

            // Title emoji
            if (preg_match('/[\x{1F300}-\x{1F9FF}]/u', $title)) {
                $strengths[] = 'ایموجی در عنوان توجه را جلب می‌کند';
                $title_score += 1;
            }
        }
        $score_breakdown['title'] = max(0, min(25, $title_score));

        // === Content Analysis (25 points) ===
        $content_score = 25;
        $clean_content = strip_tags($content);
        $clean_content = preg_replace('/\s+/', ' ', trim($clean_content));
        $word_count = str_word_count($clean_content);
        $char_count = mb_strlen($clean_content);

        $platform_limits = [
            'telegram' => 4096, 'bale' => 4096, 'eitaa' => 4096, 'rubika' => 4096,
            'instagram' => 2200, 'whatsapp' => 65536, 'website' => 999999, 'general' => 4096,
        ];
        $limit = $platform_limits[$platform] ?? 4096;

        if (empty($clean_content)) {
            $issues[] = 'محتوا خالی است';
            $content_score -= 25;
        } else {
            // Content length analysis (based on Backlinko's ranking factor study)
            // Average top-10 result: 1,447 words for website
            if ($platform === 'website') {
                if ($word_count >= 1500 && $word_count <= 2500) {
                    $strengths[] = 'طول محتوا عالی است (' . $word_count . ' کلمه). محتوای ۱۵۰۰-۲۵۰۰ کلمه رتبه بهتری دارد';
                    $content_score += 5;
                } elseif ($word_count >= 800 && $word_count < 1500) {
                    $strengths[] = 'طول محتوا خوب است (' . $word_count . ' کلمه)';
                    $content_score += 3;
                } elseif ($word_count < 800) {
                    $issues[] = 'محتوا کوتاه است (' . $word_count . ' کلمه). برای رقابت در SERP حداقل ۸۰۰ کلمه بنویسید';
                    $content_score -= 5;
                } elseif ($word_count > 3000) {
                    $suggestions[] = 'محتوا خیلی طولانی است (' . $word_count . ' کلمه). آن را بخش‌بندی کنید';
                    $content_score -= 2;
                }
            } else {

            if ($char_count > $limit) {
                $issues[] = 'محتوا از محدودیت ' . $platform . ' (' . $limit . ' کاراکتر) بیشتر است';
                $content_score -= 10;
            } elseif ($char_count > $limit * 0.9) {
                $suggestions[] = 'محتوا نزدیک به محدودیت ' . $platform . ' است';
            }

            // Reading time
            $reading_time = max(1, ceil($word_count / 200));
            $strengths[] = 'زمان تقریبی خواندن: ' . $reading_time . ' دقیقه';
            }
        }
        $score_breakdown['content'] = max(0, min(25, $content_score));

        // === Structure Analysis (15 points) ===
        $structure_score = 15;

        // Sentence analysis
        $sentences = preg_split('/[.!?؟]+/', $clean_content, -1, PREG_SPLIT_NO_EMPTY);
        $sentence_count = count($sentences);
        $avg_sentence_length = $word_count / max($sentence_count, 1);

        if ($avg_sentence_length > 25) {
            $suggestions[] = 'جملات خیلی طولانی هستند (میانگین ' . round($avg_sentence_length) . ' کلمه)';
            $structure_score -= 4;
        } elseif ($avg_sentence_length > 0 && $avg_sentence_length <= 20) {
            $strengths[] = 'طول جملات مناسب است (میانگین ' . round($avg_sentence_length) . ' کلمه)';
            $structure_score += 2;
        }

        // Paragraph analysis
        $paragraphs = preg_split('/\n\s*\n/', $content);
        $paragraph_count = count(array_filter($paragraphs, function($p) { return !empty(trim(strip_tags($p))); }));

        if ($paragraph_count === 0 && $word_count > 20) {
            $suggestions[] = 'محتوا پاراگراف‌بندی نشده';
            $structure_score -= 5;
        } elseif ($paragraph_count >= 2) {
            $strengths[] = 'محتوا پاراگراف‌بندی شده (' . $paragraph_count . ' پاراگراف)';
            $structure_score += 2;
        }

        // Heading check (for website)
        if ($platform === 'website') {
            if (preg_match('/<h[1-6]/i', $content)) {
                $headings = preg_match_all('/<h([1-6])[^>]*>/i', $content, $h_matches);
                $heading_levels = array_unique($h_matches[1]);
                if (count($heading_levels) >= 2) {
                    $strengths[] = 'ساختار عنوان‌بندی مناسب است';
                    $structure_score += 3;
                } else {
                    $suggestions[] = 'از تگ‌های H2 و H3 برای ساختار محتوا استفاده کنید';
                }
            } else {
                $suggestions[] = 'محتوا تگ عنوان (H2/H3) ندارد';
                $structure_score -= 3;
            }
        }

        // List/number detection
        if (preg_match('/\d+[\.\)\/]/', $content) || preg_match('/[-*•]\s/', $content)) {
            $strengths[] = 'محتوا شامل لیست یا اعداد است';
            $structure_score += 2;
        }

        $score_breakdown['structure'] = max(0, min(15, $structure_score));

        // === Engagement Analysis (15 points) ===
        $engagement_score = 15;

        // CTA detection (increases conversion by 202% - Wordstream study)
        $cta_words = ['کلیک', 'بزنید', 'مشاهده', 'دانلود', 'عضو', 'خرید', 'تماس', 'بفرستید', 'ارسال', 'ثبت‌نام', 'لینک', 'سفارش', 'بیشتر بخوانید', 'همین الان', 'شروع کنید', 'رایگان امتحان'];
        $has_cta = false;
        foreach ($cta_words as $cta) {
            if (mb_stripos($clean_content, $cta) !== false) {
                $has_cta = true;
                break;
            }
        }
        if ($has_cta) {
            $strengths[] = 'محتوا شامل دعوت به اقدام (CTA) است';
            $engagement_score += 3;
        } else {
            $suggestions[] = 'محتوا دعوت به اقدام (CTA) ندارد';
            $engagement_score -= 3;
        }

        // Question detection
        if (preg_match('/[؟\?]/', $content)) {
            $strengths[] = 'محتوا شامل سوال است';
            $engagement_score += 2;
        }

        // Emoji analysis
        $emoji_count = preg_match_all('/[\x{1F300}-\x{1F9FF}]/u', $content);
        if ($emoji_count > 5) {
            $suggestions[] = 'ایموجی زیادی استفاده شده (' . $emoji_count . ' عدد)';
            $engagement_score -= 2;
        } elseif ($emoji_count > 0 && $emoji_count <= 5) {
            $strengths[] = 'ایموجی به اندازه مناسب استفاده شده';
            $engagement_score += 1;
        }

        // Link detection
        if (preg_match('/https?:\/\/\S+/', $content)) {
            $strengths[] = 'محتوا شامل لینک است';
            $engagement_score += 2;
        }

        // Quote/blockquote detection
        if (preg_match('/>|«|»|"/', $content)) {
            $strengths[] = 'محتوا شامل نقل‌قول است';
            $engagement_score += 1;
        }

        $score_breakdown['engagement'] = max(0, min(15, $engagement_score));

        // === Hashtag Analysis (10 points) ===
        $hashtag_score = 10;
        $hashtag_list = array_filter(explode(' ', $hashtags), function($h) { return !empty(trim($h)); });
        $hashtag_count = count($hashtag_list);

        if ($platform !== 'website') {
            if ($hashtag_count === 0) {
                $suggestions[] = 'هشتگ اضافه کنید برای دیده شدن بیشتر';
                $hashtag_score -= 5;
            } else {
                $bad_hashtags = [];
                foreach ($hashtag_list as $tag) {
                    $tag = trim($tag);
                    if (strpos($tag, '#') !== 0) {
                        $bad_hashtags[] = $tag;
                    } else {
                        $tag_text = mb_substr($tag, 1);
                        if (mb_strlen($tag_text) < 3) {
                            $suggestions[] = 'هشتگ "' . $tag . '" خیلی کوتاه است';
                            $hashtag_score -= 1;
                        } elseif (mb_strlen($tag_text) > 30) {
                            $suggestions[] = 'هشتگ "' . $tag . '" خیلی بلند است';
                            $hashtag_score -= 1;
                        }
                    }
                }
                if (!empty($bad_hashtags)) {
                    $issues[] = 'هشتگ‌های بدون # : ' . implode(', ', $bad_hashtags);
                    $hashtag_score -= 3;
                }

                $unique = array_unique($hashtag_list);
                if (count($unique) < count($hashtag_list)) {
                    $issues[] = 'هشتگ تکراری وجود دارد';
                    $hashtag_score -= 2;
                }

                $ideal_hashtags = [
                    'telegram' => [3, 5], 'bale' => [3, 5], 'eitaa' => [3, 5],
                    'rubika' => [3, 5], 'instagram' => [5, 10], 'whatsapp' => [0, 3],
                    'general' => [3, 5],
                ];
                [$min_h, $max_h] = $ideal_hashtags[$platform] ?? [3, 5];
                if ($hashtag_count >= $min_h && $hashtag_count <= $max_h) {
                    $strengths[] = 'تعداد هشتگ‌ها مناسب است (' . $hashtag_count . ' عدد)';
                    $hashtag_score += 2;
                } elseif ($hashtag_count > $max_h) {
                    $suggestions[] = 'تعداد هشتگ‌ها زیاد است (' . $hashtag_count . ')';
                    $hashtag_score -= 2;
                }
            }
        } else {
            $hashtag_score = 10; // No penalty for website
        }
        $score_breakdown['hashtag'] = max(0, min(10, $hashtag_score));

        // === Persian Text Quality (10 points) ===
        $quality_score = 10;

        $zwnj_count = preg_match_all('/\x{200C}/', $clean_content);
        $persian_words = preg_match_all('/[\x{0600}-\x{06FF}]/', $clean_content);
        if ($persian_words > 10 && $zwnj_count === 0) {
            $suggestions[] = 'محتوا نیم‌فاصله (ZWNJ) ندارد. استفاده از نیم‌فاصله در متن فارسی صحیح توصیه می‌شود';
            $quality_score -= 2;
        }

        if (preg_match('/[ك٤ي]/u', $clean_content) && preg_match('/[کی]/u', $clean_content)) {
            $suggestions[] = 'کاراکترهای عربی و فارسی مخلوط هستند';
            $quality_score -= 3;
        }

        $wrong_punct = preg_match_all('/[,.]/', $clean_content);
        if ($wrong_punct > 0 && $persian_words > 10) {
            $suggestions[] = 'از نقطه و کاما انگلیسی به جای فارسی استفاده شده';
            $quality_score -= 2;
        }

        // Duplicate content detection
        $sentences_arr = array_map('trim', $sentences);
        $unique_sentences = array_unique($sentences_arr);
        if (count($sentences_arr) > 3 && count($unique_sentences) < count($sentences_arr) * 0.8) {
            $suggestions[] = 'محتوا جملات تکراری زیادی دارد';
            $quality_score -= 3;
        } else {
            $strengths[] = 'محتوا بدون تکرار غیرضروری است';
            $quality_score += 1;
        }

        $score_breakdown['quality'] = max(0, min(10, $quality_score));

        // === Readability Score ===
        $readability = $this->calculate_readability($clean_content, $word_count, $sentence_count);

        // === Platform-specific checks ===
        $platform_checks = $this->get_platform_seo_checks($platform, $title, $content, $hashtags);
        $issues = array_merge($issues, $platform_checks['issues']);
        $suggestions = array_merge($suggestions, $platform_checks['suggestions']);
        $strengths = array_merge($strengths, $platform_checks['strengths']);

        // === Keyword Analysis ===
        $keywords = $this->extract_keywords($clean_content);

        // === Final Score ===
        $score = array_sum($score_breakdown);

        return [
            'score' => $score,
            'score_breakdown' => $score_breakdown,
            'issues' => $issues,
            'suggestions' => $suggestions,
            'strengths' => $strengths,
            'readability' => $readability,
            'keywords' => $keywords,
            'stats' => [
                'title_length' => $title_len,
                'word_count' => $word_count,
                'char_count' => $char_count,
                'hashtag_count' => $hashtag_count,
                'sentence_count' => $sentence_count,
                'paragraph_count' => $paragraph_count,
                'emoji_count' => $emoji_count,
                'reading_time' => max(1, ceil($word_count / 200)),
                'avg_sentence_length' => round($avg_sentence_length, 1),
            ],
            'platform' => $platform,
            'platform_limit' => $limit,
        ];
    }

    private function extract_keywords($text) {
        $tokens = preg_split('/[\s,\.!?؟:;\(\)\[\]\{\}\"\'<>\/\\|@#\$%^&*\-+=~`]+/', $text, -1, PREG_SPLIT_NO_EMPTY);
        $word_freq = [];
        foreach ($tokens as $token) {
            $token = trim($token, '،.؟!.:;()[]{}"\'');
            $token_len = mb_strlen($token);
            if ($token_len >= 3 && preg_match('/^[\x{0600}-\x{06FF}\x{067E}-\x{0686}\x{06A9}-\x{06AF}\x{06CC}\x{0670}\x{200C}]+$/u', $token) && !in_array($token, self::$_seo_stop_words)) {
                $word_freq[$token] = ($word_freq[$token] ?? 0) + 1;
            }
        }
        arsort($word_freq);
        $top = array_slice($word_freq, 0, 8, true);

        $total_words = array_sum($word_freq);
        $result = [];
        foreach ($top as $word => $count) {
            $result[] = [
                'word' => $word,
                'count' => $count,
                'density' => $total_words > 0 ? round(($count / $total_words) * 100, 1) : 0,
            ];
        }
        return $result;
    }

    private function calculate_readability($text, $word_count, $sentence_count) {
        if ($word_count === 0) return ['level' => 'نامشخص', 'score' => 0, 'description' => 'محتوایی برای تحلیل وجود ندارد'];

        $avg_sentence_length = $word_count / max($sentence_count, 1);

        // Complex words (words with more than 5 characters - Persian has longer words)
        $words = preg_split('/\s+/', $text);
        $complex_words = 0;
        foreach ($words as $word) {
            $clean_word = preg_replace('/[^\p{L}]/u', '', $word);
            if (mb_strlen($clean_word) > 5) $complex_words++;
        }
        $complex_ratio = $complex_words / max($word_count, 1);

        // Flesch Reading Ease adapted for Persian
        // Original: 206.835 - 1.015(avg_word_per_sentence) - 84.6(syllables_per_word)
        // Adapted coefficients for Persian (longer words, different structure)
        $score = 206.835 - (1.015 * $avg_sentence_length) - (55 * $complex_ratio);
        $score = max(0, min(100, $score));

        // Flesch-Kincaid Grade Level (educational level needed to understand)
        $grade = (0.39 * $avg_sentence_length) + (11.8 * $complex_ratio) - 15.59;
        $grade = max(0, min(16, $grade));

        // Coleman-Liau Index adapted for Persian
        $letters = mb_strlen(preg_replace('/[^\p{L}]/u', '', $text));
        $chars_per_word = $letters / max($word_count, 1);
        $sentences_per_word = $sentence_count / max($word_count, 1);
        $coleman_liau = (5.88 * $chars_per_word) - (29.6 * $sentences_per_word) - 15.8;

        // Average of multiple formulas for more accuracy
        $final_score = ($score * 0.5) + (max(0, min(100, 100 - ($grade * 6.25))) * 0.3) + (max(0, min(100, $coleman_liau * -5 + 100)) * 0.2);
        $final_score = max(0, min(100, $final_score));

        if ($final_score >= 80) {
            $level = 'بسیار آسان';
            $desc = 'خوانایی عالی - قابل فهم برای عموم (سطح دبستان)';
        } elseif ($final_score >= 60) {
            $level = 'آسان';
            $desc = 'خوانایی خوب - قابل فهم برای بیشتر افراد (سطح دبیرستان)';
        } elseif ($final_score >= 40) {
            $level = 'متوسط';
            $desc = 'نیاز به تمرکز - مناسب مخاطب تحصیل‌کرده (سطح کارشناسی)';
        } elseif ($final_score >= 20) {
            $level = 'سخت';
            $desc = 'پیچیده - مناسب متخصصان (سطح کارشناسی ارشد)';
        } else {
            $level = 'بسیار سخت';
            $desc = 'بسیار تخصصی - مناسب محققان (سطح دکترا)';
        }

        return [
            'level' => $level,
            'score' => round($final_score),
            'flesch_score' => round($score),
            'grade_level' => round($grade, 1),
            'description' => $desc,
            'avg_words_per_sentence' => round($avg_sentence_length, 1),
            'complex_ratio' => round($complex_ratio * 100, 1),
            'chars_per_word' => round($chars_per_word, 1),
        ];
    }

    private function get_platform_seo_checks($platform, $title, $content, $hashtags) {
        $issues = [];
        $suggestions = [];
        $strengths = [];

        switch ($platform) {
            case 'telegram':
            case 'bale':
                // HTML support check
                if (preg_match('/<(?!br|p|b|i|u|a|strong|em|code|pre|blockquote|s|tg-spoiler)[a-z]/i', $content)) {
                    $suggestions[] = 'برخی تگ‌های HTML ممکن است در ' . $platform . ' پشتیبانی نشوند';
                }
                // Mention check
                if (preg_match('/@\w+/', $content)) {
                    $strengths[] = 'محتوا شامل منشن (@) است';
                }
                // Deep link check
                if (preg_match('/t\.me\/\w+/', $content)) {
                    $strengths[] = 'محتوا شامل لینک عمیق ' . $platform . ' است';
                }
                break;

            case 'instagram':
                // First line importance
                $first_line = explode("\n", $content)[0] ?? '';
                if (mb_strlen($first_line) < 30) {
                    $suggestions[] = 'خط اول اینستاگرام خیلی مهم است. آن را جذاب‌تر بنویسید';
                }
                // Hashtag count for Instagram
                $ht_count = count(array_filter(explode(' ', $hashtags)));
                if ($ht_count < 5) {
                    $suggestions[] = 'اینستاگرام به 5-10 هشتگ نیاز دارد';
                }
                break;

            case 'whatsapp':
                // Bold/italic check
                if (preg_match('/\*[^*]+\*|_[^_]+_/', $content)) {
                    $strengths[] = 'محتوا شامل فرمت‌بندی (بولد/ایتالیک) است';
                }
                // Length check (very strict for WhatsApp)
                if (mb_strlen($content) > 65536) {
                    $issues[] = 'محتوا از محدودیت WhatsApp بیشتر است';
                }
                break;

            case 'eitaa':
            case 'rubika':
                // No HTML support
                if (preg_match('/<[^>]+>/', $content)) {
                    $issues[] = $platform . ' از HTML پشتیبانی نمی‌کند. تگ‌ها حذف خواهند شد';
                }
                break;

            case 'website':
                $title_len = mb_strlen($title);
                $char_count = mb_strlen(strip_tags($content));
                // SEO title length (Google shows ~60 chars)
                if ($title_len > 60) {
                    $suggestions[] = 'عنوان SEO بیشتر از 60 کاراکتر نمایش داده نمی‌شود';
                } elseif ($title_len >= 30 && $title_len <= 60) {
                    $strengths[] = 'طول عنوان SEO مناسب است (' . $title_len . ' کاراکتر)';
                }
                // Meta description length (Google shows ~155 chars)
                if ($char_count > 0 && $char_count < 70) {
                    $suggestions[] = 'توضیحات meta خیلی کوتاه است. حداقل 70 کاراکتر بنویسید';
                }
                // H2/H3 check
                if (preg_match_all('/<h[23]/i', $content)) {
                    $strengths[] = 'محتوا شامل تگ‌های H2/H3 است';
                } else {
                    $suggestions[] = 'از تگ‌های H2 و H3 برای ساختار محتوا استفاده کنید';
                }
                // Internal link check
                if (preg_match('/href=["\'](?!(https?:\/\/))[^"\']+["\']/', $content)) {
                    $strengths[] = 'محتوا شامل لینک داخلی است';
                }
                // Image alt check
                if (preg_match('/<img/i', $content)) {
                    if (preg_match('/<img(?![^>]*alt=)[^>]*>/i', $content)) {
                        $issues[] = 'تصاویر بدون attribute alt هستند. برای SEO ضروری است';
                    } else {
                        $strengths[] = 'تصاویر دارای alt text هستند';
                    }
                }
                // Schema/structured data hint
                $suggestions[] = 'برای بهتر شدن SEO، از Schema Markup (JSON-LD) استفاده کنید';
                // URL slug hint
                $suggestions[] = 'URL صفحه باید کوتاه، توصیفی و شامل کلمه کلیدی باشد';
                break;
        }

        return ['issues' => $issues, 'suggestions' => $suggestions, 'strengths' => $strengths];
    }

    private function generate_meta_description($title, $content) {
        $clean_content = strip_tags($content);
        $clean_content = preg_replace('/\s+/', ' ', trim($clean_content));

        if (empty($clean_content)) {
            return mb_substr($title, 0, SSP_META_DESC_LENGTH);
        }

        // Try to get first meaningful sentence
        $sentences = preg_split('/[.!?؟]+/', $clean_content, -1, PREG_SPLIT_NO_EMPTY);
        $meta = '';

        foreach ($sentences as $sentence) {
            $sentence = trim($sentence);
            if (mb_strlen($sentence) > 20) {
                $meta = $sentence;
                break;
            }
        }

        if (empty($meta)) {
            $meta = $clean_content;
        }

        // Trim to meta description length
        if (mb_strlen($meta) > SSP_META_DESC_LENGTH) {
            $meta = mb_substr($meta, 0, SSP_META_DESC_LENGTH);
            $last_space = mb_strrpos($meta, ' ');
            if ($last_space > SSP_META_DESC_LENGTH * 0.7) {
                $meta = mb_substr($meta, 0, $last_space);
            }
            $meta .= '...';
        }

        return $meta;
    }

    private function generate_seo_title_suggestions($title, $content, $platform) {
        $suggestions = [];
        $clean_content = strip_tags($content);
        $clean_content = preg_replace('/\s+/', ' ', trim($clean_content));

        // Extract meaningful Persian words (stop words removed)
        $stop_words = ['و', 'در', 'از', 'به', 'با', 'که', 'این', 'آن', 'را', 'برای', 'تا', 'هم', 'یا', 'ولی', 'اما', 'اگر', 'می', 'شد', 'است', 'بود', 'شود', 'شدند', 'هستند', 'باید', 'خیلی', 'همه', 'هر', 'یک', 'دو', 'سه', 'چهار', 'پنج', 'شش', 'هفت', 'هشت', 'نه', 'ده', 'بیشتر', 'کمتر', 'بعد', 'قبل', 'وقتی', 'وقت', 'جای', 'ازجمله', 'مثل', 'همچنین', 'همچنین', 'بجز', 'غیر', 'درباره', 'درون', 'بیرون', 'روی', 'زیر', 'بالا', 'پایین', 'جلو', 'عقب', 'کنار', 'نزدیک', 'دور'];

        // Tokenize: split by whitespace and punctuation, keep only meaningful Persian words
        $tokens = preg_split('/[\s,\.!?؟:;\(\)\[\]\{\}\"\'<>\/\\|@#\$%^&*\-+=~`]+/', $clean_content, -1, PREG_SPLIT_NO_EMPTY);
        $word_freq = [];
        foreach ($tokens as $token) {
            $token = trim($token, '،.؟!.:;()[]{}"\'');
            $token_len = mb_strlen($token);
            // Only keep Persian words with 3+ characters that are not stop words
            if ($token_len >= 3 && preg_match('/^[\x{0600}-\x{06FF}\x{067E}-\x{0686}\x{06A9}-\x{06AF}\x{06CC}\x{0670}\x{064B}-\x{065F}\x{200C}]+$/u', $token) && !in_array($token, $stop_words)) {
                $word_freq[$token] = ($word_freq[$token] ?? 0) + 1;
            }
        }
        arsort($word_freq);
        $top_words = array_slice(array_keys($word_freq), 0, 5);

        if (empty($top_words)) {
            $suggestions[] = 'محتوای بیشتری وارد کنید تا پیشنهاد عنوان تولید شود';
            return $suggestions;
        }

        $main_topic = $top_words[0];
        $second_topic = $top_words[1] ?? '';

        // Platform-specific title templates
        $templates = [
            'website' => [
                'عنوان جذاب: ' . $main_topic . ' | راهنمای کامل',
                $main_topic . ' چیست؟ همه چیز درباره ' . $main_topic,
                'آموزش ' . $main_topic . ' از صفر تا صد',
                'بهترین روش‌های ' . $main_topic . ' در سال ۱۴۰۵',
                ($second_topic ? $main_topic . ' و ' . $second_topic . ': مقاسبه جامع' : 'مقایسه و بررسی ' . $main_topic),
            ],
            'telegram' => [
                '🔥 ' . $main_topic . ' | همه چیزی که باید بدانید',
                'آموزش ' . $main_topic . ' در ۵ دقیقه',
                $main_topic . ' چیست و چرا مهم است؟',
                '۳ نکته طلایی درباره ' . $main_topic,
                'راهنمای کامل ' . $main_topic . ' (فقط ۲ دقیقه)',
            ],
            'instagram' => [
                $main_topic . ' ✨ راهنمای کامل',
                'همه چیز درباره ' . $main_topic . ' 📌',
                '۵ نکته مهم درباره ' . $main_topic,
                'آموزش ' . $main_topic . ' از صفر 🎯',
                ($second_topic ? $main_topic . ' vs ' . $second_topic : 'بهترین روش ' . $main_topic),
            ],
            'general' => [
                'آموزش جامع ' . $main_topic,
                $main_topic . ': راهنمای کامل و کاربردی',
                'همه چیز درباره ' . $main_topic,
                'بهترین روش‌های ' . $main_topic,
                'نکات مهم درباره ' . $main_topic,
            ],
        ];

        $platform_templates = $templates[$platform] ?? $templates['general'];
        $suggestions = array_merge($suggestions, $platform_templates);

        return $suggestions;
    }

    /* ============ File Helpers ============ */
    private function get_extension_from_mime($mime) {
        $map = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/gif' => 'gif', 'image/webp' => 'webp'];
        return $map[$mime] ?? 'jpg';
    }

    private function get_wp_rest_type($post_type) {
        $map = ['post' => 'posts', 'page' => 'pages', 'product' => 'products'];
        return $map[$post_type] ?? 'posts';
    }

    /* ============ AI JSON Parser ============ */
    public function parse_ai_json($content) {
        $content = trim((string)$content);

        // 0. Pre-clean: strip XML thought tags <think>...</think> or <thought>...</thought>
        $content = preg_replace('/<think>[\s\S]*?<\/think>/i', '', $content);
        $content = preg_replace('/<thought>[\s\S]*?<\/thought>/i', '', $content);
        $content = preg_replace('/^(?:thinking|thought(?:\s+for\s+\d+\s+seconds)?|reasoning)\b[\s\S]*?\n\n/i', '', $content);

        // Replace smart quotes, remove BOM
        $content = preg_replace('/[\x{201C}\x{201D}\x{2018}\x{2019}\x{00AB}\x{00BB}]/u', '"', $content);
        $content = str_replace("\xEF\xBB\xBF", '', $content);
        
        // Remove Markdown code block wrappers (```json ... ``` or ``` ... ```)
        $content = preg_replace('/^```(?:json)?\s*\n?/m', '', $content);
        $content = preg_replace('/\n?```\s*$/m', '', $content);
        $content = trim($content);

        // 1. Direct decode (fast path for well-formed JSON)
        $decoded = @json_decode($content, true);
        if ($decoded && is_array($decoded)) return $decoded;

        // 2. Extract from code block (fallback if regex above didn't catch it)
        if (preg_match('/```(?:json)?\s*\n?(.*?)\n?\s*```/s', $content, $m)) {
            $decoded = @json_decode(trim($m[1]), true);
            if ($decoded && is_array($decoded)) return $decoded;
        }

        // 3. Extract from [ ... ] (array of objects [{...}])
        if (preg_match('/\[\s*\{[\s\S]*\}\s*\]/', $content, $m)) {
            $extracted = $m[0];
            // Try direct decode first
            $decoded = @json_decode($extracted, true);
            if ($decoded && is_array($decoded)) return $decoded;
            // Fix newlines inside strings, then try again
            $json_str = $this->clean_json_string($extracted);
            $decoded = @json_decode($json_str, true);
            if ($decoded && is_array($decoded)) return $decoded;
            // Last resort: replace all newlines with spaces (loses formatting but parses)
            $flat = str_replace(["\n", "\r", "\t"], ' ', $json_str);
            $decoded = @json_decode($flat, true);
            if ($decoded && is_array($decoded)) return $decoded;
        }

        // 4. Extract from { ... } (object, first { to last })
        $start = strpos($content, '{');
        $end = strrpos($content, '}');
        if ($start !== false && $end !== false && $end > $start) {
            $extracted = substr($content, $start, $end - $start + 1);
            $decoded = @json_decode($extracted, true);
            if ($decoded && is_array($decoded)) return $decoded;
            $json_str = $this->clean_json_string($extracted);
            $decoded = @json_decode($json_str, true);
            if ($decoded && is_array($decoded)) return $decoded;
            $flat = str_replace(["\n", "\r", "\t"], ' ', $json_str);
            $decoded = @json_decode($flat, true);
            if ($decoded && is_array($decoded)) return $decoded;
        }

        // 5. Last resort: aggressive clean + flatten
        $cleaned = preg_replace('/^[^{]*/', '', $content);
        $cleaned = preg_replace('/[^}]*$/', '', $cleaned);
        $cleaned = $this->clean_json_string($cleaned);
        $flat = str_replace(["\n", "\r", "\t"], ' ', $cleaned);
        $decoded = @json_decode($flat, true);
        if ($decoded && is_array($decoded)) return $decoded;

        return null;
    }

    private function clean_json_string($json) {
        // Remove trailing commas before } or ]
        $json = preg_replace('/,\s*}/', '}', $json);
        $json = preg_replace('/,\s*]/', ']', $json);
        // Remove control characters except normal whitespace (\n, \r, \t)
        $json = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F]/', '', $json);
        // Fix unescaped newlines inside JSON string values
        $json = $this->fix_newlines_in_json_strings($json);
        return $json;
    }

    private function fix_newlines_in_json_strings($json) {
        $len = strlen($json);
        $result = '';
        $i = 0;
        while ($i < $len) {
            $ch = $json[$i];
            if ($ch === '"') {
                $result .= '"';
                $i++;
                while ($i < $len) {
                    $c = $json[$i];
                    if ($c === '\\' && ($i + 1) < $len) {
                        $result .= $c . $json[$i + 1];
                        $i += 2;
                    } elseif ($c === '"') {
                        $result .= '"';
                        $i++;
                        break;
                    } elseif ($c === "\n") {
                        $result .= '\\n';
                        $i++;
                    } elseif ($c === "\r") {
                        $result .= '\\r';
                        $i++;
                    } elseif ($c === "\t") {
                        $result .= '\\t';
                        $i++;
                    } else {
                        $result .= $c;
                        $i++;
                    }
                }
            } else {
                $result .= $ch;
                $i++;
            }
        }
        return $result;
    }

    /* ============ UTM Helpers ============ */
    private function append_utm_params($url, $user_id, $platform = '') {
        $settings = is_array($__tmp = get_user_meta($user_id, 'ssp_utm_settings', true)) ? $__tmp : [];
        if (empty($settings['enabled'])) return $url;

        $parsed = wp_parse_url($url);
        if (!$parsed) return $url;

        $params = [];
        if (!empty($parsed['query'])) parse_str($parsed['query'], $params);

        $params['utm_source'] = !empty($settings['auto_source']) && !empty($platform) ? $platform : ($settings['source'] ?? 'smart-automation');
        $params['utm_medium'] = $settings['medium'] ?? 'social';
        if (!empty($settings['campaign'])) $params['utm_campaign'] = $settings['campaign'];

        $query = http_build_query($params);
        $scheme = $parsed['scheme'] ?? 'https';
        $host = $parsed['host'] ?? '';
        $path = $parsed['path'] ?? '';

        return $scheme . '://' . $host . $path . '?' . $query;
    }

    private function log_activity($user_id, $platform, $title, $message, $status, $response = '', $ai_provider = '', $ai_tokens = 0, $image_url = '', $tool_type = '') {
        $truncated_message = mb_substr($message, 0, 500);
        $truncated_response = mb_substr($response, 0, 500);

        SSP_DB::insert_log([
            'user_id' => (int)$user_id,
            'platform' => $platform,
            'title' => mb_substr($title, 0, 100),
            'message' => $truncated_message,
            'status' => $status,
            'response' => $truncated_response,
            'ai_provider' => $ai_provider,
            'ai_tokens' => (int)$ai_tokens,
            'image_url' => $image_url,
            'tool_type' => $tool_type,
            'created_at' => current_time('mysql'),
        ]);

        // Update daily count cache
        $today = current_time('Y-m-d');
        $cache_key = 'ssp_daily_count_' . $user_id . '_' . $today;
        $current_count = (int)(get_transient($cache_key) ?: 0);
        set_transient($cache_key, $current_count + 1, 86400);
    }
}
