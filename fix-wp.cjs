const fs = require('fs');

let core = fs.readFileSync('assets/js/portal-core.js', 'utf8');

let fixedWp = `
window.addWpSite = function() {
    let btn = document.getElementById('add_wpsite_btn');
    if(!btn) return;
    setBtnLoading(btn, true);
    
    let fd = new FormData();
    fd.append('action', 'ssp_add_wp_site');
    fd.append('security', nonce);
    
    let name = document.getElementById('new_site_name');
    if(name) fd.append('site_name', name.value);
    
    let url = document.getElementById('new_site_url');
    if(url) fd.append('site_url', url.value);
    
    let user = document.getElementById('new_site_user');
    if(user) fd.append('username', user.value);
    
    let pass = document.getElementById('new_site_pass');
    if(pass) fd.append('app_password', pass.value);
    
    let active = document.getElementById('new_site_active');
    if(active) fd.append('is_active', active.checked ? 1 : 0);
    
    let auto = document.getElementById('new_site_auto');
    if(auto) fd.append('auto_publish', auto.checked ? 1 : 0);
    
    let ptype = document.getElementById('new_site_post_type');
    if(ptype) fd.append('post_type', ptype.value);
    
    let cats = document.getElementById('new_site_categories');
    if(cats) fd.append('categories', cats.value);

    fetch(ajaxurl, {method: 'POST', body: fd})
        .then(r => r.json())
        .then(res => {
            setBtnLoading(btn, false);
            if(res.success) {
                showSaved('wp_site_saved');
                setTimeout(() => location.reload(), 1000);
            } else {
                showToast(res.data ? res.data.message : 'خطا', 'error');
            }
        }).catch(()=>setBtnLoading(btn, false));
};
`;

// Replace the old one which was naive:
core = core.replace(/window\.addWpSite = function\(\) \{[\s\S]*?(?=window\.saveEditWpSite)/, fixedWp);

fs.writeFileSync('assets/js/portal-core.js', core);
