const fs = require('fs');
let code = fs.readFileSync('assets/js/portal-core.js', 'utf8');

// The original script has editBot defined twice. We need to remove them and put a proper one.
const newEditBot = `window.editBot = function(id) {
    let list = document.getElementById('bot_list_section');
    let editor = document.getElementById('bot_editor_section');
    if(list) list.style.display = 'none';
    if(editor) editor.style.display = 'block';
    
    // Clear first
    if(document.getElementById('bot_editor_id')) document.getElementById('bot_editor_id').value = id;
    if(document.getElementById('bot_name')) document.getElementById('bot_name').value = 'در حال بارگذاری...';
    if(document.getElementById('bot_token')) document.getElementById('bot_token').value = '';

    let fd = new FormData();
    fd.append('action', 'ssp_get_bot_config');
    fd.append('security', nonce);
    fd.append('bot_id', id);

    fetch(ajaxurl, {method: 'POST', body: fd})
        .then(r => r.json())
        .then(res => {
            if(res.success && res.data.config) {
                let conf = res.data.config;
                if(document.getElementById('bot_name')) document.getElementById('bot_name').value = conf.name || '';
                if(document.getElementById('bot_platform')) document.getElementById('bot_platform').value = conf.platform || 'telegram';
                if(document.getElementById('bot_token')) document.getElementById('bot_token').value = conf.token || '';
                if(document.getElementById('bot_active')) document.getElementById('bot_active').checked = conf.is_active == 1;
                
                // Also trigger loading commands, buttons if they exist in UI...
                // (Usually handled dynamically if we have those methods)
            } else {
                showToast('خطا در بارگذاری اطلاعات', 'error');
                if(list) list.style.display = 'block';
                if(editor) editor.style.display = 'none';
            }
        }).catch(() => {
            showToast('خطا در ارتباط', 'error');
            if(list) list.style.display = 'block';
            if(editor) editor.style.display = 'none';
        });
};
`;

code = code.replace(/window\.editBot = function\(id\) \{[\s\S]*?(?=window\.showBotList = function\(\))/g, "");
code = code.replace(/window\.editBot = function\(id\) \{[\s\S]*?(?=window\.openPromptBuilderModal = function\(\))/g, newEditBot);

fs.writeFileSync('assets/js/portal-core.js', code);
console.log('Fixed editBot stub');
