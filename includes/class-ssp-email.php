<?php
/**
 * SSP Email Trait - Email notifications
 */
trait SSP_Email {

    private function send_email_notification($user_id, $type, $data = []) {
        $settings = get_user_meta($user_id, 'ssp_email_settings', true) ?: [];
        if (empty($settings['enabled'])) return false;

        $email = $settings['email'] ?? '';
        if (empty($email)) {
            $user = get_userdata($user_id);
            $email = $user ? $user->user_email : '';
        }
        if (empty($email)) return false;

        $send = false;
        $subject = '';
        $body = '';

        switch ($type) {
            case 'queue_failed':
                if (empty($settings['on_failure'])) return false;
                $subject = 'خطا در ارسال پیام - Smart Automation';
                $body = '<div style="font-family:Tahoma,sans-serif; direction:rtl; padding:20px;">';
                $body .= '<h2 style="color:#EF4444;">خطا در ارسال پیام</h2>';
                $body .= '<p>تعداد <strong>' . ($data['count'] ?? 0) . '</strong> پیام در صف با خطا مواجه شد.</p>';
                $body .= '<p style="color:#64748B;">خطا: ' . esc_html($data['error'] ?? 'نامشخص') . '</p>';
                $body .= '<p>لطفاً تنظیمات پیام‌رسان‌ها و اتصال شبکه را بررسی کنید.</p>';
                $body .= '</div>';
                $send = true;
                break;
            case 'license_expiring':
                if (empty($settings['on_license_expiry'])) return false;
                $days = $data['days_left'] ?? 0;
                $subject = 'اشتراک شما منقضی می‌شود - Smart Automation';
                $body = '<div style="font-family:Tahoma,sans-serif; direction:rtl; padding:20px;">';
                $body .= '<h2 style="color:#F59E0B;">اشتراک در حال انقضا</h2>';
                $body .= '<p>اشتراک Pro شما تا <strong>' . $days . ' روز</strong> دیگر منقضی می‌شود.</p>';
                $body .= '<p>برای حفظ امکانات حرفه‌ای، لطفاً اشتراک خود را تمدید کنید.</p>';
                $body .= '</div>';
                $send = true;
                break;
            case 'license_expired':
                if (empty($settings['on_license_expiry'])) return false;
                $subject = 'اشتراک شما منقضی شد - Smart Automation';
                $body = '<div style="font-family:Tahoma,sans-serif; direction:rtl; padding:20px;">';
                $body .= '<h2 style="color:#EF4444;">اشتراک منقضی شد</h2>';
                $body .= '<p>اشتراک Pro شما منقضی شده و به پلن رایگان بازگشتید.</p>';
                $body .= '<p>برای دسترسی مجدد به امکانات حرفه‌ای، لطفاً اشتراک جدید فعال کنید.</p>';
                $body .= '</div>';
                $send = true;
                break;
            case 'daily_summary':
                if (empty($settings['daily_summary'])) return false;
                $logs = $this->get_global_items('logs');
                $today = current_time('Y-m-d');
                $today_logs = array_filter($logs, function($l) use ($user_id, $today) {
                    return (int)$l['user_id'] === (int)$user_id && substr($l['created_at'], 0, 10) === $today;
                });
                $total = count($today_logs);
                $success = count(array_filter($today_logs, function($l) { return $l['status'] === 'success'; }));
                $failed = count(array_filter($today_logs, function($l) { return $l['status'] === 'error'; }));
                $subject = 'خلاصه روزانه - Smart Automation';
                $body = '<div style="font-family:Tahoma,sans-serif; direction:rtl; padding:20px;">';
                $body .= '<h2 style="color:#4F46E5;">خلاصه ارسال‌های امروز</h2>';
                $body .= '<div style="display:flex; gap:20px; margin:20px 0;">';
                $body .= '<div style="padding:16px; background:#F0FDF4; border-radius:10px; text-align:center;"><div style="font-size:24px; font-weight:700; color:#10B981;">' . $total . '</div><div style="color:#64748B; font-size:12px;">کل</div></div>';
                $body .= '<div style="padding:16px; background:#F0FDF4; border-radius:10px; text-align:center;"><div style="font-size:24px; font-weight:700; color:#10B981;">' . $success . '</div><div style="color:#64748B; font-size:12px;">موفق</div></div>';
                $body .= '<div style="padding:16px; background:#FEF2F2; border-radius:10px; text-align:center;"><div style="font-size:24px; font-weight:700; color:#EF4444;">' . $failed . '</div><div style="color:#64748B; font-size:12px;">خطا</div></div>';
                $body .= '</div></div>';
                $send = true;
                break;
        }

        if ($send) {
            $headers = ['Content-Type: text/html; charset=UTF-8'];
            wp_mail($email, $subject, $body, $headers);
        }
        return $send;
    }

    public function send_daily_summary() {
        $users = get_users(['fields' => 'ID']);
        foreach ($users as $user_id) {
            $this->send_email_notification($user_id, 'daily_summary');
        }
    }
}
