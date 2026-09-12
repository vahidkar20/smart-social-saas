<?php
/**
 * Admin Panel View — Faithful extraction from main-back.php lines 6017-6780
 */
if (!defined('ABSPATH')) exit;
if (!current_user_can('manage_options')) wp_die('غیرمجاز');

// Handle cron activation from notice link
if (isset($_GET['activate_cron']) && $_GET['activate_cron'] == '1') {
    if (wp_verify_nonce($_GET['_wpnonce'] ?? '', 'ssp_activate_cron')) {
        self::activate();
        echo '<div class="notice notice-success"><p>Cron Jobs با موفقیت فعال شدند!</p></div>';
    }
}

$nonce = wp_create_nonce('ssp_secure_nonce');

$logs = SSP_DB::get_all_logs(500);
$licenses = $this->get_global_items('licenses');
$queue = SSP_DB::get_queue_items(null, 500);

$user_ids = array_unique(array_column($logs, 'user_id'));
$total_users = count($user_ids);
$pro_users = count(get_users(['meta_key' => 'ssp_plan', 'meta_value' => 'pro', 'fields' => 'ID']));
$total_licenses = count($licenses);
$used_licenses = count(array_filter($licenses, function($l) { return !empty($l['used_by']); }));
$available_licenses = $total_licenses - $used_licenses;
$total_messages = count($logs);
$success_messages = count(array_filter($logs, function($l) { return $l['status'] === 'success'; }));
$failed_messages = count(array_filter($logs, function($l) { return $l['status'] === 'error'; }));
$queue_pending = count(array_filter($queue, function($q) { return $q['status'] === 'pending'; }));
$queue_failed = count(array_filter($queue, function($q) { return $q['status'] === 'failed'; }));

usort($licenses, function($a, $b) { return strtotime($b['created_at'] ?? '0') - strtotime($a['created_at'] ?? '0'); });
$license_users = [];
foreach ($licenses as $lic) {
    if (!empty($lic['used_by'])) {
        $u = get_userdata($lic['used_by']);
        $license_users[$lic['id']] = $u ? $u->display_name : '';
    } else {
        $license_users[$lic['id']] = '';
    }
}
?>

