const fs = require('fs');
let core = fs.readFileSync('assets/js/portal-core.js', 'utf8');

// We append the real implementations and remove them from the stub list
let realImplementations = `
window.addSchedule = function() {
    let btn = document.getElementById('add_schedule_btn');
    if(!btn) return;
    
    let title = document.getElementById('schedule_title').value;
    let message = document.getElementById('schedule_message').value;
    let datetime = document.getElementById('sch_schedule_datetime').value;
    let recurring = document.getElementById('schedule_recurring').value;
    
    if(!title || !message) { showToast('عنوان و متن الزامی است', 'error'); return; }
    // If datetime is empty, we fallback to basically some format if not provided, but it's required for a schedule
    if(!datetime) { showToast('تاریخ الزامی است', 'error'); return; }

    setBtnLoading(btn, true);
    let fd = new FormData();
    fd.append('action', 'ssp_add_schedule');
    fd.append('security', nonce);
    fd.append('title', title);
    fd.append('message', message);
    fd.append('scheduled_at', datetime);
    if(recurring) fd.append('recurring', recurring);

    fetch(ajaxurl, {method: 'POST', body: fd})
        .then(r => r.json())
        .then(res => {
            setBtnLoading(btn, false);
            if (res.success) {
                showSaved('schedule_saved');
                setTimeout(() => location.reload(), 1000);
            } else {
                showToast(res.data ? res.data.message : 'خطا', 'error');
            }
        });
};

window.cancelSchedule = function(id) {
    if(!confirm('آیا از لغو این زمان‌بندی مطمئن هستید؟')) return;
    let fd = new FormData();
    fd.append('action', 'ssp_cancel_schedule');
    fd.append('security', nonce);
    fd.append('schedule_id', id);

    fetch(ajaxurl, {method: 'POST', body: fd})
        .then(r => r.json())
        .then(res => {
            if (res.success) {
                showToast('لغو شد', 'success');
                setTimeout(() => location.reload(), 1000);
            } else {
                showToast(res.data ? res.data.message : 'خطا', 'error');
            }
        });
};

window.schOpenJalaliPicker = function() {
    // Just a placeholder for now since there's no actual JS date picker included
    let raw = prompt('Enter date in YYYY-MM-DD HH:MM format (e.g. 2026-09-10 15:30):', '');
    if(raw) {
        document.getElementById('sch_schedule_datetime').value = raw;
        document.getElementById('sch_schedule_date_display').value = raw;
    }
};

// Also attach listener for delete schedule buttons
document.addEventListener('click', function(e) {
    if(e.target && e.target.classList.contains('btn-delete-schedule')) {
        let id = e.target.getAttribute('data-id');
        if(!confirm('حذف شود؟')) return;
        let fd = new FormData();
        fd.append('action', 'ssp_delete_schedule');
        fd.append('security', nonce);
        fd.append('schedule_id', id);

        fetch(ajaxurl, {method: 'POST', body: fd})
            .then(r => r.json())
            .then(res => {
                if (res.success) {
                    showToast('حذف شد', 'success');
                    setTimeout(() => location.reload(), 1000);
                } else {
                    showToast(res.data ? res.data.message : 'خطا', 'error');
                }
            });
    }
});
`;

core += "\n" + realImplementations;

// also remove them from missingFns array so they don't get double registered as stubs
core = core.replace("'cancelSchedule', ", "");
core = core.replace("'addSchedule', ", "");
core = core.replace("'schOpenJalaliPicker', ", "");

fs.writeFileSync('assets/js/portal-core.js', core);
