const fs = require('fs');
let js = fs.readFileSync('assets/js/portal-core.js', 'utf8');

let target = `    if(e.target && e.target.classList.contains('btn-edit-messenger')) {
        let id = e.target.getAttribute('data-id');
        let m = document.getElementById('modal_edit_messenger');
        if(m) {
            document.getElementById('edit_messenger_id').value = id;
            m.style.display = 'flex';
        }
    }`;

let replacement = `    let editMessengerTarget = e.target.closest ? e.target.closest('.btn-edit-messenger') : (e.target.classList.contains('btn-edit-messenger') ? e.target : null);
    if(editMessengerTarget) {
        let id = editMessengerTarget.getAttribute('data-id');
        let m = document.getElementById('modal_edit_messenger');
        if(m) {
            document.getElementById('edit_messenger_id').value = id;
            if(window.messengerData && Array.isArray(window.messengerData)) {
                let messenger = window.messengerData.find(mItem => mItem.id == id);
                if(messenger) {
                    if(document.getElementById('edit_messenger_name')) document.getElementById('edit_messenger_name').value = messenger.name || '';
                    if(document.getElementById('edit_messenger_platform')) document.getElementById('edit_messenger_platform').value = messenger.platform || '';
                    if(document.getElementById('edit_messenger_token')) document.getElementById('edit_messenger_token').value = messenger.token || '';
                    if(document.getElementById('edit_messenger_channel')) document.getElementById('edit_messenger_channel').value = messenger.channel_id || '';
                }
            }
            m.style.display = 'flex';
        }
    }`;

js = js.replace(target, replacement);
fs.writeFileSync('assets/js/portal-core.js', js);
console.log('patched messenger edit click');
