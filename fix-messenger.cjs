const fs = require('fs');

let core = fs.readFileSync('assets/js/portal-core.js', 'utf8');

let fixedMessenger = `
window.addMessenger = function() {
    let btn = document.getElementById('add_messenger_btn');
    if(!btn) btn = event.currentTarget;
    if(!btn) return;
    setBtnLoading(btn, true);
    
    let fd = new FormData();
    fd.append('action', 'ssp_add_messenger');
    fd.append('security', nonce);
    
    let plat = document.getElementById('new_messenger_platform');
    if(plat) fd.append('platform', plat.value);
    
    let name = document.getElementById('new_messenger_name');
    if(name) fd.append('name', name.value);
    
    let token = document.getElementById('new_messenger_token');
    if(token) fd.append('bot_token', token.value);
    
    let channel = document.getElementById('new_messenger_channel');
    if(channel) fd.append('channel_id', channel.value);
    
    let active = document.getElementById('new_messenger_active');
    if(active) fd.append('is_active', active.checked ? 1 : 0);

    fetch(ajaxurl, {method:'POST', body:fd}).then(r=>r.json()).then(res=>{
        setBtnLoading(btn, false);
        if(res.success) {
            showSaved('messenger_saved');
            setTimeout(() => location.reload(), 1000);
        }
        else showToast(res.data ? res.data.message : 'خطا', 'error');
    }).catch(()=>setBtnLoading(btn, false));
};
`;

core = core.replace(/window\.addMessenger = function\(\) \{[\s\S]*?(?=window\.saveEditMessenger)/, fixedMessenger);
fs.writeFileSync('assets/js/portal-core.js', core);
