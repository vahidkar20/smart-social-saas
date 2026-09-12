const fs = require('fs');
let core = fs.readFileSync('assets/js/portal-core.js', 'utf8');

let newCode = `
window.addManualMediaUrl = function() {
    let input = document.getElementById('manual_media_url_input');
    let list = document.getElementById('manual_media_url_list');
    let preview = document.getElementById('manual_media_url_preview');
    if(!input || !input.value.trim()) return;
    
    let url = input.value.trim();
    let hidden = document.getElementById('manual_media_url_hidden');
    if(!hidden) {
        hidden = document.createElement('input');
        hidden.type = 'hidden';
        hidden.id = 'manual_media_url_hidden';
        document.getElementById('manual_media_url_input').parentNode.appendChild(hidden);
    }
    
    let currentUrls = hidden.value ? hidden.value.split(',') : [];
    currentUrls.push(url);
    hidden.value = currentUrls.join(',');
    
    let item = document.createElement('div');
    item.style = 'display:flex; justify-content:space-between; align-items:center; background:var(--bg-alt); padding:6px 10px; border-radius:6px; margin-bottom:4px; font-size:0.85rem;';
    item.innerHTML = '<span>' + url + '</span> <button type="button" class="ssp-btn-danger" style="padding:2px 6px; font-size:0.75rem;" onclick="this.parentNode.remove();">حذف</button>';
    list.appendChild(item);
    
    input.value = '';
    showToast('لینک رسانه اضافه شد', 'success');
};

window.pgResetForm = function() {
    if(typeof window.pgResetPostForm === 'function') window.pgResetPostForm();
    else showToast('در حال توسعه', 'info');
};
`;

core = core + "\n" + newCode;
fs.writeFileSync('assets/js/portal-core.js', core);
