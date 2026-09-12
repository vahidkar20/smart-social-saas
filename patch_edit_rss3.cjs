const fs = require('fs');
let js = fs.readFileSync('assets/js/portal-core.js', 'utf8');

let replacement = `    let editRssTarget = e.target.closest ? e.target.closest('.btn-edit-rss') : (e.target.classList.contains('btn-edit-rss') ? e.target : null);
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
                    // Add other fields if needed, but these are the main ones present in rssFeedData
                }
            }
            m.style.display = 'flex';
        }
    }`;

js = js.replace(/let editRssTarget = e\.target\.closest \? e\.target\.closest\('\.btn-edit-rss'\) \: \(e\.target\.classList\.contains\('\.btn-edit-rss'\) \? e\.target \: null\);[\s\S]*?m\.style\.display = 'flex';\n        }\n    }/m, replacement);
fs.writeFileSync('assets/js/portal-core.js', js);
console.log('patched rss edit fields correctly');
