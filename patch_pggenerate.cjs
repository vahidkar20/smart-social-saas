const fs = require('fs');
let content = fs.readFileSync('includes/views/portal.php', 'utf8');

const oldCode = `if (res.success) {
                            showToast('تولید محتوا موفق بود', 'success');
                            if (res.data.content) {
                                var resBox = document.getElementById('pg_post_result');
                                if (resBox) resBox.style.display = 'block';
                                var contentField = document.getElementById('pg_res_content');
                                if (contentField) {
                                    contentField.value = res.data.content.message || res.data.content.content || '';
                                }
                            }
                        } else if (res.success && res.data && res.data.mode === 'browser') {
                            showToast('درخواست مرورگر ثبت شد. تب چت‌بات را باز کنید.', 'info');
                            if (typeof window.openChatbotTab === 'function') window.openChatbotTab();
                        }`;

const newCode = `if (res.success && res.data && res.data.mode === 'browser') {
                            showToast('درخواست مرورگر ثبت شد. تب چت‌بات را باز کنید.', 'info');
                            if (typeof window.openChatbotTab === 'function') window.openChatbotTab();
                            if (typeof window.startBridgePolling === 'function') {
                                window.startBridgePolling(res.data.task_id, function(data) {
                                    var text = (data.parsed && (data.parsed.message || data.parsed.content)) ? (data.parsed.message || data.parsed.content) : (data.raw || '');
                                    var resBox = document.getElementById('pg_post_result');
                                    var contentField = document.getElementById('pg_res_content');
                                    if (resBox && contentField && text) {
                                        resBox.style.display = 'block';
                                        contentField.value = text;
                                        showToast('محتوا از مرورگر دریافت شد!', 'success');
                                    }
                                });
                            }
                        } else if (res.success) {
                            showToast('تولید محتوا موفق بود', 'success');
                            if (res.data.content) {
                                var resBox = document.getElementById('pg_post_result');
                                if (resBox) resBox.style.display = 'block';
                                var contentField = document.getElementById('pg_res_content');
                                if (contentField) {
                                    contentField.value = res.data.content.message || res.data.content.content || '';
                                }
                            }
                        }`;

if (content.includes(oldCode)) {
    content = content.replace(oldCode, newCode);
    fs.writeFileSync('includes/views/portal.php', content);
    console.log('patched pgGeneratePost');
} else {
    console.log('could not find oldCode');
}
