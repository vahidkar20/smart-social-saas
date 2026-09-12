const fs = require('fs');
let js = fs.readFileSync('assets/js/portal-core.js', 'utf8');

const oldCode1 = `window.saveEditWpSite = function() {
    let btn = event.currentTarget;
    let id = document.getElementById('edit_wpsite_id').value;
    let name = document.getElementById('edit_wpsite_name').value;
    let url = document.getElementById('edit_wpsite_url').value;
    let user = document.getElementById('edit_wpsite_user').value;
    let pass = document.getElementById('edit_wpsite_pass').value;
    let cat = document.getElementById('edit_wpsite_cat') ? document.getElementById('edit_wpsite_cat').value : '';
    let template = document.getElementById('edit_wpsite_template') ? document.getElementById('edit_wpsite_template').value : '';`;

const newCode1 = `window.saveEditWpSite = function() {
    let btn = event.currentTarget;
    let id = document.getElementById('edit_wpsite_id').value;
    let name = document.getElementById('edit_wpsite_name').value;
    let url = document.getElementById('edit_wpsite_url').value;
    let user = document.getElementById('edit_wpsite_user').value;
    let pass = document.getElementById('edit_wpsite_pass').value;
    let cat = document.getElementById('edit_wpsite_categories') ? document.getElementById('edit_wpsite_categories').value : '';
    let post_type = document.getElementById('edit_wpsite_post_type') ? document.getElementById('edit_wpsite_post_type').value : 'post';
    let is_active = document.getElementById('edit_wpsite_active') ? (document.getElementById('edit_wpsite_active').checked ? 1 : 0) : 1;
    let auto_publish = document.getElementById('edit_wpsite_auto') ? (document.getElementById('edit_wpsite_auto').checked ? 1 : 0) : 1;`;

const oldCode2 = `    fd.append('default_category', cat);
    fd.append('post_template', template);`;

const newCode2 = `    fd.append('default_category', cat);
    fd.append('post_type', post_type);
    fd.append('is_active', is_active);
    fd.append('auto_publish', auto_publish);`;

if (js.includes(oldCode1)) {
    js = js.replace(oldCode1, newCode1);
    if(js.includes(oldCode2)) js = js.replace(oldCode2, newCode2);
    fs.writeFileSync('assets/js/portal-core.js', js);
    console.log('patched saveEditWpSite mapping');
} else {
    console.log('not found saveEditWpSite mapping');
}
