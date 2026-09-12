const fs = require('fs');
let js = fs.readFileSync('assets/js/portal-core.js', 'utf8');
let target = `window.saveEditWpSite = function() {
    let btn = document.querySelector('#modal_edit_wpsite .ssp-btn-primary');
    setBtnLoading(btn, true);
    let fd = new FormData();
    fd.append('action', 'ssp_update_wp_site');
    fd.append('security', nonce);
    fd.append('site_id', document.getElementById('edit_wpsite_id').value);
    fd.append('site_url', document.getElementById('edit_wp_site_url').value);
    fd.append('username', document.getElementById('edit_wp_username').value);
    fd.append('app_password', document.getElementById('edit_wp_app_password').value);
    
    fetch(ajaxurl, {method:'POST', body:fd}).then(r=>r.json()).then(res=>{
        setBtnLoading(btn, false);
        if(res.success) location.reload();
        else showToast(res.data ? res.data.message : 'خطا', 'error');
    });
};`;

let replacement = `window.saveEditWpSite = function() {
    let btn = document.querySelector('#modal_edit_wpsite .ssp-btn-primary');
    setBtnLoading(btn, true);
    let fd = new FormData();
    fd.append('action', 'ssp_update_wp_site');
    fd.append('security', nonce);
    fd.append('site_id', document.getElementById('edit_wpsite_id').value);
    
    if(document.getElementById('edit_wpsite_name')) fd.append('site_name', document.getElementById('edit_wpsite_name').value);
    if(document.getElementById('edit_wpsite_url')) fd.append('site_url', document.getElementById('edit_wpsite_url').value);
    if(document.getElementById('edit_wpsite_user')) fd.append('username', document.getElementById('edit_wpsite_user').value);
    if(document.getElementById('edit_wpsite_pass')) fd.append('app_password', document.getElementById('edit_wpsite_pass').value);
    if(document.getElementById('edit_wpsite_active')) fd.append('is_active', document.getElementById('edit_wpsite_active').checked ? 1 : 0);
    if(document.getElementById('edit_wpsite_auto')) fd.append('auto_publish', document.getElementById('edit_wpsite_auto').checked ? 1 : 0);
    if(document.getElementById('edit_wpsite_post_type')) fd.append('post_type', document.getElementById('edit_wpsite_post_type').value);
    if(document.getElementById('edit_wpsite_categories')) fd.append('categories', document.getElementById('edit_wpsite_categories').value);
    
    fetch(ajaxurl, {method:'POST', body:fd}).then(r=>r.json()).then(res=>{
        setBtnLoading(btn, false);
        if(res.success) location.reload();
        else showToast(res.data ? res.data.message : 'خطا', 'error');
    }).catch(err => {
        setBtnLoading(btn, false);
    });
};`;

js = js.replace(target, replacement);
fs.writeFileSync('assets/js/portal-core.js', js);
console.log('patched saveEditWpSite');
