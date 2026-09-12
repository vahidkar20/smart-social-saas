const fs = require('fs');
let js = fs.readFileSync('assets/js/portal-core.js', 'utf8');

const oldCode1 = `            if(window.wpSiteData && Array.isArray(window.wpSiteData)) {
                let site = window.wpSiteData.find(s => s.id == id);
                if(site) {
                    if(document.getElementById('edit_wpsite_name')) document.getElementById('edit_wpsite_name').value = site.site_name || '';
                    if(document.getElementById('edit_wpsite_url')) document.getElementById('edit_wpsite_url').value = site.site_url || '';
                    if(document.getElementById('edit_wpsite_user')) document.getElementById('edit_wpsite_user').value = site.username || '';
                    if(document.getElementById('edit_wpsite_pass')) document.getElementById('edit_wpsite_pass').value = ''; // Don't show password
                    if(document.getElementById('edit_wpsite_cat')) document.getElementById('edit_wpsite_cat').value = site.default_category || '';
                    if(document.getElementById('edit_wpsite_template')) document.getElementById('edit_wpsite_template').value = site.post_template || '';
                }
            }`;

const newCode1 = `            if(window.wpSiteData && Array.isArray(window.wpSiteData)) {
                let site = window.wpSiteData.find(s => s.id == id);
                if(site) {
                    if(document.getElementById('edit_wpsite_name')) document.getElementById('edit_wpsite_name').value = site.site_name || '';
                    if(document.getElementById('edit_wpsite_url')) document.getElementById('edit_wpsite_url').value = site.site_url || '';
                    if(document.getElementById('edit_wpsite_user')) document.getElementById('edit_wpsite_user').value = site.username || '';
                    if(document.getElementById('edit_wpsite_pass')) document.getElementById('edit_wpsite_pass').value = ''; // Don't show password
                    if(document.getElementById('edit_wpsite_categories')) document.getElementById('edit_wpsite_categories').value = site.default_category || '';
                    if(document.getElementById('edit_wpsite_post_type')) document.getElementById('edit_wpsite_post_type').value = site.post_type || 'post';
                    if(document.getElementById('edit_wpsite_active')) document.getElementById('edit_wpsite_active').checked = parseInt(site.is_active) === 1;
                    if(document.getElementById('edit_wpsite_auto')) document.getElementById('edit_wpsite_auto').checked = parseInt(site.auto_publish) === 1;
                }
            }`;

if (js.includes(oldCode1)) {
    js = js.replace(oldCode1, newCode1);
    fs.writeFileSync('assets/js/portal-core.js', js);
    console.log('patched edit_wpsite mapping');
} else {
    console.log('not found edit_wpsite mapping');
}
