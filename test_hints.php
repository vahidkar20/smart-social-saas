<?php
function get_ssp_error_hint($response) {
    $response = strtolower($response);
    if (strpos($response, 'chat not found') !== false || strpos($response, 'peer not found') !== false) {
        return 'شناسه (ID) چت نامعتبر است. بررسی کنید که آیدی کانال/گروه صحیح باشد و ربات شما عضو آن باشد (یا در صورت پرایوت بودن، شناسه عددی درست باشد).';
    }
    if (strpos($response, 'kicked') !== false || strpos($response, 'blocked') !== false) {
        return 'ربات از گروه یا کانال اخراج شده است، یا کاربر ربات را بلاک کرده است.';
    }
    if (strpos($response, 'not enough rights') !== false || strpos($response, 'admin') !== false || strpos($response, 'not admin') !== false) {
        return 'ربات شما دسترسی لازم برای ارسال پیام را ندارد. لطفاً مطمئن شوید ربات مدیر (Admin) کانال یا گروه است.';
    }
    if (strpos($response, 'curl error 28') !== false || strpos($response, 'timeout') !== false) {
        return 'خطای تایم‌اوت! ارتباط سرور شما با پیام‌رسان برقرار نشد. این مشکل معمولاً در هاست‌های ایرانی رخ می‌دهد. بررسی کنید آیا نیاز به پروکسی دارید؟';
    }
    if (strpos($response, 'unauthorized') !== false || strpos($response, 'invalid token') !== false) {
        return 'توکن (Token) ربات شما نامعتبر است. لطفاً توکن را در بخش پیام‌رسان‌ها بررسی کنید.';
    }
    if (strpos($response, 'too long') !== false) {
        return 'متن پیام شما طولانی‌تر از حد مجاز پیام‌رسان است. لطفاً متن را کوتاه‌تر کنید.';
    }
    if (strpos($response, 'file identifier') !== false || strpos($response, 'wrong file') !== false) {
        return 'فایل مدیا (عکس/ویدیو) توسط پیام‌رسان پذیرفته نشد. ممکن است حجم آن زیاد باشد یا فرمت نامعتبری داشته باشد.';
    }
    if (strpos($response, 'api key not valid') !== false || strpos($response, 'invalid api key') !== false) {
        return 'کلید دسترسی (API Key) هوش مصنوعی معتبر نیست. لطفاً آن را در بخش تنظیمات AI بررسی کنید.';
    }
    return null;
}
echo "Helper created.";