<div class="ssp-admin-wrap">
    <div class="ssp-admin-header">
        <h1>پنل مدیریت Smart Automation Pro</h1>
        <span style="color:#94A3B8; font-size:0.85rem;">v<?php echo SSP_VERSION; ?></span>
    </div>

    <div class="ssp-admin-stats">
        <div class="ssp-admin-stat">
            <div class="ssp-admin-stat-label">کل کاربران</div>
            <div class="ssp-admin-stat-value"><?php echo $total_users; ?></div>
            <div class="ssp-admin-stat-sub"><?php echo $pro_users; ?> کاربر Pro</div>
        </div>
        <div class="ssp-admin-stat">
            <div class="ssp-admin-stat-label">لایسنس‌ها</div>
            <div class="ssp-admin-stat-value"><?php echo $total_licenses; ?></div>
            <div class="ssp-admin-stat-sub"><?php echo $available_licenses; ?> موجود / <?php echo $used_licenses; ?> استفاده شده</div>
        </div>
        <div class="ssp-admin-stat">
            <div class="ssp-admin-stat-label">کل پیام‌ها</div>
            <div class="ssp-admin-stat-value"><?php echo number_format($total_messages); ?></div>
            <div class="ssp-admin-stat-sub"><?php echo number_format($success_messages); ?> موفق / <?php echo number_format($failed_messages); ?> خطا</div>
        </div>
        <div class="ssp-admin-stat">
            <div class="ssp-admin-stat-label">صف پردازش</div>
            <div class="ssp-admin-stat-value"><?php echo $queue_pending + $queue_failed; ?></div>
            <div class="ssp-admin-stat-sub"><?php echo $queue_pending; ?> در انتظار / <?php echo $queue_failed; ?> ناموفق</div>
        </div>
    </div>

    <!-- User Browser -->
    <div class="ssp-admin-section">
        <h2>مرور کاربران</h2>
        <p style="color:#64748b; font-size:0.85rem; margin:0 0 12px;">پورتال هر کاربر را مشاهده و ویرایش کنید.</p>
        <div class="ssp-admin-form" style="margin-bottom:12px;">
            <div class="field">
                <label>جستجو</label>
                <input type="text" id="admin_user_search" placeholder="نام، ایمیل یا نام کاربری" style="width:300px;" oninput="adminSearchUsers()">
            </div>
        </div>
        <div id="admin_user_list">
            <div class="ssp-admin-loading">در حال بارگذاری...</div>
        </div>
    </div>

    <!-- License Management -->
    <div class="ssp-admin-section">
        <h2>ایجاد لایسنس جدید</h2>
        <div class="ssp-admin-form">
            <div class="field">
                <label>تعداد</label>
                <input type="number" id="admin_license_count" value="1" min="1" max="50" style="width:80px;">
            </div>
            <div class="field">
                <label>مدت روز</label>
                <input type="number" id="admin_license_duration" value="365" min="1" style="width:100px;">
            </div>
            <button class="ssp-admin-btn" onclick="adminGenerateLicense()">ایجاد لایسنس</button>
        </div>
        <div id="admin_license_output" class="ssp-admin-output"></div>
    </div>

    <div class="ssp-admin-section">
        <h2>لایسنس‌های موجود</h2>
        <div class="ssp-admin-tools">
            <button class="ssp-admin-btn ssp-admin-btn-secondary" onclick="adminProcessQueue()">پردازش دستی صف</button>
        </div>

        <?php if (empty($licenses)) : ?>
        <div class="ssp-admin-empty">هنوز لایسنسی ایجاد نشده است.</div>
        <?php else : ?>
        <div style="overflow-x:auto;">
        <table class="ssp-admin-table">
            <thead>
                <tr>
                    <th>کلید</th>
                    <th>پلن</th>
                    <th>مدت</th>
                    <th>وضعیت</th>
                    <th>استفاده شده توسط</th>
                    <th>تاریخ ایجاد</th>
                    <th>عملیات</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($licenses as $lic) : ?>
                <tr>
                    <td><span class="ssp-admin-key"><?php echo esc_html($lic['license_key']); ?></span></td>
                    <td><span class="ssp-admin-badge used"><?php echo esc_html($lic['plan']); ?></span></td>
                    <td>
                        <span id="lic-duration-<?php echo (int)$lic['id']; ?>"><?php echo (int)$lic['duration_days']; ?> روز</span>
                    </td>
                    <td>
                        <?php if (!empty($lic['used_by'])) : ?>
                            <span class="ssp-admin-badge ok">استفاده شده</span>
                        <?php else : ?>
                            <span class="ssp-admin-badge warning">موجود</span>
                        <?php endif; ?>
                    </td>
                    <td><?php echo !empty($license_users[$lic['id']]) ? esc_html($license_users[$lic['id']]) : '-'; ?></td>
                    <td><?php echo date_i18n('Y/m/d', strtotime($lic['created_at'] ?? 'now')); ?></td>
                    <td>
                        <div style="display:flex; gap:4px; flex-wrap:wrap;">
                            <button class="ssp-admin-btn ssp-admin-btn-secondary" style="padding:6px 12px; font-size:0.75rem;" onclick="adminEditLicenseExpiry(<?php echo (int)$lic['id']; ?>, <?php echo (int)$lic['duration_days']; ?>)">تغییر مدت</button>
                            <button class="ssp-admin-btn ssp-admin-btn-danger" style="padding:6px 12px; font-size:0.75rem;" onclick="adminRevokeLicense(<?php echo (int)$lic['id']; ?>)">حذف</button>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        </div>
        <?php endif; ?>
    </div>

    <!-- System Health -->
    <div class="ssp-admin-section">
        <h2>سلامت سیستم</h2>
        <div style="display:grid; grid-template-columns: repeat(auto-fill, minmax(250px, 1fr)); gap:12px;">
            <div style="padding:14px; background:#F8FAFC; border:1px solid #E2E8F0; border-radius:10px; display:flex; justify-content:space-between; align-items:center;">
                <span style="color:#64748B;">Cron Job</span>
                <?php if (wp_next_scheduled('ssp_process_queue_hook')) : ?>
                    <span class="ssp-admin-badge ok">فعال</span>
                <?php else : ?>
                    <div style="display:flex; align-items:center; gap:8px;">
                        <span class="ssp-admin-badge error">غیرفعال</span>
                        <button class="ssp-admin-btn" style="padding:6px 12px; font-size:0.75rem;" onclick="adminActivateCron()">فعال‌سازی</button>
                    </div>
                <?php endif; ?>
            </div>
            <div style="padding:14px; background:#F8FAFC; border:1px solid #E2E8F0; border-radius:10px; display:flex; justify-content:space-between; align-items:center;">
                <span style="color:#64748B;">PHP Version</span>
                <span class="ssp-admin-badge <?php echo version_compare(PHP_VERSION, '7.4', '>=') ? 'ok' : 'error'; ?>"><?php echo PHP_VERSION; ?></span>
            </div>
            <div style="padding:14px; background:#F8FAFC; border:1px solid #E2E8F0; border-radius:10px; display:flex; justify-content:space-between; align-items:center;">
                <span style="color:#64748B;">WP Curl</span>
                <span class="ssp-admin-badge <?php echo function_exists('wp_remote_post') ? 'ok' : 'error'; ?>"><?php echo function_exists('wp_remote_post') ? 'فعال' : 'غیرفعال'; ?></span>
            </div>
            <div style="padding:14px; background:#F8FAFC; border:1px solid #E2E8F0; border-radius:10px; display:flex; justify-content:space-between; align-items:center;">
                <span style="color:#64748B;">Memory Limit</span>
                <span class="ssp-admin-badge ok"><?php echo ini_get('memory_limit'); ?></span>
            </div>
        </div>
    </div>

    <!-- Network Diagnostics -->
    <div class="ssp-admin-section">
        <h2>عیب‌یابی شبکه</h2>
        <p style="color:#64748B; font-size:0.85rem; margin-bottom:16px;">
            بررسی کنید کدام سایت‌ها از سرور شما قابل دسترسی هستند. اگر OpenRouter مسدود است، از پروکسی استفاده کنید.
        </p>
        <button class="ssp-admin-btn" onclick="runNetworkDiagnostics()">شروع عیب‌یابی</button>
        <div id="diagnostics_output" class="ssp-admin-output"></div>
    </div>

    <!-- DNS over HTTPS Settings -->
    <div class="ssp-admin-section">
        <h2>DNS رایگان (حل مشکل DNS Poisoning)</h2>
        <p style="color:#64748B; font-size:0.85rem; margin-bottom:16px;">
            DNS over HTTPS درخواست‌های DNS را رمزنگاری می‌کند و از مسموم شدن جلوگیری می‌کند.
            <br><strong style="color:#059669;">⭐ معمولاً Cloudflare (1.1.1.1) در ایران کار می‌کند.</strong>
            <br><strong style="color:#D97706;">⚠️ توجه: DNS فقط مشکل DNS Poisoning را حل می‌کند. برای فیلترشکنی نیاز به پروکسی دارید.</strong>
        </p>

        <?php
        $doh_enabled = get_option('ssp_doh_enabled', 1);
        $doh_server = get_option('ssp_doh_server', 'cloudflare');
        ?>

        <div class="ssp-admin-form" style="flex-direction:column; align-items:flex-start;">
            <div class="field" style="display:flex; align-items:center; gap:10px;">
                <input type="checkbox" id="doh_enabled" <?php echo $doh_enabled ? 'checked' : ''; ?>>
                <label for="doh_enabled" style="font-weight:600; cursor:pointer;">فعال‌سازی DNS over HTTPS</label>
            </div>

            <div class="field" style="width:100%; max-width:500px;">
                <label>سرور DNS</label>
                <select id="doh_server" style="width:100%; padding:10px; border:1px solid #E2E8F0; border-radius:10px; max-height:200px; overflow-y:auto;">
                    <optgroup label="⭐ Cloudflare (توصیه شده - معمولاً کار می‌کند)">
                        <option value="cloudflare" <?php echo $doh_server === 'cloudflare' ? 'selected' : ''; ?>>Cloudflare 1.1.1.1 (سریع‌ترین)</option>
                        <option value="cloudflare_1001" <?php echo $doh_server === 'cloudflare_1001' ? 'selected' : ''; ?>>Cloudflare 1.0.0.1</option>
                        <option value="cloudflare_family" <?php echo $doh_server === 'cloudflare_family' ? 'selected' : ''; ?>>Cloudflare Family (فیلتر تبلیغات)</option>
                        <option value="cloudflare_security" <?php echo $doh_server === 'cloudflare_security' ? 'selected' : ''; ?>>Cloudflare Security (امنیت بالا)</option>
                    </optgroup>
                    <optgroup label="سایر سرورها (ممکن است کار نکنند)">
                        <option value="google" <?php echo $doh_server === 'google' ? 'selected' : ''; ?>>Google DNS</option>
                        <option value="quad9" <?php echo $doh_server === 'quad9' ? 'selected' : ''; ?>>Quad9</option>
                        <option value="adguard" <?php echo $doh_server === 'adguard' ? 'selected' : ''; ?>>AdGuard</option>
                        <option value="mullvad" <?php echo $doh_server === 'mullvad' ? 'selected' : ''; ?>>Mullvad</option>
                        <option value="nextdns" <?php echo $doh_server === 'nextdns' ? 'selected' : ''; ?>>NextDNS</option>
                        <option value="opendns" <?php echo $doh_server === 'opendns' ? 'selected' : ''; ?>>OpenDNS</option>
                        <option value="cleanbrowsing" <?php echo $doh_server === 'cleanbrowsing' ? 'selected' : ''; ?>>CleanBrowsing</option>
                    </optgroup>
                </select>
            </div>

            <div style="display:flex; gap:10px; margin-top:16px; flex-wrap:wrap;">
                <button class="ssp-admin-btn" onclick="saveDohSettings()">ذخیره تنظیمات</button>
                <button class="ssp-admin-btn ssp-admin-btn-secondary" onclick="testDohConnection()">تست DNS</button>
                <button class="ssp-admin-btn" style="background:#059669;" onclick="testTelegramDns()">🔍 تست دسترسی به تلگرام</button>
            </div>

            <div id="doh_test_output" class="ssp-admin-output"></div>
            <div id="telegram_dns_output" class="ssp-admin-output" style="margin-top:10px;"></div>
        </div>
    </div>

    <!-- Telegram Relay -->
    <div class="ssp-admin-section" style="border: 2px solid #7C3AED; background: rgba(124, 58, 237, 0.05);">
        <h2 style="color: #7C3AED;">🔗 رله تلگرام (هاست خارجی)</h2>
        <p style="color:#64748B; font-size:0.85rem; margin-bottom:16px;">
            <strong style="color:#7C3AED;">⭐ عبور از فیلترینگ تلگرام روی هاست‌های ایرانی.</strong>
            <br>یک فایل PHP ساده روی هاست خارجی آپلود کنید تا واسط بین افزونه و تلگرام باشد.
            <br><br>
            <strong>مراحل راه‌اندازی:</strong>
            <br>۱. فایل <code>relay/index.php</code> را از پوشه افزونه بردارید
            <br>۲. توکن ربات و Secret Key را در فایل تنظیم کنید
            <br>۳. فایل را روی هاست خارجی آپلود کنید
            <br>۴. آدرس فایل و Secret Key را در زیر وارد کنید
            <br>۵. تست اتصال را اجرا کنید
        </p>

        <?php
        $relay_settings = is_array($__tmp = get_option('ssp_telegram_relay', [])) ? $__tmp : [];
        ?>

        <div class="ssp-admin-form" style="flex-direction:column; align-items:flex-start;">
            <div class="field" style="display:flex; align-items:center; gap:10px;">
                <input type="checkbox" id="relay_enabled" <?php echo !empty($relay_settings['enabled']) ? 'checked' : ''; ?>>
                <label for="relay_enabled" style="font-weight:600; cursor:pointer;">فعال‌سازی رله تلگرام</label>
            </div>

            <div class="field" style="width:100%; max-width:500px;">
                <label>آدرس رله (URL فایل index.php)</label>
                <input type="url" id="worker_url" value="<?php echo esc_attr($relay_settings['worker_url'] ?? ''); ?>" placeholder="https://yourdomain.com/relay/index.php" style="width:100%; padding:10px; border:1px solid #E2E8F0; border-radius:10px;">
            </div>

            <div class="field" style="width:100%; max-width:500px;">
                <label>Secret Key</label>
                <input type="password" id="relay_secret_key" value="<?php echo esc_attr($relay_settings['secret_key'] ?? ''); ?>" placeholder="همان مقداری که در فایل index.php تنظیم کردید" style="width:100%; padding:10px; border:1px solid #E2E8F0; border-radius:10px;">
            </div>

            <div style="display:flex; gap:10px; margin-top:16px; flex-wrap:wrap;">
                <button class="ssp-admin-btn" style="background:#7C3AED;" onclick="saveRelaySettings()">ذخیره تنظیمات</button>
                <button class="ssp-admin-btn ssp-admin-btn-secondary" onclick="testRelayConnection()">تست اتصال</button>
            </div>

            <div id="relay_test_output" class="ssp-admin-output"></div>
        </div>
    </div>

    <!-- Proxy Settings -->
    <div class="ssp-admin-section" style="border: 2px solid #F59E0B; background: rgba(245, 158, 11, 0.05);">
        <h2 style="color: #D97706;">⭐ پروکسی (مهم‌ترین بخش برای عبور از فیلتر)</h2>
        <p style="color:#64748B; font-size:0.85rem; margin-bottom:16px;">
            <strong style="color:#DC2626;">⚠️ اگر سرور شما شدیداً فیلتر است (مثل نتیجه تست بالا)، فقط پروکسی می‌تواند مشکل را حل کند.</strong>
            <br><br>
            <strong>پروکسی چیست؟</strong> یک سرور واسط خارج از ایران که درخواست‌های شما را به مقصد می‌رساند.
            <br><br>
            <strong>منابع پروکسی رایگان/ارزان:</strong>
            <br>• VPS در آلمان، هلند، فنلاند، سنگاپور
            <br>• ارائه‌دهندگان پروکسی مانند Shadowsocks, V2Ray, Xray
            <br>• سرویس‌های VPN مانند V2RayN, NekoBox, Hiddify
        </p>

        <?php
        $proxy_settings = is_array($__tmp = get_option('ssp_proxy_settings', [])) ? $__tmp : [];
        ?>

        <div class="ssp-admin-form" style="flex-direction:column; align-items:flex-start;">
            <div class="field" style="display:flex; align-items:center; gap:10px;">
                <input type="checkbox" id="proxy_enabled" <?php echo !empty($proxy_settings['enabled']) ? 'checked' : ''; ?>>
                <label for="proxy_enabled" style="font-weight:600; cursor:pointer;">فعال‌سازی پروکسی</label>
            </div>

            <div style="display:grid; grid-template-columns: 1fr 1fr 1fr; gap:12px; width:100%; max-width:600px;">
                <div class="field">
                    <label>نوع پروکسی</label>
                    <select id="proxy_type" style="width:100%; padding:10px; border:1px solid #E2E8F0; border-radius:10px;">
                        <option value="http" <?php echo ($proxy_settings['type'] ?? '') === 'http' ? 'selected' : ''; ?>>HTTP</option>
                        <option value="socks5" <?php echo ($proxy_settings['type'] ?? '') === 'socks5' ? 'selected' : ''; ?>>SOCKS5</option>
                    </select>
                </div>
                <div class="field">
                    <label>آدرس سرور پروکسی</label>
                    <input type="text" id="proxy_host" value="<?php echo esc_attr($proxy_settings['host'] ?? ''); ?>" placeholder="مثلاً: 123.45.67.89" style="width:100%;">
                </div>
                <div class="field">
                    <label>پورت</label>
                    <input type="number" id="proxy_port" value="<?php echo esc_attr($proxy_settings['port'] ?? ''); ?>" placeholder="مثلاً: 8080" style="width:100%;">
                </div>
            </div>

            <div style="display:grid; grid-template-columns: 1fr 1fr; gap:12px; width:100%; max-width:600px; margin-top:12px;">
                <div class="field">
                    <label>نام کاربری (اختیاری)</label>
                    <input type="text" id="proxy_username" value="<?php echo esc_attr($proxy_settings['username'] ?? ''); ?>" placeholder="اگر نیاز به احراز هویت دارد" style="width:100%;">
                </div>
                <div class="field">
                    <label>رمز عبور (اختیاری)</label>
                    <input type="password" id="proxy_password" value="<?php echo esc_attr($proxy_settings['password'] ?? ''); ?>" placeholder="رمز عبور پروکسی" style="width:100%;">
                </div>
            </div>

            <div style="display:flex; gap:10px; margin-top:16px;">
                <button class="ssp-admin-btn" onclick="saveProxySettings()">ذخیره تنظیمات</button>
                <button class="ssp-admin-btn ssp-admin-btn-secondary" onclick="testProxyConnection()">تست اتصال پروکسی</button>
            </div>

            <div id="proxy_test_output" class="ssp-admin-output"></div>
        </div>
    </div>
