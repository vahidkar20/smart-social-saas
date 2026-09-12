const fs = require('fs');
let code = fs.readFileSync('includes/views/portal.php', 'utf8');

const target = `<div style="font-weight:700; color:var(--error); margin-bottom:6px;">علت خطا:</div>
                                                    <pre style="white-space:pre-wrap; word-break:break-all; margin:0; font-size:0.8rem; color:var(--text); direction:ltr;"><?php echo esc_html($log['response']); ?></pre>`;

const replacement = `<?php
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
                                                    <?php endif; ?>`;

code = code.replace(target, replacement);
fs.writeFileSync('includes/views/portal.php', code);
console.log('Hints added');
