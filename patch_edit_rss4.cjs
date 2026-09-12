const fs = require('fs');
let js = fs.readFileSync('assets/js/portal-core.js', 'utf8');

let targetRegex = /let editRssTarget = e\.target\.closest \? e\.target\.closest\('\.btn-edit-rss'\) \: \(e\.target\.classList\.contains\('\.btn-edit-rss'\) \? e\.target \: null\);[\s\S]*?m\.style\.display = 'flex';\n        }\n    }/;

let replacement = `let editRssTarget = e.target.closest ? e.target.closest('.btn-edit-rss') : (e.target.classList.contains('.btn-edit-rss') ? e.target : null);
    if(editRssTarget) {
        let id = editRssTarget.getAttribute('data-id');
        let m = document.getElementById('modal_edit_rss');
        if(m) {
            document.getElementById('edit_rss_id').value = id;
            if(window.rssFeedData && Array.isArray(window.rssFeedData)) {
                let rss = window.rssFeedData.find(rItem => rItem.id == id);
                if(rss) {
                    if(document.getElementById('edit_rss_name')) document.getElementById('edit_rss_name').value = rss.feed_name || '';
                    if(document.getElementById('edit_rss_url')) document.getElementById('edit_rss_url').value = rss.feed_url || '';
                    if(document.getElementById('edit_rss_active')) document.getElementById('edit_rss_active').checked = (rss.is_active == 1);
                    if(document.getElementById('edit_rss_auto')) document.getElementById('edit_rss_auto').checked = (rss.auto_fetch == 1);
                    if(document.getElementById('edit_rss_clean_ads')) document.getElementById('edit_rss_clean_ads').checked = (rss.clean_ads == 1);
                    if(document.getElementById('edit_rss_clean_urls')) document.getElementById('edit_rss_clean_urls').checked = (rss.clean_urls == 1);
                    if(document.getElementById('edit_rss_extract')) document.getElementById('edit_rss_extract').checked = (rss.extract_content == 1);
                    if(document.getElementById('edit_rss_max_length')) document.getElementById('edit_rss_max_length').value = rss.max_length || '500';
                    if(document.getElementById('edit_rss_content_mode')) document.getElementById('edit_rss_content_mode').value = rss.content_mode || 'summary';
                }
            }
            m.style.display = 'flex';
        }
    }`;

js = js.replace(targetRegex, replacement);
fs.writeFileSync('assets/js/portal-core.js', js);
console.log('patched rss edit fields (extended) correctly');
