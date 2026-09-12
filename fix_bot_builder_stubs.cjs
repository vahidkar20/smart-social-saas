const fs = require('fs');
let code = fs.readFileSync('assets/js/portal-core.js', 'utf8');

// We will replace saveBotConfig entirely.
const regexSaveBotConfig = /window\.saveBotConfig = function\(\) \{[\s\S]*?(?=window\.testBotConnection = function\(\))/;
const regexTestBotConnection = /window\.testBotConnection = function\(\) \{[\s\S]*?(?=window\.switchSeoSubtab = function\(\))/;

// Let's check other stubs
const regexTestBotWebhook = /window\.testBotWebhook = function\(\) \{[\s\S]*?(?=window\.setBotMenu = function\(\))/;
const regexSetBotMenu = /window\.setBotMenu = function\(\) \{[\s\S]*?(?=window\.getBotStats = function\(\))/;
const regexGetBotStats = /window\.getBotStats = function\(\) \{[\s\S]*?(?=window\.editCommand = function)/;

const replacementSaveBotConfig = `window.saveBotConfig = function() {
    let btn = document.querySelector('[onclick="saveBotConfig()"]');
    if(!btn) return;
    setBtnLoading(btn, true);
    let fd = new FormData();
    fd.append('action', 'ssp_save_bot_config');
    fd.append('security', nonce);
    fd.append('bot_id', document.getElementById('bot_editor_id') ? document.getElementById('bot_editor_id').value : 0);
    fd.append('name', document.getElementById('bot_name') ? document.getElementById('bot_name').value : '');
    fd.append('platform', document.getElementById('bot_platform') ? document.getElementById('bot_platform').value : '');
    fd.append('token', document.getElementById('bot_token') ? document.getElementById('bot_token').value : '');
    if(document.getElementById('bot_active')) {
        fd.append('is_active', document.getElementById('bot_active').checked ? 1 : 0);
    }
    
    fetch(ajaxurl, {method: 'POST', body: fd})
        .then(r => r.json())
        .then(res => {
            setBtnLoading(btn, false);
            if (res.success) {
                showToast('تنظیمات ربات ذخیره شد', 'success');
                if (res.data && res.data.bot_id) {
                    let idField = document.getElementById('bot_editor_id');
                    if (idField) idField.value = res.data.bot_id;
                }
            } else {
                showToast(res.data ? res.data.message : 'خطا در ذخیره سازی', 'error');
            }
        }).catch(() => {
            setBtnLoading(btn, false);
            showToast('خطا در ارتباط', 'error');
        });
};
`;

const replacementTestBotConnection = `window.testBotConnection = function() {
    let btn = event.currentTarget;
    setBtnLoading(btn, true);
    
    // We can use ssp_test_bot_webhook or maybe there's a need to just test API.
    // ssp_test_bot_webhook acts as a good connection test since it calls getWebhookInfo.
    let fd = new FormData();
    fd.append('action', 'ssp_test_bot_webhook');
    fd.append('security', nonce);
    fd.append('bot_id', document.getElementById('bot_editor_id') ? document.getElementById('bot_editor_id').value : 0);
    
    fetch(ajaxurl, {method: 'POST', body: fd})
        .then(r => r.json())
        .then(res => {
            setBtnLoading(btn, false);
            if (res.success) {
                let msg = 'ارتباط موفق!\\n';
                if(res.data.url) msg += 'وب‌هوک: ' + (res.data.url === 'تنظیم نشده' ? 'تنظیم نشده' : 'فعال');
                if(res.data.has_error) msg += '\\nخطا: ' + res.data.last_error;
                showToast(msg, res.data.has_error ? 'warning' : 'success');
            } else {
                showToast(res.data ? res.data.message : 'خطا در ارتباط', 'error');
            }
        }).catch(() => {
            setBtnLoading(btn, false);
            showToast('خطا در ارتباط', 'error');
        });
};
`;

const replacementTestBotWebhook = `window.testBotWebhook = function() {
    window.testBotConnection(); // It does the same thing
};
`;

const replacementSetBotMenu = `window.setBotMenu = function() {
    let btn = event.currentTarget;
    setBtnLoading(btn, true);
    
    let fd = new FormData();
    fd.append('action', 'ssp_set_bot_menu');
    fd.append('security', nonce);
    fd.append('bot_id', document.getElementById('bot_editor_id') ? document.getElementById('bot_editor_id').value : 0);
    
    fetch(ajaxurl, {method: 'POST', body: fd})
        .then(r => r.json())
        .then(res => {
            setBtnLoading(btn, false);
            if (res.success) {
                showToast('منوی بات با موفقیت در پیام‌رسان تنظیم شد', 'success');
            } else {
                showToast(res.data ? res.data.message : 'خطا در تنظیم منو', 'error');
            }
        }).catch(() => {
            setBtnLoading(btn, false);
            showToast('خطا در ارتباط', 'error');
        });
};
`;

const replacementGetBotStats = `window.getBotStats = function() {
    let btn = event.currentTarget;
    setBtnLoading(btn, true);
    
    let fd = new FormData();
    fd.append('action', 'ssp_get_bot_stats');
    fd.append('security', nonce);
    fd.append('bot_id', document.getElementById('bot_editor_id') ? document.getElementById('bot_editor_id').value : 0);
    
    fetch(ajaxurl, {method: 'POST', body: fd})
        .then(r => r.json())
        .then(res => {
            setBtnLoading(btn, false);
            if (res.success) {
                let msg = 'تعداد کاربران: ' + res.data.users_count + '\\nپیام‌های امروز: ' + res.data.messages_today;
                showToast(msg, 'success');
            } else {
                showToast(res.data ? res.data.message : 'خطا در دریافت آمار', 'error');
            }
        }).catch(() => {
            setBtnLoading(btn, false);
            showToast('خطا در ارتباط', 'error');
        });
};
`;

code = code.replace(regexSaveBotConfig, replacementSaveBotConfig);
code = code.replace(regexTestBotConnection, replacementTestBotConnection);

if(regexTestBotWebhook.test(code)) code = code.replace(regexTestBotWebhook, replacementTestBotWebhook);
if(regexSetBotMenu.test(code)) code = code.replace(regexSetBotMenu, replacementSetBotMenu);
if(regexGetBotStats.test(code)) code = code.replace(regexGetBotStats, replacementGetBotStats);

fs.writeFileSync('assets/js/portal-core.js', code);
console.log('Fixed Bot Builder stubs in portal-core.js');
