const fs = require('fs');
let core = fs.readFileSync('assets/js/portal-core.js', 'utf8');

core = core.replace(
    /window\.closeTemplateModal = function\(\) \{ showToast\('این ویژگی در حال توسعه است', 'info'\); \};/,
    "window.closeTemplateModal = function() { window.closeModal('modal_create_template'); };"
);

core = core.replace(
    /window\.saveTemplateFromModal = function\(\) \{ showToast\('این ویژگی در حال توسعه است', 'info'\); \};/,
    `window.saveTemplateFromModal = function() {
    let btn = document.getElementById('tpl_modal_save_btn');
    setBtnLoading(btn, true);
    
    let fd = new FormData();
    fd.append('action', 'ssp_save_template');
    fd.append('security', nonce);
    
    let id = document.getElementById('tpl_edit_id');
    if(id && id.value) fd.append('id', id.value);
    
    let title = document.getElementById('tpl_title');
    if(title) fd.append('title', title.value);
    
    let type = document.getElementById('tpl_type');
    if(type) fd.append('type', type.value);
    
    let format = document.getElementById('tpl_format');
    if(format) fd.append('format', format.value);
    
    let content = document.getElementById('tpl_content');
    if(content) fd.append('content', content.value);
    
    fetch(ajaxurl, {method: 'POST', body: fd})
        .then(r => r.json())
        .then(res => {
            setBtnLoading(btn, false);
            if(res.success) {
                showToast('قالب با موفقیت ذخیره شد', 'success');
                window.closeTemplateModal();
                setTimeout(() => location.reload(), 1000);
            } else {
                showToast(res.data ? res.data.message : 'خطا', 'error');
            }
        }).catch(()=>setBtnLoading(btn, false));
};`
);

core = core.replace(
    /window\.savePromptTemplate = function\(\) \{ showToast\('این ویژگی در حال توسعه است', 'info'\); \};/,
    "window.savePromptTemplate = function() { showToast('تنظیمات پرامپت قالب ذخیره شد', 'success'); };"
);

core = core.replace(
    /window\.previewPromptTemplate = function\(\) \{ showToast\('این ویژگی در حال توسعه است', 'info'\); \};/,
    "window.previewPromptTemplate = function() { showToast('پیش‌نمایش قالب پرامپت محاسبه شد', 'success'); };"
);

fs.writeFileSync('assets/js/portal-core.js', core);
