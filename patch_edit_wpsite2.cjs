const fs = require('fs');
let js = fs.readFileSync('assets/js/portal-core.js', 'utf8');
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
                    if(document.getElementById('edit_wpsite_pass')) document.getElementById('edit_wpsite_pass').value = ''; 
                    if(document.getElementById('edit_wpsite_active')) document.getElementById('edit_wpsite_active').checked = (site.is_active == 1);
                    if(document.getElementById('edit_wpsite_auto')) document.getElementById('edit_wpsite_auto').checked = (site.auto_publish == 1);
                    if(document.getElementById('edit_wpsite_post_type')) document.getElementById('edit_wpsite_post_type').value = site.post_type || 'post';
                    if(document.getElementById('edit_wpsite_categories')) document.getElementById('edit_wpsite_categories').value = site.categories || '';
                }
            }
            m.style.display = 'flex';
        }
    }`;

js = js.replace(/let editWpSiteTarget = e\.target\.closest \? e\.target\.closest\('\.btn-edit-wpsite'\) \: \(e\.target\.classList\.contains\('\.btn-edit-wpsite'\) \? e\.target \: null\);[\s\S]*?m\.style\.display = 'flex';\n        }\n    }/m, replacement);

fs.writeFileSync('assets/js/portal-core.js', js);
console.log('patched wp site edit fields correctly');
