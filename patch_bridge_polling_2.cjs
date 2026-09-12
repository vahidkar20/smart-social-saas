const fs = require('fs');
let js = fs.readFileSync('assets/js/portal-core.js', 'utf8');

const regex = /window\.startBridgePolling = function\(taskId, onComplete, onTimeout\) \{[\s\S]*?\}, 2500\);\n\};/m;

const newCode1 = `window._bridgeActive = false;
window._bridgeCurrentTask = null;
window._bridgePollInterval = null;
window._bridgeRetryCount = 0;

window.setBridgeModalState = function(step, errorMsg) {
    let m = document.getElementById('modal_ai_bridge');
    if (!m) return;
    
    // Reset all
    for (let i = 1; i <= 3; i++) {
        let text = document.getElementById('bridge_step_' + i + '_text');
        let icon = document.querySelector('#bridge_step_' + i + ' .bridge-step-icon');
        if (text) text.style.color = 'var(--text-muted)';
        if (icon) {
            icon.style.background = 'transparent';
            icon.style.color = 'var(--text-muted)';
            icon.innerHTML = i;
        }
    }
    document.getElementById('bridge_error_box').style.display = 'none';
    document.getElementById('bridge_retry_btn').style.display = 'none';
    
    // Set active up to step
    let progress = (step === 1) ? 20 : (step === 2) ? 60 : 100;
    document.getElementById('bridge_progress_bar').style.width = progress + '%';
    document.getElementById('bridge_progress_bar').style.background = 'var(--accent)';
    
    for (let i = 1; i <= step; i++) {
        let text = document.getElementById('bridge_step_' + i + '_text');
        let icon = document.querySelector('#bridge_step_' + i + ' .bridge-step-icon');
        if (text) text.style.color = 'var(--text)';
        if (icon) {
            icon.style.background = 'var(--accent)';
            icon.style.color = '#fff';
            icon.style.border = 'none';
            if (i < step || step === 3) {
                icon.innerHTML = '<svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2"><polyline points="20 6 9 17 4 12"/></svg>';
            }
        }
    }
    
    if (errorMsg) {
        document.getElementById('bridge_error_box').innerHTML = errorMsg;
        document.getElementById('bridge_error_box').style.display = 'block';
        document.getElementById('bridge_progress_bar').style.background = 'var(--error)';
        document.getElementById('bridge_retry_btn').style.display = 'inline-flex';
        window._bridgeActive = false;
        if (window._bridgePollInterval) clearInterval(window._bridgePollInterval);
    }
};

window.retryBridgeTask = function() {
    window._bridgeRetryCount++;
    document.getElementById('bridge_retry_btn').style.display = 'none';
    setBridgeModalState(1);
    // Give user time to see the modal resetting
    setTimeout(() => {
        if (window.openChatbotTab) window.openChatbotTab();
        window.startBridgePolling(window._bridgeCurrentTask.id, window._bridgeCurrentTask.onComplete, window._bridgeCurrentTask.onTimeout);
    }, 500);
};

window.startBridgePolling = function(taskId, onComplete, onTimeout) {
    if (!taskId) return;
    
    window._bridgeCurrentTask = { id: taskId, onComplete: onComplete, onTimeout: onTimeout };
    window._bridgeActive = true;
    
    let m = document.getElementById('modal_ai_bridge');
    if (m) {
        m.style.display = 'flex';
        // force reflow
        void m.offsetWidth;
    }
    setBridgeModalState(1); // Step 1: Request Sent
    
    let attempts = 0;
    let maxAttempts = 120; // 5 minutes max (2.5s interval * 120)
    
    if (window._bridgePollInterval) clearInterval(window._bridgePollInterval);
    
    window._bridgePollInterval = setInterval(function() {
        if (!window._bridgeActive) {
            clearInterval(window._bridgePollInterval);
            return;
        }
        
        attempts++;
        if (attempts > maxAttempts) {
            setBridgeModalState(2, 'زمان انتظار به پایان رسید. لطفاً مطمئن شوید تب چت‌بات را نبسته‌اید.');
            if (onTimeout) onTimeout();
            return;
        }
        
        let fd = new FormData();
        fd.append('action', 'ssp_bridge_poll_status');
        fd.append('security', nonce);
        fd.append('task_id', taskId);
        
        fetch(ajaxurl, {method: 'POST', body: fd})
            .then(r => r.json())
            .then(res => {
                if (res.success && window._bridgeActive) {
                    if (res.data.status === 'processing') {
                        setBridgeModalState(2); // Chatbot is generating
                    } else if (res.data.status === 'completed') {
                        setBridgeModalState(3); // Done!
                        setTimeout(() => {
                            if (window._bridgeActive) {
                                window.closeModal('modal_ai_bridge');
                                showToast('پاسخ از چت‌بات با موفقیت دریافت شد!', 'success');
                                if (onComplete) onComplete(res.data);
                            }
                        }, 1000);
                    } else if (res.data.status === 'expired' || res.data.status === 'failed') {
                        setBridgeModalState(1, 'وظیفه در سمت سرور منقضی یا لغو شد.');
                        if (onTimeout) onTimeout();
                    }
                }
            })
            .catch(() => {
                // Ignore network errors and keep polling
            });
    }, 2500);
};`;

if (regex.test(js)) {
    js = js.replace(regex, newCode1);
    fs.writeFileSync('assets/js/portal-core.js', js);
    console.log('Successfully patched startBridgePolling using regex');
} else {
    console.log('Regex did not match startBridgePolling');
}
