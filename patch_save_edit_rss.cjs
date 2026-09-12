const fs = require('fs');
let js = fs.readFileSync('assets/js/portal-core.js', 'utf8');

let target = `window.saveEditRssFeed = function() {
    let btn = document.querySelector('#modal_edit_rss .ssp-btn-primary');
    setBtnLoading(btn, true);
    let fd = new FormData();
    fd.append('action', 'ssp_update_rss_feed');
    fd.append('security', nonce);
    fd.append('feed_id', document.getElementById('edit_rss_id').value);
    fd.append('feed_url', document.getElementById('edit_rss_feed_url').value);
    
    fetch(ajaxurl, {method:'POST', body:fd}).then(r=>r.json()).then(res=>{
        setBtnLoading(btn, false);
        if(res.success) location.reload();
        else showToast(res.data ? res.data.message : 'خطا', 'error');
    });
};`;

let replacement = `window.saveEditRssFeed = function() {
    let btn = document.querySelector('#modal_edit_rss .ssp-btn-primary');
    setBtnLoading(btn, true);
    let fd = new FormData();
    fd.append('action', 'ssp_update_rss_feed');
    fd.append('security', nonce);
    fd.append('feed_id', document.getElementById('edit_rss_id').value);
    if(document.getElementById('edit_rss_name')) fd.append('feed_name', document.getElementById('edit_rss_name').value);
    if(document.getElementById('edit_rss_url')) fd.append('feed_url', document.getElementById('edit_rss_url').value);
    if(document.getElementById('edit_rss_active')) fd.append('is_active', document.getElementById('edit_rss_active').checked ? 1 : 0);
    if(document.getElementById('edit_rss_auto')) fd.append('auto_fetch', document.getElementById('edit_rss_auto').checked ? 1 : 0);
    if(document.getElementById('edit_rss_clean_ads')) fd.append('clean_ads', document.getElementById('edit_rss_clean_ads').checked ? 1 : 0);
    if(document.getElementById('edit_rss_clean_urls')) fd.append('clean_urls', document.getElementById('edit_rss_clean_urls').checked ? 1 : 0);
    if(document.getElementById('edit_rss_extract')) fd.append('extract_full', document.getElementById('edit_rss_extract').checked ? 1 : 0);
    if(document.getElementById('edit_rss_max_length')) fd.append('max_length', document.getElementById('edit_rss_max_length').value);
    if(document.getElementById('edit_rss_content_mode')) fd.append('content_mode', document.getElementById('edit_rss_content_mode').value);
    
    fetch(ajaxurl, {method:'POST', body:fd}).then(r=>r.json()).then(res=>{
        setBtnLoading(btn, false);
        if(res.success) location.reload();
        else showToast(res.data ? res.data.message : 'خطا', 'error');
    }).catch(err => setBtnLoading(btn, false));
};`;

js = js.replace(target, replacement);
fs.writeFileSync('assets/js/portal-core.js', js);
console.log('patched saveEditRssFeed');