</div>

<script>
(function() {
    var ajaxurl = '<?php echo admin_url('admin-ajax.php'); ?>';
    var nonce = '<?php echo $nonce; ?>';

    window.adminGenerateLicense = function() {
        var count = document.getElementById('admin_license_count').value;
        var duration = document.getElementById('admin_license_duration').value;
        var out = document.getElementById('admin_license_output');
        out.className = 'ssp-admin-output show';
        out.textContent = 'در حال ایجاد...';

        var fd = new FormData();
        fd.append('action', 'ssp_generate_license');
        fd.append('security', nonce);
        fd.append('count', count);
        fd.append('duration', duration);

        fetch(ajaxurl, {method: 'POST', body: fd})
            .then(function(r) { return r.json(); })
            .then(function(res) {
                if (res.success) {
                    out.textContent = 'لایسنس‌ها با موفقیت ایجاد شدند:\n\n' + res.data.keys.join('\n');
                    setTimeout(function() { location.reload(); }, 1500);
                } else {
                    out.textContent = 'خطا: ' + res.data.message;
                }
            })
            .catch(function() {
                out.textContent = 'خطا در ارتباط با سرور';
            });
    };

    window.adminRevokeLicense = function(id) {
        if (!confirm('آیا از حذف این لایسنس مطمئن هستید؟')) return;
        var fd = new FormData();
        fd.append('action', 'ssp_revoke_license');
        fd.append('security', nonce);
        fd.append('license_id', id);
        fetch(ajaxurl, {method: 'POST', body: fd})
            .then(function(r) { return r.json(); })
            .then(function(res) {
                if (res.success) { location.reload(); }
                else alert(res.data.message);
            })
            .catch(function() { alert('خطا در ارتباط با سرور'); });
    };

    window.adminEditLicenseExpiry = function(id, currentDays) {
        var newDays = prompt('مدت لایسنس (روز) را وارد کنید:', currentDays);
        if (newDays === null || newDays === '') return;
        newDays = parseInt(newDays);
        if (isNaN(newDays) || newDays < 1) { alert('عدد نامعتبر'); return; }
        var fd = new FormData();
        fd.append('action', 'ssp_update_license_expiry');
        fd.append('security', nonce);
        fd.append('license_id', id);
        fd.append('duration_days', newDays);
        fetch(ajaxurl, {method: 'POST', body: fd})
            .then(function(r) { return r.json(); })
            .then(function(res) {
                if (res.success) {
                    var el = document.getElementById('lic-duration-' + id);
                    if (el) el.textContent = newDays + ' روز';
                    alert(res.data.message);
                } else {
                    alert(res.data.message);
                }
            })
            .catch(function() { alert('خطا در ارتباط با سرور'); });
    };

    window.adminProcessQueue = function() {
        var fd = new FormData();
        fd.append('action', 'ssp_manual_process');
        fd.append('security', nonce);
        fetch(ajaxurl, {method: 'POST', body: fd})
            .then(function(r) { return r.json(); })
            .then(function(res) {
                alert(res.data ? res.data.message : 'انجام شد');
                location.reload();
            })
            .catch(function() { alert('خطا در ارتباط با سرور'); });
    };

    window.adminActivateCron = function() {
        if (!confirm('آیا می‌خواهید Cron Jobs را فعال کنید؟')) return;
        var fd = new FormData();
        fd.append('action', 'ssp_activate_cron');
        fd.append('security', nonce);
        fetch(ajaxurl, {method: 'POST', body: fd})
            .then(function(r) { return r.json(); })
            .then(function(res) {
                alert(res.data ? res.data.message : 'انجام شد');
                location.reload();
            })
            .catch(function() { alert('خطا در ارتباط با سرور'); });
    };

    // === Utility Functions ===
    function escapeHtml(s) { var d = document.createElement('div'); d.appendChild(document.createTextNode(s)); return d.innerHTML; }

    // === User Browser Functions ===
    var userSearchTimeout = null;

    window.adminSearchUsers = function() {
        clearTimeout(userSearchTimeout);
        userSearchTimeout = setTimeout(function() {
            adminLoadUsers();
        }, 300);
    };

    window.adminLoadUsers = function() {
        var search = document.getElementById('admin_user_search').value;
        var fd = new FormData();
        fd.append('action', 'ssp_get_user_list');
        fd.append('security', nonce);
        fd.append('search', search);

        fetch(ajaxurl, {method: 'POST', body: fd})
            .then(function(r) { return r.json(); })
            .then(function(res) {
                if (res.success) {
                    adminRenderUserList(res.data.users);
                }
            })
            .catch(function() { alert('خطا در بارگذاری کاربران'); });
    };

    window.adminRenderUserList = function(users) {
        var container = document.getElementById('admin_user_list');
        if (!users || users.length === 0) {
            container.innerHTML = '<div class="ssp-admin-empty">کاربری یافت نشد.</div>';
            return;
        }

        var html = '<table style="width:100%; border-collapse:collapse; font-size:0.85rem;">';
        html += '<thead><tr style="background:var(--bg-alt); text-align:right;">';
        html += '<th style="padding:8px; border:1px solid var(--border);">نام</th>';
        html += '<th style="padding:8px; border:1px solid var(--border);">ایمیل</th>';
        html += '<th style="padding:8px; border:1px solid var(--border);">پلن</th>';
        html += '<th style="padding:8px; border:1px solid var(--border);">پیام‌رسان</th>';
        html += '<th style="padding:8px; border:1px solid var(--border);">بات</th>';
        html += '<th style="padding:8px; border:1px solid var(--border);">سایت</th>';
        html += '<th style="padding:8px; border:1px solid var(--border);">پروفایل</th>';
        html += '<th style="padding:8px; border:1px solid var(--border);">عملیات</th>';
        html += '</tr></thead><tbody>';

        users.forEach(function(user) {
            var planBadge = user.plan === 'pro'
                ? '<span style="background:#22c55e; color:white; padding:2px 8px; border-radius:4px; font-size:0.75rem;">Pro</span>'
                : '<span style="background:#94a3b8; color:white; padding:2px 8px; border-radius:4px; font-size:0.75rem;">Free</span>';

            html += '<tr style="border:1px solid var(--border);">';
            html += '<td style="padding:8px; border:1px solid var(--border); font-weight:600;">' + escapeHtml(user.name) + '</td>';
            html += '<td style="padding:8px; border:1px solid var(--border); color:var(--text-muted);">' + escapeHtml(user.email) + '</td>';
            html += '<td style="padding:8px; border:1px solid var(--border); text-align:center;">' + planBadge + '</td>';
            html += '<td style="padding:8px; border:1px solid var(--border); text-align:center;">' + user.messenger_count + '</td>';
            html += '<td style="padding:8px; border:1px solid var(--border); text-align:center;">' + user.bot_count + '</td>';
            html += '<td style="padding:8px; border:1px solid var(--border); text-align:center;">' + user.wp_site_count + '</td>';
            html += '<td style="padding:8px; border:1px solid var(--border); text-align:center;">' + user.profile_count + '</td>';
            html += '<td style="padding:8px; border:1px solid var(--border); text-align:center;">';
            html += '<button class="ssp-admin-btn" onclick="adminImpersonateUser(' + user.id + ')" style="font-size:0.8rem; padding:4px 12px;">مشاهده پورتال</button>';
            html += '</td>';
            html += '</tr>';
        });

        html += '</tbody></table>';
        container.innerHTML = html;
    };

    window.adminImpersonateUser = function(userId) {
        if (!confirm('آیا می‌خواهید پورتال این کاربر را مشاهده کنید؟')) return;

        var fd = new FormData();
        fd.append('action', 'ssp_impersonate_user');
        fd.append('security', nonce);
        fd.append('target_user_id', userId);

        fetch(ajaxurl, {method: 'POST', body: fd})
            .then(function(r) { return r.json(); })
            .then(function(res) {
                if (res.success) {
                    window.location.href = res.data.redirect;
                } else {
                    alert(res.data.message);
                }
            })
            .catch(function() { alert('خطا در ارتباط با سرور'); });
    };

    // Load users on page load
    document.addEventListener('DOMContentLoaded', function() {
        if (document.getElementById('admin_user_list')) {
            adminLoadUsers();
        }
    });

    window.saveRelaySettings = function() {
        var fd = new FormData();
        fd.append('action', 'ssp_save_telegram_relay');
        fd.append('security', nonce);
        fd.append('relay_enabled', document.getElementById('relay_enabled').checked ? '1' : '0');
        fd.append('worker_url', document.getElementById('worker_url').value);
        fd.append('secret_key', document.getElementById('relay_secret_key').value);

        fetch(ajaxurl, {method: 'POST', body: fd})
            .then(function(r) { return r.json(); })
            .then(function(res) {
                alert(res.data ? res.data.message : 'ذخیره شد');
            })
            .catch(function() { alert('خطا در ارتباط با سرور'); });
    };

    window.testRelayConnection = function() {
        var out = document.getElementById('relay_test_output');
        out.className = 'ssp-admin-output show';
        out.textContent = 'در حال تست اتصال به رله تلگرام...\n\nلطفاً صبر کنید...';
        out.style.color = '#64748B';

        var fd = new FormData();
        fd.append('action', 'ssp_test_telegram_relay');
        fd.append('security', nonce);

        fetch(ajaxurl, {method: 'POST', body: fd})
            .then(function(r) { return r.json(); })
            .then(function(res) {
                if (res.success) {
                    out.textContent = '✓ ' + res.data.message + '\n\nمی‌توانید از رله تلگرام برای تمام عملیات تلگرام استفاده کنید.';
                    out.style.color = '#7C3AED';
                } else {
                    out.textContent = '✗ ' + res.data.message + '\n\nنکات عیب‌یابی:\n' +
                        '• آدرس URL را در مرورگر باز کنید (باید خطا نشان دهد)\n' +
                        '• Secret Key باید با مقدار در فایل relay/index.php یکسان باشد\n' +
                        '• فایل relay/index.php درست آپلود شده باشد\n' +
                        '• توکن ربات در فایل relay/index.php تنظیم شده باشد\n' +
                        '• DNS over HTTPS را فعال کنید (تنظیمات DNS)\n' +
                        '• اگر پروکسی دارید، آن را فعال کنید';
                    out.style.color = '#DC2626';
                }
            })
            .catch(function() {
                out.textContent = 'خطا در ارتباط با سرور';
                out.style.color = '#DC2626';
            });
    };

    window.saveProxySettings = function() {
        var fd = new FormData();
        fd.append('action', 'ssp_save_proxy_settings');
        fd.append('security', nonce);
        fd.append('proxy_enabled', document.getElementById('proxy_enabled').checked ? '1' : '0');
        fd.append('proxy_type', document.getElementById('proxy_type').value);
        fd.append('proxy_host', document.getElementById('proxy_host').value);
        fd.append('proxy_port', document.getElementById('proxy_port').value);
        fd.append('proxy_username', document.getElementById('proxy_username').value);
        fd.append('proxy_password', document.getElementById('proxy_password').value);

        fetch(ajaxurl, {method: 'POST', body: fd})
            .then(function(r) { return r.json(); })
            .then(function(res) {
                alert(res.data ? res.data.message : 'ذخیره شد');
            })
            .catch(function() { alert('خطا در ارتباط با سرور'); });
    };

    window.testProxyConnection = function() {
        var out = document.getElementById('proxy_test_output');
        out.className = 'ssp-admin-output show';
        out.textContent = 'در حال تست اتصال پروکسی...';

        var fd = new FormData();
        fd.append('action', 'ssp_test_proxy');
        fd.append('security', nonce);

        fetch(ajaxurl, {method: 'POST', body: fd})
            .then(function(r) { return r.json(); })
            .then(function(res) {
                if (res.success) {
                    out.textContent = '✓ اتصال موفق!\n\nIP خروجی: ' + (res.data.ip || 'نامشخص');
                    out.style.color = '#059669';
                } else {
                    out.textContent = '✗ خطا: ' + res.data.message;
                    out.style.color = '#DC2626';
                }
            })
            .catch(function() {
                out.textContent = 'خطا در ارتباط با سرور';
                out.style.color = '#DC2626';
            });
    };

    window.runNetworkDiagnostics = function() {
        var out = document.getElementById('diagnostics_output');
        out.className = 'ssp-admin-output show';
        out.textContent = 'در حال بررسی اتصال به سرورهای مختلف...\n\nلطفاً صبر کنید...';
        out.style.color = '#64748B';

        var fd = new FormData();
        fd.append('action', 'ssp_network_diagnostics');
        fd.append('security', nonce);

        fetch(ajaxurl, {method: 'POST', body: fd})
            .then(function(r) { return r.json(); })
            .then(function(res) {
                if (res.success) {
                    var output = 'نتایج عیب‌یابی شبکه:\n';
                    output += '═══════════════════════════════════════════\n\n';

                    var results = res.data.results;
                    var hasError = false;
                    var dnsWorks = false;
                    var openrouterWorks = false;
                    var anyApiWorks = false;

                    for (var name in results) {
                        var r = results[name];
                        var icon = r.status === 'success' ? '✓' : (r.status === 'warning' ? '⚠' : '✗');

                        output += icon + ' ' + name + '\n';
                        if (r.status === 'success') {
                            output += '   وضعیت: موفق\n';
                            if (name.indexOf('DNS') >= 0) dnsWorks = true;
                            if (name === 'OpenRouter API') openrouterWorks = true;
                            if (name.indexOf('API') >= 0) anyApiWorks = true;
                        } else {
                            output += '   وضعیت: ' + (r.message || 'خطا') + '\n';
                            hasError = true;
                        }
                        if (r.time) output += '   زمان پاسخ: ' + r.time + '\n';
                        output += '\n';
                    }

                    output += '═══════════════════════════════════════════\n\n';

                    output += 'نتیجه‌گیری:\n';
                    if (openrouterWorks) {
                        output += '✓ OpenRouter قابل دسترسی است. مشکل احتمالاً در API Key یا تنظیمات است.';
                    } else if (anyApiWorks) {
                        output += '⚠ OpenRouter مسدود است ولی سایر API ها کار می‌کنند.\n';
                        output += '→ از OpenAI یا Gemini استفاده کنید.';
                    } else if (dnsWorks) {
                        output += '⚠ DNS کار می‌کند ولی API ها مسدود هستند.\n';
                        output += '→ فایروال سرور IP های خارجی را مسدود کرده.\n';
                        output += '→ حتماً به پروکسی نیاز دارید.';
                    } else {
                        output += '✗ فایروال سرور همه اتصالات خارجی را مسدود کرده!\n';
                        output += '→ فقط پروکسی می‌تواند مشکل را حل کند.\n';
                        output += '→ با پشتیبانی هاست تماس بگیرید.';
                    }

                    out.textContent = output;
                    out.style.color = hasError ? '#D97706' : '#059669';
                } else {
                    out.textContent = 'خطا: ' + (res.data.message || 'خطای ناشناخته');
                    out.style.color = '#DC2626';
                }
            })
            .catch(function() {
                out.textContent = 'خطا در ارتباط با سرور';
                out.style.color = '#DC2626';
            });
    };

    window.saveDohSettings = function() {
        var fd = new FormData();
        fd.append('action', 'ssp_save_doh_settings');
        fd.append('security', nonce);
        fd.append('doh_enabled', document.getElementById('doh_enabled').checked ? '1' : '0');
        fd.append('doh_server', document.getElementById('doh_server').value);

        fetch(ajaxurl, {method: 'POST', body: fd})
            .then(function(r) { return r.json(); })
            .then(function(res) {
                alert(res.data ? res.data.message : 'ذخیره شد');
            })
            .catch(function() { alert('خطا در ارتباط با سرور'); });
    };

    window.testDohConnection = function() {
        var out = document.getElementById('doh_test_output');
        out.className = 'ssp-admin-output show';
        out.textContent = 'در حال تست DNS over HTTPS...\n\nلطفاً صبر کنید...';
        out.style.color = '#64748B';

        var fd = new FormData();
        fd.append('action', 'ssp_test_doh');
        fd.append('security', nonce);
        fd.append('doh_server', document.getElementById('doh_server').value);

        fetch(ajaxurl, {method: 'POST', body: fd})
            .then(function(r) { return r.json(); })
            .then(function(res) {
                if (res.success) {
                    out.textContent = '✓ DNS با موفقیت حل شد!\n\n';
                    out.textContent += 'دامنه: ' + res.data.domain + '\n';
                    out.textContent += 'آدرس IP: ' + res.data.ip + '\n';
                    out.textContent += 'زمان پاسخ: ' + res.data.time;
                    out.style.color = '#059669';
                } else {
                    out.textContent = '✗ خطا: ' + res.data.message;
                    out.style.color = '#DC2626';
                }
            })
            .catch(function() {
                out.textContent = 'خطا در ارتباط با سرور';
                out.style.color = '#DC2626';
            });
    };

    window.testTelegramDns = function() {
        var out = document.getElementById('telegram_dns_output');
        out.className = 'ssp-admin-output show';
        out.style.fontFamily = 'monospace';
        out.style.whiteSpace = 'pre';
        out.style.direction = 'ltr';
        out.style.textAlign = 'left';
        out.style.fontSize = '12px';
        out.style.lineHeight = '1.6';
        out.style.maxHeight = '600px';
        out.style.overflowY = 'auto';
        out.style.padding = '16px';
        out.style.background = '#0F172A';
        out.style.color = '#E2E8F0';
        out.style.borderRadius = '10px';
        out.style.border = '1px solid #334155';

        var output = '';
        output += '╔══════════════════════════════════════════════════════════════╗\n';
        output += '║  🔍 تست زنده دسترسی به تلگرام و بله از تمام سرورهای DNS    ║\n';
        output += '╚══════════════════════════════════════════════════════════════╝\n\n';
        output += '📡 دامنه‌های تست:\n';
        output += '   • api.telegram.org (تلگرام)\n';
        output += '   • tapi.bale.ai (بله)\n';
        output += '   • docs.bale.ai (مستندات بله)\n';
        output += '   • api.openai.com (OpenAI/GPT)\n';
        output += '   • api.anthropic.com (Anthropic/Claude)\n\n';
        output += '══════════════════════════════════════════════════════════════\n\n';
        out.textContent = output;

        var fd = new FormData();
        fd.append('action', 'ssp_test_telegram_dns');
        fd.append('security', nonce);

        fetch(ajaxurl, {method: 'POST', body: fd})
            .then(function(r) { return r.json(); })
            .then(function(res) {
                if (!res.success || !res.data.servers) {
                    out.textContent += '❌ خطا: ' + (res.data.message || 'خطای دریافت لیست سرورها');
                    out.style.color = '#EF4444';
                    return;
                }

                var servers = Object.keys(res.data.servers);
                var serverList = res.data.servers;
                var results = {};
                var workingServers = [];
                var currentIndex = 0;
                var totalCount = servers.length;

                output += '🔄 شروع تست ' + totalCount + ' سرور DNS...\n\n';
                output += '┌────────────────────────────────────────────────────────────┐\n';
                output += '│  نتایج زنده (هر سرور بلافاصله نمایش داده می‌شود)          │\n';
                output += '└────────────────────────────────────────────────────────────┘\n\n';
                out.textContent = output;

                function testNextServer() {
                    if (currentIndex >= servers.length) {
                        showSummary();
                        return;
                    }

                    var serverName = servers[currentIndex];
                    var progress = '[' + (currentIndex + 1) + '/' + totalCount + ']';

                    output += progress + ' ⏳ تست ' + serverName + '...\n';
                    out.textContent = output;
                    out.scrollTop = out.scrollHeight;

                    var testFd = new FormData();
                    testFd.append('action', 'ssp_test_telegram_dns');
                    testFd.append('security', nonce);
                    testFd.append('server', serverName);

                    fetch(ajaxurl, {method: 'POST', body: testFd})
                        .then(function(r) { return r.json(); })
                        .then(function(res) {
                            if (res.success && res.data.results) {
                                var serverResults = res.data.results;
                                var overall = res.data.overall;
                                var icon = overall === 'success' ? '✅' : '❌';

                                output = output.replace(progress + ' ⏳ تست ' + serverName + '...\n', '');

                                output += progress + ' ' + icon + ' ' + serverName.toUpperCase() + '\n';

                                Object.keys(serverResults).forEach(function(domain) {
                                    var dr = serverResults[domain];
                                    if (dr.status === 'success') {
                                        output += '        ✓ ' + domain + ': ' + dr.ip + ' (' + dr.time + 'ms)\n';
                                    } else {
                                        output += '        ✗ ' + domain + ': ' + (dr.message || 'ناموفق') + ' (' + dr.time + 'ms)\n';
                                    }
                                });
                                output += '\n';

                                results[serverName] = {results: serverResults, overall: overall};
                                if (overall === 'success') {
                                    workingServers.push(serverName);
                                }
                            } else {
                                output = output.replace(progress + ' ⏳ تست ' + serverName + '...\n', '');
                                output += progress + ' ❌ ' + serverName + ': خطا در تست\n\n';
                                results[serverName] = {results: {}, overall: 'error'};
                            }

                            out.textContent = output;
                            out.scrollTop = out.scrollHeight;
                            currentIndex++;

                            setTimeout(testNextServer, 100);
                        })
                        .catch(function() {
                            output = output.replace(progress + ' ⏳ تست ' + serverName + '...\n', '');
                            output += progress + ' ❌ ' + serverName + ': خطای ارتباط\n\n';
                            results[serverName] = {results: {}, overall: 'error'};
                            out.textContent = output;
                            out.scrollTop = out.scrollHeight;
                            currentIndex++;
                            setTimeout(testNextServer, 100);
                        });
                }

                function showSummary() {
                    output += '\n══════════════════════════════════════════════════════════════\n\n';
                    output += '┌────────────────────────────────────────────────────────────┐\n';
                    output += '│  📊 خلاصه نهایی                                          │\n';
                    output += '└────────────────────────────────────────────────────────────┘\n\n';

                    var workingCount = workingServers.length;
                    var failedCount = totalCount - workingCount;

                    output += '  📡 تعداد کل سرورها:     ' + totalCount + '\n';
                    output += '  ✅ سرورهای کارآمد:      ' + workingCount + '\n';
                    output += '  ❌ سرورهای غیرفعال:     ' + failedCount + '\n\n';

                    if (workingServers.length > 0) {
                        output += '┌────────────────────────────────────────────────────────────┐\n';
                        output += '│  🎯 سرورهای پیشنهادی (بر اساس سرعت)                     │\n';
                        output += '└────────────────────────────────────────────────────────────┘\n\n';

                        workingServers.slice(0, 8).forEach(function(s, i) {
                            var medal = i === 0 ? '🥇' : (i === 1 ? '🥈' : (i === 2 ? '🥉' : '  '));
                            output += '  ' + medal + ' ' + (i+1) + '. ' + s + '\n';
                        });

                        output += '\n  💡 سرور اول را از لیست بالا انتخاب و ذخیره کنید.\n';
                    } else {
                        output += '  ⚠️ هیچ سرور DNS نتوانست به تلگرام/بله دسترسی پیدا کند.\n\n';
                        output += '  💡 راه‌حل‌ها:\n';
                        output += '     1. از پروکسی (HTTP/SOCKS5) استفاده کنید\n';
                        output += '     2. با پشتیبانی هاست تماس بگیرید\n';
                        output += '     3. از VPS خارج از کشور استفاده کنید\n';
                    }

                    output += '\n══════════════════════════════════════════════════════════════\n';
                    output += '  ⏱️ تست در ' + new Date().toLocaleTimeString('fa-IR') + ' به پایان رسید\n';

                    out.textContent = output;
                    out.style.color = workingServers.length > 0 ? '#10B981' : '#F59E0B';
                    out.scrollTop = out.scrollHeight;
                }

                testNextServer();
            })
            .catch(function() {
                out.textContent = '❌ خطا در ارتباط با سرور';
                out.style.color = '#EF4444';
            });
    };
})();
</script>
