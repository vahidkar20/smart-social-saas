const fs = require('fs');
let code = fs.readFileSync('assets/js/portal-core.js', 'utf8');

const regexTestAiDns = /window\.testAiDns = function\(\) \{[\s\S]*?(?=window\.testNewWpSite = function\(\))/;

const replacementTestAiDns = `window.testAiDns = function() {
    let btn = event.currentTarget;
    if(btn) setBtnLoading(btn, true);
    
    let resultDiv = document.getElementById('ai_dns_test_result');
    if(resultDiv) {
        resultDiv.style.display = 'block';
        resultDiv.innerHTML = 'در حال بررسی DNS...';
        resultDiv.style.background = 'var(--bg-alt)';
        resultDiv.style.color = 'var(--text)';
    }

    let fd = new FormData();
    fd.append('action', 'ssp_test_ai_live');
    fd.append('step', 'check_dns');
    fd.append('security', nonce);
    fd.append('provider', document.getElementById('ai_provider') ? document.getElementById('ai_provider').value : 'openai');
    
    fetch(ajaxurl, {method: 'POST', body: fd})
        .then(r => r.json())
        .then(res => {
            if(btn) setBtnLoading(btn, false);
            if(resultDiv) {
                if (res.success) {
                    resultDiv.style.background = 'rgba(16,185,129,0.12)';
                    resultDiv.style.color = 'var(--success)';
                    resultDiv.innerHTML = res.data.message || 'تست DNS موفقیت‌آمیز بود';
                } else {
                    resultDiv.style.background = 'rgba(239,68,68,0.12)';
                    resultDiv.style.color = 'var(--error)';
                    resultDiv.innerHTML = (res.data ? res.data.message : 'خطا در ارتباط') + (res.data && res.data.hint ? '<br><small>راهنمایی: ' + res.data.hint + '</small>' : '');
                }
            } else {
                if (res.success) showToast(res.data.message, 'success');
                else showToast(res.data ? res.data.message : 'خطا', 'error');
            }
        }).catch(() => {
            if(btn) setBtnLoading(btn, false);
            if(resultDiv) {
                resultDiv.style.background = 'rgba(239,68,68,0.12)';
                resultDiv.style.color = 'var(--error)';
                resultDiv.innerHTML = 'خطا در برقراری ارتباط با سرور';
            } else {
                showToast('خطا در ارتباط', 'error');
            }
        });
};
`;

code = code.replace(regexTestAiDns, replacementTestAiDns);

fs.writeFileSync('assets/js/portal-core.js', code);
console.log('Fixed testAiDns stub');
