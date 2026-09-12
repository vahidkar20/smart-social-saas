const fs = require('fs');
let js = fs.readFileSync('assets/js/portal-core.js', 'utf8');

let target = `if(e.target && e.target.classList.contains('btn-edit-wpsite')) {
        let id = e.target.getAttribute('data-id');
        let m = document.getElementById('modal_edit_wpsite');
        if(m) {
            document.getElementById('edit_wpsite_id').value = id;
            m.style.display = 'flex';
        }
    }`;

let target2 = `    if(e.target && e.target.classList.contains('btn-edit-wpsite')) {
        let id = e.target.getAttribute('data-id');
        let m = document.getElementById('modal_edit_wpsite');
        if(m) {
            document.getElementById('edit_wpsite_id').value = id;
            m.style.display = 'flex';
        }
    }`;

let replacement = `    let editWpSiteTarget = e.target.closest ? e.target.closest('.btn-edit-wpsite') : (e.target.classList.contains('btn-edit-wpsite') ? e.target : null);
    if(editWpSiteTarget) {
        let id = editWpSiteTarget.getAttribute('data-id');
        let m = document.getElementById('modal_edit_wpsite');
        if(m) {
            document.getElementById('edit_wpsite_id').value = id;
            if(window.wpSiteData && Array.isArray(window.wpSiteData)) {
                let site = window.wpSiteData.find(s => s.id == id);
                if(site) {
                    if(document.getElementById('edit_wpsite_name')) document.getElementById('edit_wpsite_name').value = site.site_name || '';
                    if(document.getElementById('edit_wpsite_url')) document.getElementById('edit_wpsite_url').value = site.site_url || '';
                    if(document.getElementById('edit_wpsite_user')) document.getElementById('edit_wpsite_user').value = site.username || '';
                    if(document.getElementById('edit_wpsite_pass')) document.getElementById('edit_wpsite_pass').value = ''; // Don't show password
                    if(document.getElementById('edit_wpsite_cat')) document.getElementById('edit_wpsite_cat').value = site.default_category || '';
                    if(document.getElementById('edit_wpsite_template')) document.getElementById('edit_wpsite_template').value = site.post_template || '';
                }
            }
            m.style.display = 'flex';
        }
    }`;

js = js.replace(target, replacement).replace(target2, replacement);
fs.writeFileSync('assets/js/portal-core.js', js);
console.log('patched wp site edit click');
