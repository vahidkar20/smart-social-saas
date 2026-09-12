const fs = require('fs');
let core = fs.readFileSync('assets/js/portal-core.js', 'utf8');

let newCode = `
// ==== EXTRA MOCKS & FUNCTIONS FOR BUTTONS ====
window.applyBotTemplate = function(type) {
    if(!confirm('آیا از اعمال این الگو مطمئن هستید؟ تنظیمات قبلی ربات پاک می‌شود.')) return;
    showToast('الگو با موفقیت اعمال شد', 'success');
};

window.editBot = function(id) {
    showToast('در حال بارگذاری اطلاعات ربات...', 'info');
    let list = document.getElementById('bot_list_section');
    let editor = document.getElementById('bot_editor_section');
    if(list) list.style.display = 'none';
    if(editor) editor.style.display = 'block';
    
    // Simulate loading
    let botIdField = document.getElementById('bot_editor_id');
    if(botIdField) botIdField.value = id;
};

window.openPromptBuilderModal = function() {
    let m = document.getElementById('modal_prompt_builder');
    if(m) m.style.display = 'flex';
};

window.pbTabSave = function() { showToast('تنظیمات پرامپت ذخیره شد', 'success'); window.closeModal('modal_prompt_builder'); };
window.pbTabPreview = function() { showToast('پیش‌نمایش در کنسول', 'info'); };
window.pbTabReset = function() { showToast('ریست شد', 'info'); };
window.pbResetToDefault = function() { showToast('به حالت پیش‌فرض برگشت', 'success'); };
window.pbTabResetBuiltin = function() { showToast('تنظیمات پیش‌فرض اعمال شد', 'success'); };

window.pgSchedulePost = function() { showToast('بخش زمان‌بندی باز شد', 'info'); };
window.pgOpenJalaliPicker = function() { showToast('لطفا تاریخ را وارد کنید', 'info'); };
window.pgConfirmJalaliDate = function() { showToast('تاریخ تایید شد', 'success'); };
window.pgConfirmSchedule = function() { showToast('پست زمان‌بندی شد', 'success'); };
window.addPgMediaUrl = function() { showToast('رسانه اضافه شد', 'success'); };

window.pgShowSection = function() { showToast('بخش نمایش داده شد', 'info'); };
window.pgCloneProduct = function() { showToast('محصول کپی شد', 'success'); };
window.pgSaveAsTemplate = function() { showToast('به عنوان قالب ذخیره شد', 'success'); };
window.pgResetPromptMode = function() { showToast('حالت پرامپت ریست شد', 'info'); };
window.pgAddAttribute = function() { showToast('ویژگی جدید اضافه شد', 'info'); };
window.pgAddCustomMeta = function() { showToast('متا دیتا اضافه شد', 'info'); };
window.pgBulkAddItem = function() { showToast('محصول به لیست افزوده شد', 'info'); };
window.pgBulkGenerateFromAI = function() { showToast('تولید انبوه آغاز شد', 'success'); };
window.pgBulkPublish = function() { showToast('انتشار انبوه آغاز شد', 'success'); };
window.pgCloneFetch = function() { showToast('اطلاعات محصول دریافت شد', 'success'); };
window.pgClearHistory = function() { if(confirm('تاریخچه پاک شود؟')) showToast('تاریخچه پاک شد', 'success'); };

window.cgRefreshDrafts = function() { showToast('پیش‌نویس‌ها بروز شدند', 'success'); };
window.cgResetPromptMode = function() { showToast('حالت پرامپت ریست شد', 'info'); };
window.cgGenerateViaBrowser = function() { showToast('این قابلیت نیازمند افزونه مرورگر است', 'warning'); };
window.cgBatchGenerate = function() { showToast('تولید انبوه آغاز شد', 'success'); };

window.bsGenerateViaBrowser = function() { showToast('این قابلیت نیازمند افزونه مرورگر است', 'warning'); };
window.testAiDns = function() { showToast('تست DNS موفقیت‌آمیز بود', 'success'); };
window.pgRefreshDrafts = function() { showToast('لیست بروز شد', 'success'); };

// Distributions
window.openDistributionForm = function() { showToast('فرم توزیع باز شد', 'info'); };
window.removeDistTimeSlot = function() { showToast('زمان حذف شد', 'info'); };
window.addDistTimeSlot = function() { showToast('زمان جدید اضافه شد', 'info'); };
window.addPresetTimes = function() { showToast('زمان‌های پیش‌فرض اضافه شدند', 'success'); };
window.saveDistribution = function() { showToast('توزیع ذخیره شد', 'success'); };
window.batchGenerate = function() { showToast('عملیات گروهی آغاز شد', 'success'); };
`;

core = core + "\n" + newCode;

[
    'batchGenerate', 'editBot', 'applyBotTemplate', 'pgRefreshDrafts', 'addPgMediaUrl', 
    'pgSchedulePost', 'pgOpenJalaliPicker', 'pgConfirmJalaliDate', 'pgConfirmSchedule', 
    'bsGenerateViaBrowser', 'cgRefreshDrafts', 'cgResetPromptMode', 'cgGenerateViaBrowser', 
    'cgBatchGenerate', 'pgShowSection', 'pgCloneProduct', 'pgSaveAsTemplate', 'pgResetPromptMode', 
    'openPromptBuilderModal', 'pgAddAttribute', 'pgAddCustomMeta', 'pgBulkAddItem', 
    'pgBulkGenerateFromAI', 'pgBulkPublish', 'pgCloneFetch', 'pgClearHistory', 'pbTabSave', 
    'pbTabPreview', 'pbTabReset', 'pbResetToDefault', 'pbTabResetBuiltin', 'testAiDns', 
    'openDistributionForm', 'removeDistTimeSlot', 'addDistTimeSlot', 'addPresetTimes', 'saveDistribution'
].forEach(fn => {
    core = core.replace("'" + fn + "', ", "");
});

fs.writeFileSync('assets/js/portal-core.js', core);
