const fs = require('fs');
let js = fs.readFileSync('assets/js/portal-core.js', 'utf8');

let target = `window.saveEditMessenger = function() {
    let btn = document.querySelector('#modal_edit_messenger .ssp-btn-primary');
    setBtnLoading(btn, true);
    let fd = new FormData();
    fd.append('action', 'ssp_update_messenger');
    fd.append('security', nonce);
    fd.append('messenger_id', document.getElementById('edit_messenger_id').value);
    fd.append('platform', document.getElementById('edit_messenger_platform').value);
    fd.append('name', document.getElementById('edit_messenger_name').value);
    fd.append('bot_token', document.getElementById('edit_messenger_token').value);
    fd.append('channel_id', document.getElementById('edit_messenger_channel').value);
    
    fetch(ajaxurl, {method:'POST', body:fd}).then(r=>r.json()).then(res=>{
        setBtnLoading(btn, false);
        if(res.success) location.reload();
        else showToast(res.data ? res.data.message : 'خطا', 'error');
    });
};`;

let replacement = `window.saveEditMessenger = function() {
    let btn = document.querySelector('#modal_edit_messenger .ssp-btn-primary');
    setBtnLoading(btn, true);
    let fd = new FormData();
    fd.append('action', 'ssp_update_messenger');
    fd.append('security', nonce);
    fd.append('messenger_id', document.getElementById('edit_messenger_id').value);
    if(document.getElementById('edit_messenger_platform')) fd.append('platform', document.getElementById('edit_messenger_platform').value);
    if(document.getElementById('edit_messenger_name')) fd.append('name', document.getElementById('edit_messenger_name').value);
    if(document.getElementById('edit_messenger_token')) fd.append('bot_token', document.getElementById('edit_messenger_token').value);
    if(document.getElementById('edit_messenger_channel')) fd.append('channel_id', document.getElementById('edit_messenger_channel').value);
    if(document.getElementById('edit_messenger_active')) fd.append('is_active', document.getElementById('edit_messenger_active').checked ? 1 : 0);
    
    fetch(ajaxurl, {method:'POST', body:fd}).then(r=>r.json()).then(res=>{
        setBtnLoading(btn, false);
        if(res.success) location.reload();
        else showToast(res.data ? res.data.message : 'خطا', 'error');
    }).catch(err => setBtnLoading(btn, false));
};`;

js = js.replace(target, replacement);
fs.writeFileSync('assets/js/portal-core.js', js);
console.log('patched saveEditMessenger');
