const fs = require('fs');
let php = fs.readFileSync('includes/views/portal.php', 'utf8');

const oldCode = `            window.openChatbotTab = function() {
                var chatbot = document.getElementById('ssp_ai_chatbot').value;
                var urls = { 'deepseek': 'https://chat.deepseek.com/', 'chatgpt': 'https://chatgpt.com/' };
                var url = urls[chatbot] || urls['deepseek'];
                window._bridgeChatbotTab = window.open(url, '_blank');
                if (!window._bridgeChatbotTab || window._bridgeChatbotTab.closed || typeof window._bridgeChatbotTab.closed === 'undefined') {
                    // Popup was blocked
                    updateBridgeModalState('error');
                    var statusEl = document.getElementById('bridge-wait-status');
                    if (statusEl) {
                        statusEl.innerHTML = 'پاپ‌آپ مسدود شد! لطفاً پاپ‌آپ را برای این سایت فعال کنید یا <a href="' + url + '" target="_blank" style="color:#4f46e5;text-decoration:underline;">اینجا کلیک کنید</a>';
                    }
                    return;
                }
            };`;

const newCode = `            window.openChatbotTab = function() {
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
            };`;

if (php.includes('window.openChatbotTab = function() {')) {
    php = php.replace(oldCode, newCode);
    fs.writeFileSync('includes/views/portal.php', php);
    console.log('Patched openChatbotTab');
} else {
    console.log('Could not find openChatbotTab code');
}
