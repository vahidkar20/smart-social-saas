const fs = require('fs');

let core = fs.readFileSync('assets/js/portal-core.js', 'utf8');

let fixedRss = `
window.addRssFeed = function() {
    let btn = document.getElementById('add_rss_btn');
    if(!btn) return;
    setBtnLoading(btn, true);
    
    let fd = new FormData();
    fd.append('action', 'ssp_add_rss_feed');
    fd.append('security', nonce);
    
    let name = document.getElementById('new_feed_name');
    if(name) fd.append('feed_name', name.value);
    
    let url = document.getElementById('new_feed_url');
    if(url) fd.append('feed_url', url.value);
    
    let active = document.getElementById('new_feed_active');
    if(active) fd.append('is_active', active.checked ? 1 : 0);
    
    let auto = document.getElementById('new_feed_auto');
    if(auto) fd.append('auto_publish', auto.checked ? 1 : 0);
    
    let extract = document.getElementById('new_feed_extract');
    if(extract) fd.append('extract_content', extract.checked ? 1 : 0);
    
    let cleanAds = document.getElementById('new_feed_clean_ads');
    if(cleanAds) fd.append('clean_ads', cleanAds.checked ? 1 : 0);
    
    let cleanUrls = document.getElementById('new_feed_clean_urls');
    if(cleanUrls) fd.append('clean_urls', cleanUrls.checked ? 1 : 0);
    
    let mode = document.getElementById('new_feed_content_mode');
    if(mode) fd.append('content_mode', mode.value);
    
    let max = document.getElementById('new_feed_max_length');
    if(max) fd.append('max_length', max.value);
    
    let target = document.getElementById('new_feed_target_mode');
    if(target) fd.append('target_mode', target.value);

    let selectedMsgs = [];
    document.querySelectorAll('.new_feed_messenger_cb:checked').forEach(cb => {
        selectedMsgs.push(cb.value);
    });
    if(target && target.value === 'messengers') {
        fd.append('messengers', selectedMsgs.join(','));
    }

    fetch(ajaxurl, {method: 'POST', body: fd})
        .then(r => r.json())
        .then(res => {
            setBtnLoading(btn, false);
            if(res.success) {
                showSaved('rss_feed_saved');
                setTimeout(() => location.reload(), 1000);
            } else {
                showToast(res.data ? res.data.message : 'خطا', 'error');
            }
        }).catch(()=>setBtnLoading(btn, false));
};
`;

core = core.replace(/window\.addRssFeed = function\(\) \{[\s\S]*?(?=window\.saveEditRssFeed)/, fixedRss);
fs.writeFileSync('assets/js/portal-core.js', core);
