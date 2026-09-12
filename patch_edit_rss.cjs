const fs = require('fs');
let js = fs.readFileSync('assets/js/portal-core.js', 'utf8');

let target = `    if(e.target && e.target.classList.contains('btn-edit-rss')) {
        let id = e.target.getAttribute('data-id');
        let m = document.getElementById('modal_edit_rss');
        if(m) {
            document.getElementById('edit_rss_id').value = id;
            m.style.display = 'flex';
        }
    }`;

let replacement = `    let editRssTarget = e.target.closest ? e.target.closest('.btn-edit-rss') : (e.target.classList.contains('btn-edit-rss') ? e.target : null);
    if(editRssTarget) {
        let id = editRssTarget.getAttribute('data-id');
        let m = document.getElementById('modal_edit_rss');
        if(m) {
            document.getElementById('edit_rss_id').value = id;
            if(window.rssData && Array.isArray(window.rssData)) {
                let rss = window.rssData.find(rItem => rItem.id == id);
                if(rss) {
                    if(document.getElementById('edit_rss_name')) document.getElementById('edit_rss_name').value = rss.name || '';
                    if(document.getElementById('edit_rss_url')) document.getElementById('edit_rss_url').value = rss.feed_url || '';
                    if(document.getElementById('edit_rss_category')) document.getElementById('edit_rss_category').value = rss.category || '';
                }
            }
            m.style.display = 'flex';
        }
    }`;

js = js.replace(target, replacement);
fs.writeFileSync('assets/js/portal-core.js', js);
console.log('patched rss edit click');
