const fs = require('fs');
let core = fs.readFileSync('assets/js/portal-core.js', 'utf8');

let newCode = `
window.testNewWpSite = function() {
    let btn = event.currentTarget;
    setBtnLoading(btn, true);
    let fd = new FormData();
    fd.append('action', 'ssp_test_wp_site');
    fd.append('security', nonce);
    
    let url = document.getElementById('new_site_url');
    if(url) fd.append('site_url', url.value);
    let user = document.getElementById('new_site_user');
    if(user) fd.append('username', user.value);
    let pass = document.getElementById('new_site_pass');
    if(pass) fd.append('app_password', pass.value);

    fetch(ajaxurl, {method: 'POST', body: fd})
        .then(r => r.json())
        .then(res => {
            setBtnLoading(btn, false);
            let out = document.getElementById('new_site_test_output');
            if(out) {
                out.style.display = 'block';
                out.innerHTML = res.success ? '<span style="color:var(--success)">' + res.data.message + '</span>' : '<span style="color:var(--danger)">' + (res.data ? res.data.message : 'خطا') + '</span>';
            }
        }).catch(()=>setBtnLoading(btn, false));
};

window.fetchCategories = function(prefix) {
    let btn = event.currentTarget;
    let oldTxt = btn.innerText;
    btn.innerText = 'در حال دریافت...';
    
    let fd = new FormData();
    fd.append('action', 'ssp_fetch_wp_categories');
    fd.append('security', nonce);
    
    let url = document.getElementById(prefix + '_site_url') || document.getElementById('new_site_url');
    if(url) fd.append('site_url', url.value);
    let user = document.getElementById(prefix + '_site_user') || document.getElementById('new_site_user');
    if(user) fd.append('username', user.value);
    let pass = document.getElementById(prefix + '_site_pass') || document.getElementById('new_site_pass');
    if(pass) fd.append('app_password', pass.value);

    fetch(ajaxurl, {method: 'POST', body: fd})
        .then(r => r.json())
        .then(res => {
            btn.innerText = oldTxt;
            let list = document.getElementById(prefix + '_cat_list');
            if(res.success && list) {
                list.style.display = 'block';
                let html = '<strong>دسته‌بندی‌های موجود:</strong><br>';
                res.data.categories.forEach(c => {
                    html += c.id + ' = ' + c.name + '<br>';
                });
                list.innerHTML = html;
            } else {
                showToast(res.data ? res.data.message : 'خطا', 'error');
            }
        }).catch(()=>btn.innerText = oldTxt);
};

window.saveDraftFromManual = function() {
    let btn = document.getElementById('manual_save_draft_btn');
    if(!btn) btn = event.currentTarget;
    setBtnLoading(btn, true);
    
    let fd = new FormData();
    fd.append('action', 'ssp_save_draft');
    fd.append('security', nonce);
    
    let title = document.getElementById('manual_title');
    if(title) fd.append('title', title.value);
    
    let msg = document.getElementById('manual_message');
    if(msg && msg.value) fd.append('content', msg.value);
    else { showToast('متن پیش‌نویس الزامی است', 'error'); setBtnLoading(btn, false); return; }
    
    fetch(ajaxurl, {method: 'POST', body: fd})
        .then(r => r.json())
        .then(res => {
            setBtnLoading(btn, false);
            if(res.success) {
                showToast('پیش‌نویس ذخیره شد', 'success');
                if(typeof loadDrafts === 'function') loadDrafts();
            } else {
                showToast(res.data ? res.data.message : 'خطا', 'error');
            }
        }).catch(()=>setBtnLoading(btn, false));
};
`;

core = core + "\n" + newCode;
['testNewWpSite', 'fetchCategories', 'saveDraftFromManual'].forEach(fn => {
    core = core.replace("'" + fn + "', ", "");
});
fs.writeFileSync('assets/js/portal-core.js', core);
