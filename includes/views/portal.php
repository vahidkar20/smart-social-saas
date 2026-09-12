<?php
/**
 * Portal View - Main UI template
 */
if (!defined('ABSPATH')) exit;

if (!$is_frontend && !is_user_logged_in()) {
    echo '<div style="text-align:center; padding:80px 20px; max-width:600px; margin:80px auto; border-radius:24px; background:white; font-family:IRANSansX, sans-serif; direction:rtl; border:1px solid #e2e8f0;">
        <h2 style="color:#1e293b;">ورود به پورتال</h2>
        <p style="color:#64748b;">برای دسترسی، لطفاً وارد شوید.</p>
        <a href="' . wp_login_url(get_permalink()) . '" style="background:#4f46e5; color:#fff; padding:14px 32px; border-radius:999px; text-decoration:none; font-weight:700; display:inline-block;">ورود</a>
    </div>';
    return;
}

// Frontend (shortcode) path: block non-logged-in users with login overlay
if ($is_frontend && !is_user_logged_in()) {
    echo '<div style="text-align:center; padding:80px 20px; max-width:600px; margin:80px auto; border-radius:24px; background:white; font-family:IRANSansX, sans-serif; direction:rtl; border:1px solid #e2e8f0; box-shadow:0 4px 24px rgba(0,0,0,0.08);">
        <div style="width:64px;height:64px;border-radius:50%;background:#eef2ff;display:flex;align-items:center;justify-content:center;margin:0 auto 20px;">
            <svg viewBox="0 0 24 24" width="32" height="32" fill="none" stroke="#4f46e5" stroke-width="2"><path d="M15 3h4a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-4"/><polyline points="10 17 15 12 10 7"/><line x1="15" y1="12" x2="3" y2="12"/></svg>
        </div>
        <h2 style="color:#1e293b; margin:0 0 8px;">ورود به پورتال</h2>
        <p style="color:#64748b; margin:0 0 24px; font-size:0.95rem;">برای استفاده از ابزارها، لطفاً وارد حساب کاربری خود شوید.</p>
        <a href="' . wp_login_url(get_permalink()) . '" style="background:#4f46e5; color:#fff; padding:14px 32px; border-radius:12px; text-decoration:none; font-weight:700; display:inline-block; font-size:0.95rem;">ورود به حساب</a>
    </div>';
    return;
}

$user_id = get_current_user_id();

// Check for admin impersonation — only when accessed from admin panel with impersonate parameter
$is_impersonating = false;
$impersonate_target = null;
if (current_user_can('manage_options')) {
    if (isset($_GET['impersonate']) && $_GET['impersonate'] === '1') {
        $impersonate_target = get_transient('ssp_impersonate_' . $user_id);
        if (!empty($impersonate_target) && (int)$impersonate_target !== $user_id) {
            $is_impersonating = true;
            $user_id = (int)$impersonate_target;
        }
    } else {
        // Clear impersonation when accessing portal normally
        delete_transient('ssp_impersonate_' . $user_id);
    }
}

$plan = $this->get_user_plan($user_id);
$this->ensure_profile_fields($user_id);

$profiles = $this->get_user_items($user_id, 'profiles');
if (empty($profiles)) {
    $profiles = [['id' => 1, 'name' => 'پیش‌فرض', 'color' => '#4F46E5', 'is_active' => true, 'created_at' => current_time('mysql')]];
    $this->set_user_items($user_id, 'profiles', $profiles);
}
$active_profile_id = $this->get_active_profile_id($user_id);
$active_profile = null;
foreach ($profiles as $p) { if ((int)$p['id'] === $active_profile_id) { $active_profile = $p; break; } }
if (!$active_profile) $active_profile = $profiles[0];

$messengers = $this->get_profile_messengers($user_id, $active_profile_id);
$wp_sites = $this->get_profile_wp_sites($user_id, $active_profile_id);
$rss_feeds = $this->get_profile_rss_feeds($user_id, $active_profile_id);
$enabled_messengers = count(array_values(array_filter($messengers, function($m) { return !empty($m['is_active']); })));

$pending_count = count(SSP_DB::get_queue_items('pending', 0, $user_id));

$user_logs = SSP_DB::get_logs($user_id, 200);
$total_count = count($user_logs);
$total_tokens = 0;
foreach ($user_logs as $l) { $total_tokens += (int)($l['ai_tokens'] ?? 0); }
$logs = array_slice($user_logs, 0, 30);

$today_count = $this->get_today_count($user_id, $user_logs);

$next_cron = wp_next_scheduled('ssp_process_queue_hook');
$nonce = wp_create_nonce('ssp_secure_nonce');

// Bulk-load user meta to avoid repeated DB queries
$user_meta = get_user_meta($user_id);
$ai_provider = $user_meta['ssp_ai_provider'][0] ?? 'openai';
$ai_api_key = $user_meta['ssp_ai_api_key'][0] ?? '';
$ai_model = $user_meta['ssp_ai_model'][0] ?? 'gpt-4o-mini';
$ai_rewrite = $user_meta['ssp_ai_rewrite'][0] ?? '';
$ai_hashtags = $user_meta['ssp_ai_hashtags'][0] ?? '';
$ai_prompt_mode = $user_meta['ssp_ai_prompt_mode'][0] ?? 'simple';
$ai_custom_prompt = $user_meta['ssp_ai_custom_prompt'][0] ?? '';
$ai_mode = $user_meta['ssp_ai_mode'][0] ?? 'api';
$ai_configured = ($ai_mode === 'browser') || !empty($ai_api_key);

$msg_template = $user_meta['ssp_msg_template'][0] ?? '';
$signature = $user_meta['ssp_signature'][0] ?? '';
$global_hashtags = $user_meta['ssp_global_hashtags'][0] ?? '';
$auto_wp_posts = $user_meta['ssp_auto_wp_posts'][0] ?? '';

$link_settings = $this->get_link_settings($user_id);

$drafts = $this->get_user_drafts($user_id);
$template_items = $this->get_user_template_items($user_id);

$schedules = $this->get_user_items($user_id, 'schedules');
usort($schedules, function($a, $b) { return strtotime($a['scheduled_at'] ?? '0') - strtotime($b['scheduled_at'] ?? '0'); });

$distributions = method_exists($this, 'get_profile_items') ? $this->get_profile_items($user_id, 'distributions', $active_profile_id) : $this->get_user_items($user_id, 'distributions');

$masked_key = !empty($ai_api_key) ? esc_attr(substr($ai_api_key, 0, 8) . '....' . substr($ai_api_key, -4)) : '';

$messenger_json = json_encode(array_map(function($m) {
    $token = $m['token'];
    $masked = !empty($token) ? substr($token, 0, 4) . '****' . substr($token, -4) : '';
    return ['id' => (int)$m['id'], 'platform' => $m['platform'], 'name' => $m['name'],
            'token_masked' => $masked, 'channel_id' => $m['channel_id'], 'is_active' => (int)$m['is_active']];
}, $messengers));

$wp_sites_json = json_encode(array_map(function($s) {
    $pass = $s['app_password'];
    $masked = !empty($pass) ? substr($pass, 0, 4) . '****' . substr($pass, -4) : '';
    return ['id' => (int)$s['id'], 'site_name' => $s['site_name'], 'site_url' => $s['site_url'],
            'username' => $s['username'], 'app_password_masked' => $masked,
            'is_active' => (int)$s['is_active'], 'auto_publish' => (int)$s['auto_publish'],
            'post_type' => $s['post_type'] ?? 'post', 'categories' => $s['categories'] ?? ''];
}, $wp_sites));

$rss_feeds_json = json_encode(array_map(function($f) {
    return ['id' => (int)$f['id'], 'feed_name' => $f['feed_name'], 'feed_url' => $f['feed_url'],
            'is_active' => (int)$f['is_active'], 'auto_fetch' => (int)$f['auto_fetch'],
            'clean_ads' => (int)($f['clean_ads'] ?? 1), 'clean_urls' => (int)($f['clean_urls'] ?? 1),
            'extract_content' => (int)($f['extract_content'] ?? 0), 'max_length' => (int)($f['max_length'] ?? 500),
            'content_mode' => $f['content_mode'] ?? 'summary',
            'message_template' => $f['message_template'] ?? ''];
}, $rss_feeds));

wp_enqueue_style('ssp-portal-css', plugins_url('assets/css/portal.css', dirname(__DIR__, 2) . '/main.php'), [], SSP_VERSION);

ob_start();
?>
<meta charset="utf-8">
<?php
        ?>
        <script>

window.showToast = function(msg, type = 'info') {
    let container = document.getElementById('ssp-toast-container');
    if (!container) {
        container = document.createElement('div');
        container.id = 'ssp-toast-container';
        container.style.position = 'fixed';
        container.style.bottom = '32px';
        container.style.left = '32px';
        container.style.zIndex = '9999';
        container.style.display = 'flex';
        container.style.flexDirection = 'column';
        container.style.gap = '10px';
        document.body.appendChild(container);
    }
    
    let toast = document.createElement('div');
    toast.className = 'ssp-toast toast-' + type;
    toast.setAttribute('role', 'alert');
    toast.setAttribute('aria-live', 'assertive');
    toast.innerHTML = (type === 'success' ? '<svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" style="vertical-align:middle;margin-left:4px;"><polyline points="20 6 9 17 4 12"/></svg>' : (type === 'error' ? '<svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" style="vertical-align:middle;margin-left:4px;"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>' : '<svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" style="vertical-align:middle;margin-left:4px;"><circle cx="12" cy="12" r="10"/><line x1="12" y1="16" x2="12" y2="12"/><line x1="12" y1="8" x2="12.01" y2="8"/></svg>')) + msg;
    
    container.appendChild(toast);
    
    // Trigger animation
    setTimeout(() => toast.classList.add('show'), 10);
    
    setTimeout(() => {
        toast.classList.remove('show');
        setTimeout(() => toast.remove(), 300);
    }, 4000);
};


document.addEventListener("DOMContentLoaded", function() {
    document.querySelectorAll('.ssp-logs-table').forEach(table => {
        let headers = [];
        table.querySelectorAll('th').forEach(th => headers.push(th.innerText.trim()));
        if (headers.length > 0) {
            table.querySelectorAll('tbody tr').forEach(row => {
                row.querySelectorAll('td').forEach((td, index) => {
                    if (headers[index]) {
                        td.setAttribute('data-label', headers[index]);
                    }
                });
            });
        }
    });
});


            window.setBtnLoading = function(btn, isLoading) {
                if (!btn) return;
                if (isLoading) {
                    if (!btn.dataset.originalText) btn.dataset.originalText = btn.innerHTML;
                    btn.disabled = true;
                    btn.innerHTML = '<span class="ssp-spinner" style="display:inline-block;width:14px;height:14px;border:2px solid currentColor;border-right-color:transparent;border-radius:50%;animation:spin 0.75s linear infinite;vertical-align:middle;margin-left:6px;"></span> ' + (btn.dataset.loadingText || 'در حال پردازش...');
                    if (btn._sspLoadTimeout) clearTimeout(btn._sspLoadTimeout);
                    btn._sspLoadTimeout = setTimeout(function() {
                        if (btn && btn.disabled) {
                            btn.disabled = false;
                            if (btn.dataset.originalText) btn.innerHTML = btn.dataset.originalText;
                        }
                    }, 15000);
                } else {
                    if (btn._sspLoadTimeout) {
                        clearTimeout(btn._sspLoadTimeout);
                        btn._sspLoadTimeout = null;
                    }
                    btn.disabled = false;
                    if (btn.dataset.originalText) btn.innerHTML = btn.dataset.originalText;
                }
            };

        (function(){
            var t = localStorage.getItem('ssp_theme');
            var theme = (t === 'dark') ? 'ssp-theme-dark' : 'ssp-theme-light';
            document.documentElement.classList.add(theme);
            document.body.classList.add('ssp-page-active', theme);
            document.addEventListener('DOMContentLoaded', function() {
                var wrap = document.querySelector('.ssp-wrap');
                if (wrap) {
                    wrap.classList.remove('ssp-theme-light', 'ssp-theme-dark');
                    wrap.classList.add(theme);
                }
                var header = document.querySelector('header.site-head');
                if (header) {
                    var lastScroll = 0;
                    window.addEventListener('scroll', function() {
                        var cur = window.pageYOffset || document.documentElement.scrollTop;
                        if (cur > 50 && cur > lastScroll) {
                            header.classList.add('header-hidden');
                        } else {
                            header.classList.remove('header-hidden');
                        }
                        lastScroll = cur <= 0 ? 0 : cur;
                    }, { passive: true });
                }
            });
        })();

        </script>

        <style>
        body.ssp-page-active, body.ssp-page-active * { cursor: auto !important; }
        body.ssp-page-active a, body.ssp-page-active button, body.ssp-page-active [role="button"],
        body.ssp-page-active label, body.ssp-page-active .ssp-tab-btn, body.ssp-page-active .ssp-toggle,
        body.ssp-page-active .ssp-card[onclick] { cursor: pointer !important; }
        body.ssp-page-active input, body.ssp-page-active textarea, body.ssp-page-active select { cursor: text !important; }
        body.ssp-page-active input[type="checkbox"], body.ssp-page-active input[type="radio"] { cursor: pointer !important; }
        body.ssp-page-active input:disabled, body.ssp-page-active button:disabled { cursor: not-allowed !important; }

        /* === Theme-independent isolation === */
        body.ssp-page-active {
            background: #0f172a !important;
            overflow-x: hidden !important;
        }
        body.ssp-page-active .site-main,
        body.ssp-page-active #main {
            margin: 0 !important;
            padding: 0 !important;
            background: transparent !important;
        }
        body.ssp-page-active .entry,
        body.ssp-page-active article {
            margin: 0 !important;
            padding: 0 !important;
            background: transparent !important;
            border: none !important;
            box-shadow: none !important;
        }
        body.ssp-page-active .page-head {
            display: none !important;
        }
        body.ssp-page-active .wrap.entry-content,
        body.ssp-page-active .wrap {
            padding: 0 !important;
            max-width: none !important;
        }
        body.ssp-page-active footer,
        body.ssp-page-active .footer-wrap,
        body.ssp-page-active .footer-isle,
        body.ssp-page-active .bg-ambient,
        body.ssp-page-active .bg-orb,
        body.ssp-page-active .bg-grain,
        body.ssp-page-active .spine,
        body.ssp-page-active .to-top {
            display: none !important;
        }

        /* Header hide on scroll */
        body.ssp-page-active header.site-head {
            position: fixed !important;
            top: 0 !important;
            left: 0 !important;
            right: 0 !important;
            z-index: 1000 !important;
            transition: transform 0.3s ease !important;
        }
        body.ssp-page-active header.site-head.header-hidden {
            transform: translateY(-110%) !important;
        }

        @font-face { font-family: 'IRANSansX'; src: url('/wp-content/themes/webyar-growth/assets/fonts/IRANSansXFaNum-Light.woff2') format('woff2'); font-weight: 300; font-style: normal; font-display: swap; }
        @font-face { font-family: 'IRANSansX'; src: url('/wp-content/themes/webyar-growth/assets/fonts/IRANSansXFaNum-Regular.woff2') format('woff2'); font-weight: 400; font-style: normal; font-display: swap; }
        @font-face { font-family: 'IRANSansX'; src: url('/wp-content/themes/webyar-growth/assets/fonts/IRANSansXFaNum-Bold.woff2') format('woff2'); font-weight: 700; font-style: normal; font-display: swap; }

        .ssp-particles-canvas { position: fixed; top: 0; left: 0; width: 100%; height: 100%; z-index: 0; pointer-events: none; opacity: 0.3; }

        /* Reusable SVG icon classes - matching sidebar tab icons */
        .ssp-icon { display: inline-flex; align-items: center; justify-content: center; width: 1em; height: 1em; vertical-align: -0.125em; flex-shrink: 0; }
        .ssp-icon svg { width: 100%; height: 100%; fill: none; stroke: currentColor; stroke-width: 2; }
        .ssp-icon-sm svg { width: 14px; height: 14px; }
        .ssp-icon-md svg { width: 18px; height: 18px; }
        .ssp-icon-lg svg { width: 24px; height: 24px; }

        .ssp-theme-light {
            --bg: #F8FAFC; --bg-alt: #F1F5F9; --card: #FFFFFF;
            --text: #1E293B; --text-muted: #64748B; --text-subtle: #94A3B8;
            --border: #E2E8F0; --border-hover: #CBD5E1;
            --accent: #4F46E5; --accent-hover: #4338CA;
            --accent-soft: rgba(79, 70, 229, 0.08);
            --success: #10B981; --success-soft: rgba(16, 185, 129, 0.1);
            --error: #EF4444; --error-soft: rgba(239, 68, 68, 0.1);
            --warning: #F59E0B; --warning-soft: rgba(245, 158, 11, 0.1);
            --info: #3B82F6; --info-soft: rgba(59, 130, 246, 0.1);
            --toggle-off-bg: #CBD5E1; --toggle-off-border: #94A3B8; --toggle-off-dot: #FFFFFF;
        }
        .ssp-theme-dark {
            --bg: #111318; --bg-alt: #181B22; --card: #1E2128;
            --text: #F0F2F5; --text-muted: rgba(240,242,245,0.7);
            --text-subtle: rgba(240,242,245,0.45);
            --border: rgba(255,255,255,0.1); --border-hover: rgba(56,189,248,0.35);
            --accent: #38BDF8; --accent-hover: #0EA5E9;
            --accent-soft: rgba(56,189,248,0.12);
            --success: #34D399; --success-soft: rgba(52, 211, 153, 0.12);
            --error: #F87171; --error-soft: rgba(248, 113, 113, 0.12);
            --warning: #FBBF24; --warning-soft: rgba(251, 191, 36, 0.12);
            --info: #60A5FA; --info-soft: rgba(96, 165, 250, 0.12);
            --toggle-off-bg: #4B5563; --toggle-off-border: #6B7280; --toggle-off-dot: #E5E7EB;
        }

        body.ssp-page-active { transition: background 0.3s !important; }

        .ssp-wrap { font-family: 'IRANSansX', sans-serif !important; color: var(--text); min-height: 100vh; direction: rtl; padding: 100px 20px; position: relative; z-index: 1; }
        .ssp-admin-view.ssp-wrap { margin: -20px -20px 0 -20px; }
        .ssp-container { max-width: 1400px; margin: 0 auto; position: relative; z-index: 2; }

        .ssp-header { display: flex; align-items: center; justify-content: space-between; background: var(--card); padding: 20px 24px; border-radius: 16px; border: 1px solid var(--border); margin-bottom: 24px; flex-wrap: wrap; gap: 16px; }
        .ssp-header-left { display: flex; align-items: center; gap: 16px; }
        .ssp-header-icon { width: 48px; height: 48px; background: var(--accent-soft); border: 1px solid var(--accent); color: var(--accent); border-radius: 12px; display: flex; align-items: center; justify-content: center; font-size: 1.5rem; }
        .ssp-header-title h1 { font-size: 1.3rem; font-weight: 700; color: var(--text); margin: 0; }
        .ssp-header-title p { color: var(--text-muted); margin: 4px 0 0; font-size: 0.85rem; }

        .ssp-profile-switcher {
            min-width: 160px;
            padding: 6px 10px;
            font-size: 0.8rem;
            border-radius: 8px;
            cursor: pointer;
        }
        .ssp-profile-switcher option { direction: rtl; }

        .ssp-theme-toggle { width: 44px; height: 44px; background: var(--bg-alt); border: 1px solid var(--border); border-radius: 12px; color: var(--text); display: flex; align-items: center; justify-content: center; position: relative; overflow: hidden; transition: border-color 0.2s, color 0.2s; }
        .ssp-theme-toggle:hover { border-color: var(--accent); color: var(--accent); }
        .ssp-theme-toggle svg { width: 20px; height: 20px; transition: transform 0.4s, opacity 0.3s; position: absolute; }
        .ssp-theme-toggle .sun-icon { opacity: 0; transform: rotate(-90deg) scale(0.5); }
        .ssp-theme-toggle .moon-icon { opacity: 1; transform: rotate(0) scale(1); }
        .ssp-theme-dark .ssp-theme-toggle .sun-icon { opacity: 1; transform: rotate(0) scale(1); }
        .ssp-theme-dark .ssp-theme-toggle .moon-icon { opacity: 0; transform: rotate(90deg) scale(0.5); }

        .ssp-status-pill { display: inline-flex; align-items: center; gap: 6px; padding: 6px 14px; border-radius: 999px; font-size: 0.8rem; font-weight: 600; }
        .ssp-status-pill.ok { background: var(--success-soft); color: var(--success); }
        .ssp-status-pill.warn { background: var(--warning-soft); color: var(--warning); }
        .ssp-status-pill.pro { background: var(--accent-soft); color: var(--accent); }
        .ssp-status-dot { width: 8px; height: 8px; border-radius: 50%; background: currentColor; animation: pulse-dot 2s infinite; }
        @keyframes pulse-dot { 0%, 100% { opacity: 1; } 50% { opacity: 0.4; } }

        .ssp-main { display: flex; gap: 24px; flex-wrap: wrap; position: relative; z-index: 2; }
        .ssp-sidebar { flex: 1 0 240px; max-width: 250px; background: var(--card); border-radius: 16px; padding: 8px; border: 1px solid var(--border); height: fit-content; position: sticky; top: 20px; }
        .ssp-sidebar-section {
            font-size: 0.65rem; font-weight: 700; color: var(--text-subtle);
            text-transform: uppercase; letter-spacing: 0.08em;
            padding: 16px 14px 6px; margin-top: 4px;
            border-top: 1px solid var(--border);
            display: flex; align-items: center; gap: 6px;
            user-select: none; pointer-events: none;
        }
        .ssp-sidebar-section:first-child { border-top: none; margin-top: 0; padding-top: 8px; }
        .ssp-sidebar-section::before {
            content: ''; width: 3px; height: 3px; border-radius: 50%;
            background: var(--accent); opacity: 0.5; flex-shrink: 0;
        }
        .ssp-tab-btn {
            display: flex; align-items: center; gap: 10px; width: 100%; text-align: right;
            padding: 10px 12px; border-radius: 10px; background: transparent;
            border: 1px solid transparent; color: var(--text-muted);
            font-weight: 500; font-size: 0.85rem; transition: all 0.2s ease;
            margin-bottom: 1px; font-family: inherit; cursor: pointer;
        }
        .ssp-tab-btn svg { flex-shrink: 0; opacity: 0.6; transition: opacity 0.2s; }
        .ssp-tab-btn:hover svg, .ssp-tab-btn.active svg { opacity: 1; }
        .ssp-tab-btn:hover { background: var(--bg-alt); color: var(--text); border-color: transparent; }
        .ssp-tab-btn.active {
            background: var(--accent-soft); color: var(--accent); font-weight: 700;
        }

        .ssp-content { flex: 3 1 600px; background: var(--card); border-radius: 20px; padding: 32px; border: 1px solid var(--border); position: relative; min-height: 600px; }
        .tab-content { display: none; animation: fadeIn 0.15s ease-out; }
        .tab-content.active { display: block; }
        @keyframes fadeIn { from { opacity: 0; } to { opacity: 1; } }

        .ssp-content h2,
        .tab-content h2,
        body.ssp-page-active .ssp-content h2,
        body.ssp-page-active .tab-content h2,
        .ssp-tool-title {
            font-size: 1.5rem;
            font-weight: 700;
            margin: 0 0 8px;
            color: var(--text) !important;
            background: none !important;
            -webkit-background-clip: initial !important;
            -webkit-text-fill-color: initial !important;
            display: flex !important;
            align-items: center !important;
            flex-direction: row !important;
            flex-wrap: nowrap !important;
            gap: 10px !important;
            line-height: 1.3 !important;
        }
        .ssp-content h2 svg,
        .tab-content h2 svg,
        body.ssp-page-active .ssp-content h2 svg,
        .ssp-tool-title svg {
            display: inline-block !important;
            vertical-align: middle !important;
            flex-shrink: 0 !important;
            margin: 0 !important;
        }
        .ssp-content h2 span,
        .tab-content h2 span,
        body.ssp-page-active .ssp-content h2 span,
        .ssp-tool-title span {
            display: inline-block !important;
            vertical-align: middle !important;
        }
        .ssp-section-desc { color: var(--text-muted); margin-bottom: 28px; font-size: 0.95rem; line-height: 1.6; }

        .ssp-guide { background: var(--accent-soft); border: 2px solid var(--accent); border-radius: 16px; padding: 20px; margin-bottom: 24px; }
        .ssp-guide h3 { color: var(--accent); margin: 0 0 12px; font-size: 1.1rem; display: flex; align-items: center; gap: 8px; }
        .ssp-guide-steps { margin: 0; padding-right: 20px; }
        .ssp-guide-steps li { margin: 8px 0; color: var(--text-muted); font-size: 0.9rem; line-height: 1.7; }
        .ssp-guide-steps li strong { color: var(--text); }
        .ssp-guide-steps li .ssp-guide-hint { display: block; font-size: 0.8rem; color: var(--text-subtle); margin-top: 4px; background: var(--bg-alt); padding: 6px 10px; border-radius: 6px; }

        .ssp-tooltip { position: relative; display: inline-flex; align-items: center; margin-right: 6px; cursor: help; }
        .ssp-tooltip-icon { width: 18px; height: 18px; background: var(--accent); color: white; border-radius: 50%; display: inline-flex; align-items: center; justify-content: center; font-size: 11px; font-weight: 700; }
        .ssp-tooltip:hover .ssp-tooltip-text { opacity: 1; visibility: visible; transform: translateY(0); }
        .ssp-tooltip-text { position: absolute; bottom: calc(100% + 10px); right: 50%; transform: translateX(50%) translateY(4px); background: var(--text); color: var(--card); padding: 10px 14px; border-radius: 8px; font-size: 0.8rem; font-weight: 500; opacity: 0; visibility: hidden; transition: opacity 0.2s, visibility 0.2s; z-index: 100; max-width: 320px; line-height: 1.6; white-space: normal; box-shadow: 0 4px 12px rgba(0,0,0,0.2); }
        .ssp-tooltip-text::after { content: ''; position: absolute; top: 100%; right: 50%; transform: translateX(50%); border: 6px solid transparent; border-top-color: var(--text); }

        .ssp-form-group { margin-bottom: 20px; }
        .ssp-label { display: flex; align-items: center; font-weight: 600; color: var(--text); margin-bottom: 8px; font-size: 0.9rem; }
        .ssp-hint { color: var(--text-subtle); font-size: 0.8rem; margin-top: 6px; line-height: 1.5; }
        .ssp-input, .ssp-select, .ssp-textarea { width: 100%; padding: 11px 14px; background: var(--card); border: 1px solid var(--border); border-radius: 10px; font-size: 0.9rem; color: var(--text); font-family: inherit; box-sizing: border-box; transition: border-color 0.2s, box-shadow 0.2s; }
        .ssp-input:focus, .ssp-select:focus, .ssp-textarea:focus { border-color: var(--accent); outline: none; box-shadow: 0 0 0 3px var(--accent-soft); }
        .ssp-input.invalid, .ssp-select.invalid, .ssp-textarea.invalid { border-color: var(--error); box-shadow: 0 0 0 3px var(--error-soft); }
        .ssp-textarea { resize: vertical; min-height: 90px; }
        .ssp-grid-2 { display: grid; grid-template-columns: repeat(auto-fill, minmax(280px, 1fr)); gap: 16px; }
        .ssp-grid-3 { display: grid; grid-template-columns: repeat(auto-fill, minmax(220px, 1fr)); gap: 16px; }
        .ssp-grid-4 { display: grid; grid-template-columns: repeat(auto-fill, minmax(160px, 1fr)); gap: 12px; }

        .ssp-card { background: var(--card); padding: 20px; border-radius: 14px; border: 1px solid var(--border); transition: all 0.2s ease; }
        .ssp-card:hover { border-color: var(--border-hover); transform: translateY(-1px); box-shadow: 0 4px 12px rgba(0,0,0,0.05); }
        .ssp-card h3 { font-size: 0.85rem; font-weight: 600; color: var(--text-muted); margin: 0 0 8px; display: flex; align-items: center; gap: 6px; }
        .ssp-card .ssp-val { font-size: 1.6rem; font-weight: 700; color: var(--accent); }
        .ssp-card .ssp-sub { font-size: 0.8rem; color: var(--text-muted); margin-top: 4px; }

        .ssp-btn-primary { background: var(--accent); color: white; padding: 12px 24px; border: none; border-radius: 999px; font-weight: 700; font-size: 0.9rem; display: inline-flex; align-items: center; gap: 8px; font-family: inherit; transition: all 0.2s ease; }
        .ssp-btn-primary:hover { background: var(--accent-hover); transform: translateY(-1px); box-shadow: 0 4px 12px rgba(79, 70, 229, 0.3); }
        .ssp-btn-primary:disabled { opacity: 0.6; transform: none; box-shadow: none; }
        .ssp-btn-primary .ssp-btn-spinner { display: none; width: 16px; height: 16px; border: 2px solid rgba(255,255,255,0.3); border-top-color: white; border-radius: 50%; animation: spin 0.6s linear infinite; }
        .ssp-btn-primary.loading .ssp-btn-spinner { display: inline-block; }
        .ssp-btn-primary.loading .ssp-btn-text { display: none; }
        .ssp-btn-secondary { background: var(--bg-alt); color: var(--text); border: 1px solid var(--border); padding: 10px 18px; border-radius: 10px; font-weight: 600; font-family: inherit; font-size: 0.85rem; transition: all 0.2s ease; }
        .ssp-btn-secondary:hover { border-color: var(--accent); color: var(--accent); }
        .ssp-btn-test { background: transparent; color: var(--accent); border: 1px solid var(--border); padding: 8px 14px; border-radius: 8px; font-weight: 600; font-size: 0.8rem; margin-top: 10px; display: inline-flex; align-items: center; gap: 6px; font-family: inherit; transition: all 0.2s ease; }
        .ssp-btn-test:hover { background: var(--accent-soft); border-color: var(--accent); }
        .ssp-btn-danger { background: var(--error-soft); color: var(--error); border: 1px solid var(--error); padding: 8px 14px; border-radius: 8px; font-weight: 600; font-family: inherit; font-size: 0.8rem; transition: all 0.2s ease; }
        .ssp-btn-danger:hover { background: var(--error); color: white; }
        .ssp-btn-success { background: var(--success-soft); color: var(--success); border: 1px solid var(--success); padding: 8px 14px; border-radius: 8px; font-weight: 600; font-family: inherit; font-size: 0.8rem; }

        .ssp-toggle { display: flex; align-items: center; gap: 10px; margin-top: 10px; font-size: 0.9rem; color: var(--text-muted); position: relative; z-index: 1; cursor: pointer; }
        .ssp-toggle input { position: absolute; opacity: 0; width: 0; height: 0; pointer-events: none; }
        .ssp-toggle label { display: flex; align-items: center; gap: 10px; cursor: pointer; }
        .ssp-toggle-slider { width: 44px; height: 24px; background: #94A3B8; border: 2px solid #64748B; border-radius: 999px; position: relative; transition: all 0.3s; flex-shrink: 0; cursor: pointer; }
        .ssp-toggle-slider::before { content: ''; position: absolute; top: 1px; left: 1px; width: 18px; height: 18px; background: #FFFFFF; border-radius: 50%; transition: all 0.3s; box-shadow: 0 1px 4px rgba(0,0,0,0.4); display: block; }
        .ssp-toggle input:checked + .ssp-toggle-slider { background: #4F46E5; border-color: #4F46E5; }
        .ssp-toggle input:checked + .ssp-toggle-slider::before { background: #FFFFFF; transform: translateX(20px); }
        .ssp-toggle:hover .ssp-toggle-slider { border-color: #4F46E5; }

        .ssp-feature-card { background: var(--bg-alt); padding: 18px; border-radius: 14px; border: 1px solid var(--border); transition: all 0.2s ease; overflow: visible; }
        .ssp-feature-card.active { background: var(--accent-soft); border-color: var(--accent); }
        .ssp-feature-card-title { font-weight: 700; color: var(--text); font-size: 0.95rem; margin: 0 0 4px; display: flex; align-items: center; gap: 8px; }
        .ssp-feature-card-desc { color: var(--text-muted); font-size: 0.82rem; line-height: 1.5; margin: 0; }
        .ssp-feature-card-head { display: flex; justify-content: space-between; align-items: flex-start; gap: 12px; }

        .ssp-input-group { position: relative; display: flex; align-items: center; }
        .ssp-input-group .ssp-input { padding-left: 44px; }
        .ssp-input-group .ssp-eye-btn { position: absolute; left: 12px; background: none; border: none; color: var(--text-muted); padding: 4px; cursor: pointer !important; }
        .ssp-input-group .ssp-eye-btn:hover { color: var(--accent); }

        .ssp-badge { padding: 3px 10px; border-radius: 6px; font-size: 0.72rem; font-weight: 700; display: inline-flex; align-items: center; gap: 4px; }
        .ssp-badge.free { background: var(--bg-alt); color: var(--text-muted); }
        .ssp-badge.pro { background: var(--accent-soft); color: var(--accent); border: 1px solid var(--accent); }
        .ssp-badge.success { background: var(--success-soft); color: var(--success); }
        .ssp-badge.error { background: var(--error-soft); color: var(--error); }
        .ssp-badge.info { background: var(--info-soft); color: var(--info); }
        .ssp-badge.warning { background: var(--warning-soft); color: var(--warning); }
        .ssp-badge.active { background: var(--success-soft); color: var(--success); }
        .ssp-badge.inactive { background: var(--error-soft); color: var(--error); }

        .ssp-table-wrap { overflow-x: auto; border-radius: 12px; border: 1px solid var(--border); margin-top: 16px; }
        .ssp-logs-table { width: 100%; border-collapse: collapse; font-size: 0.85rem; min-width: 700px; }
        .ssp-logs-table th { text-align: right; padding: 14px; background: var(--bg-alt); color: var(--text-muted); font-weight: 600; border-bottom: 1px solid var(--border); font-size: 0.8rem; }
        .ssp-logs-table td { padding: 12px 14px; border-bottom: 1px solid var(--border); color: var(--text); }
        .ssp-logs-table tr:last-child td { border-bottom: none; }
        .ssp-logs-table tr:hover { background: var(--bg-alt); }

        .ssp-connection-status { font-size: 0.8rem; display: inline-flex; align-items: center; gap: 6px; margin-top: 8px; }
        .ssp-connection-status.success { color: var(--success); }
        .ssp-connection-status.error { color: var(--error); }
        .ssp-output-box { background: var(--bg-alt); border: 1px solid var(--border); border-radius: 10px; padding: 12px 16px; font-size: 0.85rem; color: var(--text); font-family: monospace; direction: ltr; text-align: left; white-space: pre-wrap; word-break: break-all; }

        .ssp-loader { display: none; position: absolute; inset: 0; background: var(--card); opacity: 0.95; justify-content: center; align-items: center; border-radius: 20px; z-index: 100; flex-direction: column; gap: 16px; color: var(--accent); }
        .ssp-loader.active { display: flex; }
        .ssp-spinner { width: 40px; height: 40px; border: 3px solid var(--border); border-top-color: var(--accent); border-radius: 50%; animation: spin 0.8s linear infinite; }
        @keyframes spin { to { transform: rotate(360deg); } }

        /* Enhanced Toast */
        .ssp-toast { position: fixed; bottom: 32px; left: 32px; background: var(--card); border: 1px solid var(--accent); color: var(--text); padding: 14px 22px; border-radius: 12px; font-weight: 600; transform: translateY(100px); opacity: 0; transition: transform 0.3s cubic-bezier(0.4, 0, 0.2, 1), opacity 0.3s; z-index: 9999; display: flex; align-items: center; gap: 10px; box-shadow: 0 10px 30px rgba(0,0,0,0.15); border-left: 4px solid var(--accent); }
        .ssp-toast.show { transform: translateY(0); opacity: 1; }
        .ssp-toast.toast-success { border-left-color: var(--success); }
        .ssp-toast.toast-error { border-left-color: var(--error); }
        .ssp-toast.toast-warning { border-left-color: var(--warning); }

        .ssp-empty { text-align: center; padding: 40px 20px; color: var(--text-muted); }
        .ssp-empty-icon { font-size: 3rem; margin-bottom: 12px; opacity: 0.5; }

        .ssp-item-card { background: var(--bg-alt); padding: 18px; border-radius: 12px; border: 1px solid var(--border); margin-bottom: 12px; transition: all 0.2s ease; }
        .ssp-item-card:hover { border-color: var(--border-hover); transform: translateY(-1px); }
        .ssp-item-card-head { display: flex; justify-content: space-between; align-items: center; gap: 12px; flex-wrap: wrap; }
        .ssp-item-card-title { font-weight: 700; color: var(--text); font-size: 1rem; display: flex; align-items: center; gap: 8px; flex-wrap: wrap; }
        .ssp-item-card-meta { color: var(--text-muted); font-size: 0.85rem; margin-top: 4px; word-break: break-all; }
        .ssp-item-card-actions { display: flex; gap: 6px; flex-wrap: wrap; margin-top: 10px; }

        .ssp-add-form { background: linear-gradient(135deg, var(--accent-soft), transparent); border: 2px dashed var(--accent); border-radius: 16px; padding: 20px 20px 28px; margin-top: 16px; overflow: visible; }
        .ssp-add-form h3 { color: var(--accent); margin: 0 0 16px; font-size: 1.1rem; display: flex; align-items: center; gap: 8px; }

        .ssp-counter { display: inline-flex; align-items: center; gap: 6px; padding: 4px 12px; background: var(--bg-alt); border-radius: 999px; font-size: 0.8rem; font-weight: 700; color: var(--text-muted); border: 1px solid var(--border); }
        .ssp-counter .count { color: var(--accent); font-size: 1rem; }
        .ssp-counter.max .count { color: var(--warning); }

        .ssp-upgrade-banner { background: var(--accent-soft); border: 1px solid var(--accent); border-radius: 16px; padding: 18px 22px; margin-bottom: 20px; display: flex; justify-content: space-between; align-items: center; gap: 16px; flex-wrap: wrap; }
        .ssp-upgrade-banner h3 { margin: 0 0 4px; color: var(--accent); font-size: 1.05rem; }
        .ssp-upgrade-banner p { margin: 0; color: var(--text-muted); font-size: 0.85rem; }

        .ssp-ai-providers { display: grid; grid-template-columns: repeat(auto-fill, minmax(160px, 1fr)); gap: 10px; margin-bottom: 20px; }
        .ssp-ai-provider-card { background: var(--bg-alt); padding: 14px; border-radius: 12px; border: 2px solid var(--border); transition: all 0.2s ease; text-align: center; position: relative; }
        .ssp-ai-provider-card:hover { border-color: var(--border-hover); }
        .ssp-ai-provider-card.selected { background: var(--accent-soft); border-color: var(--accent); }
        .ssp-ai-provider-card.selected::after { content: '\2713'; position: absolute; top: 6px; left: 6px; background: var(--accent); color: white; width: 20px; height: 20px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-weight: bold; font-size: 11px; }
        .ssp-ai-provider-icon { font-size: 1.8rem; margin-bottom: 6px; }
        .ssp-ai-provider-name { font-weight: 700; color: var(--text); font-size: 0.85rem; }
        .ssp-ai-provider-link { color: var(--accent); font-size: 0.75rem; text-decoration: none; display: block; margin-top: 4px; }

        .ssp-ai-test-result { padding: 14px; border-radius: 10px; margin-top: 12px; font-size: 0.88rem; display: none; }
        .ssp-ai-test-result.show { display: block; animation: fadeIn 0.3s ease; }
        .ssp-ai-test-result.success { background: var(--success-soft); color: var(--success); border: 1px solid var(--success); }
        .ssp-ai-test-result.error { background: var(--error-soft); color: var(--error); border: 1px solid var(--error); }
        .ssp-ai-test-response { background: var(--card); padding: 10px; border-radius: 6px; margin-top: 8px; font-family: monospace; font-size: 0.82rem; color: var(--text); border: 1px solid var(--border); }

        .ssp-cron-status { background: var(--bg-alt); border: 1px solid var(--border); border-radius: 12px; padding: 16px; margin-bottom: 20px; display: flex; align-items: center; gap: 12px; flex-wrap: wrap; }
        .ssp-cron-status.ok { background: var(--success-soft); border-color: var(--success); }
        .ssp-cron-status.warn { background: var(--warning-soft); border-color: var(--warning); }

        .ssp-step-indicator { display: flex; gap: 8px; margin-bottom: 24px; flex-wrap: wrap; }
        .ssp-step { flex: 1; min-width: 120px; padding: 12px; background: var(--bg-alt); border: 1px solid var(--border); border-radius: 10px; text-align: center; font-size: 0.82rem; color: var(--text-muted); transition: all 0.3s ease; }
        .ssp-step.completed { background: var(--success-soft); color: var(--success); border-color: var(--success); }
        .ssp-step.current { background: var(--accent-soft); color: var(--accent); border-color: var(--accent); font-weight: 700; }
        .ssp-step-num { display: block; font-size: 1.2rem; font-weight: 700; margin-bottom: 4px; }

        code { background: var(--bg-alt); padding: 2px 8px; border-radius: 6px; font-size: 0.85rem; color: var(--accent); border: 1px solid var(--border); font-family: monospace; }

        .ssp-saved-indicator { display: inline-flex; align-items: center; gap: 6px; color: var(--success); font-size: 0.85rem; font-weight: 600; opacity: 0; transition: opacity 0.3s; margin-right: 12px; }
        .ssp-saved-indicator.show { opacity: 1; }

        /* Modal Styles */
        .ssp-modal-overlay { display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.5); z-index: 10000; justify-content: center; align-items: center; padding: 20px; backdrop-filter: blur(4px); }
        .ssp-modal-overlay.active { display: flex; }
        .ssp-modal { background: var(--card); border-radius: 20px; border: 1px solid var(--border); width: 100%; max-width: 560px; max-height: 90vh; overflow-y: auto; animation: modalIn 0.3s cubic-bezier(0.4, 0, 0.2, 1); }
        @keyframes modalIn { from { opacity: 0; transform: scale(0.95) translateY(10px); } to { opacity: 1; transform: scale(1) translateY(0); } }
        .ssp-modal-header { display: flex; justify-content: space-between; align-items: center; padding: 20px 24px; border-bottom: 1px solid var(--border); }
        .ssp-modal-header h3 { margin: 0; font-size: 1.1rem; color: var(--text); display: flex; align-items: center; gap: 8px; }
        .ssp-modal-close { width: 36px; height: 36px; border-radius: 10px; border: 1px solid var(--border); background: var(--bg-alt); color: var(--text-muted); font-size: 1.2rem; cursor: pointer !important; display: flex; align-items: center; justify-content: center; transition: all 0.2s; }
        .ssp-modal-close:hover { background: var(--error-soft); border-color: var(--error); color: var(--error); }
        .ssp-modal-body { padding: 24px; }
        .ssp-modal-actions { display: flex; gap: 10px; justify-content: flex-start; padding: 16px 24px; border-top: 1px solid var(--border); }

        /* Calendar Styles */
        .ssp-calendar { display: grid; grid-template-columns: repeat(7, 1fr); gap: 4px; }
        .ssp-calendar-day { padding: 8px; background: var(--bg-alt); border-radius: 8px; min-height: 80px; border: 1px solid var(--border); transition: all 0.2s; }
        .ssp-calendar-day:hover { border-color: var(--accent); }
        .ssp-calendar-day.today { background: var(--accent-soft); border-color: var(--accent); }
        .ssp-calendar-day.has-posts { background: var(--success-soft); border-color: var(--success); }
        .ssp-calendar-post { font-size: 0.7rem; padding: 2px 4px; background: var(--card); border-radius: 4px; margin: 2px 0; border: 1px solid var(--border); }

        /* SEO Score Styles */
        .ssp-seo-score { display: inline-flex; align-items: center; justify-content: center; width: 60px; height: 60px; border-radius: 50%; font-size: 1.2rem; font-weight: 700; }
        .ssp-seo-good { background: var(--success-soft); color: var(--success); border: 2px solid var(--success); }
        .ssp-seo-medium { background: var(--warning-soft); color: var(--warning); border: 2px solid var(--warning); }
        .ssp-seo-bad { background: var(--error-soft); color: var(--error); border: 2px solid var(--error); }

        /* SEO Sub-tabs */
        .ssp-seo-subtab { padding: 8px 16px; border-radius: 8px; border: 1px solid var(--border); background: var(--bg); color: var(--text-muted); font-size: 0.85rem; cursor: pointer; transition: all 0.2s; white-space: nowrap; font-family: inherit; }
        .ssp-seo-subtab:hover { border-color: var(--accent); color: var(--accent); }
        .ssp-seo-subtab.active { background: var(--accent); color: white; border-color: var(--accent); font-weight: 600; }

        /* Draft Card Styles */
        .ssp-draft-card { background: var(--bg-alt); padding: 16px; border-radius: 12px; border: 1px solid var(--border); transition: all 0.2s; }
        .ssp-draft-card:hover { border-color: var(--accent); transform: translateY(-1px); }

        /* Template Card Styles */
        .ssp-template-card { background: var(--bg-alt); padding: 16px; border-radius: 12px; border: 1px solid var(--border); transition: all 0.2s; }
        .ssp-template-card:hover { border-color: var(--accent); }

        /* Mobile Floating Action Button (FAB) */
        .ssp-fab {
            display: none; position: fixed; bottom: 28px; left: 28px; z-index: 999997;
            width: 56px; height: 56px; border-radius: 16px; border: none;
            background: var(--accent); color: white;
            box-shadow: 0 4px 20px rgba(79, 70, 229, 0.4);
            cursor: pointer; align-items: center; justify-content: center;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }
        .ssp-fab:hover { transform: scale(1.08); box-shadow: 0 6px 28px rgba(79, 70, 229, 0.5); }
        .ssp-fab:active { transform: scale(0.95); }
        .ssp-fab svg { width: 24px; height: 24px; transition: all 0.3s; }
        .ssp-fab-icon-close { display: none; }
        .ssp-fab.open .ssp-fab-icon { display: none; }
        .ssp-fab.open .ssp-fab-icon-close { display: block; }
        .ssp-fab.open { background: var(--error); box-shadow: 0 4px 20px rgba(239, 68, 68, 0.4); }

        .ssp-drawer-overlay {
            position: fixed; inset: 0; background: rgba(0,0,0,0.5);
            z-index: 999998; opacity: 0; transition: opacity 0.3s;
            backdrop-filter: blur(4px); -webkit-backdrop-filter: blur(4px);
            pointer-events: none;
        }
        .ssp-drawer-overlay.active { opacity: 1; pointer-events: auto; }

        .ssp-drawer {
            position: fixed; top: 0; right: 0; bottom: 0;
            width: min(320px, 85vw); background: var(--card);
            border-left: 1px solid var(--border); z-index: 999999;
            transform: translateX(100%); transition: transform 0.35s cubic-bezier(0.4, 0, 0.2, 1);
            display: flex; flex-direction: column;
            box-shadow: -8px 0 30px rgba(0,0,0,0.15);
        }
        .ssp-drawer.active { transform: translateX(0); }

        .ssp-drawer-header {
            display: flex; align-items: center; gap: 12px;
            padding: 20px; border-bottom: 1px solid var(--border);
            background: var(--bg); flex-shrink: 0;
        }
        .ssp-drawer-logo { font-size: 1.5rem; }
        .ssp-drawer-title { flex: 1; font-weight: 700; font-size: 1.1rem; color: var(--text); }
        .ssp-drawer-close {
            width: 36px; height: 36px; border-radius: 10px;
            border: 1px solid var(--border); background: var(--bg-alt);
            color: var(--text-muted); display: flex; align-items: center;
            justify-content: center; cursor: pointer; transition: all 0.2s;
        }
        .ssp-drawer-close:hover { background: var(--error-soft); border-color: var(--error); color: var(--error); }
        .ssp-drawer-close svg { width: 18px; height: 18px; }

        .ssp-drawer-body {
            flex: 1; overflow-y: auto; padding: 12px 12px 32px;
            -webkit-overflow-scrolling: touch;
        }
        .ssp-drawer-body::-webkit-scrollbar { width: 4px; }
        .ssp-drawer-body::-webkit-scrollbar-thumb { background: var(--border); border-radius: 4px; }

        .ssp-drawer-group { margin-bottom: 4px; }
        .ssp-drawer-group-title {
            font-size: 0.65rem; font-weight: 700; color: var(--text-subtle);
            text-transform: uppercase; letter-spacing: 0.08em;
            padding: 14px 14px 6px; user-select: none; pointer-events: none;
            border-top: 1px solid var(--border);
            display: flex; align-items: center; gap: 6px;
        }
        .ssp-drawer-group:first-child .ssp-drawer-group-title { border-top: none; }
        .ssp-drawer-group-title::before {
            content: ''; width: 3px; height: 3px; border-radius: 50%;
            background: var(--accent); opacity: 0.5; flex-shrink: 0;
        }
        .ssp-drawer-item {
            display: flex; align-items: center; gap: 12px; width: 100%;
            text-align: right; padding: 11px 14px; border-radius: 10px;
            background: transparent; border: 1px solid transparent;
            color: var(--text-muted); font-weight: 500; font-size: 0.9rem;
            transition: all 0.2s ease; margin-bottom: 2px; font-family: inherit;
            cursor: pointer;
        }
        .ssp-drawer-item:hover { background: var(--bg-alt); color: var(--text); }
        .ssp-drawer-item.active {
            background: var(--accent-soft); color: var(--accent);
            border-color: var(--accent); font-weight: 700;
        }
        .ssp-drawer-item svg {
            width: 20px; height: 20px; flex-shrink: 0;
            stroke: currentColor; fill: none; stroke-width: 2;
            stroke-linecap: round; stroke-linejoin: round;
        }

        @media (max-width: 768px) {
            .ssp-fab { display: flex; }
            .ssp-particles-canvas { display: none !important; }
            .ssp-main { flex-direction: column; }
            .ssp-sidebar { display: none !important; }
            .ssp-content { padding: 20px 16px; overflow-x: hidden; }
            .ssp-grid-2, .ssp-grid-3, .ssp-grid-4 { grid-template-columns: 1fr; }
            .ssp-toast { left: 16px; right: 16px; bottom: 32px; }
            .ssp-modal { max-width: 100%; margin: 10px; }
            .ssp-btn-primary, .ssp-btn-secondary, .ssp-btn-test, .ssp-btn-danger { min-height: 44px; min-width: 44px; }
            .ssp-input, .ssp-select, .ssp-textarea { min-height: 44px; font-size: 16px; }
            .ssp-form-group { margin-bottom: 16px; }
            .ssp-table-wrap { margin: 0 -4px; }
            .ssp-guide, .ssp-add-form, .ssp-item-card { overflow: visible; }

            /* Sticky header on mobile */
            .ssp-header {
                position: sticky;
                top: 0;
                z-index: 100;
                padding: 12px 16px;
                gap: 8px;

            .ssp-header-left { gap: 10px; }
            .ssp-header-icon { width: 40px; height: 40px; }
            .ssp-header-icon svg { width: 18px; height: 18px; }
            .ssp-header-title h1 { font-size: 0.95rem; }
            .ssp-header-title p { font-size: 0.7rem; margin-top: 2px; }

            /* Compact header controls - consistent sizing */
            .ssp-header > div:last-child { gap: 6px; }
            .ssp-header .ssp-profile-switcher { min-width: 0 !important; max-width: 110px; font-size: 0.75rem; padding: 5px 8px; height: 32px; }
            .ssp-header button[title*="مدیریت"] { width: 32px; height: 32px; padding: 0 !important; font-size: 0.8rem; display: inline-flex; align-items: center; justify-content: center; }
            .ssp-header .ssp-status-pill { padding: 4px 10px; font-size: 0.7rem; gap: 4px; }
            .ssp-header .ssp-status-pill .ssp-status-dot { width: 6px; height: 6px; }
            .ssp-header .ssp-status-pill.pro { padding: 2px 8px; font-size: 0.65rem; }
            .ssp-header .ssp-theme-toggle { width: 36px; height: 36px; }
            .ssp-login-btn { padding: 6px 12px !important; font-size: 0.78rem !important; }

            /* === Full-screen fixed mobile layout for shortcode pages === */
            body.ssp-shortcode-page {
                overflow: hidden !important;
                height: 100vh !important;
                height: 100dvh !important;


            body.ssp-shortcode-page .ssp-wrap {
                padding: 0 !important;
                min-height: 100vh !important;
                min-height: 100dvh !important;
                height: 100vh !important;
                height: 100dvh !important;
                display: flex !important;
                flex-direction: column !important;
                overflow: hidden !important;


            body.ssp-shortcode-page .ssp-header {
                position: sticky !important;
                top: 0 !important;
                z-index: 100 !important;
                border-radius: 0 !important;
                margin-bottom: 0 !important;
                flex-shrink: 0 !important;
                border-left: none !important;
                border-right: none !important;


            body.ssp-shortcode-page .ssp-main {
                flex: 1 !important;
                overflow: hidden !important;
                margin: 0 !important;
                gap: 0 !important;
                flex-direction: column !important;


            body.ssp-shortcode-page .ssp-sidebar {
                display: none !important;


            body.ssp-shortcode-page .ssp-content {
                border-radius: 0 !important;
                border-left: none !important;
                border-right: none !important;
                border-bottom: none !important;
                height: 100% !important;
                overflow-y: auto !important;
                -webkit-overflow-scrolling: touch !important;
                padding: 16px !important;
                padding-bottom: 80px !important;
                flex: 1 !important;
                min-height: 0 !important;


            body.ssp-shortcode-page .tab-content.active {
                display: block !important;
                min-height: 100% !important;


            body.ssp-shortcode-page .ssp-modal-overlay {
                z-index: 10001 !important;

        }

        .ob-step { animation: fadeIn 0.3s ease; }

        /* Card enter/remove animations */
        .ssp-card-enter { animation: cardEnter 0.35s cubic-bezier(0.4, 0, 0.2, 1); }
        @keyframes cardEnter { from { opacity: 0; transform: translateY(12px) scale(0.97); } to { opacity: 1; transform: translateY(0) scale(1); } }
        .ssp-item-card.removing { animation: cardRemove 0.3s ease forwards; }
        @keyframes cardRemove { to { opacity: 0; transform: translateX(-20px) scale(0.95); height: 0; padding: 0; margin: 0; overflow: hidden; } }

        @media (prefers-reduced-motion: reduce) {
            .ssp-particles-canvas { display: none !important; }
            *, *::before, *::after { animation-duration: 0.01ms !important; transition-duration: 0.01ms !important; }
        }
        
        @keyframes spin { 100% { transform: rotate(360deg); } }
        .ssp-btn-primary:disabled, .ssp-btn-secondary:disabled, button:disabled { opacity: 0.7; cursor: not-allowed; }
</style>

        <div class="ssp-wrap <?php echo $is_frontend ? 'ssp-frontend-view' : 'ssp-admin-view'; ?>">
            <?php if ($is_impersonating) : ?>
            <div style="background:var(--warning); color:#000; padding:10px 20px; text-align:center; font-size:0.85rem; border-radius:12px; margin-bottom:16px; display:flex; align-items:center; justify-content:center; gap:12px; flex-wrap:wrap;">
                <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                در حال مشاهده پورتال: <strong><?php echo esc_html(get_userdata($user_id)->display_name ?? 'کاربر'); ?></strong>
                <a href="<?php echo admin_url('admin.php?page=ssp-admin-panel'); ?>" style="background:#000; color:var(--warning); padding:6px 16px; border-radius:8px; text-decoration:none; font-weight:700; font-size:0.8rem;">بازگشت به پنل مدیریت</a>
            </div>
            <?php endif; ?>
            <div class="ssp-container">
                <div class="ssp-header">
                    <div class="ssp-header-left">
                        <div class="ssp-header-icon"><svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2"><polygon points="13 2 3 14 12 14 11 22 21 10 12 10 13 2"/></svg></div>
                        <div class="ssp-header-title">
                            <h1>پورتال اتوماسیون هوشمند</h1>
                            <p><?php echo esc_html(wp_get_current_user()->display_name); ?> &bull; <?php echo $plan === 'pro' ? 'Pro' : 'رایگان'; ?></p>
                        </div>
                    </div>
                    <div style="display:flex; gap:10px; align-items:center; flex-wrap:wrap;">
                        <?php if ($plan === 'pro' && count($profiles) > 0) : ?>
                        <div style="position:relative;">
                            <select id="profile_switcher" class="ssp-select ssp-profile-switcher" onchange="switchProfile(this.value)">
                                <?php foreach ($profiles as $p) : ?>
                                <option value="<?php echo (int)$p['id']; ?>" <?php selected($p['id'], $active_profile_id); ?>><?php echo esc_html($p['name']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <button onclick="openProfileModal()" style="background:var(--bg-alt); border:1px solid var(--border); color:var(--text-muted); border-radius:8px; padding:6px 10px; cursor:pointer; font-size:0.75rem;" title="مدیریت پروفایل‌ها"><svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" style="vertical-align:middle;"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1 0 2.83 2 2 0 0 1-2.83 0l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-2 2 2 2 0 0 1-2-2v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83 0 2 2 0 0 1 0-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1-2-2 2 2 0 0 1 2-2h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 0-2.83 2 2 0 0 1 2.83 0l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 2-2 2 2 0 0 1 2 2v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 0 2 2 0 0 1 0 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 2 2 2 2 0 0 1-2 2h-.09a1.65 1.65 0 0 0-1.51 1z"/></svg></button>
                        <?php endif; ?>
                        <span class="ssp-status-pill <?php echo $next_cron ? 'ok' : 'warn'; ?>">
                            <span class="ssp-status-dot"></span>
                            <?php echo $next_cron ? 'سیستم فعال' : 'نیاز به بررسی'; ?>
                        </span>
                        <?php if ($plan === 'pro') : ?>
                        <span class="ssp-status-pill pro">Pro</span>
                        <?php endif; ?>
                        <?php if ($is_frontend && !is_user_logged_in()) : ?>
                        <a class="ssp-login-btn" href="<?php echo esc_url(wp_login_url(get_permalink())); ?>" style="background:var(--accent);color:#fff;padding:8px 18px;border-radius:10px;text-decoration:none;font-weight:700;font-size:0.85rem;display:inline-flex;align-items:center;gap:6px;white-space:nowrap;">
                            <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2"><path d="M15 3h4a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-4"/><polyline points="10 17 15 12 10 7"/><line x1="15" y1="12" x2="3" y2="12"/></svg>
                            برای استفاده از پورتال وارد شوید
                        </a>
                        <?php endif; ?>
                        <button class="ssp-theme-toggle" onclick="toggleTheme()" title="تغییر تم">
                            <svg class="sun-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="4"/><path d="M12 2v2M12 20v2M4.93 4.93l1.41 1.41M17.66 17.66l1.41 1.41M2 12h2M20 12h2M6.34 17.66l-1.41 1.41M19.07 4.93l-1.41 1.41"/></svg>
                            <svg class="moon-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"/></svg>
                        </button>
                    </div>
                </div>

                <div class="ssp-main">
                    <div class="ssp-sidebar" id="sspSidebar">
                        <div class="ssp-sidebar-section">داشبورد و گزارشات</div>
                        <button class="ssp-tab-btn active" onclick="switchTab('dashboard', this)" data-tab="dashboard">
                            <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/></svg> داشبورد
                        </button>
                        <button class="ssp-tab-btn" onclick="switchTab('calendar', this)" data-tab="calendar">
                            <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg> تقویم محتوا
                        </button>
                        <button class="ssp-tab-btn" onclick="switchTab('reports', this)" data-tab="reports">
                            <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="20" x2="18" y2="10"/><line x1="12" y1="20" x2="12" y2="4"/><line x1="6" y1="20" x2="6" y2="14"/></svg> فعالیت‌ها و لاگ‌ها
                        </button>

                        <div class="ssp-sidebar-section">استودیو هوش مصنوعی</div>
                        <button class="ssp-tab-btn" onclick="switchTab('generate', this)" data-tab="generate">
                            <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 2v20M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/></svg> مرکز تولید سریع<?php if ($plan !== 'pro') : ?><span class="ssp-badge" style="background:#4f46e5;color:#fff;font-size:0.6rem;padding:2px 6px;border-radius:8px;margin-right:4px;font-weight:700;">Pro</span><?php endif; ?>
                        </button>
                        <button class="ssp-tab-btn" onclick="switchTab('contentgen', this)" data-tab="contentgen">
                            <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/></svg> تولید مقاله سئو شده<?php if ($plan !== 'pro') : ?><span class="ssp-badge" style="background:#4f46e5;color:#fff;font-size:0.6rem;padding:2px 6px;border-radius:8px;margin-right:4px;font-weight:700;">Pro</span><?php endif; ?>
                        </button>
                        <button class="ssp-tab-btn" onclick="switchTab('productgen', this)" data-tab="productgen">
                            <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2"><path d="M6 2L3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4z"/><line x1="3" y1="6" x2="21" y2="6"/><path d="M16 10a4 4 0 0 1-8 0"/></svg> تولید محصول فروشگاهی<?php if ($plan !== 'pro') : ?><span class="ssp-badge" style="background:#4f46e5;color:#fff;font-size:0.6rem;padding:2px 6px;border-radius:8px;margin-right:4px;font-weight:700;">Pro</span><?php endif; ?>
                        </button>
                        <button class="ssp-tab-btn" onclick="switchTab('postgen', this)" data-tab="postgen">
                            <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2"><polygon points="13 2 3 14 12 14 11 22 21 10 12 10 13 2"/></svg> پست شبکه‌های اجتماعی<?php if ($plan !== 'pro') : ?><span class="ssp-badge" style="background:#4f46e5;color:#fff;font-size:0.6rem;padding:2px 6px;border-radius:8px;margin-right:4px;font-weight:700;">Pro</span><?php endif; ?>
                        </button>
                        <button class="ssp-tab-btn" onclick="switchTab('brainstorm', this)" data-tab="brainstorm">
                            <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><path d="M9.09 9a3 3 0 0 1 5.83 1c0 2-3 3-3 3"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg> ایده‌یابی هوشمند<?php if ($plan !== 'pro') : ?><span class="ssp-badge" style="background:#4f46e5;color:#fff;font-size:0.6rem;padding:2px 6px;border-radius:8px;margin-right:4px;font-weight:700;">Pro</span><?php endif; ?>
                        </button>
                        <button class="ssp-tab-btn" onclick="switchTab('promptbuilder', this)" data-tab="promptbuilder">
                            <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="10" y1="13" x2="8" y2="13"/></svg> قالب‌های پرامپت
                        </button>
                        <button class="ssp-tab-btn" onclick="switchTab('template', this)" data-tab="template">
                            <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="18" height="18" rx="2" ry="2"/><line x1="3" y1="9" x2="21" y2="9"/><line x1="9" y1="21" x2="9" y2="9"/></svg> قالب‌های محتوا
                        </button>
                        
                        <div class="ssp-sidebar-section">انتشار و اتوماسیون</div>
                        <button class="ssp-tab-btn" onclick="switchTab('manual', this)" data-tab="manual">
                            <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2"><line x1="22" y1="2" x2="11" y2="13"/><polygon points="22 2 15 22 11 13 2 9 22 2"/></svg> ارسال پیام سریع
                        </button>
                        <button class="ssp-tab-btn" onclick="switchTab('drafts', this)" data-tab="drafts">
                            <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg> پیش‌نویس‌ها
                        </button>
                        <button class="ssp-tab-btn" onclick="switchTab('schedules', this)" data-tab="schedules">
                            <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg> زمان‌بندی پیام
                        </button>
                        <button class="ssp-tab-btn" onclick="switchTab('distribution', this)" data-tab="distribution">
                            <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2"><circle cx="18" cy="5" r="3"/><circle cx="6" cy="12" r="3"/><circle cx="18" cy="19" r="3"/><line x1="8.59" y1="13.51" x2="15.42" y2="17.49"/><line x1="15.41" y1="6.51" x2="8.59" y2="10.49"/></svg> توزیع خودکار (Automation)
                        </button>

                        <div class="ssp-sidebar-section">تلگرام و پیام‌رسان‌ها</div>
                        <button class="ssp-tab-btn" onclick="switchTab('messengers', this)" data-tab="messengers">
                            <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg> مدیریت کانال‌ها
                        </button>
                        <button class="ssp-tab-btn" onclick="switchTab('botbuilder', this)" data-tab="botbuilder">
                            <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 2a2 2 0 0 1 2 2v2a2 2 0 0 1-2 2 2 2 0 0 1-2-2V4a2 2 0 0 1 2-2zm0 6a2 2 0 0 1 2 2v2a2 2 0 0 1-2 2 2 2 0 0 1-2-2v-2a2 2 0 0 1 2-2zm0 6a2 2 0 0 1 2 2v2a2 2 0 0 1-2 2 2 2 0 0 1-2-2v-2a2 2 0 0 1 2-2z"/></svg> ربات‌ساز پیشرفته (Flows)
                        </button>
                        
                        <div class="ssp-sidebar-section">ابزارهای عملیاتی (Ops)</div>
                        <button class="ssp-tab-btn" onclick="switchTab('seo', this)" data-tab="seo">
                            <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg> تحلیل و سئو سایت
                        </button>
                        <button class="ssp-tab-btn" onclick="switchTab('links', this)" data-tab="links">
                            <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2"><path d="M10 13a5 5 0 0 0 7.54.54l3-3a5 5 0 0 0-7.07-7.07l-1.72 1.71"/><path d="M14 11a5 5 0 0 0-7.54-.54l-3 3a5 5 0 0 0 7.07 7.07l1.71-1.71"/></svg> کوتاه‌کننده لینک
                        </button>
                        <button class="ssp-tab-btn" onclick="switchTab('sources', this)" data-tab="sources">
                            <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 11a9 9 0 0 1 9 9"/><path d="M4 4a16 16 0 0 1 16 16"/><circle cx="5" cy="19" r="1"/></svg> خوراک (RSS Feeds)
                        </button>
                        <button class="ssp-tab-btn" onclick="switchTab('wpsources', this)" data-tab="wpsources">
                            <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="2" y1="12" x2="22" y2="12"/><path d="M12 2a15.3 15.3 0 0 1 4 10 15.3 15.3 0 0 1-4 10 15.3 15.3 0 0 1-4-10 15.3 15.3 0 0 1 4-10z"/></svg> منابع وردپرس (Sites)
                        </button>

                        <div class="ssp-sidebar-section">پیکربندی</div>
                        <button class="ssp-tab-btn" onclick="switchTab('ai', this)" data-tab="ai">
                            <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1 0 2.83 2 2 0 0 1-2.83 0l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-2 2 2 2 0 0 1-2-2v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83 0 2 2 0 0 1 0-2.83l.06-.06A1.65 1.65 0 0 0 4.68 15a1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1-2-2 2 2 0 0 1 2-2h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 0-2.83 2 2 0 0 1 2.83 0l.06.06A1.65 1.65 0 0 0 9 4.68a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 2-2 2 2 0 0 1 2 2v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 0 2 2 0 0 1 0 2.83l-.06.06A1.65 1.65 0 0 0 19.4 9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 2 2 2 2 0 0 1-2 2h-.09a1.65 1.65 0 0 0-1.51 1z"/></svg> اتصال‌های ابری (AI)
                        </button>
                        <button class="ssp-tab-btn" onclick="switchTab('subscription', this)" data-tab="subscription">
                            <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg> حساب کاربری
                        </button>
                    </div>
                    <div class="ssp-content">
                        <div id="ssp-loader" class="ssp-loader"><div class="ssp-spinner"></div><span>در حال پردازش...</span></div>

                        <!-- ============ DASHBOARD ============ -->
                        <div id="tab-dashboard" class="tab-content active">
                            <h2>داشبورد</h2>
                            <p class="ssp-section-desc">نمای کلی از وضعیت اتوماسیون شما.</p>

                            <div class="ssp-cron-status <?php echo $next_cron ? 'ok' : 'warn'; ?>">
                                <div style="flex:1;">
                                    <strong><?php echo $next_cron ? 'سیستم خودکار فعال است' : 'سیستم خودکار غیرفعال است'; ?></strong>
                                    <div style="font-size:0.85rem; color:var(--text-muted); margin-top:4px;">
                                        <?php if ($next_cron) : ?>پردازش بعدی: <strong><?php echo human_time_diff(time(), $next_cron); ?> دیگر</strong><?php else : ?>Cron job در هاست شما فعال نیست<?php endif; ?>
                                    </div>
                                </div>
                                <?php if ($pending_count > 0) : ?>
                                <span class="ssp-badge warning"><?php echo $pending_count; ?> در صف انتظار</span>
                                <?php endif; ?>
                            </div>

                            <?php if ($plan === 'free') : ?>
                            <div class="ssp-upgrade-banner">
                                <div>
                                    <h3>امکانات حرفه‌ای را فعال کنید</h3>
                                    <p>AI، زمان‌بندی پیشرفته، تولید محتوا، و...</p>
                                </div>
                                <button type="button" class="ssp-btn-primary" onclick="switchTab('subscription', document.querySelector('[data-tab=subscription]'))">ارتقا به Pro</button>
                            </div>
                            <?php endif; ?>

                            <div class="ssp-grid-4">
                                <div class="ssp-card">
                                    <h3>ارسال امروز</h3>
                                    <div class="ssp-val"><?php echo $today_count; ?></div>
                                    <div class="ssp-sub">از <?php echo $plan === 'pro' ? 'نامحدود' : '5'; ?> مجاز</div>
                                </div>
                                <div class="ssp-card">
                                    <h3>در صف</h3>
                                    <div class="ssp-val"><?php echo $pending_count; ?></div>
                                    <div class="ssp-sub">منتظر پردازش</div>
                                </div>
                                <div class="ssp-card">
                                    <h3>پیام‌رسان‌ها</h3>
                                    <div class="ssp-val"><?php echo $enabled_messengers; ?></div>
                                    <div class="ssp-sub">فعال</div>
                                </div>
                                <div class="ssp-card">
                                    <h3>پیش‌نویس‌ها</h3>
                                    <div class="ssp-val"><?php echo count($drafts); ?></div>
                                    <div class="ssp-sub">ذخیره شده</div>
                                </div>
                            </div>

                            <?php
                            $user_queue = SSP_DB::get_queue_items('pending', 50, $user_id);
                            if (!is_array($user_queue)) $user_queue = [];
                            if (!empty($user_queue)) : ?>
                            <div style="margin:32px 0 16px;">
                                <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:12px;">
                                    <h3 style="margin:0; color:var(--text);">صف ارسال (<?php echo count($user_queue); ?> پیام)</h3>
                                    <div style="display:flex; gap:8px;">
                                        <button type="button" class="ssp-btn-primary" onclick="forceProcessQueue()" style="font-size:0.8rem; padding:4px 12px;">ارسال سریع همه (حل مشکل)</button>
                                        <button type="button" class="ssp-btn-danger" onclick="cancelAllQueue()" style="font-size:0.8rem; padding:4px 12px;">لغو همه</button>
                                    </div>
                                </div>
                                <div style="display:flex; flex-direction:column; gap:8px;">
                                    <?php foreach (array_slice($user_queue, 0, 5) as $q) : ?>
                                    <div class="ssp-item-card" style="padding:10px 14px; display:flex; justify-content:space-between; align-items:center;">
                                        <div>
                                            <div style="font-weight:600; font-size:0.9rem;"><?php echo esc_html(mb_substr($q['payload']['title'] ?? $q['payload']['message'] ?? '', 0, 50)); ?></div>
                                            <div style="font-size:0.8rem; color:var(--text-muted);">
                                                <?php echo esc_html($q['action_type'] ?? ''); ?>
                                                <?php if (!empty($q['payload']['selected_messengers'])) : ?>
                                                — <?php echo count($q['payload']['selected_messengers']); ?> پیام‌رسان
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                        <button type="button" class="ssp-btn-danger" onclick="cancelQueueItem(<?php echo (int)$q['id']; ?>)" style="font-size:0.75rem; padding:3px 10px;">لغو</button>
                                    </div>
                                    <?php endforeach; ?>
                                    <?php if (count($user_queue) > 5) : ?>
                                    <div style="font-size:0.8rem; color:var(--text-muted); text-align:center;">و <?php echo count($user_queue) - 5; ?> پیام دیگر...</div>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <?php endif; ?>

                            <h3 style="margin:32px 0 16px; color:var(--text);">شروع سریع (3 قدم ساده)</h3>
                            <div class="ssp-step-indicator">
                                <div class="ssp-step <?php echo count($messengers) > 0 ? 'completed' : 'current'; ?>">
                                    <span class="ssp-step-num">1</span>
                                    اتصال پیام‌رسان
                                    <div style="font-size:0.7rem; margin-top:4px;">تلگرام، بله و...</div>
                                </div>
                                <div class="ssp-step <?php echo $ai_configured ? 'completed' : (count($messengers) > 0 ? 'current' : ''); ?>">
                                    <span class="ssp-step-num">2</span>
                                    تنظیم AI
                                    <div style="font-size:0.7rem; margin-top:4px;">API Key یا حالت مرورگر</div>
                                </div>
                                <div class="ssp-step <?php echo $total_count > 0 ? 'completed' : ''; ?>">
                                    <span class="ssp-step-num">3</span>
                                    اولین ارسال
                                    <div style="font-size:0.7rem; margin-top:4px;">تست کنید!</div>
                                </div>
                            </div>

                            <div class="ssp-grid-3">
                                <div class="ssp-card" onclick="switchTab('messengers', document.querySelector('[data-tab=messengers]'))" style="cursor:pointer;">
                                    <h3>اتصال پیام‌رسان</h3>
                                    <p class="ssp-sub">تلگرام، بله، ایتا، روبیکا</p>
                                </div>
                                <div class="ssp-card" onclick="switchTab('calendar', document.querySelector('[data-tab=calendar]'))" style="cursor:pointer;">
                                    <h3>تقویم محتوا</h3>
                                    <p class="ssp-sub">زمان‌بندی بصری ارسال‌ها</p>
                                </div>
                                <div class="ssp-card" onclick="switchTab('manual', document.querySelector('[data-tab=manual]'))" style="cursor:pointer;">
                                    <h3>ارسال پیام</h3>
                                    <p class="ssp-sub">همین الان تست کنید</p>
                                </div>
                            </div>

                            <div class="ssp-grid-3" style="margin-top:12px;">
                                <div class="ssp-card" onclick="switchTab('seo', document.querySelector('[data-tab=seo]'))" style="cursor:pointer;">
                                    <h3>ابزار SEO</h3>
                                    <p class="ssp-sub">تحلیل و بهینه‌سازی محتوا</p>
                                </div>
                                <div class="ssp-card" onclick="switchTab('drafts', document.querySelector('[data-tab=drafts]'))" style="cursor:pointer;">
                                    <h3>پیش‌نویس‌ها</h3>
                                    <p class="ssp-sub">ذخیره و مدیریت پیام‌ها</p>
                                </div>
                            </div>

                            <h3 style="margin:32px 0 16px; color:var(--text);">فعالیت‌های اخیر</h3>
                            <div id="dashboard_activity">
                                <?php
                                $recent_logs = array_slice($user_logs, 0, 5);
                                if (!empty($recent_logs)) :
                                    foreach ($recent_logs as $log) :
                                        $status_icon = ($log['status'] ?? '') === 'success' ? '&#10003;' : '&#10007;';
                                        $status_class = ($log['status'] ?? '') === 'success' ? 'success' : 'error';
                                ?>
                                <div class="ssp-item-card" style="padding:12px 16px; margin-bottom:8px;">
                                    <div style="display:flex; justify-content:space-between; align-items:center; gap:8px;">
                                        <div style="display:flex; align-items:center; gap:8px;">
                                            <span class="ssp-badge <?php echo $status_class; ?>"><?php echo $status_icon; ?></span>
                                            <span style="font-weight:600; font-size:0.88rem; color:var(--text);"><?php echo esc_html(mb_substr($log['title'] ?? $log['message'] ?? '', 0, 50)); ?></span>
                                        </div>
                                        <div style="display:flex; align-items:center; gap:10px;">
                                            <span class="ssp-badge info"><?php echo esc_html($log['platform']); ?></span>
                                            <span style="color:var(--text-subtle); font-size:0.78rem; white-space:nowrap;"><?php
                                                $log_ts = ($log['created_at'] ?? '') ? (new \DateTime($log['created_at'], wp_timezone()))->getTimestamp() : current_time('timestamp');
                                                echo human_time_diff($log_ts) . ' پیش';
                                            ?></span>
                                        </div>
                                    </div>
                                </div>
                                <?php endforeach;
                                else : ?>
                                <div class="ssp-empty" style="padding:30px 20px;">
                                    <div class="ssp-empty-icon" style="font-size:3rem; color:var(--text-subtle); margin-bottom:12px;"><svg viewBox="0 0 24 24" width="36" height="36" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="20" x2="18" y2="10"/><line x1="12" y1="20" x2="12" y2="4"/><line x1="6" y1="20" x2="6" y2="14"/></svg></div>
                                    <h4 style="margin:0 0 8px; font-size:1.1rem; color:var(--text);">هیچ فعالیتی یافت نشد</h4>
                                    <p style="color:var(--text-muted); font-size:0.9rem; max-width:350px; margin:0 auto 16px;">تا کنون هیچ پیام، تولید محتوا یا اتوماسیونی توسط سیستم اجرا نشده است.</p>
                                    <button type="button" class="ssp-btn-secondary" onclick="switchTab('generate', document.querySelector('[data-tab=generate]'))">شروع تولید محتوا</button>
                                </div>
                                <?php endif; ?>
                            </div>

                            <h3 style="margin:24px 0 12px; color:var(--text);">دسترسی سریع</h3>
                            <div style="display:flex; gap:10px; flex-wrap:wrap;">
                                <button type="button" class="ssp-btn-primary" onclick="switchTab('manual', document.querySelector('[data-tab=manual]'))">ارسال پیام</button>
                                <button type="button" class="ssp-btn-secondary" onclick="switchTab('generate', document.querySelector('[data-tab=generate]'))">تولید محتوا</button>
                                <button type="button" class="ssp-btn-secondary" onclick="switchTab('reports', document.querySelector('[data-tab=reports]'))">مشاهده گزارشات</button>
                            </div>

                            <?php if ($plan === 'pro' && !empty($total_tokens)) : ?>
                            <div class="ssp-card" style="margin-top:20px; background: linear-gradient(135deg, var(--info-soft), transparent); border-color: var(--info);">
                                <h3>آمار استفاده از AI</h3>
                                <div class="ssp-grid-2" style="margin-top:12px;">
                                    <div>
                                        <div class="ssp-val" style="font-size:1.2rem;"><?php echo number_format($total_tokens); ?></div>
                                        <div class="ssp-sub">کل توکن‌های مصرف شده</div>
                                    </div>
                                    <div>
                                        <div class="ssp-val" style="font-size:1.2rem; color:var(--success);">$<?php echo number_format($total_tokens * 0.000015, 4); ?></div>
                                        <div class="ssp-sub">هزینه تقریبی (gpt-4o-mini)</div>
                                    </div>
                                </div>
                            </div>
                            <?php endif; ?>
                        </div>
                        <!-- ============ SEND MESSAGE ============ -->
                        <div id="tab-manual" class="tab-content">
                            <?php if ($plan === 'free') : ?>
                            <div style="text-align:center; padding:60px 20px;">
                                <div style="width:64px;height:64px;border-radius:50%;background:#eef2ff;display:flex;align-items:center;justify-content:center;margin:0 auto 20px;">
                                    <svg viewBox="0 0 24 24" width="32" height="32" fill="none" stroke="#4f46e5" stroke-width="2"><line x1="22" y1="2" x2="11" y2="13"/><polygon points="22 2 15 22 11 13 2 9 22 2"/></svg>
                                </div>
                                <h3 style="color:#1e293b; margin:0 0 8px;">قابلیت حرفه‌ای</h3>
                                <p style="color:#64748b; margin:0 0 20px; font-size:0.9rem;">ارسال پیام ویژگی پلن Pro است. با ارتقا به پلن حرفه‌ای، به این ابزار دسترسی پیدا کنید.</p>
                                <a href="#" onclick="switchTab('subscription', document.querySelector('[data-tab=subscription]')); return false;" style="background:#4f46e5; color:#fff; padding:12px 28px; border-radius:10px; text-decoration:none; font-weight:700; display:inline-block;">ارتقا به Pro</a>
                            </div>
                            <?php else : ?>
                            <h2>ارسال پیام</h2>
                            <p class="ssp-section-desc">پیام خود را بنویسید و به یک یا چند پیام‌رسان ارسال کنید.</p>

                            <?php if ($enabled_messengers === 0 && count($wp_sites) === 0) : ?>
                            <div class="ssp-upgrade-banner" style="background:var(--warning-soft); border-color:var(--warning);">
                                <div>
                                    <h3 style="color:var(--warning);">ابتدا یک مقصد متصل کنید</h3>
                                    <p>به تب «پیام‌رسان‌ها» بروید و حداقل یک پیام‌رسان اضافه کنید.</p>
                                </div>
                                <button type="button" class="ssp-btn-primary" onclick="switchTab('messengers', document.querySelector('[data-tab=messengers]'))">رفتن به پیام‌رسان‌ها</button>
                            </div>
                            <?php else : ?>

                            <form id="manual_form" onsubmit="manualSend(event)">
                                <!-- Messenger Selection (Grid with Select All) -->
                                <div class="ssp-form-group">
                                    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:8px;">
                                        <label class="ssp-label" style="margin-bottom:0;">پیام‌رسان‌های مقصد</label>
                                        <div style="display:flex; gap:8px; font-size:0.78rem;">
                                            <button type="button" class="ssp-btn-secondary" onclick="document.querySelectorAll('.manual_messenger_cb').forEach(function(cb){ cb.checked = true; });" style="padding:3px 10px; font-size:0.75rem; border-radius:6px;">انتخاب همه</button>
                                            <button type="button" class="ssp-btn-secondary" onclick="document.querySelectorAll('.manual_messenger_cb').forEach(function(cb){ cb.checked = false; });" style="padding:3px 10px; font-size:0.75rem; border-radius:6px;">لغو همه</button>
                                        </div>
                                    </div>
                                    <div id="manual_messenger_list" style="display:grid; grid-template-columns:repeat(auto-fill, minmax(200px, 1fr)); gap:10px;">
                                        <?php
                                        $send_platform_icons = ['telegram' => '🔵', 'bale' => '🟢', 'eitaa' => '🟠', 'rubika' => '🟣', 'instagram' => '📷', 'whatsapp' => '💬'];
                                        $send_platform_colors = ['telegram' => '#0088cc', 'bale' => '#39c551', 'eitaa' => '#ff8800', 'rubika' => '#7b4dba', 'instagram' => '#e4405f', 'whatsapp' => '#25d366'];
                                        $send_platform_names = ['telegram' => 'تلگرام', 'bale' => 'بله', 'eitaa' => 'ایتا', 'rubika' => 'روبیکا', 'instagram' => 'اینستاگرام', 'whatsapp' => 'واتساپ'];
                                        ?>
                                        <?php foreach ($messengers as $m) :
                                            $plat = $m['platform'];
                                            $pcolor = $send_platform_colors[$plat] ?? '#666';
                                        ?>
                                        <label style="display:flex; align-items:center; gap:10px; padding:12px; background:var(--bg-alt); border-radius:10px; border:1px solid var(--border); cursor:pointer; transition:all 0.2s;" class="ssp-messenger-check">
                                            <input type="checkbox" name="manual_messengers[]" class="manual_messenger_cb" value="<?php echo (int)$m['id']; ?>" <?php echo $m['is_active'] ? 'checked' : ''; ?> style="width:18px; height:18px; accent-color:var(--accent);">
                                            <span style="display:inline-flex; align-items:center; justify-content:center; width:28px; height:28px; border-radius:8px; background:<?php echo esc_attr($pcolor); ?>15; font-size:14px; flex-shrink:0;"><?php echo $send_platform_icons[$plat] ?? '💬'; ?></span>
                                            <div style="min-width:0;">
                                                <div style="font-weight:600; font-size:0.85rem; color:var(--text); white-space:nowrap; overflow:hidden; text-overflow:ellipsis;"><?php echo esc_html($m['name']); ?></div>
                                                <div style="font-size:0.7rem; color:<?php echo esc_attr($pcolor); ?>; font-weight:500;"><?php echo esc_html($send_platform_names[$plat] ?? $plat); ?></div>
                                            </div>
                                        </label>
                                        <?php endforeach; ?>
                                    </div>
                                </div>

                                <!-- Template Selector -->
                                <div class="ssp-form-group" id="manual_template_selector" style="display:none;">
                                    <label class="ssp-label">بارگذاری از قالب</label>
                                    <div style="display:flex; gap:8px; align-items:center;">
                                        <select id="manual_template_select" class="ssp-select" onchange="onManualTemplateSelect()" style="flex:1;">
                                            <option value="">— انتخاب قالب —</option>
                                        </select>
                                        <label style="display:flex; align-items:center; gap:6px; font-size:0.8rem; color:var(--text-secondary); white-space:nowrap; cursor:pointer; padding:0 8px;">
                                            <input type="checkbox" id="manual_template_default" onchange="onManualTemplateDefaultToggle()" style="accent-color:var(--accent);">
                                            پیش‌فرض
                                        </label>
                                    </div>
                                    <p class="ssp-hint">عنوان، متن، هشتگ‌ها و امضای قالب انتخابی را پر می‌کند.</p>
                                </div>

                                <!-- Content -->
                                <div class="ssp-form-group">
                                    <label class="ssp-label">عنوان پیام</label>
                                    <input type="text" id="manual_title" class="ssp-input" placeholder="مثلاً: خبر فوری فناوری" required>
                                </div>
                                <div class="ssp-form-group">
                                    <label class="ssp-label">متن پیام</label>
                                    <textarea id="manual_message" rows="6" class="ssp-textarea" placeholder="متن کامل پیام را اینجا بنویسید..." required oninput="updateManualCharCount()"></textarea>
                                    <div style="display:flex; justify-content:space-between; margin-top:6px;">
                                        <span class="ssp-hint" id="manual_char_count">0 کاراکتر</span>
                                        <span class="ssp-hint">حداکثر 4096 (تلگرام)</span>
                                    </div>
                                </div>
                                <div class="ssp-grid-2">
                                    <div class="ssp-form-group">
                                        <label class="ssp-label">هشتگ‌ها</label>
                                        <input type="text" id="manual_hashtags" class="ssp-input" placeholder="#tag1 #tag2">
                                    </div>
                                    <div class="ssp-form-group">
                                        <label class="ssp-label">تصویر، ویدیو یا فایل/داکیومنت (اختیاری)</label>
                                        <input type="hidden" id="manual_image_url" value="">
                                        <!-- Tab buttons -->
                                        <div style="display:flex; gap:0; margin-bottom:0;">
                                            <button type="button" class="ssp-btn-secondary manual_media_tab active" onclick="switchManualMediaTab('upload')" style="border-radius:0 0 0 8px; flex:1; font-size:0.8rem; padding:6px;">آپلود فایل / داکیومنت</button>
                                            <button type="button" class="ssp-btn-secondary manual_media_tab" onclick="switchManualMediaTab('url')" style="border-radius:0 0 8px 0; flex:1; font-size:0.8rem; padding:6px;">از لینک مستقیم URL</button>
                                        </div>
                                        <!-- Upload tab -->
                                        <div id="manual_media_tab_upload">
                                            <div id="manual_media_upload" style="position:relative; border:2px dashed var(--border); border-top:none; border-radius:0 0 10px 10px; padding:16px; text-align:center; cursor:pointer; transition:all 0.2s;" onclick="document.getElementById('manual_media_file').click()">
                                                <input type="file" id="manual_media_file" accept="image/*,video/*,audio/*,.pdf,.doc,.docx,.xls,.xlsx,.ppt,.pptx,.txt,.csv,.zip,.rar,.tar,.gz,.7z,.apk" multiple style="display:none;" onchange="handleManualMediaUpload(this)">
                                                <div id="manual_media_placeholder">
                                                    <svg viewBox="0 0 24 24" width="24" height="24" fill="none" stroke="var(--text-subtle)" stroke-width="2" style="margin-bottom:4px;"><rect x="3" y="3" width="18" height="18" rx="2" ry="2"/><circle cx="8.5" cy="8.5" r="1.5"/><polyline points="21 15 16 10 5 21"/></svg>
                                                    <div style="font-size:0.8rem; color:var(--text-muted);">کلیک کنید یا فایل، تصویر، ویدیو یا سند را بکشید</div>
                                                    <div style="font-size:0.7rem; color:var(--text-subtle); margin-top:4px;">تصویر: تا ۱۵MB | ویدیو: تا ۸۰MB | داکیومنت/فایل/صوت: تا ۵۰MB</div>
                                                </div>
                                                <div id="manual_media_preview" style="display:none;"></div>
                                                <div id="manual_media_progress" style="display:none; margin-top:8px;">
                                                    <div style="height:4px; background:var(--border); border-radius:2px; overflow:hidden;"><div id="manual_media_progress_bar" style="height:100%; background:var(--accent); width:0%; transition:width 0.3s;"></div></div>
                                                    <div style="font-size:0.75rem; color:var(--text-muted); margin-top:4px;" id="manual_media_status">در حال آپلود...</div>
                                                </div>
                                            </div>
                                        </div>
                                        <!-- URL tab -->
                                        <div id="manual_media_tab_url" style="display:none;">
                                            <div style="border:2px dashed var(--border); border-top:none; border-radius:0 0 10px 10px; padding:12px;">
                                                <div style="display:flex; gap:6px;">
                                                    <input type="url" id="manual_media_url_input" class="ssp-input" dir="ltr" placeholder="https://example.com/file.pdf" style="flex:1;">
                                                    <button type="button" class="ssp-btn-primary" onclick="addManualMediaUrl()" style="padding:6px 12px; font-size:0.8rem; white-space:nowrap;">افزودن</button>
                                                </div>
                                                <div class="ssp-hint" style="margin-top:6px;">لینک مستقیم تصویر، ویدیو یا فایل/داکیومنت را وارد کنید. چند فایل = آلبوم</div>
                                                <div id="manual_media_url_list" style="margin-top:8px;"></div>
                                                <div id="manual_media_url_preview" style="display:none; margin-top:8px;"></div>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Gallery limitation notice -->
                                <div id="manual_media_notice" style="display:none; padding:10px 14px; background:var(--warning-soft); border:1px solid var(--warning); border-radius:8px; margin:8px 0;">
                                    <div style="display:flex; align-items:flex-start; gap:8px;">
                                        <span style="font-size:1.1rem; line-height:1; color:var(--warning);">!</span>
                                        <div style="font-size:0.8rem; color:var(--text); line-height:1.6;">
                                            <strong>توجه:</strong> روبیکا و ایتا از ارسال گالری (چند تصویر/ویدیو در یک پیام) پشتیبانی نمی‌کنند.
                                            در این پلتفرم‌ها فقط <strong>اولین تصویر یا ویدیو</strong> همراه متن ارسال خواهد شد.
                                            ارسال گالری فقط در <strong>تلگرام</strong> و <strong>بله</strong> امکان‌پذیر است.
                                        </div>
                                    </div>
                                </div>

                                <!-- Preview -->
                                <div class="ssp-card" style="background:var(--bg-alt); margin:12px 0; cursor:pointer;" onclick="toggleManualPreview()">
                                    <div style="display:flex; justify-content:space-between; align-items:center;">
                                        <span style="font-weight:600; color:var(--text); font-size:0.9rem;">پیش‌نمایش پیام</span>
                                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="6 9 12 15 18 9"/></svg>
                                    </div>
                                    <div id="manual_preview" style="display:none; margin-top:12px; padding:14px; background:var(--card); border-radius:8px; border:1px solid var(--border); white-space:pre-wrap; font-size:0.85rem; color:var(--text); max-height:200px; overflow-y:auto;"></div>
                                </div>

                                <!-- Schedule Toggle -->
                                <div class="ssp-card" style="margin:12px 0; border-color:var(--border);">
                                    <label style="display:flex; align-items:center; gap:10px; cursor:pointer;">
                                        <input type="checkbox" id="manual_schedule_toggle" onchange="toggleManualSchedule()" style="width:18px; height:18px; accent-color:var(--accent);">
                                        <div>
                                            <div style="font-weight:600; font-size:0.9rem; color:var(--text);">ارسال در زمان مشخص</div>
                                            <div style="font-size:0.75rem; color:var(--text-muted);">پیام را برای ارسال در آینده زمان‌بندی کنید</div>
                                        </div>
                                    </label>
                                    <div id="manual_schedule_fields" style="display:none; margin-top:12px;">
                                        <?php if ($plan === 'pro') : ?>
                                        <div class="ssp-grid-2">
                                            <div class="ssp-form-group">
                                                <label class="ssp-label">تاریخ و ساعت ارسال (شمسی)</label>
                                                <input type="text" id="manual_schedule_display" class="ssp-input" readonly placeholder="تاریخ را انتخاب کنید" style="cursor:pointer;" onclick="manualOpenJalaliPicker()">
                                                <input type="hidden" id="manual_schedule_datetime" name="scheduled_at" value="">
                                                <div class="ssp-hint" style="margin-top:4px;">حداقل ۱۰ دقیقه آینده</div>
                                                <div id="manual_jalali_picker" style="display:none; margin-top:8px; padding:12px; background:var(--card); border:1px solid var(--border); border-radius:8px;"></div>
                                            </div>
                                            <div class="ssp-form-group">
                                                <label class="ssp-label">تکرار</label>
                                                <select id="manual_schedule_recurring" name="recurring" class="ssp-select">
                                                    <option value="">بدون تکرار</option>
                                                    <option value="daily">روزانه</option>
                                                    <option value="weekly">هفتگی</option>
                                                    <option value="monthly">ماهانه</option>
                                                </select>
                                            </div>
                                        </div>
                                        <?php else : ?>
                                        <div class="ssp-upgrade-banner" style="background:var(--accent-soft); border-color:var(--accent); margin-top:8px;">
                                            <div style="display:flex; align-items:center; gap:10px;">
                                                <span>زمان‌بندی ارسال یک قابلیت Pro است.</span>
                                                <button type="button" class="ssp-btn-primary" onclick="switchTab('subscription', document.querySelector('[data-tab=subscription]'))">ارتقاء به Pro</button>
                                            </div>
                                        </div>
                                        <?php endif; ?>
                                    </div>
                                </div>

                                <div style="display:flex; gap:10px; align-items:center; flex-wrap:wrap;">
                                    <button type="submit" class="ssp-btn-primary" id="manual_send_btn"><span class="ssp-btn-text">افزودن به صف ارسال</span><span class="ssp-btn-spinner"></span></button>
                                    <button type="button" class="ssp-btn-secondary" onclick="saveDraftFromManual()">ذخیره به عنوان پیش‌نویس</button>
                                    <span class="ssp-saved-indicator" id="manual_saved">به صف اضافه شد!</span>
                                </div>
                            </form>

                            <?php endif; ?>
                            <?php endif; // end Pro guard for manual ?>
                        </div>

                        <div id="tab-drafts" class="tab-content">
                            <h2>پیش‌نویس‌ها</h2>
                            <p class="ssp-section-desc">پیام‌های ذخیره شده خود را مدیریت کنید و هر زمان خواستید ارسال کنید.</p>

                            <div id="drafts_list">
                                <div class="ssp-empty">
                                    <div class="ssp-empty-icon"><svg viewBox="0 0 24 24" width="48" height="48" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/><polyline points="10 9 9 9 8 9"/></svg></div>
                                    <p>هنوز پیش‌نویسی ندارید.</p>
                                    <p style="font-size:0.85rem;">از تب «ارسال پیام» می‌توانید پیام‌ها را به عنوان پیش‌نویس ذخیره کنید.</p>
                                </div>
                            </div>

                            <div class="ssp-add-form">
                                <h3>پیش‌نویس جدید</h3>
                                <div class="ssp-form-group">
                                    <label class="ssp-label">عنوان</label>
                                    <input type="text" id="draft_title" class="ssp-input" placeholder="عنوان پیام">
                                </div>
                                <div class="ssp-form-group">
                                    <label class="ssp-label">متن پیام</label>
                                    <textarea id="draft_content" rows="4" class="ssp-textarea" placeholder="متن پیام..."></textarea>
                                </div>
                                <div class="ssp-form-group">
                                    <label class="ssp-label">هشتگ‌ها</label>
                                    <input type="text" id="draft_hashtags" class="ssp-input" placeholder="#tag1 #tag2">
                                </div>
                                <div style="display:flex; gap:10px; align-items:center;">
                                    <button type="button" class="ssp-btn-primary" onclick="saveDraft()" id="save_draft_btn"><span class="ssp-btn-text">ذخیره پیش‌نویس</span><span class="ssp-btn-spinner"></span></button>
                                    <span class="ssp-saved-indicator" id="draft_saved">ذخیره شد!</span>
                                </div>
                            </div>
                        </div>

                        <!-- ============ AI GENERATE HUB ============ -->
                        <div id="tab-generate" class="tab-content">
                            <?php if ($plan === 'free') : ?>
                            <div style="text-align:center; padding:60px 20px;">
                                <div style="width:64px;height:64px;border-radius:50%;background:#eef2ff;display:flex;align-items:center;justify-content:center;margin:0 auto 20px;">
                                    <svg viewBox="0 0 24 24" width="32" height="32" fill="none" stroke="#4f46e5" stroke-width="2"><path d="M12 2v20M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/></svg>
                                </div>
                                <h3 style="color:#1e293b; margin:0 0 8px;">قابلیت حرفه‌ای</h3>
                                <p style="color:#64748b; margin:0 0 20px; font-size:0.9rem;">مرکز تولید محتوا ویژگی پلن Pro است. با ارتقا به پلن حرفه‌ای، به این ابزار دسترسی پیدا کنید.</p>
                                <a href="#" onclick="switchTab('subscription', document.querySelector('[data-tab=subscription]')); return false;" style="background:#4f46e5; color:#fff; padding:12px 28px; border-radius:10px; text-decoration:none; font-weight:700; display:inline-block;">ارتقا به Pro</a>
                            </div>
                            <?php else : ?>
                            <div style="text-align:center; margin-bottom:40px; margin-top:10px;">
                                <div style="width:64px; height:64px; background:linear-gradient(135deg, #4f46e5, #ec4899); border-radius:20px; display:inline-flex; align-items:center; justify-content:center; color:#fff; margin-bottom:20px; box-shadow:0 10px 25px rgba(79, 70, 229, 0.25);">
                                    <svg viewBox="0 0 24 24" width="32" height="32" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 2v20M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/></svg>
                                </div>
                                <h2 style="margin:0 0 12px; font-weight:800; font-size:1.8rem; color:var(--text);">استودیو هوش مصنوعی</h2>
                                <p style="color:var(--text-muted); font-size:1rem; max-width:550px; margin:0 auto; line-height:1.6;">مجموعه یکپارچه ابزارهای هوش مصنوعی برای تولید انواع محتوا. ابزار مورد نظر خود را برای شروع انتخاب کنید.</p>
                            </div>

                            <?php if (!$ai_configured) : ?>
                            <div class="ssp-upgrade-banner" style="background:var(--warning-soft); border-color:var(--warning);">
                                <div>
                                    <h3 style="color:var(--warning);">هوش مصنوعی تنظیم نشده است</h3>
                                    <p>برای استفاده از امکانات استودیو هوش مصنوعی، ابتدا API Key را تنظیم کنید یا حالت مرورگر را فعال نمایید.</p>
                                </div>
                                <button type="button" class="ssp-btn-primary" onclick="switchTab('ai', document.querySelector('[data-tab=ai]'))">رفتن به تنظیمات AI</button>
                            </div>
                            <?php else : ?>

                            <div class="ssp-grid-2" style="gap:24px;">
                                <!-- Article Gen -->
                                <div class="ssp-card" style="cursor:pointer; transition:all 0.3s ease; border:1px solid var(--border); padding:24px;" onmouseover="this.style.borderColor='var(--accent)'; this.style.transform='translateY(-4px)'; this.style.boxShadow='0 12px 24px rgba(79,70,229,0.1)';" onmouseout="this.style.borderColor='var(--border)'; this.style.transform='translateY(0)'; this.style.boxShadow='0 2px 8px rgba(0,0,0,0.04)';" onclick="switchTab('contentgen', document.querySelector('[data-tab=contentgen]'))">
                                    <div style="font-size:2.5rem; margin-bottom:16px;">📝</div>
                                    <h3 style="margin:0 0 8px; font-size:1.2rem; font-weight:800; color:var(--text);">تولید مقاله سئو شده</h3>
                                    <p style="margin:0; font-size:0.9rem; color:var(--text-muted); line-height:1.6;">نگارش مقالات طولانی و ساختاریافته وردپرس با تگ‌ها و هدینگ‌های استاندارد و کاملاً سئو شده.</p>
                                </div>

                                <!-- Post Gen -->
                                <div class="ssp-card" style="cursor:pointer; transition:all 0.3s ease; border:1px solid var(--border); padding:24px;" onmouseover="this.style.borderColor='var(--accent)'; this.style.transform='translateY(-4px)'; this.style.boxShadow='0 12px 24px rgba(79,70,229,0.1)';" onmouseout="this.style.borderColor='var(--border)'; this.style.transform='translateY(0)'; this.style.boxShadow='0 2px 8px rgba(0,0,0,0.04)';" onclick="switchTab('postgen', document.querySelector('[data-tab=postgen]'))">
                                    <div style="font-size:2.5rem; margin-bottom:16px;">📱</div>
                                    <h3 style="margin:0 0 8px; font-size:1.2rem; font-weight:800; color:var(--text);">تولید پست شبکه‌های اجتماعی</h3>
                                    <p style="margin:0; font-size:0.9rem; color:var(--text-muted); line-height:1.6;">نگارش جذاب و خلاقانه کپشن و پست برای تلگرام، اینستاگرام، بله، ایتا و سایر پیام‌رسان‌ها.</p>
                                </div>

                                <!-- Product Gen -->
                                <div class="ssp-card" style="cursor:pointer; transition:all 0.3s ease; border:1px solid var(--border); padding:24px;" onmouseover="this.style.borderColor='var(--accent)'; this.style.transform='translateY(-4px)'; this.style.boxShadow='0 12px 24px rgba(79,70,229,0.1)';" onmouseout="this.style.borderColor='var(--border)'; this.style.transform='translateY(0)'; this.style.boxShadow='0 2px 8px rgba(0,0,0,0.04)';" onclick="switchTab('productgen', document.querySelector('[data-tab=productgen]'))">
                                    <div style="font-size:2.5rem; margin-bottom:16px;">🛍️</div>
                                    <h3 style="margin:0 0 8px; font-size:1.2rem; font-weight:800; color:var(--text);">تولید محصول فروشگاهی</h3>
                                    <p style="margin:0; font-size:0.9rem; color:var(--text-muted); line-height:1.6;">معرفی جذاب و قانع‌کننده محصولات برای فروشگاه ووکامرس شما جهت افزایش نرخ تبدیل فروش.</p>
                                </div>

                                <!-- Brainstorm -->
                                <div class="ssp-card" style="cursor:pointer; transition:all 0.3s ease; border:1px solid var(--border); padding:24px;" onmouseover="this.style.borderColor='var(--accent)'; this.style.transform='translateY(-4px)'; this.style.boxShadow='0 12px 24px rgba(79,70,229,0.1)';" onmouseout="this.style.borderColor='var(--border)'; this.style.transform='translateY(0)'; this.style.boxShadow='0 2px 8px rgba(0,0,0,0.04)';" onclick="switchTab('brainstorm', document.querySelector('[data-tab=brainstorm]'))">
                                    <div style="font-size:2.5rem; margin-bottom:16px;">💡</div>
                                    <h3 style="margin:0 0 8px; font-size:1.2rem; font-weight:800; color:var(--text);">ایده‌یابی هوشمند</h3>
                                    <p style="margin:0; font-size:0.9rem; color:var(--text-muted); line-height:1.6;">طوفان فکری و پیدا کردن سوژه‌ها و ایده‌های ناب برای تقویم محتوایی در روزها و هفته‌های آینده.</p>
                                </div>
                            </div>
                            
                            <div style="margin-top:40px; padding-top:24px; border-top:1px solid var(--border); display:flex; gap:12px; flex-wrap:wrap; justify-content:center;">
                                <button type="button" class="ssp-btn-secondary" onclick="switchTab('promptbuilder', document.querySelector('[data-tab=promptbuilder]'))" style="font-size:0.9rem; padding:8px 16px;">🛠️ پرامپت ساز پیشرفته</button>
                                <button type="button" class="ssp-btn-secondary" onclick="switchTab('drafts', document.querySelector('[data-tab=drafts]'))" style="font-size:0.9rem; padding:8px 16px;">📂 صندوق پیش‌نویس‌ها</button>
                                <button type="button" class="ssp-btn-secondary" onclick="switchTab('template', document.querySelector('[data-tab=template]'))" style="font-size:0.9rem; padding:8px 16px;">📋 قالب‌های پیام</button>
                            </div>

                            <?php endif; ?>
                            <?php endif; // end Pro guard for generate ?>
                        </div>
                        
                        <!-- ============ MESSENGERS ============ -->
                        <div id="tab-messengers" class="tab-content">
                            <?php if ($plan === 'free') : ?>
                            <div style="text-align:center; padding:60px 20px;">
                                <div style="width:64px;height:64px;border-radius:50%;background:#eef2ff;display:flex;align-items:center;justify-content:center;margin:0 auto 20px;">
                                    <svg viewBox="0 0 24 24" width="32" height="32" fill="none" stroke="#4f46e5" stroke-width="2"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>
                                </div>
                                <h3 style="color:#1e293b; margin:0 0 8px;">قابلیت حرفه‌ای</h3>
                                <p style="color:#64748b; margin:0 0 20px; font-size:0.9rem;">مدیریت پیام‌رسان‌ها ویژگی پلن Pro است. با ارتقا به پلن حرفه‌ای، به این ابزار دسترسی پیدا کنید.</p>
                                <a href="#" onclick="switchTab('subscription', document.querySelector('[data-tab=subscription]')); return false;" style="background:#4f46e5; color:#fff; padding:12px 28px; border-radius:10px; text-decoration:none; font-weight:700; display:inline-block;">ارتقا به Pro</a>
                            </div>
                            <?php else : ?>
                            <h2>
                                پیام‌رسان‌ها
                                <span class="ssp-counter <?php echo count($messengers) >= ($plan === 'pro' ? 10 : 1) ? 'max' : ''; ?>">
                                    <span class="count"><?php echo count($messengers); ?></span> / <?php echo $plan === 'pro' ? '10' : '1'; ?>
                                </span>
                            </h2>
                            <p class="ssp-section-desc">پیام‌رسان‌هایی که می‌خواهید محتوا به آن‌ها ارسال شود.</p>

                            <?php if ($plan === 'free' && count($messengers) >= 1) : ?>
                            <div class="ssp-upgrade-banner">
                                <div>
                                    <h3>برای افزودن پیام‌رسان بیشتر</h3>
                                    <p>در پلن رایگان فقط 1 پیام‌رسان. با Pro تا 10 پیام‌رسان!</p>
                                </div>
                                <button type="button" class="ssp-btn-primary" onclick="switchTab('subscription', document.querySelector('[data-tab=subscription]'))">ارتقا</button>
                            </div>
                            <?php endif; ?>

                            <div class="ssp-guide">
                                <h3>راهنمای گام به گام</h3>
                                <ol class="ssp-guide-steps">
                                    <li><strong>پلتفرم</strong> را انتخاب کنید (تلگرام، بله، ایتا یا روبیکا)</li>
                                    <li>یک <strong>نام دلخواه</strong> وارد کنید</li>
                                    <li><strong>توکن ربات</strong> را وارد کنید (از @BotFather یا پنل پیام‌رسان)</li>
                                    <li><strong>Chat ID / Channel ID</strong> را وارد کنید (اختیاری)</li>
                                    <li>دکمه <strong>«تست اتصال»</strong> را بزنید</li>
                                    <li>دکمه <strong>«ذخیره»</strong> را بزنید</li>
                                </ol>
                            </div>

                            <div id="messengers_list">
                                <?php if (empty($messengers)) : ?>
                                <div class="ssp-empty">
                                    <div class="ssp-empty-icon"><svg viewBox="0 0 24 24" width="48" height="48" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg></div>
                                    <p>هنوز پیام‌رسانی اضافه نکرده‌اید.</p>
                                    <p style="font-size:0.85rem;">از فرم زیر اولین پیام‌رسان خود را اضافه کنید.</p>
                                </div>
                                <?php else : ?>
                                    <?php foreach ($messengers as $m) :
                                        $platform_icons = ['telegram' => '🔵', 'bale' => '🟢', 'eitaa' => '🟠', 'rubika' => '🟣', 'instagram' => '📷', 'whatsapp' => '🟢'];
                                        $platform_names = ['telegram' => 'تلگرام', 'bale' => 'بله', 'eitaa' => 'ایتا', 'rubika' => 'روبیکا', 'instagram' => 'اینستاگرام', 'whatsapp' => 'واتساپ'];
                                    ?>
                                    <div class="ssp-item-card" data-id="<?php echo (int)$m['id']; ?>">
                                        <div class="ssp-item-card-head">
                                            <div>
                                                <div class="ssp-item-card-title">
                                                    <?php echo $platform_icons[$m['platform']] ?? '💬'; ?>
                                                    <?php echo esc_html($m['name']); ?>
                                                    <span class="ssp-badge <?php echo $m['is_active'] ? 'active' : 'inactive'; ?>">
                                                        <?php echo $m['is_active'] ? 'فعال' : 'غیرفعال'; ?>
                                                    </span>
                                                </div>
                                                <div class="ssp-item-card-meta">
                                                    پلتفرم: <?php echo $platform_names[$m['platform']] ?? $m['platform']; ?>
                                                    <?php if (!empty($m['channel_id'])) : ?> &bull; Chat ID: <?php echo esc_html($m['channel_id']); ?><?php endif; ?>
                                                </div>
                                            </div>
                                            <div style="display:flex; gap:6px;">
                                                <button class="ssp-btn-test btn-test-messenger" data-id="<?php echo (int)$m['id']; ?>">تست</button>
                                                <button class="ssp-btn-secondary btn-edit-messenger" data-id="<?php echo (int)$m['id']; ?>">ويرايش</button>
                                                <button class="ssp-btn-danger btn-delete-messenger" data-id="<?php echo (int)$m['id']; ?>">حذف</button>
                                            </div>
                                        </div>
                                        <span id="messenger_status_<?php echo (int)$m['id']; ?>" class="ssp-connection-status"></span>
                                    </div>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </div>

                            <?php if ($plan === 'free' && count($messengers) >= 1) : ?>
                                <div style="margin-top:16px; padding:14px; background:var(--warning-soft); border-radius:10px; color:var(--warning); text-align:center;">
                                    در پلن رایگان فقط 1 پیام‌رسان. <a href="#" onclick="switchTab('subscription', document.querySelector('[data-tab=subscription]')); return false;" style="color:var(--accent);">ارتقا دهید</a>.
                                </div>
                            <?php elseif (count($messengers) >= 10) : ?>
                                <div style="margin-top:16px; padding:14px; background:var(--warning-soft); border-radius:10px; color:var(--warning); text-align:center;">
                                    به حداکثر 10 پیام‌رسان رسیده‌اید.
                                </div>
                            <?php else : ?>
                            <div class="ssp-add-form" id="messenger_add_form">
                                <h3>افزودن پیام‌رسان جدید</h3>
                                <div class="ssp-form-group">
                                    <label class="ssp-label">پلتفرم</label>
                                    <select id="new_messenger_platform" class="ssp-select">
                                        <option value="telegram">🔵 تلگرام (Telegram)</option>
                                        <option value="bale">🟢 بله (Bale)</option>
                                        <option value="eitaa">🟠 ایتا (Eitaa)</option>
                                        <option value="rubika">🟣 روبیکا (Rubika)</option>
                                        <option value="instagram">اینستاگرام (Instagram)</option>
                                        <option value="whatsapp">🟢 واتساپ Business (WhatsApp)</option>
                                    </select>
                                </div>
                                <div class="ssp-form-group">
                                    <label class="ssp-label">نام دلخواه</label>
                                    <input type="text" id="new_messenger_name" class="ssp-input" placeholder="مثلاً: کانال اصلی تلگرام">
                                </div>
                                <div class="ssp-form-group">
                                    <label class="ssp-label">توکن ربات</label>
                                    <div class="ssp-input-group">
                                        <input type="password" id="new_messenger_token" class="ssp-input" dir="ltr" placeholder="123456:ABC-DEF...">
                                        <button type="button" class="ssp-eye-btn" onclick="togglePass('new_messenger_token')"><svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg></button>
                                    </div>
                                </div>
                                <div class="ssp-form-group">
                                    <label class="ssp-label">Chat ID / Channel ID (اختیاری)</label>
                                    <input type="text" id="new_messenger_channel" class="ssp-input" dir="ltr" placeholder="@mychannel یا -100123456789">
                                </div>
                                <div class="ssp-toggle" style="margin-top:0;">
                                    <label>
                                        <input type="checkbox" id="new_messenger_active" checked>
                                        <span class="ssp-toggle-slider"></span>
                                        <span>فعال (ارسال خودکار)</span>
                                    </label>
                                </div>
                                <div style="margin-top:16px; display:flex; gap:10px; align-items:center;">
                                    <button type="button" class="ssp-btn-primary" onclick="addMessenger()" id="add_messenger_btn"><span class="ssp-btn-text">ذخیره پیام‌رسان</span><span class="ssp-btn-spinner"></span></button>
                                    <span class="ssp-saved-indicator" id="messenger_saved">ذخیره شد!</span>
                                </div>
                                <div id="messenger_platform_hint" style="margin-top:12px; padding:10px; background:var(--info-soft); border-radius:8px; font-size:0.85rem; color:var(--info);">
                                    <strong>راهنما:</strong> از @BotFather یا پنل پیام‌رسان مربوطه توکن ربات خود را دریافت کنید.
                                </div>
                            </div>
                            <?php endif; ?>
                            <?php endif; // end Pro guard for messengers ?>
                        </div>

                        <!-- ============ BOT BUILDER ============ -->
                        <div id="tab-botbuilder" class="tab-content">
                            <?php if ($plan === 'free') : ?>
                            <div style="text-align:center; padding:60px 20px;">
                                <div style="width:64px;height:64px;border-radius:50%;background:#eef2ff;display:flex;align-items:center;justify-content:center;margin:0 auto 20px;">
                                    <svg viewBox="0 0 24 24" width="32" height="32" fill="none" stroke="#4f46e5" stroke-width="2"><rect x="3" y="11" width="18" height="10" rx="2"/><circle cx="12" cy="5" r="2"/><path d="M12 7v4"/></svg>
                                </div>
                                <h3 style="color:#1e293b; margin:0 0 8px;">قابلیت حرفه‌ای</h3>
                                <p style="color:#64748b; margin:0 0 20px; font-size:0.9rem;">ساخت بات ویژگی پلن Pro است. با ارتقا به پلن حرفه‌ای، به این ابزار دسترسی پیدا کنید.</p>
                                <a href="#" onclick="switchTab('subscription', document.querySelector('[data-tab=subscription]')); return false;" style="background:#4f46e5; color:#fff; padding:12px 28px; border-radius:10px; text-decoration:none; font-weight:700; display:inline-block;">ارتقا به Pro</a>
                            </div>
                            <?php else : ?>
                            <h2 class="ssp-tool-title" style="display:flex;align-items:center;gap:10px;margin:0 0 8px;"><svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" style="display:inline-block;vertical-align:middle;flex-shrink:0;"><rect x="3" y="11" width="18" height="10" rx="2"/><circle cx="12" cy="5" r="2"/><path d="M12 7v4"/></svg> <span>سازنده بات</span></h2>
                            <p class="ssp-section-desc">بات خود را برای تلگرام یا بله بسازید و مدیریت کنید. دکمه، دستور و پاسخ خودکار اضافه کنید.</p>

                            <?php
                            $user_bot_configs = $this->get_user_bot_configs($user_id);
                            $active_bot_id = intval($_GET['bot_id'] ?? 0);
                            $active_config = $active_bot_id ? $this->get_bot_config($user_id, $active_bot_id) : null;
                            ?>

                            <!-- Bot List -->
                            <div id="bot_list_section">
                                <div class="ssp-card" style="margin-bottom:16px;">
                                    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:16px;">
                                        <h3 style="margin:0;">لیست بات‌ها</h3>
                                        <button type="button" class="ssp-btn-primary" onclick="showAddBotForm()">+ بات جدید</button>
                                    </div>

                                    <?php if (empty($user_bot_configs)) : ?>
                                    <div class="ssp-empty">
                                        <div class="ssp-empty-icon"><svg viewBox="0 0 24 24" width="48" height="48" fill="none" stroke="currentColor" stroke-width="1.5"><rect x="3" y="11" width="18" height="10" rx="2"/><circle cx="12" cy="5" r="2"/><path d="M12 7v4"/></svg></div>
                                        <p>هنوز باتی نساخته‌اید.</p>
                                        <p style="font-size:0.85rem;">اولین بات خود را برای تلگرام یا بله بسازید.</p>
                                    </div>
                                    <?php else : ?>
                                    <div id="bots_list">
                                        <?php foreach ($user_bot_configs as $bot) :
                                            $platform_icons = ['telegram' => '🔵', 'bale' => '🟢'];
                                            $platform_names = ['telegram' => 'تلگرام', 'bale' => 'بله'];
                                            $has_token = !empty($bot['token']);
                                        ?>
                                        <div class="ssp-item-card" data-id="<?php echo (int)$bot['id']; ?>" style="cursor:pointer; <?php echo !$has_token ? 'border:2px solid var(--warning);' : ''; ?>" onclick="editBot(<?php echo (int)$bot['id']; ?>)">
                                            <div class="ssp-item-card-head">
                                                <div>
                                                    <div class="ssp-item-card-title">
                                                        <?php echo esc_html($platform_icons[$bot['platform']] ?? '<svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" style="vertical-align:middle;"><rect x="3" y="11" width="18" height="10" rx="2"/><circle cx="12" cy="5" r="2"/><path d="M12 7v4"/></svg>'); ?>
                                                        <?php echo esc_html($bot['name']); ?>
                                                        <span style="font-size:0.75rem; color:var(--text-muted);">(<?php echo esc_html($platform_names[$bot['platform']] ?? $bot['platform']); ?>)</span>
                                                        <span style="font-size:0.7rem; color:var(--text-muted);">#<?php echo (int)$bot['id']; ?></span>
                                                        <?php if (!$has_token) : ?>
                                                            <span style="font-size:0.7rem; color:var(--warning);">! بدون توکن</span>
                                                        <?php endif; ?>
                                                    </div>
                                                    <div class="ssp-item-card-meta">
                                                        <?php echo count($bot['commands'] ?? []); ?> دستور |
                                                        <?php echo count($bot['buttons'] ?? []); ?> دکمه |
                                                        <?php echo count($bot['auto_replies'] ?? []); ?> پاسخ خودکار
                                                    </div>
                                                </div>
                                                <div style="display:flex; gap:6px;">
                                                    <span class="ssp-status-badge <?php echo !empty($bot['is_active']) ? 'active' : ''; ?>">
                                                        <?php echo !empty($bot['is_active']) ? 'فعال' : 'غیرفعال'; ?>
                                                    </span>
                                                    <button class="ssp-btn-icon" onclick="event.stopPropagation(); deleteBot(<?php echo (int)$bot['id']; ?>)" title="حذف"><svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/></svg></button>
                                                </div>
                                            </div>
                                        </div>
                                        <?php endforeach; ?>
                                    </div>
                                    <?php endif; ?>
                                </div>
                            </div>

                            <!-- Bot Templates -->
                            <div id="bot_templates_section" class="ssp-card" style="margin-bottom:16px;">
                                <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:16px;">
                                    <div>
                                        <h3 style="margin:0;">قالب‌های آماده</h3>
                                        <p class="ssp-hint" style="margin:4px 0 0 0;">یک قالب انتخاب کنید تا تنظیمات آن به صورت خودکار بارگذاری شود.</p>
                                    </div>
                                    <button type="button" class="ssp-btn-secondary" onclick="toggleTemplatesSection()" id="toggle_templates_btn">پنهان کردن</button>
                                </div>

                                <div id="templates_grid" class="ssp-grid-2" style="margin-top:12px;">
                                    <!-- Content Publisher Template -->
                                    <div class="ssp-item-card" style="cursor:pointer; border:2px solid var(--primary); position:relative; transition: all 0.2s;" onmouseover="this.style.transform='translateY(-2px)'; this.style.boxShadow='0 4px 12px rgba(0,0,0,0.1)'" onmouseout="this.style.transform=''; this.style.boxShadow=''" onclick="applyBotTemplate('content_publisher')">
                                        <div class="ssp-item-card-head">
                                            <div style="flex:1;">
                                                <div class="ssp-item-card-title">بات انتشار محتوا</div>
                                                <div class="ssp-item-card-meta" style="margin-top:4px;">ارسال و زمان‌بندی پست‌ها از طریق تلگرام</div>
                                                <div style="margin-top:8px; display:flex; gap:6px; flex-wrap:wrap;">
                                                    <span class="ssp-status-badge active" style="font-size:0.7rem;">4 دکمه</span>
                                                    <span class="ssp-status-badge" style="font-size:0.7rem;">4 پاسخ خودکار</span>
                                                    <span class="ssp-status-badge" style="font-size:0.7rem;">پیام خوش‌آمد</span>
                                                </div>
                                                <div style="margin-top:8px; font-size:0.75rem; color:var(--text-muted);">
                                                    بدون نیاز به دستورات /slash<br>
                                                    همه چیز با دکمه کار می‌کند<br>
                                                    آمار لحظه‌ای
                                                </div>
                                            </div>
                                            <div style="font-size:2.5rem; opacity:0.4;">□</div>
                                        </div>
                                    </div>

                                    <!-- FAQ Template -->
                                    <div class="ssp-item-card" style="cursor:pointer; border:2px solid var(--success); position:relative; transition: all 0.2s;" onmouseover="this.style.transform='translateY(-2px)'; this.style.boxShadow='0 4px 12px rgba(0,0,0,0.1)'" onmouseout="this.style.transform=''; this.style.boxShadow=''" onclick="applyBotTemplate('faq_bot')">
                                        <div class="ssp-item-card-head">
                                            <div style="flex:1;">
                                                <div class="ssp-item-card-title">? بات سوالات متداول</div>
                                                <div class="ssp-item-card-meta" style="margin-top:4px;">پاسخ خودکار به سوالات رایج مشتریان</div>
                                                <div style="margin-top:8px; display:flex; gap:6px; flex-wrap:wrap;">
                                                    <span class="ssp-status-badge active" style="font-size:0.7rem;">4 دکمه</span>
                                                    <span class="ssp-status-badge" style="font-size:0.7rem;">4 پاسخ خودکار</span>
                                                    <span class="ssp-status-badge" style="font-size:0.7rem;">پشتیبانی</span>
                                                </div>
                                                <div style="margin-top:8px; font-size:0.75rem; color:var(--text-muted);">
                                                    منوی دسته‌بندی شده<br>
                                                    ارسال به پشتیبانی<br>
                                                    مناسب فروشگاه‌ها
                                                </div>
                                            </div>
                                            <div style="font-size:2.5rem; opacity:0.4;">?</div>
                                        </div>
                                    </div>

                                    <!-- Appointment Template -->
                                    <div class="ssp-item-card" style="cursor:pointer; border:2px solid var(--warning); position:relative; transition: all 0.2s;" onmouseover="this.style.transform='translateY(-2px)'; this.style.boxShadow='0 4px 12px rgba(0,0,0,0.1)'" onmouseout="this.style.transform=''; this.style.boxShadow=''" onclick="applyBotTemplate('appointment')">
                                        <div class="ssp-item-card-head">
                                            <div style="flex:1;">
                                                <div class="ssp-item-card-title">بات نوبت‌دهی</div>
                                                <div class="ssp-item-card-meta" style="margin-top:4px;">رزرو نوبت و یادآوری وقت ملاقات</div>
                                                <div style="margin-top:8px; display:flex; gap:6px; flex-wrap:wrap;">
                                                    <span class="ssp-status-badge active" style="font-size:0.7rem;">سناریو</span>
                                                    <span class="ssp-status-badge" style="font-size:0.7rem;">۵ مرحله</span>
                                                    <span class="ssp-status-badge" style="font-size:0.7rem;">چندمسیره</span>
                                                </div>
                                                <div style="margin-top:8px; font-size:0.75rem; color:var(--text-muted);">
                                                    سناریوی چندمرحله‌ای<br>
                                                    انتخاب خدمت/تاریخ/ساعت<br>
                                                    مناسب کلینیک‌ها
                                                </div>
                                            </div>
                                            <div style="font-size:2.5rem; opacity:0.4;"><svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" style="vertical-align:middle;"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg></div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Bot Editor (hidden by default) -->
                            <div id="bot_editor_section" style="display:none;">
                                <input type="hidden" id="bot_editor_id">

                                <!-- Bot Info Card -->
                                <div class="ssp-card" style="margin-bottom:16px;">
                                    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:16px;">
                                        <h3 style="margin:0;" id="bot_editor_title">تنظیمات بات</h3>
                                        <button type="button" class="ssp-btn-secondary" onclick="showBotList()">← بازگشت به لیست</button>
                                    </div>

                                    <div class="ssp-grid-2">
                                        <div class="ssp-form-group">
                                            <label class="ssp-label">نام بات</label>
                                            <input type="text" id="bot_name" class="ssp-input" placeholder="مثلاً: بات پشتیبانی">
                                        </div>
                                        <div class="ssp-form-group">
                                            <label class="ssp-label">پلتفرم</label>
                                            <select id="bot_platform" class="ssp-select">
                                                <option value="telegram">🔵 تلگرام</option>
                                                <option value="bale">🟢 بله</option>
                                            </select>
                                        </div>
                                    </div>

                                    <div class="ssp-form-group">
                                        <label class="ssp-label">توکن ربات</label>
                                        <div class="ssp-input-group">
                                            <input type="password" id="bot_token" class="ssp-input" dir="ltr" placeholder="123456:ABC-DEF...">
                                            <button type="button" class="ssp-eye-btn" onclick="togglePass('bot_token')"><svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg></button>
                                        </div>
                                    </div>

                                    <div class="ssp-toggle" style="margin-top:0;">
                                        <label>
                                            <input type="checkbox" id="bot_active" checked>
                                            <span class="ssp-toggle-slider"></span>
                                            <span>فعال</span>
                                        </label>
                                    </div>

                                    <div style="margin-top:16px; display:flex; gap:10px; flex-wrap:wrap;">
                                        <button type="button" class="ssp-btn-primary" onclick="saveBotConfig()">ذخیره تنظیمات</button>
                                        <button type="button" class="ssp-btn-secondary" onclick="testBotConnection()">تست اتصال</button>
                                        <button type="button" class="ssp-btn-secondary" onclick="getBotStats()">آمار بات</button>
                                    </div>

                                    <div id="bot_connection_result" style="margin-top:12px; display:none;"></div>
                                </div>

                                <!-- Welcome Message -->
                                <div class="ssp-card" style="margin-bottom:16px;">
                                    <h3>پیام خوش‌آمدگویی</h3>
                                    <p class="ssp-hint">متنی که هنگام شروع کاربر با بات (/start) ارسال می‌شود.</p>
                                    <div class="ssp-form-group">
                                        <textarea id="bot_welcome" rows="4" class="ssp-textarea" placeholder="سلام!&#10;به بات ما خوش آمدید.&#10;&#10;از منوی زیر گزینه مورد نظر خود را انتخاب کنید."></textarea>
                                    </div>
                                    <button type="button" class="ssp-btn-primary" onclick="saveBotWelcome()">ذخیره پیام خوش‌آمدگویی</button>
                                </div>

                                <!-- Commands -->
                                <div class="ssp-card" style="margin-bottom:16px;">
                                    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:16px;">
                                        <h3 style="margin:0;">دستورات (/commands)</h3>
                                        <button type="button" class="ssp-btn-secondary" onclick="showAddCommandForm()">+ دستور جدید</button>
                                    </div>
                                    <p class="ssp-hint">دستوراتی که کاربران می‌توانند با تایپ آن‌ها در بات استفاده کنند.</p>

                                    <div id="bot_commands_list">
                                        <div class="ssp-empty" style="padding:20px;">
                                            <p>هنوز دستوری اضافه نکرده‌اید.</p>
                                        </div>
                                    </div>

                                    <!-- Add Command Form (hidden) -->
                                    <div id="add_command_form" style="display:none; margin-top:16px; padding:16px; background:var(--bg-alt); border-radius:12px;">
                                        <h4>افزودن دستور جدید</h4>
                                        <input type="hidden" id="edit_command_id">
                                        <div class="ssp-grid-2">
                                            <div class="ssp-form-group">
                                                <label class="ssp-label">نام دستور</label>
                                                <input type="text" id="command_name" class="ssp-input" dir="ltr" placeholder="مثلاً: help">
                                            </div>
                                            <div class="ssp-form-group">
                                                <label class="ssp-label">توضیحات (اختیاری)</label>
                                                <input type="text" id="command_desc" class="ssp-input" placeholder="نمایش راهنما">
                                            </div>
                                        </div>
                                        <div class="ssp-form-group">
                                            <label class="ssp-label">متن پاسخ</label>
                                            <textarea id="command_response" rows="4" class="ssp-textarea" placeholder="متنی که بات در پاسخ ارسال می‌کند..."></textarea>
                                        </div>
                                        <div style="display:flex; gap:10px;">
                                            <button type="button" class="ssp-btn-primary" onclick="saveCommand()">ذخیره دستور</button>
                                            <button type="button" class="ssp-btn-secondary" onclick="hideAddCommandForm()">انصراف</button>
                                        </div>
                                    </div>
                                </div>

                                <!-- Buttons -->
                                <div class="ssp-card" style="margin-bottom:16px;">
                                    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:16px;">
                                        <h3 style="margin:0;">دکمه‌ها (Keyboard)</h3>
                                        <button type="button" class="ssp-btn-secondary" onclick="showAddButtonForm()">+ دکمه جدید</button>
                                    </div>
                                    <p class="ssp-hint">دکمه‌هایی که زیر صفحه چت نمایش داده می‌شوند.</p>

                                    <div id="bot_buttons_list">
                                        <div class="ssp-empty" style="padding:20px;">
                                            <p>هنوز دکمه‌ای اضافه نکرده‌اید.</p>
                                        </div>
                                    </div>

                                    <!-- Button Preview -->
                                    <div id="button_preview" style="display:none; margin-top:16px; padding:16px; background:var(--bg-alt); border-radius:12px;">
                                        <h4>پیش‌نمایش دکمه‌ها</h4>
                                        <div id="keyboard_preview" style="display:flex; flex-direction:column; gap:6px; max-width:300px;"></div>
                                    </div>

                                    <!-- Add Button Form (hidden) -->
                                    <div id="add_button_form" style="display:none; margin-top:16px; padding:16px; background:var(--bg-alt); border-radius:12px;">
                                        <h4>افزودن دکمه جدید</h4>
                                        <input type="hidden" id="edit_button_id">

                                        <!-- Keyboard Type -->
                                        <div class="ssp-form-group">
                                            <label class="ssp-label">نوع کیبورد</label>
                                            <select id="button_keyboard_type" class="ssp-select" onchange="toggleButtonTypeFields()">
                                                <option value="inline">Inline Keyboard (زیر پیام)</option>
                                                <option value="reply"><svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" style="vertical-align:middle;"><rect x="2" y="4" width="20" height="16" rx="2" ry="2"/><line x1="6" y1="8" x2="6.01" y2="8"/><line x1="10" y1="8" x2="10.01" y2="8"/><line x1="14" y1="8" x2="14.01" y2="8"/><line x1="18" y1="8" x2="18.01" y2="8"/><line x1="8" y1="12" x2="8.01" y2="12"/><line x1="12" y1="12" x2="12.01" y2="12"/><line x1="16" y1="12" x2="16.01" y2="12"/><line x1="7" y1="16" x2="17" y2="16"/></svg> Reply Keyboard (زیر چت)</option>
                                            </select>
                                            <p class="ssp-hint">Inline: فقط دکمه‌های لینک، Callback، Web App و... | Reply: دکمه‌های ساده متنی</p>
                                            <p id="inline_type_warning" class="ssp-hint" style="color:var(--warning); display:none; margin-top:4px;">
                                                <svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" style="color:var(--warning);vertical-align:middle;margin-right:2px;"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg> در Inline Keyboard فقط دکمه‌های لینک، Callback، Web App و... مجاز هستند
                                            </p>
                                        </div>

                                        <div class="ssp-grid-2">
                                            <div class="ssp-form-group">
                                                <label class="ssp-label">متن دکمه</label>
                                                <input type="text" id="button_text" class="ssp-input" placeholder="مثلاً: راهنما">
                                            </div>
                                            <div class="ssp-form-group">
                                                <label class="ssp-label">نوع دکمه</label>
                                                <select id="button_type" class="ssp-select" onchange="toggleButtonTypeFields()">
                                                    <option value="simple">□ ساده (متن) - فقط Reply</option>
                                                    <option value="url">→ لینک (URL)</option>
                                                    <option value="callback"><svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" style="vertical-align:middle;margin-left:4px;"><polygon points="13 2 3 14 12 14 11 22 21 10 12 10 13 2"/></svg> Callback</option>
                                                    <option value="web_app">◎ Web App</option>
                                                    <option value="login_url">⊞ Login URL</option>
                                                    <option value="switch_inline">Switch Inline</option>
                                                    <option value="request_contact">درخواست شماره</option>
                                                    <option value="request_location">درخواست موقعیت</option>
                                                    <option value="request_poll">▥ درخواست نظرسنجی</option>
                                                    <option value="request_peer">درخواست کاربر/گروه</option>
                                                    <option value="pay">پرداخت</option>
                                                </select>
                                            </div>
                                        </div>

                                        <!-- Value Field (varies by type) -->
                                        <div id="button_value_group" class="ssp-form-group">
                                            <label class="ssp-label" id="button_value_label">مقدار</label>
                                            <input type="text" id="button_value" class="ssp-input" dir="ltr" placeholder="">
                                        </div>

                                        <!-- URL specific fields -->
                                        <div id="button_url_fields" style="display:none;">
                                            <div class="ssp-form-group">
                                                <label class="ssp-label">آدرس URL</label>
                                                <input type="url" id="button_url" class="ssp-input" dir="ltr" placeholder="https://example.com">
                                            </div>
                                        </div>

                                        <!-- Web App specific fields -->
                                        <div id="button_webapp_fields" style="display:none;">
                                            <div class="ssp-form-group">
                                                <label class="ssp-label">آدرس Web App</label>
                                                <input type="url" id="button_webapp_url" class="ssp-input" dir="ltr" placeholder="https://your-app.com">
                                            </div>
                                        </div>

                                        <!-- Login URL specific fields -->
                                        <div id="button_login_fields" style="display:none;">
                                            <div class="ssp-grid-2">
                                                <div class="ssp-form-group">
                                                    <label class="ssp-label">آدرس Login</label>
                                                    <input type="url" id="button_login_url" class="ssp-input" dir="ltr" placeholder="https://example.com/login">
                                                </div>
                                                <div class="ssp-form-group">
                                                    <label class="ssp-label">نام کاربری ربات</label>
                                                    <input type="text" id="button_bot_username" class="ssp-input" dir="ltr" placeholder="your_bot">
                                                </div>
                                            </div>
                                            <div class="ssp-form-group">
                                                <label class="ssp-label">متن انتقال (اختیاری)</label>
                                                <input type="text" id="button_forward_text" class="ssp-input" placeholder="متنی که کاربر می‌بیند...">
                                            </div>
                                        </div>

                                        <!-- Switch Inline specific fields -->
                                        <div id="button_switch_inline_fields" style="display:none;">
                                            <div class="ssp-form-group">
                                                <label class="ssp-label">کوئری Inline</label>
                                                <input type="text" id="button_switch_query" class="ssp-input" dir="ltr" placeholder="query to insert">
                                            </div>
                                            <div class="ssp-toggle" style="margin-top:0;">
                                                <label>
                                                    <input type="checkbox" id="button_switch_to_chat">
                                                    <span class="ssp-toggle-slider"></span>
                                                    <span>ارسال به چت جاری</span>
                                                </label>
                                            </div>
                                        </div>

                                        <!-- Request Peer specific fields -->
                                        <div id="button_request_peer_fields" style="display:none;">
                                            <div class="ssp-grid-2">
                                                <div class="ssp-form-group">
                                                    <label class="ssp-label">نوع درخواست</label>
                                                    <select id="button_peer_type" class="ssp-select">
                                                        <option value="user">کاربر</option>
                                                        <option value="chat">گروه</option>
                                                        <option value="channel">کانال</option>
                                                    </select>
                                                </div>
                                                <div class="ssp-form-group">
                                                    <label class="ssp-label">حداکثر تعداد</label>
                                                    <input type="number" id="button_max_quantity" class="ssp-input" value="1" min="1" max="10">
                                                </div>
                                            </div>
                                            <div class="ssp-grid-3" style="margin-top:8px;">
                                                <div class="ssp-toggle" style="margin-top:0;">
                                                    <label>
                                                        <input type="checkbox" id="button_name_requested">
                                                        <span class="ssp-toggle-slider"></span>
                                                        <span>درخواست نام</span>
                                                    </label>
                                                </div>
                                                <div class="ssp-toggle" style="margin-top:0;">
                                                    <label>
                                                        <input type="checkbox" id="button_username_requested">
                                                        <span class="ssp-toggle-slider"></span>
                                                        <span>درخواست یوزرنیم</span>
                                                    </label>
                                                </div>
                                                <div class="ssp-toggle" style="margin-top:0;">
                                                    <label>
                                                        <input type="checkbox" id="button_photo_requested">
                                                        <span class="ssp-toggle-slider"></span>
                                                        <span>درخواست عکس</span>
                                                    </label>
                                                </div>
                                            </div>
                                        </div>

                                        <!-- Callback specific fields -->
                                        <div id="button_callback_fields" style="display:none;">
                                            <div class="ssp-form-group">
                                                <label class="ssp-label">Callback Data</label>
                                                <input type="text" id="button_callback_data" class="ssp-input" dir="ltr" placeholder="action_name">
                                            </div>
                                            <div class="ssp-toggle" style="margin-top:0;">
                                                <label>
                                                    <input type="checkbox" id="button_requires_password">
                                                    <span class="ssp-toggle-slider"></span>
                                                    <span>نیاز به تأیید رمز عبور (SRP)</span>
                                                </label>
                                            </div>
                                        </div>

                                        <!-- Style Options - DISABLED due to Bot API incompatibility -->
                                        <!--
                                        <div class="ssp-card" style="margin-top:12px; padding:12px; background:var(--surface);">
                                            <h5 style="margin:0 0 8px 0; font-size:0.9rem;">استایل دکمه</h5>
                                            <div class="ssp-grid-2">
                                                <div class="ssp-form-group">
                                                    <label class="ssp-label">رنگ پس‌زمینه</label>
                                                    <select id="button_style_bg" class="ssp-select">
                                                        <option value="">پیش‌فرض</option>
                                                        <option value="primary">🔵 آبی (عمل اصلی)</option>
                                                        <option value="danger">قرمز (عمل مخرب)</option>
                                                        <option value="success">🟢 سبز (عمل مثبت)</option>
                                                    </select>
                                                </div>
                                                <div class="ssp-form-group">
                                                    <label class="ssp-label">آیکون (Emoji ID)</label>
                                                    <input type="text" id="button_style_icon" class="ssp-input" dir="ltr" placeholder="Custom Emoji ID">
                                                    <p class="ssp-hint" style="font-size:0.75rem;">اختیاری - شناسه ایموجی سفارشی</p>
                                                </div>
                                            </div>
                                        </div>
                                        -->

                                        <!-- Layout Options -->
                                        <div class="ssp-grid-2" style="margin-top:12px;">
                                            <div class="ssp-form-group">
                                                <label class="ssp-label">شماره ردیف</label>
                                                <input type="number" id="button_row" class="ssp-input" value="0" min="0">
                                            </div>
                                            <div class="ssp-form-group">
                                                <label class="ssp-label">شماره ستون</label>
                                                <input type="number" id="button_col" class="ssp-input" value="0" min="0">
                                            </div>
                                        </div>

                                        <!-- Child Buttons (Button Tree) -->
                                        <div class="ssp-form-group" style="margin-top:12px;">
                                            <label class="ssp-label">دکمه‌های فرزند (درخت دکمه‌ها)</label>
                                            <p class="ssp-hint">وقتی کاربر این دکمه را کلیک کند، این دکمه‌ها نمایش داده می‌شوند. دکمه "بازگشت" به صورت خودکار اضافه می‌شود.</p>
                                            <div id="child_buttons_container">
                                                <!-- Child buttons will be added here -->
                                            </div>
                                            <button type="button" class="ssp-btn-secondary" onclick="addChildButtonField()" style="margin-top:8px;">+ دکمه فرزند</button>
                                        </div>

                                        <div style="display:flex; gap:10px; margin-top:16px;">
                                            <button type="button" class="ssp-btn-primary" onclick="saveButton()">ذخیره دکمه</button>
                                            <button type="button" class="ssp-btn-secondary" onclick="hideAddButtonForm()">انصراف</button>
                                        </div>
                                    </div>
                                </div>

                                <!-- Auto Replies -->
                                <div class="ssp-card" style="margin-bottom:16px;">
                                    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:16px;">
                                        <h3 style="margin:0;">پاسخ‌های خودکار</h3>
                                        <button type="button" class="ssp-btn-secondary" onclick="showAddAutoReplyForm()">+ پاسخ جدید</button>
                                    </div>
                                    <p class="ssp-hint">پاسخ‌هایی که بر اساس کلمات کلیدی ارسال می‌شوند.</p>

                                    <div id="bot_auto_replies_list">
                                        <div class="ssp-empty" style="padding:20px;">
                                            <p>هنوز پاسخ خودکاری اضافه نکرده‌اید.</p>
                                        </div>
                                    </div>

                                    <!-- Add Auto Reply Form (hidden) -->
                                    <div id="add_auto_reply_form" style="display:none; margin-top:16px; padding:16px; background:var(--bg-alt); border-radius:12px;">
                                        <h4>افزودن پاسخ خودکار</h4>
                                        <input type="hidden" id="edit_reply_id">
                                        <div class="ssp-grid-2">
                                            <div class="ssp-form-group">
                                                <label class="ssp-label">کلمه/عبارت محرک</label>
                                                <input type="text" id="reply_trigger" class="ssp-input" placeholder="مثلاً: قیمت">
                                            </div>
                                            <div class="ssp-form-group">
                                                <label class="ssp-label">نوع تطابق</label>
                                                <select id="reply_match_type" class="ssp-select">
                                                    <option value="exact">دقیق</option>
                                                    <option value="contains">شامل</option>
                                                    <option value="starts_with">شروع با</option>
                                                </select>
                                            </div>
                                        </div>
                                        <div class="ssp-form-group">
                                            <label class="ssp-label">متن پاسخ</label>
                                            <textarea id="reply_response" rows="4" class="ssp-textarea" placeholder="متن پاسخ خودکار..."></textarea>
                                        </div>
                                        <div style="display:flex; gap:10px;">
                                            <button type="button" class="ssp-btn-primary" onclick="saveAutoReply()">ذخیره پاسخ</button>
                                            <button type="button" class="ssp-btn-secondary" onclick="hideAddAutoReplyForm()">انصراف</button>
                                        </div>
                                    </div>
                                </div>

                                <!-- Scenarios -->
                                <div class="ssp-card" style="margin-bottom:16px;">
                                    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:16px;">
                                        <div>
                                            <h3 style="margin:0;">سناریوها (گفتگوی چندمرحله‌ای)</h3>
                                            <p class="ssp-hint" style="margin:4px 0 0 0;">سناریوهای چندمرحله‌ای با دکمه‌ها بسازید. کاربر با کلیک روی دکمه‌ها مراحل را طی می‌کند.</p>
                                        </div>
                                        <button type="button" class="ssp-btn-secondary" onclick="showAddScenarioForm()">+ سناریو جدید</button>
                                    </div>

                                    <div id="bot_scenarios_list">
                                        <div class="ssp-empty" style="padding:20px;">
                                            <p>هنوز سناریویی اضافه نکرده‌اید.</p>
                                            <p style="font-size:0.85rem; color:var(--text-muted);">سناریوها به شما امکان ایجاد گفتگوهای چندمرحله‌ای با کاربر را می‌دهند.</p>
                                        </div>
                                    </div>

                                    <!-- Add Scenario Form (hidden) -->
                                    <div id="add_scenario_form" style="display:none; margin-top:16px; padding:16px; background:var(--bg-alt); border-radius:12px;">
                                        <h4>افزودن سناریوی جدید</h4>
                                        <input type="hidden" id="edit_scenario_id">

                                        <div class="ssp-grid-2">
                                            <div class="ssp-form-group">
                                                <label class="ssp-label">نام سناریو</label>
                                                <input type="text" id="scenario_name" class="ssp-input" placeholder="مثلاً: رزرو نوبت">
                                            </div>
                                            <div class="ssp-form-group">
                                                <label class="ssp-label">محرک شروع</label>
                                                <input type="text" id="scenario_trigger" class="ssp-input" dir="ltr" placeholder="/start یا نام دکمه">
                                                <p class="ssp-hint">کلمه یا دستوری که سناریو را شروع می‌کند</p>
                                            </div>
                                        </div>

                                        <!-- Steps Editor -->
                                        <div class="ssp-form-group">
                                            <label class="ssp-label">مراحل سناریو</label>
                                            <p class="ssp-hint">هر مرحله شامل یک پیام و دکمه‌هایی است که کاربر می‌تواند انتخاب کند.</p>

                                            <div id="scenario_steps_container">
                                                <!-- Steps will be added here dynamically -->
                                            </div>

                                            <button type="button" class="ssp-btn-secondary" onclick="addScenarioStep()" style="margin-top:12px;">+ افزودن مرحله</button>
                                        </div>

                                        <div style="display:flex; gap:10px; margin-top:16px;">
                                            <button type="button" class="ssp-btn-primary" onclick="saveScenario()">ذخیره سناریو</button>
                                            <button type="button" class="ssp-btn-secondary" onclick="hideAddScenarioForm()">انصراف</button>
                                        </div>
                                    </div>
                                </div>

                                <!-- Webhook & Advanced -->
                                <div class="ssp-card" style="margin-bottom:16px;">
                                    <h3 style="margin-bottom:12px;">تنظیمات پیشرفته</h3>

                                    <div class="ssp-form-group">
                                        <label class="ssp-label">آدرس Webhook</label>
                                        <div style="display:flex; gap:8px; align-items:center;">
                                            <input type="text" id="bot_webhook_url_display" class="ssp-input" dir="ltr" readonly style="flex:1; background:var(--bg-alt); color:var(--text-muted); font-size:0.85rem;">
                                            <button type="button" class="ssp-btn-secondary" onclick="navigator.clipboard.writeText(document.getElementById('bot_webhook_url_display').value); showToast('کپی شد!', 'success');" style="white-space:nowrap;">کپی</button>
                                        </div>
                                        <p class="ssp-hint" style="margin-top:6px;">وبهوک هنگام ذخیره بات به صورت خودکار تنظیم می‌شود. در صورت عدم موفقیت، از دکمه زیر استفاده کنید.</p>
                                    </div>

                                    <div id="bot_webhook_status" style="display:none; padding:10px 14px; border-radius:8px; margin-bottom:12px; font-size:0.85rem;"></div>

                                    <div style="display:flex; gap:10px; flex-wrap:wrap;">
                                        <button type="button" class="ssp-btn-primary" onclick="setBotWebhookManual()" style="background:#8b5cf6;">
                                            <span class="ssp-btn-text">تنظیم مجدد Webhook</span>
                                            <span class="ssp-btn-spinner"></span>
                                        </button>
                                        <button type="button" class="ssp-btn-secondary" onclick="testBotWebhook()"><span class="ssp-btn-text">تست اتصال Webhook</span><span class="ssp-btn-spinner"></span></button>
                                        <button type="button" class="ssp-btn-secondary" onclick="setBotMenu()">تنظیم منوی بات</button>
                                        <button type="button" class="ssp-btn-secondary" onclick="debugBotList()" style="background:var(--warning-soft); color:var(--warning);">Debug: لیست بات‌ها</button>
                                        <button type="button" class="ssp-btn-secondary" onclick="debugBotButtons()" style="background:var(--info-soft); color:var(--info);">Debug: دکمه‌ها</button>
                                    </div>

                                    <div class="ssp-card" style="margin-top:12px; padding:12px; background:var(--bg-alt); font-size:0.85rem;">
                                        <strong>راهنما:</strong>
                                        <ul style="margin:8px 0 0; padding-right:20px; color:var(--text-muted);">
                                            <li>آدرس وبهوک باید با HTTPS شروع شود</li>
                                            <li>سرور باید درخواست‌های POST از تلگرام را بپذیرد</li>
                                            <li>فایروال نباید IP‌های تلگرام را بلاک کند</li>
                                            <li>در صورت استفاده از CDN، webhook ممکن است کار نکند</li>
                                        </ul>
                                    </div>
                                </div>
                            </div>
                            <?php endif; // end Pro guard for botbuilder ?>
                        </div>

                        <!-- ============ WP SOURCES ============ -->
                        <div id="tab-wpsources" class="tab-content">
                            <h2>
                                منابع وردپرس
                                <span class="ssp-counter <?php echo count($wp_sites) >= 10 ? 'max' : ''; ?>">
                                    <span class="count"><?php echo count($wp_sites); ?></span> / 10
                                </span>
                            </h2>
                            <p class="ssp-section-desc">محتوا را از سایت‌های وردپرسی دیگر دریافت کنید (برای بازنشر در پیام‌رسان‌ها).</p>

                            <div class="ssp-guide">
                                <h3>اتصال منبع وردپرس</h3>
                                <ol class="ssp-guide-steps">
                                    <li>آدرس سایت وردپرسی مورد نظر را وارد کنید</li>
                                    <li>سیستم به صورت خودکار پست‌های جدید را دریافت می‌کند</li>
                                    <li>محتوا را می‌توانید در پیام‌رسان‌ها بازنشر کنید</li>
                                    <li><strong>توجه:</strong> این سیستم فقط دریافت محتوا دارد، انتشار خودکار انجام نمی‌شود</li>
                                </ol>
                            </div>

                            <div id="wp_sites_list">
                                <?php if (empty($wp_sites)) : ?>
                                <div class="ssp-empty">
                                    <div class="ssp-empty-icon"><svg viewBox="0 0 24 24" width="48" height="48" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M5 12.55a11 11 0 0 1 14.08 0"/><path d="M1.42 9a16 16 0 0 1 21.16 0"/><path d="M8.53 16.11a6 6 0 0 1 6.95 0"/><line x1="12" y1="20" x2="12.01" y2="20"/></svg></div>
                                    <p>هنوز منبع وردپرسی اضافه نکرده‌اید.</p>
                                </div>
                                <?php else : ?>
                                    <?php foreach ($wp_sites as $site) : ?>
                                    <div class="ssp-item-card" data-id="<?php echo (int)$site['id']; ?>">
                                        <div class="ssp-item-card-head">
                                            <div>
                                                <div class="ssp-item-card-title">
                                                    ◉ <?php echo esc_html($site['site_name']); ?>
                                                    <span class="ssp-badge <?php echo $site['is_active'] ? 'active' : 'inactive'; ?>">
                                                        <?php echo $site['is_active'] ? 'فعال' : 'غیرفعال'; ?>
                                                    </span>
                                                </div>
                                                <div class="ssp-item-card-meta">
                                                    <?php echo esc_html($site['site_url']); ?>
                                                </div>
                                            </div>
                                            <div style="display:flex; gap:6px;">
                                                <button class="ssp-btn-test btn-test-wpsite" data-id="<?php echo (int)$site['id']; ?>">تست</button>
                                                <button class="ssp-btn-secondary btn-edit-wpsite" data-id="<?php echo (int)$site['id']; ?>">ويرايش</button>
                                                <button class="ssp-btn-danger btn-delete-wpsite" data-id="<?php echo (int)$site['id']; ?>">حذف</button>
                                            </div>
                                        </div>
                                        <span id="wp_site_status_<?php echo (int)$site['id']; ?>" class="ssp-connection-status"></span>
                                    </div>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </div>

                            <?php if (count($wp_sites) < 10) : ?>
                            <div class="ssp-add-form" id="wp_site_add_form">
                                <h3>افزودن سایت وردپرسی جدید</h3>
                                <div class="ssp-grid-2">
                                    <div class="ssp-form-group">
                                        <label class="ssp-label">نام دلخواه</label>
                                        <input type="text" id="new_site_name" class="ssp-input" placeholder="مثلاً: فروشگاه من">
                                    </div>
                                    <div class="ssp-form-group">
                                        <label class="ssp-label">آدرس سایت</label>
                                        <input type="url" id="new_site_url" class="ssp-input" dir="ltr" placeholder="https://example.com">
                                    </div>
                                </div>
                                <div class="ssp-grid-2">
                                    <div class="ssp-form-group">
                                        <label class="ssp-label">نام کاربری</label>
                                        <input type="text" id="new_site_user" class="ssp-input" dir="ltr" placeholder="نام کاربری WordPress">
                                    </div>
                                    <div class="ssp-form-group">
                                        <label class="ssp-label">Application Password</label>
                                        <div class="ssp-input-group">
                                            <input type="password" id="new_site_pass" class="ssp-input" dir="ltr" placeholder="رمز نرم‌افزاری از پنل وردپرس">
                                            <button type="button" class="ssp-eye-btn" onclick="togglePass('new_site_pass')"><svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg></button>
                                        </div>
                                        <p class="ssp-hint" style="margin-top:4px; font-size:0.75rem; color:var(--text-subtle);">از نمایه > کاربران > ویرایش > رمز نرم‌افزاری ساخته شود</p>
                                    </div>
                                </div>
                                <div class="ssp-grid-2">
                                    <div class="ssp-toggle" style="margin-top:0;">
                                        <label>
                                            <input type="checkbox" id="new_site_active" checked>
                                            <span class="ssp-toggle-slider"></span>
                                            <span>فعال</span>
                                        </label>
                                    </div>
                                    <div class="ssp-toggle" style="margin-top:0;">
                                        <label>
                                            <input type="checkbox" id="new_site_auto" checked>
                                            <span class="ssp-toggle-slider"></span>
                                            <span>انتشار خودکار</span>
                                        </label>
                                    </div>
                                </div>
                                <div class="ssp-grid-2" style="margin-top:12px;">
                                    <div class="ssp-form-group">
                                        <label class="ssp-label">نوع محتوای پیش‌فرض</label>
                                        <input type="text" id="new_site_post_type" class="ssp-input" value="post" placeholder="مثلاً: post, product, portfolio">
                                        <small class="ssp-hint" style="display:block; margin-top:4px; color:#64748b;">نام پست‌تایپ را وارد کنید (مانند post برای نوشته، product برای محصول)</small>
                                    </div>
                                    <div class="ssp-form-group">
                                        <label class="ssp-label">دسته‌بندی‌ها (IDs)</label>
                                        <input type="text" id="new_site_categories" class="ssp-input" placeholder="1,2,3">
                                        <button type="button" class="ssp-btn-test" onclick="fetchCategories('new')" style="margin-top:6px;">دریافت دسته‌بندی‌ها</button>
                                        <div id="new_cat_list" class="ssp-hint" style="margin-top:4px;"></div>
                                    </div>
                                </div>
                                <div style="margin-top:16px; display:flex; gap:10px; align-items:center;">
                                    <button type="button" class="ssp-btn-primary" onclick="addWpSite()" id="add_wpsite_btn"><span class="ssp-btn-text">ذخیره سایت</span><span class="ssp-btn-spinner"></span></button>
                                    <button class="ssp-btn-test" onclick="testNewWpSite()" type="button">تست اتصال</button>
                                    <span class="ssp-saved-indicator" id="wp_site_saved">ذخیره شد!</span>
                                </div>
                                <div id="new_site_test_output" class="ssp-output-box" style="display:none; margin-top:12px;"></div>
                            </div>
                            <?php endif; ?>
                        </div>

                        <!-- ============ POST GENERATOR ============ -->
                        <div id="tab-postgen" class="tab-content">
                            <?php if ($plan === 'free') : ?>
                            <div style="text-align:center; padding:60px 20px;">
                                <div style="width:64px;height:64px;border-radius:50%;background:#eef2ff;display:flex;align-items:center;justify-content:center;margin:0 auto 20px;">
                                    <svg viewBox="0 0 24 24" width="32" height="32" fill="none" stroke="#4f46e5" stroke-width="2"><polygon points="13 2 3 14 12 14 11 22 21 10 12 10 13 2"/></svg>
                                </div>
                                <h3 style="color:#1e293b; margin:0 0 8px;">قابلیت حرفه‌ای</h3>
                                <p style="color:#64748b; margin:0 0 20px; font-size:0.9rem;">تولید پست ویژگی پلن Pro است. با ارتقا به پلن حرفه‌ای، به این ابزار دسترسی پیدا کنید.</p>
                                <a href="#" onclick="switchTab('subscription', document.querySelector('[data-tab=subscription]')); return false;" style="background:#4f46e5; color:#fff; padding:12px 28px; border-radius:10px; text-decoration:none; font-weight:700; display:inline-block;">ارتقا به Pro</a>
                            </div>
                            <?php else : ?>
                            <h2 class="ssp-tool-title" style="display:flex;align-items:center;gap:10px;margin:0 0 8px;"><svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" style="display:inline-block;vertical-align:middle;flex-shrink:0;"><polygon points="13 2 3 14 12 14 11 22 21 10 12 10 13 2"/></svg> <span>تولید پست</span></h2>
                            <p class="ssp-section-desc">محتوای مناسب پیام‌رسان‌ها (تلگرام، اینستاگرام، واتساپ و...) را با کمک AI تولید کنید.</p>

                            <?php if (!$ai_configured) : ?>
                            <div class="ssp-empty" style="background:var(--warning-soft); border:1px solid var(--warning); border-radius:12px;">
                                <div class="ssp-empty-icon"><svg viewBox="0 0 24 24" width="48" height="48" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg></div>
                                <h3 style="color:var(--warning);"> هوش مصنوعی تنظیم نشده است</h3>
                                <p>برای استفاده از ابزارهای AI، ابتدا یکی از حالت‌های زیر را فعال کنید:</p>
                                <ul style="color:var(--text-muted); margin:8px 0 16px; padding-right:20px; text-align:right;">
                                    <li><strong>حالت API</strong>: کلید API یکی از ارائه‌دهندگان را وارد کنید</li>
                                    <li><strong>حالت مرورگر</strong>: از طریق مرورگر خود (DeepSeek یا ChatGPT) استفاده کنید</li>
                                </ul>
                                <button type="button" class="ssp-btn-primary" onclick="switchTab('ai', document.querySelector('[data-tab=ai]'))">رفتن به تنظیمات AI</button>
                            </div>
                            <?php else : ?>

                            <!-- Load from Draft -->
                            <div class="ssp-card" style="margin-bottom:16px; border-style:dashed;">
                                <div style="display:flex; align-items:center; gap:10px; flex-wrap:wrap;">
                                    <span style="font-size:0.85rem; color:var(--text-muted);">&#128194; بارگذاری از پیش‌نویس:</span>
                                    <select id="pg_load_draft" class="ssp-select" style="max-width:300px;" onchange="pgLoadDraft(this.value)">
                                        <option value="">انتخاب پیش‌نویس...</option>
                                    </select>
                                    <button type="button" class="ssp-btn-secondary" onclick="pgRefreshDrafts()" style="font-size:0.8rem; padding:4px 10px;">بازخوانی</button>
                                </div>
                            </div>

                            <!-- Step 1: Content Details -->
                            <div class="ssp-card" style="margin-bottom:16px;">
                                <h3 style="margin-bottom:12px;">جزئیات محتوا</h3>
                                <div class="ssp-form-group">
                                    <label class="ssp-label">موضوع / عنوان اصلی *</label>
                                    <input type="text" id="pg_topic" class="ssp-input" placeholder="مثلاً: معرفی محصول جدید، خبر فناوری،...">
                                </div>
                                <div class="ssp-grid-2">
                                    <div class="ssp-form-group">
                                        <label class="ssp-label">سبک محتوا</label>
                                        <select id="pg_style" class="ssp-select">
                                            <option value="general">عمومی</option>
                                            <option value="formal">رسمی</option>
                                            <option value="casual">صمیمی</option>
                                            <option value="promotional">تبلیغاتی</option>
                                            <option value="educational">آموزشی</option>
                                            <option value="news">خبری</option>
                                        </select>
                                    </div>
                                    <div class="ssp-form-group">
                                        <label class="ssp-label">اندازه محتوا</label>
                                        <select id="pg_length" class="ssp-select">
                                            <option value="400">خیلی کوتاه (حدود ۴۰۰ کاراکتر)</option>
                                            <option value="700">کوتاه (حدود ۷۰۰ کاراکتر)</option>
                                            <option value="1000">متوسط کوتاه (حدود ۱۰۰۰ کاراکتر)</option>
                                            <option value="2000" selected>متوسط (حدود ۲۰۰۰ کاراکتر)</option>
                                            <option value="3000">بلند (حدود ۳۰۰۰ کاراکتر)</option>
                                            <option value="4000">خیلی بلند (حدود ۴۰۰۰ کاراکتر)</option>
                                        </select>
                                    </div>
                                </div>
                                <div class="ssp-form-group">
                                    <label class="ssp-label">توضیحات تکمیلی (اختیاری)</label>
                                    <textarea id="pg_details" class="ssp-textarea" rows="2" placeholder="اطلاعات بیشتر درباره موضوع بنویسید..."></textarea>
                                </div>
                            </div>

                            <!-- Step 3: Generate -->
                            <div class="ssp-card" style="margin-bottom:16px;">
                                <h3 style="margin-bottom:12px;">تولید با AI</h3>
                                <div style="display:flex; gap:10px; align-items:center; flex-wrap:wrap;">
                                    <?php if ((get_user_meta($user_id, 'ssp_ai_mode', true) ?: 'api') !== 'browser') : ?>
                                    <button type="button" class="ssp-btn-primary" onclick="pgGeneratePost()" id="pg_gen_btn">
                                        <span class="ssp-btn-text">تولید پست با API</span>
                                        <span class="ssp-btn-spinner"></span>
                                    </button>
                                    <?php endif; ?>
                                    <?php if ((get_user_meta($user_id, 'ssp_ai_mode', true) ?: 'api') === 'browser') : ?>
                                    <button type="button" class="ssp-btn-primary" style="background:#10B981; color:white;" onclick="pgGeneratePostViaBrowser()" id="pg_post_browser_btn">
                                        <span class="ssp-btn-text">تولید پست با چت‌بات رایگان</span>
                                        <span class="ssp-btn-spinner"></span>
                                    </button>
                                    <?php endif; ?>
                                    <button class="ssp-btn-secondary" onclick="pgResetPostForm()" type="button">پاک کردن</button>
                                    <span id="pg_gen_tokens" class="ssp-badge" style="display:none;"></span>
                                </div>
                                <p class="ssp-hint" style="margin-top:8px;">با API: سریع‌تر و مستقیم (نیاز به کلید API) | با چت‌بات رایگان: از حساب رایگان DeepSeek/ChatGPT (نیاز به افزونه مرورگر)</p>
                                <div id="pg_gen_error" style="color:var(--error); font-size:0.85rem; margin-top:8px; display:none;"></div>
                            </div>

                            <!-- Step 4: Result -->
                            <div id="pg_post_result" style="display:none;">
                                <div class="ssp-card" style="margin-bottom:16px; border-color:var(--success);">
                                    <h3 style="margin-bottom:12px;">محتوای تولید شده</h3>
                                    <div class="ssp-form-group">
                                        <label class="ssp-label">عنوان</label>
                                        <input type="text" id="pg_result_title" class="ssp-input">
                                    </div>
                                    <div class="ssp-form-group">
                                        <label class="ssp-label">محتوا</label>
                                        <div style="display:flex; gap:6px; margin-bottom:6px;">
                                            <button type="button" class="ssp-btn-secondary" onclick="pgToggleView('visual')" id="pg_view_visual_btn" style="font-size:0.8rem; padding:4px 10px; border-color:var(--accent); color:var(--accent);">نمایش بصری</button>
                                            <button type="button" class="ssp-btn-secondary" onclick="pgToggleView('raw')" id="pg_view_raw_btn" style="font-size:0.8rem; padding:4px 10px;">متن خام</button>
                                        </div>
                                        <textarea id="pg_result_content" class="ssp-textarea" rows="8" style="display:none;"></textarea>
                                        <div id="pg_result_preview" contenteditable="false" style="min-height:120px; padding:12px; background:var(--card); border:1px solid var(--border); border-radius:8px; white-space:pre-wrap; font-size:0.9rem; color:var(--text); line-height:1.7; outline:none; direction:rtl;"></div>
                                    </div>
                                    <div class="ssp-form-group">
                                        <label class="ssp-label">هشتگ‌ها</label>
                                        <input type="text" id="pg_result_hashtags" class="ssp-input" placeholder="#tag1 #tag2">
                                    </div>
                                    <div class="ssp-form-group">
                                        <label class="ssp-label">تصویر، ویدیو، صوت یا فایل/داکیومنت (اختیاری)</label>
                                        <input type="hidden" id="pg_image_url" value="">
                                        <div style="display:flex; gap:0; margin-bottom:0;">
                                            <button type="button" class="ssp-btn-secondary pg_media_tab active" onclick="switchPgMediaTab('upload')" style="border-radius:0 0 0 8px; flex:1; font-size:0.8rem; padding:6px;">آپلود فایل / داکیومنت</button>
                                            <button type="button" class="ssp-btn-secondary pg_media_tab" onclick="switchPgMediaTab('url')" style="border-radius:0 0 8px 0; flex:1; font-size:0.8rem; padding:6px;">از لینک مستقیم URL</button>
                                        </div>
                                        <div id="pg_media_tab_upload">
                                            <div id="pg_media_upload" style="position:relative; border:2px dashed var(--border); border-top:none; border-radius:0 0 10px 10px; padding:16px; text-align:center; cursor:pointer; transition:all 0.2s;" onclick="document.getElementById('pg_media_file').click()">
                                                <input type="file" id="pg_media_file" accept="image/*,video/*,audio/*,.pdf,.doc,.docx,.xls,.xlsx,.ppt,.pptx,.txt,.csv,.zip,.rar,.tar,.gz,.7z,.apk" multiple style="display:none;" onchange="handlePgMediaUpload(this)">
                                                <div id="pg_media_placeholder">
                                                    <svg viewBox="0 0 24 24" width="32" height="32" fill="none" stroke="currentColor" stroke-width="1.5" style="color:var(--text-muted);"><rect x="3" y="3" width="18" height="18" rx="2" ry="2"/><circle cx="8.5" cy="8.5" r="1.5"/><polyline points="21 15 16 10 5 21"/></svg>
                                                    <p style="color:var(--text-muted); margin:8px 0 0; font-size:0.85rem;">فایل‌ها یا اسناد را اینجا رها کنید یا کلیک کنید</p>
                                                    <p style="color:var(--text-subtle); margin:4px 0 0; font-size:0.75rem;">تصاویر تا ۱۵MB، ویدیوها تا ۸۰MB، داکیومنت‌ها و فایل‌ها تا ۵۰MB</p>
                                                </div>
                                                <div id="pg_media_preview" style="display:none;"></div>
                                                <div id="pg_media_progress" style="display:none; margin-top:8px;">
                                                    <div style="height:4px; background:var(--border); border-radius:2px; overflow:hidden;"><div id="pg_media_progress_bar" style="height:100%; background:var(--accent); width:0%; transition:width 0.3s;"></div></div>
                                                    <div style="font-size:0.75rem; color:var(--text-muted); margin-top:4px;" id="pg_media_status">در حال آپلود...</div>
                                                </div>
                                            </div>
                                        </div>
                                        <div id="pg_media_tab_url" style="display:none;">
                                            <div style="border:2px dashed var(--border); border-top:none; border-radius:0 0 10px 10px; padding:12px;">
                                                <div style="display:flex; gap:8px;">
                                                    <input type="url" id="pg_media_url_input" class="ssp-input" dir="ltr" placeholder="https://example.com/file.pdf" style="flex:1;">
                                                    <button type="button" class="ssp-btn-secondary" onclick="addPgMediaUrl()" style="white-space:nowrap;">افزودن</button>
                                                </div>
                                                <div id="pg_media_url_list" style="margin-top:8px;"></div>
                                            </div>
                                        </div>
                                        <div id="pg_media_notice" style="display:none; padding:8px 12px; background:var(--warning-soft); border:1px solid var(--warning); border-radius:8px; margin:8px 0; font-size:0.8rem; color:var(--warning);">
                                            <strong>توجه:</strong> ایتا و روبیکا فقط یک تصویر/ویدیو را پشتیبانی می‌کنند. در صورت ارسال چند فایل، فقط اولین فایل ارسال می‌شود.
                                        </div>
                                    </div>
                                    <div style="display:flex; gap:10px; flex-wrap:wrap;">
                                        <button type="button" class="ssp-btn-primary" onclick="pgSendPost()">ارسال به پیام‌رسان‌ها</button>
                                        <button type="button" class="ssp-btn-secondary" onclick="pgSchedulePost()">زمان‌بندی ارسال</button>
                                        <button type="button" class="ssp-btn-secondary" onclick="pgSaveAsDraftPost()">ذخیره به عنوان پیش‌نویس</button>
                                        <button type="button" class="ssp-btn-secondary" onclick="pgCopyPost()">کپی</button>
                                    </div>
                                    <!-- Schedule Mini-Form -->
                                    <div id="pg_schedule_form" style="display:none; margin-top:16px; padding:16px; background:var(--bg-alt); border-radius:12px; border:1px solid var(--border);">
                                        <h4 style="margin-bottom:12px;">زمان‌بندی ارسال</h4>
                                        <div class="ssp-grid-2">
                                            <div class="ssp-form-group">
                                                <label class="ssp-label">تاریخ شمسی</label>
                                                <input type="text" id="pg_schedule_date_display" class="ssp-input" readonly placeholder="تاریخ را انتخاب کنید" style="cursor:pointer;" onclick="pgOpenJalaliPicker()">
                                                <input type="hidden" id="pg_schedule_datetime">
                                                <div class="ssp-hint" style="margin-top:4px;">حداقل ۱۰ دقیقه آینده</div>
                                                <div id="pg_jalali_picker" style="display:none; margin-top:8px; padding:12px; background:var(--card); border:1px solid var(--border); border-radius:8px;"></div>
                                            </div>
                                            <div class="ssp-form-group">
                                                <label class="ssp-label">تکرار</label>
                                                <select id="pg_schedule_recurring" class="ssp-select">
                                                    <option value="">بدون تکرار</option>
                                                    <option value="daily">روزانه</option>
                                                    <option value="weekly">هفتگی</option>
                                                    <option value="monthly">ماهانه</option>
                                                </select>
                                            </div>
                                        </div>
                                        <div style="display:flex; gap:10px; margin-top:12px;">
                                            <button type="button" class="ssp-btn-primary" onclick="pgConfirmSchedule()">تایید زمان‌بندی</button>
                                            <button type="button" class="ssp-btn-secondary" onclick="document.getElementById('pg_schedule_form').style.display='none'">انصراف</button>
                                        </div>
                                    </div>
                                </div>
                            </div>

                                <!-- Per-Messenger Selection -->
                                <div class="ssp-card">
                                    <h3 style="margin-bottom:12px;">انتخاب پیام‌رسان مقصد</h3>
                                    <div style="display:flex; gap:6px; flex-wrap:wrap;">
                                        <?php
                                        $pg_platform_icons = ['telegram' => '🔵', 'bale' => '🟢', 'eitaa' => '🟠', 'rubika' => '🟣', 'instagram' => '📷', 'whatsapp' => '💬'];
                                        $pg_platform_names = ['telegram' => 'تلگرام', 'bale' => 'بله', 'eitaa' => 'ایتا', 'rubika' => 'روبیکا', 'instagram' => 'اینستاگرام', 'whatsapp' => 'واتساپ'];
                                        ?>
                                        <?php foreach ($messengers as $m) : ?>
                                        <label style="display:inline-flex; align-items:center; gap:6px; padding:6px 12px; background:var(--bg-alt); border-radius:8px; border:1px solid var(--border); cursor:pointer; font-size:0.85rem;">
                                            <input type="checkbox" name="pg_dest_messengers[]" value="<?php echo (int)$m['id']; ?>" <?php echo $m['is_active'] ? 'checked' : ''; ?> style="accent-color:var(--accent);">
                                            <?php echo ($pg_platform_icons[$m['platform']] ?? '') . ' ' . esc_html($m['name']) . ' <span style="font-size:0.75rem; color:var(--text-muted);">(' . ($pg_platform_names[$m['platform']] ?? $m['platform']) . ')</span>'; ?>
                                        </label>
                                        <?php endforeach; ?>
                                    </div>
                                </div>

                            <?php endif; ?>
                            <?php endif; // end Pro guard for postgen ?>
                        </div>

                        <!-- ============ BRAINSTORM / IDEATION ============ -->
                        <div id="tab-brainstorm" class="tab-content">
                            <?php if ($plan === 'free') : ?>
                            <div style="text-align:center; padding:60px 20px;">
                                <div style="width:64px;height:64px;border-radius:50%;background:#eef2ff;display:flex;align-items:center;justify-content:center;margin:0 auto 20px;">
                                    <svg viewBox="0 0 24 24" width="32" height="32" fill="none" stroke="#4f46e5" stroke-width="2"><circle cx="12" cy="12" r="10"/><path d="M9.09 9a3 3 0 0 1 5.83 1c0 2-3 3-3 3"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
                                </div>
                                <h3 style="color:#1e293b; margin:0 0 8px;">قابلیت حرفه‌ای</h3>
                                <p style="color:#64748b; margin:0 0 20px; font-size:0.9rem;">ایده‌یابی ویژگی پلن Pro است. با ارتقا به پلن حرفه‌ای، به این ابزار دسترسی پیدا کنید.</p>
                                <a href="#" onclick="switchTab('subscription', document.querySelector('[data-tab=subscription]')); return false;" style="background:#4f46e5; color:#fff; padding:12px 28px; border-radius:10px; text-decoration:none; font-weight:700; display:inline-block;">ارتقا به Pro</a>
                            </div>
                            <?php else : ?>
                            <h2 class="ssp-tool-title" style="display:flex;align-items:center;gap:10px;margin:0 0 8px;"><svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" style="display:inline-block;vertical-align:middle;flex-shrink:0;"><path d="M9 18h6"/><path d="M10 22h4"/><path d="M15.09 14c.18-.98.65-1.74 1.41-2.5A4.65 4.65 0 0 0 18 8 6 6 0 0 0 6 8c0 1 .23 2.23 1.5 3.5A4.61 4.61 0 0 1 8.91 14"/></svg> <span>ایده‌یابی محتوا</span></h2>
                            <p class="ssp-section-desc">با کمک هوش مصنوعی ایده‌های متنوع برای تولید محتوا پیدا کنید و مستقیماً از هر ایده تولید محتوا را شروع کنید.</p>

                            <?php if (!$ai_configured) : ?>
                            <div class="ssp-empty" style="background:var(--warning-soft); border:1px solid var(--warning); border-radius:12px;">
                                <div class="ssp-empty-icon"><svg viewBox="0 0 24 24" width="48" height="48" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg></div>
                                <h3 style="color:var(--warning);"> هوش مصنوعی تنظیم نشده است</h3>
                                <p>برای استفاده از ایده‌یابی، ابتدا یکی از حالت‌های زیر را فعال کنید:</p>
                                <ul style="color:var(--text-muted); margin:8px 0 16px; padding-right:20px; text-align:right;">
                                    <li><strong>حالت API</strong>: کلید API یکی از ارائه‌دهندگان را وارد کنید</li>
                                    <li><strong>حالت مرورگر</strong>: از طریق مرورگر خود (DeepSeek یا ChatGPT) استفاده کنید</li>
                                </ul>
                                <button type="button" class="ssp-btn-primary" onclick="switchTab('ai', document.querySelector('[data-tab=ai]'))">رفتن به تنظیمات AI</button>
                            </div>
                            <?php else : ?>

                            <!-- Topic Input -->
                            <div class="ssp-card" style="margin-bottom:16px; background: linear-gradient(135deg, var(--accent-soft), transparent); border-color:var(--accent);">
                                <h3 style="margin-bottom:12px;">موضوع مورد نظر</h3>
                                <div class="ssp-form-group">
                                    <input type="text" id="bs_topic" class="ssp-input" placeholder="مثلاً: موتورسواری، تکنولوژی، آشپزی، سلامت، ورزش..." style="font-size:1rem;">
                                </div>
                                <div style="display:flex; gap:6px; flex-wrap:wrap; margin-bottom:12px;">
                                    <span class="ssp-hint">موضوعات پیشنهادی:</span>
                                    <button type="button" class="ssp-btn-secondary" onclick="document.getElementById('bs_topic').value='تکنولوژی و هوش مصنوعی'" style="font-size:0.8rem; padding:3px 10px;">تکنولوژی و AI</button>
                                    <button type="button" class="ssp-btn-secondary" onclick="document.getElementById('bs_topic').value='آموزش رشد فروش و بازاریابی دیجیتال'" style="font-size:0.8rem; padding:3px 10px;">بازاریابی و فروش</button>
                                    <button type="button" class="ssp-btn-secondary" onclick="document.getElementById('bs_topic').value='معرفی محصولات جدید و تخفیف ویژه'" style="font-size:0.8rem; padding:3px 10px;">فروشگاهی و تخفیف</button>
                                    <button type="button" class="ssp-btn-secondary" onclick="document.getElementById('bs_topic').value='ترفندها و راهنمای جامع خرید'" style="font-size:0.8rem; padding:3px 10px;">راهنمای خرید</button>
                                    <button type="button" class="ssp-btn-secondary" onclick="document.getElementById('bs_topic').value='سلامت، سبک زندگی و تغذیه'" style="font-size:0.8rem; padding:3px 10px;">سبک زندگی</button>
                                </div>
                                <div class="ssp-grid-2">
                                    <div class="ssp-form-group">
                                        <label class="ssp-label">تعداد ایده</label>
                                        <select id="bs_count" class="ssp-select">
                                            <option value="5">&#10102; ۵ ایده سریع</option>
                                            <option value="10" selected>&#10103; ۱۰ ایده جامع</option>
                                            <option value="15">&#10104; ۱۵ ایده متنوع</option>
                                        </select>
                                    </div>
                                    <div class="ssp-form-group">
                                        <label class="ssp-label">نوع محتوا و مقصد</label>
                                        <select id="bs_type" class="ssp-select">
                                            <option value="all">همه فرمت‌ها (ترکیبی)</option>
                                            <option value="post">📱 پست شبکه‌های اجتماعی (تلگرام، ایتا، بله)</option>
                                            <option value="article">📝 مقاله وبسایت و وبلاگ (سئو وردپرس)</option>
                                            <option value="product">🛍️ محصول ووکامرس (فروشگاهی / تجاری)</option>
                                            <option value="promo">🎯 کمپین تبلیغاتی و تخفیف مناسبتی</option>
                                        </select>
                                    </div>
                                </div>
                                <div style="display:flex; gap:10px; flex-wrap:wrap;">
                                    <?php if ((get_user_meta($user_id, 'ssp_ai_mode', true) ?: 'api') !== 'browser') : ?>
                                    <button type="button" class="ssp-btn-primary" onclick="bsGenerate()" id="bs_gen_btn">
                                        <span class="ssp-btn-text">تولید ایده با AI</span>
                                        <span class="ssp-btn-spinner"></span>
                                    </button>
                                    <?php endif; ?>
                                    <?php if ((get_user_meta($user_id, 'ssp_ai_mode', true) ?: 'api') === 'browser') : ?>
                                    <button type="button" class="ssp-btn-primary" style="background:#10B981; color:white;" onclick="bsGenerateViaBrowser()" id="bs_browser_btn">
                                        <span class="ssp-btn-text">تولید با چت‌بات رایگان</span>
                                        <span class="ssp-btn-spinner"></span>
                                    </button>
                                    <?php endif; ?>
                                </div>
                                <div id="bs_error" style="color:var(--error); font-size:0.85rem; margin-top:8px; display:none;"></div>
                            </div>

                            <!-- Results -->
                            <div id="bs_results" style="display:none;">
                                <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:12px;">
                                    <h3 style="margin:0;">ایده‌های تولید شده</h3>
                                    <span id="bs_count_badge" class="ssp-badge info"></span>
                                </div>
                                <div id="bs_ideas_grid" style="display:grid; grid-template-columns:repeat(auto-fill, minmax(340px, 1fr)); gap:12px;"></div>
                            </div>
                            <?php endif; ?>
                            <?php endif; // end Pro guard for brainstorm ?>
                        </div>

                        <!-- ============ CONTENT GENERATOR ============ -->
                        <div id="tab-contentgen" class="tab-content">
                            <?php if ($plan === 'free') : ?>
                            <div style="text-align:center; padding:60px 20px;">
                                <div style="width:64px;height:64px;border-radius:50%;background:#eef2ff;display:flex;align-items:center;justify-content:center;margin:0 auto 20px;">
                                    <svg viewBox="0 0 24 24" width="32" height="32" fill="none" stroke="#4f46e5" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/></svg>
                                </div>
                                <h3 style="color:#1e293b; margin:0 0 8px;">قابلیت حرفه‌ای</h3>
                                <p style="color:#64748b; margin:0 0 20px; font-size:0.9rem;">تولید مقاله ویژگی پلن Pro است. با ارتقا به پلن حرفه‌ای، به این ابزار دسترسی پیدا کنید.</p>
                                <a href="#" onclick="switchTab('subscription', document.querySelector('[data-tab=subscription]')); return false;" style="background:#4f46e5; color:#fff; padding:12px 28px; border-radius:10px; text-decoration:none; font-weight:700; display:inline-block;">ارتقا به Pro</a>
                            </div>
                            <?php else : ?>
                            <h2 class="ssp-tool-title" style="display:flex;align-items:center;gap:10px;margin:0 0 8px;"><svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" style="display:inline-block;vertical-align:middle;flex-shrink:0;"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/><polyline points="10 9 9 9 8 9"/></svg> <span>تولید مقاله</span></h2>
                            <p class="ssp-section-desc">نوشته یا برگه وردپرس روی سایت‌های متصل با کمک هوش مصنوعی یا به صورت دستی بسازید.</p>

                            <?php if (empty($wp_sites)) : ?>
                            <div class="ssp-empty">
                                <div class="ssp-empty-icon"><svg viewBox="0 0 24 24" width="48" height="48" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M10 13a5 5 0 0 0 7.54.54l3-3a5 5 0 0 0-7.07-7.07l-1.72 1.71"/><path d="M14 11a5 5 0 0 0-7.54-.54l-3 3a5 5 0 0 0 7.07 7.07l1.71-1.71"/></svg></div>
                                <p>ابتدا یک سایت وردپرسی اضافه کنید.</p>
                                <button type="button" class="ssp-btn-primary" onclick="switchTab('wpsources', document.querySelector('[data-tab=wpsources]'))">رفتن به منابع وردپرس</button>
                            </div>
                            <?php else : ?>

                            <?php if (!$ai_configured) : ?>
                            <div class="ssp-empty" style="background:var(--warning-soft); border:1px solid var(--warning); border-radius:12px;">
                                <div class="ssp-empty-icon"><svg viewBox="0 0 24 24" width="48" height="48" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg></div>
                                <h3 style="color:var(--warning);"> هوش مصنوعی تنظیم نشده است</h3>
                                <p>برای تولید مقاله با AI، ابتدا تنظیمات هوش مصنوعی را تکمیل کنید.</p>
                                <button type="button" class="ssp-btn-primary" onclick="switchTab('ai', document.querySelector('[data-tab=ai]'))">رفتن به تنظیمات AI</button>
                            </div>
                            <?php else : ?>

                            <!-- Load from Draft -->
                            <div class="ssp-card" style="margin-bottom:16px; border-style:dashed;">
                                <div style="display:flex; align-items:center; gap:10px; flex-wrap:wrap;">
                                    <span style="font-size:0.85rem; color:var(--text-muted);">&#128194; بارگذاری از پیش‌نویس:</span>
                                    <select id="cg_load_draft" class="ssp-select" style="max-width:300px;" onchange="cgLoadDraft(this.value)">
                                        <option value="">انتخاب پیش‌نویس...</option>
                                    </select>
                                    <button type="button" class="ssp-btn-secondary" onclick="cgRefreshDrafts()" style="font-size:0.8rem; padding:4px 10px;">بازخوانی</button>
                                </div>
                            </div>

                            <!-- Step 1: Select Target Site -->
                            <div class="ssp-card" style="margin-bottom:16px;">
                                <h3 style="margin-bottom:12px;">انتخاب سایت مقصد</h3>
                                <div class="ssp-grid-2">
                                    <div class="ssp-form-group">
                                        <label class="ssp-label">سایت مقصد</label>
                                        <select id="cg_target_site" class="ssp-select" onchange="cgSiteChanged()">
                                            <?php foreach ($wp_sites as $site) : ?>
                                            <option value="<?php echo (int)$site['id']; ?>" data-name="<?php echo esc_attr($site['site_name']); ?>" data-url="<?php echo esc_attr($site['site_url']); ?>">
                                                <?php echo esc_html($site['site_name']); ?> (<?php echo esc_html($site['site_url']); ?>)
                                            </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div class="ssp-form-group">
                                        <label class="ssp-label">نوع محتوا</label>
                                        <select id="cg_content_type" class="ssp-select">
                                            <option value="post">نوشته (Post)</option>
                                            <option value="page">برگه (Page)</option>
                                        </select>
                                    </div>
                                </div>
                            </div>

                            <!-- Step 2: AI Generation -->
                            <div class="ssp-card" style="margin-bottom:16px;">
                                <h3 style="margin-bottom:12px;">تولید خودکار با هوش مصنوعی</h3>
                                <div class="ssp-grid-2">
                                    <div class="ssp-form-group">
                                        <label class="ssp-label">موضوع / عنوان</label>
                                        <input type="text" id="cg_ai_name" class="ssp-input" placeholder="مثلاً: آموزش سئو در ۱۴۰۳">
                                    </div>
                                    <div class="ssp-form-group">
                                        <label class="ssp-label">قالب پرامپت</label>
                                        <div style="display:flex; gap:8px; align-items:center;">
                                            <select id="cg_prompt_mode" class="ssp-select" onchange="cgPromptModeChanged()" style="flex:1;">
                                                <option value="default_post">پیش‌فرض نوشته</option>
                                                <option value="fashion">پوشاک و مد</option>
                                                <option value="electronics">لوازم الکترونیکی</option>
                                                <option value="food">مواد غذایی</option>
                                                <option value="health_beauty">سلامت و زیبایی</option>
                                                <option value="home_garden">خانه و باغ</option>
                                                <option value="sports">ورزشی</option>
                                                <option value="custom">پرامپت سفارشی</option>
                                            </select>
                                            <button type="button" class="ssp-btn-secondary" onclick="cgResetPromptMode()" title="بازگردانی به پیش‌فرض" style="padding:8px 12px; font-size:0.75rem;">↺</button>
                                        </div>
                                    </div>
                                </div>
                                <div class="ssp-form-group">
                                    <label class="ssp-label">توضیح کوتاه (اختیاری)</label>
                                    <textarea id="cg_ai_brief" class="ssp-textarea" rows="2" placeholder="چند کلمه درباره موضوع بنویسید..."></textarea>
                                </div>
                                <div class="ssp-form-group" id="cg_custom_prompt_wrap" style="display:none;">
                                    <label class="ssp-label">پرامپت سفارشی</label>
                                    <textarea id="cg_custom_prompt" class="ssp-textarea" rows="4" placeholder="پرامپت خود را بنویسید... از متغیرهای {name} و {brief} استفاده کنید."></textarea>
                                </div>
                                <div style="display:flex; gap:10px; align-items:center; flex-wrap:wrap;">
                                    <?php if ((get_user_meta($user_id, 'ssp_ai_mode', true) ?: 'api') !== 'browser') : ?>
                                    <button type="button" class="ssp-btn-primary" onclick="cgGenerateWithAI()" id="cg_ai_btn">
                                        <span class="ssp-btn-text">تولید با API</span>
                                        <span class="ssp-btn-spinner"></span>
                                    </button>
                                    <?php endif; ?>
                                    <?php if ((get_user_meta($user_id, 'ssp_ai_mode', true) ?: 'api') === 'browser') : ?>
                                    <button type="button" class="ssp-btn-primary" style="background:#10B981; color:white;" onclick="cgGenerateViaBrowser()" id="cg_browser_btn">
                                        <span class="ssp-btn-text">تولید با چت‌بات رایگان</span>
                                        <span class="ssp-btn-spinner"></span>
                                    </button>
                                    <?php endif; ?>
                                    <button class="ssp-btn-secondary" onclick="cgResetForm()" type="button">پاک کردن فرم</button>
                                    <span id="cg_ai_tokens" class="ssp-badge" style="display:none;"></span>
                                </div>
                                <p class="ssp-hint" style="margin-top:8px;">با API: سریع‌تر و مستقیم (نیاز به کلید API) | با چت‌بات رایگان: از حساب رایگان DeepSeek/ChatGPT (نیاز به افزونه مرورگر)</p>
                                <div id="cg_ai_error" style="color:var(--error); font-size:0.85rem; margin-top:8px; display:none;"></div>
                            </div>

                            <!-- Step 3: Content Form -->
                            <div class="ssp-card" style="margin-bottom:16px;">
                                <h3 style="margin-bottom:12px;">اطلاعات محتوا</h3>
                                <div class="ssp-form-group">
                                    <label class="ssp-label">عنوان *</label>
                                    <input type="text" id="cg_post_title" class="ssp-input" placeholder="عنوان نوشته">
                                </div>
                                <div class="ssp-grid-2">
                                    <div class="ssp-form-group">
                                        <label class="ssp-label">دسته‌بندی</label>
                                        <select id="cg_wp_categories" class="ssp-select">
                                            <option value="">در حال بارگذاری...</option>
                                        </select>
                                    </div>
                                    <div class="ssp-form-group">
                                        <label class="ssp-label">وضعیت</label>
                                        <select id="cg_post_status" class="ssp-select">
                                            <option value="publish">منتشر شده</option>
                                            <option value="draft">پیش‌نویس</option>
                                            <option value="pending">در انتظار بررسی</option>
                                        </select>
                                    </div>
                                </div>
                                <div class="ssp-form-group">
                                    <label class="ssp-label">برچسب‌ها (جداشده با کاما)</label>
                                    <input type="text" id="cg_post_tags" class="ssp-input" placeholder="برچسب۱, برچسب۲, برچسب۳">
                                </div>
                                <div class="ssp-form-group">
                                    <label class="ssp-label">محتوا (HTML)</label>
                                    <textarea id="cg_post_content" class="ssp-textarea" rows="8" placeholder="محتوای نوشته..."></textarea>
                                </div>
                                <div style="border-top:1px solid var(--border); margin-top:16px; padding-top:16px;">
                                    <h4 style="margin:0 0 12px; color:var(--text); font-size:0.9rem;">تنظیمات SEO</h4>
                                    <div class="ssp-form-group">
                                        <label class="ssp-label">عنوان SEO</label>
                                        <input type="text" id="cg_meta_title" class="ssp-input" placeholder="عنوان SEO" maxlength="60">
                                    </div>
                                    <div class="ssp-form-group">
                                        <label class="ssp-label">توضیحات SEO</label>
                                        <textarea id="cg_meta_description" class="ssp-textarea" rows="2" placeholder="توضیحات SEO" maxlength="155"></textarea>
                                    </div>
                                </div>
                            </div>

                            <!-- Step 4: Image Upload -->
                            <div class="ssp-card" style="margin-bottom:16px;">
                                <h3 style="margin-bottom:12px;">تصویر شاخص</h3>
                                <div class="ssp-grid-2">
                                    <div class="ssp-form-group">
                                        <label class="ssp-label">آپلود فایل</label>
                                        <input type="file" id="cg_thumbnail_file" accept="image/webp,image/jpeg,image/png" class="ssp-input" style="padding:8px;">
                                    </div>
                                    <div class="ssp-form-group">
                                        <label class="ssp-label">یا URL تصویر</label>
                                        <input type="url" id="cg_thumbnail_url" class="ssp-input" dir="ltr" placeholder="https://example.com/image.webp">
                                    </div>
                                </div>
                                <div class="ssp-form-group">
                                    <label class="ssp-label">ابعاد تصویر</label>
                                    <select id="cg_image_resize" class="ssp-select">
                                        <option value="">ابعاد اصلی</option>
                                        <option value="thumbnail">thumbnail (300x300)</option>
                                        <option value="medium">medium (300x300)</option>
                                    </select>
                                </div>
                            </div>

                            <!-- Step 5: Publish -->
                            <div class="ssp-card">
                                <h3 style="margin-bottom:12px;">انتشار</h3>
                                <div style="display:flex; gap:10px; align-items:center; flex-wrap:wrap;">
                                    <button type="button" class="ssp-btn-primary" onclick="cgPublish()" id="cg_publish_btn" style="font-size:1rem; padding:14px 28px;">
                                        <span class="ssp-btn-text">انتشار روی سایت مقصد</span>
                                        <span class="ssp-btn-spinner"></span>
                                    </button>
                                    <button class="ssp-btn-secondary" onclick="cgPreview()" type="button">پیش‌نمایش</button>
                                    <button class="ssp-btn-secondary" onclick="sendCgToSeo()" type="button">ارسال به ابزار SEO</button>
                                    <button class="ssp-btn-secondary" onclick="cgSaveAsDraft()" type="button">&#128190; ذخیره به عنوان پیش‌نویس</button>
                                </div>
                                <div id="cg_result" style="display:none; margin-top:16px; padding:16px; border-radius:12px;"></div>
                            </div>

                            <!-- Batch Content Generation -->
                            <div class="ssp-card" style="margin-top:16px;">
                                <h3 style="margin-bottom:12px;">تولید دسته‌ای محتوا</h3>
                                <p style="color:var(--text-muted); font-size:0.85rem; margin-bottom:12px;">چند محتوا را همزمان تولید کنید و روی سایت مقصد منتشر کنید.</p>
                                <div class="ssp-grid-2">
                                    <div class="ssp-form-group">
                                        <label class="ssp-label">تعداد محتوا (1-10)</label>
                                        <input type="range" id="cg_batch_count" min="1" max="10" value="3" style="width:100%; accent-color:var(--accent);" oninput="document.getElementById('cg_batch_count_val').textContent = this.value">
                                        <div style="text-align:center; font-weight:700; color:var(--accent);" id="cg_batch_count_val">3</div>
                                    </div>
                                    <div class="ssp-form-group">
                                        <label class="ssp-label">سبک محتوا</label>
                                        <select id="cg_batch_style" class="ssp-select">
                                            <option value="general">عمومی</option>
                                            <option value="formal">رسمی</option>
                                            <option value="casual">صمیمی</option>
                                            <option value="promotional">تبلیغاتی</option>
                                            <option value="educational">آموزشی</option>
                                            <option value="news">خبری</option>
                                        </select>
                                    </div>
                                </div>
                                <div class="ssp-form-group">
                                    <label class="ssp-label">موضوع</label>
                                    <input type="text" id="cg_batch_topic" class="ssp-input" placeholder="مثلاً: فناوری، مد، ورزشی">
                                </div>
                                <button type="button" class="ssp-btn-primary" onclick="cgBatchGenerate()" id="cg_batch_gen_btn">
                                    <span class="ssp-btn-text">تولید دسته‌ای</span>
                                    <span class="ssp-btn-spinner"></span>
                                </button>
                                <div id="cg_batch_results" style="margin-top:12px;"></div>
                            </div>

                            <?php endif; ?>
                            <?php endif; ?>
                            <?php endif; // end Pro guard for contentgen ?>
                        </div>

                        <!-- ============ PRODUCT GENERATOR ============ -->
                        <div id="tab-productgen" class="tab-content">
                            <?php if ($plan === 'free') : ?>
                            <div style="text-align:center; padding:60px 20px;">
                                <div style="width:64px;height:64px;border-radius:50%;background:#eef2ff;display:flex;align-items:center;justify-content:center;margin:0 auto 20px;">
                                    <svg viewBox="0 0 24 24" width="32" height="32" fill="none" stroke="#4f46e5" stroke-width="2"><path d="M6 2L3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4z"/><line x1="3" y1="6" x2="21" y2="6"/><path d="M16 10a4 4 0 0 1-8 0"/></svg>
                                </div>
                                <h3 style="color:#1e293b; margin:0 0 8px;">قابلیت حرفه‌ای</h3>
                                <p style="color:#64748b; margin:0 0 20px; font-size:0.9rem;">تولید محصول ویژگی پلن Pro است. با ارتقا به پلن حرفه‌ای، به این ابزار دسترسی پیدا کنید.</p>
                                <a href="#" onclick="switchTab('subscription', document.querySelector('[data-tab=subscription]')); return false;" style="background:#4f46e5; color:#fff; padding:12px 28px; border-radius:10px; text-decoration:none; font-weight:700; display:inline-block;">ارتقا به Pro</a>
                            </div>
                            <?php else : ?>
                            <h2 class="ssp-tool-title" style="display:flex;align-items:center;gap:10px;margin:0 0 8px;"><svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" style="display:inline-block;vertical-align:middle;flex-shrink:0;"><path d="M6 2L3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V8z"/><line x1="3" y1="6" x2="21" y2="6"/><path d="M16 10a4 4 0 0 1-8 0"/></svg> <span>تولید محصول</span></h2>
                            <p class="ssp-section-desc">محصول ووکامرس روی سایت‌های متصل با کمک هوش مصنوعی یا به صورت دستی بسازید.</p>

                            <!-- Quick Actions Bar -->
                            <div style="display:flex; gap:8px; flex-wrap:wrap; margin-bottom:16px;">
                                <button type="button" class="ssp-btn-secondary" onclick="pgShowSection('single')" id="pg_mode_single" style="border-color:var(--accent);">تکی</button>
                                <button type="button" class="ssp-btn-secondary" onclick="pgShowSection('bulk')" id="pg_mode_bulk">انبوه</button>
                                <button type="button" class="ssp-btn-secondary" onclick="pgShowSection('clone')" id="pg_mode_clone">کلون</button>
                                <button type="button" class="ssp-btn-secondary" onclick="pgShowSection('templates')" id="pg_mode_templates">قالب‌ها</button>
                                <button type="button" class="ssp-btn-secondary" onclick="pgShowSection('history')" id="pg_mode_history">تاریخچه</button>
                            </div>

                            <?php if (empty($wp_sites)) : ?>
                            <div class="ssp-empty">
                                <div class="ssp-empty-icon"><svg viewBox="0 0 24 24" width="48" height="48" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M10 13a5 5 0 0 0 7.54.54l3-3a5 5 0 0 0-7.07-7.07l-1.72 1.71"/><path d="M14 11a5 5 0 0 0-7.54-.54l-3 3a5 5 0 0 0 7.07 7.07l1.71-1.71"/></svg></div>
                                <p>ابتدا یک سایت وردپرسی اضافه کنید.</p>
                                <button type="button" class="ssp-btn-primary" onclick="switchTab('wpsources', document.querySelector('[data-tab=wpsources]'))">رفتن به منابع وردپرس</button>
                            </div>
                            <?php else : ?>

                            <?php if (!$ai_configured) : ?>
                            <div class="ssp-empty" style="background:var(--warning-soft); border:1px solid var(--warning); border-radius:12px;">
                                <div class="ssp-empty-icon"><svg viewBox="0 0 24 24" width="48" height="48" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg></div>
                                <h3 style="color:var(--warning);"> هوش مصنوعی تنظیم نشده است</h3>
                                <p>برای تولید محصول با AI، ابتدا تنظیمات هوش مصنوعی را تکمیل کنید.</p>
                                <button type="button" class="ssp-btn-primary" onclick="switchTab('ai', document.querySelector('[data-tab=ai]'))">رفتن به تنظیمات AI</button>
                            </div>
                            <?php else : ?>

                            <!-- ========== SECTION: Single Product ========== -->
                            <div id="pg_section_single">

                            <!-- Step 1: Select Target Site -->
                            <div class="ssp-card" style="margin-bottom:16px;">
                                <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:8px;">
                                    <h3 style="margin:0;">انتخاب سایت مقصد</h3>
                                    <div style="display:flex; gap:6px;">
                                        <button type="button" class="ssp-btn-secondary" onclick="pgCloneProduct()" style="font-size:0.8rem; padding:6px 12px;">کلون از سایت</button>
                                        <button type="button" class="ssp-btn-secondary" onclick="pgSaveAsTemplate()" style="font-size:0.8rem; padding:6px 12px;">ذخیره قالب</button>
                                    </div>
                                </div>
                                <div class="ssp-form-group" style="margin-top:12px;">
                                    <label class="ssp-label">سایت مقصد</label>
                                    <select id="pg_target_site" class="ssp-select" onchange="pgSiteChanged()">
                                        <?php foreach ($wp_sites as $site) : ?>
                                        <option value="<?php echo (int)$site['id']; ?>" data-name="<?php echo esc_attr($site['site_name']); ?>" data-url="<?php echo esc_attr($site['site_url']); ?>" data-user="<?php echo esc_attr($site['username'] ?? ''); ?>" data-pass="<?php echo esc_attr($site['app_password'] ?? ''); ?>">
                                            <?php echo esc_html($site['site_name']); ?> (<?php echo esc_attr($site['site_url']); ?>)
                                        </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div id="pg_site_status" class="ssp-connection-status" style="margin-top:8px;"></div>
                            </div>

                            <!-- Step 2: AI Generation -->
                            <div class="ssp-card" style="margin-bottom:16px;">
                                <h3 style="margin-bottom:12px;">تولید خودکار با هوش مصنوعی</h3>
                                <p style="color:var(--text-muted); font-size:0.85rem; margin-bottom:12px;">نام و توضیح کوتاه محصول را وارد کنید تا AI بقیه اطلاعات را تولید کند.</p>
                                <div class="ssp-grid-2">
                                    <div class="ssp-form-group">
                                        <label class="ssp-label">نام محصول / عنوان</label>
                                        <input type="text" id="pg_ai_name" class="ssp-input" placeholder="مثلاً: گوشی سامسونگ Galaxy S24">
                                    </div>
                                    <div class="ssp-form-group">
                                        <label class="ssp-label">قالب پرامپت</label>
                                        <div style="display:flex; gap:8px; align-items:center;">
                                            <select id="pg_prompt_mode" class="ssp-select" onchange="pgPromptModeChanged()" style="flex:1;">
                                                <option value="default_product">پیش‌فرض محصول</option>
                                                <option value="default_post">پیش‌فرض نوشته</option>
                                                <option value="fashion">پوشاک و مد</option>
                                                <option value="electronics">لوازم الکترونیکی</option>
                                                <option value="food">مواد غذایی</option>
                                                <option value="health_beauty">سلامت و زیبایی</option>
                                                <option value="home_garden">خانه و باغ</option>
                                                <option value="sports">ورزشی</option>
                                                <option value="custom">پرامپت سفارشی</option>
                                                <optgroup label="قالب‌های ذخیره شده" id="pg_saved_templatesoptgroup"></optgroup>
                                            </select>
                                            <button type="button" class="ssp-btn-secondary" onclick="pgResetPromptMode()" title="بازگردانی به پیش‌فرض" style="padding:8px 12px; font-size:0.75rem;">↺</button>
                                        </div>
                                        <button type="button" class="ssp-btn-secondary" style="margin-top:6px; font-size:0.75rem;" onclick="openPromptBuilderModal()">مدیریت قالب‌های پرامپت</button>
                                    </div>
                                </div>
                                <div class="ssp-form-group">
                                    <label class="ssp-label">توضیح کوتاه (اختیاری)</label>
                                    <textarea id="pg_ai_brief" class="ssp-textarea" rows="2" placeholder="چند کلمه درباره محصول بنویسید تا AI بهتر تولید کند..."></textarea>
                                </div>
                                <div class="ssp-form-group" id="pg_custom_prompt_wrap" style="display:none;">
                                    <label class="ssp-label">پرامپت سفارشی</label>
                                    <textarea id="pg_custom_prompt" class="ssp-textarea" rows="4" placeholder="پرامپت خود را بنویسید... از متغیرهای {name} و {brief} استفاده کنید."></textarea>
                                </div>
                                <div style="display:flex; gap:10px; align-items:center; flex-wrap:wrap;">
                                    <?php if ((get_user_meta($user_id, 'ssp_ai_mode', true) ?: 'api') !== 'browser') : ?>
                                    <button type="button" class="ssp-btn-primary" onclick="pgGenerateWithAI()" id="pg_ai_btn">
                                        <span class="ssp-btn-text">تولید با API</span>
                                        <span class="ssp-btn-spinner"></span>
                                    </button>
                                    <?php endif; ?>
                                    <?php if ((get_user_meta($user_id, 'ssp_ai_mode', true) ?: 'api') === 'browser') : ?>
                                    <button type="button" class="ssp-btn-primary" style="background:#10B981; color:white;" onclick="pgGenerateViaBrowser()" id="pg_browser_btn">
                                        <span class="ssp-btn-text">تولید با چت‌بات رایگان</span>
                                        <span class="ssp-btn-spinner"></span>
                                    </button>
                                    <?php endif; ?>
                                    <button class="ssp-btn-secondary" onclick="pgResetForm()" type="button">پاک کردن فرم</button>
                                    <span id="pg_ai_tokens" class="ssp-badge" style="display:none;"></span>
                                </div>
                                <p class="ssp-hint" style="margin-top:8px;">با API: سریع‌تر و مستقیم (نیاز به کلید API) | با چت‌بات رایگان: از حساب رایگان DeepSeek/ChatGPT (نیاز به افزونه مرورگر)</p>
                                <div id="pg_ai_error" style="color:var(--error); font-size:0.85rem; margin-top:8px; display:none;"></div>
                            </div>

                            <!-- Step 3: Product Form -->
                            <div class="ssp-card" style="margin-bottom:16px;">
                                <h3 style="margin-bottom:12px;" id="pg_form_title">اطلاعات محصول</h3>

                                <!-- Product Fields -->
                                <div id="pg_product_fields">
                                    <div class="ssp-grid-2">
                                        <div class="ssp-form-group">
                                            <label class="ssp-label">نام محصول *</label>
                                            <input type="text" id="pg_product_name" class="ssp-input" placeholder="نام محصول">
                                        </div>
                                        <div class="ssp-form-group">
                                            <label class="ssp-label">SKU</label>
                                            <input type="text" id="pg_sku" class="ssp-input" dir="ltr" placeholder="BRAND-001">
                                        </div>
                                    </div>
                                    <div class="ssp-grid-2">
                                        <div class="ssp-form-group">
                                            <label class="ssp-label">دسته‌بندی</label>
                                            <select id="pg_woo_categories" class="ssp-select">
                                                <option value="">در حال بارگذاری...</option>
                                            </select>
                                        </div>
                                        <div class="ssp-form-group">
                                            <label class="ssp-label">وضعیت</label>
                                            <select id="pg_product_status" class="ssp-select">
                                                <option value="draft">پیش‌نویس</option>
                                                <option value="publish">منتشر شده</option>
                                                <option value="pending">در انتظار بررسی</option>
                                            </select>
                                        </div>
                                    </div>
                                    <div class="ssp-grid-2">
                                        <div class="ssp-form-group">
                                            <label class="ssp-label">قیمت عادی (تومان)</label>
                                            <input type="number" id="pg_regular_price" class="ssp-input" placeholder="مثلاً: 15000000">
                                        </div>
                                        <div class="ssp-form-group">
                                            <label class="ssp-label">قیمت ویژه (تومان) - اختیاری</label>
                                            <input type="number" id="pg_sale_price" class="ssp-input" placeholder="مثلاً: 12000000">
                                        </div>
                                    </div>
                                    <div class="ssp-grid-2">
                                        <div class="ssp-form-group">
                                            <label class="ssp-label">تاریخ شروع تخفیف</label>
                                            <input type="datetime-local" id="pg_date_on_sale_from" class="ssp-input">
                                        </div>
                                        <div class="ssp-form-group">
                                            <label class="ssp-label">تاریخ پایان تخفیف</label>
                                            <input type="datetime-local" id="pg_date_on_sale_to" class="ssp-input">
                                        </div>
                                    </div>
                                    <div class="ssp-grid-2">
                                        <div class="ssp-form-group">
                                            <label class="ssp-label">برند</label>
                                            <select id="pg_brand" class="ssp-select">
                                                <option value="">بدون برند</option>
                                            </select>
                                        </div>
                                        <div class="ssp-form-group">
                                            <label class="ssp-label">کلاس ارسال</label>
                                            <select id="pg_shipping_class" class="ssp-select">
                                                <option value="">پیش‌فرض</option>
                                            </select>
                                        </div>
                                    </div>
                                    <div class="ssp-form-group">
                                        <label class="ssp-label">توضیحات کوتاه</label>
                                        <textarea id="pg_short_desc" class="ssp-textarea" rows="3" placeholder="توضیحات کوتاه محصول (نمایش در لیست محصولات)"></textarea>
                                    </div>
                                    <div class="ssp-form-group">
                                        <label class="ssp-label">توضیحات بلند (HTML پشتیبانی می‌شود)</label>
                                        <textarea id="pg_description" class="ssp-textarea" rows="6" placeholder="توضیحات کامل محصول... می‌توانید از HTML استفاده کنید."></textarea>
                                    </div>

                                    <!-- Attributes -->
                                    <div class="ssp-form-group">
                                        <label class="ssp-label">ویژگی‌ها</label>
                                        <div id="pg_attributes_list"></div>
                                        <button class="ssp-btn-secondary" onclick="pgAddAttribute()" type="button" style="margin-top:8px;">+ افزودن ویژگی</button>
                                    </div>

                                    <!-- Weight & Dimensions -->
                                    <div class="ssp-grid-4">
                                        <div class="ssp-form-group">
                                            <label class="ssp-label">وزن (kg)</label>
                                            <input type="number" step="0.01" id="pg_weight" class="ssp-input" placeholder="0.5">
                                        </div>
                                        <div class="ssp-form-group">
                                            <label class="ssp-label">طول (cm)</label>
                                            <input type="number" step="0.01" id="pg_length" class="ssp-input">
                                        </div>
                                        <div class="ssp-form-group">
                                            <label class="ssp-label">عرض (cm)</label>
                                            <input type="number" step="0.01" id="pg_width" class="ssp-input">
                                        </div>
                                        <div class="ssp-form-group">
                                            <label class="ssp-label">ارتفاع (cm)</label>
                                            <input type="number" step="0.01" id="pg_height" class="ssp-input">
                                        </div>
                                    </div>

                                    <!-- Stock -->
                                    <div class="ssp-grid-2">
                                        <div class="ssp-toggle" style="margin-top:0;">
                                            <label>
                                                <input type="checkbox" id="pg_manage_stock">
                                                <span class="ssp-toggle-slider"></span>
                                                <span>مدیریت انبار</span>
                                            </label>
                                        </div>
                                        <div class="ssp-form-group" id="pg_stock_quantity_group" style="display:none;">
                                            <label class="ssp-label">مقدار موجودی</label>
                                            <input type="number" id="pg_stock_quantity" class="ssp-form-control" value="10" min="0">
                                        </div>
                                    </div>

                                    <!-- Purchase Limit -->
                                    <div class="ssp-grid-2">
                                        <div class="ssp-form-group">
                                            <label class="ssp-label">محدودیت خرید (اختیاری)</label>
                                            <input type="number" id="pg_purchase_limit" class="ssp-input" placeholder="0 = بدون محدودیت">
                                        </div>
                                        <div class="ssp-form-group">
                                            <label class="ssp-label">ترتیب منو</label>
                                            <input type="number" id="pg_menu_order" class="ssp-input" placeholder="0">
                                        </div>
                                    </div>

                                    <!-- Virtual & Downloadable -->
                                    <div class="ssp-grid-2">
                                        <div class="ssp-toggle" style="margin-top:0;">
                                            <label>
                                                <input type="checkbox" id="pg_virtual">
                                                <span class="ssp-toggle-slider"></span>
                                                <span>محصول مجازی</span>
                                            </label>
                                        </div>
                                        <div class="ssp-toggle" style="margin-top:0;">
                                            <label>
                                                <input type="checkbox" id="pg_downloadable">
                                                <span class="ssp-toggle-slider"></span>
                                                <span>محصول دانلودی</span>
                                            </label>
                                        </div>
                                    </div>

                                    <!-- SEO Fields -->
                                    <div style="border-top:1px solid var(--border); margin-top:16px; padding-top:16px;">
                                        <h4 style="margin:0 0 12px; color:var(--text); font-size:0.9rem;">تنظیمات SEO</h4>
                                        <div class="ssp-form-group">
                                            <label class="ssp-label">عنوان SEO (Yoast/RankMath)</label>
                                            <input type="text" id="pg_meta_title" class="ssp-input" placeholder="عنوان بهینه برای موتورهای جستجو" maxlength="60">
                                            <span style="font-size:0.75rem; color:var(--text-subtle);"><span id="pg_meta_title_count">0</span>/60</span>
                                        </div>
                                        <div class="ssp-form-group">
                                            <label class="ssp-label">توضیحات SEO</label>
                                            <textarea id="pg_meta_description" class="ssp-textarea" rows="2" placeholder="توضیحات بهینه برای موتورهای جستجو" maxlength="155"></textarea>
                                            <span style="font-size:0.75rem; color:var(--text-subtle);"><span id="pg_meta_desc_count">0</span>/155</span>
                                        </div>
                                    </div>

                                    <!-- Cross-sell & Up-sell -->
                                    <div style="border-top:1px solid var(--border); margin-top:16px; padding-top:16px;">
                                        <h4 style="margin:0 0 12px; color:var(--text); font-size:0.9rem;">محصولات مرتبط</h4>
                                        <div class="ssp-grid-2">
                                            <div class="ssp-form-group">
                                                <label class="ssp-label">محصولات Cross-sell (IDs با کاما)</label>
                                                <input type="text" id="pg_cross_sell_ids" class="ssp-input" dir="ltr" placeholder="1,2,3">
                                            </div>
                                            <div class="ssp-form-group">
                                                <label class="ssp-label">محصولات Up-sell (IDs با کاما)</label>
                                                <input type="text" id="pg_upsell_ids" class="ssp-input" dir="ltr" placeholder="1,2,3">
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Custom Meta Fields -->
                                    <div style="border-top:1px solid var(--border); margin-top:16px; padding-top:16px;">
                                        <h4 style="margin:0 0 12px; color:var(--text); font-size:0.9rem;">فیلدهای سفارشی (Meta)</h4>
                                        <div id="pg_custom_meta_list"></div>
                                        <button class="ssp-btn-secondary" onclick="pgAddCustomMeta()" type="button" style="margin-top:8px;">+ افزودن فیلد سفارشی</button>
                                    </div>
                                </div>

                            </div>

                            <!-- Step 4: Image Upload -->
                            <div class="ssp-card" style="margin-bottom:16px;">
                                <h3 style="margin-bottom:12px;">تصویر محصول</h3>
                                <div class="ssp-grid-2">
                                    <div class="ssp-form-group">
                                        <label class="ssp-label">تصویر شاخص</label>
                                        <input type="file" id="pg_thumbnail_file" accept="image/webp,image/jpeg,image/png" class="ssp-input" style="padding:8px;">
                                        <p style="color:var(--text-subtle); font-size:0.75rem; margin-top:4px;">فرمت پیشنهادی: WebP | حداکثر: 5MB</p>
                                    </div>
                                    <div class="ssp-form-group">
                                        <label class="ssp-label">یا URL تصویر</label>
                                        <input type="url" id="pg_thumbnail_url" class="ssp-input" dir="ltr" placeholder="https://example.com/image.webp">
                                    </div>
                                </div>
                                <div class="ssp-grid-2">
                                    <div class="ssp-form-group">
                                        <label class="ssp-label">ابعاد تصویر</label>
                                        <select id="pg_image_resize" class="ssp-select">
                                            <option value="">ابعاد اصلی (بدون تغییر)</option>
                                            <option value="woocommerce_thumbnail">woocommerce_thumbnail (600x600)</option>
                                            <option value="woocommerce_single">woocommerce_single (600x600)</option>
                                            <option value="woocommerce_gallery_thumbnail">woocommerce_gallery_thumbnail (100x100)</option>
                                            <option value="thumbnail">thumbnail (300x300)</option>
                                            <option value="medium">medium (300x300)</option>
                                        </select>
                                    </div>
                                    <div class="ssp-form-group">
                                        <label class="ssp-label">گالری تصاویر (اختیاری)</label>
                                        <input type="file" id="pg_gallery_files" accept="image/webp,image/jpeg,image/png" class="ssp-input" multiple style="padding:8px;">
                                    </div>
                                </div>
                                <div class="ssp-form-group">
                                    <label class="ssp-label">URL های گالری (هر خط یک URL)</label>
                                    <textarea id="pg_gallery_urls" class="ssp-textarea" rows="2" placeholder="https://example.com/img1.webp&#10;https://example.com/img2.webp"></textarea>
                                </div>
                                <div id="pg_image_preview" style="display:none; margin-top:12px;">
                                    <label class="ssp-label">پیش‌نمایش تصویر شاخص:</label>
                                    <img id="pg_image_preview_img" src="" style="max-width:200px; max-height:200px; border-radius:8px; border:1px solid var(--border);">
                                </div>
                            </div>

                            <!-- Step 5: Publish -->
                            <div class="ssp-card">
                                <h3 style="margin-bottom:12px;">انتشار</h3>
                                <div style="display:flex; gap:10px; align-items:center; flex-wrap:wrap;">
                                    <button type="button" class="ssp-btn-primary" onclick="pgPublish()" id="pg_publish_btn" style="font-size:1rem; padding:14px 28px;">
                                        <span class="ssp-btn-text">انتشار روی سایت مقصد</span>
                                        <span class="ssp-btn-spinner"></span>
                                    </button>
                                    <button class="ssp-btn-secondary" onclick="pgPreview()" type="button">پیش‌نمایش</button>
                                    <button class="ssp-btn-secondary" onclick="sendPgToSeo()" type="button">ارسال به ابزار SEO</button>
                                </div>
                                <div id="pg_result" style="display:none; margin-top:16px; padding:16px; border-radius:12px;"></div>
                            </div>

                            </div><!-- /pg_section_single -->

                            <!-- ========== SECTION: Bulk Generation ========== -->
                            <div id="pg_section_bulk" style="display:none;">
                                <div class="ssp-card">
                                    <h3 style="margin-bottom:12px;">تولید انبوه محصول</h3>
                                    <p style="color:var(--text-muted); font-size:0.85rem; margin-bottom:16px;">چند محصول را همزمان روی سایت مقصد منتشر کنید. هر ردیف یک محصول است.</p>
                                    <div class="ssp-grid-2" style="margin-bottom:12px;">
                                        <div class="ssp-form-group">
                                            <label class="ssp-label">سایت مقصد</label>
                                            <select id="pg_bulk_site" class="ssp-select">
                                                <?php foreach ($wp_sites as $site) : ?>
                                                <option value="<?php echo (int)$site['id']; ?>"><?php echo esc_html($site['site_name']); ?></option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                        <div class="ssp-form-group">
                                            <label class="ssp-label">نوع محتوا</label>
                                            <select id="pg_bulk_content_type" class="ssp-select">
                                                <option value="product">محصول ووکامرس</option>
                                                <option value="post">نوشته</option>
                                            </select>
                                        </div>
                                    </div>
                                    <div id="pg_bulk_items"></div>
                                    <div style="display:flex; gap:10px; margin-top:12px;">
                                        <button type="button" class="ssp-btn-secondary" onclick="pgBulkAddItem()">+ افزودن ردیف</button>
                                        <button type="button" class="ssp-btn-secondary" onclick="pgBulkGenerateFromAI()">تولید همه با AI</button>
                                    </div>
                                    <div style="margin-top:16px; display:flex; gap:10px;">
                                        <button type="button" class="ssp-btn-primary" onclick="pgBulkPublish()" id="pg_bulk_publish_btn">
                                            <span class="ssp-btn-text">انتشار همه</span>
                                            <span class="ssp-btn-spinner"></span>
                                        </button>
                                    </div>
                                    <div id="pg_bulk_result" style="display:none; margin-top:16px;"></div>
                                </div>
                            </div>

                            <!-- ========== SECTION: Clone Product ========== -->
                            <div id="pg_section_clone" style="display:none;">
                                <div class="ssp-card">
                                    <h3 style="margin-bottom:12px;">کلون محصول از سایت مقصد</h3>
                                    <p style="color:var(--text-muted); font-size:0.85rem; margin-bottom:16px;">آدرس محصول را از سایت مقصد وارد کنید تا اطلاعات آن دریافت شود.</p>
                                    <div class="ssp-grid-2">
                                        <div class="ssp-form-group">
                                            <label class="ssp-label">سایت مقصد</label>
                                            <select id="pg_clone_site" class="ssp-select">
                                                <?php foreach ($wp_sites as $site) : ?>
                                                <option value="<?php echo (int)$site['id']; ?>"><?php echo esc_html($site['site_name']); ?></option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                        <div class="ssp-form-group">
                                            <label class="ssp-label">آدرس محصول</label>
                                            <input type="url" id="pg_clone_url" class="ssp-input" dir="ltr" placeholder="https://example.com/product/sample-product/">
                                        </div>
                                    </div>
                                    <button type="button" class="ssp-btn-primary" onclick="pgCloneFetch()" id="pg_clone_btn">
                                        <span class="ssp-btn-text">دریافت اطلاعات</span>
                                        <span class="ssp-btn-spinner"></span>
                                    </button>
                                    <div id="pg_clone_result" style="display:none; margin-top:16px;"></div>
                                </div>
                            </div>

                            <!-- ========== SECTION: Templates ========== -->
                            <div id="pg_section_templates" style="display:none;">
                                <div class="ssp-card">
                                    <h3 style="margin-bottom:12px;">قالب‌های ذخیره شده</h3>
                                    <p style="color:var(--text-muted); font-size:0.85rem; margin-bottom:16px;">قالب‌های ذخیره شده برای استفاده سریع.</p>
                                    <div id="pg_templates_list">
                                        <div class="ssp-empty">
                                            <p>هنوز قالبی ذخیره نشده است.</p>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- ========== SECTION: History ========== -->
                            <div id="pg_section_history" style="display:none;">
                                <div class="ssp-card">
                                    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:12px;">
                                        <h3 style="margin:0;">تاریخچه انتشار</h3>
                                        <button type="button" class="ssp-btn-secondary" onclick="pgClearHistory()" style="font-size:0.8rem;">پاک کردن</button>
                                    </div>
                                    <div id="pg_history_list">
                                        <div class="ssp-empty">
                                            <p>هنوز محصولی منتشر نشده است.</p>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <?php endif; ?>
                            <?php endif; ?>
                            <?php endif; // end Pro guard for productgen ?>
                        </div>

                        <!-- ============ PROMPT BUILDER ============ -->
                        <div id="tab-promptbuilder" class="tab-content">
                            <?php if ($plan === 'free') : ?>
                            <div style="text-align:center; padding:60px 20px;">
                                <div style="width:64px;height:64px;border-radius:50%;background:#eef2ff;display:flex;align-items:center;justify-content:center;margin:0 auto 20px;">
                                    <svg viewBox="0 0 24 24" width="32" height="32" fill="none" stroke="#4f46e5" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/></svg>
                                </div>
                                <h3 style="color:#1e293b; margin:0 0 8px;">قابلیت حرفه‌ای</h3>
                                <p style="color:#64748b; margin:0 0 20px; font-size:0.9rem;">قالب پرامپت ویژگی پلن Pro است. با ارتقا به پلن حرفه‌ای، به این ابزار دسترسی پیدا کنید.</p>
                                <a href="#" onclick="switchTab('subscription', document.querySelector('[data-tab=subscription]')); return false;" style="background:#4f46e5; color:#fff; padding:12px 28px; border-radius:10px; text-decoration:none; font-weight:700; display:inline-block;">ارتقا به Pro</a>
                            </div>
                            <?php else : ?>
                            <h2 class="ssp-tool-title" style="display:flex;align-items:center;gap:10px;margin:0 0 8px;"><svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" style="display:inline-block;vertical-align:middle;flex-shrink:0;"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/></svg> <span>قالب پرامپت</span></h2>
                            <p class="ssp-section-desc">قالب پرامپت سفارشی بسازید. با پر کردن فیلدهای زیر، پرامپت به صورت خودکار ساخته شده و خروجی JSON استاندارد برمی‌گرداند.</p>

                            <input type="hidden" id="pb_tab_edit_id" value="">
                            <input type="hidden" id="pb_tab_original_builtin_id" value="">
                            <div class="ssp-grid-2">
                                <div class="ssp-form-group">
                                    <label class="ssp-label">نام قالب *</label>
                                    <input type="text" id="pb_tab_name" class="ssp-input" placeholder="مثال: قطعات موتورسیکلت">
                                </div>
                                <div class="ssp-form-group">
                                    <label class="ssp-label">نوع قالب</label>
                                    <select id="pb_tab_type" class="ssp-select">
                                        <option value="product">محصول (WooCommerce)</option>
                                        <option value="article">مقاله (نوشته/برگه)</option>
                                    </select>
                                </div>
                            </div>
                            <div class="ssp-grid-2">
                                <div class="ssp-form-group">
                                    <label class="ssp-label">حوزه / صنعت</label>
                                    <input type="text" id="pb_tab_industry" class="ssp-input" placeholder="مثال: لوازم یدکی موتورسیکلت">
                                </div>
                                <div class="ssp-form-group">
                                    <label class="ssp-label">لحن محتوا</label>
                                    <input type="text" id="pb_tab_tone" class="ssp-input" placeholder="مثال: حرفه‌ای و تخصصی">
                                </div>
                            </div>
                            <div class="ssp-grid-2">
                                <div class="ssp-form-group">
                                    <label class="ssp-label">تمرکز توضیحات</label>
                                    <input type="text" id="pb_tab_focus" class="ssp-input" placeholder="مزایا، دوام، ارزش خرید">
                                </div>
                            </div>
                            <div class="ssp-form-group">
                                <label class="ssp-label">قوانین کلی</label>
                                <textarea id="pb_tab_general_rules" class="ssp-textarea" rows="3" placeholder="مثال: فقط اطلاعات واقعی از عنوان • بدون اختراع عدد"></textarea>
                            </div>
                            <div class="ssp-form-group">
                                <label class="ssp-label">قوانین توضیحات کوتاه (short_description)</label>
                                <textarea id="pb_tab_short_rules" class="ssp-textarea" rows="3" placeholder="مثال: ۳-۴ جمله، کاربرد و مزیت کلیدی. لیست حداقل ۴ ردیف"></textarea>
                            </div>
                            <div class="ssp-form-group">
                                <label class="ssp-label">قوانین توضیحات بلند (description)</label>
                                <textarea id="pb_tab_long_rules" class="ssp-textarea" rows="3" placeholder="مثال: حداقل ۶۰۰ کلمه، حداقل ۳ h3، تکرار کلمه کلیدی ۵-۱۰ بار"></textarea>
                            </div>
                            <div class="ssp-form-group">
                                <label class="ssp-label">قوانین سئو (meta)</label>
                                <textarea id="pb_tab_seo_rules" class="ssp-textarea" rows="2" placeholder="مثال: تیتر ≤۶۰ کاراکتر، متا ≤۱۷۰ کاراکتر، شامل برند و مزیت"></textarea>
                            </div>
                            <div class="ssp-grid-2">
                                <div class="ssp-form-group">
                                    <label class="ssp-label">ممنوعیات</label>
                                    <input type="text" id="pb_tab_forbidden" class="ssp-input" placeholder="بدون احوالپرسی، بدون آموزش نصب">
                                </div>
                                <div class="ssp-form-group">
                                    <label class="ssp-label">توضیحات اضافه / زمینه</label>
                                    <textarea id="pb_tab_extra" class="ssp-textarea" rows="2" placeholder="زمینه و اطلاعات پس‌زمینه حوزه کاری"></textarea>
                                </div>
                            </div>
                            <div class="ssp-toggle" style="margin-bottom:16px;">
                                <label>
                                    <input type="checkbox" id="pb_tab_is_default">
                                    <span class="ssp-toggle-slider"></span>
                                    <span>قالب پیش‌فرض</span>
                                </label>
                            </div>
                            <div style="display:flex; gap:10px; flex-wrap:wrap; margin-bottom:20px;">
                                <button type="button" class="ssp-btn-primary" onclick="pbTabSave()">ذخیره قالب</button>
                                <button type="button" class="ssp-btn-secondary" onclick="pbTabPreview()">پیش‌نمایش پرامپت</button>
                                <button type="button" class="ssp-btn-secondary" onclick="pbTabReset()">پاک کردن فرم</button>
                                <button type="button" class="ssp-btn-secondary" onclick="pbResetToDefault()" title="بازگردانی به پیش‌فرض" style="padding:8px 12px; font-size:0.75rem;">↺ پیش‌فرض سیستم</button>
                                <button type="button" id="pb_tab_reset_builtin_btn" class="ssp-btn-warning" onclick="pbTabResetBuiltin()" title="بازگردانی قالب آماده به حالت اولیه" style="padding:8px 12px; font-size:0.75rem; display:none; background:#f59e0b; color:#fff; border:1px solid #d97706;">↺ بازگردانی قالب آماده</button>
                            </div>
                            <div id="pb_tab_preview" style="display:none; margin-bottom:20px; padding:12px; background:var(--bg-alt); border:1px solid var(--border); border-radius:8px; white-space:pre-wrap; font-size:0.8rem; max-height:300px; overflow-y:auto;"></div>
                            <div style="border-top:1px solid var(--border); padding-top:16px;">
                                <h4 style="font-size:0.9rem; margin-bottom:10px;">قالب‌های ذخیره شده</h4>
                                <div id="pb_tab_templates_list"></div>
                            </div>
                            <?php endif; // end Pro guard for promptbuilder ?>
                        </div>

                        <!-- ============ SOURCES ============ -->
                        <div id="tab-sources" class="tab-content">
                            <h2>RSS Feeds</h2>
                            <p class="ssp-section-desc">محتوای سایت‌های دیگر را از طریق RSS دریافت و بازنشر کنید.</p>

                            <div class="ssp-guide">
                                <h3>RSS Feed چیست؟</h3>
                                <ol class="ssp-guide-steps">
                                    <li><strong>RSS Feed</strong> فید خبری سایت‌هاست که محتوای جدید را به صورت خودکار نمایش می‌دهد</li>
                                    <li>با اضافه کردن فید، محتوای جدید سایت به صورت خودکار دریافت می‌شود</li>
                                    <li>می‌توانید محتوا را در پیام‌رسان‌ها بازنشر کنید</li>
                                </ol>
                            </div>

                            <div id="rss_feeds_list">
                                <?php if (empty($rss_feeds)) : ?>
                                <div class="ssp-empty">
                                    <div class="ssp-empty-icon"><svg viewBox="0 0 24 24" width="48" height="48" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M5 12.55a11 11 0 0 1 14.08 0"/><path d="M1.42 9a16 16 0 0 1 21.16 0"/><path d="M8.53 16.11a6 6 0 0 1 6.95 0"/><line x1="12" y1="20" x2="12.01" y2="20"/></svg></div>
                                    <p>هنوز RSS Feed اضافه نکرده‌اید.</p>
                                </div>
                                <?php else : ?>
                                    <?php foreach ($rss_feeds as $feed) : ?>
                                    <div class="ssp-item-card" data-id="<?php echo (int)$feed['id']; ?>">
                                        <div class="ssp-item-card-head">
                                            <div>
                                                <div class="ssp-item-card-title">
                                                    ◉ <?php echo esc_html($feed['feed_name']); ?>
                                                    <span class="ssp-badge <?php echo $feed['is_active'] ? 'active' : 'inactive'; ?>">
                                                        <?php echo $feed['is_active'] ? 'فعال' : 'غیرفعال'; ?>
                                                    </span>
                                                    <?php if ($feed['auto_fetch']) : ?>
                                                    <span class="ssp-badge info">دریافت خودکار</span>
                                                    <?php endif; ?>
                                                </div>
                                                <div class="ssp-item-card-meta"><?php echo esc_html($feed['feed_url']); ?></div>
                                                <?php if (!empty($feed['last_fetched'])) : ?>
                                                <div class="ssp-item-card-meta" style="margin-top:4px;">
                                                    <span style="color:var(--success);"> آخرین دریافت: <?php
                                                        $feed_ts = ($feed['last_fetched'] ?? '') ? (new \DateTime($feed['last_fetched'], wp_timezone()))->getTimestamp() : current_time('timestamp');
                                                        echo human_time_diff($feed_ts) . ' پیش';
                                                    ?></span>
                                                    <?php if (!empty($feed['fetched_count'])) : ?>
                                                    &bull; <span><?php echo (int)$feed['fetched_count']; ?> آیتم دریافت شده</span>
                                                    <?php endif; ?>
                                                </div>
                                                <?php endif; ?>
                                            </div>
                                            <div style="display:flex; gap:6px; flex-wrap:wrap;">
                                                <button class="ssp-btn-test btn-fetch-rss" data-id="<?php echo (int)$feed['id']; ?>">دریافت فوری</button>
                                                <button class="ssp-btn-primary btn-fetch-extract" data-id="<?php echo (int)$feed['id']; ?>" style="font-size:0.75rem; padding:4px 10px;" title="دریافت و استخراج محتوای کامل">استخراج کامل</button>
                                                <button class="ssp-btn-secondary btn-edit-rss" data-id="<?php echo (int)$feed['id']; ?>">ويرايش</button>
                                                <button class="ssp-btn-danger btn-delete-rss" data-id="<?php echo (int)$feed['id']; ?>">حذف</button>
                                            </div>
                                        </div>
                                    </div>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </div>

                            <?php if (count($rss_feeds) < 10) : ?>
                            <div class="ssp-add-form">
                                <h3>افزودن RSS Feed جدید</h3>
                                <div class="ssp-form-group">
                                    <label class="ssp-label">نام دلخواه فید</label>
                                    <input type="text" id="new_feed_name" class="ssp-input" placeholder="مثلاً: فید زومیت">
                                </div>
                                <div class="ssp-form-group">
                                    <label class="ssp-label">آدرس RSS Feed</label>
                                    <input type="url" id="new_feed_url" class="ssp-input" dir="ltr" placeholder="https://example.com/feed">
                                </div>
                                <div class="ssp-grid-2">
                                    <div class="ssp-toggle" style="margin-top:0;">
                                        <label>
                                            <input type="checkbox" id="new_feed_active" checked>
                                            <span class="ssp-toggle-slider"></span>
                                            <span>فعال</span>
                                        </label>
                                    </div>
                                    <div class="ssp-toggle" style="margin-top:0;">
                                        <label>
                                            <input type="checkbox" id="new_feed_auto" checked>
                                            <span class="ssp-toggle-slider"></span>
                                            <span>دریافت خودکار</span>
                                        </label>
                                    </div>
                                </div>

                                <!-- Content Cleaning & Extraction Options -->
                                <div style="margin-top:16px; padding:12px; background:var(--bg-alt); border-radius:8px;">
                                    <h4 style="margin:0 0 8px; font-size:0.9rem;">پاکسازی و استخراج محتوا</h4>
                                    <div class="ssp-grid-2">
                                        <div class="ssp-toggle" style="margin-top:0;">
                                            <label>
                                                <input type="checkbox" id="new_feed_clean_ads" checked>
                                                <span class="ssp-toggle-slider"></span>
                                                <span>حذف تبلیغات و متن‌های اضافی</span>
                                            </label>
                                        </div>
                                        <div class="ssp-toggle" style="margin-top:0;">
                                            <label>
                                                <input type="checkbox" id="new_feed_clean_urls" checked>
                                                <span class="ssp-toggle-slider"></span>
                                                <span>حذف لینک‌ها از متن</span>
                                            </label>
                                        </div>
                                    </div>
                                    <div class="ssp-toggle" style="margin-top:8px;">
                                        <label>
                                            <input type="checkbox" id="new_feed_extract">
                                            <span class="ssp-toggle-slider"></span>
                                            <span>استخراج محتوای کامل از لینک مقالات</span>
                                        </label>
                                        <p class="ssp-hint" style="margin:4px 0 0 28px; font-size:0.75rem;">با فعال‌سازی، محتوای واقعی هر مقاله از سایت مبدأ دریافت می‌شود (متن کامل، نه فقط خلاصه RSS)</p>
                                    </div>
                                    <div class="ssp-grid-2" style="margin-top:8px;">
                                        <div class="ssp-form-group" style="margin:0;">
                                            <label class="ssp-label">حداکثر طول متن</label>
                                            <select id="new_feed_max_length" class="ssp-select">
                                                <option value="200">۲۰۰ کاراکتر</option>
                                                <option value="500" selected>۵۰۰ کاراکتر</option>
                                                <option value="1000">۱۰۰۰ کاراکتر</option>
                                                <option value="2000">۲۰۰۰ کاراکتر</option>
                                            </select>
                                        </div>
                                        <div class="ssp-form-group" style="margin:0;">
                                            <label class="ssp-label">حالت محتوا (پیش‌فرض)</label>
                                            <select id="new_feed_content_mode" class="ssp-select">
                                                <option value="summary" selected>خلاصه (عنوان + متن + لینک)</option>
                                                <option value="title_only">فقط عنوان</option>
                                                <option value="title_link">عنوان + لینک</option>
                                                <option value="full">متن کامل</option>
                                            </select>
                                        </div>
                                    </div>
                                </div>

                                <!-- Destination Options -->
                                <div style="margin-top:12px; padding:12px; background:var(--bg-alt); border-radius:8px;">
                                    <h4 style="margin:0 0 8px; font-size:0.9rem;">مقصد انتشار</h4>
                                    <div class="ssp-form-group" style="margin:0;">
                                        <label class="ssp-label">محتوا کجا منتشر شود؟</label>
                                        <select id="new_feed_target_mode" class="ssp-select" onchange="toggleRssTargetMode()">
                                            <option value="all" selected>همه پیام‌رسان‌های فعال</option>
                                            <option value="messengers">پیام‌رسان‌های انتخابی</option>
                                        </select>
                                    </div>
                                    <div id="new_feed_messenger_list" style="display:none; margin-top:8px;">
                                        <p style="font-size:0.8rem; color:var(--text-muted); margin:0 0 6px;">پیام‌رسان‌های مقصد را انتخاب کنید:</p>
                                        <div style="display:flex; gap:6px; flex-wrap:wrap;">
                                            <?php foreach ($messengers as $m) : ?>
                                            <label style="display:inline-flex; align-items:center; gap:4px; font-size:0.85rem; cursor:pointer;">
                                                <input type="checkbox" class="new_feed_messenger_cb" value="<?php echo (int)$m['id']; ?>">
                                                <?php echo esc_html($m['name']); ?> (<?php echo esc_html($m['platform']); ?>)
                                            </label>
                                            <?php endforeach; ?>
                                        </div>
                                    </div>
                                </div>

                                <div style="margin-top:16px; display:flex; gap:10px; align-items:center;">
                                    <button type="button" class="ssp-btn-primary" onclick="addRssFeed()" id="add_rss_btn"><span class="ssp-btn-text">ذخیره فید</span><span class="ssp-btn-spinner"></span></button>
                                    <span class="ssp-saved-indicator" id="rss_feed_saved">ذخیره شد!</span>
                                </div>
                            </div>
                            <?php endif; ?>
                        </div>

                        <!-- ============ AI SETTINGS ============ -->
                        <div id="tab-ai" class="tab-content">
                            <h2>تنظیمات هوش مصنوعی</h2>
                            <p class="ssp-section-desc">تنظیمات AI برای بازنویسی و تولید خودکار محتوا.</p>

                            <?php if ($plan === 'free') : ?>
                            <div class="ssp-upgrade-banner" style="background:var(--warning-soft); border-color:var(--warning);">
                                <div>
                                    <h3 style="color:var(--warning);">AI فقط در پلن Pro</h3>
                                    <p>با ارتقا به Pro می‌توانید از قابلیت هوش مصنوعی استفاده کنید.</p>
                                </div>
                                <button type="button" class="ssp-btn-primary" onclick="switchTab('subscription', document.querySelector('[data-tab=subscription]'))">ارتقا به Pro</button>
                            </div>
                            <?php else : ?>

                            <form onsubmit="saveAiSettings(event)">
                                <h3 style="margin:24px 0 12px;">حالت هوش مصنوعی</h3>
                                <div class="ssp-grid-2">
                                    <div class="ssp-feature-card <?php echo (get_user_meta($user_id, 'ssp_ai_mode', true) ?: 'api') === 'api' ? 'active' : ''; ?>" onclick="selectAIMode('api')">
                                        <div class="ssp-feature-card-head">
                                            <div>
                                                <h4 class="ssp-feature-card-title">حالت API</h4>
                                                <p class="ssp-feature-card-desc">اتصال مستقیم با API پروایدرها</p>
                                            </div>
                                            <label class="ssp-toggle" onclick="event.stopPropagation();">
                                                <input type="radio" name="ssp_ai_mode" value="api" <?php checked(get_user_meta($user_id, 'ssp_ai_mode', true) ?: 'api', 'api'); ?> onchange="selectAIMode('api')">
                                                <span class="ssp-toggle-slider"></span>
                                            </label>
                                        </div>
                                    </div>
                                    <div class="ssp-feature-card <?php echo (get_user_meta($user_id, 'ssp_ai_mode', true) ?: 'api') === 'browser' ? 'active' : ''; ?>" onclick="selectAIMode('browser')">
                                        <div class="ssp-feature-card-head">
                                            <div>
                                                <h4 class="ssp-feature-card-title">حالت مرورگر</h4>
                                                <p class="ssp-feature-card-desc">اتصال از طریق رابط وب چت‌بات‌ها (بدون API Key)</p>
                                            </div>
                                            <label class="ssp-toggle" onclick="event.stopPropagation();">
                                                <input type="radio" name="ssp_ai_mode" value="browser" <?php checked(get_user_meta($user_id, 'ssp_ai_mode', true) ?: 'api', 'browser'); ?> onchange="selectAIMode('browser')">
                                                <span class="ssp-toggle-slider"></span>
                                            </label>
                                        </div>
                                    </div>
                                </div>
                                <input type="hidden" id="ssp_ai_mode" name="ssp_ai_mode" value="<?php echo esc_attr(get_user_meta($user_id, 'ssp_ai_mode', true) ?: 'api'); ?>">

                                <div id="api_mode_settings" style="display:<?php echo (get_user_meta($user_id, 'ssp_ai_mode', true) ?: 'api') === 'api' ? 'block' : 'none'; ?>;">
                                <h3 style="margin:24px 0 12px;">1. سرویس‌دهنده AI را انتخاب کنید</h3>
                                <div class="ssp-ai-providers">
                                    <?php foreach ([
                                        'groq' => ['<svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" style="vertical-align:middle;"><polygon points="13 2 3 14 12 14 11 22 21 10 12 10 13 2"/></svg>', 'Groq (رایگان)', 'https://console.groq.com/keys'],
                                        'deepseek' => ['<svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" style="vertical-align:middle;margin-left:4px;"><path d="M22 12c0 6-4.39 10-9.8 10C6.6 22 2 17.6 2 12 2 6.6 6.6 2 12 2c5.4 0 10 4.4 10 10z"/><path d="M16 12a4 4 0 0 0-8 0"/></svg>', 'DeepSeek', 'https://platform.deepseek.com/api_keys'],
                                        'openai' => ['🟢', 'OpenAI', 'https://platform.openai.com/api-keys'],
                                        'anthropic' => ['🟠', 'Claude', 'https://console.anthropic.com/'],
                                        'gemini' => ['🔵', 'Gemini', 'https://aistudio.google.com/app/apikey'],
                                        'openrouter' => ['🟣', 'OpenRouter', 'https://openrouter.ai/keys'],
                                    ] as $k => $v) : ?>
                                    <div class="ssp-ai-provider-card <?php echo $ai_provider === $k ? 'selected' : ''; ?>" onclick="selectProvider('<?php echo $k; ?>', this)">
                                        <div class="ssp-ai-provider-icon"><?php echo $v[0]; ?></div>
                                        <div class="ssp-ai-provider-name"><?php echo $v[1]; ?></div>
                                        <a href="<?php echo esc_url($v[2]); ?>" target="_blank" class="ssp-ai-provider-link" onclick="event.stopPropagation();">دریافت API Key</a>
                                    </div>
                                    <?php endforeach; ?>
                                </div>
                                <input type="hidden" id="ai_provider" name="ai_provider" value="<?php echo esc_attr($ai_provider); ?>">

                                <h3 style="margin:24px 0 12px;">2. API Key و مدل</h3>
                                <div class="ssp-form-group">
                                    <label class="ssp-label">API Key</label>
                                    <div class="ssp-input-group">
                                        <input type="password" id="ai_api_key" name="ai_api_key" value="" class="ssp-input" dir="ltr" placeholder="<?php echo $masked_key ?: 'sk-...'; ?>">
                                        <button type="button" class="ssp-eye-btn" onclick="togglePass('ai_api_key')"><svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg></button>
                                    </div>
                                    <p class="ssp-hint">کلید شما فقط برای درخواست‌های شما استفاده می‌شود.</p>
                                </div>
                                <div class="ssp-form-group">
                                    <label class="ssp-label">مدل AI</label>
                                    <input type="text" id="ai_model" name="ai_model" value="<?php echo esc_attr($ai_model); ?>" class="ssp-input" dir="ltr" placeholder="gpt-4o-mini">
                                    <p class="ssp-hint" id="model_hint">مدل‌های پیشنهادی: gpt-4o-mini, gpt-4o, claude-3-haiku, gemini-2.0-flash, llama-3.3-70b-versatile, deepseek-chat</p>
                                    <p class="ssp-hint">پیشنهاد: gpt-4o-mini (OpenAI)، claude-3-5-sonnet (Anthropic)، gemini-1.5-flash (Gemini)</p>
                                </div>

                                <button type="button" class="ssp-btn-test" onclick="testAiConnection()" id="test_ai_btn">تست اتصال AI</button>
                                <button type="button" class="ssp-btn-test" style="background:#10B981; color:white;" onclick="testAiDns()">تست دسترسی DNS به سرورهای AI</button>
                                <div id="ai_test_result" class="ssp-ai-test-result"></div>
                                <div id="ai_dns_test_result" class="ssp-ai-test-result" style="margin-top:10px;"></div>

                                <div style="margin:40px 0 20px; padding:24px; background:rgba(79, 70, 229, 0.03); border:1px solid rgba(79, 70, 229, 0.15); border-radius:12px;">
                                    <h3 style="margin:0 0 8px; color:var(--text); display:flex; align-items:center; gap:8px;">
                                        <svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" style="color:var(--accent);"><path d="M12 2v20M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/></svg>
                                        تنظیمات پردازش اتوماسیون هوش مصنوعی
                                    </h3>
                                    <p style="color:var(--text-muted); font-size:0.85rem; margin-bottom:24px; line-height:1.6;"><strong>توجه:</strong> قابلیت‌های زیر صرفاً برای پردازش محتواهای اتوماتیک (مثل فید RSS یا ربات‌های خودکار وردپرس) کاربرد دارند. این تنظیمات روی <strong>«استودیو هوش مصنوعی»</strong> یا تولید مقالات دستی تاثیری ندارند.</p>
                                    
                                    <h4 style="margin:0 0 12px; font-size:1rem;">نحوه پردازش و بازنویسی (Prompt Mode)</h4>
                                <div class="ssp-grid-2">
                                    <div class="ssp-feature-card <?php echo $ai_prompt_mode !== 'advanced' ? 'active' : ''; ?>" onclick="togglePromptMode('simple')">
                                        <div class="ssp-feature-card-head">
                                            <div>
                                                <h4 class="ssp-feature-card-title">حالت ساده</h4>
                                                <p class="ssp-feature-card-desc">فیلدهای آماده برای موضوع، لحن و تعداد کلمات</p>
                                            </div>
                                            <label class="ssp-toggle" onclick="event.stopPropagation();">
                                                <input type="radio" name="ai_prompt_mode" value="simple" <?php checked($ai_prompt_mode, 'simple'); ?> onchange="togglePromptMode('simple')">
                                                <span class="ssp-toggle-slider"></span>
                                            </label>
                                        </div>
                                    </div>
                                    <div class="ssp-feature-card <?php echo $ai_prompt_mode === 'advanced' ? 'active' : ''; ?>" onclick="togglePromptMode('advanced')">
                                        <div class="ssp-feature-card-head">
                                            <div>
                                                <h4 class="ssp-feature-card-title">حالت پیشرفته</h4>
                                                <p class="ssp-feature-card-desc">نوشتن پرامپت دلخواه با متغیرها</p>
                                            </div>
                                            <label class="ssp-toggle" onclick="event.stopPropagation();">
                                                <input type="radio" name="ai_prompt_mode" value="advanced" <?php checked($ai_prompt_mode, 'advanced'); ?> onchange="togglePromptMode('advanced')">
                                                <span class="ssp-toggle-slider"></span>
                                            </label>
                                        </div>
                                    </div>
                                </div>

                                <div id="ai_custom_prompt_section" style="margin-top:16px; display:<?php echo $ai_prompt_mode === 'advanced' ? 'block' : 'none'; ?>;">
                                    <div class="ssp-guide">
                                        <h3>پرامپت سفارشی</h3>
                                        <ol class="ssp-guide-steps">
                                            <li>از متغیر <code>{title}</code> برای عنوان موجود (هنگام بازنویسی) استفاده کنید</li>
                                            <li>از متغیر <code>{content}</code> برای متن موجود استفاده کنید</li>
                                            <li>حتماً در پایان بنویسید: Return JSON: {{"title":"...", "message":"..."}}</li>
                                        </ol>
                                    </div>
                                    <div class="ssp-form-group">
                                        <label class="ssp-label">پرامپت سفارشی شما</label>
                                        <textarea id="ai_custom_prompt" name="ai_custom_prompt" rows="10" class="ssp-textarea" dir="ltr" placeholder="You are a Persian content writer specializing in {topic}...&#10;&#10;Write an article about recent developments...&#10;&#10;Return JSON: {&quot;title&quot;:&quot;...&quot;, &quot;message&quot;:&quot;...&quot;}"><?php echo esc_textarea($ai_custom_prompt); ?></textarea>
                                    </div>
                                </div>

                                <h4 style="margin:32px 0 12px; font-size:1rem;">عملیات پردازش روی محتواهای ورودی</h4>
                                <div class="ssp-grid-2">
                                    <div class="ssp-feature-card <?php echo !empty($ai_rewrite) ? 'active' : ''; ?>">
                                        <div class="ssp-feature-card-head">
                                            <div>
                                                <h4 class="ssp-feature-card-title">بازنویسی محتوا</h4>
                                                <p class="ssp-feature-card-desc">جلوگیری از Duplicate Content</p>
                                            </div>
                                            <label class="ssp-toggle" onclick="event.stopPropagation();">
                                                <input type="checkbox" id="ai_rewrite" name="ai_rewrite" value="1" <?php checked($ai_rewrite); ?>>
                                                <span class="ssp-toggle-slider"></span>
                                            </label>
                                        </div>
                                    </div>
                                    <div class="ssp-feature-card <?php echo !empty($ai_hashtags) ? 'active' : ''; ?>">
                                        <div class="ssp-feature-card-head">
                                            <div>
                                                <h4 class="ssp-feature-card-title">تولید خودکار هشتگ</h4>
                                                <p class="ssp-feature-card-desc">افزودن هشتگ به محتوای تولید شده</p>
                                            </div>
                                            <label class="ssp-toggle" onclick="event.stopPropagation();">
                                                <input type="checkbox" id="ai_hashtags" name="ai_hashtags" value="1" <?php checked($ai_hashtags); ?>>
                                                <span class="ssp-toggle-slider"></span>
                                            </label>
                                        </div>
                                    </div>
                                </div>
                                </div>
                                </div><!-- end api_mode_settings -->

                                <!-- Browser Mode Settings -->
                                <div id="browser_mode_settings" style="display:<?php echo (get_user_meta($user_id, 'ssp_ai_mode', true) ?: 'api') === 'browser' ? 'block' : 'none'; ?>;">
                                    <h3 style="margin:24px 0 12px;">انتخاب چت‌بات</h3>
                                    <div class="ssp-grid-2">
                                        <div class="ssp-feature-card <?php echo (get_user_meta($user_id, 'ssp_ai_chatbot', true) ?: 'deepseek') === 'deepseek' ? 'active' : ''; ?>" onclick="selectChatbot('deepseek')">
                                            <div class="ssp-feature-card-head">
                                                <div>
                                                    <h4 class="ssp-feature-card-title">DeepSeek Chat</h4>
                                                    <p class="ssp-feature-card-desc">chat.deepseek.com</p>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="ssp-feature-card <?php echo (get_user_meta($user_id, 'ssp_ai_chatbot', true) ?: 'deepseek') === 'chatgpt' ? 'active' : ''; ?>" onclick="selectChatbot('chatgpt')">
                                            <div class="ssp-feature-card-head">
                                                <div>
                                                    <h4 class="ssp-feature-card-title">ChatGPT</h4>
                                                    <p class="ssp-feature-card-desc">chatgpt.com</p>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    <input type="hidden" id="ssp_ai_chatbot" name="ssp_ai_chatbot" value="<?php echo esc_attr(get_user_meta($user_id, 'ssp_ai_chatbot', true) ?: 'deepseek'); ?>">

                                    <div class="ssp-card" style="margin-top:16px; background:var(--accent-soft); border-color:var(--accent);">
                                        <h3>تنظیم اولیه پل ارتباطی مرورگر (یک بار انجام می‌شود)</h3>
                                        <p class="ssp-section-desc" style="margin-top:4px;">پل ارتباطی به شما اجازه می‌دهد بدون نیاز به خرید کلید API، مستقیماً از حساب رایگان خود در DeepSeek یا ChatGPT استفاده کنید.</p>
                                        <ol class="ssp-guide-steps" style="margin:12px 0;">
                                            <li>یکی از افزونه‌های <strong>ScriptCat</strong> یا <strong>Tampermonkey</strong> را روی مرورگر خود نصب کنید: 
                                                <a href="https://chromewebstore.google.com/detail/scriptcat/ndcooeababalnlpkfedmmbbbgkljhpjf" target="_blank" style="display:inline-block; background:#4f46e5; color:white; padding:2px 10px; border-radius:12px; font-size:0.75rem; text-decoration:none; margin:0 4px; vertical-align:middle;">دانلود ScriptCat</a>
                                                <a href="https://chromewebstore.google.com/detail/tampermonkey/dhdgffkkebhmkfjojejmpbldmpobfkfo" target="_blank" style="display:inline-block; background:#10b981; color:white; padding:2px 10px; border-radius:12px; font-size:0.75rem; text-decoration:none; margin:0 4px; vertical-align:middle;">دانلود Tampermonkey</a>
                                            </li>
                                            <li>اگر از ScriptCat استفاده می‌کنید، به صفحه افزونه‌ها (<code>chrome://extensions</code>) بروید، وارد <strong>Details</strong> (جزئیات) اکستنشن شوید و گزینه <strong>Developer mode</strong> را فعال کنید (<a href="https://docs.scriptcat.org/en/docs/use/open-dev/?userscript_enabled=false&userscript_permission=true&userscript_guard=allowScript&browser=chrome#allow-user-scripts" target="_blank">راهنمای فعال‌سازی</a>).</li>
                                            <li>روی دکمه <strong>«دریافت کد و لینک اسکریپت»</strong> در زیر کلیک کنید.</li>
                                            <li><strong>نصب دستی (سریع و مطمئن):</strong> در افزونه ScriptCat یا Tampermonkey روی دکمه <strong>افزودن اسکریپت جدید (+ New Script)</strong> کلیک کنید، کدهای قبلی را پاک کرده و کد اسکریپت کپی‌شده را Paste کنید، سپس ذخیره (Save) را بزنید.</li>
                                            <li><strong>نصب با یک کلیک:</strong> لینک اسکریپت را در یک تب جدید مرورگر باز کنید تا دیالوگ نصب خودکار افزونه نمایان شود و دکمه <strong>Install</strong> را بزنید.</li>
                                            <li>به سایت چت‌بات (<a href="https://chat.deepseek.com" target="_blank">DeepSeek</a> یا <a href="https://chatgpt.com" target="_blank">ChatGPT</a>) بروید؛ ویجت سبز رنگ در گوشه صفحه به معنای اتصال موفقیت‌آمیز است.</li>
                                        </ol>
                                        <div style="display:flex; gap:10px; flex-wrap:wrap;">
                                            <button type="button" class="ssp-btn-primary" onclick="setupBrowserBridge()" id="bridge_setup_btn"><span class="ssp-btn-text">دریافت کد و لینک اسکریپت</span><span class="ssp-btn-spinner"></span></button>
                                            <button type="button" class="ssp-btn-secondary" onclick="testBrowserBridge()" id="bridge_test_btn">تست اتصال</button>
                                        </div>
                                        <div id="bridge_setup_result" style="margin-top:12px;"></div>
                                    </div>

                                    <div class="ssp-card" style="margin-top:12px;">
                                        <h3>جایگزین: بوکمارکلت (بدون افزونه)</h3>
                                        <p class="ssp-section-desc">این روش نیازی به افزونه مرورگر (مانند ScriptCat) ندارد. لینک زیر را به نوار بوکمارک اضافه کنید و پس از دریافت پاسخ از چت‌بات، آن را کلیک کنید.</p>
                                        <a id="bookmarklet_link" href="#" class="ssp-btn-primary" style="text-decoration:none; display:inline-block;" onclick="event.preventDefault();">کپی پاسخ از چت‌بات</a>
                                        <p class="ssp-hint" style="margin-top:8px;">این لینک را به نوار بوکمارک اضافه کنید (drag & drop یا راست کلیک → Bookmark This Link)</p>
                                    </div>
                                </div><!-- end browser_mode_settings -->

                                <div style="margin-top:24px; display:flex; gap:10px; align-items:center;">
                                    <button type="submit" class="ssp-btn-primary" id="save_ai_btn"><span class="ssp-btn-text">ذخیره تنظیمات AI</span><span class="ssp-btn-spinner"></span></button>
                                    <span class="ssp-saved-indicator" id="ai_saved">تنظیمات ذخیره شد!</span>
                                </div>
                            </form>
                            <?php endif; ?>
                        </div>

                        <!-- ============ TEMPLATE ============ -->
                        <div id="tab-template" class="tab-content">
                            <h2>قالب‌ها</h2>
                            <p class="ssp-section-desc">قالب پیام و قالب‌های ذخیره شده خود را برای هر پروفایل مدیریت کنید.</p>

                            <!-- Message Format Template (Global) -->
                            <div class="ssp-card" style="margin-bottom:24px;">
                                <h3>قالب فرمت پیام</h3>
                                <p class="ssp-hint" style="margin-bottom:12px;">قالب نهایی پیامی که به پیام‌رسان‌ها ارسال می‌شود را سفارشی کنید.</p>

                                <div class="ssp-guide" style="margin-bottom:16px;">
                                    <h3>متغیرهای قابل استفاده</h3>
                                    <ol class="ssp-guide-steps">
                                        <li><code>{title}</code> - عنوان پست</li>
                                        <li><code>{message}</code> - متن پیام</li>
                                        <li><code>{link}</code> - لینک پست</li>
                                        <li><code>{hashtags}</code> - هشتگ‌ها</li>
                                        <li><code>{signature}</code> - امضای شما</li>
                                    </ol>
                                </div>

                                <form onsubmit="saveTemplate(event)">
                                    <div class="ssp-form-group">
                                        <label class="ssp-label">قالب پیام</label>
                                        <textarea id="msg_template" rows="6" class="ssp-textarea" placeholder="{title}&#10;&#10;{message}&#10;&#10;{hashtags}&#10;&#10;{link}&#10;&#10;{signature}"><?php echo esc_textarea($msg_template); ?></textarea>
                                    </div>
                                    <div class="ssp-grid-2">
                                        <div class="ssp-form-group">
                                            <label class="ssp-label">هشتگ‌های ثابت</label>
                                            <input type="text" id="hashtags" value="<?php echo esc_attr($global_hashtags); ?>" class="ssp-input" placeholder="#فناوری #اخبار">
                                        </div>
                                        <div class="ssp-form-group">
                                            <label class="ssp-label">امضای پیام</label>
                                            <textarea id="signature" rows="2" class="ssp-textarea" placeholder="کانال من: @mychannel"><?php echo esc_textarea($signature); ?></textarea>
                                        </div>
                                    </div>
                                    <div style="display:flex; gap:10px; align-items:center;">
                                        <button type="submit" class="ssp-btn-primary" id="save_template_btn"><span class="ssp-btn-text">ذخیره قالب</span><span class="ssp-btn-spinner"></span></button>
                                        <span class="ssp-saved-indicator" id="template_saved">قالب ذخیره شد!</span>
                                    </div>
                                </form>
                            </div>

                            <!-- Template Library (Profile-scoped) -->
                            <div class="ssp-card">
                                <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:16px;">
                                    <div>
                                        <h3 style="margin:0;">کتابخانه قالب‌ها</h3>
                                        <p class="ssp-hint" style="margin:4px 0 0 0;">قالب‌های این پروفایل را مدیریت کنید. هر قالب عنوان، متن، هشتگ و امضای اختصاصی دارد.</p>
                                    </div>
                                    <button type="button" class="ssp-btn-primary" onclick="openTemplateCreateModal()" style="white-space:nowrap;">+ قالب جدید</button>
                                </div>

                                <!-- Category filter -->
                                <div style="display:flex; gap:8px; margin-bottom:16px; flex-wrap:wrap;">
                                    <button class="ssp-btn-secondary tpl-filter active" data-filter="all" onclick="filterTemplates('all', this)" style="font-size:0.8rem; padding:5px 12px;">همه</button>
                                    <button class="ssp-btn-secondary tpl-filter" data-filter="general" onclick="filterTemplates('general', this)" style="font-size:0.8rem; padding:5px 12px;">عمومی</button>
                                    <button class="ssp-btn-secondary tpl-filter" data-filter="news" onclick="filterTemplates('news', this)" style="font-size:0.8rem; padding:5px 12px;">اخبار</button>
                                    <button class="ssp-btn-secondary tpl-filter" data-filter="promotion" onclick="filterTemplates('promotion', this)" style="font-size:0.8rem; padding:5px 12px;">تبلیغات</button>
                                    <button class="ssp-btn-secondary tpl-filter" data-filter="educational" onclick="filterTemplates('educational', this)" style="font-size:0.8rem; padding:5px 12px;">آموزشی</button>
                                </div>

                                <div id="template_library_list">
                                    <div class="ssp-empty">
                                        <div class="ssp-empty-icon"><svg viewBox="0 0 24 24" width="48" height="48" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20"/><path d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2z"/></svg></div>
                                        <p>هنوز قالبی ذخیره نکرده‌اید.</p>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- ============ CONTENT CALENDAR ============ -->
                        <div id="tab-calendar" class="tab-content">
                            <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:12px; margin-bottom:8px;">
                                <div>
                                    <h2 class="ssp-tool-title" style="display:flex;align-items:center;gap:10px;margin:0;"><svg viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="currentColor" stroke-width="2" style="display:inline-block;vertical-align:middle;flex-shrink:0;"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg> <span>تقویم هوشمند محتوا</span></h2>
                                    <p class="ssp-section-desc" style="margin:4px 0 0;">برنامه‌ریزی، زمان‌بندی، ویرایش و نظارت بصری بر ارسال‌های خودکار در تقویم جلالی (شمسی)</p>
                                </div>
                                <div style="display:flex; gap:8px; align-items:center;">
                                    <button type="button" class="ssp-btn-secondary" onclick="loadCalendar(true)" title="تازه‌سازی تقویم" style="font-size:0.85rem; padding:8px 12px;">
                                        🔄 بروزرسانی
                                    </button>
                                    <button type="button" class="ssp-btn-primary" onclick="openNewScheduleForDate()" style="font-size:0.85rem; padding:8px 16px;">
                                        + زمان‌بندی جدید
                                    </button>
                                </div>
                            </div>

                            <!-- Calendar Navigation & Filter Controls -->
                            <div class="ssp-card" style="margin-bottom:16px; padding:14px 18px;">
                                <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:14px;">
                                    <!-- Month Switcher -->
                                    <div style="display:flex; align-items:center; gap:10px;">
                                        <button type="button" class="ssp-btn-secondary" onclick="calendarPrevMonth()" style="padding:6px 12px; font-weight:700;">&laquo; ماه قبل</button>
                                        <button type="button" class="ssp-btn-secondary" onclick="calendarToday()" style="padding:6px 12px; font-size:0.8rem;">امروز</button>
                                        <button type="button" class="ssp-btn-secondary" onclick="calendarNextMonth()" style="padding:6px 12px; font-weight:700;">ماه بعد &raquo;</button>
                                        <div style="margin-right:10px;">
                                            <span id="cal_month_label" style="font-weight:800; font-size:1.15rem; color:var(--text);"></span>
                                            <span id="calendar_month_label" style="display:none;"></span>
                                            <div id="cal_sub_label" style="font-size:0.75rem; color:var(--text-muted); direction:ltr; text-align:right;"></div>
                                        </div>
                                    </div>

                                    <!-- Status Filter Tabs -->
                                    <div style="display:flex; gap:6px; background:var(--bg-alt); padding:4px; border-radius:8px; border:1px solid var(--border); flex-wrap:wrap;">
                                        <button type="button" class="ssp-btn-secondary ssp-cal-filter-btn active" data-status="all" onclick="filterCalendarStatus('all')" style="font-size:0.78rem; padding:4px 10px; border-radius:6px;">همه</button>
                                        <button type="button" class="ssp-btn-secondary ssp-cal-filter-btn" data-status="pending" onclick="filterCalendarStatus('pending')" style="font-size:0.78rem; padding:4px 10px; border-radius:6px; color:#d97706;">در انتظار</button>
                                        <button type="button" class="ssp-btn-secondary ssp-cal-filter-btn" data-status="completed" onclick="filterCalendarStatus('completed')" style="font-size:0.78rem; padding:4px 10px; border-radius:6px; color:#059669;">ارسال شده</button>
                                        <button type="button" class="ssp-btn-secondary ssp-cal-filter-btn" data-status="cancelled" onclick="filterCalendarStatus('cancelled')" style="font-size:0.78rem; padding:4px 10px; border-radius:6px; color:#dc2626;">لغو شده</button>
                                    </div>
                                </div>
                            </div>

                            <!-- Persian Week Days Header -->
                            <div style="display:grid; grid-template-columns:repeat(7,1fr); gap:8px; margin-bottom:8px; text-align:center; font-weight:700; font-size:0.85rem; color:var(--text-muted);">
                                <div style="padding:6px; background:var(--bg-alt); border-radius:6px;">شنبه</div>
                                <div style="padding:6px; background:var(--bg-alt); border-radius:6px;">یکشنبه</div>
                                <div style="padding:6px; background:var(--bg-alt); border-radius:6px;">دوشنبه</div>
                                <div style="padding:6px; background:var(--bg-alt); border-radius:6px;">سه‌شنبه</div>
                                <div style="padding:6px; background:var(--bg-alt); border-radius:6px;">چهارشنبه</div>
                                <div style="padding:6px; background:var(--bg-alt); border-radius:6px;">پنجشنبه</div>
                                <div style="padding:6px; background:var(--bg-alt); border-radius:6px; color:#ef4444;">جمعه</div>
                            </div>

                            <!-- Calendar Grid Container -->
                            <div class="ssp-calendar" id="calendar_grid" style="display:grid; grid-template-columns:repeat(7,1fr); gap:8px;">
                                <!-- Calendar loaded via JS -->
                            </div>
                            <div id="content_calendar" style="display:none;"></div>

                            <!-- Calendar Legend & Help -->
                            <div style="margin-top:20px; padding:16px 20px; background:var(--bg-alt); border-radius:12px; border:1px solid var(--border);">
                                <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:12px;">
                                    <div style="display:flex; gap:16px; flex-wrap:wrap; font-size:0.82rem; color:var(--text-muted); align-items:center;">
                                        <strong style="color:var(--text); font-size:0.85rem;">راهنمای وضعیت‌ها:</strong>
                                        <span style="display:inline-flex; align-items:center; gap:5px;">
                                            <span style="display:inline-block; width:10px; height:10px; background:rgba(79,70,229,0.2); border:2px solid var(--accent); border-radius:3px;"></span>
                                            <span>امروز</span>
                                        </span>
                                        <span style="display:inline-flex; align-items:center; gap:5px;">
                                            <span style="display:inline-block; width:8px; height:8px; background:#f59e0b; border-radius:50%;"></span>
                                            <span style="color:#d97706; font-weight:600;">در انتظار ارسال</span>
                                        </span>
                                        <span style="display:inline-flex; align-items:center; gap:5px;">
                                            <span style="display:inline-block; width:8px; height:8px; background:#10b981; border-radius:50%;"></span>
                                            <span style="color:#059669; font-weight:600;">ارسال شده</span>
                                        </span>
                                        <span style="display:inline-flex; align-items:center; gap:5px;">
                                            <span style="display:inline-block; width:8px; height:8px; background:#ef4444; border-radius:50%;"></span>
                                            <span style="color:#dc2626; font-weight:600;">لغو شده</span>
                                        </span>
                                    </div>
                                    <div style="font-size:0.8rem; color:var(--text-muted);">
                                        💡 <em>برای مشاهده، ویرایش، لغو یا ایجاد زمان‌بندی، روی هر روز کلیک کنید.</em>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- ============ SCHEDULES ============ -->
                        <div id="tab-schedules" class="tab-content">
                            <h2>زمان‌بندی ارسال</h2>
                            <p class="ssp-section-desc">پیام‌های خود را برای ارسال در زمان مشخص برنامه‌ریزی کنید.</p>

                            <?php if ($plan === 'free') : ?>
                            <div class="ssp-upgrade-banner" style="background:var(--warning-soft); border-color:var(--warning);">
                                <div>
                                    <h3 style="color:var(--warning);">زمان‌بندی فقط در پلن Pro</h3>
                                    <p>با ارتقا به Pro می‌توانید ارسال‌های خود را زمان‌بندی کنید.</p>
                                </div>
                                <button type="button" class="ssp-btn-primary" onclick="switchTab('subscription', document.querySelector('[data-tab=subscription]'))">ارتقا به Pro</button>
                            </div>
                            <?php else : ?>

                            <div id="schedules_list">
                                <?php if (empty($schedules)) : ?>
                                <div class="ssp-empty">
                                    <div class="ssp-empty-icon"><svg viewBox="0 0 24 24" width="48" height="48" fill="none" stroke="currentColor" stroke-width="1.5"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg></div>
                                    <p>هنوز زمان‌بندی ایجاد نکرده‌اید.</p>
                                </div>
                                <?php else : ?>
                                    <?php foreach ($schedules as $sch) :
                                        $sch_border = $sch['status'] !== 'pending' ? 'var(--success)' : 'var(--accent)';
                                        $sch_icon = $sch['status'] !== 'pending' ? '&#10003;' : '&#9203;';
                                    ?>
                                    <div class="ssp-item-card" style="border-right:3px solid <?php echo $sch_border; ?>;">
                                        <div class="ssp-item-card-head">
                                            <div style="flex:1;">
                                                <div class="ssp-item-card-title">
                                                    <?php echo $sch_icon; ?> <?php echo esc_html($sch['title']); ?>
                                                    <span class="ssp-badge <?php echo $sch['status'] === 'pending' ? 'info' : 'success'; ?>">
                                                        <?php echo $sch['status'] === 'pending' ? 'در انتظار' : 'انجام شده'; ?>
                                                    </span>
                                                    <?php if (!empty($sch['recurring'])) : ?>
                                                    <span class="ssp-badge pro"><?php
                                                        $rec_labels = ['daily' => 'روزانه', 'weekly' => 'هفتگی', 'monthly' => 'ماهانه'];
                                                        echo $rec_labels[$sch['recurring']] ?? $sch['recurring'];
                                                    ?></span>
                                                    <?php endif; ?>
                                                </div>
                                                <div class="ssp-item-card-meta">
                                                    &#128197; <?php echo $this->gregorian_to_jalali_str($sch['scheduled_at']); ?>
                                                </div>
                                                <?php if (!empty($sch['message'])) : ?>
                                                <div class="ssp-item-card-meta" style="margin-top:4px; font-size:0.8rem;">
                                                    <?php echo esc_html(mb_substr($sch['message'], 0, 80)); ?>...
                                                </div>
                                                <?php endif; ?>
                                            </div>
                                            <div style="display:flex; gap:6px; flex-wrap:wrap;">
                                                <button type="button" class="ssp-btn-secondary" onclick="openEditScheduleModal(<?php echo (int)$sch['id']; ?>)" style="font-size:0.75rem; padding:4px 8px;">✏️ ویرایش</button>
                                                <?php if ($sch['status'] === 'pending') : ?>
                                                <button type="button" class="ssp-btn-secondary" onclick="cancelSchedule(<?php echo (int)$sch['id']; ?>)" style="font-size:0.75rem; padding:4px 8px; color:#d97706;">لغو</button>
                                                <?php elseif ($sch['status'] === 'cancelled') : ?>
                                                <button type="button" class="ssp-btn-secondary" onclick="reactivateSchedule(<?php echo (int)$sch['id']; ?>)" style="font-size:0.75rem; padding:4px 8px; color:#059669;">فعال‌سازی</button>
                                                <?php endif; ?>
                                                <button type="button" class="ssp-btn-danger" onclick="deleteSchedule(<?php echo (int)$sch['id']; ?>)" style="font-size:0.75rem; padding:4px 8px;">حذف</button>
                                            </div>
                                        </div>
                                    </div>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </div>

                            <div class="ssp-add-form">
                                <h3>ایجاد زمان‌بندی جدید</h3>
                                <div class="ssp-form-group">
                                    <label class="ssp-label">عنوان پیام</label>
                                    <input type="text" id="schedule_title" class="ssp-input" placeholder="مثلاً: خبر فناوری">
                                </div>
                                <div class="ssp-form-group">
                                    <label class="ssp-label">متن پیام</label>
                                    <textarea id="schedule_message" rows="4" class="ssp-textarea" placeholder="متن پیام..."></textarea>
                                </div>
                                <div class="ssp-grid-2">
                                    <div class="ssp-form-group">
                                        <label class="ssp-label">تاریخ و ساعت ارسال (شمسی)</label>
                                        <input type="text" id="sch_schedule_date_display" class="ssp-input" readonly placeholder="تاریخ را انتخاب کنید" style="cursor:pointer;" onclick="schOpenJalaliPicker()">
                                        <input type="hidden" id="sch_schedule_datetime">
                                        <div class="ssp-hint" style="margin-top:4px;">حداقل ۱۰ دقیقه آینده</div>
                                        <div id="sch_jalali_picker" style="display:none; margin-top:8px; padding:12px; background:var(--card); border:1px solid var(--border); border-radius:8px;">
                                        </div>
                                    </div>
                                    <div class="ssp-form-group">
                                        <label class="ssp-label">تکرار</label>
                                        <select id="schedule_recurring" class="ssp-select">
                                            <option value="">بدون تکرار</option>
                                            <option value="daily">روزانه</option>
                                            <option value="weekly">هفتگی</option>
                                            <option value="monthly">ماهانه</option>
                                        </select>
                                    </div>
                                </div>
                                <div style="margin-top:16px; display:flex; gap:10px; align-items:center;">
                                    <button type="button" class="ssp-btn-primary" onclick="addSchedule()" id="add_schedule_btn"><span class="ssp-btn-text">ایجاد زمان‌بندی</span><span class="ssp-btn-spinner"></span></button>
                                    <span class="ssp-saved-indicator" id="schedule_saved">ایجاد شد!</span>
                                </div>
                            </div>
                            <?php endif; ?>
                        </div>

<!-- ============ TEMPLATE LIBRARY ============ -->

                        <!-- ============ CONTENT DISTRIBUTION ============ -->
                        <div id="tab-distribution" class="tab-content">
                            <h2>توزیع خودکار محتوا</h2>
                            <p class="ssp-section-desc">محتوای سایت‌های متصل را به صورت خودکار و زمان‌بندی شده در پیام‌رسان‌ها توزیع کنید.</p>

                            <?php if ($plan !== 'pro') : ?>
                            <div class="ssp-empty" style="background:var(--warning-soft); border:1px solid var(--warning); border-radius:12px;">
                                <div class="ssp-empty-icon"><svg viewBox="0 0 24 24" width="48" height="48" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg></div>
                                <h3 style="color:var(--warning);">قابلیت Pro</h3>
                                <p>توزیع خودکار محتوا فقط در پلن Pro موجود است.</p>
                                <button type="button" class="ssp-btn-primary" onclick="switchTab('subscription', document.querySelector('[data-tab=subscription]'))">ارتقا به Pro</button>
                            </div>
                            <?php elseif (empty($wp_sites)) : ?>
                            <div class="ssp-empty">
                                <div class="ssp-empty-icon"><svg viewBox="0 0 24 24" width="48" height="48" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M10 13a5 5 0 0 0 7.54.54l3-3a5 5 0 0 0-7.07-7.07l-1.72 1.71"/><path d="M14 11a5 5 0 0 0-7.54-.54l-3 3a5 5 0 0 0 7.07 7.07l1.71-1.71"/></svg></div>
                                <p>ابتدا یک سایت وردپرسی اضافه کنید.</p>
                                <button type="button" class="ssp-btn-primary" onclick="switchTab('wpsources', document.querySelector('[data-tab=wpsources]'))">رفتن به منابع وردپرس</button>
                            </div>
                            <?php else : ?>
                            <div style="display:flex; gap:10px; margin-bottom:16px; flex-wrap:wrap;">
                                <button type="button" class="ssp-btn-primary" onclick="openDistributionForm()">+ توزیع جدید</button>
                            </div>
                            <div id="distributions_list"></div>
                            <div id="distribution_form_wrap" style="display:none;">
                                <div class="ssp-card" style="margin-bottom:16px;">
                                    <h3 style="margin-bottom:12px;">تنظیمات توزیع</h3>
                                    <input type="hidden" id="dist_edit_id" value="">
                                    <div class="ssp-grid-2">
                                        <div class="ssp-form-group">
                                            <label class="ssp-label">نام توزیع</label>
                                            <input type="text" id="dist_name" class="ssp-input" placeholder="مثال: محصولات جدید موتورسیکلت">
                                        </div>
                                        <div class="ssp-form-group">
                                            <label class="ssp-label">منبع محتوا</label>
                                            <select id="dist_source_site" class="ssp-select">
                                                <?php foreach ($wp_sites as $site) : ?>
                                                <option value="<?php echo (int)$site['id']; ?>"><?php echo esc_html($site['site_name']); ?></option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                    </div>
                                    <div class="ssp-grid-2">
                                        <div class="ssp-form-group">
                                            <label class="ssp-label">نوع محتوا</label>
                                            <select id="dist_source_type" class="ssp-select">
                                                <option value="product">محصولات WooCommerce</option>
                                                <option value="post">نوشته‌ها/مقالات</option>
                                                <option value="draft">پیش‌نویس‌های پورتال</option>
                                            </select>
                                        </div>
                                        <div class="ssp-form-group">
                                            <label class="ssp-toggle" style="display:flex; align-items:center; gap:10px;">
                                                <input type="checkbox" id="dist_only_new" onchange="toggleNewContentDelay()">
                                                <span class="ssp-toggle-slider"></span>
                                            </label>
                                            <div>
                                                <span style="font-size:0.85rem; font-weight:600;">فقط محتوای جدید ارسال شود</span>
                                                <p style="font-size:0.75rem; color:var(--text-muted); margin:2px 0 0;">در غیر این صورت از بین همه محتواهای موجود به صورت چرخشی انتخاب می‌کند</p>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="ssp-grid-2">
                                        <div class="ssp-form-group">
                                            <label class="ssp-label">فیلتر دسته‌بندی (ID)</label>
                                            <input type="text" id="dist_filter_categories" class="ssp-input" placeholder="مثال: ۱۵,۲۳,۴۲">
                                            <p class="ssp-hint">فقط محتوای این دسته‌ها ارسال شود.</p>
                                        </div>
                                        <div class="ssp-form-group">
                                            <label class="ssp-label">فیلتر برچسب (ID)</label>
                                            <input type="text" id="dist_filter_tags" class="ssp-input" placeholder="مثال: ۸,۱۲">
                                            <p class="ssp-hint">فقط محتوای این برچسب‌ها ارسال شود.</p>
                                        </div>
                                    </div>
                                    <div class="ssp-grid-2">
                                        <div class="ssp-form-group">
                                            <label class="ssp-label">حداقل قیمت (تومان)</label>
                                            <input type="number" id="dist_filter_min_price" class="ssp-input" placeholder="۰" min="0" style="width:100%;">
                                            <p class="ssp-hint">فقط محصولات با قیمت بالاتر از این مقدار</p>
                                        </div>
                                        <div class="ssp-form-group">
                                            <label class="ssp-label">حداکثر قیمت (تومان)</label>
                                            <input type="number" id="dist_filter_max_price" class="ssp-input" placeholder="۰" min="0" style="width:100%;">
                                            <p class="ssp-hint">فقط محصولات با قیمت پایین‌تر از این مقدار</p>
                                        </div>
                                    </div>
                                    <div class="ssp-form-group">
                                        <label class="ssp-label">وضعیت موجودی</label>
                                        <select id="dist_filter_stock" class="ssp-select">
                                            <option value="">همه</option>
                                            <option value="instock">موجود</option>
                                            <option value="outofstock">ناموجود</option>
                                            <option value="onbackorder">قابل سفارش</option>
                                        </select>
                                        <p class="ssp-hint">فقط محصولات با وضعیت انبار مشخص شده</p>
                                    </div>
                                    <div id="new_content_delay_section" class="ssp-form-group" style="display:block;">
                                        <label class="ssp-label">تأخیر ارسال محتوای جدید</label>
                                        <div style="display:flex; gap:10px; align-items:center;">
                                            <input type="number" id="dist_new_delay" class="ssp-input" value="5" min="0" style="width:100px;">
                                            <select id="dist_new_delay_unit" class="ssp-select" style="width:120px;">
                                                <option value="minutes">دقیقه</option>
                                                <option value="hours">ساعت</option>
                                                <option value="days">روز</option>
                                            </select>
                                        </div>
                                        <p class="ssp-hint">محتوای جدید پس از این مدت از زمان انتشار، در پیام‌رسان‌ها ارسال می‌شود.</p>
                                    </div>
                                    <div class="ssp-form-group">
                                        <label class="ssp-label">مرتب‌سازی</label>
                                        <select id="dist_sort" class="ssp-select">
                                            <option value="newest">جدیدترین</option>
                                            <option value="oldest">قدیمی‌ترین</option>
                                            <option value="random">تصادفی</option>
                                        </select>
                                    </div>
                                    <div class="ssp-form-group">
                                        <label class="ssp-label">قالب پیام</label>
                                        <textarea id="dist_template" class="ssp-textarea" rows="5" placeholder="{title}
{excerpt}
{url}">{title}
{excerpt}
{url}</textarea>
                                        <p class="ssp-hint">متغیرها: <code>{title}</code> <code>{excerpt}</code> <code>{url}</code> <code>{image}</code> <code>{price}</code> — برای خط جدید Enter بزنید</p>
                                    </div>
                                    <div class="ssp-form-group">
                                        <label class="ssp-label">پیام‌رسان‌های مقصد</label>
                                        <div style="display:flex; gap:8px; flex-wrap:wrap;">
                                            <?php foreach ($messengers as $m) : ?>
                                            <label style="display:flex; align-items:center; gap:4px; font-size:0.85rem; cursor:pointer;">
                                                <input type="checkbox" class="dist_target_cb" value="<?php echo (int)$m['id']; ?>" checked>
                                                <?php echo esc_html($m['name']); ?> (<?php echo esc_html($m['platform']); ?>)
                                            </label>
                                            <?php endforeach; ?>
                                        </div>
                                    </div>
                                    <div class="ssp-form-group">
                                        <label class="ssp-toggle" style="display:flex; align-items:center; gap:10px;">
                                            <input type="checkbox" id="dist_include_image" value="1">
                                            <span class="ssp-toggle-slider"></span>
                                        </label>
                                        <div>
                                            <span style="font-size:0.85rem; font-weight:600;">ارسال تصویر شاخص محصول</span>
                                            <p style="font-size:0.75rem; color:var(--text-muted); margin:2px 0 0;">تصویر شاخص محصول به همراه پیام ارسال شود</p>
                                        </div>
                                    </div>

                                    <!-- زمان‌بندی دقیق ارسال -->
                                    <div id="dist_schedule_section" class="ssp-card" style="margin-top:16px; border:1px solid var(--border); background:var(--bg-alt);">
                                        <h4 style="margin:0 0 8px; font-size:0.95rem; color:var(--primary); display:flex; align-items:center; gap:8px;">
                                            <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
                                            زمان‌بندی ارسال
                                        </h4>
                                        <p style="font-size:0.8rem; color:var(--text-muted); margin:0 0 16px;">ساعت‌هایی که می‌خواهید محتوا ارسال شود را اضافه کنید. تعداد ساعت‌های تعریف‌شده = تعداد ارسال روزانه.</p>
                                        
                                        <input type="hidden" id="dist_schedule_type" value="daily">
                                        
                                        <div id="dist_time_slots" style="display:flex; flex-wrap:wrap; gap:12px; margin-bottom:16px; min-height:60px; padding:16px; background:var(--bg-alt); border-radius:12px; border:2px dashed var(--border);">
                                            <div class="dist-time-slot" style="display:flex; align-items:center; gap:10px; background:var(--card); padding:10px 14px; border-radius:10px; box-shadow:0 2px 8px rgba(0,0,0,0.08); border:1px solid var(--border);">
                                                <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="var(--primary)" stroke-width="2"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
                                                <input type="time" class="dist-time-input ssp-input" value="10:00" style="padding:8px 12px; font-size:0.95rem; width:140px; border:1px solid var(--border); border-radius:8px; font-weight:600; color:var(--text);">
                                                <button type="button" onclick="removeDistTimeSlot(this)" style="background:var(--error-soft); border:none; color:var(--danger); cursor:pointer; font-size:1.3rem; padding:0 10px; border-radius:8px; transition:all 0.2s; display:flex; align-items:center; justify-content:center;" title="حذف این ساعت">×</button>
                                            </div>
                                        </div>
                                        
                                        <div style="display:flex; gap:10px; align-items:center; flex-wrap:wrap;">
                                            <button type="button" class="ssp-btn-secondary" onclick="addDistTimeSlot()" style="font-size:0.9rem; padding:10px 18px; display:inline-flex; align-items:center; gap:8px; border-radius:10px; font-weight:600; transition:all 0.2s;">
                                                <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
                                                افزودن زمان
                                            </button>
                                            <button type="button" class="ssp-btn-secondary" onclick="addPresetTimes()" style="font-size:0.85rem; padding:8px 14px; border-radius:8px; opacity:0.8; display:inline-flex; align-items:center; gap:6px;" title="افزودن زمان‌های پیشنهادی (۹ صبح، ۱۲ ظهر، ۳ بعدازظهر، ۶ عصر)">
                                                <svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2"><polygon points="13 2 3 14 12 14 11 22 21 10 12 10 13 2"/></svg>
                                                زمان‌های پیشنهادی
                                            </button>
                                        </div>
                                        
                                        <input type="hidden" id="dist_schedule_times" value="10:00">
                                        
                                        <div id="dist_schedule_summary" style="margin-top:16px; padding:12px 16px; background:var(--accent-soft); border-radius:10px; font-size:0.9rem; color:var(--text); border:1px solid var(--accent); display:flex; align-items:center; gap:8px;">
                                            <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="var(--primary)" stroke-width="2"><line x1="18" y1="20" x2="18" y2="10"/><line x1="12" y1="20" x2="12" y2="4"/><line x1="6" y1="20" x2="6" y2="14"/></svg>
                                            <strong style="color:var(--primary);">خلاصه:</strong> <span id="schedule_summary_text" style="font-weight:500;">۱ ارسال در روز (۱۰:۰۰)</span>
                                        </div>
                                    </div>

                                    <div style="display:flex; gap:10px; margin-top:16px;">
                                        <button type="button" class="ssp-btn-primary" onclick="saveDistribution()">ذخیره توزیع</button>
                                        <button type="button" class="ssp-btn-secondary" onclick="closeDistributionForm()">انصراف</button>
                                        <button type="button" class="ssp-btn-secondary" onclick="previewDistribution()">پیش‌نمایش</button>
                                    </div>
                                </div>
                            </div>
                            <div id="dist_preview" style="display:none;"></div>
                            <?php endif; ?>
                        </div>

                        <!-- ============ SEO TOOLS ============ -->
                        <div id="tab-seo" class="tab-content">
                            <h2>ابزار SEO</h2>

                            <!-- SEO Sub-tabs Navigation -->
                            <div style="display:flex; gap:6px; margin-bottom:20px; overflow-x:auto; padding-bottom:4px; flex-wrap:wrap;">
                                <button class="ssp-seo-subtab active" onclick="switchSeoSubtab('content', this)"><svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" style="vertical-align:middle;margin-right:4px;"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/></svg> تحلیل محتوا</button>
                                <button class="ssp-seo-subtab" onclick="switchSeoSubtab('keywords', this)">کلمات کلیدی</button>
                                <button class="ssp-seo-subtab" onclick="switchSeoSubtab('serp', this)">SERP & URL</button>
                                <button class="ssp-seo-subtab" onclick="switchSeoSubtab('geo', this)">GEO</button>
                                <button class="ssp-seo-subtab" onclick="switchSeoSubtab('eeat', this)">E-E-A-T</button>
                                <button class="ssp-seo-subtab" onclick="switchSeoSubtab('schema', this)">Schema</button>
                                <button class="ssp-seo-subtab" onclick="switchSeoSubtab('site-audit', this)">Site Audit</button>
                            </div>

                            <?php if (!$ai_configured) : ?>
                            <div class="ssp-empty" style="background:var(--warning-soft); border:1px solid var(--warning); border-radius:12px;">
                                <div class="ssp-empty-icon"><svg viewBox="0 0 24 24" width="48" height="48" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg></div>
                                <h3 style="color:var(--warning);"> هوش مصنوعی تنظیم نشده است</h3>
                                <p>برای استفاده از ابزارهای SEO هوشمند، ابتدا تنظیمات هوش مصنوعی را تکمیل کنید.</p>
                                <button type="button" class="ssp-btn-primary" onclick="switchTab('ai', document.querySelector('[data-tab=ai]'))">رفتن به تنظیمات AI</button>
                            </div>
                            <?php else : ?>

                            <!-- ===== SUB-TAB: Content Analysis ===== -->
                            <div id="seo-subtab-content" class="seo-subtab-content">
                                <div class="ssp-card">
                                    <h3 style="margin:0 0 12px;">تحلیلگر SEO محتوا</h3>
                                    <p style="color:var(--text-muted); font-size:0.85rem; margin:0 0 12px;">محتوا را برای موتورهای جستجو و پلتفرم‌های مختلف تحلیل کنید</p>
                                    <div class="ssp-form-group">
                                        <label class="ssp-label">عنوان</label>
                                        <input type="text" id="seo_title" class="ssp-input" placeholder="عنوان پیام خود را وارد کنید" oninput="updateSeoCharCount('title')">
                                        <span class="ssp-hint" id="seo_title_count">0 کاراکتر</span>
                                    </div>
                                    <div class="ssp-form-group">
                                        <label class="ssp-label">محتوا</label>
                                        <textarea id="seo_content" rows="4" class="ssp-textarea" placeholder="متن پیام خود را وارد کنید" oninput="updateSeoCharCount('content')"></textarea>
                                        <span class="ssp-hint" id="seo_content_count">0 کاراکتر • 0 کلمه</span>
                                    </div>
                                    <div class="ssp-form-group">
                                        <label class="ssp-label">هشتگ‌ها</label>
                                        <input type="text" id="seo_hashtags" class="ssp-input" placeholder="#tag1 #tag2" oninput="updateSeoHashtagCount()">
                                        <span class="ssp-hint" id="seo_hashtag_count">0 هشتگ</span>
                                    </div>
                                    <div class="ssp-form-group">
                                        <label class="ssp-label">پلتفرم هدف</label>
                                        <select id="seo_platform" class="ssp-select" onchange="showPlatformTips()">
                                            <option value="general">عمومی</option>
                                            <option value="website">وبسایت</option>
                                            <option value="telegram">تلگرام</option>
                                            <option value="bale">بله</option>
                                            <option value="eitaa">ایتا</option>
                                            <option value="rubika">روبیکا</option>
                                            <option value="instagram">اینستاگرام</option>
                                            <option value="whatsapp">واتساپ</option>
                                        </select>
                                    </div>
                                    <div id="platform_tips" style="display:none; background:var(--info-soft); padding:12px; border-radius:10px; margin-bottom:16px; font-size:0.85rem; color:var(--info);"></div>
                                    <div class="ssp-form-group">
                                        <label class="ssp-label">حالت پرامپت AI</label>
                                        <div style="display:flex; gap:8px; align-items:center;">
                                            <select id="seo_prompt_mode" class="ssp-select" onchange="updateSeoPromptMode()" style="flex:1;">
                                                <option value="">پیش‌فرض (بدون حالت خاص)</option>
                                                <option value="human">Human Writer - متن انسانی‌تر</option>
                                                <option value="redteam">Red Team - تحلیل انتقادی</option>
                                                <option value="x10think">x10 Think - تفکر عمیق</option>
                                                <option value="socrates">Socrates - پرسش‌گری هوشمند</option>
                                                <option value="truth"> Truth - واقعیت‌محور</option>
                                                <option value="meta">Meta - تحلیل فرآیند</option>
                                                <option value="predict">Predict - پیش‌بینی آینده</option>
                                                <option value="ooda">OODA Loop - چرخه تصمیم‌گیری</option>
                                                <option value="eli10">ELI10 - توضیح ساده</option>
                                                <option value="alt3">Alt3 - ۳ دیدگاه مختلف</option>
                                            </select>
                                            <button type="button" class="ssp-btn-secondary" onclick="seoResetPromptMode()" title="بازگردانی به پیش‌فرض" style="padding:8px 12px; font-size:0.75rem;">↺</button>
                                        </div>
                                        <p class="ssp-hint" id="seo_prompt_mode_hint" style="display:none;"></p>
                                    </div>
                                    <div style="display:flex; gap:10px; align-items:center; flex-wrap:wrap;">
                                        <button type="button" class="ssp-btn-primary" onclick="analyzeSeo()" id="seo_analyze_btn"><span class="ssp-btn-text">تحلیل SEO</span><span class="ssp-btn-spinner"></span></button>
                                        <button type="button" class="ssp-btn-secondary" onclick="suggestSeoTitle()">پیشنهاد عنوان</button>
                                        <button type="button" class="ssp-btn-secondary" onclick="generateMetaDesc()">تولید متا دیسکریپشن</button>
                                        <?php if ($plan === 'pro') : ?>
                                        <?php if ((get_user_meta($user_id, 'ssp_ai_mode', true) ?: 'api') !== 'browser') : ?>
                                        <button type="button" class="ssp-btn-secondary" onclick="analyzeSeoWithAi()" id="seo_ai_btn"><span class="ssp-btn-text">تحلیل با API</span><span class="ssp-btn-spinner"></span></button>
                                        <?php endif; ?>
                                        <?php if ((get_user_meta($user_id, 'ssp_ai_mode', true) ?: 'api') === 'browser') : ?>
                                        <button type="button" class="ssp-btn-primary" style="background:#10B981; color:white;" onclick="seoAnalyzeViaBrowser()" id="seo_browser_btn">
                                            <span class="ssp-btn-text">تحلیل با چت‌بات رایگان</span>
                                            <span class="ssp-btn-spinner"></span>
                                        </button>
                                        <?php endif; ?>
                                        <?php endif; ?>
                                    </div>
                                    <p class="ssp-hint" style="margin-top:8px;">با API: سریع‌تر و مستقیم (نیاز به کلید API) | با چت‌بات رایگان: از حساب رایگان DeepSeek/ChatGPT (نیاز به افزونه مرورگر)</p>
                                    <div id="seo_results" style="margin-top:20px;"></div>
                                    <div id="meta_desc_result" style="margin-top:16px;"></div>
                                </div>

                                <!-- Content Gap Analysis -->
                                <div class="ssp-card" style="margin-top:16px;">
                                    <h3 style="margin:0 0 12px;">تحلیل شکاف محتوا (Content Gap)</h3>
                                    <p style="color:var(--text-muted); font-size:0.85rem; margin:0 0 12px;">بررسی کنید کلمات کلیدی مورد نظر شما در محتوا وجود دارند یا خیر</p>
                                    <div class="ssp-form-group">
                                        <label class="ssp-label">کلمات کلیدی (با کاما جدا کنید)</label>
                                        <input type="text" id="gap_keywords" class="ssp-input" placeholder="سئو, بازاریابی محتوا, گوگل">
                                    </div>
                                    <button type="button" class="ssp-btn-secondary" onclick="analyzeContentGap()">تحلیل شکاف محتوا</button>
                                    <div id="gap_results" style="margin-top:16px;"></div>
                                </div>

                                <!-- Topic Clustering -->
                                <div class="ssp-card" style="margin-top:16px;">
                                    <h3 style="margin:0 0 12px;">تحلیل خوشه موضوعی (Topic Cluster)</h3>
                                    <p style="color:var(--text-muted); font-size:0.85rem; margin:0 0 12px;">موضوعات اصلی و ارتباط بین کلمات محتوا را شناسایی کنید</p>
                                    <button type="button" class="ssp-btn-secondary" onclick="analyzeTopicCluster()">تحلیل خوشه موضوعی</button>
                                    <div id="cluster_results" style="margin-top:16px;"></div>
                                </div>

                                <!-- Featured Snippet Optimizer -->
                                <div class="ssp-card" style="margin-top:16px;">
                                    <h3 style="margin:0 0 12px;">بهینه‌سازی Featured Snippet</h3>
                                    <p style="color:var(--text-muted); font-size:0.85rem; margin:0 0 12px;">فرمت محتوا را برای قرار گرفتن در Position Zero گوگل بهینه کنید</p>
                                    <button type="button" class="ssp-btn-secondary" onclick="analyzeFeaturedSnippet()">تحلیل Featured Snippet</button>
                                    <div id="snippet_results" style="margin-top:16px;"></div>
                                </div>

                                <!-- Voice Search Optimizer -->
                                <div class="ssp-card" style="margin-top:16px;">
                                    <h3 style="margin:0 0 12px;">بهینه‌سازی جستجوی صوتی</h3>
                                    <p style="color:var(--text-muted); font-size:0.85rem; margin:0 0 12px;">محتوا را برای جستجوی صوتی (Google Assistant, Siri) بهینه کنید</p>
                                    <button type="button" class="ssp-btn-secondary" onclick="analyzeVoiceSearch()">تحلیل جستجوی صوتی</button>
                                    <div id="voice_results" style="margin-top:16px;"></div>
                                </div>

                                <!-- SEO Checklist -->
                                <div class="ssp-card" style="margin-top:16px;">
                                    <h3 style="margin:0 0 12px;">چک‌لیست SEO</h3>
                                    <p style="color:var(--text-muted); font-size:0.85rem; margin:0 0 12px;">بررسی کامل تمام عناصر SEO محتوا</p>
                                    <button type="button" class="ssp-btn-secondary" onclick="generateSeoChecklist()">تولید چک‌لیست</button>
                                    <div id="checklist_results" style="margin-top:16px;"></div>
                                </div>
                            </div>

                            <!-- ===== SUB-TAB: Keywords ===== -->
                            <div id="seo-subtab-keywords" class="seo-subtab-content" style="display:none;">
                                <div class="ssp-card">
                                    <h3 style="margin:0 0 12px;">تحلیلگر کلمات کلیدی</h3>
                                    <p style="color:var(--text-muted); font-size:0.85rem; margin:0 0 12px;">تراکم، تکرار و عبارات کلیدی محتوا را تحلیل کنید</p>
                                    <div style="margin-bottom:12px;">
                                        <button type="button" class="ssp-btn-secondary" onclick="importToKeywordAnalyzer()" style="font-size:0.8rem;">دریافت از فرم تحلیل محتوا</button>
                                    </div>
                                    <div class="ssp-form-group">
                                        <label class="ssp-label">محتوا برای تحلیل</label>
                                        <textarea id="kw_content" rows="4" class="ssp-textarea" placeholder="متن محتوا را اینجا وارد کنید..."></textarea>
                                    </div>
                                    <div class="ssp-form-group">
                                        <label class="ssp-label">کلمه کلیدی اصلی (اختیاری)</label>
                                        <input type="text" id="kw_focus" class="ssp-input" placeholder="مثلاً: آموزش سئو">
                                    </div>
                                    <button type="button" class="ssp-btn-primary" onclick="analyzeKeywords()" id="kw_analyze_btn"><span class="ssp-btn-text">تحلیل کلمات کلیدی</span><span class="ssp-btn-spinner"></span></button>
                                    <div id="kw_results" style="margin-top:16px;"></div>
                                </div>
                            </div>

                            <!-- ===== SUB-TAB: SERP & URL ===== -->
                            <div id="seo-subtab-serp" class="seo-subtab-content" style="display:none;">
                                <!-- SERP Preview -->
                                <div class="ssp-card">
                                    <h3 style="margin:0 0 12px;">پیش‌نمایش نتیجه جستجو (SERP)</h3>
                                    <p style="color:var(--text-muted); font-size:0.85rem; margin:0 0 12px;">ببینید صفحه شما در گوگل چطور نمایش داده می‌شود</p>
                                    <div style="margin-bottom:12px;">
                                        <button type="button" class="ssp-btn-secondary" onclick="importToSerpPreview()" style="font-size:0.8rem;">دریافت از فرم تحلیل محتوا</button>
                                    </div>
                                    <div class="ssp-form-group">
                                        <label class="ssp-label">عنوان صفحه</label>
                                        <input type="text" id="serp_title" class="ssp-input" placeholder="عنوان صفحه" oninput="updateSerpPreview()">
                                    </div>
                                    <div class="ssp-form-group">
                                        <label class="ssp-label">آدرس URL</label>
                                        <input type="text" id="serp_url" class="ssp-input" placeholder="https://example.com/page" oninput="updateSerpPreview()">
                                    </div>
                                    <div class="ssp-form-group">
                                        <label class="ssp-label">توضیحات meta</label>
                                        <textarea id="serp_desc" rows="2" class="ssp-textarea" placeholder="توضیحات صفحه برای موتورهای جستجو" oninput="updateSerpPreview()"></textarea>
                                        <span class="ssp-hint" id="serp_desc_count">0/155</span>
                                    </div>
                                    <div id="serp_preview_box" style="margin-top:12px; padding:16px; background:#fff; border:1px solid #dadce0; border-radius:8px; font-family:Arial,sans-serif; direction:ltr;">
                                        <div style="color:#1a0dab; font-size:1.1rem; cursor:pointer; margin-bottom:4px;" id="serp_display_title">عنوان صفحه</div>
                                        <div style="color:#006621; font-size:0.85rem; margin-bottom:4px;" id="serp_display_url">example.com/page</div>
                                        <div style="color:#545454; font-size:0.85rem; line-height:1.4;" id="serp_display_desc">توضیحات صفحه در اینجا نمایش داده می‌شود...</div>
                                    </div>
                                    <div id="serp_analysis" style="margin-top:12px;"></div>
                                </div>

                                <!-- URL Auditor -->
                                <div class="ssp-card" style="margin-top:16px;">
                                    <h3 style="margin:0 0 12px;">ممیزی SEO صفحه (URL Audit)</h3>
                                    <p style="color:var(--text-muted); font-size:0.85rem; margin:0 0 12px;">یک URL وارد کنید تا عناصر SEO آن بررسی شود</p>
                                    <div class="ssp-form-group">
                                        <label class="ssp-label">آدرس URL</label>
                                        <input type="text" id="audit_url" class="ssp-input" placeholder="https://example.com/page">
                                    </div>
                                    <button type="button" class="ssp-btn-primary" onclick="auditUrl()" id="audit_btn"><span class="ssp-btn-text">ممیزی صفحه</span><span class="ssp-btn-spinner"></span></button>
                                    <div id="audit_results" style="margin-top:16px;"></div>
                                </div>
                            </div>

                            <!-- ===== SUB-TAB: GEO ===== -->
                            <div id="seo-subtab-geo" class="seo-subtab-content" style="display:none;">
                                <div class="ssp-card" style="border:1px solid var(--accent);">
                                    <h3 style="margin:0 0 4px;">تحلیلگر GEO</h3>
                                    <p style="color:var(--text-muted); font-size:0.85rem; margin:0 0 12px;">بهینه‌سازی محتوا برای ChatGPT، Gemini، Perplexity و سایر موتورهای جستجوی هوش مصنوعی</p>
                                    <div style="margin-bottom:12px;">
                                        <button type="button" class="ssp-btn-secondary" onclick="importToGeo()" style="font-size:0.8rem;">دریافت از فرم تحلیل محتوا</button>
                                    </div>
                                    <div class="ssp-form-group">
                                        <label class="ssp-label">عنوان محتوا</label>
                                        <input type="text" id="geo_title" class="ssp-input" placeholder="عنوان محتوا">
                                    </div>
                                    <div class="ssp-form-group">
                                        <label class="ssp-label">محتوا</label>
                                        <textarea id="geo_content" rows="5" class="ssp-textarea" placeholder="متن کامل محتوا را اینجا وارد کنید..."></textarea>
                                    </div>
                                    <button type="button" class="ssp-btn-primary" onclick="analyzeGeo()" id="geo_btn"><span class="ssp-btn-text">تحلیل GEO</span><span class="ssp-btn-spinner"></span></button>
                                    <div id="geo_results" style="margin-top:16px;"></div>
                                </div>
                            </div>

                            <!-- ===== SUB-TAB: E-E-A-T ===== -->
                            <div id="seo-subtab-eeat" class="seo-subtab-content" style="display:none;">
                                <div class="ssp-card">
                                    <h3 style="margin:0 0 12px;">تحلیلگر E-E-A-T</h3>
                                    <p style="color:var(--text-muted); font-size:0.85rem; margin:0 0 12px;">تجربه، تخصص، اعتبار و اعتماد محتوا را بررسی کنید (معیار کیفیت گوگل)</p>
                                    <div style="margin-bottom:12px;">
                                        <button type="button" class="ssp-btn-secondary" onclick="importToEeat()" style="font-size:0.8rem;">دریافت از فرم تحلیل محتوا</button>
                                    </div>
                                    <div class="ssp-form-group">
                                        <label class="ssp-label">عنوان</label>
                                        <input type="text" id="eeat_title" class="ssp-input" placeholder="عنوان محتوا">
                                    </div>
                                    <div class="ssp-form-group">
                                        <label class="ssp-label">محتوا</label>
                                        <textarea id="eeat_content" rows="5" class="ssp-textarea" placeholder="متن کامل محتوا..."></textarea>
                                    </div>
                                    <div class="ssp-form-group">
                                        <label class="ssp-label">نام نویسنده (اختیاری)</label>
                                        <input type="text" id="eeat_author" class="ssp-input" placeholder="نام نویسنده">
                                    </div>
                                    <button type="button" class="ssp-btn-primary" onclick="analyzeEeat()" id="eeat_btn"><span class="ssp-btn-text">تحلیل E-E-A-T</span><span class="ssp-btn-spinner"></span></button>
                                    <div id="eeat_results" style="margin-top:16px;"></div>
                                </div>
                            </div>

                            <!-- ===== SUB-TAB: Schema ===== -->
                            <div id="seo-subtab-schema" class="seo-subtab-content" style="display:none;">
                                <div class="ssp-card">
                                    <h3 style="margin:0 0 12px;">تولیدکننده Schema Markup</h3>
                                    <p style="color:var(--text-muted); font-size:0.85rem; margin:0 0 12px;">داده‌های ساختاریافته (JSON-LD) برای موتورهای جستجو تولید کنید</p>
                                    <div class="ssp-form-group">
                                        <label class="ssp-label">نوع Schema</label>
                                        <select id="schema_type" class="ssp-select" onchange="updateSchemaForm()">
                                            <option value="article">مقاله (Article)</option>
                                            <option value="faq">سوالات متداول (FAQ)</option>
                                            <option value="howto">آموزش گام‌به‌گام (HowTo)</option>
                                            <option value="product">محصول (Product)</option>
                                            <option value="organization">سازمان (Organization)</option>
                                            <option value="localbusiness">کسب‌وکار محلی (LocalBusiness)</option>
                                            <option value="breadcrumb">مسیر ناوبری (Breadcrumb)</option>
                                            <option value="video">ویدیو (Video)</option>
                                        </select>
                                    </div>
                                    <div id="schema_form_fields">
                                        <div class="ssp-form-group">
                                            <label class="ssp-label">عنوان</label>
                                            <input type="text" id="schema_title" class="ssp-input" placeholder="عنوان">
                                        </div>
                                        <div class="ssp-form-group">
                                            <label class="ssp-label">توضیحات</label>
                                            <textarea id="schema_desc" rows="2" class="ssp-textarea" placeholder="توضیحات"></textarea>
                                        </div>
                                        <div class="ssp-form-group">
                                            <label class="ssp-label">آدرس URL</label>
                                            <input type="text" id="schema_url" class="ssp-input" placeholder="https://example.com/page">
                                        </div>
                                    </div>
                                    <button type="button" class="ssp-btn-primary" onclick="generateSchema()" id="schema_btn"><span class="ssp-btn-text">تولید Schema</span><span class="ssp-btn-spinner"></span></button>
                                    <div id="schema_results" style="margin-top:16px;"></div>
                                </div>
                            </div>

                            <!-- ===== SUB-TAB: Site Audit ===== -->
                            <div id="seo-subtab-site-audit" class="seo-subtab-content" style="display:none;">
                                <div class="ssp-card">
                                    <h3 style="margin:0 0 8px;">تحلیل کامل سایت</h3>
                                    <p style="color:var(--text-muted); font-size:0.85rem; margin:0 0 12px;">آدرس صفحه مورد نظر را وارد کنید تا تحلیل جامع SEO، نقاط قوت و ضعف، پیشنهادات بهبود، ایده‌های تولید محتوا، ترفندهای رتبه‌بندی و راهکارهای دیده شدن در نتایج هوش مصنوعی دریافت کنید.</p>
                                    <div class="ssp-form-group">
                                        <label class="ssp-label">آدرس صفحه (URL)</label>
                                        <input type="url" id="site_audit_url" class="ssp-input" placeholder="https://example.com/page" dir="ltr">
                                    </div>
                                    <div class="ssp-form-group">
                                        <label class="ssp-label">حالت پرامپت AI</label>
                                        <div style="display:flex; gap:8px; align-items:center;">
                                            <select id="site_audit_prompt_mode" class="ssp-select" onchange="updateSiteAuditPromptMode()" style="flex:1;">
                                                <option value="">پیش‌فرض (بدون حالت خاص)</option>
                                                <option value="human">Human Writer - متن انسانی‌تر</option>
                                                <option value="redteam">Red Team - تحلیل انتقادی</option>
                                                <option value="x10think">x10 Think - تفکر عمیق</option>
                                                <option value="socrates">Socrates - پرسش‌گری هوشمند</option>
                                                <option value="truth"> Truth - واقعیت‌محور</option>
                                                <option value="meta">Meta - تحلیل فرآیند</option>
                                                <option value="predict">Predict - پیش‌بینی آینده</option>
                                                <option value="ooda">OODA Loop - چرخه تصمیم‌گیری</option>
                                                <option value="eli10">ELI10 - توضیح ساده</option>
                                                <option value="alt3">Alt3 - ۳ دیدگاه مختلف</option>
                                            </select>
                                            <button type="button" class="ssp-btn-secondary" onclick="siteAuditResetPromptMode()" title="بازگردانی به پیش‌فرض" style="padding:8px 12px; font-size:0.75rem;">↺</button>
                                        </div>
                                        <p class="ssp-hint" id="site_audit_prompt_mode_hint" style="display:none;"></p>
                                    </div>
                                    <div style="display:flex; gap:10px; flex-wrap:wrap;">
                                        <?php if ((get_user_meta($user_id, 'ssp_ai_mode', true) ?: 'api') !== 'browser') : ?>
                                        <button type="button" class="ssp-btn-primary" onclick="runSiteAudit()" id="site_audit_btn">
                                            <span class="ssp-btn-text">تحلیل کامل سایت</span>
                                            <span class="ssp-btn-spinner"></span>
                                        </button>
                                        <?php endif; ?>
                                        <?php if ((get_user_meta($user_id, 'ssp_ai_mode', true) ?: 'api') === 'browser') : ?>
                                        <button type="button" class="ssp-btn-primary" style="background:#10B981; color:white;" onclick="runSiteAuditViaBrowser()" id="site_audit_browser_btn">
                                            <span class="ssp-btn-text">تحلیل با هوش مصنوعی مرورگر</span>
                                            <span class="ssp-btn-spinner"></span>
                                        </button>
                                        <?php endif; ?>
                                    </div>
                                    <div id="site_audit_results" style="margin-top:16px;"></div>
                                </div>
                            </div>
                            <?php endif; ?>
                        </div>

                        <!-- ============ LINK SHORTENER ============ -->
                        <div id="tab-links" class="tab-content">
                            <h2>کوتاه‌کننده لینک</h2>
                            <p class="ssp-section-desc">لینک‌های خود را کوتاه کنید و عملکرد آنها را ردیابی کنید.</p>

                            <div class="ssp-guide">
                                <h3>کوتاه‌کننده لینک چیست؟</h3>
                                <ol class="ssp-guide-steps">
                                    <li>لینک‌های طولانی را کوتاه و خوانا کنید</li>
                                    <li>تعداد کلیک‌ها را ردیابی کنید</li>
                                    <li>آمار استفاده از لینک‌ها را مشاهده کنید</li>
                                    <li><strong>SEO Friendly:</strong> ریدایرکت 301 هیچ آسیبی به سئو نمیزنه</li>
                                </ol>
                            </div>

                            <?php
                            $short_stats = $this->get_short_link_stats($user_id);
                            ?>

                            <div class="ssp-grid-3" style="margin-bottom:20px;">
                                <div class="ssp-card">
                                    <h3>کل لینک‌ها</h3>
                                    <div class="ssp-val"><?php echo $short_stats['total_links']; ?></div>
                                </div>
                                <div class="ssp-card">
                                    <h3>کل کلیک‌ها</h3>
                                    <div class="ssp-val"><?php echo number_format($short_stats['total_clicks']); ?></div>
                                </div>
                                <div class="ssp-card">
                                    <h3>میانگین کلیک</h3>
                                    <div class="ssp-val"><?php echo $short_stats['total_links'] > 0 ? number_format($short_stats['total_clicks'] / $short_stats['total_links']) : '0'; ?></div>
                                </div>
                            </div>

                            <div class="ssp-card">
                                <h3>تنظیمات کوتاه‌کننده لینک</h3>

                                <?php
                                $host = parse_url(home_url(), PHP_URL_HOST);
                                ?>

                                <div style="background:var(--success-soft); padding:14px; border-radius:10px; margin-bottom:16px;">
                                    <strong>فرمت لینک کوتاه:</strong>
                                    <div style="font-size:0.9rem; margin-top:8px; font-family:monospace; direction:ltr; text-align:left;">
                                        <?php echo home_url('/go/abc123'); ?>
                                    </div>
                                    <div style="font-size:0.8rem; color:var(--text-muted); margin-top:4px;">
                                        لینک‌های کوتاه با فرمت بالا ساخته میشن
                                    </div>
                                </div>
                            </div>

                            <div class="ssp-card" style="margin-top:16px;">
                                <h3>تنظیمات کوتاه‌کننده لینک</h3>
                                <form onsubmit="saveLinkSettings(event)">
                                    <div class="ssp-toggle" style="margin-bottom:16px;">
                                        <label>
                                            <input type="checkbox" id="link_shortener_enabled" <?php echo $link_settings['enabled'] ?? false ? 'checked' : ''; ?>>
                                            <span class="ssp-toggle-slider"></span>
                                            <span>فعال‌سازی کوتاه‌کننده لینک</span>
                                        </label>
                                    </div>
                                    <div class="ssp-grid-2">
                                        <div class="ssp-form-group">
                                            <label class="ssp-label">سرویس‌دهنده</label>
                                            <select id="link_provider" class="ssp-select" onchange="toggleApiKeyField()">
                                                <option value="self" <?php echo ($link_settings['provider'] ?? '') === 'self' ? 'selected' : ''; ?>>خودمیزبان (پیشنهادی)</option>
                                                <option value="bitly" <?php echo ($link_settings['provider'] ?? '') === 'bitly' ? 'selected' : ''; ?>>Bitly</option>
                                                <option value="tinyurl" <?php echo ($link_settings['provider'] ?? '') === 'tinyurl' ? 'selected' : ''; ?>>TinyURL</option>
                                            </select>
                                        </div>
                                        <div class="ssp-form-group" id="api_key_field" style="display:<?php echo ($link_settings['provider'] ?? 'self') === 'self' ? 'none' : 'block'; ?>;">
                                            <label class="ssp-label">API Key</label>
                                            <div class="ssp-input-group">
                                                <input type="password" id="link_api_key" class="ssp-input" dir="ltr" placeholder="API Key">
                                                <button type="button" class="ssp-eye-btn" onclick="togglePass('link_api_key')"><svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg></button>
                                            </div>
                                        </div>
                                    </div>
                                    <div style="background:var(--success-soft); padding:12px; border-radius:10px; margin:12px 0; color:var(--success); font-size:0.85rem;">
                                        <strong>خودمیزبان:</strong> لینک‌ها با دامنه سایت شما ساخته میشن (مثلاً: <?php echo home_url('/go/abc123'); ?>)
                                        - بدون نیاز به API Key، رایگان، و SEO Friendly
                                    </div>
                                    <button type="submit" class="ssp-btn-primary" id="save_link_btn"><span class="ssp-btn-text">ذخیره تنظیمات</span><span class="ssp-btn-spinner"></span></button>
                                </form>
                            </div>

                            <div class="ssp-card" style="margin-top:16px;">
                                <h3>تست کوتاه‌کردن لینک</h3>
                                <div class="ssp-form-group">
                                    <label class="ssp-label">لینک اصلی</label>
                                    <input type="url" id="test_long_url" class="ssp-input" dir="ltr" placeholder="https://example.com/very-long-url">
                                </div>
                                <button type="button" class="ssp-btn-secondary" onclick="testShortenUrl()" id="test_shorten_btn">کوتاه کردن لینک</button>
                                <div id="shorten_result" style="margin-top:12px;"></div>
                            </div>

                            <?php if (!empty($short_stats['links'])) : ?>
                            <div class="ssp-card" style="margin-top:16px;">
                                <h3>لینک‌های کوتاه شده</h3>
                                <div class="ssp-table-wrap">
                                    <table class="ssp-logs-table">
                                        <thead>
                                            <tr>
                                                <th>کد</th>
                                                <th>لینک اصلی</th>
                                                <th>کلیک</th>
                                                <th>تاریخ ایجاد</th>
                                                <th>عملیات</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($short_stats['links'] as $code => $link) : ?>
                                            <tr>
                                                <td><code><?php echo esc_html($code); ?></code></td>
                                                <td style="max-width:300px; overflow:hidden; text-overflow:ellipsis; white-space:nowrap;"><?php echo esc_html($link['long_url']); ?></td>
                                                <td><strong><?php echo $link['clicks'] ?? 0; ?></strong></td>
                                                <td><?php echo $this->jalali_date_short($link['created_at']); ?></td>
                                                <td>
                                                    <button type="button" class="ssp-btn-secondary" onclick="copyShortLink('<?php echo esc_js(home_url('/go/' . $code)); ?>')" style="padding:4px 8px; font-size:0.75rem;">کپی</button>
                                                    <button type="button" class="ssp-btn-danger" onclick="deleteShortLink('<?php echo esc_js($code); ?>')" style="padding:4px 8px; font-size:0.75rem;">حذف</button>
                                                </td>
                                            </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                            <?php endif; ?>

                            <!-- UTM Parameters Section -->
                            <div class="ssp-card" style="margin-top:16px;">
                                <h3>پارامترهای UTM</h3>
                                <p class="ssp-section-desc" style="margin-bottom:16px;">پارامترهای ردیابی به صورت خودکار به لینک‌ها اضافه شوند.</p>

                                <?php $utm = is_array($__tmp = get_user_meta($user_id, 'ssp_utm_settings', true)) ? $__tmp : []; ?>
                                <form onsubmit="saveUtmSettings(event)">
                                    <div class="ssp-toggle" style="margin-bottom:16px;">
                                        <label>
                                            <input type="checkbox" id="utm_enabled" <?php echo !empty($utm['enabled']) ? 'checked' : ''; ?>>
                                            <span class="ssp-toggle-slider"></span>
                                            <span>فعال‌سازی پارامترهای UTM</span>
                                        </label>
                                    </div>
                                    <div class="ssp-grid-2">
                                        <div class="ssp-form-group">
                                            <label class="ssp-label">UTM Source</label>
                                            <input type="text" id="utm_source" class="ssp-input" value="<?php echo esc_attr($utm['source'] ?? 'smart-automation'); ?>" placeholder="smart-automation">
                                        </div>
                                        <div class="ssp-form-group">
                                            <label class="ssp-label">UTM Medium</label>
                                            <input type="text" id="utm_medium" class="ssp-input" value="<?php echo esc_attr($utm['medium'] ?? 'social'); ?>" placeholder="social">
                                        </div>
                                    </div>
                                    <div class="ssp-grid-2">
                                        <div class="ssp-form-group">
                                            <label class="ssp-label">UTM Campaign (اختیاری)</label>
                                            <input type="text" id="utm_campaign" class="ssp-input" value="<?php echo esc_attr($utm['campaign'] ?? ''); ?>" placeholder="مثلاً: summer-sale">
                                        </div>
                                        <div class="ssp-toggle" style="margin-top:24px;">
                                            <label>
                                                <input type="checkbox" id="utm_auto_source" <?php echo isset($utm['auto_source']) ? ($utm['auto_source'] ? 'checked' : '') : 'checked'; ?>>
                                                <span class="ssp-toggle-slider"></span>
                                                <span>منبع خودکار (نام پلتفرم)</span>
                                            </label>
                                        </div>
                                    </div>
                                    <div style="background:var(--info-soft); padding:12px; border-radius:10px; margin:12px 0; color:var(--info); font-size:0.85rem;">
                                        <strong>پیش‌نمایش:</strong> <span id="utm_preview" style="font-family:monospace; direction:ltr;"></span>
                                    </div>
                                    <button type="submit" class="ssp-btn-primary" id="save_utm_btn"><span class="ssp-btn-text">ذخیره تنظیمات UTM</span><span class="ssp-btn-spinner"></span></button>
                                </form>
                            </div>
                        </div>

                        <!-- ============ REPORTS ============ -->
                        <div id="tab-reports" class="tab-content">
                            <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:20px; flex-wrap:wrap; gap:12px;">
                                <div>
                                    <h2 style="margin:0;">گزارشات فعالیت</h2>
                                    <p style="color:var(--text-muted); margin:4px 0 0; font-size:0.9rem;">30 فعالیت اخیر شما</p>
                                    <?php
                                    $error_count = count(array_filter($logs, function($l) { return $l['status'] === 'error'; }));
                                    if ($error_count > 0) : ?>
                                    <div style="margin-top:8px; display:inline-flex; align-items:center; gap:6px; background:var(--error-soft); border:1px solid var(--error); border-radius:8px; padding:4px 12px; font-size:0.8rem; color:var(--error);">
                                        <svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="15" y1="9" x2="9" y2="15"/><line x1="9" y1="9" x2="15" y2="15"/></svg>
                                        <?php echo $error_count; ?> خطا در ارسال‌ها
                                    </div>
                                    <?php endif; ?>
                                </div>
                                <div style="display:flex; gap:8px;">
                                    <button type="button" class="ssp-btn-secondary" onclick="exportCSV()">خروجی CSV</button>
                                    <button type="button" class="ssp-btn-danger" onclick="clearLogs()">پاک کردن همه</button>
                                </div>
                            </div>

                            <?php if ($logs) : ?>
                            <div class="ssp-table-wrap">
                                <table class="ssp-logs-table" id="sspLogsTable">
                                    <thead>
                                        <tr>
                                            <th>پلتفرم</th>
                                            <th>عنوان</th>
                                            <th>AI</th>
                                            <th>زمان</th>
                                            <th>وضعیت</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($logs as $log) : ?>
                                        <tr class="ssp-log-row" style="cursor:<?php echo $log['status'] === 'error' ? 'pointer' : 'default'; ?>;" onclick="toggleLogDetail(<?php echo (int)$log['id']; ?>)">
                                            <td data-label="پلتفرم"><strong><?php echo esc_html($log['platform']); ?></strong></td>
                                            <td data-label="عنوان"><?php echo esc_html($log['title'] ? mb_substr($log['title'], 0, 40) : mb_substr($log['message'] ?? '', 0, 40)); ?></td>
                                            <td data-label="AI پردازش"><?php echo $log['ai_processed'] ? '<span class="ssp-badge info">' . esc_html($log['ai_provider']) . '</span>' : '-'; ?></td>
                                            <td data-label="زمان ثبت"><?php echo $this->gregorian_to_jalali_str($log['created_at'] ?? 'now'); ?></td>
                                            <td data-label="وضعیت">
                                                <span class="ssp-badge <?php echo $log['status'] === 'success' ? 'success' : 'error'; ?>">
                                                    <?php echo $log['status'] === 'success' ? 'موفق' : 'خطا'; ?>
                                                </span>
                                            </td>
                                        </tr>
                                        <?php if ($log['status'] === 'error' && !empty($log['response'])) : ?>
                                        <?php
                                        $log_time = strtotime($log['created_at'] ?? 'now');
                                        $is_old = (time() - $log_time) > 86400; // More than 24 hours
                                        $has_image = !empty($log['image_url']);
                                        $tool_type = $log['tool_type'] ?? '';
                                        ?>
                                        <tr id="log-detail-<?php echo (int)$log['id']; ?>" style="display:none;">
                                            <td colspan="5" style="padding:0;">
                                                <div style="padding:12px 16px; background:var(--error-soft); border-top:2px solid var(--error); font-size:0.85rem;">
                                                    <?php
                                                    $raw_error = strtolower($log['response']);
                                                    $error_hint = '';
                                                    if (strpos($raw_error, 'chat not found') !== false || strpos($raw_error, 'peer not found') !== false) {
                                                        $error_hint = 'شناسه چت نامعتبر است. بررسی کنید که آیدی کانال/گروه صحیح باشد (و با @ شروع شود) و ربات شما عضو آن باشد.';
                                                    } elseif (strpos($raw_error, 'kicked') !== false || strpos($raw_error, 'blocked') !== false || strpos($raw_error, 'banned') !== false) {
                                                        $error_hint = 'ربات از گروه یا کانال اخراج شده است، یا کاربر ربات را بلاک کرده است.';
                                                    } elseif (strpos($raw_error, 'not enough rights') !== false || strpos($raw_error, 'admin') !== false || strpos($raw_error, 'not admin') !== false) {
                                                        $error_hint = 'ربات شما دسترسی لازم را ندارد. لطفاً مطمئن شوید ربات مدیر (Admin) کانال یا گروه است.';
                                                    } elseif (strpos($raw_error, 'curl error 28') !== false || strpos($raw_error, 'timeout') !== false) {
                                                        $error_hint = 'ارتباط سرور با پیام‌رسان قطع شد. (در هاست‌های ایرانی ممکن است نیاز به پراکسی یا افزونه‌های دور زدن تحریم داشته باشید).';
                                                    } elseif (strpos($raw_error, 'unauthorized') !== false || strpos($raw_error, 'invalid token') !== false) {
                                                        $error_hint = 'توکن (Token) ربات شما نامعتبر است. لطفاً توکن را در بخش پیام‌رسان‌ها به‌روزرسانی کنید.';
                                                    } elseif (strpos($raw_error, 'too long') !== false || strpos($raw_error, 'max length') !== false) {
                                                        $error_hint = 'متن پیام شما طولانی‌تر از حد مجاز این پیام‌رسان است. لطفاً متن را خلاصه‌تر کنید.';
                                                    } elseif (strpos($raw_error, 'file identifier') !== false || strpos($raw_error, 'wrong file') !== false || strpos($raw_error, 'media') !== false) {
                                                        $error_hint = 'فایل رسانه (عکس/ویدیو) پذیرفته نشد. ممکن است حجم آن زیاد باشد یا پیام‌رسان فرمت آن را پشتیبانی نکند.';
                                                    }
                                                    ?>
                                                    <div style="font-weight:700; color:var(--error); margin-bottom:6px;">علت خطا:</div>
                                                    <pre style="white-space:pre-wrap; word-break:break-all; margin:0 0 12px 0; font-size:0.8rem; color:var(--text); direction:ltr; background:var(--bg-main); padding:8px; border-radius:6px;"><?php echo esc_html($log['response']); ?></pre>
                                                    <?php if ($error_hint) : ?>
                                                    <div style="margin-bottom:12px; padding:10px 14px; background:var(--warning-soft); border-right:4px solid var(--warning); border-radius:4px; font-size:0.85rem; color:var(--text); line-height:1.6;">
                                                        <strong>💡 راهنمای عیب‌یابی:</strong><br>
                                                        <?php echo $error_hint; ?>
                                                    </div>
                                                    <?php endif; ?>
                                                    <?php if (!empty($log['message'])) : ?>
                                                    <div style="margin-top:8px; font-weight:600;">متن پیام:</div>
                                                    <pre style="white-space:pre-wrap; word-break:break-all; margin:4px 0 0; font-size:0.8rem; color:var(--text);"><?php echo esc_html(mb_substr($log['message'], 0, 300)); ?></pre>
                                                    <?php endif; ?>
                                                    <?php if ($has_image && !$is_old) : ?>
                                                    <div style="margin-top:8px; font-weight:600;">تصویر/ویدیو:</div>
                                                    <a href="<?php echo esc_url($log['image_url']); ?>" target="_blank" style="color:var(--accent); font-size:0.8rem;">مشاهده فایل رسانه</a>
                                                    <?php elseif ($has_image && $is_old) : ?>
                                                    <div style="margin-top:8px; font-size:0.8rem; color:var(--warning);">تصویر و ویدیو موجود نمیباشد (بیش از ۲۴ ساعت گذشته)</div>
                                                    <?php endif; ?>
                                                    <div style="margin-top:12px; display:flex; gap:8px; align-items:center;">
                                                        <?php if ($is_old) : ?>
                                                        <button type="button" class="ssp-btn-primary" onclick="retryMessageOld(<?php echo (int)$log['id']; ?>, '<?php echo esc_js($tool_type); ?>', '<?php echo esc_js($log['title'] ?? ''); ?>', '<?php echo esc_js($log['message'] ?? ''); ?>')" style="font-size:0.8rem; padding:6px 16px;">ویرایش و ارسال مجدد</button>
                                                        <?php else : ?>
                                                        <button type="button" class="ssp-btn-primary" onclick="retryMessage(<?php echo (int)$log['id']; ?>)" style="font-size:0.8rem; padding:6px 16px;">ارسال مجدد</button>
                                                        <?php endif; ?>
                                                    </div>
                                                </div>
                                            </td>
                                        </tr>
                                        <?php endif; ?>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                            <?php else : ?>
                            <div class="ssp-empty">
                                <div class="ssp-empty-icon"><svg viewBox="0 0 24 24" width="48" height="48" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M22 12h-6l-2 3h-4l-2-3H2"/><path d="M5.45 5.11L2 12v6a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2v-6l-3.45-6.89A2 2 0 0 0 16.76 4H7.24a2 2 0 0 0-1.79 1.11z"/></svg></div>
                                <p>هنوز فعالیتی ثبت نشده است.</p>
                            </div>
                            <?php endif; ?>
                        </div>

                        <!-- ============ SUBSCRIPTION ============ -->
                        <div id="tab-subscription" class="tab-content">
                            <h2>اشتراک و لایسنس</h2>
                            <p class="ssp-section-desc">مدیریت پلن اشتراک و فعال‌سازی کدهای Pro.</p>

                            <div class="ssp-card" style="background: linear-gradient(135deg, var(--accent-soft), transparent); border-color: var(--accent);">
                                <h3>پلن فعلی شما</h3>
                                <div class="ssp-val"><?php echo $plan === 'pro' ? 'حرفه‌ای (Pro)' : 'رایگان (Free)'; ?></div>
                                <?php
                                $expiry = get_user_meta($user_id, 'ssp_plan_expiry', true);
                                if ($plan === 'pro' && $expiry) : ?>
                                <div class="ssp-sub">تاریخ انقضا: <?php echo $this->jalali_date_short(date('Y-m-d', $expiry)); ?></div>
                                <?php endif; ?>
                            </div>

                            <?php if ($plan === 'free') : ?>
                            <h3 style="margin:32px 0 16px;">مقایسه پلن‌ها</h3>
                            <div class="ssp-table-wrap">
                                <table class="ssp-logs-table">
                                    <thead>
                                        <tr><th>ویژگی</th><th>رایگان</th><th>Pro</th></tr>
                                    </thead>
                                    <tbody>
                                        <tr><td>تعداد پیام‌رسان</td><td>1 عدد</td><td><strong>10 عدد</strong></td></tr>
                                        <tr><td>سایت وردپرسی</td><td>-</td><td><strong>10 عدد</strong></td></tr>
                                        <tr><td>ارسال روزانه</td><td>5 عدد</td><td><strong>نامحدود</strong></td></tr>
                                        <tr><td>هوش مصنوعی</td><td>-</td><td>بله</td></tr>
                                        <tr><td>بازنویسی محتوا</td><td>-</td><td>بله</td></tr>
                                        <tr><td>تولید خودکار</td><td>-</td><td>بله</td></tr>
                                        <tr><td>RSS Feed</td><td>بله</td><td>بله</td></tr>
                                    </tbody>
                                </table>
                            </div>
                            <?php endif; ?>

                            <h3 style="margin:32px 0 12px;">فعال‌سازی کد لایسنس</h3>
                            <div id="license-activate-msg" style="display:none;margin-bottom:12px;padding:12px 16px;border-radius:10px;font-weight:600;"></div>
                            <form id="license-activate-form" onsubmit="activateLicense(event)">
                                <div style="display:flex; gap:10px; flex-wrap:wrap;">
                                    <input type="text" id="license_key_input" class="ssp-input" dir="ltr" placeholder="PRO-XXXX-XXXX-XXXX" required style="flex:1; min-width:200px;">
                                    <button type="submit" class="ssp-btn-primary" id="license-activate-btn">فعال‌سازی</button>
                                </div>
                            </form>
                            <script>
                            function activateLicense(e) {
                                e.preventDefault();
                                var key = document.getElementById('license_key_input').value.trim();
                                var btn = document.getElementById('license-activate-btn');
                                var msgEl = document.getElementById('license-activate-msg');
                                if (!key) return;
                                btn.disabled = true;
                                btn.textContent = 'در حال فعال‌سازی...';
                                msgEl.style.display = 'none';
                                var fd = new FormData();
                                fd.append('action', 'ssp_activate_license');
                                fd.append('security', '<?php echo $nonce; ?>');
                                fd.append('license_key', key);
                                var ctrl = new AbortController();
                                var tid = setTimeout(function() { ctrl.abort(); }, 30000);
                                fetch('<?php echo admin_url('admin-ajax.php'); ?>', {method:'POST', body:fd, signal:ctrl.signal})
                                    .then(function(r){ clearTimeout(tid); return r.json(); })
                                    .then(function(res){
                                        btn.disabled = false;
                                        btn.textContent = 'فعال‌سازی';
                                        msgEl.style.display = 'block';
                                        if (res.success) {
                                            msgEl.style.background = 'rgba(16,185,129,0.12)';
                                            msgEl.style.color = 'var(--success)';
                                            msgEl.textContent = res.data.message;
                                            setTimeout(function(){ location.reload(); }, 1500);
                                        } else {
                                            msgEl.style.background = 'rgba(239,68,68,0.12)';
                                            msgEl.style.color = 'var(--error)';
                                            msgEl.textContent = res.data ? res.data.message : 'خطا در فعال‌سازی لایسنس';
                                        }
                                    })
                                    .catch(function(err){
                                        clearTimeout(tid);
                                        btn.disabled = false;
                                        btn.textContent = 'فعال‌سازی';
                                        msgEl.style.display = 'block';
                                        msgEl.style.background = 'rgba(239,68,68,0.12)';
                                        msgEl.style.color = 'var(--error)';
                                        msgEl.textContent = err.name === 'AbortError' ? 'درخواست تمام شد' : 'خطا در اتصال';
                                    });
                            }
                            </script>

                            <!-- Email Notification Settings -->
                            <div class="ssp-card" style="margin-top:32px;">
                                <h3>اعلام‌های ایمیلی</h3>
                                <p class="ssp-section-desc" style="margin-bottom:16px;">از وضعیت ارسال‌ها و اشتراک خود باخبر شوید.</p>

                                <?php $email_settings = is_array($__tmp = get_user_meta($user_id, 'ssp_email_settings', true)) ? $__tmp : []; ?>
                                <div id="email-settings-msg" style="display:none;margin-bottom:12px;padding:12px 16px;border-radius:10px;font-weight:600;"></div>
                                <form id="email-settings-form" onsubmit="submitEmailSettings(event)">
                                    <div class="ssp-toggle" style="margin-bottom:16px;">
                                        <label>
                                            <input type="checkbox" id="email_enabled" <?php echo !empty($email_settings['enabled']) ? 'checked' : ''; ?>>
                                            <span class="ssp-toggle-slider"></span>
                                            <span>فعال‌سازی ایمیل</span>
                                        </label>
                                    </div>
                                    <div class="ssp-form-group">
                                        <label class="ssp-label">آدرس ایمیل</label>
                                        <input type="email" id="email_address" class="ssp-input" dir="ltr" value="<?php echo esc_attr($email_settings['email'] ?? (get_userdata($user_id) ? get_userdata($user_id)->user_email : '') ?? ''); ?>" placeholder="email@example.com">
                                    </div>
                                    <div class="ssp-grid-3" style="margin-bottom:16px;">
                                        <div class="ssp-toggle" style="margin-top:0;">
                                            <label>
                                                <input type="checkbox" id="email_on_failure" <?php echo isset($email_settings['on_failure']) ? ($email_settings['on_failure'] ? 'checked' : '') : 'checked'; ?>>
                                                <span class="ssp-toggle-slider"></span>
                                                <span>خطای ارسال</span>
                                            </label>
                                        </div>
                                        <div class="ssp-toggle" style="margin-top:0;">
                                            <label>
                                                <input type="checkbox" id="email_on_license_expiry" <?php echo isset($email_settings['on_license_expiry']) ? ($email_settings['on_license_expiry'] ? 'checked' : '') : 'checked'; ?>>
                                                <span class="ssp-toggle-slider"></span>
                                                <span>انقضای اشتراک</span>
                                            </label>
                                        </div>
                                        <div class="ssp-toggle" style="margin-top:0;">
                                            <label>
                                                <input type="checkbox" id="email_daily_summary" <?php echo !empty($email_settings['daily_summary']) ? 'checked' : ''; ?>>
                                                <span class="ssp-toggle-slider"></span>
                                                <span>خلاصه روزانه</span>
                                            </label>
                                        </div>
                                    </div>
                                    <div style="display:flex; gap:10px; flex-wrap:wrap;">
                                        <button type="submit" class="ssp-btn-primary" id="save_email_btn">ذخیره تنظیمات</button>
                                        <button type="button" class="ssp-btn-secondary" onclick="testEmail()">ارسال ایمیل تست</button>
                                    </div>
                                </form>
                                <script>
                                function submitEmailSettings(e) {
                                    e.preventDefault();
                                    var email = document.getElementById('email_address').value;
                                    if (!email) { showToast('آدرس ایمیل را وارد کنید', 'error'); return; }
                                    var btn = document.getElementById('save_email_btn');
                                    var msgEl = document.getElementById('email-settings-msg');
                                    btn.disabled = true;
                                    btn.textContent = 'در حال ذخیره...';
                                    msgEl.style.display = 'none';
                                    var fd = new FormData();
                                    fd.append('action', 'ssp_save_email_settings');
                                    fd.append('security', '<?php echo $nonce; ?>');
                                    fd.append('email_enabled', document.getElementById('email_enabled').checked ? '1' : '0');
                                    fd.append('email_address', document.getElementById('email_address').value);
                                    fd.append('email_on_failure', document.getElementById('email_on_failure').checked ? '1' : '0');
                                    fd.append('email_on_license_expiry', document.getElementById('email_on_license_expiry').checked ? '1' : '0');
                                    fd.append('email_daily_summary', document.getElementById('email_daily_summary').checked ? '1' : '0');
                                    var ctrl = new AbortController();
                                    var tid = setTimeout(function() { ctrl.abort(); }, 30000);
                                    fetch('<?php echo admin_url('admin-ajax.php'); ?>', {method:'POST', body:fd, signal:ctrl.signal})
                                        .then(function(r){ clearTimeout(tid); return r.json(); })
                                        .then(function(res){
                                            btn.disabled = false;
                                            btn.textContent = 'ذخیره تنظیمات';
                                            msgEl.style.display = 'block';
                                            if (res.success) {
                                                msgEl.style.background = 'rgba(16,185,129,0.12)';
                                                msgEl.style.color = 'var(--success)';
                                                msgEl.textContent = res.data.message;
                                            } else {
                                                msgEl.style.background = 'rgba(239,68,68,0.12)';
                                                msgEl.style.color = 'var(--error)';
                                                msgEl.textContent = res.data ? res.data.message : 'خطا در ذخیره';
                                            }
                                        })
                                        .catch(function(err){
                                            clearTimeout(tid);
                                            btn.disabled = false;
                                            btn.textContent = 'ذخیره تنظیمات';
                                            msgEl.style.display = 'block';
                                            msgEl.style.background = 'rgba(239,68,68,0.12)';
                                            msgEl.style.color = 'var(--error)';
                                            msgEl.textContent = err.name === 'AbortError' ? 'درخواست تمام شد' : 'خطا در اتصال';
                                        });
                                }
                                function testEmail() {
                                    var email = document.getElementById('email_address').value;
                                    if (!email) { showToast('آدرس ایمیل را وارد کنید', 'error'); return; }
                                    var msgEl = document.getElementById('email-settings-msg');
                                    msgEl.style.display = 'none';
                                    var fd = new FormData();
                                    fd.append('action', 'ssp_test_email');
                                    fd.append('security', '<?php echo $nonce; ?>');
                                    fd.append('email_address', email);
                                    var ctrl = new AbortController();
                                    var tid = setTimeout(function() { ctrl.abort(); }, 30000);
                                    fetch('<?php echo admin_url('admin-ajax.php'); ?>', {method:'POST', body:fd, signal:ctrl.signal})
                                        .then(function(r){ clearTimeout(tid); var ct = r.headers.get('content-type') || ''; if (ct.indexOf('json') === -1) { return r.text().then(function(t){ throw new Error('Server returned non-JSON: ' + t.substring(0, 200)); }); } return r.json(); })
                                        .then(function(res){
                                            msgEl.style.display = 'block';
                                            if (res.success) {
                                                msgEl.style.background = 'rgba(16,185,129,0.12)';
                                                msgEl.style.color = 'var(--success)';
                                            } else {
                                                msgEl.style.background = 'rgba(239,68,68,0.12)';
                                                msgEl.style.color = 'var(--error)';
                                            }
                                            msgEl.textContent = res.data ? res.data.message : 'انجام شد';
                                        })
                                        .catch(function(err){
                                            clearTimeout(tid);
                                            msgEl.style.display = 'block';
                                            msgEl.style.background = 'rgba(239,68,68,0.12)';
                                            msgEl.style.color = 'var(--error)';
                                            msgEl.textContent = err.name === 'AbortError' ? 'درخواست تمام شد' : ('خطا: ' + (err.message || 'ارتباط با سرور برقرار نشد'));
                                        });
                                }
                                </script>
                            </div>
                        </div>

                    </div>
                </div>
            </div>
            <div id="ssp-toast" class="ssp-toast"><span class="toast-msg"></span></div>

            <!-- ============ EDIT MODELS ============ -->
            <div class="ssp-modal-overlay" id="modal_edit_messenger">
                <div class="ssp-modal">
                    <div class="ssp-modal-header">
                        <h3>ويرايش پیام‌رسان</h3>
                        <button type="button" class="ssp-modal-close" onclick="closeModal('modal_edit_messenger')">&times;</button>
                    </div>
                    <div class="ssp-modal-body">
                        <input type="hidden" id="edit_messenger_id">
                        <div class="ssp-form-group">
                            <label class="ssp-label">پلتفرم</label>
                            <select id="edit_messenger_platform" class="ssp-select">
                                <option value="telegram">تلگرام</option>
                                <option value="bale">بله</option>
                                <option value="eitaa">ایتا</option>
                                <option value="rubika">روبیکا</option>
                                <option value="instagram">اینستاگرام</option>
                                <option value="whatsapp">واتساپ Business</option>
                            </select>
                        </div>
                        <div class="ssp-form-group">
                            <label class="ssp-label">نام</label>
                            <input type="text" id="edit_messenger_name" class="ssp-input">
                        </div>
                        <div class="ssp-form-group">
                            <label class="ssp-label">توکن ربات</label>
                            <div class="ssp-input-group">
                                <input type="password" id="edit_messenger_token" class="ssp-input" dir="ltr">
                                <button type="button" class="ssp-eye-btn" onclick="togglePass('edit_messenger_token')"><svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg></button>
                            </div>
                        </div>
                        <div class="ssp-form-group">
                            <label class="ssp-label">Chat ID</label>
                            <input type="text" id="edit_messenger_channel" class="ssp-input" dir="ltr">
                        </div>
                        <div class="ssp-toggle" style="margin-top:0;">
                            <label>
                                <input type="checkbox" id="edit_messenger_active">
                                <span class="ssp-toggle-slider"></span>
                                <span>فعال</span>
                            </label>
                        </div>
                    </div>
                    <div class="ssp-modal-actions">
                        <button type="button" class="ssp-btn-primary" onclick="saveEditMessenger()"><span class="ssp-btn-text">ذخیره تغییرات</span><span class="ssp-btn-spinner"></span></button>
                        <button type="button" class="ssp-btn-secondary" onclick="closeModal('modal_edit_messenger')">انصراف</button>
                    </div>
                </div>
            </div>

            <div class="ssp-modal-overlay" id="modal_edit_wpsite">
                <div class="ssp-modal">
                    <div class="ssp-modal-header">
                        <h3>ويرايش سایت وردپرسی</h3>
                        <button type="button" class="ssp-modal-close" onclick="closeModal('modal_edit_wpsite')">&times;</button>
                    </div>
                    <div class="ssp-modal-body">
                        <input type="hidden" id="edit_wpsite_id">
                        <div class="ssp-form-group">
                            <label class="ssp-label">نام سایت</label>
                            <input type="text" id="edit_wpsite_name" class="ssp-input">
                        </div>
                        <div class="ssp-form-group">
                            <label class="ssp-label">آدرس سایت</label>
                            <input type="url" id="edit_wpsite_url" class="ssp-input" dir="ltr">
                        </div>
                        <div class="ssp-grid-2">
                            <div class="ssp-form-group">
                                <label class="ssp-label">نام کاربری</label>
                                <input type="text" id="edit_wpsite_user" class="ssp-input" dir="ltr">
                            </div>
                            <div class="ssp-form-group">
                                <label class="ssp-label">Application Password</label>
                                <div class="ssp-input-group">
                                    <input type="password" id="edit_wpsite_pass" class="ssp-input" dir="ltr" placeholder="خالی بگذارید تا تغییر نکند">
                                    <button type="button" class="ssp-eye-btn" onclick="togglePass('edit_wpsite_pass')"><svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg></button>
                                </div>
                            </div>
                        </div>
                        <div class="ssp-grid-2">
                            <div class="ssp-toggle" style="margin-top:0;">
                                <label>
                                    <input type="checkbox" id="edit_wpsite_active">
                                    <span class="ssp-toggle-slider"></span>
                                    <span>فعال</span>
                                </label>
                            </div>
                            <div class="ssp-toggle" style="margin-top:0;">
                                <label>
                                    <input type="checkbox" id="edit_wpsite_auto">
                                    <span class="ssp-toggle-slider"></span>
                                    <span>انتشار خودکار</span>
                                </label>
                            </div>
                        </div>
                        <div class="ssp-grid-2" style="margin-top:12px;">
                            <div class="ssp-form-group">
                                <label class="ssp-label">نوع محتوا</label>
                                <input type="text" id="edit_wpsite_post_type" class="ssp-input" value="post" placeholder="مثلاً: post, product, portfolio">
                                <small class="ssp-hint" style="display:block; margin-top:4px; color:#64748b;">نام پست‌تایپ را وارد کنید (مانند post برای نوشته، product برای محصول)</small>
                            </div>
                            <div class="ssp-form-group">
                                <label class="ssp-label">دسته‌بندی‌ها</label>
                                <input type="text" id="edit_wpsite_categories" class="ssp-input" placeholder="1,2,3">
                                <button type="button" class="ssp-btn-test" onclick="fetchCategories('edit')" style="margin-top:6px;">دریافت دسته‌بندی‌ها</button>
                                <div id="edit_cat_list" class="ssp-hint" style="margin-top:4px;"></div>
                            </div>
                        </div>
                    </div>
                    <div class="ssp-modal-actions">
                        <button type="button" class="ssp-btn-primary" onclick="saveEditWpSite()"><span class="ssp-btn-text">ذخیره تغییرات</span><span class="ssp-btn-spinner"></span></button>
                        <button type="button" class="ssp-btn-secondary" onclick="closeModal('modal_edit_wpsite')">انصراف</button>
                    </div>
                </div>
            </div>

            
            <!-- Bridge AI Modal -->
            <div class="ssp-modal-overlay" id="modal_ai_bridge">
                <div class="ssp-modal" style="max-width: 480px;">
                    <div class="ssp-modal-header" style="border-bottom:none; padding-bottom:0;">
                        <h3 style="display:flex; align-items:center; justify-content:space-between; width:100%;">
                            <span style="display:flex; align-items:center; gap:8px;">
                                <svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="var(--primary)" stroke-width="2"><path d="M12 2a10 10 0 1 0 10 10H12V2Z"/><path d="M12 12 21.1 2.9"/></svg>
                                ارتباط زنده با چت‌بات (افزونه مرورگر)
                            </span>
                            <span id="bridge_timer_badge" style="font-size:0.75rem; font-weight:normal; background:var(--bg-alt); padding:3px 10px; border-radius:12px; border:1px solid var(--border); color:var(--text-muted);">زمان: ۰ ثانیه</span>
                        </h3>
                        <button type="button" class="ssp-modal-close" onclick="closeModal('modal_ai_bridge'); window._bridgeActive = false;">&times;</button>
                    </div>
                    <div class="ssp-modal-body" style="padding-top:10px;">
                        <div style="background:var(--bg-alt); padding:16px; border-radius:12px; border:1px solid var(--border); margin-bottom:14px;">
                            <div style="display:flex; align-items:center; gap:12px; margin-bottom:12px;" id="bridge_step_1">
                                <div class="bridge-step-icon" style="width:24px; height:24px; border-radius:50%; background:var(--accent); color:#fff; display:flex; align-items:center; justify-content:center; font-size:12px;">1</div>
                                <div style="font-weight:600; color:var(--text);" id="bridge_step_1_text">مرحله ۱: ارسال پرامپت هوشمند به تب چت‌بات</div>
                            </div>
                            <div style="display:flex; align-items:center; gap:12px; margin-bottom:12px;" id="bridge_step_2">
                                <div class="bridge-step-icon" style="width:24px; height:24px; border-radius:50%; border:2px solid var(--border); color:var(--text-muted); display:flex; align-items:center; justify-content:center; font-size:12px;">2</div>
                                <div style="font-weight:600; color:var(--text-muted);" id="bridge_step_2_text">مرحله ۲: در حال پردازش و استخراج پاسخ توسط هوش مصنوعی</div>
                            </div>
                            <div style="display:flex; align-items:center; gap:12px;" id="bridge_step_3">
                                <div class="bridge-step-icon" style="width:24px; height:24px; border-radius:50%; border:2px solid var(--border); color:var(--text-muted); display:flex; align-items:center; justify-content:center; font-size:12px;">3</div>
                                <div style="font-weight:600; color:var(--text-muted);" id="bridge_step_3_text">مرحله ۳: تجزیه JSON و پر کردن خودکار فرم ابزار</div>
                            </div>
                        </div>

                        <div style="margin-bottom:14px;">
                            <div style="height:6px; background:var(--bg-alt); border-radius:3px; overflow:hidden;">
                                <div id="bridge_progress_bar" style="height:100%; width:20%; background:var(--accent); transition:width 0.3s ease;"></div>
                            </div>
                        </div>

                        <div id="bridge_error_box" style="display:none; background:var(--error-soft); color:var(--error); padding:12px; border-radius:8px; font-size:0.85rem; margin-bottom:14px; border:1px solid var(--error);">
                            <!-- Error message here -->
                        </div>

                        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:10px;">
                            <button type="button" class="ssp-btn-secondary" style="font-size:0.75rem; padding:4px 10px;" onclick="let lw = document.getElementById('bridge_log_wrap'); lw.style.display = (lw.style.display === 'none' ? 'block' : 'none');">
                                📊 کنسول عیب‌یابی و لاگ زنده
                            </button>
                            <button type="button" class="ssp-btn-secondary" style="font-size:0.75rem; padding:4px 10px;" onclick="if(window.openChatbotTab) window.openChatbotTab();">
                                🔗 باز کردن تب چت‌بات
                            </button>
                        </div>

                        <div id="bridge_log_wrap" style="display:none; background:#0f172a; color:#38bdf8; font-family:monospace; font-size:0.72rem; padding:10px; border-radius:8px; max-height:130px; overflow-y:auto; border:1px solid #334155; margin-bottom:12px;" dir="ltr">
                            <div id="bridge_log_content">Waiting for bridge activity...</div>
                        </div>

                        <div style="font-size:0.78rem; color:var(--text-muted); line-height:1.6; background:rgba(0,0,0,0.02); padding:8px 12px; border-radius:8px;">
                            💡 <strong>راهنما:</strong> مطمئن شوید افزونه <strong>ScriptCat</strong> یا <strong>Tampermonkey</strong> در مرورگر فعال است و آخرین نسخه اسکریپت (نسخه ۴.۵.۰) نصب شده باشد.
                        </div>
                    </div>
                    <div class="ssp-modal-actions" style="border-top:1px solid var(--border); padding-top:14px; margin-top:14px; display:flex; justify-content:flex-end; gap:8px;">
                        <button type="button" class="ssp-btn-secondary" onclick="closeModal('modal_ai_bridge'); window._bridgeActive = false;">بستن پنجره</button>
                        <button type="button" class="ssp-btn-primary" id="bridge_retry_btn" onclick="retryBridgeTask()" style="display:none;">تلاش مجدد و ارسال دوباره</button>
                    </div>
                </div>
            </div>

            <!-- RSS Preview Modal -->
            <div class="ssp-modal-overlay" id="modal_rss_preview">
                <div class="ssp-modal" style="max-width:700px; max-height:80vh;">
                    <div class="ssp-modal-header">
                        <h3>پیش‌نمایش محتوای دریافتی</h3>
                        <button type="button" class="ssp-modal-close" onclick="closeModal('modal_rss_preview')">&times;</button>
                    </div>
                    <div class="ssp-modal-body" style="overflow-y:auto; max-height:60vh;">
                        <div id="rss_preview_items"></div>
                    </div>
                    <div class="ssp-modal-actions" style="display:flex; gap:8px; flex-wrap:wrap;">
                        <button type="button" class="ssp-btn-primary" onclick="rssSendSelected()">ارسال انتخاب شده‌ها</button>
                        <button type="button" class="ssp-btn-secondary" onclick="rssSaveDrafts()">ذخیره به عنوان پیش‌نویس</button>
                        <button type="button" class="ssp-btn-secondary" onclick="rssScheduleItems()">زمان‌بندی ارسال</button>
                        <button type="button" class="ssp-btn-test" onclick="rssAiProcessSelected()">AI</button>
                        <button type="button" class="ssp-btn-secondary" onclick="closeModal('modal_rss_preview')">انصراف</button>
                    </div>
                </div>
            </div>

            <div class="ssp-modal-overlay" id="modal_edit_rss">
                <div class="ssp-modal">
                    <div class="ssp-modal-header">
                        <h3>ويرايش RSS Feed</h3>
                        <button type="button" class="ssp-modal-close" onclick="closeModal('modal_edit_rss')">&times;</button>
                    </div>
                    <div class="ssp-modal-body">
                        <input type="hidden" id="edit_rss_id">
                        <div class="ssp-form-group">
                            <label class="ssp-label">نام فید</label>
                            <input type="text" id="edit_rss_name" class="ssp-input">
                        </div>
                        <div class="ssp-form-group">
                            <label class="ssp-label">آدرس فید</label>
                            <input type="url" id="edit_rss_url" class="ssp-input" dir="ltr">
                        </div>
                        <div class="ssp-grid-2">
                            <div class="ssp-toggle" style="margin-top:0;">
                                <label>
                                    <input type="checkbox" id="edit_rss_active">
                                    <span class="ssp-toggle-slider"></span>
                                    <span>فعال</span>
                                </label>
                            </div>
                            <div class="ssp-toggle" style="margin-top:0;">
                                <label>
                                    <input type="checkbox" id="edit_rss_auto">
                                    <span class="ssp-toggle-slider"></span>
                                    <span>دریافت خودکار</span>
                                </label>
                            </div>
                        </div>
                        <!-- Content Cleaning & Extraction -->
                        <div style="margin-top:12px; padding:10px; background:var(--bg-alt); border-radius:8px;">
                            <h4 style="margin:0 0 8px; font-size:0.85rem;">پاکسازی و استخراج محتوا</h4>
                            <div class="ssp-grid-2">
                                <div class="ssp-toggle" style="margin-top:0;">
                                    <label><input type="checkbox" id="edit_rss_clean_ads"><span class="ssp-toggle-slider"></span><span>حذف تبلیغات</span></label>
                                </div>
                                <div class="ssp-toggle" style="margin-top:0;">
                                    <label><input type="checkbox" id="edit_rss_clean_urls"><span class="ssp-toggle-slider"></span><span>حذف لینک‌ها</span></label>
                                </div>
                            </div>
                            <div class="ssp-toggle" style="margin-top:8px;">
                                <label>
                                    <input type="checkbox" id="edit_rss_extract">
                                    <span class="ssp-toggle-slider"></span>
                                    <span>استخراج محتوای کامل</span>
                                </label>
                            </div>
                            <div class="ssp-grid-2" style="margin-top:8px;">
                                <div class="ssp-form-group" style="margin:0;">
                                    <label class="ssp-label">حداکثر طول</label>
                                    <select id="edit_rss_max_length" class="ssp-select">
                                        <option value="200">۲۰۰</option>
                                        <option value="500">۵۰۰</option>
                                        <option value="1000">۱۰۰۰</option>
                                        <option value="2000">۲۰۰۰</option>
                                    </select>
                                </div>
                                <div class="ssp-form-group" style="margin:0;">
                                    <label class="ssp-label">حالت محتوا</label>
                                    <select id="edit_rss_content_mode" class="ssp-select">
                                        <option value="summary">خلاصه</option>
                                        <option value="title_only">فقط عنوان</option>
                                        <option value="title_link">عنوان+لینک</option>
                                        <option value="full">متن کامل</option>
                                    </select>
                                </div>
                            </div>
                            <div class="ssp-form-group" style="margin-top:12px;">
                                <label class="ssp-label">قالب پیام سفارشی (اختیاری)</label>
                                <textarea id="edit_rss_message_template" class="ssp-textarea" rows="3" placeholder="{title}

{content}

منبع: {url}"></textarea>
                                <small class="ssp-hint">متغیرها: {title}, {content}, {url}, {excerpt}</small>
                            </div>
                        </div>
                    </div>
                    <div class="ssp-modal-actions">
                        <button type="button" class="ssp-btn-primary" onclick="saveEditRssFeed()"><span class="ssp-btn-text">ذخیره تغییرات</span><span class="ssp-btn-spinner"></span></button>
                        <button type="button" class="ssp-btn-secondary" onclick="closeModal('modal_edit_rss')">انصراف</button>
                    </div>
                </div>
            </div>

            <!-- Onboarding Wizard Modal -->
            <div class="ssp-modal-overlay" id="modal_onboarding">
                <div class="ssp-modal" style="max-width:600px;">
                    <div class="ssp-modal-header">
                        <h3>خوش آمدید! راهنمای شروع سریع</h3>
                        <button type="button" class="ssp-modal-close" onclick="skipOnboarding()">&times;</button>
                    </div>
                    <div class="ssp-modal-body">
                        <div class="ob-step active" id="ob-step-1">
                            <div style="text-align:center; margin-bottom:20px;">
                                <div style="font-size:3rem;"><svg viewBox="0 0 24 24" width="48" height="48" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg></div>
                                <h3 style="color:var(--text);">مرحله ۱: اتصال پیام‌رسان</h3>
                                <p style="color:var(--text-muted);">یک پیام‌رسان (تلگرام، بله و...) متصل کنید تا محتوا ارسال شود.</p>
                            </div>
                            <div style="text-align:center;">
                                <button type="button" class="ssp-btn-primary" onclick="skipOnboardingStep(1)">اتصال پیام‌رسان</button>
                                <button type="button" class="ssp-btn-secondary" onclick="skipOnboardingStep(1, true)" style="margin-top:8px;">رد شدن</button>
                            </div>
                        </div>
                        <div class="ob-step" id="ob-step-2" style="display:none;">
                            <div style="text-align:center; margin-bottom:20px;">
                                <div style="font-size:3rem;">⊡</div>
                                <h3 style="color:var(--text);">مرحله ۲: تنظیم هوش مصنوعی</h3>
                                <p style="color:var(--text-muted);">API Key هوش مصنوعی را وارد کنید یا حالت مرورگر را فعال کنید تا محتوا به صورت خودکار تولید شود.</p>
                            </div>
                            <div style="text-align:center;">
                                <button type="button" class="ssp-btn-primary" onclick="skipOnboardingStep(2)">تنظیم AI</button>
                                <button type="button" class="ssp-btn-secondary" onclick="skipOnboardingStep(2, true)" style="margin-top:8px;">رد شدن</button>
                            </div>
                        </div>
                        <div class="ob-step" id="ob-step-3" style="display:none;">
                            <div style="text-align:center; margin-bottom:20px;">
                                <div style="font-size:3rem;"><svg viewBox="0 0 24 24" width="48" height="48" fill="none" stroke="currentColor" stroke-width="2"><path d="M4.5 16.5c-1.5 1.26-2 5-2 5s3.74-.5 5-2l.5-.5c2.14-2.14 2.14-5.61 0-7.75l-1.5-1.5c-2.14-2.14-5.61-2.14-7.75 0l-.5.5z"/><path d="M12 15l2 2"/><path d="M15 12l2 2"/></svg></div>
                                <h3 style="color:var(--text);">مرحله ۳: اولین ارسال</h3>
                                <p style="color:var(--text-muted);">اولین پیام خود را ارسال کنید!</p>
                            </div>
                            <div style="text-align:center;">
                                <button type="button" class="ssp-btn-primary" onclick="skipOnboardingStep(3)">ارسال دستی</button>
                                <button type="button" class="ssp-btn-secondary" onclick="skipOnboarding()" style="margin-top:8px;">رد شدن</button>
                            </div>
                        </div>
                        <div class="ssp-step-indicator" style="margin-top:20px;">
                            <div class="ssp-step current" id="ob-ind-1"><span class="ssp-step-num">1</span></div>
                            <div class="ssp-step" id="ob-ind-2"><span class="ssp-step-num">2</span></div>
                            <div class="ssp-step" id="ob-ind-3"><span class="ssp-step-num">3</span></div>
                        </div>
                    </div>
                </div>
            </div>

        </div>

            <!-- Profile Management Modal -->
            <div class="ssp-modal-overlay" id="modal_profiles">
                <div class="ssp-modal" style="max-width:500px;">
                    <div class="ssp-modal-header">
                        <h3>مدیریت پروفایل‌ها</h3>
                        <button type="button" class="ssp-modal-close" onclick="closeModal('modal_profiles')">&times;</button>
                    </div>
                    <div class="ssp-modal-body">
                        <p style="color:var(--text-muted); font-size:0.85rem; margin-bottom:16px;">هر پروفایل یک کسب و کار مستقل است. پیام‌رسان‌ها، سایت‌ها و زمان‌بندی‌ها بر اساس پروفایل فیلتر می‌شوند.</p>
                        <div id="profiles_list">
                            <?php foreach ($profiles as $p) : ?>
                            <div style="display:flex; align-items:center; gap:10px; padding:10px; border:1px solid var(--border); border-radius:10px; margin-bottom:8px; <?php echo !empty($p['is_active']) ? 'border-color:var(--accent); background:var(--accent-soft);' : ''; ?>">
                                <div style="width:12px; height:12px; border-radius:50%; background:<?php echo esc_attr($p['color'] ?? '#4F46E5'); ?>; flex-shrink:0; border:1px solid var(--border);"></div>
                                <div style="flex:1;">
                                    <strong style="font-size:0.9rem; color:var(--text);"><?php echo esc_html($p['name']); ?></strong>
                                    <?php if (!empty($p['is_active'])) : ?><span class="ssp-badge success" style="font-size:0.7rem; margin-right:6px;">فعال</span><?php endif; ?>
                                </div>
                                <button type="button" class="ssp-btn-secondary" style="padding:4px 10px; font-size:0.75rem;" onclick="document.getElementById('profile_name').value='<?php echo esc_attr($p['name']); ?>';document.getElementById('profile_color').value='<?php echo esc_attr($p['color'] ?? '#4F46E5'); ?>';document.getElementById('profile_edit_id').value='<?php echo (int)$p['id']; ?>';">ویرایش</button>
                                <?php if ((int)$p['id'] !== 1) : ?>
                                <button type="button" class="ssp-btn-danger" style="padding:4px 10px; font-size:0.75rem;" onclick="deleteProfile(<?php echo (int)$p['id']; ?>)">حذف</button>
                                <?php endif; ?>
                            </div>
                            <?php endforeach; ?>
                        </div>
                        <div style="border-top:1px solid var(--border); padding-top:16px; margin-top:16px;">
                            <h4 style="font-size:0.9rem; margin-bottom:10px;">پروفایل جدید</h4>
                            <input type="hidden" id="profile_edit_id" value="">
                            <div style="display:flex; gap:8px; align-items:center;">
                                <input type="text" id="profile_name" class="ssp-input" placeholder="نام کسب و کار" style="flex:1;">
                                <input type="color" id="profile_color" value="#4F46E5" style="width:40px; height:38px; border:none; border-radius:8px; cursor:pointer;">
                                <button type="button" class="ssp-btn-primary" onclick="saveProfile()">ذخیره</button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Prompt Builder Modal -->
            <div class="ssp-modal-overlay" id="modal_prompt_builder">
                <div class="ssp-modal" style="max-width:700px;">
                    <div class="ssp-modal-header">
                        <h3>پرامپت‌ساز هوشمند</h3>
                        <button type="button" class="ssp-modal-close" onclick="closeModal('modal_prompt_builder')">&times;</button>
                    </div>
                    <div class="ssp-modal-body" style="max-height:70vh; overflow-y:auto;">
                        <p style="color:var(--text-muted); font-size:0.85rem; margin-bottom:16px;">قالب پرامپت سفارشی بسازید. با پر کردن فیلدهای زیر، پرامپت به صورت خودکار ساخته شده و خروجی JSON استاندارد برمی‌گرداند.</p>
                        <input type="hidden" id="pb_edit_id" value="">
                        <div class="ssp-form-group">
                            <label class="ssp-label">نام قالب *</label>
                            <input type="text" id="pb_name" class="ssp-input" placeholder="مثال: قطعات موتورسیکلت">
                        </div>
                        <div class="ssp-grid-2">
                            <div class="ssp-form-group">
                                <label class="ssp-label">نوع قالب</label>
                                <select id="pb_type" class="ssp-select">
                                    <option value="product">محصول (WooCommerce)</option>
                                    <option value="article">مقاله (نوشته/برگه)</option>
                                </select>
                            </div>
                            <div class="ssp-form-group">
                                <label class="ssp-label">حوزه / صنعت</label>
                                <input type="text" id="pb_industry" class="ssp-input" placeholder="مثال: لوازم یدکی موتورسیکلت">
                            </div>
                        </div>
                        <div class="ssp-grid-2">
                            <div class="ssp-form-group">
                                <label class="ssp-label">لحن محتوا</label>
                                <input type="text" id="pb_tone" class="ssp-input" placeholder="مثال: حرفه‌ای و تخصصی">
                            </div>
                            <div class="ssp-form-group" style="visibility:hidden;">
                                <label class="ssp-label">-</label>
                            </div>
                        </div>
                        <div class="ssp-form-group">
                            <label class="ssp-label">قوانین کلی</label>
                            <textarea id="pb_general_rules" class="ssp-textarea" rows="3" placeholder="مثال: فقط اطلاعات واقعی از عنوان • بدون اختراع عدد"></textarea>
                        </div>
                        <div class="ssp-form-group">
                            <label class="ssp-label">قوانین توضیحات کوتاه (short_description)</label>
                            <textarea id="pb_short_rules" class="ssp-textarea" rows="3" placeholder="مثال: ۳-۴ جمله، کاربرد و مزیت کلیدی. لیست حداقل ۴ ردیف"></textarea>
                        </div>
                        <div class="ssp-form-group">
                            <label class="ssp-label">قوانین توضیحات بلند (description)</label>
                            <textarea id="pb_long_rules" class="ssp-textarea" rows="3" placeholder="مثال: حداقل ۶۰۰ کلمه، حداقل ۳ h3، تکرار کلمه کلیدی ۵-۱۰ بار"></textarea>
                        </div>
                        <div class="ssp-form-group">
                            <label class="ssp-label">قوانین سئو (meta)</label>
                            <textarea id="pb_seo_rules" class="ssp-textarea" rows="2" placeholder="مثال: تیتر ≤۶۰ کاراکتر، متا ≤۱۷۰ کاراکتر، شامل برند و مزیت"></textarea>
                        </div>
                        <div class="ssp-grid-2">
                            <div class="ssp-form-group">
                                <label class="ssp-label">تمرکز توضیحات</label>
                                <input type="text" id="pb_focus" class="ssp-input" placeholder="مزایا، دوام، ارزش خرید">
                            </div>
                            <div class="ssp-form-group">
                                <label class="ssp-label">ممنوعیات</label>
                                <input type="text" id="pb_forbidden" class="ssp-input" placeholder="بدون احوالپرسی، بدون آموزش نصب">
                            </div>
                        </div>
                        <div class="ssp-form-group">
                            <label class="ssp-label">توضیحات اضافه / زمینه</label>
                            <textarea id="pb_extra" class="ssp-textarea" rows="2" placeholder="زمینه و اطلاعات پس‌زمینه حوزه کاری"></textarea>
                        </div>
                        <div class="ssp-toggle" style="margin-bottom:16px;">
                            <label>
                                <input type="checkbox" id="pb_is_default">
                                <span class="ssp-toggle-slider"></span>
                                <span>قالب پیش‌فرض</span>
                            </label>
                        </div>
                        <div style="display:flex; gap:10px; flex-wrap:wrap;">
                            <button type="button" class="ssp-btn-primary" onclick="savePromptTemplate()">ذخیره قالب</button>
                            <button type="button" class="ssp-btn-secondary" onclick="previewPromptTemplate()">پیش‌نمایش پرامپت</button>
                            <button type="button" class="ssp-btn-secondary" onclick="closeModal('modal_prompt_builder')">بستن</button>
                        </div>
                        <div id="pb_preview" style="display:none; margin-top:16px; padding:12px; background:var(--bg-alt); border:1px solid var(--border); border-radius:8px; white-space:pre-wrap; font-size:0.8rem; max-height:300px; overflow-y:auto;"></div>
                        <div style="margin-top:20px; border-top:1px solid var(--border); padding-top:16px;">
                            <h4 style="font-size:0.9rem; margin-bottom:10px;">قالب‌های ذخیره شده</h4>
                            <div id="pb_templates_list"></div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Template Create/Edit Modal -->
            <div class="ssp-modal-overlay" id="modal_template_edit">
                <div class="ssp-modal" style="max-width:560px;">
                    <div class="ssp-modal-header">
                        <h3 id="tpl_modal_title">قالب جدید</h3>
                        <button type="button" class="ssp-modal-close" onclick="closeTemplateModal()">&times;</button>
                    </div>
                    <div class="ssp-modal-body">
                        <input type="hidden" id="tpl_edit_id" value="">
                        <div class="ssp-grid-2">
                            <div class="ssp-form-group">
                                <label class="ssp-label">نام قالب *</label>
                                <input type="text" id="tpl_edit_name" class="ssp-input" placeholder="مثلاً: خبر فناوری">
                            </div>
                            <div class="ssp-form-group">
                                <label class="ssp-label">دسته‌بندی</label>
                                <select id="tpl_edit_category" class="ssp-select">
                                    <option value="general">عمومی</option>
                                    <option value="news">اخبار</option>
                                    <option value="promotion">تبلیغات</option>
                                    <option value="educational">آموزشی</option>
                                </select>
                            </div>
                        </div>
                        <div class="ssp-form-group">
                            <label class="ssp-label">محتوای قالب *</label>
                            <textarea id="tpl_edit_content" rows="5" class="ssp-textarea" placeholder="متن قالب را اینجا بنویسید...&#10;&#10;مثال:&#10; {title}&#10;&#10;{message}&#10;&#10;{link}&#10;&#10;{hashtags}&#10;&#10;{signature}"></textarea>
                            <p class="ssp-hint">متغیرها: <code>{title}</code> <code>{message}</code> <code>{link}</code> <code>{hashtags}</code> <code>{signature}</code></p>
                        </div>
                        <div class="ssp-grid-2">
                            <div class="ssp-form-group">
                                <label class="ssp-label">هشتگ‌های این قالب</label>
                                <input type="text" id="tpl_edit_hashtags" class="ssp-input" placeholder="#فناوری #اخبار">
                                <p class="ssp-hint">هشتگ‌های اختصاصی این قالب</p>
                            </div>
                            <div class="ssp-form-group">
                                <label class="ssp-label">امضای این قالب</label>
                                <input type="text" id="tpl_edit_signature" class="ssp-input" placeholder="کانال: @mychannel | سایت: example.com">
                                <p class="ssp-hint">آیدی کانال، سایت، شماره و...</p>
                            </div>
                        </div>
                        <div style="display:flex; gap:10px; justify-content:flex-end; margin-top:20px; padding-top:16px; border-top:1px solid var(--border);">
                            <button type="button" class="ssp-btn-secondary" onclick="closeTemplateModal()">انصراف</button>
                            <button type="button" class="ssp-btn-primary" onclick="saveTemplateFromModal()" id="tpl_modal_save_btn">
                                <span class="ssp-btn-text">ذخیره قالب</span>
                                <span class="ssp-btn-spinner"></span>
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- ============ CALENDAR DAY DETAIL MODAL ============ -->
            <div class="ssp-modal-overlay" id="modal_calendar_day" style="display:none;" onclick="if(event.target===this)closeCalendarDayModal();">
                <div class="ssp-modal-box" style="max-width:600px; width:95%; max-height:85vh; display:flex; flex-direction:column; overflow:hidden;">
                    <div class="ssp-modal-header" style="display:flex; justify-content:space-between; align-items:center; padding:16px 20px; border-bottom:1px solid var(--border);">
                        <h3 id="cal_day_modal_title" style="margin:0; font-size:1.05rem; font-weight:700; color:var(--text);">برنامه‌های روز</h3>
                        <button type="button" class="ssp-btn-secondary" onclick="closeCalendarDayModal()" style="padding:4px 8px; font-size:1.1rem; line-height:1; border-radius:6px;">&times;</button>
                    </div>
                    <div class="ssp-modal-body" style="padding:20px; overflow-y:auto; flex:1;">
                        <div id="cal_day_modal_list">
                            <!-- Populated via JS -->
                        </div>
                    </div>
                    <div class="ssp-modal-footer" style="padding:14px 20px; border-top:1px solid var(--border); display:flex; justify-content:space-between; align-items:center; background:var(--bg-alt);">
                        <button type="button" class="ssp-btn-secondary" onclick="closeCalendarDayModal()">بستن</button>
                        <button type="button" class="ssp-btn-primary" id="cal_day_add_btn">
                            <span>+ افزودن زمان‌بندی جدید برای این روز</span>
                        </button>
                    </div>
                </div>
            </div>

            <!-- ============ EDIT SCHEDULE MODAL ============ -->
            <div class="ssp-modal-overlay" id="modal_edit_schedule" style="display:none;" onclick="if(event.target===this)closeEditScheduleModal();">
                <div class="ssp-modal-box" style="max-width:540px; width:95%; max-height:90vh; overflow-y:auto;">
                    <div class="ssp-modal-header" style="display:flex; justify-content:space-between; align-items:center; padding:16px 20px; border-bottom:1px solid var(--border);">
                        <h3 style="margin:0; font-size:1.05rem; font-weight:700; color:var(--text);">✏️ ویرایش زمان‌بندی ارسال</h3>
                        <button type="button" class="ssp-btn-secondary" onclick="closeEditScheduleModal()" style="padding:4px 8px; font-size:1.1rem; line-height:1; border-radius:6px;">&times;</button>
                    </div>
                    <div class="ssp-modal-body" style="padding:20px;">
                        <input type="hidden" id="editsch_id">
                        
                        <div class="ssp-form-group" style="margin-bottom:16px;">
                            <label class="ssp-label">عنوان زمان‌بندی *</label>
                            <input type="text" id="editsch_title" class="ssp-input" placeholder="عنوان پیام یا پست">
                        </div>

                        <div class="ssp-form-group" style="margin-bottom:16px;">
                            <label class="ssp-label">متن محتوا</label>
                            <textarea id="editsch_message" rows="5" class="ssp-textarea" placeholder="متن پیام زمان‌بندی شده..."></textarea>
                        </div>

                        <div class="ssp-grid-2" style="margin-bottom:16px;">
                            <div class="ssp-form-group">
                                <label class="ssp-label">تاریخ و ساعت ارسال (شمسی) *</label>
                                <input type="text" id="editsch_datetime_display" class="ssp-input" readonly placeholder="انتخاب تاریخ و ساعت" style="cursor:pointer;" onclick="editschOpenJalaliPicker()">
                                <input type="hidden" id="editsch_datetime">
                                <div id="editsch_jalali_picker" style="display:none; margin-top:8px; padding:12px; background:var(--card); border:1px solid var(--border); border-radius:8px;"></div>
                            </div>
                            <div class="ssp-form-group">
                                <label class="ssp-label">تکرار خودکار</label>
                                <select id="editsch_recurring" class="ssp-select">
                                    <option value="">بدون تکرار (یکباره)</option>
                                    <option value="daily">روزانه</option>
                                    <option value="weekly">هفتگی</option>
                                    <option value="monthly">ماهانه</option>
                                </select>
                            </div>
                        </div>

                        <div class="ssp-form-group" style="margin-bottom:16px;">
                            <label class="ssp-label">وضعیت زمان‌بندی</label>
                            <select id="editsch_status" class="ssp-select">
                                <option value="pending">در انتظار ارسال (فعال)</option>
                                <option value="completed">انجام شده (ارسال موفق)</option>
                                <option value="cancelled">لغو شده</option>
                            </select>
                        </div>
                    </div>
                    <div class="ssp-modal-footer" style="padding:14px 20px; border-top:1px solid var(--border); display:flex; justify-content:flex-end; gap:10px; background:var(--bg-alt);">
                        <button type="button" class="ssp-btn-secondary" onclick="closeEditScheduleModal()">انصراف</button>
                        <button type="button" class="ssp-btn-primary" id="editsch_save_btn" onclick="saveScheduleEdit()">
                            <span class="ssp-btn-text">ذخیره تغییرات</span>
                            <span class="ssp-btn-spinner"></span>
                        </button>
                    </div>
                </div>
            </div>

            <!-- ============ EDIT DRAFT MODAL ============ -->
            <div class="ssp-modal-overlay" id="modal_edit_draft" style="display:none;" onclick="if(event.target===this)closeEditDraftModal();">
                <div class="ssp-modal-box" style="max-width:540px; width:95%; max-height:90vh; overflow-y:auto;">
                    <div class="ssp-modal-header" style="display:flex; justify-content:space-between; align-items:center; padding:16px 20px; border-bottom:1px solid var(--border);">
                        <h3 style="margin:0; font-size:1.05rem; font-weight:700; color:var(--text);">✏️ ویرایش پیش‌نویس</h3>
                        <button type="button" class="ssp-btn-secondary" onclick="closeEditDraftModal()" style="padding:4px 8px; font-size:1.1rem; line-height:1; border-radius:6px;">&times;</button>
                    </div>
                    <div class="ssp-modal-body" style="padding:20px;">
                        <input type="hidden" id="editdraft_id">
                        
                        <div class="ssp-form-group" style="margin-bottom:16px;">
                            <label class="ssp-label">عنوان پیش‌نویس</label>
                            <input type="text" id="editdraft_title" class="ssp-input" placeholder="عنوان پیش‌نویس...">
                        </div>

                        <div class="ssp-form-group" style="margin-bottom:16px;">
                            <label class="ssp-label">محتوای پیش‌نویس *</label>
                            <textarea id="editdraft_content" rows="7" class="ssp-textarea" placeholder="متن پیام یا پست..."></textarea>
                        </div>
                    </div>
                    <div class="ssp-modal-footer" style="padding:14px 20px; border-top:1px solid var(--border); display:flex; justify-content:flex-end; gap:10px; background:var(--bg-alt);">
                        <button type="button" class="ssp-btn-secondary" onclick="closeEditDraftModal()">انصراف</button>
                        <button type="button" class="ssp-btn-primary" id="editdraft_save_btn" onclick="saveDraftEdit()">
                            <span class="ssp-btn-text">ذخیره پیش‌نویس</span>
                            <span class="ssp-btn-spinner"></span>
                        </button>
                    </div>
                </div>
            </div>

            <!-- ============ SCHEDULE DRAFT MODAL ============ -->
            <div class="ssp-modal-overlay" id="modal_schedule_draft" style="display:none;" onclick="if(event.target===this)closeScheduleDraftModal();">
                <div class="ssp-modal-box" style="max-width:520px; width:95%; max-height:90vh; overflow-y:auto;">
                    <div class="ssp-modal-header" style="display:flex; justify-content:space-between; align-items:center; padding:16px 20px; border-bottom:1px solid var(--border);">
                        <h3 style="margin:0; font-size:1.05rem; font-weight:700; color:var(--text);">📅 زمان‌بندی ارسال پیش‌نویس</h3>
                        <button type="button" class="ssp-btn-secondary" onclick="closeScheduleDraftModal()" style="padding:4px 8px; font-size:1.1rem; line-height:1; border-radius:6px;">&times;</button>
                    </div>
                    <div class="ssp-modal-body" style="padding:20px;">
                        <input type="hidden" id="schdraft_id">
                        
                        <div style="background:var(--bg-alt); padding:12px 14px; border-radius:10px; border:1px solid var(--border); margin-bottom:16px;">
                            <div style="font-size:0.8rem; color:var(--text-muted); margin-bottom:4px;">پیش‌نویس انتخابی:</div>
                            <div id="schdraft_title_display" style="font-weight:700; color:var(--text);"></div>
                        </div>

                        <div class="ssp-form-group" style="margin-bottom:16px;">
                            <label class="ssp-label">تاریخ و ساعت ارسال (شمسی) *</label>
                            <input type="text" id="schdraft_datetime_display" class="ssp-input" readonly placeholder="انتخاب تاریخ و ساعت" style="cursor:pointer;" onclick="schdraftOpenJalaliPicker()">
                            <input type="hidden" id="schdraft_datetime">
                            <div id="schdraft_jalali_picker" style="display:none; margin-top:8px; padding:12px; background:var(--card); border:1px solid var(--border); border-radius:8px;"></div>
                        </div>

                        <div class="ssp-form-group" style="margin-bottom:16px;">
                            <label class="ssp-label">تکرار خودکار</label>
                            <select id="schdraft_recurring" class="ssp-select">
                                <option value="">بدون تکرار (یکباره)</option>
                                <option value="daily">روزانه</option>
                                <option value="weekly">هفتگی</option>
                                <option value="monthly">ماهانه</option>
                            </select>
                        </div>
                    </div>
                    <div class="ssp-modal-footer" style="padding:14px 20px; border-top:1px solid var(--border); display:flex; justify-content:flex-end; gap:10px; background:var(--bg-alt);">
                        <button type="button" class="ssp-btn-secondary" onclick="closeScheduleDraftModal()">انصراف</button>
                        <button type="button" class="ssp-btn-primary" id="schdraft_confirm_btn" onclick="confirmScheduleDraft()">
                            <span class="ssp-btn-text">ثبت در تقویم محتوا</span>
                            <span class="ssp-btn-spinner"></span>
                        </button>
                    </div>
                </div>
            </div>

        <!-- Mobile Drawer (outside ssp-wrap for proper z-index stacking) -->
        <div class="ssp-drawer-overlay" id="sspDrawerOverlay" onclick="closeDrawer()"></div>
        <div class="ssp-drawer" id="sspDrawer">
            <div class="ssp-drawer-header">
                <div class="ssp-drawer-logo"><svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2"><polygon points="13 2 3 14 12 14 11 22 21 10 12 10 13 2"/></svg></div>
                <div class="ssp-drawer-title">منوی پورتال</div>
                <button class="ssp-drawer-close" onclick="closeDrawer()">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
                </button>
            </div>
            <div class="ssp-drawer-body">
                <div class="ssp-drawer-group">
                    <div class="ssp-drawer-group-title">نمای کلی</div>
                    <button class="ssp-drawer-item active" onclick="drawerSelect('dashboard', this)" data-tab="dashboard">
                        <svg viewBox="0 0 24 24"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/></svg>
                        <span>داشبورد</span>
                    </button>
                </div>
                <div class="ssp-drawer-group">
                    <div class="ssp-drawer-group-title">پیام‌رسان‌ها</div>
                    <button class="ssp-drawer-item" onclick="drawerSelect('messengers', this)" data-tab="messengers">
                        <svg viewBox="0 0 24 24"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>
                        <span>مدیریت پیام‌رسان‌ها</span>
                    </button>
                    <button class="ssp-drawer-item" onclick="drawerSelect('botbuilder', this)" data-tab="botbuilder">
                        <svg viewBox="0 0 24 24"><rect x="3" y="11" width="18" height="10" rx="2"/><circle cx="12" cy="5" r="2"/><path d="M12 7v4"/><line x1="8" y1="16" x2="8" y2="16"/><line x1="16" y1="16" x2="16" y2="16"/></svg>
                        <span>ساخت بات</span>
                    </button>
                    <button class="ssp-drawer-item" onclick="drawerSelect('manual', this)" data-tab="manual">
                        <svg viewBox="0 0 24 24"><line x1="22" y1="2" x2="11" y2="13"/><polygon points="22 2 15 22 11 13 2 9 22 2"/></svg>
                        <span>ارسال پیام</span>
                    </button>
                    <button class="ssp-drawer-item" onclick="drawerSelect('drafts', this)" data-tab="drafts">
                        <svg viewBox="0 0 24 24"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/></svg>
                        <span>پیش‌نویس‌ها</span>
                    </button>
                    <button class="ssp-drawer-item" onclick="drawerSelect('template', this)" data-tab="template">
                        <svg viewBox="0 0 24 24"><rect x="3" y="3" width="18" height="18" rx="2" ry="2"/><line x1="3" y1="9" x2="21" y2="9"/><line x1="9" y1="21" x2="9" y2="9"/></svg>
                        <span>قالب‌ها</span>
                    </button>
                </div>
                <div class="ssp-drawer-group">
                    <div class="ssp-drawer-group-title">تولید محتوا</div>
                    <button class="ssp-drawer-item" onclick="drawerSelect('generate', this)" data-tab="generate">
                        <svg viewBox="0 0 24 24"><rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/></svg>
                        <span>مرکز تولید محتوا</span>
                    </button>
                    <button class="ssp-drawer-item" onclick="drawerSelect('brainstorm', this)" data-tab="brainstorm">
                        <svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><path d="M9.09 9a3 3 0 0 1 5.83 1c0 2-3 3-3 3"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
                        <span>ایده‌یابی</span>
                    </button>
                    <button class="ssp-drawer-item" onclick="drawerSelect('postgen', this)" data-tab="postgen">
                        <svg viewBox="0 0 24 24"><polygon points="13 2 3 14 12 14 11 22 21 10 12 10 13 2"/></svg>
                        <span>تولید پست</span>
                    </button>
                    <button class="ssp-drawer-item" onclick="drawerSelect('contentgen', this)" data-tab="contentgen">
                        <svg viewBox="0 0 24 24"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/></svg>
                        <span>تولید مقاله</span>
                    </button>
                    <button class="ssp-drawer-item" onclick="drawerSelect('productgen', this)" data-tab="productgen">
                        <svg viewBox="0 0 24 24"><path d="M6 2L3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4z"/><line x1="3" y1="6" x2="21" y2="6"/><path d="M16 10a4 4 0 0 1-8 0"/></svg>
                        <span>تولید محصول</span>
                    </button>
                    <button class="ssp-drawer-item" onclick="drawerSelect('promptbuilder', this)" data-tab="promptbuilder">
                        <svg viewBox="0 0 24 24"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/><line x1="10" y1="9" x2="8" y2="9"/></svg>
                        <span>قالب پرامپت</span>
                    </button>
                </div>
                <div class="ssp-drawer-group">
                    <div class="ssp-drawer-group-title">زمان‌بندی</div>
                    <button class="ssp-drawer-item" onclick="drawerSelect('calendar', this)" data-tab="calendar">
                        <svg viewBox="0 0 24 24"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
                        <span>تقویم محتوا</span>
                    </button>
                    <button class="ssp-drawer-item" onclick="drawerSelect('schedules', this)" data-tab="schedules">
                        <svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
                        <span>زمان‌بندی ساده</span>
                    </button>
                </div>
                <div class="ssp-drawer-group">
                    <div class="ssp-drawer-group-title">اتوماسیون و گزارش</div>
                    <button class="ssp-drawer-item" onclick="drawerSelect('reports', this)" data-tab="reports">
                        <svg viewBox="0 0 24 24"><line x1="18" y1="20" x2="18" y2="10"/><line x1="12" y1="20" x2="12" y2="4"/><line x1="6" y1="20" x2="6" y2="14"/></svg>
                        <span>گزارشات</span>
                    </button>
                    <button class="ssp-drawer-item" onclick="drawerSelect('distribution', this)" data-tab="distribution">
                        <svg viewBox="0 0 24 24"><circle cx="18" cy="5" r="3"/><circle cx="6" cy="12" r="3"/><circle cx="18" cy="19" r="3"/><line x1="8.59" y1="13.51" x2="15.42" y2="17.49"/><line x1="15.41" y1="6.51" x2="8.59" y2="10.49"/></svg>
                        <span>توزیع خودکار</span>
                    </button>
                </div>
                <div class="ssp-drawer-group">
                    <div class="ssp-drawer-group-title">ابزارها</div>
                    <button class="ssp-drawer-item" onclick="drawerSelect('seo', this)" data-tab="seo">
                        <svg viewBox="0 0 24 24"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
                        <span>ابزار SEO</span>
                    </button>
                    <button class="ssp-drawer-item" onclick="drawerSelect('links', this)" data-tab="links">
                        <svg viewBox="0 0 24 24"><path d="M10 13a5 5 0 0 0 7.54.54l3-3a5 5 0 0 0-7.07-7.07l-1.72 1.71"/><path d="M14 11a5 5 0 0 0-7.54-.54l-3 3a5 5 0 0 0 7.07 7.07l1.71-1.71"/></svg>
                        <span>کوتاه‌کننده لینک</span>
                    </button>
                </div>
                <div class="ssp-drawer-group">
                    <div class="ssp-drawer-group-title">تنظیمات</div>
                    <button class="ssp-drawer-item" onclick="drawerSelect('sources', this)" data-tab="sources">
                        <svg viewBox="0 0 24 24"><path d="M4 11a9 9 0 0 1 9 9"/><path d="M4 4a16 16 0 0 1 16 16"/><circle cx="5" cy="19" r="1"/></svg>
                        <span>RSS Feeds</span>
                    </button>
                    <button class="ssp-drawer-item" onclick="drawerSelect('wpsources', this)" data-tab="wpsources">
                        <svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><line x1="2" y1="12" x2="22" y2="12"/><path d="M12 2a15.3 15.3 0 0 1 4 10 15.3 15.3 0 0 1-4 10 15.3 15.3 0 0 1-4-10 15.3 15.3 0 0 1 4-10z"/></svg>
                        <span>منابع وردپرس</span>
                    </button>
                    <button class="ssp-drawer-item" onclick="drawerSelect('ai', this)" data-tab="ai">
                        <svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1 0 2.83 2 2 0 0 1-2.83 0l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-2 2 2 2 0 0 1-2-2v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83 0 2 2 0 0 1 0-2.83l.06-.06A1.65 1.65 0 0 0 4.68 15a1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1-2-2 2 2 0 0 1 2-2h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 0-2.83 2 2 0 0 1 2.83 0l.06.06A1.65 1.65 0 0 0 9 4.68a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 2-2 2 2 0 0 1 2 2v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 0 2 2 0 0 1 0 2.83l-.06.06A1.65 1.65 0 0 0 19.4 9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 2 2 2 2 0 0 1-2 2h-.09a1.65 1.65 0 0 0-1.51 1z"/></svg>
                        <span>تنظیمات AI</span>
                    </button>
                    <button class="ssp-drawer-item" onclick="drawerSelect('subscription', this)" data-tab="subscription">
                        <svg viewBox="0 0 24 24"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>
                        <span>اشتراک</span>
                    </button>
                </div>
            </div>
        </div>

        <!-- Floating Menu Button (FAB) -->
        <button class="ssp-fab" id="sspFab" onclick="toggleDrawer()" title="منو">
            <svg class="ssp-fab-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><line x1="3" y1="6" x2="21" y2="6"/><line x1="3" y1="12" x2="21" y2="12"/><line x1="3" y1="18" x2="21" y2="18"/></svg>
            <svg class="ssp-fab-icon-close" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
        </button>

        <script>
        window.ajaxurl = '<?php echo admin_url('admin-ajax.php'); ?>';
        window.nonce = '<?php echo $nonce; ?>';
        window.userPlan = '<?php echo $plan; ?>';
        window.sspSiteUrl = '<?php echo esc_url(home_url()); ?>';
        window.isImpersonating = <?php echo $is_impersonating ? 'true' : 'false'; ?>;
        var ajaxurl = window.ajaxurl;
        var nonce = window.nonce;
        var userPlan = window.userPlan;
        var sspSiteUrl = window.sspSiteUrl;
        var isImpersonating = window.isImpersonating;

        (function() {
            // Helper to add impersonate param to FormData (global for all IIFEs)
            window.addImpersonate = function(fd) {
                if (isImpersonating) fd.append('impersonate', '1');
            };

            // Store entity data in JS (no raw tokens in HTML attributes)
            window.messengerData = messengerData = <?php echo $messenger_json; ?>;
            window.wpSiteData = wpSiteData = <?php echo $wp_sites_json; ?>;
            window.rssFeedData = rssFeedData = <?php echo $rss_feeds_json; ?>;
            window.profileData = profileData = <?php echo json_encode($profiles); ?>;
            window.schedulesData = schedulesData = <?php echo json_encode($schedules); ?>;
            window.draftsData = draftsData = <?php echo json_encode($drafts); ?>;
            window.distributionsData = distributionsData = <?php echo json_encode($distributions ?? []); ?>;
            window.templatesData = templatesData = <?php echo json_encode($template_items); ?>;
            window.logsData = logsData = <?php echo json_encode($user_logs); ?>;
            window.activeProfileId = activeProfileId = <?php echo (int)$active_profile_id; ?>;
            window._defaultTemplateId = <?php echo (int) get_user_meta($user_id, 'ssp_default_template_id', true); ?>;

            // Profile switching
            window.switchProfile = function(profileId) {
                var fd = new FormData();
                fd.append('action', 'ssp_switch_profile');
                fd.append('security', nonce);
                fd.append('profile_id', profileId);
                fetch(ajaxurl, {method: 'POST', body: fd})
                    .then(function(r) { return r.json(); })
                    .then(function(res) {
                        if (res.success) location.reload();
                        else showToast(res.data ? res.data.message : 'خطا', 'error');
                    })
                    .catch(function() { showToast('خطا در اتصال', 'error'); });
            };

            window.openProfileModal = function() {
                var modal = document.getElementById('modal_profiles');
                if (modal) modal.classList.add('active');
            };

            window.saveProfile = function() {
                var name = document.getElementById('profile_name').value.trim();
                if (!name) { showToast('نام پروفایل را وارد کنید', 'error'); return; }
                var editId = document.getElementById('profile_edit_id').value;
                var fd = new FormData();
                fd.append('action', editId ? 'ssp_update_profile' : 'ssp_add_profile');
                fd.append('security', nonce);
                fd.append('name', name);
                fd.append('color', document.getElementById('profile_color').value);
                if (editId) fd.append('profile_id', editId);
                fetch(ajaxurl, {method: 'POST', body: fd})
                    .then(function(r) { return r.json(); })
                    .then(function(res) {
                        if (res.success) location.reload();
                        else showToast(res.data ? res.data.message : 'خطا', 'error');
                    })
                    .catch(function() { showToast('خطا در اتصال', 'error'); });
            };

            window.deleteProfile = function(id) {
                if (!confirm('آیا از حذف این پروفایل مطمئن هستید؟')) return;
                var fd = new FormData();
                fd.append('action', 'ssp_delete_profile');
                fd.append('security', nonce);
                fd.append('profile_id', id);
                fetch(ajaxurl, {method: 'POST', body: fd})
                    .then(function(r) { return r.json(); })
                    .then(function(res) {
                        if (res.success) location.reload();
                        else showToast(res.data ? res.data.message : 'خطا', 'error');
                    })
                    .catch(function() { showToast('خطا در اتصال', 'error'); });
            };

            // ===== Theme Toggle =====
            window.toggleTheme = function() {
                var wrap = document.querySelector('.ssp-wrap');
                var html = document.documentElement, body = document.body;
                var toDark = wrap.classList.contains('ssp-theme-light');
                wrap.classList.toggle('ssp-theme-light', !toDark);
                wrap.classList.toggle('ssp-theme-dark', toDark);
                html.classList.toggle('ssp-theme-light', !toDark);
                html.classList.toggle('ssp-theme-dark', toDark);
                body.classList.toggle('ssp-theme-light', !toDark);
                body.classList.toggle('ssp-theme-dark', toDark);
                localStorage.setItem('ssp_theme', toDark ? 'dark' : 'light');
                showToast(toDark ? 'تم تاریک فعال شد' : 'تم روشن فعال شد', 'success');
            };

            // ===== Mobile Drawer =====
            window.toggleDrawer = function() {
                var overlay = document.getElementById('sspDrawerOverlay');
                var drawer = document.getElementById('sspDrawer');
                var fab = document.getElementById('sspFab');
                var isActive = overlay.classList.contains('active');
                if (isActive) {
                    closeDrawer();
                } else {
                    overlay.classList.add('active');
                    drawer.classList.add('active');
                    if (fab) fab.classList.add('open');
                    document.body.style.overflow = 'hidden';
                }
            };
            window.closeDrawer = function() {
                var overlay = document.getElementById('sspDrawerOverlay');
                var drawer = document.getElementById('sspDrawer');
                var fab = document.getElementById('sspFab');
                overlay.classList.remove('active');
                drawer.classList.remove('active');
                if (fab) fab.classList.remove('open');
                document.body.style.overflow = '';
            };
            window.drawerSelect = function(tabId, drawerBtn) {
                var sidebarBtn = document.querySelector('.ssp-sidebar [data-tab="' + tabId + '"]');
                switchTab(tabId, sidebarBtn);
                document.querySelectorAll('.ssp-drawer-item').forEach(function(b) { b.classList.remove('active'); });
                if (drawerBtn) drawerBtn.classList.add('active');
                closeDrawer();
            };

            // ===== Tab Switching =====
            window.switchTab = function(tabId, btn) {
                document.querySelectorAll('.ssp-tab-btn').forEach(function(b) { b.classList.remove('active'); });
                if (btn) {
                    btn.classList.add('active');
                    if (window.innerWidth <= 768) {
                        btn.scrollIntoView({ behavior: 'smooth', block: 'nearest', inline: 'center' });
                    }
                }
                // Sync drawer active state
                document.querySelectorAll('.ssp-drawer-item').forEach(function(b) {
                    b.classList.toggle('active', b.dataset.tab === tabId);
                });
                document.querySelectorAll('.tab-content').forEach(function(c) { c.classList.remove('active'); });
                var target = document.getElementById('tab-' + tabId);
                if (target) target.classList.add('active');
                localStorage.setItem('ssp_active_tab', tabId);
                window.scrollTo(0, 0);

                // Load data for specific tabs
                if (tabId === 'drafts' && typeof loadDrafts === 'function') loadDrafts();
                else if (tabId === 'calendar' && typeof loadCalendar === 'function') loadCalendar();
                else if (tabId === 'schedules' && typeof updateSchedulesTabList === 'function') updateSchedulesTabList();
                else if (tabId === 'template' && typeof loadTemplates === 'function') loadTemplates();
                else if (tabId === 'manual' && typeof loadManualTemplateSelect === 'function') loadManualTemplateSelect();
                else if (tabId === 'distribution' && typeof loadDistributions === 'function') loadDistributions();

                // Pick up retry data from localStorage
                if (tabId === 'manual') {
                    var retryData = localStorage.getItem('ssp_retry_data');
                    if (retryData) {
                        try {
                            var data = JSON.parse(retryData);
                            var titleEl = document.getElementById('manual_title');
                            var msgEl = document.getElementById('manual_message');
                            if (titleEl && data.title) titleEl.value = data.title;
                            if (msgEl && data.message) msgEl.value = data.message;
                            localStorage.removeItem('ssp_retry_data');
                            showToast('محتوا بارگذاری شد. تصویر/ویدیو را مجدداً آپلود کنید.', 'info');
                        } catch(e) {}
                    }
                }
            };

            // Restore active tab from localStorage
            (function() {
                var savedTab = localStorage.getItem('ssp_active_tab');
                if (savedTab && document.getElementById('tab-' + savedTab)) {
                    var sidebarBtn = document.querySelector('.ssp-sidebar [data-tab="' + savedTab + '"]');
                    var drawerBtn = document.querySelector('.ssp-drawer [data-tab="' + savedTab + '"]');
                    switchTab(savedTab, sidebarBtn || drawerBtn);
                }
            })();

            // ===== Utility =====
            window.togglePass = function(id) {
                var i = document.getElementById(id);
                if (i) i.type = i.type === 'password' ? 'text' : 'password';
            };

            function escapeHtml(str) {
                if (!str) return '';
                var div = document.createElement('div');
                div.appendChild(document.createTextNode(str));
                return div.innerHTML;
            }
            window.escapeHtml = escapeHtml;

            // ===== Platform Constants =====
            var platformIcons = {telegram: '\uD83D\uDD35', bale: '\uD83D\uDFE2', eitaa: '\uD83D\uDFE0', rubika: '\uD83D\uDFE3', instagram: '\uD83D\uDCF7', whatsapp: '\uD83D\uDFE2'};
            var platformNames = {telegram: '\u062A\u0644\u06AF\u0631\u0627\u0645', bale: '\u0628\u0644\u0647', eitaa: '\u0627\u06CC\u062A\u0627', rubika: '\u0631\u0648\u0628\u06CC\u06A9\u0627', instagram: '\u0627\u06CC\u0646\u0633\u062A\u0627\u06AF\u0631\u0627\u0645', whatsapp: '\u0648\u0627\u062A\u0633\u0627\u067E'};
            var recLabels = {daily: '\u0631\u0648\u0632\u0627\u0646\u0647', weekly: '\u0647\u0641\u062A\u06AF\u06CC', monthly: '\u0645\u0627\u0647\u0627\u0646\u0647'};

            // ===== Card Helpers =====
            function createMessengerCard(m) {
                var icon = platformIcons[m.platform] || '\uD83D\uDCAC';
                var pname = platformNames[m.platform] || m.platform;
                return '<div class="ssp-item-card ssp-card-enter" data-id="' + m.id + '">' +
                    '<div class="ssp-item-card-head"><div>' +
                    '<div class="ssp-item-card-title">' + icon + ' ' + escapeHtml(m.name) +
                    ' <span class="ssp-badge ' + (m.is_active ? 'active' : 'inactive') + '">' + (m.is_active ? '\u0641\u0639\u0627\u0644' : '\u063A\u06CC\u0631\u0641\u0639\u0627\u0644') + '</span></div>' +
                    '<div class="ssp-item-card-meta">\u067E\u0644\u062A\u0641\u0631\u0645: ' + pname +
                    (m.channel_id ? ' &bull; Chat ID: ' + escapeHtml(m.channel_id) : '') + '</div>' +
                    '</div><div style="display:flex; gap:6px;">' +
                    '<button class="ssp-btn-test btn-test-messenger" data-id="' + m.id + '">\u062A\u0633\u062A</button>' +
                    '<button class="ssp-btn-secondary btn-edit-messenger" data-id="' + m.id + '">\u0648\u06CC\u0631\u0627\u06CC\u0634</button>' +
                    '<button class="ssp-btn-danger btn-delete-messenger" data-id="' + m.id + '">\u062D\u0630\u0641</button>' +
                    '</div></div>' +
                    '<span id="messenger_status_' + m.id + '" class="ssp-connection-status"></span></div>';
            }
            function createWpSiteCard(s) {
                return '<div class="ssp-item-card ssp-card-enter" data-id="' + s.id + '">' +
                    '<div class="ssp-item-card-head"><div>' +
                    '<div class="ssp-item-card-title">\uD83C\uDF10 ' + escapeHtml(s.site_name) +
                    ' <span class="ssp-badge ' + (s.is_active ? 'active' : 'inactive') + '">' + (s.is_active ? '\u0641\u0639\u0627\u0644' : '\u063A\u06CC\u0631\u0641\u0639\u0627\u0644') + '</span>' +
                    (s.auto_publish ? ' <span class="ssp-badge info">\u0627\u0646\u062A\u0634\u0627\u0631 \u062E\u0648\u062F\u06A9\u0627\u0631</span>' : '') + '</div>' +
                    '<div class="ssp-item-card-meta">' + escapeHtml(s.site_url) + ' &bull; ' + escapeHtml(s.username) + '</div>' +
                    '</div><div style="display:flex; gap:6px;">' +
                    '<button class="ssp-btn-test btn-test-wpsite" data-id="' + s.id + '">\u062A\u0633\u062A</button>' +
                    '<button class="ssp-btn-secondary btn-edit-wpsite" data-id="' + s.id + '">\u0648\u06CC\u0631\u0627\u06CC\u0634</button>' +
                    '<button class="ssp-btn-danger btn-delete-wpsite" data-id="' + s.id + '">\u062D\u0630\u0641</button>' +
                    '</div></div>' +
                    '<span id="wp_site_status_' + s.id + '" class="ssp-connection-status"></span></div>';
            }
            function createRssFeedCard(f) {
                var lastFetched = f.last_fetched ? '<div class="ssp-item-card-meta" style="margin-top:4px;"><span style="color:var(--success);">\u0622\u062E\u0631\u06CC\u0646 \u062F\u0631\u06CC\u0627\u0641\u062A: ' + formatJalaliDateTime(f.last_fetched) + '</span>' + (f.fetched_count ? ' \u2022 ' + f.fetched_count + ' \u0622\u06CC\u062A\u0645' : '') + '</div>' : '';
                var extractBadge = f.extract_content ? ' <span class="ssp-badge success" style="background:var(--success);color:#fff;">استخراج کامل</span>' : '';
                return '<div class="ssp-item-card ssp-card-enter" data-id="' + f.id + '">' +
                    '<div class="ssp-item-card-head"><div>' +
                    '<div class="ssp-item-card-title">\uD83D\uDCE1 ' + escapeHtml(f.feed_name) +
                    ' <span class="ssp-badge ' + (f.is_active ? 'active' : 'inactive') + '">' + (f.is_active ? '\u0641\u0639\u0627\u0644' : '\u063A\u06CC\u0631\u0641\u0639\u0627\u0644') + '</span>' +
                    (f.auto_fetch ? ' <span class="ssp-badge info">\u062F\u0631\u06CC\u0627\u0641\u062A \u062E\u0648\u062F\u06A9\u0627\u0631</span>' : '') + extractBadge + '</div>' +
                    '<div class="ssp-item-card-meta">' + escapeHtml(f.feed_url) + '</div>' +
                    lastFetched +
                    '</div><div style="display:flex; gap:6px; flex-wrap:wrap;">' +
                    '<button class="ssp-btn-test btn-fetch-rss" data-id="' + f.id + '">\u062F\u0631\u06CC\u0627\u0641\u062A \u0641\u0648\u0631\u06CC</button>' +
                    '<button class="ssp-btn-primary btn-fetch-extract" data-id="' + f.id + '" style="font-size:0.75rem; padding:4px 10px;">استخراج کامل</button>' +
                    '<button class="ssp-btn-secondary btn-edit-rss" data-id="' + f.id + '">\u0648\u06CC\u0631\u0627\u06CC\u0634</button>' +
                    '<button class="ssp-btn-danger btn-delete-rss" data-id="' + f.id + '">\u062D\u0630\u0641</button>' +
                    '</div></div></div>';
            }
            function createScheduleCard(s) {
                var statusClass = s.status === 'pending' ? 'info' : 'success';
                var statusIcon = s.status === 'pending' ? '<svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" style="color:var(--warning);vertical-align:middle;"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>' : '<svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" style="color:var(--success);vertical-align:middle;margin-right:2px;"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>';
                var statusText = s.status === 'pending' ? 'در انتظار' : 'انجام شده';
                var dt = s.scheduled_at ? s.scheduled_at.replace('T', ' ') : '';
                var jalaliDt = formatJalaliDateTime(dt);
                var recLabels = {daily:'روزانه', weekly:'هفتگی', monthly:'ماهانه'};
                var msgPreview = (s.message || '').substring(0, 80);
                var isPast = s.status !== 'pending';
                var borderColor = isPast ? 'var(--success)' : 'var(--accent)';
                return '<div class="ssp-item-card ssp-card-enter" style="border-right:3px solid ' + borderColor + ';">' +
                    '<div class="ssp-item-card-head"><div style="flex:1;">' +
                    '<div class="ssp-item-card-title">' + statusIcon + ' ' + escapeHtml(s.title) +
                    ' <span class="ssp-badge ' + statusClass + '">' + statusText + '</span>' +
                    (s.recurring ? ' <span class="ssp-badge pro">' + (recLabels[s.recurring] || s.recurring) + '</span>' : '') + '</div>' +
                    '<div class="ssp-item-card-meta" style="margin-top:4px;"><svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" style="vertical-align:middle;"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg> ' + escapeHtml(jalaliDt) + '</div>' +
                    (msgPreview ? '<div class="ssp-item-card-meta" style="margin-top:4px; font-size:0.8rem;">' + escapeHtml(msgPreview) + '...</div>' : '') +
                    '</div><div style="display:flex; gap:6px; align-items:start;">' +
                    '<button class="ssp-btn-danger btn-delete-schedule" data-id="' + s.id + '">حذف</button>' +
                    '</div></div></div>';
            }
            // ===== SPA Helpers =====
            function removeEmptyState(listId) {
                var el = document.querySelector('#' + listId + ' .ssp-empty');
                if (el) el.remove();
            }
            function showEmptyState(listId, icon, text, subtext, actionBtnHtml = '') {
                var list = document.getElementById(listId);
                if (list && list.children.length === 0) {
                    list.innerHTML = '<div class="ssp-empty"><div class="ssp-empty-icon" style="font-size:3.5rem; color:var(--text-subtle); margin-bottom:16px;">' + icon + '</div><h4 style="margin:0 0 8px; font-size:1.1rem; color:var(--text);">' + text + '</h4>' + (subtext ? '<p style="font-size:0.9rem; color:var(--text-muted); max-width:400px; margin:0 auto 20px;">' + subtext + '</p>' : '') + (actionBtnHtml ? '<div>'+actionBtnHtml+'</div>' : '') + '</div>';
                }
            }
            function showSaved(id) {
                var el = document.getElementById(id);
                if (!el) return;
                el.classList.add('show');
                setTimeout(function() { el.classList.remove('show'); }, 2500);
            }
            window.selectProvider = function(key, el) {
                document.querySelectorAll('.ssp-ai-provider-card').forEach(function(c) { c.classList.remove('selected'); });
                el.classList.add('selected');
                document.getElementById('ai_provider').value = key;

                // Update model hint
                var hints = {
                    'groq': 'مدل‌های Groq (رایگان و سریع): llama-3.3-70b-versatile, llama-3.1-8b-instant, mixtral-8x7b-32768',
                    'deepseek': 'مدل‌های DeepSeek: deepseek-chat, deepseek-reasoner',
                    'openai': 'مدل‌های OpenAI: gpt-4o-mini, gpt-4o, gpt-3.5-turbo',
                    'anthropic': 'مدل‌های Claude: claude-3-haiku, claude-3-sonnet, claude-3-opus',
                    'gemini': 'مدل‌های Gemini: gemini-2.0-flash, gemini-1.5-pro, gemini-1.5-flash',
                    'openrouter': 'مدل‌های متنوع: هر مدلی از OpenAI, Anthropic, Meta و...'
                };
                var hintEl = document.getElementById('model_hint');
                if (hintEl && hints[key]) {
                    hintEl.textContent = hints[key];
                }
            };

            window.togglePromptMode = function(mode) {
                document.querySelectorAll('input[name="ai_prompt_mode"]').forEach(function(r) {
                    r.checked = r.value === mode;
                });
                var section = document.getElementById('ai_custom_prompt_section');
                section.style.display = mode === 'advanced' ? 'block' : 'none';
                var cards = section.parentElement.querySelectorAll('.ssp-feature-card');
                if (cards.length >= 2) {
                    cards[0].classList.toggle('active', mode === 'simple');
                    cards[1].classList.toggle('active', mode === 'advanced');
                }
            };

            // ===== Browser Bridge Mode =====

            window.selectAIMode = function(mode) {
                document.getElementById('ssp_ai_mode').value = mode;
                document.querySelectorAll('input[name="ssp_ai_mode"]').forEach(function(r) {
                    r.checked = r.value === mode;
                });
                var modeCards = document.querySelectorAll('#tab-ai > form > .ssp-grid-2')[0];
                if (modeCards) {
                    var cards = modeCards.querySelectorAll('.ssp-feature-card');
                    if (cards.length >= 2) {
                        cards[0].classList.toggle('active', mode === 'api');
                        cards[1].classList.toggle('active', mode === 'browser');
                    }
                }
                var apiSettings = document.getElementById('api_mode_settings');
                var browserSettings = document.getElementById('browser_mode_settings');
                if (apiSettings) apiSettings.style.display = mode === 'api' ? 'block' : 'none';
                if (browserSettings) browserSettings.style.display = mode === 'browser' ? 'block' : 'none';
            };

            window.selectChatbot = function(bot) {
                document.getElementById('ssp_ai_chatbot').value = bot;
                var section = document.getElementById('browser_mode_settings');
                if (section) {
                    var cards = section.querySelectorAll('.ssp-grid-2 .ssp-feature-card');
                    cards.forEach(function(c, i) {
                        c.classList.toggle('active', (i === 0 && bot === 'deepseek') || (i === 1 && bot === 'chatgpt'));
                    });
                }
            };

            window._sspBridgeScriptCode = '';
            window._sspBridgeScriptUrl = '';

            window.copyBridgeCode = function() {
                var code = window._sspBridgeScriptCode || '';
                if (!code) {
                    showToast('کد اسکریپت دریافت نشد، لطفاً دوباره دکمه دریافت را بزنید.', 'error');
                    return;
                }
                var successMsg = 'کد اسکریپت کپی شد! در ScriptCat یا Tampermonkey روی + New Script بزنید، کد را پیست کرده و Save کنید.';
                if (navigator.clipboard && navigator.clipboard.writeText) {
                    navigator.clipboard.writeText(code).then(function() {
                        showToast(successMsg, 'success');
                    }).catch(function() {
                        fallbackCopy(code);
                    });
                } else {
                    fallbackCopy(code);
                }
                function fallbackCopy(text) {
                    var ta = document.createElement('textarea');
                    ta.value = text;
                    ta.style.position = 'fixed';
                    ta.style.left = '-9999px';
                    document.body.appendChild(ta);
                    ta.focus();
                    ta.select();
                    try {
                        document.execCommand('copy');
                        showToast(successMsg, 'success');
                    } catch(e) {
                        showToast('خطا در کپی خودکار', 'error');
                    }
                    document.body.removeChild(ta);
                }
            };

            window.setupBrowserBridge = function() {
                var btn = document.getElementById('bridge_setup_btn');
                btn.classList.add('loading');
                var fd = new FormData();
                fd.append('action', 'ssp_bridge_setup');
                fd.append('security', nonce);
                fetch(ajaxurl, {method: 'POST', body: fd})
                    .then(function(r) { return r.json(); })
                    .then(function(res) {
                        btn.classList.remove('loading');
                        if (res.success) {
                            var d = res.data;
                            var scriptUrl = d.direct_url || (d.script_url + '?token=' + encodeURIComponent(d.token) + '&site=' + encodeURIComponent(d.site_url));
                            window._sspBridgeScriptCode = d.script_code || '';
                            window._sspBridgeScriptUrl = scriptUrl;

                            var resultEl = document.getElementById('bridge_setup_result');
                            resultEl.innerHTML = '<div class="ssp-card" style="background:#f8fafc; border:1px solid #cbd5e1; padding:16px; border-radius:10px; margin-top:12px;">' +
                                '<h4 style="margin:0 0 12px; color:#1e293b; display:flex; align-items:center; gap:8px; font-size:0.95rem;">' +
                                '<span style="display:inline-block; width:10px; height:10px; border-radius:50%; background:#10b981;"></span>' +
                                'کد و لینک اختصاصی اسکریپت آماده شد (۲ روش ساده برای نصب در مرورگر)' +
                                '</h4>' +
                                
                                '<div style="background:white; border:1px solid #e2e8f0; border-radius:8px; padding:14px; margin-bottom:12px;">' +
                                '<div style="font-weight:bold; color:#0f172a; margin-bottom:6px; font-size:0.9rem;">روش اول (ساده و مطمئن): جایگذاری مستقیم کد در افزونه مرورگر</div>' +
                                '<ol style="font-size:0.84rem; color:#475569; margin:0 0 10px; padding-right:20px; line-height:1.7;">' +
                                '<li>روی دکمه <strong>«کپی کد کامل اسکریپت»</strong> زیر کلیک کنید.</li>' +
                                '<li>افزونه <strong>ScriptCat</strong> یا <strong>Tampermonkey</strong> را باز کرده و روی دکمه افزودن اسکریپت جدید کلیک کنید.</li>' +
                                '<li>کل کدهای پیش‌فرض داخل ویرایشگر را پاک کنید (<code>Ctrl + A</code> و سپس <code>Delete</code>).</li>' +
                                '<li>کد کپی‌شده را پیست کنید (<code>Ctrl + V</code>) و در بالا سمت راست دکمه <strong>Save</strong> را بزنید.</li>' +
                                '</ol>' +
                                '<button type="button" class="ssp-btn-primary" onclick="copyBridgeCode()" style="display:inline-flex; align-items:center; gap:6px;">' +
                                '<span>کپی کد کامل اسکریپت (روش پیشنهادی)</span>' +
                                '</button>' +
                                '</div>' +

                                '<div style="background:white; border:1px solid #e2e8f0; border-radius:8px; padding:14px;">' +
                                '<div style="font-weight:bold; color:#0f172a; margin-bottom:6px; font-size:0.9rem;">روش دوم: باز کردن لینک مستقیم در تب مرورگر</div>' +
                                '<p style="font-size:0.84rem; color:#475569; margin:0 0 8px; line-height:1.6;">' +
                                'اگر لینک زیر را در یک تب جدید از مرورگر باز کنید، افزونه مرورگر به صورت خودکار فایل اسکریپت را شناخته و پنجره نصب را به شما نشان می‌دهد:' +
                                '</p>' +
                                '<div style="display:flex; gap:8px; align-items:center; flex-wrap:wrap; margin-bottom:8px;">' +
                                '<button type="button" class="ssp-btn-secondary" onclick="window.open(window._sspBridgeScriptUrl, \'_blank\');" style="white-space:nowrap;">باز کردن لینک در تب جدید</button>' +
                                '<button type="button" class="ssp-btn-secondary" onclick="navigator.clipboard.writeText(window._sspBridgeScriptUrl).then(function(){showToast(\'لینک کپی شد!\',\'success\');})" style="white-space:nowrap;">کپی لینک اسکریپت</button>' +
                                '</div>' +
                                '<code id="bridge_script_url" style="word-break:break-all; padding:8px 10px; background:#f1f5f9; border:1px solid #cbd5e1; border-radius:6px; direction:ltr; text-align:left; font-size:0.75rem; color:#334155; display:block;">' + scriptUrl + '</code>' +
                                '</div>' +
                                '</div>';

                            // Pre-configure bookmarklet
                            var bkCode = "javascript:void(function(){var t='" + d.token + "';var u='" + d.site_url + "';var tid=localStorage.getItem('ssp_bridge_pending_task');if(!tid){alert('No pending task found. Generate content from the plugin first.');return;}var msgs=document.querySelectorAll('[data-message-author-role=assistant] .markdown');if(!msgs.length)msgs=document.querySelectorAll('.ds-markdown--block');if(!msgs.length){alert('No AI response found.');return;}var txt=msgs[msgs.length-1].innerText;fetch(u+'/wp-json/ssp/v1/ai-bridge/response',{method:'POST',headers:{'Content-Type':'application/json','X-SSP-Bridge-Token':t},body:JSON.stringify({task_id:tid,response_text:txt,status:'completed'})}).then(function(){alert('Response sent to plugin!');}).catch(function(e){alert('Error: '+e.message);});}())";
                            document.getElementById('bookmarklet_link').href = bkCode;

                            showToast('تنظیمات آماده شد!', 'success');
                        } else {
                            showToast(res.data.message || 'خطا', 'error');
                        }
                    })
                    .catch(function() { btn.classList.remove('loading'); showToast('خطا در ارتباط با سرور', 'error'); });
            };

            window.testBrowserBridge = function() {
                showToast('لطفاً به سایت چت‌بات بروید و ویجت سبز را بررسی کنید.', 'success');
                var chatbot = document.getElementById('ssp_ai_chatbot').value;
                var urls = { 'deepseek': 'https://chat.deepseek.com/', 'chatgpt': 'https://chatgpt.com/' };
                window.open(urls[chatbot] || urls['deepseek'], '_blank');
            };

            // ===== Browser Mode Generation =====

            // ===== Browser Bridge State =====
            window._bridgeCurrentTool = null;
            window._bridgeCurrentPrompt = null;
            window._bridgeCurrentContext = null;
            window._bridgeCurrentToolFn = null;
            window._bridgeModalState = null;
            window._bridgeChatbotTab = null;
            window._bridgeBrainstormResult = null;
            window._bridgePostgenResult = null;

            window.openChatbotTab = function() {
                var chatbotObj = document.getElementById('ssp_ai_chatbot');
                var chatbot = chatbotObj ? chatbotObj.value : 'deepseek';
                var urls = { 'deepseek': 'https://chat.deepseek.com/', 'chatgpt': 'https://chatgpt.com/' };
                var url = urls[chatbot] || urls['deepseek'];
                window._bridgeChatbotTab = window.open(url, '_blank');
                
                if (!window._bridgeChatbotTab || window._bridgeChatbotTab.closed || typeof window._bridgeChatbotTab.closed === 'undefined') {
                    // Popup was blocked
                    if (typeof window.setBridgeModalState === 'function') {
                        window.setBridgeModalState(1, 'پاپ‌آپ مرورگر شما مسدود شده است! لطفاً پاپ‌آپ را برای این سایت فعال کنید یا <a href="' + url + '" target="_blank" style="color:var(--error);text-decoration:underline;font-weight:bold;">اینجا کلیک کنید تا تب جدید باز شود</a> و سپس دکمه تلاش مجدد را بزنید.');
                    } else {
                        if (typeof showToast === 'function') showToast('پاپ‌آپ مسدود شد! لطفاً پاپ‌آپ را فعال کنید.', 'error');
                    }
                }
            };

            // ===== Modal State Machine =====
            var bridgeStates = {

                'success':   { step: 3, status: 'پاسخ با موفقیت دریافت شد!', progress: 100, icon1: 'done', icon2: 'done', icon3: 'done' }
            };
            function getStepIcon(state) {
                if (state === 'done') return '<svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" style="vertical-align:middle;margin-left:4px;"><polyline points="20 6 9 17 4 12"/></svg>';
                if (state === 'spinner') return '<svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"/><circle cx="12" cy="12" r="3"/></svg>';
                if (state === 'error') return '<svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" style="vertical-align:middle;margin-left:4px;"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>';
                return '<svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/></svg>';
            }

            // ===== AI Post & Product Generation (Delegated to Unified portal-ai.js) =====
            window.pgGeneratePostViaBrowser = function() {
                if (typeof window.pgGeneratePost === 'function') {
                    window.pgGeneratePost();
                } else {
                    showToast('در حال بارگذاری موتور هوش مصنوعی...', 'info');
                }
            };

            window.pgGenerateViaBrowser = function() {
                if (typeof window.pgGenerateWithAI === 'function') {
                    window.pgGenerateWithAI();
                } else {
                    showToast('در حال بارگذاری موتور هوش مصنوعی...', 'info');
                }
            };

            window.cgGenerateViaBrowser = function() {
                if (typeof window.cgGenerateWithAI === 'function') {
                    window.cgGenerateWithAI();
                } else {
                    showToast('در حال بارگذاری موتور هوش مصنوعی...', 'info');
                }
            };

            window.bsGenerateViaBrowser = function() {
                if (typeof window.bsGenerate === 'function') {
                    window.bsGenerate();
                } else {
                    showToast('در حال بارگذاری موتور هوش مصنوعی...', 'info');
                }
            };
        })();



// ===== CALENDAR & DRAFTS DELEGATION & MANAGEMENT =====

// Initialize and ensure Jalali calendar loads smoothly
if (typeof loadCalendar === 'function') {
    // portal-core.js provides rich Jalali loadCalendar, calendarPrevMonth, calendarNextMonth, calendarToday
}

window.loadDrafts = function() {
    let listEl = document.getElementById('drafts_list');
    if (!listEl) return;
    let drafts = Array.isArray(window.draftsData) ? window.draftsData : [];
    if (drafts.length === 0) {
        listEl.innerHTML = `<div class="ssp-empty"><div class="ssp-empty-icon"><svg viewBox="0 0 24 24" width="48" height="48" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/><polyline points="10 9 9 9 8 9"/></svg></div><p>هنوز پیش‌نویسی ندارید.</p></div>`;
        return;
    }
    
    let html = '';
    drafts.forEach(d => {
        let titleEsc = (typeof escapeHtml === 'function') ? escapeHtml(d.title || 'بدون عنوان') : (d.title || 'بدون عنوان');
        let contentEsc = (typeof escapeHtml === 'function') ? escapeHtml(d.content || '') : (d.content || '');
        let created = d.created_at ? `<span style="font-size:0.75rem; color:var(--text-muted);">&#128197; ${d.created_at.substring(0, 16)}</span>` : '';
        html += `
            <div class="ssp-card ssp-card-enter" style="margin-bottom:12px; padding:16px;">
                <div style="display:flex; justify-content:space-between; align-items:flex-start; gap:12px; flex-wrap:wrap;">
                    <div style="flex:1; min-width:240px;">
                        <div style="display:flex; align-items:center; gap:8px; margin-bottom:6px;">
                            <h4 style="margin:0; font-size:0.95rem; font-weight:700;">${titleEsc}</h4>
                            ${created}
                        </div>
                        <p style="margin:0; font-size:0.85rem; color:var(--text-muted); max-height:48px; overflow:hidden; text-overflow:ellipsis; line-height:1.5;">${contentEsc}</p>
                    </div>
                    <div style="display:flex; gap:6px; flex-wrap:wrap; align-items:center;">
                        <button type="button" class="ssp-btn-primary" onclick="useDraft(${d.id})" style="font-size:0.8rem; padding:6px 12px;">🚀 استفاده</button>
                        <button type="button" class="ssp-btn-secondary" onclick="openEditDraftModal(${d.id})" style="font-size:0.8rem; padding:6px 10px;">✏️ ویرایش</button>
                        <button type="button" class="ssp-btn-secondary" onclick="openScheduleDraftModal(${d.id})" style="font-size:0.8rem; padding:6px 10px;">📅 زمان‌بندی</button>
                        <button type="button" class="ssp-btn-danger" onclick="deleteDraft(${d.id})" style="font-size:0.8rem; padding:6px 10px;">🗑️ حذف</button>
                    </div>
                </div>
            </div>
        `;
    });
    listEl.innerHTML = html;
};

window.useDraft = function(id) {
    let drafts = Array.isArray(window.draftsData) ? window.draftsData : [];
    let draft = drafts.find(d => d.id == id);
    if(draft) {
        let titleEl = document.getElementById('manual_title');
        let msgEl = document.getElementById('manual_message');
        let tagEl = document.getElementById('manual_hashtags');
        if(titleEl) titleEl.value = draft.title || '';
        if(msgEl) msgEl.value = draft.content || '';
        if(tagEl && draft.hashtags) tagEl.value = draft.hashtags;
        if (typeof updateManualCharCount === 'function') updateManualCharCount();
        switchTab('manual', document.querySelector('.ssp-sidebar [data-tab="manual"]'));
        showToast('پیش‌نویس بارگذاری شد', 'success');
    }
};

window.deleteDraft = function(id) {
    if(!confirm('آیا از حذف این پیش‌نویس مطمئن هستید؟')) return;
    let fd = new FormData();
    fd.append('action', 'ssp_delete_draft');
    fd.append('security', window.nonce || (typeof nonce !== 'undefined' ? nonce : ''));
    fd.append('draft_id', id);
    fd.append('id', id);
    fetch(ajaxurl, {method: 'POST', body: fd})
        .then(r => r.json())
        .then(res => {
            if (res.success) {
                if (Array.isArray(window.draftsData)) {
                    window.draftsData = window.draftsData.filter(d => d.id != id);
                }
                loadDrafts();
                showToast('پیش‌نویس با موفقیت حذف شد', 'success');
            } else {
                showToast(res.data ? res.data.message : 'خطا در حذف پیش‌نویس', 'error');
            }
        })
        .catch(() => showToast('خطای ارتباط با سرور', 'error'));
};

window.saveDraft = function() {
    let btn = document.getElementById('save_draft_btn');
    if(!btn) return;
    let title = document.getElementById('draft_title') ? document.getElementById('draft_title').value : '';
    let content = document.getElementById('draft_content') ? document.getElementById('draft_content').value : '';
    let hashtags = document.getElementById('draft_hashtags') ? document.getElementById('draft_hashtags').value : '';
    if(!content) { showToast('متن پیش‌نویس الزامی است', 'error'); return; }
    
    setBtnLoading(btn, true);
    let fd = new FormData();
    fd.append('action', 'ssp_save_draft');
    fd.append('security', window.nonce || (typeof nonce !== 'undefined' ? nonce : ''));
    fd.append('title', title);
    fd.append('content', content);
    fd.append('hashtags', hashtags);
    fetch(ajaxurl, {method: 'POST', body: fd})
        .then(r => r.json())
        .then(res => {
            setBtnLoading(btn, false);
            if (res.success) {
                if (document.getElementById('draft_title')) document.getElementById('draft_title').value = '';
                if (document.getElementById('draft_content')) document.getElementById('draft_content').value = '';
                if (document.getElementById('draft_hashtags')) document.getElementById('draft_hashtags').value = '';
                showSaved('draft_saved');
                let newId = (res.data && res.data.id) ? res.data.id : Date.now();
                if (!Array.isArray(window.draftsData)) window.draftsData = [];
                window.draftsData.unshift({
                    id: newId,
                    title: title,
                    content: content,
                    hashtags: hashtags,
                    created_at: new Date().toISOString().replace('T', ' ').substring(0, 19)
                });
                loadDrafts();
            } else {
                showToast(res.data ? res.data.message : 'خطا در ذخیره پیش‌نویس', 'error');
            }
        })
        .catch(() => {
            setBtnLoading(btn, false);
            showToast('خطای شبکه در ذخیره پیش‌نویس', 'error');
        });
};

// Templates Implementation
window.loadTemplates = function() {
    let listEl = document.getElementById('template_library_list');
    if (!listEl) return;
    if (!templatesData || templatesData.length === 0) {
        listEl.innerHTML = `<div class="ssp-empty"><div class="ssp-empty-icon"><svg viewBox="0 0 24 24" width="48" height="48" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20"/><path d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2z"/></svg></div><p>هنوز قالبی ذخیره نکرده‌اید.</p></div>`;
        return;
    }
    renderTemplates(templatesData);
};

window.filterTemplates = function(cat, btn) {
    document.querySelectorAll('.tpl-filter').forEach(b => b.classList.remove('active'));
    if(btn) btn.classList.add('active');
    
    if(cat === 'all') renderTemplates(templatesData);
    else renderTemplates(templatesData.filter(t => t.category === cat));
};

function renderTemplates(templates) {
    let listEl = document.getElementById('template_library_list');
    if(!templates || templates.length === 0) {
        listEl.innerHTML = `<div class="ssp-empty"><p>قالبی در این دسته یافت نشد.</p></div>`;
        return;
    }
    let html = '';
    templates.forEach(t => {
        html += `
            <div class="ssp-card ssp-card-enter" style="margin-bottom:12px; padding:16px;">
                <div style="display:flex; justify-content:space-between; align-items:flex-start;">
                    <div>
                        <h4 style="margin:0 0 4px;">${escapeHtml(t.name || 'بدون نام')} <span class="ssp-badge">${escapeHtml(t.category)}</span></h4>
                        <p style="margin:0; font-size:0.85rem; color:var(--text-muted); max-height:20px; overflow:hidden;">${escapeHtml(t.content)}</p>
                    </div>
                    <div style="display:flex; gap:8px;">
                        <button type="button" class="ssp-btn-danger" onclick="deleteTemplate(${t.id})">حذف</button>
                    </div>
                </div>
            </div>
        `;
    });
    listEl.innerHTML = html;
}

window.deleteTemplate = function(id) {
    if(!confirm('آیا از حذف این قالب مطمئن هستید؟')) return;
    let fd = new FormData();
    fd.append('action', 'ssp_delete_template_item');
    fd.append('security', nonce);
    fd.append('template_id', id);
    fetch(ajaxurl, {method: 'POST', body: fd})
        .then(r => r.json())
        .then(res => {
            if (res.success) {
                templatesData = templatesData.filter(t => t.id != id);
                loadTemplates();
                showToast('حذف شد', 'success');
            } else {
                showToast(res.data ? res.data.message : 'خطا', 'error');
            }
        });
};

window.openTemplateCreateModal = function() {
    alert("This feature is best used by navigating to 'Create Template' section or configuring default message format.");
};


window.loadManualTemplateSelect = function() {
    let sel = document.getElementById('manual_template_select');
    let container = document.getElementById('manual_template_selector');
    if (!sel || !container) return;
    
    if (templatesData && templatesData.length > 0) {
        container.style.display = 'block';
        let html = '<option value="">-- انتخاب قالب ذخیره شده --</option>';
        templatesData.forEach(t => {
            html += `<option value="${t.id}">${escapeHtml(t.name)}</option>`;
        });
        sel.innerHTML = html;
        
        if (window._defaultTemplateId) {
            sel.value = window._defaultTemplateId;
            onManualTemplateSelect();
        }
    } else {
        container.style.display = 'none';
    }
};

window.onManualTemplateSelect = function() {
    let sel = document.getElementById('manual_template_select');
    if (!sel || !sel.value) return;
    
    let t = templatesData.find(x => x.id == sel.value);
    if (t) {
        let msgEl = document.getElementById('manual_message');
        if (msgEl) {
            let content = t.content;
            if (t.hashtags) content += '\n\n' + t.hashtags;
            if (t.signature) content += '\n\n' + t.signature;
            msgEl.value = content;
        }
    }
};

window.saveTemplate = function(e) {
    e.preventDefault();
    let btn = document.getElementById('save_template_btn');
    let template = document.getElementById('msg_template').value;
    let hashtags = document.getElementById('hashtags').value;
    let signature = document.getElementById('signature').value;
    
    setBtnLoading(btn, true);
    let fd = new FormData();
    fd.append('action', 'ssp_save_template_item');
    fd.append('security', nonce);
    fd.append('name', 'قالب عمومی پیش‌فرض');
    fd.append('category', 'general');
    fd.append('content', template);
    fd.append('hashtags', hashtags);
    fd.append('signature', signature);
    
    fetch(ajaxurl, {method: 'POST', body: fd})
        .then(r => r.json())
        .then(res => {
            setBtnLoading(btn, false);
            if (res.success) {
                showSaved('template_saved');
                setTimeout(() => location.reload(), 1000);
            } else {
                showToast(res.data ? res.data.message : 'خطا', 'error');
            }
        });
};

        </script>

<script src="<?php echo plugins_url('assets/js/portal-core.js', dirname(__DIR__, 2) . '/main.php'); ?>?v=<?php echo SSP_VERSION; ?>"></script>
<script src="<?php echo plugins_url('assets/js/portal-ai.js', dirname(__DIR__, 2) . '/main.php'); ?>?v=<?php echo SSP_VERSION; ?>"></script>
